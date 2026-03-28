<?php
declare(strict_types=1);

/*
 * append_api.php
 *
 * Notemod-selfhosted 用 追記API
 *
 * 【概要】
 * 指定したカテゴリ名とノート名、または target_note_id に一致する既存ノートを検索し、
 * そのノート本文(content)の末尾に、1行改行したうえで追記します。
 *
 * 追記本文は text パラメータで受け取ります。
 *
 * また、必要に応じて追記本文の前後へ以下の情報を自動挿入できます。
 * - 日付
 * - 時刻
 * - 日時
 * - 追記先カテゴリ名
 * - 追記先ノート名
 * - 別の指定ノートの本文（挿入元ノート）
 *
 * 区切り方法も指定できます。
 * - newline : 改行区切り
 * - space   : 半角スペース区切り
 * - none    : 連結
 *
 * 【対応】
 * - GET / POST
 * - application/json ボディ
 *
 * 【必須パラメータ】
 * - token
 * - text
 *
 * 【追記先指定】
 * 以下のどちらか
 * A)
 * - category        : 追記先カテゴリ名
 * - note            : 追記先ノート名
 *
 * B)
 * - target_note_id  : 追記先ノートID
 *
 * 【ユーザー指定】
 * - dir_user / user / username
 *   → api.php と同じ優先順で解決
 *
 * 【既存の任意パラメータ】
 * - insert_date=1
 * - insert_time=1
 * - insert_datetime=1
 * - insert_category=1
 * - insert_note=1
 *
 * - date_pos=before|after
 * - time_pos=before|after
 * - datetime_pos=before|after
 * - category_pos=before|after
 * - note_pos=before|after
 *
 * - separator=newline|space|none
 * - append_newline=1|0
 *
 * 【挿入元ノート本文を差し込む機能】
 * - source_category=挿入
 * - source_note=挿入テスト
 * - source_pos=before|after
 *
 * 【今回追加した機能】
 * 1. dry_run=1|0
 *    - 1 の場合は保存せず、結果プレビューのみ返す
 *
 * 2. label_datetime / label_date / label_time / label_category / label_note
 *    - 自動挿入値の前にラベル文字列を付ける
 *
 * 3. prefix / suffix
 *    - 追記ブロック全体の前後に固定文字列を追加する
 *
 * 4. target_note_id
 *    - category + note より優先して、ノートID直接指定で追記先を決定する
 *
 * 【設定の読み方】
 * - config.api.php の場所は auth_common.php の nm_api_config_path() に従う
 * - data.json の場所は config.api.php の DATA_JSON を使う
 */

require_once dirname(__DIR__) . '/auth_common.php';
require_once __DIR__ . '/../logger.php';

header('Content-Type: application/json; charset=utf-8');

/* =========================
 * 共通レスポンス
 * ========================= */
function append_respond_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);

    $pretty = $_GET['pretty'] ?? $_POST['pretty'] ?? '2';

    if ((string)$pretty === '2') {
        header('Content-Type: text/plain; charset=utf-8');

        $lines = [];

        foreach ($payload as $key => $value) {
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

    header('Content-Type: application/json; charset=utf-8');

    $flags = JSON_UNESCAPED_UNICODE;
    if ((string)$pretty === '1' || strtolower((string)$pretty) === 'true') {
        $flags |= JSON_PRETTY_PRINT;
    }

    echo json_encode($payload, $flags);
    exit;
}

/* =========================
 * 安全なロック付き data.json 読み込み/保存
 * ========================= */
function append_locked_load_notemod(string $path)
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

function append_locked_save_notemod($fp, array $data): bool
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

function append_locked_close($fp): void
{
    if (is_resource($fp)) {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }
}

/* =========================
 * Notemod形式HTML変換
 * ========================= */
function append_text_to_notemod_html_preserve_newlines(string $text): string
{
    $text = str_replace("\r\n", "\n", $text);
    $text = str_replace("\r", "\n", $text);

    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $lines = explode("\n", $escaped);

    if ($text === '') {
        return '';
    }

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

function append_text_to_notemod_html_as_appended_block(string $text): string
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

function append_notemod_html_to_plain_text(string $html): string
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
 * 補助
 * ========================= */
function append_param_bool(array $params, string $key, bool $default = false): bool
{
    if (!array_key_exists($key, $params)) return $default;
    $v = strtolower(trim((string)$params[$key]));
    return in_array($v, ['1', 'true', 'yes', 'on'], true);
}

function append_param_str(array $params, string $key, string $default = ''): string
{
    return isset($params[$key]) ? (string)$params[$key] : $default;
}

function append_separator(string $separator): string
{
    $separator = strtolower(trim($separator));
    return match ($separator) {
        'space' => ' ',
        'none'  => '',
        default => "\n",
    };
}

function append_now_str(string $format): string
{
    return date($format);
}

function append_find_category_id_by_name(array $categories, string $categoryName)
{
    foreach ($categories as $category) {
        if (!is_array($category)) continue;
        $name = (string)($category['name'] ?? '');
        if ($name === $categoryName) {
            return $category['id'] ?? null;
        }
    }
    return null;
}

function append_find_category_name_by_id(array $categories, $categoryId): string
{
    foreach ($categories as $category) {
        if (!is_array($category)) continue;
        if ((string)($category['id'] ?? '') === (string)$categoryId) {
            return (string)($category['name'] ?? '');
        }
    }
    return '';
}

function append_note_belongs_to_category(array $note, $categoryId): bool
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

function append_find_note_indexes(array $notes, $categoryId, string $noteTitle): array
{
    $matches = [];

    foreach ($notes as $index => $note) {
        if (!is_array($note)) continue;

        $title = (string)($note['title'] ?? '');
        if ($title !== $noteTitle) continue;

        if (append_note_belongs_to_category($note, $categoryId)) {
            $matches[] = $index;
        }
    }

    return $matches;
}

function append_find_note_index_by_id(array $notes, string $targetNoteId): ?int
{
    foreach ($notes as $index => $note) {
        if (!is_array($note)) continue;
        if ((string)($note['id'] ?? '') === $targetNoteId) {
            return $index;
        }
    }
    return null;
}

function append_build_labeled_value(string $label, string $value): string
{
    return $label !== '' ? ($label . $value) : $value;
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
 * 0. config.api.php 読み込み
 * ========================= */
$configFile = nm_api_config_path($dirUser !== '' ? $dirUser : null);
if (!file_exists($configFile)) {
    append_respond_json([
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
    append_respond_json([
        'status'  => 'error',
        'message' => 'Server not configured (EXPECTED_TOKEN / DATA_JSON)'
    ], 500);
}

/* =========================
 * 1. GET / POST / JSON body 統合
 * ========================= */
$jsonBody = [];
$ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode((string)$raw, true);
    if (is_array($decoded)) $jsonBody = $decoded;
}

$params = array_change_key_case($_GET, CASE_LOWER);
$params = $params + array_change_key_case($_POST, CASE_LOWER);
$params = $params + array_change_key_case($jsonBody, CASE_LOWER);

/* =========================
 * 2. token チェック
 * ========================= */
$token = (string)($params['token'] ?? '');
if (!hash_equals($EXPECTED_TOKEN, $token)) {
    append_respond_json([
        'status'  => 'error',
        'message' => 'Forbidden'
    ], 403);
}

/* =========================
 * 3. パラメータ取得
 * ========================= */
$text = append_param_str($params, 'text');
$targetCategory = trim(append_param_str($params, 'category'));
$targetNote     = trim(append_param_str($params, 'note'));
$targetNoteId   = trim(append_param_str($params, 'target_note_id'));

if ($text === '') {
    append_respond_json([
        'status'  => 'error',
        'message' => 'text is required'
    ], 400);
}

if ($targetNoteId === '' && ($targetCategory === '' || $targetNote === '')) {
    append_respond_json([
        'status'  => 'error',
        'message' => 'target_note_id or category + note are required'
    ], 400);
}

$dryRun = append_param_bool($params, 'dry_run', false);

$insertDate     = append_param_bool($params, 'insert_date', false);
$insertTime     = append_param_bool($params, 'insert_time', false);
$insertDatetime = append_param_bool($params, 'insert_datetime', false);
$insertCategory = append_param_bool($params, 'insert_category', false);
$insertNote     = append_param_bool($params, 'insert_note', false);

$datePos        = strtolower(append_param_str($params, 'date_pos', 'before'));
$timePos        = strtolower(append_param_str($params, 'time_pos', 'before'));
$datetimePos    = strtolower(append_param_str($params, 'datetime_pos', 'before'));
$categoryPos    = strtolower(append_param_str($params, 'category_pos', 'before'));
$notePos        = strtolower(append_param_str($params, 'note_pos', 'before'));

$separator      = append_separator(append_param_str($params, 'separator', 'newline'));
$appendNewline  = append_param_bool($params, 'append_newline', false);

$dateFormat     = append_param_str($params, 'date_format', 'Y-m-d');
$timeFormat     = append_param_str($params, 'time_format', 'H:i:s');
$datetimeFormat = append_param_str($params, 'datetime_format', 'Y-m-d H:i:s');

$labelDate      = append_param_str($params, 'label_date', '');
$labelTime      = append_param_str($params, 'label_time', '');
$labelDatetime  = append_param_str($params, 'label_datetime', '');
$labelCategory  = append_param_str($params, 'label_category', '');
$labelNote      = append_param_str($params, 'label_note', '');

$prefix         = append_param_str($params, 'prefix', '');
$suffix         = append_param_str($params, 'suffix', '');

$sourceCategory = trim(append_param_str($params, 'source_category'));
$sourceNote     = trim(append_param_str($params, 'source_note'));
$sourcePos      = strtolower(append_param_str($params, 'source_pos', 'before'));

/* =========================
 * 4. data.json 読み込み
 * ========================= */
if (!file_exists($notemodFile)) {
    append_respond_json([
        'status'  => 'error',
        'message' => 'data.json not found'
    ], 500);
}
if (!is_readable($notemodFile) || !is_writable($notemodFile)) {
    append_respond_json([
        'status'  => 'error',
        'message' => 'data.json not readable/writable'
    ], 500);
}

[$fp, $data, $loadErr] = append_locked_load_notemod($notemodFile);
if ($loadErr !== null || !is_array($data)) {
    append_respond_json([
        'status'  => 'error',
        'message' => 'failed to load data.json safely',
        'detail'  => $loadErr
    ], 500);
}

$categoriesArr = $data['categories'] ?? [];
$notesArr      = $data['notes'] ?? [];

if (!is_array($categoriesArr)) {
    append_locked_close($fp);
    append_respond_json([
        'status'  => 'error',
        'message' => 'categories is invalid in data.json'
    ], 500);
}
if (!is_array($notesArr)) {
    append_locked_close($fp);
    append_respond_json([
        'status'  => 'error',
        'message' => 'notes is invalid in data.json'
    ], 500);
}

/* =========================
 * 5. 追記先カテゴリ / ノート検索
 *    target_note_id が最優先
 * ========================= */
$targetIndex      = null;
$resolvedCategory = $targetCategory;
$resolvedNote     = $targetNote;
$resolvedNoteId   = '';
$targetCategoryId = null;

if ($targetNoteId !== '') {
    $targetIndex = append_find_note_index_by_id($notesArr, $targetNoteId);
    if ($targetIndex === null) {
        append_locked_close($fp);
        append_respond_json([
            'status'  => 'error',
            'message' => 'target note id not found'
        ], 404);
    }

    $resolvedNoteId = (string)($notesArr[$targetIndex]['id'] ?? '');
    $resolvedNote   = (string)($notesArr[$targetIndex]['title'] ?? '');

    $cats = $notesArr[$targetIndex]['categories'] ?? [];
    if (is_array($cats) && isset($cats[0])) {
        $targetCategoryId = $cats[0];
        $resolvedCategory = append_find_category_name_by_id($categoriesArr, $targetCategoryId);
    } else {
        $resolvedCategory = '';
    }
} else {
    $targetCategoryId = append_find_category_id_by_name($categoriesArr, $targetCategory);
    if ($targetCategoryId === null) {
        append_locked_close($fp);
        append_respond_json([
            'status'  => 'error',
            'message' => 'target category not found'
        ], 404);
    }

    $targetMatches = append_find_note_indexes($notesArr, $targetCategoryId, $targetNote);
    if (count($targetMatches) === 0) {
        append_locked_close($fp);
        append_respond_json([
            'status'  => 'error',
            'message' => 'target note not found'
        ], 404);
    }
    if (count($targetMatches) > 1) {
        append_locked_close($fp);
        append_respond_json([
            'status'  => 'error',
            'message' => 'multiple target notes matched'
        ], 409);
    }

    $targetIndex    = $targetMatches[0];
    $resolvedNoteId = (string)($notesArr[$targetIndex]['id'] ?? '');
    $resolvedCategory = $targetCategory;
    $resolvedNote     = $targetNote;
}

/* =========================
 * 6. 挿入ブロック生成
 * ========================= */
$beforeParts = [];
$afterParts  = [];

if ($prefix !== '') {
    $beforeParts[] = $prefix;
}

if ($insertDatetime) {
    $v = append_build_labeled_value($labelDatetime, append_now_str($datetimeFormat));
    if ($datetimePos === 'after') $afterParts[] = $v;
    else $beforeParts[] = $v;
} else {
    if ($insertDate) {
        $v = append_build_labeled_value($labelDate, append_now_str($dateFormat));
        if ($datePos === 'after') $afterParts[] = $v;
        else $beforeParts[] = $v;
    }
    if ($insertTime) {
        $v = append_build_labeled_value($labelTime, append_now_str($timeFormat));
        if ($timePos === 'after') $afterParts[] = $v;
        else $beforeParts[] = $v;
    }
}

if ($insertCategory) {
    $v = append_build_labeled_value($labelCategory, $resolvedCategory);
    if ($categoryPos === 'after') $afterParts[] = $v;
    else $beforeParts[] = $v;
}

if ($insertNote) {
    $v = append_build_labeled_value($labelNote, $resolvedNote);
    if ($notePos === 'after') $afterParts[] = $v;
    else $beforeParts[] = $v;
}

if ($sourceCategory !== '' && $sourceNote !== '') {
    $sourceCategoryId = append_find_category_id_by_name($categoriesArr, $sourceCategory);
    if ($sourceCategoryId === null) {
        append_locked_close($fp);
        append_respond_json([
            'status'  => 'error',
            'message' => 'source category not found'
        ], 404);
    }

    $sourceMatches = append_find_note_indexes($notesArr, $sourceCategoryId, $sourceNote);
    if (count($sourceMatches) === 0) {
        append_locked_close($fp);
        append_respond_json([
            'status'  => 'error',
            'message' => 'source note not found'
        ], 404);
    }
    if (count($sourceMatches) > 1) {
        append_locked_close($fp);
        append_respond_json([
            'status'  => 'error',
            'message' => 'multiple source notes matched'
        ], 409);
    }

    $sourceIndex = $sourceMatches[0];
    $sourceHtml = (string)($notesArr[$sourceIndex]['content'] ?? '');
    $sourceText = append_notemod_html_to_plain_text($sourceHtml);

    if ($sourcePos === 'after') $afterParts[] = $sourceText;
    else $beforeParts[] = $sourceText;
}

if ($suffix !== '') {
    $afterParts[] = $suffix;
}

$appendBlock = implode($separator, array_merge($beforeParts, [$text], $afterParts));
if ($appendNewline) {
    $appendBlock .= "\n";
}

/* =========================
 * 7. 既存本文へ追記
 * ========================= */
$oldContent = (string)($notesArr[$targetIndex]['content'] ?? '');

if ($oldContent === '') {
    $newContent = append_text_to_notemod_html_preserve_newlines($appendBlock);
} else {
    $appendHtml = append_text_to_notemod_html_as_appended_block($appendBlock);
    $newContent = $oldContent . $appendHtml;
}

$oldPlainText = append_notemod_html_to_plain_text($oldContent);
$newPlainText = append_notemod_html_to_plain_text($newContent);

/* =========================
 * 8. dry_run / 保存
 * ========================= */
if ($dryRun) {
    append_locked_close($fp);
    append_respond_json([
        'status'                     => 'ok',
        'dry_run'                    => true,
        'message'                    => 'Dry run completed',
        'dir_user'                   => $dirUser,
        'category'                   => $resolvedCategory,
        'note'                       => $resolvedNote,
        'note_id'                    => $resolvedNoteId,
        'appended_text'              => $appendBlock,
        'old_plain_text'             => $oldPlainText,
        'updated_plain_text_preview' => $newPlainText,
        'updated_at'                 => gmdate('Y-m-d\TH:i:s\Z')
    ], 200);
}

$notesArr[$targetIndex]['content'] = $newContent;
$notesArr[$targetIndex]['updatedAt'] = gmdate('Y-m-d\TH:i:s\Z');
$data['notes'] = $notesArr;

if (!append_locked_save_notemod($fp, $data)) {
    append_respond_json([
        'status'  => 'error',
        'message' => 'failed to save data.json safely'
    ], 500);
}

/* =========================
 * 9. 完了
 * ========================= */
append_respond_json([
    'status'        => 'ok',
    'dry_run'       => false,
    'message'       => 'Text appended successfully',
    'dir_user'      => $dirUser,
    'category'      => $resolvedCategory,
    'note'          => $resolvedNote,
    'note_id'       => $resolvedNoteId,
    'appended_text' => $appendBlock,
    'updated_at'    => gmdate('Y-m-d\TH:i:s\Z')
], 200);