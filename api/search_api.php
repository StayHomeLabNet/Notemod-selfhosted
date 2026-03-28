<?php
declare(strict_types=1);

/*
 * search_api.php
 *
 * Notemod-selfhosted 用 検索API
 *
 * 【概要】
 * - Notemod の categories / notes を検索する専用API
 * - 保存・更新・削除は行わず、検索結果のみを返す
 * - append_api.php の target_note_id を探す用途にも使える
 *
 * 【対応】
 * - GET / POST
 * - application/json body
 *
 * 【必須パラメータ】
 * - token
 * - q
 *
 * 【ユーザー指定】
 * - dir_user / user / username
 *   → api.php と同じ優先順で解決
 *
 * 【任意パラメータ】
 * - type=all|note_title|category|content
 * - category=カテゴリ名         // 検索対象カテゴリ絞り込み
 * - limit=20
 * - match=partial|exact
 * - case_sensitive=1|0
 * - snippet=1|0
 * - snippet_length=60
 * - include_content=1|0
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
function search_respond_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);

    $pretty = $_GET['pretty'] ?? $_POST['pretty'] ?? '2';

    if ((string)$pretty === '2') {
        header('Content-Type: text/plain; charset=utf-8');

        $lines = [];

        $status = (string)($payload['status'] ?? '');
        $query  = (string)($payload['query'] ?? '');
        $type   = (string)($payload['type'] ?? '');
        $count  = (string)($payload['count'] ?? '');

        if ($status !== '') $lines[] = 'status: ' . $status;
        if ($query !== '')  $lines[] = 'query: ' . $query;
        if ($type !== '')   $lines[] = 'type: ' . $type;
        if ($count !== '')  $lines[] = 'count: ' . $count;

        $results = $payload['results'] ?? null;
        if (is_array($results)) {
            $lines[] = '';
            $lines[] = '[results]';

            foreach ($results as $i => $row) {
                if (!is_array($row)) {
                    $lines[] = '- ' . (string)$row;
                    continue;
                }

                $lines[] = '';
                $lines[] = 'Result ' . ($i + 1);

                foreach ($row as $key => $value) {
                    if (is_array($value)) {
                        $lines[] = $key . ': ' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
                    } else {
                        $lines[] = $key . ': ' . $stringValue;
                    }
                }
            }
        } else {
            foreach ($payload as $key => $value) {
                if (in_array($key, ['status', 'query', 'type', 'count', 'results'], true)) {
                    continue;
                }

                if (is_array($value)) {
                    $lines[] = '';
                    $lines[] = '[' . $key . ']';
                    $lines[] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    continue;
                }

                if ($value === null) {
                    $lines[] = $key . ': ';
                    continue;
                }

                $stringValue = (string)$value;

                if (str_contains($stringValue, "\n")) {
                    $lines[] = '';
                    $lines[] = '[' . $key . ']';
                    $lines[] = $stringValue;
                } else {
                    $lines[] = $key . ': ' . $stringValue;
                }
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
 * 安全なロック付き data.json 読み込み
 * ========================= */
function search_locked_load_notemod(string $path)
{
    $fp = @fopen($path, 'c+');
    if ($fp === false) {
        return [null, null, 'open_failed'];
    }
    if (!@flock($fp, LOCK_SH)) {
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

function search_locked_close($fp): void
{
    if (is_resource($fp)) {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }
}

/* =========================
 * Notemod HTML -> plain text
 * ========================= */
function search_notemod_html_to_plain_text(string $html): string
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
function search_param_bool(array $params, string $key, bool $default = false): bool
{
    if (!array_key_exists($key, $params)) return $default;
    $v = strtolower(trim((string)$params[$key]));
    return in_array($v, ['1', 'true', 'yes', 'on'], true);
}

function search_param_str(array $params, string $key, string $default = ''): string
{
    return isset($params[$key]) ? (string)$params[$key] : $default;
}

function search_param_int(array $params, string $key, int $default = 0): int
{
    if (!isset($params[$key])) return $default;
    return (int)$params[$key];
}

function search_normalize_for_match(string $value, bool $caseSensitive): string
{
    return $caseSensitive ? $value : mb_strtolower($value, 'UTF-8');
}

function search_matches(string $haystack, string $needle, string $matchMode, bool $caseSensitive): bool
{
    $haystackN = search_normalize_for_match($haystack, $caseSensitive);
    $needleN   = search_normalize_for_match($needle, $caseSensitive);

    if ($matchMode === 'exact') {
        return $haystackN === $needleN;
    }

    return mb_strpos($haystackN, $needleN, 0, 'UTF-8') !== false;
}

function search_make_snippet(string $text, string $query, int $snippetLength, bool $caseSensitive): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = trim($text);

    if ($text === '') {
        return '';
    }

    $textN  = search_normalize_for_match($text, $caseSensitive);
    $queryN = search_normalize_for_match($query, $caseSensitive);

    $pos = mb_strpos($textN, $queryN, 0, 'UTF-8');
    if ($pos === false) {
        return mb_substr($text, 0, $snippetLength, 'UTF-8');
    }

    $qLen   = max(1, mb_strlen($query, 'UTF-8'));
    $half   = max(1, intdiv($snippetLength, 2));
    $start  = max(0, $pos - $half);
    $length = $snippetLength;

    $snippet = mb_substr($text, $start, $length, 'UTF-8');

    if ($start > 0) {
        $snippet = '...' . $snippet;
    }

    $textLen = mb_strlen($text, 'UTF-8');
    if (($start + $length) < $textLen) {
        $snippet .= '...';
    }

    return $snippet;
}

function search_find_category_name_by_id(array $categories, $categoryId): string
{
    foreach ($categories as $category) {
        if (!is_array($category)) continue;
        if ((string)($category['id'] ?? '') === (string)$categoryId) {
            return (string)($category['name'] ?? '');
        }
    }
    return '';
}

function search_note_primary_category_id(array $note)
{
    $cats = $note['categories'] ?? null;
    if (!is_array($cats) || !isset($cats[0])) {
        return null;
    }
    return $cats[0];
}

function search_note_in_filter_category(array $note, $filterCategoryId): bool
{
    if ($filterCategoryId === null) return true;

    $cats = $note['categories'] ?? null;
    if (!is_array($cats)) return false;

    foreach ($cats as $cid) {
        if ((string)$cid === (string)$filterCategoryId) {
            return true;
        }
    }
    return false;
}

function search_add_result(array &$results, array $row): void
{
    $results[] = $row;
}

function search_score(string $matchType, string $matchMode): int
{
    if ($matchType === 'note_title') {
        return $matchMode === 'exact' ? 100 : 80;
    }
    if ($matchType === 'category') {
        return $matchMode === 'exact' ? 90 : 70;
    }
    return $matchMode === 'exact' ? 60 : 50;
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
    search_respond_json([
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
    search_respond_json([
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
    if (is_array($decoded)) $jsonBody = $decoded;
}

$params = array_change_key_case($_GET, CASE_LOWER);
$params = $params + array_change_key_case($_POST, CASE_LOWER);
$params = $params + array_change_key_case($jsonBody, CASE_LOWER);

/* =========================
 * token チェック
 * ========================= */
$token = (string)($params['token'] ?? '');
if (!hash_equals($EXPECTED_TOKEN, $token)) {
    search_respond_json([
        'status'  => 'error',
        'message' => 'Forbidden'
    ], 403);
}

/* =========================
 * パラメータ取得
 * ========================= */
$q = trim(search_param_str($params, 'q'));
if ($q === '') {
    search_respond_json([
        'status'  => 'error',
        'message' => 'q is required'
    ], 400);
}

$type          = strtolower(search_param_str($params, 'type', 'all'));
$filterCategory = trim(search_param_str($params, 'category'));
$limit         = search_param_int($params, 'limit', 20);
$matchMode     = strtolower(search_param_str($params, 'match', 'partial'));
$caseSensitive = search_param_bool($params, 'case_sensitive', false);
$withSnippet   = search_param_bool($params, 'snippet', true);
$snippetLength = search_param_int($params, 'snippet_length', 60);
$includeContent = search_param_bool($params, 'include_content', false);

$allowedTypes = ['all', 'note_title', 'category', 'content'];
if (!in_array($type, $allowedTypes, true)) {
    search_respond_json([
        'status'  => 'error',
        'message' => 'invalid type'
    ], 400);
}

if (!in_array($matchMode, ['partial', 'exact'], true)) {
    search_respond_json([
        'status'  => 'error',
        'message' => 'invalid match'
    ], 400);
}

if ($limit <= 0) $limit = 20;
if ($limit > 100) $limit = 100;
if ($snippetLength <= 0) $snippetLength = 60;
if ($snippetLength > 500) $snippetLength = 500;

/* =========================
 * data.json 読み込み
 * ========================= */
if (!file_exists($notemodFile)) {
    search_respond_json([
        'status'  => 'error',
        'message' => 'data.json not found'
    ], 500);
}
if (!is_readable($notemodFile)) {
    search_respond_json([
        'status'  => 'error',
        'message' => 'data.json not readable'
    ], 500);
}

[$fp, $data, $loadErr] = search_locked_load_notemod($notemodFile);
if ($loadErr !== null || !is_array($data)) {
    search_respond_json([
        'status'  => 'error',
        'message' => 'failed to load data.json safely',
        'detail'  => $loadErr
    ], 500);
}

$categoriesArr = $data['categories'] ?? [];
$notesArr      = $data['notes'] ?? [];

if (!is_array($categoriesArr)) {
    search_locked_close($fp);
    search_respond_json([
        'status'  => 'error',
        'message' => 'categories is invalid in data.json'
    ], 500);
}
if (!is_array($notesArr)) {
    search_locked_close($fp);
    search_respond_json([
        'status'  => 'error',
        'message' => 'notes is invalid in data.json'
    ], 500);
}

/* =========================
 * 絞り込みカテゴリ解決
 * ========================= */
$filterCategoryId = null;
if ($filterCategory !== '') {
    foreach ($categoriesArr as $categoryObj) {
        if (!is_array($categoryObj)) continue;
        if ((string)($categoryObj['name'] ?? '') === $filterCategory) {
            $filterCategoryId = $categoryObj['id'] ?? null;
            break;
        }
    }

    if ($filterCategoryId === null) {
        search_locked_close($fp);
        search_respond_json([
            'status'  => 'ok',
            'query'   => $q,
            'type'    => $type,
            'count'   => 0,
            'results' => []
        ], 200);
    }
}

/* =========================
 * 検索実行
 * ========================= */
$results = [];

/* category 検索 */
if ($type === 'all' || $type === 'category') {
    foreach ($categoriesArr as $categoryObj) {
        if (!is_array($categoryObj)) continue;

        $categoryName = (string)($categoryObj['name'] ?? '');
        if ($categoryName === '') continue;

        if ($filterCategory !== '' && $categoryName !== $filterCategory) {
            continue;
        }

        if (search_matches($categoryName, $q, $matchMode, $caseSensitive)) {
            search_add_result($results, [
                'match_type' => 'category',
                'note_id'    => null,
                'title'      => null,
                'category'   => $categoryName,
                'snippet'    => '',
                'score'      => search_score('category', $matchMode),
            ]);
        }
    }
}

/* note_title/content 検索 */
foreach ($notesArr as $noteObj) {
    if (!is_array($noteObj)) continue;

    if (!search_note_in_filter_category($noteObj, $filterCategoryId)) {
        continue;
    }

    $noteId    = (string)($noteObj['id'] ?? '');
    $title     = (string)($noteObj['title'] ?? '');
    $content   = (string)($noteObj['content'] ?? '');
    $plainText = search_notemod_html_to_plain_text($content);

    $primaryCategoryId   = search_note_primary_category_id($noteObj);
    $primaryCategoryName = $primaryCategoryId !== null
        ? search_find_category_name_by_id($categoriesArr, $primaryCategoryId)
        : '';

    if (($type === 'all' || $type === 'note_title') && $title !== '') {
        if (search_matches($title, $q, $matchMode, $caseSensitive)) {
            $row = [
                'match_type' => 'note_title',
                'note_id'    => $noteId,
                'title'      => $title,
                'category'   => $primaryCategoryName,
                'snippet'    => '',
                'score'      => search_score('note_title', $matchMode),
            ];
            if ($includeContent) {
                $row['content'] = $plainText;
            }
            search_add_result($results, $row);
        }
    }

    if (($type === 'all' || $type === 'content') && $plainText !== '') {
        if (search_matches($plainText, $q, $matchMode, $caseSensitive)) {
            $row = [
                'match_type' => 'content',
                'note_id'    => $noteId,
                'title'      => $title,
                'category'   => $primaryCategoryName,
                'snippet'    => $withSnippet ? search_make_snippet($plainText, $q, $snippetLength, $caseSensitive) : '',
                'score'      => search_score('content', $matchMode),
            ];
            if ($includeContent) {
                $row['content'] = $plainText;
            }
            search_add_result($results, $row);
        }
    }
}

search_locked_close($fp);

/* =========================
 * relevance 順に並び替え
 * ========================= */
usort($results, static function (array $a, array $b): int {
    $scoreA = (int)($a['score'] ?? 0);
    $scoreB = (int)($b['score'] ?? 0);

    if ($scoreA !== $scoreB) {
        return $scoreB <=> $scoreA;
    }

    $catA = (string)($a['category'] ?? '');
    $catB = (string)($b['category'] ?? '');
    if ($catA !== $catB) {
        return strcmp($catA, $catB);
    }

    $titleA = (string)($a['title'] ?? '');
    $titleB = (string)($b['title'] ?? '');
    return strcmp($titleA, $titleB);
});

if (count($results) > $limit) {
    $results = array_slice($results, 0, $limit);
}

/* =========================
 * 完了
 * ========================= */
search_respond_json([
    'status' => 'ok',
    'query'  => $q,
    'type'   => $type,
    'count'  => count($results),
    'results' => $results,
], 200);