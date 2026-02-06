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

// =====================
// ★重要：APIレスポンスをキャッシュさせない（古いlatest_noteが返る対策）
// =====================
function nm_send_no_cache_headers(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');
}

// =====================
// pretty パラメータ解釈（仕様変更）
// =====================
function nm_param_pretty_mode(array $params): string
{
    if (!array_key_exists('pretty', $params)) return '2'; // デフォルト pretty=2 相当
    $v = trim((string)$params['pretty']);
    if ($v === '') return '2';

    $lv = strtolower($v);
    if ($v === '2') return '2';
    if ($v === '1' || $lv === 'true') return '1';
    if ($v === '0' || $lv === 'false') return '0';

    return '2';
}

// =====================
// JSONレスポンス
// =====================
function respond_json(array $payload, int $statusCode = 200, string $prettyMode = '0'): void
{
    nm_send_no_cache_headers();
    http_response_code($statusCode);

    $flags = JSON_UNESCAPED_UNICODE;
    if ($prettyMode === '1') $flags |= JSON_PRETTY_PRINT;

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, $flags);
    exit;
}

// =====================
// shared lock 付きでファイルを読む（保存と読みが被る事故を減らす）
// =====================
function nm_read_file_with_lock(string $path): string
{
    $fp = @fopen($path, 'rb');
    if (!$fp) return '';

    // shared lock（読取り）
    @flock($fp, LOCK_SH);
    $data = stream_get_contents($fp);
    @flock($fp, LOCK_UN);
    @fclose($fp);

    return is_string($data) ? $data : '';
}

// =====================
// pretty=2 用：HTMLっぽいcontentを「見た目の改行」に寄せたプレーンテキストへ
// =====================
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

    // <div><br></div> は「改行1つ」にする
    $text = preg_replace(
        '/<div\b[^>]*>\s*<br\s*\/?>\s*<\/div>/i',
        "\n",
        $text
    );

    // <br> は改行（直後の空白/改行も吸収して二重改行を防ぐ）
    $text = preg_replace('/<br\s*\/?>[ \t]*\r?\n?/i', "\n", $text);

    // <div> は「次の行の開始」扱い：改行へ
    $text = preg_replace('/<div\b[^>]*>/i', "\n", $text);

    // 閉じdivは消す（改行にしない）
    $text = preg_replace('/<\/div>/i', '', $text);

    // 最低限の他タグも保険で
    $text = preg_replace('/<p\b[^>]*>/i', "\n", $text);
    $text = preg_replace('/<\/p>/i', '', $text);

    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

    // 改行統一
    $text = preg_replace("/\r\n|\r|\n/", "\n", $text);

    // 先頭の \n を1つだけ削る
    $text = preg_replace("/^\n/", "", $text);

    // 末尾が empty div 由来なら「改行1つ」に寄せる
    if ($hadTrailingEmptyDiv) {
        $text = preg_replace("/\n+$/", "\n", $text);
    }

    // trim() はしない（末尾改行は意味がある）
    return $text;
}

// =====================
// 0. 設定読み込み（config/config.api.php）
// =====================
$configFile = dirname(__DIR__) . '/config/config.api.php';
if (!file_exists($configFile)) {
    respond_json(['status' => 'error', 'message' => 'config.api.php missing'], 500, '0');
}

$cfg = require $configFile;
if (!is_array($cfg)) $cfg = [];

$EXPECTED_TOKEN = (string)($cfg['EXPECTED_TOKEN'] ?? '');
$notemodFile    = (string)($cfg['DATA_JSON'] ?? '');

if ($EXPECTED_TOKEN === '' || $notemodFile === '') {
    respond_json(['status' => 'error', 'message' => 'Server not configured (EXPECTED_TOKEN / DATA_JSON)'], 500, '0');
}

// =====================
// 1. パラメータ正規化（GET/POST/JSON）
// =====================
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

$params = $getLower;
$params = $postLower + $params;
$params = $jsonLower + $params;

$prettyMode = nm_param_pretty_mode($params);

// =====================
// 2. トークンチェック
// =====================
$token = (string)($params['token'] ?? '');
if (!hash_equals($EXPECTED_TOKEN, $token)) {
    respond_json(['status' => 'error', 'message' => 'Forbidden'], 403, $prettyMode === '1' ? '1' : '0');
}

// =====================
// 3. action
// =====================
$action = (string)($params['action'] ?? 'list_categories');

// =====================
// 4. data.json を読み込む（shared lock）
// =====================
if (!file_exists($notemodFile)) {
    respond_json(['status' => 'error', 'message' => 'data.json not found'], 500, $prettyMode === '1' ? '1' : '0');
}
if (!is_readable($notemodFile)) {
    respond_json(['status' => 'error', 'message' => 'data.json not readable'], 500, $prettyMode === '1' ? '1' : '0');
}

$json = nm_read_file_with_lock($notemodFile);
if ($json === '') {
    // lock読みに失敗した場合の保険
    $json = (string)@file_get_contents($notemodFile);
}

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
// 5. Logs カテゴリーID
// =====================
$logsCategoryId = null;
foreach ($categoriesArr as $cat) {
    if (isset($cat['name']) && $cat['name'] === 'Logs') {
        $logsCategoryId = $cat['id'] ?? null;
        break;
    }
}

// =====================
// 6. action
// =====================
switch ($action) {

    case 'list_categories':
        respond_json([
            'status'     => 'ok',
            'count'      => count($categoriesArr),
            'categories' => $categoriesArr,
        ], 200, $prettyMode === '1' ? '1' : '0');

    case 'list_notes': {
        $categoryName  = trim((string)($params['category'] ?? ''));
        $categoryIdStr = trim((string)($params['category_id'] ?? ''));
        $limitParam    = trim((string)($params['limit'] ?? ''));
        $summaryParam  = trim((string)($params['summary'] ?? ''));

        $filterCategoryId = null;

        if ($categoryIdStr !== '' && ctype_digit($categoryIdStr)) {
            $filterCategoryId = (int)$categoryIdStr;
        } elseif ($categoryName !== '') {
            foreach ($categoriesArr as $cat) {
                if (($cat['name'] ?? '') === $categoryName) {
                    $filterCategoryId = $cat['id'] ?? null;
                    break;
                }
            }
            if ($filterCategoryId === null) {
                respond_json(['status' => 'ok', 'count' => 0, 'notes' => [], 'message' => 'category not found'], 200, $prettyMode === '1' ? '1' : '0');
            }
        }

        $limit = null;
        if ($limitParam !== '') {
            $limitVal = (int)$limitParam;
            if ($limitVal > 0) $limit = $limitVal;
        }

        $filtered = [];
        foreach ($notesArr as $note) {
            if ($filterCategoryId !== null) {
                if (
                    !isset($note['categories']) ||
                    !is_array($note['categories']) ||
                    !in_array($filterCategoryId, $note['categories'], true)
                ) continue;
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
                    $note['preview'] = function_exists('mb_substr') ? mb_substr($plain, 0, 80, 'UTF-8') : substr($plain, 0, 80);
                }
            }
            unset($note);
        }

        respond_json(['status' => 'ok', 'count' => count($filtered), 'notes' => $filtered], 200, $prettyMode === '1' ? '1' : '0');
    }

    case 'latest_note': {
        $categoryName  = trim((string)($params['category'] ?? ''));
        $categoryIdStr = trim((string)($params['category_id'] ?? ''));

        $filterCategoryId = null;

        if ($categoryIdStr !== '' && ctype_digit($categoryIdStr)) {
            $filterCategoryId = (int)$categoryIdStr;
        } elseif ($categoryName !== '') {
            foreach ($categoriesArr as $cat) {
                if (($cat['name'] ?? '') === $categoryName) {
                    $filterCategoryId = $cat['id'] ?? null;
                    break;
                }
            }
            if ($filterCategoryId === null) {
                // pretty=2デフォルトでも、エラーはJSONで返す
                respond_json(['status' => 'ok', 'content' => null, 'message' => 'category not found'], 200, $prettyMode === '1' ? '1' : '0');
            }
        }

        // Logsカテゴリが指定されてたら対象外
        if ($logsCategoryId !== null && $filterCategoryId !== null && $filterCategoryId === $logsCategoryId) {
            respond_json(['status' => 'ok', 'content' => null, 'message' => 'Logs category is excluded'], 200, $prettyMode === '1' ? '1' : '0');
        }

        // ★高速化：ソートせず、1パスで最新を探す
        $bestNote = null;
        $bestTime = '';

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

            // カテゴリ指定がある場合
            if ($filterCategoryId !== null) {
                if (
                    !isset($note['categories']) ||
                    !is_array($note['categories']) ||
                    !in_array($filterCategoryId, $note['categories'], true)
                ) {
                    continue;
                }
            }

            $t = (string)($note['updatedAt'] ?? $note['createdAt'] ?? '');
            if ($t === '') continue;

            // ISO8601は基本的に文字列比較でOK（同フォーマット前提）
            if ($bestTime === '' || strcmp($t, $bestTime) > 0) {
                $bestTime = $t;
                $bestNote = $note;
            }
        }

        if ($bestNote === null) {
            respond_json(['status' => 'ok', 'content' => null, 'message' => 'no notes found'], 200, $prettyMode === '1' ? '1' : '0');
        }

        $latestContent = (string)($bestNote['content'] ?? '');

        if (function_exists('mb_convert_encoding')) {
            $latestContent = mb_convert_encoding($latestContent, 'UTF-8', 'UTF-8, SJIS-win, EUC-JP, JIS, ISO-2022-JP, ASCII');
        }

        // デフォルト（pretty未指定）はここに入る
        if ($prettyMode === '2') {
            nm_send_no_cache_headers();
            $plain = content_to_plain_text($latestContent);
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(200);
            echo $plain;
            exit;
        }

        respond_json(['status' => 'ok', 'content' => $latestContent], 200, $prettyMode === '1' ? '1' : '0');
    }

    case 'get_note': {
        $categoryName = trim((string)($params['category'] ?? ''));
        $title        = trim((string)($params['title'] ?? ''));

        if ($categoryName === '' || $title === '') {
            respond_json(['status' => 'error', 'message' => 'category and title are required'], 400, $prettyMode === '1' ? '1' : '0');
        }

        $categoryId = null;
        foreach ($categoriesArr as $cat) {
            if (($cat['name'] ?? '') === $categoryName) {
                $categoryId = $cat['id'] ?? null;
                break;
            }
        }

        if ($categoryId === null) {
            respond_json(['status' => 'ok', 'note' => null, 'message' => 'category not found'], 200, $prettyMode === '1' ? '1' : '0');
        }

        $found = null;
        foreach ($notesArr as $note) {
            if ((string)($note['title'] ?? '') !== $title) continue;
            if (
                !isset($note['categories']) ||
                !is_array($note['categories']) ||
                !in_array($categoryId, $note['categories'], true)
            ) continue;

            $found = $note;
            break;
        }

        if ($found === null) {
            respond_json(['status' => 'ok', 'note' => null, 'message' => 'note not found'], 200, $prettyMode === '1' ? '1' : '0');
        }

        $content = (string)($found['content'] ?? '');
        if (function_exists('mb_convert_encoding')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8, SJIS-win, EUC-JP, JIS, ISO-2022-JP, ASCII');
        }

        if ($prettyMode === '2') {
            nm_send_no_cache_headers();
            $plain = content_to_plain_text($content);
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(200);
            echo $plain;
            exit;
        }

        $found['content'] = $content;
        respond_json(['status' => 'ok', 'note' => $found], 200, $prettyMode === '1' ? '1' : '0');
    }

    default:
        respond_json(['status' => 'error', 'message' => 'unknown action'], 400, $prettyMode === '1' ? '1' : '0');
}