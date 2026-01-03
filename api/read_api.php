<?php
// read_api.php
// Notemod の categories / notes を読み取るための簡易 API
//
// 機能：
//   action=list_categories
//   action=list_notes
//   action=latest_note   → Logs カテゴリーは常に除外
//   action=get_note      → category名 + title(ノート名) で1件取得
//
// pretty:
//   pretty=1 : JSONを整形（pretty print）
//   pretty=2 : （latest_note / get_note のみ）JSONではなく本文だけ（改行を見た目一致）で返す
//
// 入力：
//   GET / POST(form) / POST(JSON) を受け付ける
//   キーは小文字正規化するので Category / category 等の揺れを吸収

require_once __DIR__ . '/../logger.php';

// =====================
// タイムゾーン設定（config/config.php から読む）
// ※logger.php 側でも設定しているが、単体動作の保険としてここでも読む
// =====================
$tz = 'Pacific/Auckland';
$cfgCommonFile = dirname(__DIR__) . '/config/config.php';
if (file_exists($cfgCommonFile)) {
    $common = require $cfgCommonFile;
    if (is_array($common)) {
        $t = (string)($common['TIMEZONE'] ?? $common['timezone'] ?? '');
        if ($t !== '') $tz = $t;
    }
}
date_default_timezone_set($tz);

header('Content-Type: application/json; charset=utf-8');

// 共通レスポンス関数（pretty対応）
function respond_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);

    $flags  = JSON_UNESCAPED_UNICODE;
    $pretty = $_GET['pretty'] ?? $_POST['pretty'] ?? '';
    if ($pretty === '1' || strtolower($pretty) === 'true') {
        $flags |= JSON_PRETTY_PRINT;
    }

    echo json_encode($payload, $flags);
    exit;
}

// latest_note / get_note の pretty=2 用：HTMLっぽいcontentを「見た目の改行」に寄せたプレーンテキストへ
function content_to_plain_text(string $html): string
{
    // 1) <br> を改行へ
    $text = preg_replace('/<br\s*\/?>/i', "\n", $html);

    // 2) いくつかの閉じタグも改行扱い（必要最低限）
    $text = preg_replace('/<\/(p|div|li|h[1-6])>/i', "\n", $text);

    // 3) タグを除去
    $text = strip_tags($text);

    // 4) エンティティを戻す
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

    // 5) 改行コード統一
    $text = preg_replace("/\r\n|\r|\n/", "\n", $text);

    return trim($text);
}

// =====================
// 0. 設定読み込み（config/config.api.php）
// =====================

$configFile = dirname(__DIR__) . '/config/config.api.php';
if (!file_exists($configFile)) {
    respond_json(['status' => 'error', 'message' => 'config.api.php missing'], 500);
}

$cfg = require $configFile;
if (!is_array($cfg)) $cfg = [];

$EXPECTED_TOKEN = (string)($cfg['EXPECTED_TOKEN'] ?? '');
$notemodFile    = (string)($cfg['DATA_JSON'] ?? '');

if ($EXPECTED_TOKEN === '' || $notemodFile === '') {
    respond_json([
        'status'  => 'error',
        'message' => 'Server not configured (EXPECTED_TOKEN / DATA_JSON)',
    ], 500);
}

// =====================
// 1. パラメータ正規化（GET/POST/JSON）
//    - キーを小文字に統一して Category/category 等を吸収
// =====================

// JSONボディ対応（POST時のみ来る想定だが、読めるときは読む）
$jsonBody = [];
$ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $jsonBody = $decoded;
}

$getLower  = array_change_key_case($_GET, CASE_LOWER);
$postLower = array_change_key_case($_POST, CASE_LOWER);
$jsonLower = array_change_key_case($jsonBody, CASE_LOWER);

// 優先順位：JSON > POST > GET
$params = $getLower;
$params = $postLower + $params;
$params = $jsonLower + $params;

// =====================
// 2. トークンチェック
// =====================

$token = (string)($params['token'] ?? '');
if (!hash_equals($EXPECTED_TOKEN, $token)) {
    respond_json([
        'status'  => 'error',
        'message' => 'Forbidden',
    ], 403);
}

// =====================
// 3. action 取得
// =====================

$action = (string)($params['action'] ?? 'list_categories');

// pretty=2 は latest_note/get_note のみで使う
$prettyMode = (string)($params['pretty'] ?? '');

// =====================
// 4. data.json を読み込む
// =====================

if (!file_exists($notemodFile)) {
    respond_json(['status' => 'error', 'message' => 'data.json not found'], 500);
}
if (!is_readable($notemodFile)) {
    respond_json(['status' => 'error', 'message' => 'data.json not readable'], 500);
}

$json = file_get_contents($notemodFile);
$data = json_decode($json, true);
if (!is_array($data)) $data = [];

// Notemodの保存形式が「JSON文字列」でも「配列」でも対応
$categoriesVal = $data['categories'] ?? '[]';
$notesVal      = $data['notes'] ?? '[]';

$categoriesArr = is_string($categoriesVal) ? json_decode($categoriesVal, true) : $categoriesVal;
$notesArr      = is_string($notesVal) ? json_decode($notesVal, true) : $notesVal;

if (!is_array($categoriesArr)) $categoriesArr = [];
if (!is_array($notesArr)) $notesArr = [];

// =====================
// 5. Logs カテゴリーの ID を取得（あれば）
// =====================

$logsCategoryId = null;
foreach ($categoriesArr as $cat) {
    if (isset($cat['name']) && $cat['name'] === 'Logs') {
        $logsCategoryId = $cat['id'] ?? null;
        break;
    }
}

// =====================
// 6. action ごとの処理
// =====================

switch ($action) {

    // -----------------
    // カテゴリ一覧
    // -----------------
    case 'list_categories':
        respond_json([
            'status'     => 'ok',
            'count'      => count($categoriesArr),
            'categories' => $categoriesArr,
        ]);

    // -----------------
    // ノート一覧
    // -----------------
    case 'list_notes': {
        // パラメータ:
        //   category=INBOX
        //   category_id=1765...
        //   limit=10
        //   summary=1
        $categoryName  = trim((string)($params['category'] ?? ''));
        $categoryIdStr = trim((string)($params['category_id'] ?? ''));
        $limitParam    = trim((string)($params['limit'] ?? ''));
        $summaryParam  = trim((string)($params['summary'] ?? ''));

        $filterCategoryId = null;

        if ($categoryIdStr !== '' && ctype_digit($categoryIdStr)) {
            $filterCategoryId = (int)$categoryIdStr;
        } elseif ($categoryName !== '') {
            foreach ($categoriesArr as $cat) {
                if (isset($cat['name']) && $cat['name'] === $categoryName) {
                    $filterCategoryId = $cat['id'] ?? null;
                    break;
                }
            }
            if ($filterCategoryId === null) {
                respond_json([
                    'status'  => 'ok',
                    'count'   => 0,
                    'notes'   => [],
                    'message' => 'category not found',
                ]);
            }
        }

        $limit = null;
        if ($limitParam !== '') {
            $limitVal = (int)$limitParam;
            if ($limitVal > 0) $limit = $limitVal;
        }

        // list_notes は Logs を含めてOK（必要ならここも除外にできる）
        $filtered = [];
        foreach ($notesArr as $note) {
            if ($filterCategoryId !== null) {
                if (
                    !isset($note['categories']) ||
                    !is_array($note['categories']) ||
                    !in_array($filterCategoryId, $note['categories'], true)
                ) {
                    continue;
                }
            }
            $filtered[] = $note;
        }

        // 新しい順（updatedAt優先）
        usort($filtered, function ($a, $b) {
            $aTime = $a['updatedAt'] ?? $a['createdAt'] ?? '';
            $bTime = $b['updatedAt'] ?? $b['createdAt'] ?? '';
            return strcmp($bTime, $aTime);
        });

        if ($limit !== null && count($filtered) > $limit) {
            $filtered = array_slice($filtered, 0, $limit);
        }

        $summary = ($summaryParam === '1' || strtolower($summaryParam) === 'true');
        if ($summary) {
            foreach ($filtered as &$note) {
                if (isset($note['content'])) {
                    $plain           = strip_tags((string)$note['content']);
                    $note['preview'] = mb_substr($plain, 0, 80, 'UTF-8');
                    // unset($note['content']); // content を返したくない場合
                }
            }
            unset($note);
        }

        respond_json([
            'status' => 'ok',
            'count'  => count($filtered),
            'notes'  => $filtered,
        ]);
    }

    // -----------------
    // 最新ノート1件（Logs カテゴリは常に除外）
    // -----------------
    case 'latest_note': {
        $categoryName  = trim((string)($params['category'] ?? ''));
        $categoryIdStr = trim((string)($params['category_id'] ?? ''));

        $filterCategoryId = null;

        if ($categoryIdStr !== '' && ctype_digit($categoryIdStr)) {
            $filterCategoryId = (int)$categoryIdStr;
        } elseif ($categoryName !== '') {
            foreach ($categoriesArr as $cat) {
                if (isset($cat['name']) && $cat['name'] === $categoryName) {
                    $filterCategoryId = $cat['id'] ?? null;
                    break;
                }
            }
            if ($filterCategoryId === null) {
                respond_json([
                    'status'  => 'ok',
                    'content' => null,
                    'message' => 'category not found',
                ]);
            }
        }

        // Logsカテゴリが指定されてたら対象外
        if ($logsCategoryId !== null && $filterCategoryId !== null && $filterCategoryId === $logsCategoryId) {
            respond_json([
                'status'  => 'ok',
                'content' => null,
                'message' => 'Logs category is excluded',
            ]);
        }

        $filtered = [];
        foreach ($notesArr as $note) {
            // Logsカテゴリ所属は常に除外
            if (
                $logsCategoryId !== null &&
                isset($note['categories']) &&
                is_array($note['categories']) &&
                in_array($logsCategoryId, $note['categories'], true)
            ) {
                continue;
            }

            if ($filterCategoryId !== null) {
                if (
                    !isset($note['categories']) ||
                    !is_array($note['categories']) ||
                    !in_array($filterCategoryId, $note['categories'], true)
                ) {
                    continue;
                }
            }

            $filtered[] = $note;
        }

        if (empty($filtered)) {
            respond_json([
                'status'  => 'ok',
                'content' => null,
                'message' => 'no notes found',
            ]);
        }

        usort($filtered, function ($a, $b) {
            $aTime = $a['updatedAt'] ?? $a['createdAt'] ?? '';
            $bTime = $b['updatedAt'] ?? $b['createdAt'] ?? '';
            return strcmp($bTime, $aTime);
        });

        $latestContent = (string)($filtered[0]['content'] ?? '');

        // 文字化け保険（混在しててもUTF-8へ）
        if (function_exists('mb_convert_encoding')) {
            $latestContent = mb_convert_encoding(
                $latestContent,
                'UTF-8',
                'UTF-8, SJIS-win, EUC-JP, JIS, ISO-2022-JP, ASCII'
            );
        }

        if ($prettyMode === '2') {
            $plain = content_to_plain_text($latestContent);
            header_remove('Content-Type');
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(200);
            echo $plain;
            exit;
        }

        respond_json([
            'status'  => 'ok',
            'content' => $latestContent,
        ]);
    }

    // -----------------
    // category名 + title(ノート名) でノート1件取得
    // -----------------
    case 'get_note': {
        // 必須:
        //   category=Shopping
        //   title=xxx
        //
        // pretty=2 のときは本文だけ（改行見た目一致）で返す
        $categoryName = trim((string)($params['category'] ?? ''));
        $title        = trim((string)($params['title'] ?? ''));

        if ($categoryName === '' || $title === '') {
            respond_json([
                'status'  => 'error',
                'message' => 'category and title are required',
            ], 400);
        }

        // category名 → ID を探す
        $categoryId = null;
        foreach ($categoriesArr as $cat) {
            if (isset($cat['name']) && $cat['name'] === $categoryName) {
                $categoryId = $cat['id'] ?? null;
                break;
            }
        }

        if ($categoryId === null) {
            respond_json([
                'status'  => 'ok',
                'note'    => null,
                'message' => 'category not found',
            ]);
        }

        // ノート検索：title一致 かつ categories に categoryId が含まれる
        $found = null;
        foreach ($notesArr as $note) {
            if (!isset($note['title']) || (string)$note['title'] !== $title) {
                continue;
            }
            if (
                !isset($note['categories']) ||
                !is_array($note['categories']) ||
                !in_array($categoryId, $note['categories'], true)
            ) {
                continue;
            }
            $found = $note;
            break; // 同名が複数あるなら最初の1件（必要なら最新にする実装も可能）
        }

        if ($found === null) {
            respond_json([
                'status'  => 'ok',
                'note'    => null,
                'message' => 'note not found',
            ]);
        }

        $content = (string)($found['content'] ?? '');

        // 文字化け保険
        if (function_exists('mb_convert_encoding')) {
            $content = mb_convert_encoding(
                $content,
                'UTF-8',
                'UTF-8, SJIS-win, EUC-JP, JIS, ISO-2022-JP, ASCII'
            );
        }

        if ($prettyMode === '2') {
            $plain = content_to_plain_text($content);
            header_remove('Content-Type');
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(200);
            echo $plain;
            exit;
        }

        // JSONで返す場合は、found の content を補正したものに差し替え
        $found['content'] = $content;

        respond_json([
            'status' => 'ok',
            'note'   => $found,
        ]);
    }

    // -----------------
    // 不明な action
    // -----------------
    default:
        respond_json([
            'status'  => 'error',
            'message' => 'unknown action',
        ], 400);
}