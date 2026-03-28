<?php
declare(strict_types=1);

/*
 * journal_api.php
 *
 * Notemod-selfhosted 用 ジャーナルAPI
 *
 * 【概要】
 * - 日付ベース / 月次 / 週次 / 固定ノートへ、自動または半自動で追記するAPI
 * - カテゴリやノートが存在しない場合、自動作成可能
 * - 日記、作業ログ、簡易メモ、タスク記録向け
 *
 * 【対応】
 * - GET / POST
 * - application/json body
 *
 * 【必須パラメータ】
 * - token
 * - text
 *
 * 【ユーザー指定】
 * - dir_user / user / username
 *
 * 【主な任意パラメータ】
 * - category=日記
 * - mode=date|month|week|fixed
 * - note=日報                  // mode=fixed のとき使用
 * - create_if_missing=1|0
 * - create_category_if_missing=1|0
 * - template=journal|log|plain|task
 * - insert_weekday=1|0
 * - weekday_lang=ja|en
 * - date_format=Y-m-d
 * - time_format=H:i:s
 * - datetime_format=Y-m-d H:i
 * - label_date=
 * - label_time=
 * - label_datetime=
 * - prefix=
 * - suffix=
 * - dry_run=1|0
 * - pretty=1|2
 *
 * 【pretty の挙動】
 * - 未指定   : pretty=2 と同じ（text/plain）
 * - pretty=1 : pretty JSON
 * - pretty=2 : text/plain
 */

require_once dirname(__DIR__) . '/auth_common.php';
require_once __DIR__ . '/../logger.php';

header('Content-Type: text/plain; charset=utf-8');

/* =========================
 * 共通レスポンス
 * ========================= */
function journal_respond(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);

    $pretty = $_GET['pretty'] ?? $_POST['pretty'] ?? '2';

    if ((string)$pretty === '1' || strtolower((string)$pretty) === 'true') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    header('Content-Type: text/plain; charset=utf-8');

    $order = [
        'status',
        'message',
        'dir_user',
        'category',
        'note',
        'note_id',
        'mode',
        'template',
        'created_category',
        'created_note',
        'dry_run',
        'updated_at',
        'appended_text',
        'old_plain_text',
        'updated_plain_text_preview',
    ];

    $lines = [];
    $used = [];

    foreach ($order as $key) {
        if (!array_key_exists($key, $payload)) {
            continue;
        }
        $used[$key] = true;
        $value = $payload[$key];

        if (is_array($value)) {
            $lines[] = '[' . $key . ']';
            $lines[] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $lines[] = '';
            continue;
        }

        if ($value === null) {
            $lines[] = $key . ': ';
            continue;
        }

        $stringValue = (string)$value;
        if (str_contains($stringValue, "\n")) {
            $lines[] = '[' . $key . ']';
            $lines[] = $stringValue;
            $lines[] = '';
        } else {
            $lines[] = $key . ': ' . $stringValue;
        }
    }

    foreach ($payload as $key => $value) {
        if (isset($used[$key])) {
            continue;
        }

        if (is_array($value)) {
            $lines[] = '[' . $key . ']';
            $lines[] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $lines[] = '';
            continue;
        }

        if ($value === null) {
            $lines[] = $key . ': ';
            continue;
        }

        $stringValue = (string)$value;
        if (str_contains($stringValue, "\n")) {
            $lines[] = '[' . $key . ']';
            $lines[] = $stringValue;
            $lines[] = '';
        } else {
            $lines[] = $key . ': ' . $stringValue;
        }
    }

    echo implode("\n", $lines);
    exit;
}

/* =========================
 * ファイル読み書き
 * ========================= */
function journal_locked_load_notemod(string $path)
{
    $fp = @fopen($path, 'c+');
    if ($fp === false) {
        return [null, null, 'open_failed'];
    }
    if (!@flock($fp, LOCK_EX)) {
        @fclose($fp);
        return [null, null, 'lock_failed'];
    }

    clearstatcache(true, $path);
    $raw = stream_get_contents($fp);
    if ($raw === false) {
        @flock($fp, LOCK_UN);
        @fclose($fp);
        return [null, null, 'read_failed'];
    }
    if ($raw === '') {
        $raw = '{}';
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        @flock($fp, LOCK_UN);
        @fclose($fp);
        return [null, null, 'json_invalid'];
    }

    return [$fp, $data, null];
}

function journal_locked_save_notemod($fp, array $data): bool
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    if (@ftruncate($fp, 0) === false) return false;
    if (@rewind($fp) === false) return false;
    if (@fwrite($fp, $json) === false) return false;
    @fflush($fp);
    @flock($fp, LOCK_UN);
    @fclose($fp);
    return true;
}

function journal_locked_close($fp): void
{
    if (is_resource($fp)) {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }
}

/* =========================
 * Notemod HTML helpers
 * ========================= */
function journal_text_to_notemod_html_preserve_newlines(string $text): string
{
    $text = str_replace("\r\n", "\n", $text);
    $text = str_replace("\r", "\n", $text);

    if ($text === '') {
        return '';
    }

    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $lines = explode("\n", $escaped);

    $out = '';
    $first = $lines[0] ?? '';
    if ($first === '') {
        $out .= '<div><br></div>';
    } else {
        $out .= $first;
    }

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

function journal_text_to_notemod_html_as_appended_block(string $text): string
{
    $text = str_replace("\r\n", "\n", $text);
    $text = str_replace("\r", "\n", $text);

    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $lines = explode("\n", $escaped);

    $out = '';
    foreach ($lines as $line) {
        if ($line === '') {
            $out .= '<div><br></div>';
        } else {
            $out .= '<div>' . $line . '</div>';
        }
    }

    return $out;
}

function journal_notemod_html_to_plain_text(string $html): string
{
    if ($html === '') {
        return '';
    }

    $text = $html;
    $text = preg_replace('/<div><br\s*\/?><\/div>/i', "\n", $text);
    $text = preg_replace('/<div>/i', "\n", $text);
    $text = preg_replace('/<\/div>/i', '', $text);
    $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
    $text = strip_tags((string)$text);
    $text = html_entity_decode((string)$text, ENT_QUOTES, 'UTF-8');
    $text = ltrim($text, "\n");

    return $text;
}

/* =========================
 * パラメータ helpers
 * ========================= */
function journal_param_bool(array $params, string $key, bool $default = false): bool
{
    if (!array_key_exists($key, $params)) return $default;
    $v = strtolower(trim((string)$params[$key]));
    return in_array($v, ['1', 'true', 'yes', 'on'], true);
}

function journal_param_str(array $params, string $key, string $default = ''): string
{
    return isset($params[$key]) ? (string)$params[$key] : $default;
}

function journal_param_int(array $params, string $key, int $default = 0): int
{
    if (!isset($params[$key])) return $default;
    return (int)$params[$key];
}

/* =========================
 * データ helpers
 * ========================= */
function journal_find_category_index_by_name(array $categories, string $categoryName): ?int
{
    foreach ($categories as $index => $category) {
        if (!is_array($category)) continue;
        if ((string)($category['name'] ?? '') === $categoryName) {
            return $index;
        }
    }
    return null;
}

function journal_find_category_name_by_id(array $categories, $categoryId): string
{
    foreach ($categories as $category) {
        if (!is_array($category)) continue;
        if ((string)($category['id'] ?? '') === (string)$categoryId) {
            return (string)($category['name'] ?? '');
        }
    }
    return '';
}

function journal_note_belongs_to_category(array $note, $categoryId): bool
{
    $cats = $note['categories'] ?? null;
    if (!is_array($cats)) return false;

    foreach ($cats as $cid) {
        if ((string)$cid === (string)$categoryId) {
            return true;
        }
    }
    return false;
}

function journal_find_note_index(array $notes, $categoryId, string $noteTitle): ?int
{
    foreach ($notes as $index => $note) {
        if (!is_array($note)) continue;
        if ((string)($note['title'] ?? '') !== $noteTitle) continue;
        if (journal_note_belongs_to_category($note, $categoryId)) {
            return $index;
        }
    }
    return null;
}

function journal_generate_id(string $prefix = ''): string
{
    try {
        return $prefix . bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        return $prefix . uniqid('', true);
    }
}

function journal_weekday_string(DateTimeImmutable $dt, string $lang): string
{
    $w = (int)$dt->format('w'); // 0=Sun
    $ja = ['日', '月', '火', '水', '木', '金', '土'];
    $en = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    return $lang === 'en' ? $en[$w] : $ja[$w];
}

function journal_note_title(DateTimeImmutable $dt, string $mode, string $fixedNote): string
{
    return match ($mode) {
        'month' => $dt->format('Y-m'),
        'week'  => $dt->format('o-\WW'),
        'fixed' => $fixedNote,
        default => $dt->format('Y-m-d'),
    };
}

function journal_build_entry(
    string $template,
    string $text,
    DateTimeImmutable $dt,
    bool $insertWeekday,
    string $weekdayLang,
    string $dateFormat,
    string $timeFormat,
    string $datetimeFormat,
    string $labelDate,
    string $labelTime,
    string $labelDatetime,
    string $prefix,
    string $suffix
): string {
    $parts = [];

    if ($prefix !== '') {
        $parts[] = $prefix;
    }

    $dateStr = $dt->format($dateFormat);
    $timeStr = $dt->format($timeFormat);
    $datetimeStr = $dt->format($datetimeFormat);

    if ($insertWeekday) {
        $weekday = journal_weekday_string($dt, $weekdayLang);
        $dateStr .= ' (' . $weekday . ')';

        // datetime_format の先頭が日付と同じ想定で、曜日付き日付 + 時刻に組み直す
        $datetimeStr = $dateStr . ' ' . $timeStr;
    }

    switch ($template) {
        case 'log':
            $header = '[' . ($labelDatetime !== '' ? $labelDatetime : '') . $datetimeStr . '] ' . $text;
            $parts[] = $header;
            break;

        case 'plain':
            if ($labelDatetime !== '') {
                $parts[] = $labelDatetime . $datetimeStr;
            }
            $parts[] = $text;
            break;

        case 'task':
            if ($labelDatetime !== '') {
                $parts[] = $labelDatetime . $datetimeStr;
            }
            $parts[] = '[ ] ' . $text;
            break;

        case 'journal':
        default:
            $header = ($labelDatetime !== '' ? $labelDatetime : '') . $datetimeStr;
            $parts[] = $header;
            $parts[] = $text;
            break;
    }

    if ($suffix !== '') {
        $parts[] = $suffix;
    }

    return implode("\n", $parts);
}

/* =========================
 * dir_user 解決
 * ========================= */
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

/* =========================
 * タイムゾーン設定
 * ========================= */
$tz = 'Pacific/Auckland';
$cfgCommonFile = nm_config_path($dirUser !== '' ? $dirUser : null);
if (file_exists($cfgCommonFile)) {
    $common = require $cfgCommonFile;
    if (is_array($common)) {
        $t = (string)($common['TIMEZONE'] ?? $common['timezone'] ?? '');
        if ($t !== '') $tz = $t;
    }
}
date_default_timezone_set($tz);

/* =========================
 * config.api.php 読み込み
 * ========================= */
$configFile = nm_api_config_path($dirUser !== '' ? $dirUser : null);
if (!file_exists($configFile)) {
    journal_respond([
        'status'  => 'error',
        'message' => 'config.api.php missing',
        'path'    => 'config/' . ($dirUser !== '' ? $dirUser : '<USER_NAME>') . '/config.api.php'
    ], 500);
}

$cfg = require $configFile;
if (!is_array($cfg)) $cfg = [];

$EXPECTED_TOKEN = (string)($cfg['EXPECTED_TOKEN'] ?? '');
$notemodFile    = (string)($cfg['DATA_JSON'] ?? '');

if ($EXPECTED_TOKEN === '' || $notemodFile === '') {
    journal_respond([
        'status'  => 'error',
        'message' => 'Server not configured (EXPECTED_TOKEN / DATA_JSON)'
    ], 500);
}

/* =========================
 * GET / POST / JSON body 統合
 * ========================= */
$jsonBody = [];
$ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode((string)$raw, true);
    if (is_array($decoded)) {
        $jsonBody = $decoded;
    }
}

$params = array_change_key_case($_GET, CASE_LOWER);
$params = $params + array_change_key_case($_POST, CASE_LOWER);
$params = $params + array_change_key_case($jsonBody, CASE_LOWER);

/* =========================
 * token チェック
 * ========================= */
$token = (string)($params['token'] ?? '');
if (!hash_equals($EXPECTED_TOKEN, $token)) {
    journal_respond([
        'status'  => 'error',
        'message' => 'Forbidden'
    ], 403);
}

/* =========================
 * パラメータ取得
 * ========================= */
$text = journal_param_str($params, 'text');
if ($text === '') {
    journal_respond([
        'status'  => 'error',
        'message' => 'text is required'
    ], 400);
}

$categoryName = trim(journal_param_str($params, 'category', '日記'));
$mode = strtolower(journal_param_str($params, 'mode', 'date'));
$fixedNote = trim(journal_param_str($params, 'note', ''));

if (!in_array($mode, ['date', 'month', 'week', 'fixed'], true)) {
    journal_respond([
        'status'  => 'error',
        'message' => 'invalid mode'
    ], 400);
}

if ($mode === 'fixed' && $fixedNote === '') {
    journal_respond([
        'status'  => 'error',
        'message' => 'note is required when mode=fixed'
    ], 400);
}

$template = strtolower(journal_param_str($params, 'template', 'journal'));
if (!in_array($template, ['journal', 'log', 'plain', 'task'], true)) {
    journal_respond([
        'status'  => 'error',
        'message' => 'invalid template'
    ], 400);
}

$createIfMissing = journal_param_bool($params, 'create_if_missing', true);
$createCategoryIfMissing = journal_param_bool($params, 'create_category_if_missing', true);
$insertWeekday = journal_param_bool($params, 'insert_weekday', false);
$weekdayLang = strtolower(journal_param_str($params, 'weekday_lang', 'ja'));
if (!in_array($weekdayLang, ['ja', 'en'], true)) {
    $weekdayLang = 'ja';
}

$dateFormat = journal_param_str($params, 'date_format', 'Y-m-d');
$timeFormat = journal_param_str($params, 'time_format', 'H:i:s');
$datetimeFormat = journal_param_str($params, 'datetime_format', 'Y-m-d H:i');

$labelDate = journal_param_str($params, 'label_date', '');
$labelTime = journal_param_str($params, 'label_time', '');
$labelDatetime = journal_param_str($params, 'label_datetime', '');

$prefix = journal_param_str($params, 'prefix', '');
$suffix = journal_param_str($params, 'suffix', '');

$dryRun = journal_param_bool($params, 'dry_run', false);

/* =========================
 * data.json 読み込み
 * ========================= */
if (!file_exists($notemodFile)) {
    journal_respond([
        'status'  => 'error',
        'message' => 'data.json not found'
    ], 500);
}
if (!is_readable($notemodFile) || !is_writable($notemodFile)) {
    journal_respond([
        'status'  => 'error',
        'message' => 'data.json not readable/writable'
    ], 500);
}

[$fp, $data, $loadErr] = journal_locked_load_notemod($notemodFile);
if ($loadErr !== null || !is_array($data)) {
    journal_respond([
        'status'  => 'error',
        'message' => 'failed to load data.json safely',
        'detail'  => $loadErr
    ], 500);
}

$categoriesArr = $data['categories'] ?? [];
$notesArr = $data['notes'] ?? [];

if (!is_array($categoriesArr) || !is_array($notesArr)) {
    journal_locked_close($fp);
    journal_respond([
        'status'  => 'error',
        'message' => 'invalid data structure'
    ], 500);
}

/* =========================
 * 追記先決定
 * ========================= */
$now = new DateTimeImmutable('now');
$noteTitle = journal_note_title($now, $mode, $fixedNote);

$createdCategory = false;
$createdNote = false;

$categoryIndex = journal_find_category_index_by_name($categoriesArr, $categoryName);
if ($categoryIndex === null) {
    if (!$createCategoryIfMissing) {
        journal_locked_close($fp);
        journal_respond([
            'status'  => 'error',
            'message' => 'target category not found'
        ], 404);
    }

    $newCategory = [
        'id'   => journal_generate_id('cat_'),
        'name' => $categoryName,
    ];
    $categoriesArr[] = $newCategory;
    $categoryIndex = array_key_last($categoriesArr);
    $createdCategory = true;
}

$categoryId = $categoriesArr[$categoryIndex]['id'] ?? null;
if ($categoryId === null || $categoryId === '') {
    journal_locked_close($fp);
    journal_respond([
        'status'  => 'error',
        'message' => 'invalid category id'
    ], 500);
}

$noteIndex = journal_find_note_index($notesArr, $categoryId, $noteTitle);
if ($noteIndex === null) {
    if (!$createIfMissing) {
        journal_locked_close($fp);
        journal_respond([
            'status'  => 'error',
            'message' => 'target note not found'
        ], 404);
    }

    $newNote = [
        'id'         => journal_generate_id('note_'),
        'title'      => $noteTitle,
        'content'    => '',
        'categories' => [$categoryId],
        'updatedAt'  => gmdate('Y-m-d\TH:i:s\Z'),
    ];
    $notesArr[] = $newNote;
    $noteIndex = array_key_last($notesArr);
    $createdNote = true;
}

$noteId = (string)($notesArr[$noteIndex]['id'] ?? '');

/* =========================
 * 追記ブロック生成
 * ========================= */
$appendedText = journal_build_entry(
    $template,
    $text,
    $now,
    $insertWeekday,
    $weekdayLang,
    $dateFormat,
    $timeFormat,
    $datetimeFormat,
    $labelDate,
    $labelTime,
    $labelDatetime,
    $prefix,
    $suffix
);

/* =========================
 * 新本文生成
 * ========================= */
$oldContent = (string)($notesArr[$noteIndex]['content'] ?? '');
if ($oldContent === '') {
    $newContent = journal_text_to_notemod_html_preserve_newlines($appendedText);
} else {
    $appendHtml = journal_text_to_notemod_html_as_appended_block($appendedText);
    $newContent = $oldContent . $appendHtml;
}

$oldPlainText = journal_notemod_html_to_plain_text($oldContent);
$newPlainText = journal_notemod_html_to_plain_text($newContent);

/* =========================
 * dry_run
 * ========================= */
if ($dryRun) {
    journal_locked_close($fp);
    journal_respond([
        'status'                     => 'ok',
        'message'                    => 'Dry run completed',
        'dir_user'                   => $dirUser,
        'category'                   => $categoryName,
        'note'                       => $noteTitle,
        'note_id'                    => $noteId,
        'mode'                       => $mode,
        'template'                   => $template,
        'created_category'           => $createdCategory,
        'created_note'               => $createdNote,
        'dry_run'                    => true,
        'updated_at'                 => gmdate('Y-m-d\TH:i:s\Z'),
        'appended_text'              => $appendedText,
        'old_plain_text'             => $oldPlainText,
        'updated_plain_text_preview' => $newPlainText,
    ], 200);
}

/* =========================
 * 保存
 * ========================= */
$notesArr[$noteIndex]['content'] = $newContent;
$notesArr[$noteIndex]['updatedAt'] = gmdate('Y-m-d\TH:i:s\Z');

$data['categories'] = $categoriesArr;
$data['notes'] = $notesArr;

if (!journal_locked_save_notemod($fp, $data)) {
    journal_respond([
        'status'  => 'error',
        'message' => 'failed to save data.json safely'
    ], 500);
}

/* =========================
 * 完了
 * ========================= */
journal_respond([
    'status'           => 'ok',
    'message'          => 'Journal entry appended successfully',
    'dir_user'         => $dirUser,
    'category'         => $categoryName,
    'note'             => $noteTitle,
    'note_id'          => $noteId,
    'mode'             => $mode,
    'template'         => $template,
    'created_category' => $createdCategory,
    'created_note'     => $createdNote,
    'dry_run'          => false,
    'updated_at'       => gmdate('Y-m-d\TH:i:s\Z'),
    'appended_text'    => $appendedText,
], 200);