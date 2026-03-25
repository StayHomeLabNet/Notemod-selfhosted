<?php
// cleanup_api.php
// 指定カテゴリのノートを全削除（危険操作：POST専用 + confirm必須 + dry_runあり + backup任意）
//
// dry_run:
//   0 = 実行（削除する）※ confirm=YES 必須
//   1 = 実行しない（対象一覧/詳細を返す）
//   2 = 実行しない（対象「数」だけ返す）← NEW

require_once __DIR__ . '/../logger.php';

// =====================
// タイムゾーン設定（config/config.php から読む）
// =====================
$tz = 'Pacific/Auckland';
$cfgCommonFile = dirname(__DIR__) . '/config/config.php'; // api/ の1つ上 → config/
$commonCfg = [];
if (file_exists($cfgCommonFile)) {
    $tmp = require $cfgCommonFile;
    if (is_array($tmp)) $commonCfg = $tmp;

    $t = (string)($commonCfg['TIMEZONE'] ?? $commonCfg['timezone'] ?? '');
    if ($t !== '') $tz = $t;
}
date_default_timezone_set($tz);

header('Content-Type: application/json; charset=utf-8');

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

// =====================
// 0. POST 強制（ただし GET action=backup_now は許可）
// =====================
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? ''));
$getAction = trim((string)($_GET['action'] ?? ''));
$isBackupNowGet = ($method === 'GET' && $getAction === 'backup_now');
if ($method !== 'POST' && !$isBackupNowGet) {
    respond_json([
        'status'  => 'error',
        'message' => 'Method Not Allowed (POST only, except GET action=backup_now)',
    ], 405);
}

// =====================
// 1. 設定読み込み（config/config.api.php）
// =====================
$configFile = dirname(__DIR__) . '/config/config.api.php';
if (!file_exists($configFile)) {
    respond_json(['status' => 'error', 'message' => 'config.api.php missing'], 500);
}

$cfg = require $configFile;
if (!is_array($cfg)) $cfg = [];

// cleanup用トークン（同じEXPECTED_TOKENでもいいし、分けたいなら ADMIN_TOKEN を追加してもOK）
$ADMIN_TOKEN = (string)($cfg['ADMIN_TOKEN'] ?? $cfg['EXPECTED_TOKEN'] ?? '');
$notemodFile = (string)($cfg['DATA_JSON'] ?? '');

// バックアップ設定
$backupEnabled = (bool)($cfg['CLEANUP_BACKUP_ENABLED'] ?? true);
$backupSuffix  = (string)($cfg['CLEANUP_BACKUP_SUFFIX'] ?? '.bak-');

if ($ADMIN_TOKEN === '' || $notemodFile === '') {
    respond_json([
        'status'  => 'error',
        'message' => 'Server not configured (ADMIN_TOKEN/EXPECTED_TOKEN or DATA_JSON)',
    ], 500);
}

if (!function_exists('nm_create_backup_now')) {
    function nm_create_backup_now(string $notemodFile, string $backupSuffix): array
    {
        if (!file_exists($notemodFile)) {
            return ['ok' => false, 'message' => 'data.json not found'];
        }
        if (!is_readable($notemodFile)) {
            return ['ok' => false, 'message' => 'data.json not readable'];
        }
        $backupFile = $notemodFile . $backupSuffix . date('Ymd-His');
        if (!@copy($notemodFile, $backupFile)) {
            return ['ok' => false, 'message' => 'failed to create backup'];
        }
        return [
            'ok' => true,
            'file' => basename($backupFile),
            'path' => $backupFile,
        ];
    }
}

if ($isBackupNowGet) {
    $r = nm_create_backup_now($notemodFile, $backupSuffix);
    if (!$r['ok']) {
        respond_json([
            'status' => 'error',
            'message' => $r['message'],
            'action' => 'backup_now',
        ], 500);
    }
    respond_json([
        'status' => 'ok',
        'message' => 'backup created',
        'action' => 'backup_now',
        'backup' => [
            'enabled' => true,
            'file' => $r['file'],
        ],
    ], 200);
}

// =====================
// 2. パラメータ正規化（POSTのみ）
// =====================
$params = array_change_key_case($_POST, CASE_LOWER);

// JSONボディ対応
$jsonBody = [];
$ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $jsonBody = $decoded;
}
if (!empty($jsonBody)) {
    $jsonLower = array_change_key_case($jsonBody, CASE_LOWER);
    $params = $jsonLower + $params; // JSON優先
}

// =====================
// 3. 認証
// =====================
$token = (string)($params['token'] ?? '');
if (!hash_equals($ADMIN_TOKEN, $token)) {
    respond_json(['status' => 'error', 'message' => 'Forbidden'], 403);
}

// =====================
// 4. パラメータ（category / confirm / dry_run / purge_bak / purge_log）
// =====================
$TARGET_CATEGORY_NAME_DEFAULT = 'INBOX';

$categoryName = trim((string)($params['category'] ?? $TARGET_CATEGORY_NAME_DEFAULT));
$confirm      = trim((string)($params['confirm'] ?? ''));
$dryRunRaw    = trim((string)($params['dry_run'] ?? '0'));

$purgeBak     = trim((string)($params['purge_bak'] ?? $params['delete_bak'] ?? '0'));
$purgeLog     = trim((string)($params['purge_log'] ?? '0'));

// 追加機能：images/files の削除
$purgeImages  = trim((string)($params['purge_images'] ?? '0'));
$purgeFiles   = trim((string)($params['purge_files'] ?? '0'));
$purgeMedia   = trim((string)($params['purge_media'] ?? '0'));
$deleteImages = $_POST['delete_images'] ?? ($jsonBody['delete_images'] ?? []);
$deleteFiles  = $_POST['delete_files'] ?? ($jsonBody['delete_files'] ?? []);

$purgeBakBool    = ($purgeBak === '1' || strtolower($purgeBak) === 'true');
$purgeLogBool    = ($purgeLog === '1' || strtolower($purgeLog) === 'true');
$purgeImagesBool = ($purgeImages === '1' || strtolower($purgeImages) === 'true');
$purgeFilesBool  = ($purgeFiles === '1' || strtolower($purgeFiles) === 'true');
$purgeMediaBool  = ($purgeMedia === '1' || strtolower($purgeMedia) === 'true');

$deleteImagesList = nm_normalize_name_list($deleteImages);
$deleteFilesList = nm_normalize_name_list($deleteFiles);
$deleteImagesBool = (count($deleteImagesList) > 0);
$deleteFilesBool = (count($deleteFilesList) > 0);

// purge_media=1 は images + files をまとめて削除
if ($purgeMediaBool) {
    $purgeImagesBool = true;
    $purgeFilesBool = true;
}

// dry_run モード判定（NEW）
// - 'true' は 1 扱い
$dryRunMode = 0;
if ($dryRunRaw === '2') $dryRunMode = 2;
elseif ($dryRunRaw === '1' || strtolower($dryRunRaw) === 'true') $dryRunMode = 1;
$dryRunBool = ($dryRunMode > 0);

// confirm が無いと実行しない（dry_run=1/2 はOK）
if (!$dryRunBool && $confirm !== 'YES') {
    respond_json([
        'status'  => 'error',
        'message' => 'This is a destructive action. Add confirm=YES (or use dry_run=1/2).',
    ], 400);
}


// =====================
// 追加：<user> 判定（config/auth.php の USERNAME） + userDir 推定
// =====================
function nm_get_username(): string
{
    $u = 'default';
    $authFile = dirname(__DIR__) . '/config/auth.php';
    if (file_exists($authFile)) {
        $auth = require $authFile;
        if (is_array($auth) && isset($auth['USERNAME'])) {
            $u = (string)$auth['USERNAME'];
        } elseif (defined('USERNAME')) {
            $u = (string)USERNAME;
        }
    }
    $u = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)$u);
    if ($u === '' || $u === null) $u = 'default';
    return $u;
}

function nm_get_user_dir(string $dataJsonPath, string $username): string
{
    $dataJsonDir = realpath(dirname($dataJsonPath));
    $root = $dataJsonDir ?: dirname($dataJsonPath);

    // /notemod-data/<user>/data.json の場合は 1つ上を root とみなす
    if ($dataJsonDir && basename($dataJsonDir) === $username) {
        $parent = realpath(dirname($dataJsonDir));
        if ($parent) $root = $parent;
    }

    return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $username;
}

function nm_safe_basename(string $name): string
{
    $name = basename($name);
    $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);
    return $name;
}

function nm_read_json_file(string $path): ?array
{
    if (!is_file($path) || !is_readable($path)) return null;
    $raw = file_get_contents($path);
    $decoded = json_decode($raw ?: '', true);
    return is_array($decoded) ? $decoded : null;
}


function nm_normalize_name_list($raw): array
{
    if (!is_array($raw)) return [];
    $out = [];
    foreach ($raw as $v) {
        if (!is_scalar($v)) continue;
        $name = nm_safe_basename((string)$v);
        if ($name === '') continue;
        $out[$name] = true;
    }
    return array_keys($out);
}

function nm_delete_selected_in_dir(string $dir, array $selectedNames, ?string $protectFilename, int $dryRunMode): array
{
    $targets = [];
    $deleted = [];
    $errors  = [];

    if (!is_dir($dir)) {
        return ['targets' => [], 'deleted' => [], 'errors' => []];
    }

    $baseReal = realpath($dir);
    if (!$baseReal) {
        return ['targets' => [], 'deleted' => [], 'errors' => [['message' => 'failed to resolve dir', 'dir' => $dir]]];
    }

    foreach ($selectedNames as $name) {
        $name = nm_safe_basename((string)$name);
        if ($name === '') continue;
        if ($protectFilename !== null && $protectFilename !== '' && $name === $protectFilename) {
            continue;
        }
        $full = $dir . DIRECTORY_SEPARATOR . $name;
        $fullReal = realpath($full);
        if (!$fullReal || strpos($fullReal, $baseReal) !== 0 || !is_file($fullReal)) {
            continue;
        }
        $targets[] = $name;
    }

    if ($dryRunMode > 0) {
        return ['targets' => $targets, 'deleted' => [], 'errors' => []];
    }

    foreach ($targets as $name) {
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (@unlink($path)) {
            $deleted[] = $name;
        } else {
            $errors[] = ['file' => $name, 'err' => error_get_last()];
        }
    }

    return ['targets' => $targets, 'deleted' => $deleted, 'errors' => $errors];
}

function nm_purge_files_in_dir(string $dir, ?string $protectFilename, int $dryRunMode): array
{
    // 返却：['targets'=>[], 'deleted'=>[], 'errors'=>[]]
    $targets = [];
    $deleted = [];
    $errors  = [];

    if (!is_dir($dir)) {
        return ['targets' => [], 'deleted' => [], 'errors' => []];
    }

    $baseReal = realpath($dir);
    if (!$baseReal) {
        return ['targets' => [], 'deleted' => [], 'errors' => [['message' => 'failed to resolve dir', 'dir' => $dir]]];
    }

    $it = new DirectoryIterator($dir);
    foreach ($it as $f) {
        if ($f->isDot() || !$f->isFile()) continue;
        $name = $f->getFilename();

        // latest実体は保護
        if ($protectFilename !== null && $protectFilename !== '' && $name === $protectFilename) {
            continue;
        }

        $full = $f->getPathname();
        $fullReal = realpath($full);
        if (!$fullReal || strpos($fullReal, $baseReal) !== 0) {
            // 想定外パスは無視（安全側）
            continue;
        }
        $targets[] = $name;
    }

    // dry_run=1/2 は削除しない
    if ($dryRunMode > 0) {
        return ['targets' => $targets, 'deleted' => [], 'errors' => []];
    }

    foreach ($targets as $name) {
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (@unlink($path)) {
            $deleted[] = $name;
        } else {
            $errors[] = ['file' => $name, 'err' => error_get_last()];
        }
    }

    return ['targets' => $targets, 'deleted' => $deleted, 'errors' => $errors];
}


// =====================
// 追加：file_index.json 再生成（方針A） + file_latest.json 補正
// - purge_files 実行後に /files の現状を index 化
// - file_latest.json が指す実体が無い場合は、indexの先頭（最新）に付け替える
// - latestメタが壊れて読めない場合でも、削除後の補正として安全に作り直す（※削除処理自体は既存ルール通り）
// =====================
function nm_write_json_atomic(string $path, array $data): bool
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) return false;
    }
    $tmp = $path . '.tmp';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    @chmod($tmp, 0644);
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    @chmod($path, 0644);
    return true;
}

function nm_detect_mime(string $path): string
{
    $mime = 'application/octet-stream';
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $m = @finfo_file($finfo, $path);
            if (is_string($m) && $m !== '') $mime = $m;
            @finfo_close($finfo);
        }
    }
    return $mime;
}


function nm_decode_rfc2047(string $s): string
{
    // "=?utf-8?B?...?=" のような MIME encoded-word を復号（存在すれば）
    $s = (string)$s;
    if ($s === '') return $s;
    if (!preg_match('/^=\?utf-8\?[bq]\?.*\?=$/i', $s)) return $s;
    if (function_exists('mb_decode_mimeheader')) {
        $decoded = @mb_decode_mimeheader($s);
        if (is_string($decoded) && $decoded !== '') return $decoded;
    }
    return $s;
}


function nm_load_file_history_map(string $fileJsonPath, int $maxLines = 20000): array
{
    // file.json は JSON Lines（1行=1JSON）想定
    if (!is_file($fileJsonPath) || !is_readable($fileJsonPath)) return [];
    $lines = @file($fileJsonPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) return [];

    // 新しい行が後ろにある想定：後ろから見て最初に出たfilenameを採用
    $map = [];
    $cnt = 0;
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        $cnt++;
        if ($cnt > $maxLines) break;
        $row = json_decode($lines[$i], true);
        if (!is_array($row)) continue;
        $fn = (string)($row['filename'] ?? '');
        if ($fn === '') continue;
        if (isset($map[$fn])) continue;
        $map[$fn] = $row;
    }
    return $map;
}

function nm_rebuild_file_index_and_fix_latest(string $userDir): array
{
    $filesDir = rtrim($userDir, '/\\') . DIRECTORY_SEPARATOR . 'files';
    $indexPath = rtrim($userDir, '/\\') . DIRECTORY_SEPARATOR . 'file_index.json';
    $latestPath = rtrim($userDir, '/\\') . DIRECTORY_SEPARATOR . 'file_latest.json';
    $historyPath = rtrim($userDir, '/\\') . DIRECTORY_SEPARATOR . 'file.json';

    if (!is_dir($filesDir)) {
        // files/ 自体が無い場合：indexは空で作り、latestは消す
        nm_write_json_atomic($indexPath, [
            'v' => 1,
            'generated_at' => gmdate('c'),
            'generated_at_unix' => time(),
            'count' => 0,
            'files' => [],
        ]);
        if (is_file($latestPath)) @unlink($latestPath);
        return ['ok' => true, 'message' => 'no files dir; cleared file_latest.json', 'index_count' => 0];
    }

    // 履歴マップ（filename -> latest meta row）
    $histMap = nm_load_file_history_map($historyPath);

    // files/ 走査
    $items = [];
    $it = new DirectoryIterator($filesDir);
    foreach ($it as $fi) {
        if ($fi->isDot()) continue;
        if (!$fi->isFile()) continue;

        $stored = $fi->getFilename();
        $stored = nm_safe_basename($stored);
        if ($stored === '') continue;

        $full = $fi->getPathname();
        $size = $fi->getSize();
        $mtime = $fi->getMTime();

        $row = $histMap[$stored] ?? [];

        $original = (string)($row['original_name'] ?? '');
        $original = nm_decode_rfc2047($original);
        $createdAt = (string)($row['created_at'] ?? '');
        $createdUnix = (int)($row['created_at_unix'] ?? 0);
        if ($createdUnix <= 0) $createdUnix = $mtime;

        if ($createdAt === '') {
            // UTCで保存
            $createdAt = gmdate('c', $createdUnix);
        }

        $mime = (string)($row['mime'] ?? '');
        if ($mime === '') $mime = nm_detect_mime($full);

        $ext = (string)($row['ext'] ?? '');
        if ($ext === '') $ext = strtolower(pathinfo($stored, PATHINFO_EXTENSION));

        $sha = (string)($row['sha256'] ?? '');

        $items[] = [
            'filename' => $stored,
            'original_name' => $original,
            'ext' => $ext,
            'mime' => $mime,
            'size' => (int)$size,
            'sha256' => $sha,
            'created_at' => $createdAt,
            'created_at_unix' => (int)$createdUnix,
            'file_id' => (string)($row['file_id'] ?? ''),
        ];
    }

    // created_at_unix desc
    usort($items, function($a, $b){
        $au = (int)($a['created_at_unix'] ?? 0);
        $bu = (int)($b['created_at_unix'] ?? 0);
        return $bu <=> $au;
    });

    $index = [
        'v' => 1,
        'generated_at' => gmdate('c'),
        'generated_at_unix' => time(),
        'count' => count($items),
        'files' => $items,
    ];

    if (!nm_write_json_atomic($indexPath, $index)) {
        return ['ok' => false, 'message' => 'failed to write file_index.json', 'index_count' => count($items)];
    }

    // latest補正：latestが無い or 指す実体が無い場合、index先頭へ付け替え
    if (count($items) === 0) {
        if (is_file($latestPath)) @unlink($latestPath);
        return ['ok' => true, 'message' => 'file_index rebuilt; no files -> removed file_latest.json', 'index_count' => 0];
    }

    $needFix = false;
    $latestFn = '';

    if (is_file($latestPath)) {
        $meta = nm_read_json_file($latestPath);
        if ($meta === null) {
            // 壊れてたら付け替え
            $needFix = true;
        } else {
            $latestFn = nm_safe_basename((string)($meta['filename'] ?? ''));
            if ($latestFn === '' || !is_file($filesDir . DIRECTORY_SEPARATOR . $latestFn)) {
                $needFix = true;
            }
        }
    } else {
        $needFix = true;
    }

    if ($needFix) {
        $top = $items[0];
        $newLatest = [
            'v' => 1,
            'type' => 'file',
            'file_id' => ($top['file_id'] !== '' ? $top['file_id'] : (string)pathinfo($top['filename'], PATHINFO_FILENAME)),
            'filename' => $top['filename'],
            'ext' => $top['ext'],
            'mime' => $top['mime'],
            'size' => (int)$top['size'],
            'sha256' => $top['sha256'],
            'original_name' => $top['original_name'],
            'created_at' => $top['created_at'],
            'created_at_unix' => (int)$top['created_at_unix'],
        ];
        if (!nm_write_json_atomic($latestPath, $newLatest)) {
            return ['ok' => false, 'message' => 'file_index rebuilt but failed to update file_latest.json', 'index_count' => count($items)];
        }
        return ['ok' => true, 'message' => 'file_index rebuilt; file_latest.json updated', 'index_count' => count($items)];
    }

    return ['ok' => true, 'message' => 'file_index rebuilt; file_latest.json ok', 'index_count' => count($items)];
}


// =====================
// 追加機能：images/files 削除（purge_images / purge_files / purge_media）
// 絶対ルール：latest実体は削除しない／latestメタが壊れて読めない場合は削除しない
// - dry_run=1: 対象一覧
// - dry_run=2: 対象数のみ
// =====================
if ($purgeImagesBool || $purgeFilesBool || $deleteImagesBool || $deleteFilesBool) {

    $username = nm_get_username();
    $userDir  = nm_get_user_dir($notemodFile, $username);

    $result = [
        'status'  => 'ok',
        'message' => 'purge completed',
        'user'    => $username,
        'dry_run' => $dryRunMode,
        'images'  => ['enabled' => ($purgeImagesBool || $deleteImagesBool)],
        'files'   => ['enabled' => ($purgeFilesBool || $deleteFilesBool)],
    ];

    // ---- images ----
    if ($purgeImagesBool || $deleteImagesBool) {
        $metaPath  = $userDir . DIRECTORY_SEPARATOR . 'image_latest.json';
        $imagesDir = $userDir . DIRECTORY_SEPARATOR . 'images';

        $protect = null;
        if (is_file($metaPath)) {
            $meta = nm_read_json_file($metaPath);
            if ($meta === null) {
                // latestメタが壊れている場合は削除しない（絶対ルール）
                respond_json([
                    'status'  => 'error',
                    'message' => 'invalid image_latest.json - aborting purge_images to protect latest',
                    'meta'    => $metaPath,
                ], 500);
            }
            $fn = nm_safe_basename((string)($meta['filename'] ?? ''));
            if ($fn === '' || !preg_match('/^[a-zA-Z0-9_-]+\.(png|jpg|jpeg|webp)$/i', $fn)) {
                respond_json([
                    'status'  => 'error',
                    'message' => 'invalid image_latest.json filename - aborting purge_images to protect latest',
                    'meta'    => $metaPath,
                    'filename'=> $fn,
                ], 500);
            }
            $protect = $fn;
        }

        $r = $deleteImagesBool
            ? nm_delete_selected_in_dir($imagesDir, $deleteImagesList, $protect, $dryRunMode)
            : nm_purge_files_in_dir($imagesDir, $protect, $dryRunMode);
        $result['images'] += [
            'dir'     => $imagesDir,
            'protect' => $protect,
            'mode'    => $deleteImagesBool ? 'selected' : 'purge',
            'requested' => $deleteImagesBool ? $deleteImagesList : [],
            'count'   => count($r['targets']),
        ];
        $result['image'] = ['count' => $result['images']['count']];

        if ($dryRunMode === 1) {
            $result['images']['files'] = $r['targets'];
        }
        if ($dryRunMode === 0) {
            $result['images']['deleted'] = count($r['deleted']);
            $result['images']['failed']  = count($r['errors']);
            $result['images']['files']   = $r['deleted'];
            $result['images']['errors']  = $r['errors'];
        }
        if ($dryRunMode === 2) {
            // count only already set
        }
    }

    // ---- files ----
    if ($purgeFilesBool || $deleteFilesBool) {
        $metaPath = $userDir . DIRECTORY_SEPARATOR . 'file_latest.json';
        $filesDir = $userDir . DIRECTORY_SEPARATOR . 'files';

        $protect = null;
        if (is_file($metaPath)) {
            $meta = nm_read_json_file($metaPath);
            if ($meta === null) {
                respond_json([
                    'status'  => 'error',
                    'message' => 'invalid file_latest.json - aborting purge_files to protect latest',
                    'meta'    => $metaPath,
                ], 500);
            }
            $fn = nm_safe_basename((string)($meta['filename'] ?? ''));
            // files は拡張子が様々なので緩め（1-10文字）
            if ($fn === '' || !preg_match('/^[a-zA-Z0-9_-]+\.[a-zA-Z0-9]{1,10}$/', $fn)) {
                respond_json([
                    'status'  => 'error',
                    'message' => 'invalid file_latest.json filename - aborting purge_files to protect latest',
                    'meta'    => $metaPath,
                    'filename'=> $fn,
                ], 500);
            }
            $protect = $fn;
        }

        $r = $deleteFilesBool
            ? nm_delete_selected_in_dir($filesDir, $deleteFilesList, $protect, $dryRunMode)
            : nm_purge_files_in_dir($filesDir, $protect, $dryRunMode);
        $result['files'] += [
            'dir'     => $filesDir,
            'protect' => $protect,
            'mode'    => $deleteFilesBool ? 'selected' : 'purge',
            'requested' => $deleteFilesBool ? $deleteFilesList : [],
            'count'   => count($r['targets']),
        ];
        $result['file'] = ['count' => $result['files']['count']];

        if ($dryRunMode === 1) {
            $result['files']['files'] = $r['targets'];
        }
        if ($dryRunMode === 0) {
            $result['files']['deleted'] = count($r['deleted']);
            $result['files']['failed']  = count($r['errors']);
            $result['files']['files']   = $r['deleted'];
            $result['files']['errors']  = $r['errors'];

    // ---- post process: file_index.json 再生成 + latest補正（削除が実行された時のみ）----
    if (($purgeFilesBool || $deleteFilesBool) && $dryRunMode === 0) {
        $fix = nm_rebuild_file_index_and_fix_latest($userDir);
        $result['files']['index_rebuild'] = $fix;
    }

        }
    }

    // メッセージ調整
    if ($dryRunMode === 2) {
        $result['message'] = 'dry run (count only) - delete would remove files';
    } elseif ($dryRunMode === 1) {
        $result['message'] = 'dry run - delete would remove these files';
    } else {
        $result['message'] = 'media cleanup completed';
    }

    respond_json($result, 200);
}

// =====================
// 追加機能：notemod-data 内の bak ファイル全削除（purge_bak=1）
// - dry_run=1: 対象一覧
// - dry_run=2: 対象数のみ（NEW）
// =====================
if ($purgeBakBool) {

    $dir = dirname($notemodFile);
    if (!is_dir($dir)) {
        respond_json(['status' => 'error', 'message' => 'notemod-data dir not found', 'dir' => $dir], 500);
    }

    $targets = [];
    $it = new DirectoryIterator($dir);
    foreach ($it as $f) {
        if ($f->isDot() || !$f->isFile()) continue;

        $name = $f->getFilename();
        $path = $f->getPathname();

        // data.json 本体は絶対に消さない
        if (realpath($path) === realpath($notemodFile)) continue;

        // 対象：.bak / .bak- / .bak. を含むファイル
        if (preg_match('/\.bak(\-|\.|$)/i', $name) === 1) {
            $targets[] = $name;
        }
    }

    if ($dryRunBool) {
        if ($dryRunMode === 2) {
            respond_json([
                'status'  => 'ok',
                'message' => 'dry run (count only) - purge_bak would delete files',
                'dir'     => $dir,
                'dry_run' => 2,
                'count'   => count($targets),
            ]);
        }

        respond_json([
            'status'  => 'ok',
            'message' => 'dry run - purge_bak would delete these files',
            'dir'     => $dir,
            'dry_run' => 1,
            'count'   => count($targets),
            'files'   => $targets,
        ]);
    }

    $deleted = [];
    $errors  = [];

    foreach ($targets as $name) {
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (@unlink($path)) {
            $deleted[] = $name;
        } else {
            $errors[] = ['file' => $name, 'err' => error_get_last()];
        }
    }

    respond_json([
        'status'  => 'ok',
        'message' => 'purge_bak completed',
        'dir'     => $dir,
        'deleted' => count($deleted),
        'failed'  => count($errors),
        'files'   => $deleted,
        'errors'  => $errors,
    ]);
}

// =====================
// 追加機能：logs/<USER_NAME>/ 内の .log ファイル全削除（purge_log=1）
// - dry_run=1: 対象一覧
// - dry_run=2: 対象数のみ（NEW）
// =====================
if ($purgeLogBool) {

    $logsDir = function_exists('nm_logs_dir')
        ? nm_logs_dir($currentDirUser !== '' ? $currentDirUser : null)
        : (dirname(__DIR__) . '/logs/' . $currentDirUser);

    if (!is_dir($logsDir)) {
        respond_json([
            'status'  => 'error',
            'message' => 'logs dir not found',
            'dir'     => $logsDir,
        ], 500);
    }

    $targets = [];
    $it = new DirectoryIterator($logsDir);
    foreach ($it as $f) {
        if ($f->isDot() || !$f->isFile()) continue;

        $name = $f->getFilename();

        if (preg_match('/\.log$/i', $name) === 1) {
            $targets[] = $name;
        }
    }

    if ($dryRunBool) {
        if ($dryRunMode === 2) {
            respond_json([
                'status'  => 'ok',
                'message' => 'dry run (count only) - purge_log would delete files',
                'dir'     => $logsDir,
                'dry_run' => 2,
                'count'   => count($targets),
            ]);
        }

        respond_json([
            'status'  => 'ok',
            'message' => 'dry run - purge_log would delete these files',
            'dir'     => $logsDir,
            'dry_run' => 1,
            'count'   => count($targets),
            'files'   => $targets,
        ]);
    }

    $deleted = [];
    $errors  = [];

    foreach ($targets as $name) {
        $path = $logsDir . DIRECTORY_SEPARATOR . $name;
        if (@unlink($path)) {
            $deleted[] = $name;
        } else {
            $errors[] = [
                'file' => $name,
                'err'  => error_get_last(),
            ];
        }
    }

    respond_json([
        'status'  => 'ok',
        'message' => 'purge_log completed',
        'dir'     => $logsDir,
        'deleted' => count($deleted),
        'failed'  => count($errors),
        'files'   => $deleted,
        'errors'  => $errors,
    ]);
}

// =====================
// 5. data.json 読み込み
// =====================
if (!file_exists($notemodFile)) {
    respond_json(['status' => 'error', 'message' => 'data.json not found'], 500);
}
if (!is_readable($notemodFile) || !is_writable($notemodFile)) {
    respond_json(['status' => 'error', 'message' => 'data.json not readable/writable'], 500);
}

$data = json_decode(file_get_contents($notemodFile), true);
if (!is_array($data)) $data = [];

// Notemodの保存形式が「JSON文字列」でも「配列」でも対応
$categoriesVal = $data['categories'] ?? '[]';
$notesVal      = $data['notes'] ?? '[]';

$categoriesArr = is_string($categoriesVal) ? json_decode($categoriesVal, true) : $categoriesVal;
$notesArr      = is_string($notesVal) ? json_decode($notesVal, true) : $notesVal;

if (!is_array($categoriesArr)) $categoriesArr = [];
if (!is_array($notesArr)) $notesArr = [];

// =====================
// 6. 対象カテゴリIDを探す（大小文字ゆれ吸収）
// =====================
$categoryId = null;
$resolvedCategoryName = $categoryName;

foreach ($categoriesArr as $cat) {
    $catName = (string)($cat['name'] ?? '');
    if ($catName !== '' && strcasecmp($catName, $categoryName) === 0) {
        $categoryId = $cat['id'] ?? null;
        $resolvedCategoryName = $catName; // 既存表記に寄せる
        break;
    }
}

if ($categoryId === null) {
    // dry_run=2 なら「数だけ」に寄せる
    if ($dryRunMode === 2) {
        respond_json([
            'status'  => 'ok',
            'message' => 'category not found',
            'dry_run' => 2,
            'count'   => 0,
        ]);
    }

    respond_json([
        'status'   => 'ok',
        'message'  => 'category not found',
        'category' => $categoryName,
        'deleted'  => 0,
        'dry_run'  => $dryRunBool ? 1 : 0,
    ]);
}

// =====================
// 7. 削除対象をフィルタ
// =====================
$keptNotes = [];
$deletedCount = 0;

foreach ($notesArr as $note) {
    $cats = $note['categories'] ?? null;
    $isInCategory = is_array($cats) && in_array($categoryId, $cats, true);

    if ($isInCategory) {
        $deletedCount++;
        continue;
    }
    $keptNotes[] = $note;
}

if ($dryRunBool) {
    // dry_run=2: 数だけ返す（NEW）
    if ($dryRunMode === 2) {
        respond_json([
            'status'  => 'ok',
            'message' => 'dry run (count only) - nothing deleted',
            'dry_run' => 2,
            'count'   => $deletedCount,
        ]);
    }

    // dry_run=1: 従来どおり（※このAPIは「一覧」ではなく、削除数などの詳細を返す）
    respond_json([
        'status'   => 'ok',
        'message'  => 'dry run - nothing deleted',
        'category' => ['name' => $resolvedCategoryName, 'id' => $categoryId],
        'deleted'  => $deletedCount,
        'dry_run'  => 1,
        'backup'   => ['enabled' => $backupEnabled],
    ]);
}

// =====================
// 8. バックアップ作成（設定でON/OFF）
// =====================
$backupBaseName = null;

if ($backupEnabled) {
    $backupFile = $notemodFile . $backupSuffix . date('Ymd-His');

    if (!copy($notemodFile, $backupFile)) {
        respond_json(['status' => 'error', 'message' => 'failed to create backup'], 500);
    }

    $backupBaseName = basename($backupFile);
}

// =====================
// 9. 保存（元の形式を維持）
// =====================
$data['notes'] = is_string($notesVal)
    ? json_encode($keptNotes, JSON_UNESCAPED_UNICODE)
    : $keptNotes;

$saveResult = file_put_contents(
    $notemodFile,
    json_encode($data, JSON_UNESCAPED_UNICODE),
    LOCK_EX
);

if ($saveResult === false) {
    respond_json(['status' => 'error', 'message' => 'failed to save data.json'], 500);
}

$out = [
    'status'   => 'ok',
    'message'  => 'deleted notes in category',
    'category' => ['name' => $resolvedCategoryName, 'id' => $categoryId],
    'deleted'  => $deletedCount,
    'dry_run'  => 0,
    'backup'   => [
        'enabled' => $backupEnabled,
    ],
];

if ($backupEnabled) {
    $out['backup']['file'] = $backupBaseName;
}

respond_json($out);