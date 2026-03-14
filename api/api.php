<?php
// api.php
// 任意カテゴリにノートを追加する簡易 REST API
// - token 認証
// - Category / category の大小文字ゆれ吸収
// - category が無ければ INBOX
// - 指定カテゴリが無ければ作成
// - data.json へ追記
//
// ★改善点（今回）
// - text の末尾改行を trim で消さない
// - Notemod オリジナルに寄せた HTML形式で保存（１<div>２</div><div><br></div>...）
//   → 末尾改行の数も忠実に保存できる
// - WebP画像を受信した場合、サーバー側で自動的にPNGに変換して保存する

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

header('Content-Type: application/json; charset=utf-8');

// 共通レスポンス関数（pretty対応）
function respond_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);

    $flags  = JSON_UNESCAPED_UNICODE;
    $pretty = $_GET['pretty'] ?? $_POST['pretty'] ?? '';
    if ($pretty === '1' || strtolower((string)$pretty) === 'true') {
        $flags |= JSON_PRETTY_PRINT;
    }

    echo json_encode($payload, $flags);
    exit;
}


// =====================
// file_index.json 更新（現状インデックス）
// - file.json は追記専用ログのまま
// - file_index.json は「現存ファイルの一覧」を表示用に持つ
//   ※ upload時は差分更新。purge後は cleanup_api.php 側で再生成する想定
// =====================
function nm_update_file_index(string $userDir, array $latestMeta, string $originalName): bool
{
    $indexPath = rtrim($userDir, '/\\') . '/file_index.json';
    $tmpPath   = $indexPath . '.tmp';

    $nowUnix = time();
    $nowIso  = gmdate('c');

    // 追加/更新するエントリ（表示に必要な項目をまとめる）
    $entry = [
        'filename' => (string)($latestMeta['filename'] ?? ''),
        'original_name' => $originalName,
        'ext' => (string)($latestMeta['ext'] ?? ''),
        'mime' => (string)($latestMeta['mime'] ?? ''),
        'size' => (int)($latestMeta['size'] ?? 0),
        'sha256' => (string)($latestMeta['sha256'] ?? ''),
        'created_at' => (string)($latestMeta['created_at'] ?? $nowIso),
        'created_at_unix' => (int)($latestMeta['created_at_unix'] ?? $nowUnix),
        // あるなら file_id も入れる（無くてもOK）
        'file_id' => $latestMeta['file_id'] ?? null,
    ];

    if ($entry['filename'] === '') return false;

    // 既存 index を読む（壊れていたら新規作成）
    $index = [
        'v' => 1,
        'generated_at' => $nowIso,
        'generated_at_unix' => $nowUnix,
        'count' => 0,
        'files' => [],
    ];

    if (is_file($indexPath)) {
        $raw = @file_get_contents($indexPath);
        $tmp = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($tmp) && isset($tmp['files']) && is_array($tmp['files'])) {
            // v が無い/違っても files が配列なら読み込む（安全側）
            $index['files'] = $tmp['files'];
        }
    }

    // filename で差分更新（既存があれば置換、無ければ追加）
    $newFiles = [];
    $replaced = false;
    foreach ($index['files'] as $row) {
        if (!is_array($row)) continue;
        $fn = (string)($row['filename'] ?? '');
        if ($fn === $entry['filename']) {
            $newFiles[] = $entry;
            $replaced = true;
        } else {
            $newFiles[] = $row;
        }
    }
    if (!$replaced) {
        $newFiles[] = $entry;
    }

    // created_at_unix 降順でソート（最新を先頭に）
    usort($newFiles, function ($a, $b) {
        $au = (int)($a['created_at_unix'] ?? 0);
        $bu = (int)($b['created_at_unix'] ?? 0);
        if ($au === $bu) return 0;
        return ($bu <=> $au);
    });

    $index['generated_at'] = $nowIso;
    $index['generated_at_unix'] = $nowUnix;
    $index['files'] = $newFiles;
    $index['count'] = count($newFiles);

    $json = json_encode($index, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;

    // atomic write
    if (@file_put_contents($tmpPath, $json, LOCK_EX) === false) return false;
    @chmod($tmpPath, 0644);
    if (!@rename($tmpPath, $indexPath)) {
        @unlink($tmpPath);
        return false;
    }
    @chmod($indexPath, 0644);
    return true;
}

// =====================
// Notemod風の content 生成（末尾改行も保持）
// =====================
function notemod_text_to_html_preserve_newlines(string $text): string
{
    // 改行統一（Windows/Mac混在対策）
    $text = str_replace("\r\n", "\n", $text);
    $text = str_replace("\r", "\n", $text);

    // XSS対策（タグを無効化）
    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // 行分割（末尾が \n でも explode は末尾の空要素を保持する）
    $lines = explode("\n", $escaped);

    if ($text === '') {
        return '';
    }

    $out = '';

    // 1行目
    $first = $lines[0] ?? '';
    if ($first === '') {
        $out .= '<div><br></div>';
    } else {
        $out .= $first;
    }

    // 2行目以降
    $count = count($lines);
    for ($i = 1; $i < $count; $i++) {
        $line = $lines[$i];
        if ($line === '') {
            $out .= '<div><br></div>';
        } else {
            $out .= '<div>' . $line . '</div>';
        }
    }

    return $out;
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
$defaultColor   = (string)($cfg['DEFAULT_COLOR'] ?? '3478bd');

if ($EXPECTED_TOKEN === '' || $notemodFile === '') {
    respond_json(['status' => 'error', 'message' => 'Server not configured (EXPECTED_TOKEN / DATA_JSON)'], 500);
}

// =====================
// 1. パラメータ正規化（大小文字吸収 + POST/GET統一）
// =====================
$jsonBody = [];
$ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $jsonBody = $decoded;
}

$params = array_change_key_case($_GET, CASE_LOWER);
$params = $params + array_change_key_case($_POST, CASE_LOWER);
$params = $params + array_change_key_case($jsonBody, CASE_LOWER);

// =====================
// 2. トークンチェック
// =====================
$token = (string)($params['token'] ?? '');
if (!hash_equals($EXPECTED_TOKEN, $token)) {
    respond_json(['status' => 'error', 'message' => 'Forbidden'], 403);
}

// =====================
// 3. パラメータ取得（type / text / title / category / image / file）
// =====================
$type = strtolower(trim((string)($params['type'] ?? 'text')));
if ($type === '') $type = 'text';
if ($type !== 'text' && $type !== 'image' && $type !== 'file') {
    respond_json(['status' => 'error', 'message' => 'type must be text, image, or file'], 400);
}

if ($type === 'image') {
    // ---------------------
    // 画像アップロード処理
    // ---------------------

    $authFile = dirname(__DIR__) . '/config/auth.php';
    $auth = [];
    if (file_exists($authFile)) {
        $tmp = require $authFile;
        if (is_array($tmp)) $auth = $tmp;
    }
    $username = (string)($auth['USERNAME'] ?? $auth['username'] ?? 'default');
    $username = trim($username);
    if ($username === '') $username = 'default';

    $userDir   = dirname(__DIR__) . '/notemod-data/' . $username;
    $imagesDir = $userDir . '/images';

    if (!is_dir($imagesDir)) {
        if (!mkdir($imagesDir, 0755, true) && !is_dir($imagesDir)) {
            respond_json(['status' => 'error', 'message' => 'Failed to create images directory'], 500);
        }
    }

    $file = null;
    if (isset($_FILES['image'])) {
        $file = $_FILES['image'];
    } elseif (!empty($_FILES)) {
        $firstKey = array_key_first($_FILES);
        if ($firstKey !== null) $file = $_FILES[$firstKey];
    }

    if ($file === null || !isset($file['tmp_name'])) {
        respond_json(['status' => 'error', 'message' => 'image file is required (multipart/form-data)'], 400);
    }
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $err = $file['error'] ?? 'unknown';
        respond_json(['status' => 'error', 'message' => 'Upload failed', 'error' => $err], 400);
    }

    $tmpPath = (string)$file['tmp_name'];
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        respond_json(['status' => 'error', 'message' => 'Invalid uploaded file'], 400);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string)$finfo->file($tmpPath);

    // 画像として取り扱う拡張子
    $allowed = [
      'image/png'     => 'png',
      'image/jpeg'    => 'jpg',
      'image/webp'  => 'webp',
      'image/gif'       => 'gif',
      'image/heic'    => 'heic',
      'image/heif'     => 'heif',
      'image/tiff'       => 'tif',
      'image/bmp'    => 'bmp',
    ];
    if (!isset($allowed[$mime])) {
        respond_json(['status' => 'error', 'message' => 'Unsupported image type', 'mime' => $mime], 415);
    }

    // ==========================================
    // WebP だった場合は強制的に PNG に変換する
    // ==========================================
    if ($mime === 'image/webp') {
        if (!function_exists('imagecreatefromwebp') || !function_exists('imagepng')) {
            respond_json(['status' => 'error', 'message' => 'Server lacks WebP support (GD library)'], 500);
        }

        $image = @imagecreatefromwebp($tmpPath);
        if (!$image) {
            respond_json(['status' => 'error', 'message' => 'Failed to decode WebP image'], 500);
        }

        // 変換用の一時ファイルを作成
        $newTmpPath = $tmpPath . '_converted.png';
        if (!imagepng($image, $newTmpPath)) {
            imagedestroy($image);
            respond_json(['status' => 'error', 'message' => 'Failed to convert WebP to PNG'], 500);
        }
        imagedestroy($image);

        // 以降の処理は変換後の PNG として扱う
        $tmpPath = $newTmpPath;
        $mime = 'image/png';
        $ext = 'png';
    } else {
        $ext = $allowed[$mime];
    }
    // ==========================================

    try {
        $rand = bin2hex(random_bytes(4));
    } catch (Exception $e) {
        $rand = substr(sha1(uniqid('', true)), 0, 8);
    }
    $imageId = gmdate('Ymd\THis\Z') . '_' . $rand;
    $imageId = preg_replace('/[^A-Za-z0-9_\-]/', '_', $imageId);

    $filename = $imageId . '.' . $ext;
    $destPath = $imagesDir . '/' . $filename;

    $retry = 0;
    while (file_exists($destPath) && $retry < 3) {
        $retry++;
        $imageId = gmdate('Ymd\THis\Z') . '_' . substr(sha1(uniqid('', true)), 0, 8);
        $imageId = preg_replace('/[^A-Za-z0-9_\-]/', '_', $imageId);
        $filename = $imageId . '.' . $ext;
        $destPath = $imagesDir . '/' . $filename;
    }
    if (file_exists($destPath)) {
        respond_json(['status' => 'error', 'message' => 'Failed to generate unique filename'], 500);
    }

    $size = (int)filesize($tmpPath); // filesize を再取得 (変換で変わるため)
    if ($size < 0) $size = 0;

    $sha256 = hash_file('sha256', $tmpPath);

    // WebPから変換して新規作成したファイルか、アップロードされた一時ファイルかで処理を分ける
    if ($mime === 'image/png' && isset($newTmpPath) && $tmpPath === $newTmpPath) {
        // 変換したファイルの場合は rename で移動
        if (!rename($tmpPath, $destPath)) {
            respond_json(['status' => 'error', 'message' => 'Failed to save converted image'], 500);
        }
    } else {
        // 通常のアップロードファイルの場合は move_uploaded_file
        if (!move_uploaded_file($tmpPath, $destPath)) {
            respond_json(['status' => 'error', 'message' => 'Failed to save uploaded image'], 500);
        }
    }
    
    @chmod($destPath, 0644);

    $latest = [
        'v' => 1,
        'type' => 'image',
        'image_id' => $imageId,
        'filename' => $filename,
        'ext' => $ext,
        'mime' => $mime,
        'size' => $size,
        'sha256' => $sha256,
        'created_at' => gmdate('c'),
        'created_at_unix' => time(),
    ];

    $latestPath = $userDir . '/image_latest.json';
    $tmpLatest  = $latestPath . '.tmp';

    $jsonOut = json_encode($latest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($jsonOut === false) {
        respond_json(['status' => 'error', 'message' => 'Failed to encode metadata'], 500);
    }

    if (file_put_contents($tmpLatest, $jsonOut, LOCK_EX) === false) {
        respond_json(['status' => 'error', 'message' => 'Failed to write image_latest.json'], 500);
    }
    @chmod($tmpLatest, 0644);

    if (!rename($tmpLatest, $latestPath)) {
        if (file_put_contents($latestPath, $jsonOut, LOCK_EX) === false) {
            respond_json(['status' => 'error', 'message' => 'Failed to finalize image_latest.json'], 500);
        }
        @unlink($tmpLatest);
    }

if (function_exists('logMessage')) {
        logMessage('[api.php] image uploaded user=' . $username . ' file=' . $filename . ' size=' . $size . ' mime=' . $mime);
    }

    respond_json([
        'status' => 'ok',
        'mode' => 'image',
        'user' => $username,
        'image' => $latest,
    ], 200);
}

if ($type === 'file') {
    // ---------------------
    // 一般ファイルアップロード処理
    // ---------------------

    $authFile = dirname(__DIR__) . '/config/auth.php';
    $auth = [];
    if (file_exists($authFile)) {
        $tmp = require $authFile;
        if (is_array($tmp)) $auth = $tmp;
    }
    $username = (string)($auth['USERNAME'] ?? $auth['username'] ?? 'default');
    $username = trim($username);
    if ($username === '') $username = 'default';

    $userDir  = dirname(__DIR__) . '/notemod-data/' . $username;
    $filesDir = $userDir . '/files';

    if (!is_dir($filesDir)) {
        if (!mkdir($filesDir, 0755, true) && !is_dir($filesDir)) {
            respond_json(['status' => 'error', 'message' => 'Failed to create files directory'], 500);
        }
    }

    $file = null;
    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];
    } elseif (!empty($_FILES)) {
        $firstKey = array_key_first($_FILES);
        if ($firstKey !== null) $file = $_FILES[$firstKey];
    }

    if ($file === null || !isset($file['tmp_name'])) {
        respond_json(['status' => 'error', 'message' => 'file is required (multipart/form-data)'], 400);
    }
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $err = $file['error'] ?? 'unknown';
        respond_json(['status' => 'error', 'message' => 'Upload failed', 'error' => $err], 400);
    }

    $tmpPath = (string)$file['tmp_name'];
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        respond_json(['status' => 'error', 'message' => 'Invalid uploaded file'], 400);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string)$finfo->file($tmpPath);

    $originalName = (string)($file['name'] ?? '');
    $originalName = trim($originalName);
    // iOS/クライアントによっては RFC2047 (=?utf-8?B?...?=) 形式で来ることがあるので復号
    if ($originalName !== '' && preg_match('/^=\?utf-8\?[bq]\?.*\?=$/i', $originalName)) {
        if (function_exists('mb_decode_mimeheader')) {
            $decoded = @mb_decode_mimeheader($originalName);
            if (is_string($decoded) && $decoded !== '') {
                $originalName = $decoded;
            }
        }
    }

    $ext = '';
    if ($originalName !== '') {
        $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext);
        if (strlen($ext) > 10) $ext = substr($ext, 0, 10);
    }

    $blockedExt = ['php','phtml','phar','cgi','pl','sh','bat','cmd','exe','com','msi','js','jsp','asp','aspx','htm','html'];
    if ($ext === '' || in_array($ext, $blockedExt, true)) {
        $mimeMap = [
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'application/zip' => 'zip',
            'application/x-zip-compressed' => 'zip',
            'application/json' => 'json',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/msword' => 'doc',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.ms-powerpoint' => 'ppt',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];
        $ext = $mimeMap[$mime] ?? 'bin';
    }

    try {
        $rand = bin2hex(random_bytes(4));
    } catch (Exception $e) {
        $rand = substr(sha1(uniqid('', true)), 0, 8);
    }
    $fileId = gmdate('Ymd\THis\Z') . '_' . $rand;
    $fileId = preg_replace('/[^A-Za-z0-9_\-]/', '_', $fileId);

    $savedName = $fileId . '.' . $ext;
    $destPath  = $filesDir . '/' . $savedName;

    $retry = 0;
    while (file_exists($destPath) && $retry < 3) {
        $retry++;
        $fileId = gmdate('Ymd\THis\Z') . '_' . substr(sha1(uniqid('', true)), 0, 8);
        $fileId = preg_replace('/[^A-Za-z0-9_\-]/', '_', $fileId);
        $savedName = $fileId . '.' . $ext;
        $destPath  = $filesDir . '/' . $savedName;
    }
    if (file_exists($destPath)) {
        respond_json(['status' => 'error', 'message' => 'Failed to generate unique filename'], 500);
    }

    $size = (int)($file['size'] ?? filesize($tmpPath));
    if ($size < 0) $size = 0;

    $sha256 = hash_file('sha256', $tmpPath);

    if (!move_uploaded_file($tmpPath, $destPath)) {
        respond_json(['status' => 'error', 'message' => 'Failed to save uploaded file'], 500);
    }
    @chmod($destPath, 0644);

    $latest = [
        'v' => 1,
        'type' => 'file',
        'file_id' => $fileId,
        'filename' => $savedName,
        'ext' => $ext,
        'mime' => $mime,
        'size' => $size,
        'sha256' => $sha256,
        'original_name' => $originalName,
        'created_at' => gmdate('c'),
        'created_at_unix' => time(),
    ];

    $latestPath = $userDir . '/file_latest.json';
    $tmpLatest  = $latestPath . '.tmp';

    $jsonOut = json_encode($latest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($jsonOut === false) {
        respond_json(['status' => 'error', 'message' => 'Failed to encode metadata'], 500);
    }

    if (file_put_contents($tmpLatest, $jsonOut, LOCK_EX) === false) {
        respond_json(['status' => 'error', 'message' => 'Failed to write file_latest.json'], 500);
    }
    @chmod($tmpLatest, 0644);

    if (!rename($tmpLatest, $latestPath)) {
        if (file_put_contents($latestPath, $jsonOut, LOCK_EX) === false) {
            respond_json(['status' => 'error', 'message' => 'Failed to finalize file_latest.json'], 500);
        }
        @unlink($tmpLatest);
    }

    // ---------------------
    // 追加：file.json へ履歴追記（オリジナルファイル名一覧用）
    // - 1行1JSON（JSON Lines）で追記し、後から一覧生成しやすくする
    // - 既存の file_latest.json の仕様は変更しない
    // ---------------------
    $historyPath = $userDir . '/file.json';
    $historyEntry = [
        'v' => 1,
        'type' => 'file',
        'file_id' => $fileId,
        'filename' => $savedName,
        'ext' => $ext,
        'mime' => $mime,
        'size' => $size,
        'sha256' => $sha256,
        'original_name' => $originalName,
        'created_at' => $latest['created_at'],
        'created_at_unix' => $latest['created_at_unix'],
    ];

    $historyLine = json_encode($historyEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $historyOk = true;
    if ($historyLine === false) {
        $historyOk = false;
    } else {
        // 末尾改行を付けて追記（LOCK_EX）。ファイルが無ければ自動作成される。
        $written = @file_put_contents($historyPath, $historyLine . "\n", FILE_APPEND | LOCK_EX);
        if ($written === false) {
            $historyOk = false;
        } else {
            @chmod($historyPath, 0644);
        }
    }

    // ---------------------
    // 追加：file_index.json を差分更新（表示用インデックス）
    // ---------------------
    $indexOk = nm_update_file_index($userDir, $latest, $originalName);



    if (function_exists('logMessage')) {
        logMessage('[api.php] file uploaded user=' . $username . ' file=' . $savedName . ' size=' . $size . ' mime=' . $mime);
        if (!$historyOk) {
            logMessage('[api.php] WARN: failed to append file history (file.json) user=' . $username);
        }
        if (!$indexOk) {
            logMessage('[api.php] WARN: failed to update file_index.json user=' . $username);
        }
    }


    respond_json([
        'status' => 'ok',
        'mode' => 'file',
        'user' => $username,
        'file' => $latest,
    ], 200);
}

// ---------------------
// text（従来動作）
// ---------------------
$textRaw       = (string)($params['text'] ?? '');
$titleParam    = trim((string)($params['title'] ?? ''));
$categoryParam = trim((string)($params['category'] ?? ''));

if ($textRaw === '') {
    respond_json(['status' => 'error', 'message' => 'text is required'], 400);
}

$noteTitle    = ($titleParam !== '') ? $titleParam : date('Y-m-d H:i:s');
$categoryName = ($categoryParam !== '') ? $categoryParam : 'INBOX';

// =====================
// 4. Notemod の data.json を読み込む
// =====================
if (!file_exists($notemodFile)) {
    respond_json(['status' => 'error', 'message' => 'data.json not found'], 500);
}
if (!is_readable($notemodFile) || !is_writable($notemodFile)) {
    respond_json(['status' => 'error', 'message' => 'data.json not readable/writable'], 500);
}

$json = file_get_contents($notemodFile);
$data = json_decode($json, true);
if (!is_array($data)) $data = [];

$categoriesVal = $data['categories'] ?? '[]';
$notesVal      = $data['notes'] ?? '[]';

$categoriesArr = is_string($categoriesVal) ? json_decode($categoriesVal, true) : $categoriesVal;
$notesArr      = is_string($notesVal) ? json_decode($notesVal, true) : $notesVal;

if (!is_array($categoriesArr)) $categoriesArr = [];
if (!is_array($notesArr)) $notesArr = [];

// =====================
// 5. categoryName のカテゴリーを探す or 作る
// =====================
$categoryId = null;

foreach ($categoriesArr as $cat) {
    $catName = (string)($cat['name'] ?? '');
    if ($catName !== '' && strcasecmp($catName, $categoryName) === 0) {
        $categoryId   = $cat['id'] ?? null;
        $categoryName = $catName;
        break;
    }
}

if ($categoryId === null) {
    $categoryId = (int)floor(microtime(true) * 1000);
    $categoriesArr[] = [
        'id'    => $categoryId,
        'name'  => $categoryName,
        'color' => $defaultColor,
    ];
}

// =====================
// 6. ノートを1つ作成して追加
// =====================
$noteIdMs  = (int)floor(microtime(true) * 1000);
$noteId    = (string)$noteIdMs;
$createdAt = gmdate('Y-m-d\TH:i:s\Z');

$safeText = notemod_text_to_html_preserve_newlines($textRaw);
if ($safeText === '') {
    respond_json(['status' => 'error', 'message' => 'text is required'], 400);
}

$newNote = [
    'id'           => $noteId,
    'title'        => $noteTitle,
    'color'        => $defaultColor,
    'task_content' => null,
    'content'      => $safeText,
    'categories'   => [$categoryId],
    'createdAt'    => $createdAt,
    'updatedAt'    => $createdAt,
];

$notesArr[] = $newNote;

// =====================
// 7. そのカテゴリに属するノート数を数える
// =====================
$noteCountInCategory = 0;
foreach ($notesArr as $n) {
    $cats = $n['categories'] ?? null;
    if (is_array($cats) && in_array($categoryId, $cats, true)) {
        $noteCountInCategory++;
    }
}

// =====================
// 8. data.json に書き戻す
// =====================
$data['categories'] = is_string($categoriesVal)
    ? json_encode($categoriesArr, JSON_UNESCAPED_UNICODE)
    : $categoriesArr;

$data['notes'] = is_string($notesVal)
    ? json_encode($notesArr, JSON_UNESCAPED_UNICODE)
    : $notesArr;

$saveResult = file_put_contents(
    $notemodFile,
    json_encode($data, JSON_UNESCAPED_UNICODE),
    LOCK_EX
);

if ($saveResult === false) {
    respond_json(['status' => 'error', 'message' => 'failed to save data.json'], 500);
}


// =====================
// 8.5) 追加：テキスト追加時に note_latest.json を更新（latest_clip_type 用）
// - notemod-data/<user>/note_latest.json
// - 無ければ作成
// - 既存機能に影響しないよう、この処理が失敗しても main 処理は継続
// =====================
try {
    $authFile = dirname(__DIR__) . '/config/auth.php';
    $auth = [];
    if (file_exists($authFile)) {
        $tmp = require $authFile;
        if (is_array($tmp)) $auth = $tmp;
    }
    $username = (string)($auth['USERNAME'] ?? $auth['username'] ?? 'default');
    $username = trim($username);
    if ($username === '') $username = 'default';

    $userDir = dirname(__DIR__) . '/notemod-data/' . $username;
    if (!is_dir($userDir)) {
        @mkdir($userDir, 0755, true);
    }

    $noteLatest = [
        'v' => 1,
        'type' => 'note',
        'note_id' => $noteId,
        'created_at' => gmdate('c'),
        'created_at_unix' => time(),
    ];

    $noteLatestPath = $userDir . '/note_latest.json';
    $tmpNoteLatest  = $noteLatestPath . '.tmp';
    $jsonOut = json_encode($noteLatest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($jsonOut !== false) {
        if (@file_put_contents($tmpNoteLatest, $jsonOut, LOCK_EX) !== false) {
            @chmod($tmpNoteLatest, 0644);
            if (!@rename($tmpNoteLatest, $noteLatestPath)) {
                @file_put_contents($noteLatestPath, $jsonOut, LOCK_EX);
                @unlink($tmpNoteLatest);
            }
            @chmod($noteLatestPath, 0644);
        }
    }
} catch (Throwable $e) {
    // 失敗しても本体の text 追加処理は成功させる
    if (function_exists('logMessage')) {
        logMessage('[api.php] WARN: failed to update note_latest.json: ' . $e->getMessage());
    }
}

// =====================
// 9. 成功レスポンス
// =====================
respond_json([
    'status'   => 'ok',
    'category' => [
        'id'        => $categoryId,
        'name'      => $categoryName,
        'noteCount' => $noteCountInCategory,
    ],
    'note' => $newNote,
]);