<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_common.php';
nm_auth_require_login();
nm_send_security_headers_json();

// notemod_sync.php
header('Content-Type: application/json; charset=utf-8');

// --------------------
// 認証済み save チェック
// --------------------
function nm_sync_require_logged_in_json(): void
{
    nm_auth_start_session();
    if (!nm_auth_is_logged_in()) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Login expired. Please log in again before syncing.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// --------------------
// config/api/data パスから、対象 user の共通 config を $GLOBALS['cfg'] に注入
// --------------------
function nm_sync_inject_common_config_from_data_file(string $dataFile): array
{
    $cfg = array();

    $dataDir = realpath(dirname($dataFile));
    if ($dataDir === false) {
        $dataDir = dirname($dataFile);
    }

    $dirUser = basename($dataDir);
    $dirUser = normalize_username((string)$dirUser);

    if ($dirUser !== '') {
        $configPath = nm_config_path($dirUser);
        if (is_file($configPath)) {
            $tmp = require $configPath;
            if (is_array($tmp)) {
                $cfg = $tmp;
            }
        }
    }

    $GLOBALS['cfg'] = is_array($cfg) ? $cfg : array();
    return $GLOBALS['cfg'];
}

// --------------------
// Safer write helper (keep response format/flow unchanged)
// --------------------

function nm_sync_load_api_config_from_data_file(string $dataFile): array
{
    $cfgApi = array();

    $dataDir = realpath(dirname($dataFile));
    if ($dataDir === false) {
        $dataDir = dirname($dataFile);
    }

    $dirUser = basename($dataDir);
    $dirUser = normalize_username((string)$dirUser);

    if ($dirUser !== '') {
        $configApiPath = nm_api_config_path($dirUser);
        if (is_file($configApiPath)) {
            $tmp = require $configApiPath;
            if (is_array($tmp)) {
                $cfgApi = $tmp;
            }
        }
    }

    return is_array($cfgApi) ? $cfgApi : array();
}

function nm_sync_backup_files(string $dataFile): array
{
    $dir = dirname($dataFile);
    if (!is_dir($dir)) {
        return array();
    }

    $items = @scandir($dir);
    if (!is_array($items)) {
        return array();
    }

    $list = array();
    foreach ($items as $name) {
        if ($name === '.' || $name === '..') continue;
        if (!nm_is_supported_backup_filename($name)) continue;

        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path)) continue;

        $mtime = @filemtime($path);
        $list[] = array(
            'path' => $path,
            'mtime' => is_int($mtime) ? $mtime : 0,
        );
    }

    usort($list, function ($a, $b) {
        return (int)$b['mtime'] <=> (int)$a['mtime'];
    });

    return $list;
}

function nm_sync_delete_old_backups(string $dataFile, int $keep): array
{
    $keep = max(0, $keep);
    $list = nm_sync_backup_files($dataFile);
    $targets = ($keep === 0) ? $list : array_slice($list, $keep);

    $deleted = 0;
    $failed = array();

    foreach ($targets as $item) {
        $path = (string)($item['path'] ?? '');
        if ($path === '' || !is_file($path)) continue;
        if (@unlink($path)) {
            $deleted++;
        } else {
            $failed[] = $path;
        }
    }

    return array($deleted, $failed);
}

function nm_sync_atomic_write_json(string $path, string $payload): bool
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) return false;
    }

    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
    $ok = @file_put_contents($tmp, $payload, LOCK_EX);
    if ($ok === false) {
        @unlink($tmp);
        return false;
    }

    @chmod($tmp, 0644);

    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }

    if (function_exists('clearstatcache')) {
        clearstatcache(true, $path);
    }
    return true;
}

function nm_sync_lock_path(string $dataFile): string
{
    return $dataFile . '.lock';
}

function nm_sync_with_lock(string $dataFile, callable $fn)
{
    $lockPath = nm_sync_lock_path($dataFile);
    $lockFp = @fopen($lockPath, 'c');
    if ($lockFp === false) {
        return [false, 'lock_open_failed', null];
    }

    try {
        if (!@flock($lockFp, LOCK_EX)) {
            return [false, 'lock_failed', null];
        }

        $result = $fn();

        @flock($lockFp, LOCK_UN);
        @fclose($lockFp);
        return [true, null, $result];
    } catch (Throwable $e) {
        @flock($lockFp, LOCK_UN);
        @fclose($lockFp);
        throw $e;
    }
}

// --------------------
// ログ関数（1行JSON）
// --------------------
function nm_log(string $path, string $event, array $ctx = []): void {
    $line = json_encode(
        ['ts' => date('c'), 'event' => $event, 'ctx' => $ctx],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) . "\n";
    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}

// --------------------
// 同期ガード
// --------------------

function nm_sync_normalize_snapshot(array $data): array
{
    foreach (['categories', 'notes', 'categoryOrder', 'noteOrder'] as $key) {
        if (array_key_exists($key, $data) && is_string($data[$key])) {
            $decoded = json_decode($data[$key], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data[$key] = $decoded;
            }
        }
    }

    foreach (['sidebarState', 'thizaState', 'tema'] as $key) {
        if (array_key_exists($key, $data) && $data[$key] === 'null') {
            $data[$key] = null;
        }
    }

    if (isset($data['hasSelectedLanguage']) && is_string($data['hasSelectedLanguage'])) {
        if ($data['hasSelectedLanguage'] === 'true') {
            $data['hasSelectedLanguage'] = true;
        } elseif ($data['hasSelectedLanguage'] === 'false') {
            $data['hasSelectedLanguage'] = false;
        } else {
            $decoded = json_decode($data['hasSelectedLanguage'], true);
            if (is_bool($decoded)) {
                $data['hasSelectedLanguage'] = $decoded;
            }
        }
    }

    if (isset($data['selectedLanguage']) && is_string($data['selectedLanguage'])) {
        $decoded = json_decode($data['selectedLanguage'], true);
        if (is_string($decoded)) {
            $data['selectedLanguage'] = $decoded;
        }
    }

    return $data;
}

function nm_sync_is_effectively_empty_snapshot(array $data): bool
{
    $notes = $data['notes'] ?? null;
    $categories = $data['categories'] ?? null;

    $notesEmpty = !is_array($notes) || count($notes) === 0;
    $categoriesEmpty = !is_array($categories) || count($categories) === 0;

    return $notesEmpty && $categoriesEmpty;
}

function nm_sync_snapshot_count_summary(array $data): array
{
    $notes = $data['notes'] ?? null;
    $categories = $data['categories'] ?? null;

    return [
        'notes_count' => is_array($notes) ? count($notes) : 0,
        'categories_count' => is_array($categories) ? count($categories) : 0,
        'is_effectively_empty' => nm_sync_is_effectively_empty_snapshot($data),
    ];
}

function nm_sync_create_pre_save_backup(string $dataFile): array
{
    $raw = @file_get_contents($dataFile);
    if ($raw === false || trim($raw) === '') {
        return [true, null, 'nothing_to_backup'];
    }

    $isEncrypted = nm_is_encrypted_data_json($raw);
    $timestamp = date('Ymd-His');
    $backupName = nm_get_backup_basename($isEncrypted, $timestamp);
    $backupPath = dirname($dataFile) . DIRECTORY_SEPARATOR . $backupName;

    if (!nm_sync_atomic_write_json($backupPath, $raw)) {
        return [false, null, 'backup_write_failed'];
    }

    return [true, $backupPath, null];
}

// --------------------
// パス設定（DIR_USER ベース）
// --------------------
nm_auth_start_session();

$dirUser = nm_get_current_dir_user();
$configPath = nm_config_path($dirUser);
$configDir  = dirname($configPath);

$dataFile = nm_data_json_path($dirUser);
$baseDir  = dirname($dataFile);

$logDir  = nm_logs_dir($dirUser);
$logFile = rtrim($logDir, '/\\') . DIRECTORY_SEPARATOR . '_sync_debug.log';

$apiDir = __DIR__ . '/api';

// config フォルダー保護
$DEBUG_BOOT = false;
try {
    $ok = nm_write_htaccess_content($configDir, nm_default_deny_htaccess_content(), false, false);
    if (!$ok && $DEBUG_BOOT) {
        nm_log($logFile, 'htaccess_skip_or_failed', ['dir' => $configDir]);
    }
} catch (Throwable $e) {
}

if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Missing config for current user', 'config_path' => $configPath], JSON_UNESCAPED_UNICODE);
    exit;
}
$cfg = require $configPath;
if (!is_array($cfg)) $cfg = [];

if ($dirUser === '') {
    $dirUser = normalize_username((string)($cfg['DIR_USER'] ?? ''));
    if ($dirUser !== '') {
        $configPath = nm_config_path($dirUser);
        $configDir  = dirname($configPath);
        $dataFile   = nm_data_json_path($dirUser);
        $baseDir    = dirname($dataFile);
        $logDir     = nm_logs_dir($dirUser);
        $logFile    = rtrim($logDir, '/\\') . DIRECTORY_SEPARATOR . '_sync_debug.log';
    }
}

// 対象 data.json に対応する config を data_crypto.php へ渡す
$GLOBALS['cfg'] = is_array($cfg) ? $cfg : array();

require_once __DIR__ . '/data_crypto.php';
nm_sync_inject_common_config_from_data_file($dataFile);

$SECRET = (string)($cfg['SECRET'] ?? '');
if ($SECRET === '' || strlen($SECRET) < 16) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'SECRET is empty or too short in current user config', 'config_path' => $configPath], JSON_UNESCAPED_UNICODE);
    exit;
}

$TIMEZONE = (string)($cfg['TIMEZONE'] ?? 'UTC');
if ($TIMEZONE === '') $TIMEZONE = 'UTC';
@date_default_timezone_set($TIMEZONE);

$INITIAL_SNAPSHOT = (string)($cfg['INITIAL_SNAPSHOT'] ?? json_encode([
    'categories' => null,
    'hasSelectedLanguage' => null,
    'notes' => null,
    'selectedLanguage' => null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$DEBUG = (bool)($cfg['DEBUG'] ?? false);

if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
if (!is_dir($baseDir)) {
    @mkdir($baseDir, 0755, true);
}

try {
    $ok = nm_write_htaccess_content($logDir, nm_default_deny_htaccess_content(), false, true);
    if (!$ok && $DEBUG) nm_log($logFile, 'logs_htaccess_failed', ['dir' => $logDir]);
} catch (Throwable $e) {
    if ($DEBUG) nm_log($logFile, 'logs_htaccess_exception', ['msg' => $e->getMessage()]);
}

try {
    $ok = nm_write_htaccess_content($baseDir, nm_default_deny_htaccess_content(), false, true);
    if (!$ok && $DEBUG) nm_log($logFile, 'data_htaccess_failed', ['dir' => $baseDir]);
} catch (Throwable $e) {
    if ($DEBUG) nm_log($logFile, 'htaccess_exception', ['msg' => $e->getMessage()]);
}

try {
    $ok = nm_write_htaccess_content($apiDir, nm_default_api_htaccess_content(), false, true);
    if (!$ok && $DEBUG) nm_log($logFile, 'api_htaccess_failed', ['dir' => $apiDir]);
} catch (Throwable $e) {
    if ($DEBUG) nm_log($logFile, 'api_htaccess_exception', ['msg' => $e->getMessage()]);
}

$ct  = $_SERVER['CONTENT_TYPE'] ?? '';
$raw = file_get_contents('php://input') ?: '';

if (str_starts_with($raw, "\xEF\xBB\xBF")) {
    $raw = substr($raw, 3);
}

$req = null;

if ($raw !== '') {
    $tmp = json_decode($raw, true);
    if (is_array($tmp)) $req = $tmp;
}

if (!is_array($req)) {
    if (!empty($_POST) && is_array($_POST)) $req = $_POST;
    elseif (!empty($_GET) && is_array($_GET)) $req = $_GET;
    else $req = [];
}

if ($dirUser === '') {
    foreach (['user', 'username'] as $key) {
        if (isset($req[$key])) {
            $tmpUser = normalize_username((string)$req[$key]);
            if ($tmpUser !== '') {
                $dirUser = $tmpUser;
                break;
            }
        }
    }
    if ($dirUser !== '') {
        $configPath = nm_config_path($dirUser);
        $configDir  = dirname($configPath);
        $dataFile   = nm_data_json_path($dirUser);
        $baseDir    = dirname($dataFile);
        $logDir     = nm_logs_dir($dirUser);
        $logFile    = rtrim($logDir, '/\\') . DIRECTORY_SEPARATOR . '_sync_debug.log';
        nm_sync_inject_common_config_from_data_file($dataFile);
    }
}

if ($DEBUG) {
    nm_log($logFile, 'request', [
        'tz' => date_default_timezone_get(),
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'content_type' => $ct,
        'content_length' => (int)($_SERVER['CONTENT_LENGTH'] ?? 0),
        'raw_len' => strlen($raw),
        'raw_head' => substr($raw, 0, 200),
        'keys' => array_slice(array_keys($req), 0, 20),
    ]);
}

if (!is_array($req) || $req === []) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Empty or unreadable request'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!hash_equals($SECRET, (string)($req['token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = (string)($req['action'] ?? '');

if ($action === 'save') {
    nm_sync_require_logged_in_json();

    $payload = $req['data'] ?? null;

    if (!is_string($payload) || $payload === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing data'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $decodedPayload = json_decode($payload, true);
    if (!is_array($decodedPayload)) {
        if ($DEBUG) nm_log($logFile, 'payload_invalid_json', ['err' => json_last_error_msg(), 'head' => substr($payload, 0, 200)]);
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Data is not valid JSON', 'detail' => json_last_error_msg()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $decodedPayload = nm_sync_normalize_snapshot($decodedPayload);

    list($lockOk, $lockErr, $saveResult) = nm_sync_with_lock($dataFile, function () use ($dataFile, $decodedPayload) {
        nm_sync_inject_common_config_from_data_file($dataFile);

        list($serverLoadOk, $serverData, $serverReason) = nm_try_load_data_file($dataFile);
        if (!$serverLoadOk) {
            return ['ok' => false, 'code' => 500, 'message' => 'Failed to read current server data before save', 'detail' => $serverReason];
        }

        $serverSummary = nm_sync_snapshot_count_summary($serverData);
        $localSummary = nm_sync_snapshot_count_summary($decodedPayload);

        if (!$serverSummary['is_effectively_empty'] && $localSummary['is_effectively_empty']) {
            return [
                'ok' => false,
                'code' => 409,
                'message' => 'Refused to overwrite non-empty server data with an empty snapshot',
                'detail' => [
                    'server' => $serverSummary,
                    'local' => $localSummary,
                ],
            ];
        }

        if ($serverData === $decodedPayload) {
            return [
                'ok' => true,
                'status' => 'ok',
                'result' => 'no_change',
                'backup' => null,
            ];
        }

        $commonCfg = is_array($GLOBALS['cfg'] ?? null) ? $GLOBALS['cfg'] : array();
        $syncPreSaveBackupEnabled = (bool)($commonCfg['SYNC_PRE_SAVE_BACKUP_ENABLED'] ?? true);
        $syncPreSavePruneEnabled = (bool)($commonCfg['SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED'] ?? false);

        if ($syncPreSaveBackupEnabled && $syncPreSavePruneEnabled) {
            $cfgApi = nm_sync_load_api_config_from_data_file($dataFile);
            $keep = (int)($cfgApi['CLEANUP_BACKUP_KEEP'] ?? 20);
            if ($keep < 0) {
                $keep = 0;
            }

            list($prunedCount, $pruneFailed) = nm_sync_delete_old_backups($dataFile, $keep);
            if (!empty($pruneFailed)) {
                return [
                    'ok' => false,
                    'code' => 500,
                    'message' => 'Failed to prune old backups before save',
                    'detail' => $pruneFailed,
                ];
            }
        }

        $backupPath = null;
        if ($syncPreSaveBackupEnabled) {
            list($backupOk, $backupPath, $backupReason) = nm_sync_create_pre_save_backup($dataFile);
            if (!$backupOk) {
                return [
                    'ok' => false,
                    'code' => 500,
                    'message' => 'Failed to create backup before save',
                    'detail' => $backupReason,
                ];
            }
        }

        $saveOk = nm_save_data_file($dataFile, $decodedPayload);
        if ($saveOk !== true) {
            return [
                'ok' => false,
                'code' => 500,
                'message' => 'Failed to write file',
                'detail' => 'save_failed',
            ];
        }

        return [
            'ok' => true,
            'status' => 'ok',
            'result' => 'saved',
            'backup' => $backupPath,
            'server_before' => $serverSummary,
            'local_after' => $localSummary,
        ];
    });

    if (!$lockOk || !is_array($saveResult)) {
        if ($DEBUG) nm_log($logFile, 'write_failed', ['lock_error' => $lockErr, 'err' => error_get_last()]);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to write file'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (empty($saveResult['ok'])) {
        $code = (int)($saveResult['code'] ?? 500);
        http_response_code($code > 0 ? $code : 500);
        if ($DEBUG) nm_log($logFile, 'save_rejected', [
            'code' => $code,
            'message' => $saveResult['message'] ?? '',
            'detail' => $saveResult['detail'] ?? null,
        ]);
        echo json_encode([
            'status' => 'error',
            'message' => (string)($saveResult['message'] ?? 'Save rejected'),
            'detail' => $saveResult['detail'] ?? null,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($DEBUG) nm_log($logFile, 'saved', [
        'bytes' => strlen($payload),
        'result' => $saveResult['result'] ?? 'saved',
        'backup' => $saveResult['backup'] ?? null,
    ]);

    echo json_encode([
        'status' => 'ok',
        'result' => $saveResult['result'] ?? 'saved',
        'backup' => $saveResult['backup'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'load') {
    if (!file_exists($dataFile) || @filesize($dataFile) === 0) {
        $initialData = json_decode($INITIAL_SNAPSHOT, true);
        if (!is_array($initialData)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'INITIAL_SNAPSHOT is invalid JSON', 'detail' => json_last_error_msg()], JSON_UNESCAPED_UNICODE);
            exit;
        }

        list($lockOk, $lockErr, $initOk) = nm_sync_with_lock($dataFile, function () use ($dataFile, $initialData) {
            nm_sync_inject_common_config_from_data_file($dataFile);
            return nm_save_data_file($dataFile, $initialData);
        });

        if (!$lockOk || $initOk !== true) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to initialize data.json'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($DEBUG) nm_log($logFile, 'initialized', ['bytes' => strlen($INITIAL_SNAPSHOT)]);
    }

    list($lockOk, $lockErr, $loaded) = nm_sync_with_lock($dataFile, function () use ($dataFile) {
        nm_sync_inject_common_config_from_data_file($dataFile);
        return nm_try_load_data_file($dataFile);
    });

    if (!$lockOk || !is_array($loaded)) {
        if ($DEBUG) nm_log($logFile, 'stored_read_failed', ['lock_error' => $lockErr]);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to read stored data.json'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    list($loadOk, $storedData, $loadReason) = $loaded;
    if (!$loadOk || !is_array($storedData)) {
        if ($DEBUG) nm_log($logFile, 'stored_invalid_json', ['reason' => $loadReason, 'size' => @filesize($dataFile)]);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Stored data.json is invalid or could not be decrypted', 'detail' => $loadReason], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stored = nm_json_encode_data($storedData);
    if (!is_string($stored) || $stored === false) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to encode stored data.json'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['status' => 'ok', 'data' => $stored], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown action'], JSON_UNESCAPED_UNICODE);
exit;