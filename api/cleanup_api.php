<?php
// cleanup_api.php
// 指定カテゴリのノートを全削除（危険操作：POST専用 + confirm必須 + dry_runあり + backup任意）

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

// ★追加：バックアップ設定
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
//    - form
//    - JSON
//    - キーを小文字に統一して Category/category 等を吸収
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
// 4. パラメータ（category / confirm / dry_run）
// =====================

$TARGET_CATEGORY_NAME_DEFAULT = 'INBOX';

$categoryName = trim((string)($params['category'] ?? $TARGET_CATEGORY_NAME_DEFAULT));
$confirm      = trim((string)($params['confirm'] ?? ''));
$dryRun       = trim((string)($params['dry_run'] ?? '0'));

$dryRunBool = ($dryRun === '1' || strtolower($dryRun) === 'true');

// confirm が無いと実行しない（dry_runはOK）
if (!$dryRunBool && $confirm !== 'YES') {
    respond_json([
        'status'  => 'error',
        'message' => 'This is a destructive action. Add confirm=YES (or use dry_run=1).',
    ], 400);
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
    respond_json([
        'status'   => 'ok',
        'message'  => 'category not found',
        'category' => $categoryName,
        'deleted'  => 0,
        'dry_run'  => $dryRunBool,
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
    respond_json([
        'status'   => 'ok',
        'message'  => 'dry run - nothing deleted',
        'category' => ['name' => $resolvedCategoryName, 'id' => $categoryId],
        'deleted'  => $deletedCount,
        'dry_run'  => true,
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
    'dry_run'  => false,
    'backup'   => [
        'enabled' => $backupEnabled,
    ],
];

if ($backupEnabled) {
    $out['backup']['file'] = $backupBaseName;
}

respond_json($out);