<?php
// cleanup_api.php
// 指定カテゴリのノートを全削除（危険操作：POST専用 + confirm必須 + dry_runあり + backup任意）

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

/**
 * 指定の例に合わせた JSON（インデント2スペース）を生成する
 * - 文字列内の改行/エスケープは json_encode に任せる
 * - インデント幅だけ 2 スペースで整形する
 */
function nm_json_pretty_2($value): string
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        // 最悪の保険（ここはバックアップなので落としたくない）
        return "{}\n";
    }

    $out = '';
    $indent = 0;
    $inString = false;
    $escape = false;

    $len = strlen($json);
    for ($i = 0; $i < $len; $i++) {
        $ch = $json[$i];

        if ($inString) {
            $out .= $ch;
            if ($escape) {
                $escape = false;
            } elseif ($ch === '\\') {
                $escape = true;
            } elseif ($ch === '"') {
                $inString = false;
            }
            continue;
        }

        switch ($ch) {
            case '"':
                $inString = true;
                $out .= $ch;
                break;

            case '{':
            case '[':
                $out .= $ch . "\n";
                $indent++;
                $out .= str_repeat('  ', $indent);
                break;

            case '}':
            case ']':
                $out .= "\n";
                $indent = max(0, $indent - 1);
                $out .= str_repeat('  ', $indent) . $ch;
                break;

            case ',':
                $out .= $ch . "\n" . str_repeat('  ', $indent);
                break;

            case ':':
                $out .= ": ";
                break;

            default:
                // スペースは不要（例も不要）
                if ($ch !== ' ' && $ch !== "\n" && $ch !== "\r" && $ch !== "\t") {
                    $out .= $ch;
                }
                break;
        }
    }

    return $out . "\n";
}

/**
 * Notemod の data.json（categories/notes が “JSON文字列” の場合がある）を
 * バックアップ用に「配列構造」に展開し、キー順もなるべく例に寄せる
 */
function nm_build_backup_object(array $data, array $categoriesArr, array $notesArr): array
{
    // 例に出ている「存在しないなら null にしたいキー」
    $ensureNullKeys = [
        'categoryOrder',
        'sidebarState',
        'thizaState',
        'tema',
        'gistFile',
        'gistId',
        'gistToken',
        'sync',
    ];

    // まずベースは $data を引き継ぐ
    $base = $data;

    // categories / notes は配列に展開したものを入れる
    $base['categories'] = $categoriesArr;
    $base['notes'] = $notesArr;

    // 指定キーが無ければ null を入れる
    foreach ($ensureNullKeys as $k) {
        if (!array_key_exists($k, $base)) {
            $base[$k] = null;
        }
    }

    // 例の順番に寄せる（それ以外のキーは最後に付け足す）
    $orderedKeys = [
        'categories',
        'categoryOrder',
        'notes',
        'sidebarState',
        'thizaState',
        'tema',
        'gistFile',
        'gistId',
        'gistToken',
        'sync',
        'hasSelectedLanguage',
        'selectedLanguage',
    ];

    $out = [];
    foreach ($orderedKeys as $k) {
        if (array_key_exists($k, $base)) {
            $out[$k] = $base[$k];
        } else {
            // hasSelectedLanguage/selectedLanguage が無い場合もあるので null ではなく “無いまま” にする
        }
    }

    // それ以外のキーも失わない（機能維持）
    foreach ($base as $k => $v) {
        if (!array_key_exists($k, $out)) {
            $out[$k] = $v;
        }
    }

    return $out;
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
//    ★ここだけ変更：コピーではなく「指定フォーマットの整形JSON」を書き出す
// =====================

$backupBaseName = null;

if ($backupEnabled) {
    $backupFile = $notemodFile . $backupSuffix . date('Ymd-His');

    // バックアップ用オブジェクト作成（categories/notesを配列化＋順序寄せ＋null補完）
    $backupObj = nm_build_backup_object($data, $categoriesArr, $notesArr);

    // 例に合わせたインデント2のJSONを書き出す
    $backupJson = nm_json_pretty_2($backupObj);

    $ok = @file_put_contents($backupFile, $backupJson, LOCK_EX);
    if ($ok === false) {
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