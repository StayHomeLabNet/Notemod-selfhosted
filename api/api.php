<?php
// api.php
// 任意カテゴリにノートを追加する簡易 REST API
// - token 認証
// - Category / category の大小文字ゆれ吸収
// - category が無ければ INBOX
// - 指定カテゴリが無ければ作成
// - data.json へ追記

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

// 共通レスポンス関数（pretty対応）
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
// 0. 設定読み込み（config/config.api.php）
// =====================

$configFile = dirname(__DIR__) . '/config/config.api.php';
if (!file_exists($configFile)) {
    respond_json([
        'status'  => 'error',
        'message' => 'config.api.php missing',
    ], 500);
}

$cfg = require $configFile;
if (!is_array($cfg)) $cfg = [];

$EXPECTED_TOKEN = (string)($cfg['EXPECTED_TOKEN'] ?? '');
$notemodFile    = (string)($cfg['DATA_JSON'] ?? '');
$defaultColor   = (string)($cfg['DEFAULT_COLOR'] ?? '3478bd');

if ($EXPECTED_TOKEN === '' || $notemodFile === '') {
    respond_json([
        'status'  => 'error',
        'message' => 'Server not configured (EXPECTED_TOKEN / DATA_JSON)',
    ], 500);
}

// =====================
// 1. パラメータ正規化（大小文字吸収 + POST/GET統一）
// =====================

// POST(JSON)も来る可能性があるので一応対応
$jsonBody = [];
$ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $jsonBody = $decoded;
}

// GET/POST/JSON を小文字キーで統一（POST/JSONを優先）
$params = array_change_key_case($_GET, CASE_LOWER);
$params = $params + array_change_key_case($_POST, CASE_LOWER);
$params = $params + array_change_key_case($jsonBody, CASE_LOWER);

// =====================
// 2. トークンチェック
// =====================

$token = (string)($params['token'] ?? '');
if (!hash_equals($EXPECTED_TOKEN, $token)) {
    respond_json([
        'status'  => 'error',
        'message' => 'Forbidden',
    ], 403);
}

// =====================
// 3. パラメータ取得（text / title / category）
// =====================

$text          = trim((string)($params['text'] ?? ''));
$titleParam    = trim((string)($params['title'] ?? ''));
$categoryParam = trim((string)($params['category'] ?? '')); // ← Category/categoryどちらでもOK

if ($text === '') {
    respond_json([
        'status'  => 'error',
        'message' => 'text is required',
    ], 400);
}

// タイトル：指定がなければ「日付＋時間」
$noteTitle = ($titleParam !== '') ? $titleParam : date('Y-m-d H:i:s');

// カテゴリ名：指定がなければ "INBOX"
$categoryName = ($categoryParam !== '') ? $categoryParam : 'INBOX';

// =====================
// 4. Notemod の data.json を読み込む
// =====================

if (!file_exists($notemodFile)) {
    respond_json([
        'status'  => 'error',
        'message' => 'data.json not found',
    ], 500);
}
if (!is_readable($notemodFile) || !is_writable($notemodFile)) {
    respond_json([
        'status'  => 'error',
        'message' => 'data.json not readable/writable',
    ], 500);
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
// 5. categoryName のカテゴリーを探す or 作る
// =====================

$categoryId = null;

// 大小文字を区別せずカテゴリ名一致（MEMO / memo 等を同一扱いにしたい場合）
foreach ($categoriesArr as $cat) {
    $catName = (string)($cat['name'] ?? '');
    if ($catName !== '' && strcasecmp($catName, $categoryName) === 0) {
        $categoryId   = $cat['id'] ?? null;
        $categoryName = $catName; // 既存表記に寄せる
        break;
    }
}

if ($categoryId === null) {
    // ミリ秒ID（カテゴリ用）
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
$createdAt = gmdate('Y-m-d\TH:i:s\Z'); // v(ミリ秒)非対応環境でも壊れない形

// 改行を <br> に変換して保存（XSS対策）
$safeText = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
$safeText = nl2br($safeText, false); // <br /> ではなく <br>

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

// 元の形式が文字列なら文字列に戻す（互換性維持）
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
    respond_json([
        'status'  => 'error',
        'message' => 'failed to save data.json',
    ], 500);
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
    'note'     => $newNote,
]);