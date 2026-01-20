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
// pretty（仕様変更）:
//   (指定なし) : デフォルトで「pretty=2」相当（latest_note / get_note は本文だけ text/plain）
//   pretty=1 または pretty=true  : 可読性重視のJSON（pretty print）
//   pretty=0 または pretty=false : 通常JSON（圧縮）
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
$cfgCommonFile = dirname(__DIR__) . '/config/config.php'; // api/ の1つ上 → config/
if (file_exists($cfgCommonFile)) {
    $common = require $cfgCommonFile;
    if (is_array($common)) {
        $t = (string)($common['TIMEZONE'] ?? $common['timezone'] ?? '');
        if ($t !== '') $tz = $t;
    }
}
date_default_timezone_set($tz);

// =====================
// pretty パラメータ解釈（仕様変更）
// - 未指定: '2' 扱い（本文だけ返すデフォルト）
// - 1/true : JSON pretty print
// - 0/false: JSON compact
// =====================
function nm_param_pretty_mode(array $params): string
{
    if (!array_key_exists('pretty', $params)) {
        return '2'; // ★デフォルトは pretty=2 相当
    }
    $v = trim((string)$params['pretty']);

    if ($v === '') return '2';
    $lv = strtolower($v);

    if ($v === '2') return '2';
    if ($v === '1' || $lv === 'true') return '1';
    if ($v === '0' || $lv === 'false') return '0';

    // 互換：想定外はデフォルト寄り（本文だけ）
    return '2';
}

// 共通レスポンス関数（pretty=1/0 対応）
function respond_json(array $payload, int $statusCode = 200, string $prettyMode = '0'): void
{
    http_response_code($statusCode);

    $flags = JSON_UNESCAPED_UNICODE;

    // pretty=1 / true のときだけ整形
    if ($prettyMode === '1') {
        $flags |= JSON_PRETTY_PRINT;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, $flags);
    exit;
}

// latest_note / get_note の pretty=2 用：HTMLっぽいcontentを「見た目の改行」に寄せたプレーンテキストへ
function content_to_plain_text(string $html): string
{
    $text = $html;

    // data.json 由来で <\/div> みたいな形が来る場合があるので正規化
    $text = str_replace('\\/', '/', $text);

    // 改行コード統一
    $text = preg_replace("/\r\n|\r/", "\n", $text);

    // 末尾が <div><br></div>（Notemodの末尾改行表現）か判定しておく
    $hadTrailingEmptyDiv = (bool)preg_match(
        '/<div\b[^>]*>\s*<br\s*\/?>\s*<\/div>\s*$/i',
        $text
    );

    // ★重要：<div><br></div> は「改行1つ」にする（2つにしない）
    $text = preg_replace(
        '/<div\b[^>]*>\s*<br\s*\/?>\s*<\/div>/i',
        "\n",
        $text
    );

    // <br> は改行（直後の空白/改行も一緒に吸収して二重改行を防ぐ）
    // ※ Windows側で <br>\n が混ざると \n\n になりがちなので吸収
    $text = preg_replace('/<br\s*\/?>[ \t]*\r?\n?/i', "\n", $text);

    // <div> は「次の行の開始」扱い：改行へ
    $text = preg_replace('/<div\b[^>]*>/i', "\n", $text);

    // 閉じdivは消す（改行にしない）
    $text = preg_replace('/<\/div>/i', '', $text);

    // 最低限の他タグも保険で
    $text = preg_replace('/<p\b[^>]*>/i', "\n", $text);
    $text = preg_replace('/<\/p>/i', '', $text);

    // タグ除去
    $text = strip_tags($text);

    // エンティティ戻し
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

    // 改行コード統一（再度）
    $text = preg_replace("/\r\n|\r|\n/", "\n", $text);

    // 先頭に \n が出がちなので1つだけ削る
    $text = preg_replace("/^\n/", "", $text);

    // 末尾が <div><br></div> 由来なら「改行1つ」に寄せる（\n\n になりがちなので）
    if ($hadTrailingEmptyDiv) {
        $text = preg_replace("/\n+$/", "\n", $text);
    }

    // trim() はしない（末尾改行は意味がある）
    return $text;
}

// =====================
// 0. 設定読み込み（config/config.api.php）
// =====================

$configFile = dirname(__DIR__) . '/config/config.api.php'; // api/ の1つ上 → config/
if (!file_exists($configFile)) {
    // ここでは params が未確定なので compact JSONで返す
    respond_json(['status' => 'error', 'message' => 'config.api.php missing'], 500, '0');
}

$cfg = require $configFile;
if (!is_array($cfg)) $cfg = [];

$EXPECTED_TOKEN = (string)($cfg['EXPECTED_TOKEN'] ?? '');
$notemodFile    = (string)($cfg['DATA_JSON'] ?? '');

if ($EXPECTED_TOKEN === '' || $notemodFile === '') {
    respond_json([
        'status'  => 'error',
        'message' => 'Server not configured (EXPECTED_TOKEN / DATA_JSON)',
    ], 500, '0');
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

// pretty mode（仕様変更）
$prettyMode = nm_param_pretty_mode($params);

// =====================
// 2. トークンチェック
// =====================

$token = (string)($params['token'] ?? '');
if (!hash_equals($EXPECTED_TOKEN, $token)) {
    respond_json([
        'status'  => 'error',
        'message' => 'Forbidden',
    ], 403, $prettyMode === '1' ? '1' : '0');
}

// =====================
// 3. action 取得
// =====================

$action = (string)($params['action'] ?? 'list_categories');

// =====================
// 4. data.json を読み込む
// =====================

if (!file_exists($notemodFile)) {
    respond_json(['status' => 'error', 'message' => 'data.json not found'], 500, $prettyMode === '1' ? '1' : '0');
}
if (!is_readable($notemodFile)) {
    respond_json(['status' => 'error', 'message' => 'data.json not readable'], 500, $prettyMode === '1' ? '1' : '0');
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
        ], 200, $prettyMode === '1' ? '1' : '0');

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
                ], 200, $prettyMode === '1' ? '1' : '0');
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
                }
            }
            unset($note);
        }

        respond_json([
            'status' => 'ok',
            'count'  => count($filtered),
            'notes'  => $filtered,
        ], 200, $prettyMode === '1' ? '1' : '0');
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
                // デフォルト(=2)でも、latest_note の「カテゴリなし」は JSONで返す
                respond_json([
                    'status'  => 'ok',
                    'content' => null,
                    'message' => 'category not found',
                ], 200, $prettyMode === '1' ? '1' : '0');
            }
        }

        // Logsカテゴリが指定されてたら対象外
        if ($logsCategoryId !== null && $filterCategoryId !== null && $filterCategoryId === $logsCategoryId) {
            respond_json([
                'status'  => 'ok',
                'content' => null,
                'message' => 'Logs category is excluded',
            ], 200, $prettyMode === '1' ? '1' : '0');
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
            ], 200, $prettyMode === '1' ? '1' : '0');
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

        // ★デフォルトは pretty=2 相当 → 本文だけ返す
        if ($prettyMode === '2') {
            $plain = content_to_plain_text($latestContent);
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(200);
            echo $plain;
            exit;
        }

        respond_json([
            'status'  => 'ok',
            'content' => $latestContent,
        ], 200, $prettyMode === '1' ? '1' : '0');
    }

    // -----------------
    // category名 + title(ノート名) でノート1件取得
    // -----------------
    case 'get_note': {
        $categoryName = trim((string)($params['category'] ?? ''));
        $title        = trim((string)($params['title'] ?? ''));

        if ($categoryName === '' || $title === '') {
            respond_json([
                'status'  => 'error',
                'message' => 'category and title are required',
            ], 400, $prettyMode === '1' ? '1' : '0');
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
            ], 200, $prettyMode === '1' ? '1' : '0');
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
            break;
        }

        if ($found === null) {
            respond_json([
                'status'  => 'ok',
                'note'    => null,
                'message' => 'note not found',
            ], 200, $prettyMode === '1' ? '1' : '0');
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

        // ★デフォルトは pretty=2 相当 → 本文だけ返す
        if ($prettyMode === '2') {
            $plain = content_to_plain_text($content);
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
        ], 200, $prettyMode === '1' ? '1' : '0');
    }

    // -----------------
    // 不明な action
    // -----------------
    default:
        respond_json([
            'status'  => 'error',
            'message' => 'unknown action',
        ], 400, $prettyMode === '1' ? '1' : '0');
}