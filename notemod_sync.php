<?php
declare(strict_types=1);

// notemod_sync.php
header('Content-Type: application/json; charset=utf-8');

// --------------------
// 互換：PHP7系でも動くように polyfill
// --------------------
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
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
// ディレクトリ保護：.htaccess が無ければ作成（内容を指定できる版）
// - createDir=true なら dir が無い場合も作る
// - createDir=false なら dir が存在する場合だけ書く（config向け）
// - content は .htaccess の内容
// --------------------
function nm_ensure_htaccess_content(
    string $dir,
    bool $createDir,
    string $content,
    bool $debug,
    string $logFile
): void
{
    if (!is_dir($dir)) {
        if (!$createDir) {
            if ($debug) nm_log($logFile, 'htaccess_skip_dir_missing', ['dir' => $dir]);
            return;
        }
        if (!@mkdir($dir, 0755, true)) {
            if ($debug) nm_log($logFile, 'mkdir_failed', ['dir' => $dir, 'err' => error_get_last()]);
            throw new RuntimeException('Failed to create dir: ' . $dir);
        }
    }

    $htaccess = rtrim($dir, "/\\") . DIRECTORY_SEPARATOR . '.htaccess';

    // 既にあれば上書きしない（手動ルールを尊重）
    if (file_exists($htaccess)) return;

    // できるだけ原子的に作成
    $tmp = $htaccess . '.tmp-' . bin2hex(random_bytes(4));
    $ok = @file_put_contents($tmp, $content, LOCK_EX);
    if ($ok === false) {
        if ($debug) nm_log($logFile, 'htaccess_write_failed', ['tmp' => $tmp, 'err' => error_get_last()]);
        @unlink($tmp);
        return; // .htaccess が作れなくても同期自体は続行
    }

    @chmod($tmp, 0644);

    if (!@rename($tmp, $htaccess)) {
        if ($debug) nm_log($logFile, 'htaccess_rename_failed', ['tmp' => $tmp, 'dst' => $htaccess, 'err' => error_get_last()]);
        @unlink($tmp);
        return;
    }

    if ($debug) nm_log($logFile, 'htaccess_created', ['path' => $htaccess]);
}

// --------------------
// 既存互換：deny ルール（notemod-data / config 用）
// --------------------
function nm_default_deny_htaccess(): string
{
    return <<<HT
<IfModule mod_authz_core.c>
  Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
  Order allow,deny
  Deny from all
</IfModule>

HT;
}

// --------------------
// allow ルール（api 用：誰でもアクセスOK）
// --------------------
function nm_api_allow_htaccess(): string
{
    return <<<HT
Require all granted

HT;
}

// --------------------
// パス設定
// --------------------
$baseDir   = __DIR__ . '/notemod-data';
$dataFile  = $baseDir . '/data.json';
$logFile   = $baseDir . '/_sync_debug.log';

$configDir  = __DIR__ . '/config';
$configPath = $configDir . '/config.php';

$apiDir = __DIR__ . '/api';

// --------------------
// config フォルダー保護（存在する時だけ）
// ※ config/ が無い状態で勝手に作らない（秘密情報置き場なので）
// --------------------
$DEBUG_BOOT = false; // config読める前なので暫定
try {
    nm_ensure_htaccess_content($configDir, false, nm_default_deny_htaccess(), $DEBUG_BOOT, $logFile);
} catch (Throwable $e) {
    // ここで止めない
}

// --------------------
// config 読み込み（本番はここに SECRET / TIMEZONE を置く）
// public_html/config/config.php を作ってください（Gitに入れない）
// --------------------
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Missing config/config.php'], JSON_UNESCAPED_UNICODE);
    exit;
}
$cfg = require $configPath;
if (!is_array($cfg)) $cfg = [];

$SECRET = (string)($cfg['SECRET'] ?? '');
if ($SECRET === '' || strlen($SECRET) < 16) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'SECRET is empty or too short in config/config.php'], JSON_UNESCAPED_UNICODE);
    exit;
}

// タイムゾーン（configから）
$TIMEZONE = (string)($cfg['TIMEZONE'] ?? 'UTC');
if ($TIMEZONE === '') $TIMEZONE = 'UTC';
@date_default_timezone_set($TIMEZONE);

// 初回作成用スナップショット（スナップショットのJSON文字列）
$INITIAL_SNAPSHOT = (string)($cfg['INITIAL_SNAPSHOT'] ?? json_encode([
    'categories' => null,
    'hasSelectedLanguage' => null,
    'notes' => null,
    'selectedLanguage' => null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

// デバッグログ有効/無効（ここで確定）
$DEBUG = (bool)($cfg['DEBUG'] ?? false);

// --------------------
// 保存先：notemod-data dir 作成（無ければ作る）
// --------------------
if (!is_dir($baseDir)) {
    @mkdir($baseDir, 0755, true);
}

// --------------------
// notemod-data 保護：.htaccess が無ければ作成（deny）
// （notemod-data は無ければ作る）
// --------------------
try {
    nm_ensure_htaccess_content($baseDir, true, nm_default_deny_htaccess(), $DEBUG, $logFile);
} catch (Throwable $e) {
    if ($DEBUG) nm_log($logFile, 'htaccess_exception', ['msg' => $e->getMessage()]);
}

// --------------------
// api フォルダー：.htaccess を自動作成（allow）
// （api は既に存在しているはずだが、無ければ作る）
// --------------------
try {
    nm_ensure_htaccess_content($apiDir, true, nm_api_allow_htaccess(), $DEBUG, $logFile);
} catch (Throwable $e) {
    if ($DEBUG) nm_log($logFile, 'api_htaccess_exception', ['msg' => $e->getMessage()]);
}

// --------------------
// 受け取り：JSON body → ダメなら $_POST → ダメなら $_GET
// --------------------
$ct  = $_SERVER['CONTENT_TYPE'] ?? '';
$raw = file_get_contents('php://input') ?: '';

// UTF-8 BOM除去
if (str_starts_with($raw, "\xEF\xBB\xBF")) {
    $raw = substr($raw, 3);
}

$req = null;

// まず JSON として読む（Content-Typeが違っても試す）
if ($raw !== '') {
    $tmp = json_decode($raw, true);
    if (is_array($tmp)) $req = $tmp;
}

// フォーム送信などのためにフォールバック
if (!is_array($req)) {
    if (!empty($_POST) && is_array($_POST)) $req = $_POST;
    elseif (!empty($_GET) && is_array($_GET)) $req = $_GET;
    else $req = [];
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

// token/action が無いならここでエラー
if (!is_array($req) || $req === []) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Empty or unreadable request'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 簡易認証
if (!hash_equals($SECRET, (string)($req['token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = (string)($req['action'] ?? '');

// --------------------
// save
// --------------------
if ($action === 'save') {

    $payload = $req['data'] ?? null;

    if (!is_string($payload) || $payload === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing data'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // JSON妥当性チェック
    json_decode($payload, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        if ($DEBUG) nm_log($logFile, 'payload_invalid_json', ['err' => json_last_error_msg(), 'head' => substr($payload, 0, 200)]);
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Data is not valid JSON', 'detail' => json_last_error_msg()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ok = @file_put_contents($dataFile, $payload, LOCK_EX);

    if ($ok === false) {
        if ($DEBUG) nm_log($logFile, 'write_failed', ['err' => error_get_last()]);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to write file'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($DEBUG) nm_log($logFile, 'saved', ['bytes' => $ok]);
    echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
    exit;
}

// --------------------
// load
// --------------------
if ($action === 'load') {

    // 初回起動用：data.json が無ければ初期スナップショットで作成
    if (!file_exists($dataFile) || @filesize($dataFile) === 0) {

        json_decode($INITIAL_SNAPSHOT, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'INITIAL_SNAPSHOT is invalid JSON', 'detail' => json_last_error_msg()], JSON_UNESCAPED_UNICODE);
            exit;
        }

        @file_put_contents($dataFile, $INITIAL_SNAPSHOT, LOCK_EX);
        if ($DEBUG) nm_log($logFile, 'initialized', ['bytes' => strlen($INITIAL_SNAPSHOT)]);
    }

    $stored = (string)@file_get_contents($dataFile);

    json_decode($stored, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        if ($DEBUG) nm_log($logFile, 'stored_invalid_json', ['err' => json_last_error_msg(), 'size' => @filesize($dataFile)]);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Stored data.json is invalid JSON', 'detail' => json_last_error_msg()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['status' => 'ok', 'data' => $stored], JSON_UNESCAPED_UNICODE);
    exit;
}

// action 不明
http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown action'], JSON_UNESCAPED_UNICODE);
exit;