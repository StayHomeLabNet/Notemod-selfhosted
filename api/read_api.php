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

require_once dirname(__DIR__) . '/auth_common.php';

$dirUser = '';
foreach (['dir_user', 'user', 'username'] as $key) {
    if (isset($_REQUEST[$key]) && (string)$_REQUEST[$key] !== '') {
        $dirUser = normalize_username((string)$_REQUEST[$key]);
        if ($dirUser !== '') {
            break;
        }
    }
}
if ($dirUser === '') {
    $dirUser = nm_get_current_dir_user();
}
$dirUser = function_exists('nm_get_current_dir_user') ? nm_get_current_dir_user() : '';
foreach (['dir_user','user','username'] as $nmKey) {
    if ($dirUser === '' && isset($_REQUEST[$nmKey])) {
        $dirUser = normalize_username((string)$_REQUEST[$nmKey]);
    }
}

require_once __DIR__ . '/../logger.php';

// =====================
// タイムゾーン設定（config/config.php から読む）
// =====================
$tz = 'Pacific/Auckland';
$cfgCommonFile = nm_config_path(isset($dirUser) ? $dirUser : null);
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
$configFile = nm_api_config_path(isset($dirUser) ? $dirUser : null);
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
// 追加：USERNAME（マルチユーザー想定・シングルでもOK）
// =====================
$USERNAME = 'default';
$authFile = nm_auth_config_path(isset($dirUser) ? $dirUser : null);
if (file_exists($authFile)) {
    $auth = require $authFile;
    if (is_array($auth) && isset($auth['USERNAME'])) {
        $USERNAME = (string)$auth['USERNAME'];
    } elseif (defined('USERNAME')) {
        $USERNAME = (string)USERNAME;
    }
}
// ディレクトリ名として安全な形に寄せる
$USERNAME = preg_replace('/[^a-zA-Z0-9_-]/', '_', $USERNAME);
if ($USERNAME === '' || $USERNAME === null) $USERNAME = 'default';

// notemod-data のルートを推定（DATA_JSON が /notemod-data/<user>/data.json の場合にも対応）
$dataJsonDir = realpath(dirname($notemodFile));
$notemodDataRoot = $dataJsonDir ?: dirname($notemodFile);

// 末尾が "/<user>" の場合はその1つ上をルート扱いにする
if ($dataJsonDir && basename($dataJsonDir) === $USERNAME) {
    $parent = realpath(dirname($dataJsonDir));
    if ($parent) $notemodDataRoot = $parent;
}

// ユーザーディレクトリ（存在しない場合でも参照できるようにパス組み立て）
$userDir = rtrim($notemodDataRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $USERNAME;

// =====================
// 追加：バイナリ応答ヘルパー
// =====================
function respond_binary_file(string $path, string $mime, ?string $downloadName = null): void
{
    if (!is_file($path) || !is_readable($path)) {
        respond_json(['status' => 'error', 'message' => 'file not found'], 404, '0');
    }

    // キャッシュ抑制
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    header('Content-Type: ' . $mime);

    if ($downloadName !== null && $downloadName !== '') {
        // Content-Disposition はファイル受信時に便利（iOSは必ずしも反映しないが、ブラウザ等で有効）
        $downloadName = preg_replace('/[\r\n]+/', ' ', $downloadName);
        $downloadName = str_replace(['"', '\\'], '_', $downloadName);
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    }

    $size = filesize($path);
    if ($size !== false) {
        header('Content-Length: ' . $size);
    }

    // 出力
    $fp = fopen($path, 'rb');
    if ($fp === false) {
        respond_json(['status' => 'error', 'message' => 'failed to open file'], 500, '0');
    }
    // 共有ロック（ベストエフォート）
    @flock($fp, LOCK_SH);
    while (!feof($fp)) {
        $buf = fread($fp, 8192);
        if ($buf === false) break;
        echo $buf;
    }
    @flock($fp, LOCK_UN);
    fclose($fp);
    exit;
}

function safe_basename(string $name): string
{
    // ディレクトリ要素を除去
    $name = basename($name);
    // 制御文字など除去
    $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);
    return $name;
}

// =====================
// 追加：時刻文字列/メタからUnix秒を得る（比較用）
// =====================
function nm_to_unix(?string $isoOrEmpty): int
{
    if ($isoOrEmpty === null) return 0;
    $s = trim($isoOrEmpty);
    if ($s === '') return 0;
    $t = strtotime($s);
    return ($t === false) ? 0 : (int)$t;
}

function nm_meta_time_unix(array $meta): int
{
    // created_at_unix を最優先、無ければ created_at を strtotime、無ければ 0
    if (isset($meta['created_at_unix']) && is_numeric($meta['created_at_unix'])) {
        return (int)$meta['created_at_unix'];
    }
    if (isset($meta['created_at'])) {
        $u = nm_to_unix((string)$meta['created_at']);
        if ($u > 0) return $u;
    }
    return 0;
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

    
    
    case 'latest_clip_type': {
        // 最新の「クリップ（note / image / file）」がどれかを返す
        // 判定基準：notemod-data/<user>/note_latest.json, image_latest.json, file_latest.json の created_at(_unix)
        // 優先順位（同時刻の場合）：image > file > note

        // 1) latest_note（テキスト）の時刻を計算（note_latest.json）
        $noteMeta = null;
        $noteUnix = 0;
        $noteMetaPath = $userDir . DIRECTORY_SEPARATOR . 'note_latest.json';
        if (is_file($noteMetaPath)) {
            $raw = nm_read_file_with_lock($noteMetaPath);
            if ($raw === '') { $raw = (string)@file_get_contents($noteMetaPath); }
            $tmp = json_decode($raw ?: '', true);
            if (is_array($tmp)) {
                $noteMeta = $tmp;
                $noteUnix = nm_meta_time_unix($noteMeta);
            }
        }

        // 2) latest_image の時刻を計算（image_latest.json）
        $imageMeta = null;
        $imageUnix = 0;
        $imageMetaPath = $userDir . DIRECTORY_SEPARATOR . 'image_latest.json';
        if (is_file($imageMetaPath)) {
            $raw = nm_read_file_with_lock($imageMetaPath);
            if ($raw === '') { $raw = (string)@file_get_contents($imageMetaPath); }
            $tmp = json_decode($raw ?: '', true);
            if (is_array($tmp)) {
                $imageMeta = $tmp;
                $imageUnix = nm_meta_time_unix($imageMeta);
            }
        }

        // 3) latest_file の時刻を計算（file_latest.json）
        $fileMeta = null;
        $fileUnix = 0;
        $fileMetaPath = $userDir . DIRECTORY_SEPARATOR . 'file_latest.json';
        if (is_file($fileMetaPath)) {
            $raw = nm_read_file_with_lock($fileMetaPath);
            if ($raw === '') { $raw = (string)@file_get_contents($fileMetaPath); }
            $tmp = json_decode($raw ?: '', true);
            if (is_array($tmp)) {
                $fileMeta = $tmp;
                $fileUnix = nm_meta_time_unix($fileMeta);
            }
        }

        // 4) もっとも新しいものを選ぶ（同時刻なら優先順位：image > file > note）
        $type = 'none';
        $latestUnix = 0;

        if ($noteUnix > 0) {
            $type = 'note';
            $latestUnix = $noteUnix;
        }

        if ($fileUnix > $latestUnix || ($fileUnix === $latestUnix && $fileUnix > 0 && $type !== 'image')) {
            $type = 'file';
            $latestUnix = $fileUnix;
        }

        if ($imageUnix > $latestUnix || ($imageUnix === $latestUnix && $imageUnix > 0)) {
            $type = 'image';
            $latestUnix = $imageUnix;
        }

        // 返却（メタは最小限）
        $payload = [
            'status' => 'ok',
            'type' => $type,
            'latest_unix' => $latestUnix,
        ];

        if ($type === 'note' && is_array($noteMeta)) {
            // note_latest.json の内容は api.php 側で決める（最低限 created_at / created_at_unix があればOK）
            $payload['note'] = [
                'created_at' => $noteMeta['created_at'] ?? null,
                'created_at_unix' => $noteMeta['created_at_unix'] ?? null,
            ];
        } elseif ($type === 'image' && is_array($imageMeta)) {
            $payload['image'] = [
                'image_id' => $imageMeta['image_id'] ?? null,
                'filename' => $imageMeta['filename'] ?? null,
                'mime' => $imageMeta['mime'] ?? null,
                'size' => $imageMeta['size'] ?? null,
                'created_at' => $imageMeta['created_at'] ?? null,
                'created_at_unix' => $imageMeta['created_at_unix'] ?? null,
                'sha256' => $imageMeta['sha256'] ?? null,
            ];
        } elseif ($type === 'file' && is_array($fileMeta)) {
            $payload['file'] = [
                'file_id' => $fileMeta['file_id'] ?? null,
                'filename' => $fileMeta['filename'] ?? null,
                'mime' => $fileMeta['mime'] ?? null,
                'size' => $fileMeta['size'] ?? null,
                'original_name' => $fileMeta['original_name'] ?? null,
                'created_at' => $fileMeta['created_at'] ?? null,
                'created_at_unix' => $fileMeta['created_at_unix'] ?? null,
                'sha256' => $fileMeta['sha256'] ?? null,
            ];
        }

        respond_json($payload, 200, $prettyMode === '1' ? '1' : '0');
    }

case 'latest_image': {
        // image_latest.json から最新画像を探して、そのままバイナリを返す
        $metaPath  = $userDir . DIRECTORY_SEPARATOR . 'image_latest.json';
        $imagesDir = $userDir . DIRECTORY_SEPARATOR . 'images';

        if (!is_file($metaPath)) {
            respond_json(['status' => 'ok', 'exists' => false, 'message' => 'no image'], 200, $prettyMode === '1' ? '1' : '0');
        }

        $metaRaw = nm_read_file_with_lock($metaPath);
        if ($metaRaw === '') { $metaRaw = (string)@file_get_contents($metaPath); }
        $meta = json_decode($metaRaw ?: '', true);
        if (!is_array($meta)) {
            respond_json(['status' => 'error', 'message' => 'invalid image_latest.json'], 500, $prettyMode === '1' ? '1' : '0');
        }

        $filename = safe_basename((string)($meta['filename'] ?? ''));
        $mime     = (string)($meta['mime'] ?? 'application/octet-stream');

        if ($filename === '' || !preg_match('/^[a-zA-Z0-9_-]+\.(png|jpg|jpeg|webp)$/i', $filename)) {
            respond_json(['status' => 'error', 'message' => 'invalid image filename'], 500, $prettyMode === '1' ? '1' : '0');
        }

        $baseReal = realpath($imagesDir);
        $fullReal = realpath($imagesDir . DIRECTORY_SEPARATOR . $filename);

        if (!$baseReal || !$fullReal || strpos($fullReal, $baseReal) !== 0) {
            respond_json(['status' => 'ok', 'exists' => false, 'message' => 'image file not found'], 200, $prettyMode === '1' ? '1' : '0');
        }

        // 画像は downloadName なし（貼り付け用途が多いので）
        respond_binary_file($fullReal, $mime, null);
    }

    case 'latest_file': {
        // file_latest.json から最新ファイルを探して、そのままバイナリを返す
        $metaPath  = $userDir . DIRECTORY_SEPARATOR . 'file_latest.json';
        $filesDir  = $userDir . DIRECTORY_SEPARATOR . 'files';

        if (!is_file($metaPath)) {
            respond_json(['status' => 'ok', 'exists' => false, 'message' => 'no file'], 200, $prettyMode === '1' ? '1' : '0');
        }

        $metaRaw = nm_read_file_with_lock($metaPath);
        if ($metaRaw === '') { $metaRaw = (string)@file_get_contents($metaPath); }
        $meta = json_decode($metaRaw ?: '', true);
        if (!is_array($meta)) {
            respond_json(['status' => 'error', 'message' => 'invalid file_latest.json'], 500, $prettyMode === '1' ? '1' : '0');
        }

        $filename = safe_basename((string)($meta['filename'] ?? ''));
        $mime     = (string)($meta['mime'] ?? 'application/octet-stream');
        $origName = safe_basename((string)($meta['original_name'] ?? ''));

        if ($filename === '' || !preg_match('/^[a-zA-Z0-9_-]+\.[a-zA-Z0-9]{1,10}$/', $filename)) {
            respond_json(['status' => 'error', 'message' => 'invalid file filename'], 500, $prettyMode === '1' ? '1' : '0');
        }

        $baseReal = realpath($filesDir);
        $fullReal = realpath($filesDir . DIRECTORY_SEPARATOR . $filename);

        if (!$baseReal || !$fullReal || strpos($fullReal, $baseReal) !== 0) {
            respond_json(['status' => 'ok', 'exists' => false, 'message' => 'file not found'], 200, $prettyMode === '1' ? '1' : '0');
        }

        // 元ファイル名が無い/不正なら、保存名を使う
        $downloadName = $origName !== '' ? $origName : $filename;
        respond_binary_file($fullReal, $mime, $downloadName);
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