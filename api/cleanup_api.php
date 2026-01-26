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
// 0. POST 強制
// =====================
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (strtoupper($method) !== 'POST') {
    respond_json([
        'status'  => 'error',
        'message' => 'Method Not Allowed (POST only)',
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

$purgeBakBool = ($purgeBak === '1' || strtolower($purgeBak) === 'true');
$purgeLogBool = ($purgeLog === '1' || strtolower($purgeLog) === 'true');

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
// 追加機能：logs フォルダー内の .log ファイル全削除（purge_log=1）
// - dry_run=1: 対象一覧
// - dry_run=2: 対象数のみ（NEW）
// =====================
if ($purgeLogBool) {

    // logger.php の __DIR__ は public_html（想定）
    // cleanup_api.php の __DIR__ は public_html/api なので、baseRoot は 1階層上（public_html）
    $baseRoot = dirname(__DIR__);

    // config/config.php の LOGGER_LOGS_DIRNAME を優先（無ければ 'logs'）
    $logsDirName = (string)($commonCfg['LOGGER_LOGS_DIRNAME'] ?? '');
    $logsDirName = trim($logsDirName) !== '' ? trim($logsDirName) : 'logs';

    // 安全：フォルダ名だけ許可（/ や .. を拒否）
    if (preg_match('/[\/\\\\]/', $logsDirName) || strpos($logsDirName, '..') !== false) {
        respond_json([
            'status'  => 'error',
            'message' => 'Invalid LOGGER_LOGS_DIRNAME',
            'value'   => $logsDirName,
        ], 500);
    }

    $logsDir = $baseRoot . '/' . $logsDirName;

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

        // 対象：拡張子が .log のみ
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
            $errors[] = ['file' => $name, 'err' => error_get_last()];
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