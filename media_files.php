<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_common.php';

/*
 * media_files.php
 * - UIは bak_settings.php と揃える（JP/EN, Dark/Light、右上トグル、上部にログイン中ユーザー名）
 * - 機能はそのまま：
 * - サーバー設定（折りたたみ）
 * - images/files 件数表示（cleanup_api.php dry_run=2 優先）
 * - 画像一覧（サムネ + ダウンロード） + ドロップアップロード
 * - ファイル一覧（file.json: JSON Lines） + ドロップアップロード
 */

nm_auth_require_login();
header('Content-Type: text/html; charset=utf-8');

// lang/theme
$ui    = nm_ui_bootstrap();
$lang  = $ui['lang'];
$theme = $ui['theme'];

// --------------------
// Auth / user (bak_settings.php と同じ)
// --------------------
$cfgAuth = nm_auth_load();
$user = (string)($cfgAuth['USERNAME'] ?? '');
if ($user === '') $user = 'unknown';

// i18n
$t = [
  'ja' => [
    'title' => 'メディア / ファイル',
    'logged_as' => 'ログイン中:',
    'back' => '戻る',
    'logout' => 'ログアウト',
    'lang_label' => '言語',
    'theme_label' => 'テーマ',
    'dark' => 'Dark',
    'light' => 'Light',

    'images' => '画像',
    'files'  => 'ファイル',

    'section_env' => 'サーバーのアップロード/ダウンロード関連設定（クリックで開く）',
    'env_help' => '※ 大きいファイルが失敗する場合は upload_max_filesize / post_max_size / max_input_time / max_execution_time / upload_tmp_dir / WAF 等を確認してください。',

    'section_images' => '画像一覧（/notemod-data/<user>/images/）',
    'no_images' => '画像がありません',
    'drop_images' => '画像をドロップしてアップロード',
    'or_click' => 'またはクリックして選択',

    'section_files' => 'ファイル一覧（file.json から）',
    'no_files' => 'file.json が無い、または履歴がありません',
    'drop_files' => 'ファイルをドロップしてアップロード',

    'col_thumb' => 'Thumb',
    'col_filename' => 'ファイル名',
    'col_created' => '作成日時',
    'col_size' => 'サイズ',
    'col_ext' => '拡張子',
    'col_dl' => 'DL',
    'col_orig' => 'オリジナル名',
    'col_stored' => '保存名',
    'col_select' => '選択',

    'btn_download' => 'Download',
    'upload_done' => 'アップロード完了。更新します',
    'upload_failed' => 'アップロードに失敗しました',
    'download_failed' => 'ダウンロードに失敗しました',
    'no_admin_token' => 'ADMIN_TOKEN が未設定のため、件数はフォルダ実数で表示しています',
    'btn_delete_all_images' => '画像ファイルを全削除',
    'btn_delete_all_files' => 'ファイルを全削除',
    'confirm_delete_all_images' => '最新画像を保護しつつ、他の画像ファイルを全削除します。よろしいですか？',
    'confirm_delete_all_files' => '最新ファイルを保護しつつ、他のファイルを全削除します。よろしいですか？',
    'cleanup_done' => '削除完了。更新します',
    'cleanup_failed' => '削除に失敗しました',
    'btn_delete_selected_images' => '選択した画像ファイルを削除',
    'btn_delete_selected_files' => '選択したファイルを削除',
    'confirm_delete_selected_images' => '選択した画像ファイルを削除します。よろしいですか？',
    'confirm_delete_selected_files' => '選択したファイルを削除します。よろしいですか？',
    'copied_image' => '画像をコピーしました',
  ],
  'en' => [
    'title' => 'Media / Files',
    'logged_as' => 'Logged in as:',
    'back' => 'Back',
    'logout' => 'Logout',
    'lang_label' => 'Language',
    'theme_label' => 'Theme',
    'dark' => 'Dark',
    'light' => 'Light',

    'images' => 'Images',
    'files'  => 'Files',

    'section_env' => 'Server upload/download settings (click to expand)',
    'env_help' => 'Tip: check upload_max_filesize / post_max_size / max_input_time / max_execution_time / upload_tmp_dir / WAF if large uploads fail.',

    'section_images' => 'Images (/notemod-data/<user>/images/)',
    'no_images' => 'No images',
    'drop_images' => 'Drop images to upload',
    'or_click' => 'or click to choose',

    'section_files' => 'Files (from file.json)',
    'no_files' => 'No file.json or no history',
    'drop_files' => 'Drop files to upload',

    'col_thumb' => 'Thumb',
    'col_filename' => 'Filename',
    'col_created' => 'Created',
    'col_size' => 'Size',
    'col_ext' => 'Ext',
    'col_dl' => 'DL',
    'col_orig' => 'Original name',
    'col_stored' => 'Stored name',
    'col_select' => 'Select',

    'btn_download' => 'Download',
    'upload_done' => 'Uploaded. Reloading...',
    'upload_failed' => 'Upload failed',
    'download_failed' => 'Download failed',
    'no_admin_token' => 'ADMIN_TOKEN is not set, so counts are computed from the folders.',
    'btn_delete_all_images' => 'Delete all image files',
    'btn_delete_all_files' => 'Delete all files',
    'confirm_delete_all_images' => 'Delete all image files except the latest protected image. Continue?',
    'confirm_delete_all_files' => 'Delete all files except the latest protected file. Continue?',
    'cleanup_done' => 'Cleanup complete. Reloading...',
    'cleanup_failed' => 'Cleanup failed',
    'btn_delete_selected_images' => 'Delete selected image files',
    'btn_delete_selected_files' => 'Delete selected files',
    'confirm_delete_selected_images' => 'Delete the selected image files. Continue?',
    'confirm_delete_selected_files' => 'Delete the selected files. Continue?',
    'copied_image' => 'Image copied to clipboard',
  ],
];
if (!isset($t[$lang])) $lang = 'ja';

// config
$configApiPath = __DIR__ . '/config/config.api.php';
if (!is_file($configApiPath)) { http_response_code(500); echo "config/config.api.php not found"; exit; }
$cfg = require $configApiPath;
if (!is_array($cfg)) $cfg = [];

$EXPECTED_TOKEN = (string)($cfg['EXPECTED_TOKEN'] ?? '');
$ADMIN_TOKEN    = (string)($cfg['ADMIN_TOKEN'] ?? '');
$DATA_JSON      = (string)($cfg['DATA_JSON'] ?? '');

if ($EXPECTED_TOKEN === '' || $DATA_JSON === '') { http_response_code(500); echo "Server not configured (EXPECTED_TOKEN / DATA_JSON)"; exit; }

// TIMEZONE (config/config.php) - 画面表示の日時に反映
$TIMEZONE = 'UTC';
$configPath = __DIR__ . '/config/config.php';
if (is_file($configPath)) {
  $cfg2 = require $configPath;
  if (is_array($cfg2) && isset($cfg2['TIMEZONE']) && is_string($cfg2['TIMEZONE']) && $cfg2['TIMEZONE'] !== '') {
    $TIMEZONE = $cfg2['TIMEZONE'];
  }
}
if (@date_default_timezone_set($TIMEZONE) === false) {
  date_default_timezone_set('UTC');
  $TIMEZONE = 'UTC';
}

function fmt_local_time_from_unix(int $unix): string {
  // date_default_timezone_set済みのローカルTZで表示
  return date('Y-m-d H:i:s', $unix);
}

function fmt_local_time_from_iso(string $iso): string {
  $iso = trim($iso);
  if ($iso === '') return '';
  try {
    $dt = new DateTime($iso);
    $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
    return $dt->format('Y-m-d H:i:s');
  } catch (Throwable $e) {
    return $iso;
  }
}


// USERNAME
$USERNAME = 'default';
$authFile = __DIR__ . '/config/auth.php';
if (file_exists($authFile)) {
  $auth = require $authFile;
  if (is_array($auth) && isset($auth['USERNAME'])) $USERNAME = (string)$auth['USERNAME'];
  elseif (defined('USERNAME')) $USERNAME = (string)USERNAME;
}
$USERNAME = preg_replace('/[^a-zA-Z0-9_-]/', '_', $USERNAME);
if ($USERNAME === '' || $USERNAME === null) $USERNAME = 'default';

// notemod-data root inference
$dataJsonDir = realpath(dirname($DATA_JSON));
$notemodDataRoot = $dataJsonDir ?: dirname($DATA_JSON);
if ($dataJsonDir && basename($dataJsonDir) === $USERNAME) {
  $parent = realpath(dirname($dataJsonDir));
  if ($parent) $notemodDataRoot = $parent;
}
$userDir   = rtrim($notemodDataRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $USERNAME;
$imagesDir = $userDir . DIRECTORY_SEPARATOR . 'images';
$filesDir  = $userDir . DIRECTORY_SEPARATOR . 'files';
$fileHistoryPath = $userDir . DIRECTORY_SEPARATOR . 'file.json';
$fileIndexPath  = $userDir . DIRECTORY_SEPARATOR . 'file_index.json';


// URLs
$uBase = nm_auth_base_url();
$apiDirUrl     = rtrim($uBase, '/') . '/api';
$apiUploadUrl  = $apiDirUrl . '/api.php';
$apiCleanupUrl = $apiDirUrl . '/cleanup_api.php';

// helpers
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function safe_basename(string $name): string {
  $name = basename($name);
  $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);
  return $name;
}

function real_under(string $baseDir, string $file): ?string {
  $baseReal = realpath($baseDir);
  if (!$baseReal) return null;
  $fullReal = realpath($baseDir . DIRECTORY_SEPARATOR . $file);
  if (!$fullReal) return null;
  if (strpos($fullReal, $baseReal) !== 0) return null;
  return $fullReal;
}

function detect_mime(string $path): string {
  $mime = 'application/octet-stream';
  if (function_exists('finfo_open')) {
    $f = @finfo_open(FILEINFO_MIME_TYPE);
    if ($f) {
      $m = @finfo_file($f, $path);
      if (is_string($m) && $m !== '') $mime = $m;
      @finfo_close($f);
    }
  }
  return $mime;
}

function send_binary(string $path, string $mime, string $downloadName): void {
  if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "File not found";
    exit;
  }
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
  header('X-Content-Type-Options: nosniff');
  header('Content-Type: ' . $mime);

  $downloadName = preg_replace('/[\r\n]+/', ' ', $downloadName);
  $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName);
  if ($fallback === '' || $fallback === null) $fallback = 'download.bin';
  $encoded = rawurlencode($downloadName);
  header('Content-Disposition: attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . $encoded);

  $size = filesize($path);
  if ($size !== false) header('Content-Length: ' . $size);

  $fp = fopen($path, 'rb');
  if (!$fp) { http_response_code(500); exit; }
  @flock($fp, LOCK_SH);
  while (!feof($fp)) {
    $buf = fread($fp, 8192);
    if ($buf === false) break;
    echo $buf;
  }
  @flock($fp, LOCK_UN);
  fclose($fp);
  exit;
}

function list_images(string $dir, int $limit = 500): array {
  if (!is_dir($dir)) return [];
  $items = [];
  $dh = opendir($dir);
  if (!$dh) return [];
  while (($f = readdir($dh)) !== false) {
    if ($f === '.' || $f === '..') continue;
    if (!preg_match('/\.(png|jpg|jpeg|webp|gif|heic|heif)$/i', $f)) continue;
    $path = $dir . DIRECTORY_SEPARATOR . $f;
    if (!is_file($path)) continue;
    $items[] = [
      'stored_filename' => $f,
      'ext' => strtolower(pathinfo($f, PATHINFO_EXTENSION)),
      'size' => filesize($path) ?: 0,
      'mtime' => filemtime($path) ?: 0,
    ];
    if (count($items) >= $limit) break;
  }
  closedir($dh);
  usort($items, fn($a,$b) => ($b['mtime'] <=> $a['mtime']));
  return $items;
}

function parse_file_history_jsonl(string $path, int $limit = 2000): array {
  if (!is_file($path) || !is_readable($path)) return [];
  $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (!is_array($lines)) return [];
  $rows = [];
  foreach (array_reverse($lines) as $line) {
    $obj = json_decode($line, true);
    if (!is_array($obj)) continue;
    $rows[] = $obj;
    if (count($rows) >= $limit) break;
  }
  return $rows;
}


function parse_file_index_json(string $path): array {
  // file_index.json の形式:
  // { v, generated_at(_unix), count, files:[ {filename, original_name, ext, mime, size, created_at, created_at_unix, sha256, file_id?}, ... ] }
  if (!is_file($path) || !is_readable($path)) return [];
  $raw = @file_get_contents($path);
  if (!is_string($raw) || $raw === '') return [];
  $json = json_decode($raw, true);
  if (!is_array($json)) return [];
  if (!isset($json['files']) || !is_array($json['files'])) return [];
  $out = [];
  foreach ($json['files'] as $row) {
    if (!is_array($row)) continue;
    if (!isset($row['filename']) || (string)$row['filename'] === '') continue;
    $out[] = $row;
    if (count($out) >= 500) break;
  }
  return $out;
}

function count_dir_files(string $dir, string $pattern = null): int {
  if (!is_dir($dir)) return 0;
  $n = 0;
  $dh = opendir($dir);
  if (!$dh) return 0;
  while (($f = readdir($dh)) !== false) {
    if ($f === '.' || $f === '..') continue;
    $path = $dir . DIRECTORY_SEPARATOR . $f;
    if (!is_file($path)) continue;
    if ($pattern && !preg_match($pattern, $f)) continue;
    $n++;
  }
  closedir($dh);
  return $n;
}

function call_cleanup_count(string $url, string $adminToken, string $modeKey): ?int {
  if ($adminToken === '') return null;

  $post = http_build_query([
    'token' => $adminToken,
    $modeKey => '1',
    'dry_run' => '2',
  ]);

  $opts = [
    'http' => [
      'method' => 'POST',
      'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                  "Content-Length: " . strlen($post) . "\r\n",
      'content' => $post,
      'timeout' => 8,
    ]
  ];
  $ctx = stream_context_create($opts);
  $raw = @file_get_contents($url, false, $ctx);
  if (!is_string($raw) || $raw === '') return null;
  $json = json_decode($raw, true);
  if (!is_array($json)) return null;

  $candidates = [];
  if ($modeKey === 'purge_images') {
    $candidates = [
      $json['image']['count'] ?? null,
      $json['images']['count'] ?? null,
      $json['count'] ?? null,
    ];
  } elseif ($modeKey === 'purge_files') {
    $candidates = [
      $json['files']['count'] ?? null,
      $json['file']['count'] ?? null,
      $json['count'] ?? null,
    ];
  }
  foreach ($candidates as $v) {
    if (is_int($v)) return $v;
    if (is_numeric($v)) return (int)$v;
  }
  return null;
}

// quick endpoints
if (isset($_GET['thumb']) && $_GET['thumb'] === '1') {
  $f = safe_basename((string)($_GET['f'] ?? ''));
  $f = urldecode($f);
  $real = real_under($imagesDir, $f);
  if (!$real) { http_response_code(404); exit; }
  $mime = detect_mime($real);
  header('Content-Type: ' . $mime);
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  readfile($real);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'download') {
  $token = (string)($_POST['token'] ?? '');
  if (!hash_equals($EXPECTED_TOKEN, $token)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status'=>'error','message'=>'Forbidden'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  $kind = (string)($_POST['kind'] ?? '');
  $stored = safe_basename((string)($_POST['stored'] ?? ''));
  $orig = (string)($_POST['orig'] ?? '');

  if ($kind === 'image') {
    if ($stored === '') { http_response_code(400); exit; }
    $real = real_under($imagesDir, $stored);
    if (!$real) { http_response_code(404); exit; }
    $mime = detect_mime($real);
    send_binary($real, $mime, $stored);
  }
  if ($kind === 'file') {
    if ($stored === '') { http_response_code(400); exit; }
    $real = real_under($filesDir, $stored);
    if (!$real) { http_response_code(404); exit; }
    $mime = detect_mime($real);
    $dlName = $orig !== '' ? safe_basename($orig) : $stored;
    send_binary($real, $mime, $dlName);
  }

  http_response_code(400);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['status'=>'error','message'=>'unknown kind'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

// page data
$iniKeys = [
  'file_uploads','upload_max_filesize','post_max_size','max_file_uploads','max_input_time',
  'max_execution_time','memory_limit','upload_tmp_dir','max_input_vars','zlib.output_compression',
  'output_buffering','open_basedir',
];
$ini = [];
foreach ($iniKeys as $k) $ini[$k] = ini_get($k);

$imageCount = call_cleanup_count($apiCleanupUrl, $ADMIN_TOKEN, 'purge_images');
$fileCount  = call_cleanup_count($apiCleanupUrl, $ADMIN_TOKEN, 'purge_files');
if ($imageCount === null) $imageCount = count_dir_files($imagesDir, '/\.(png|jpg|jpeg|webp|gif|heic|heif)$/i');
if ($fileCount  === null) $fileCount  = count_dir_files($filesDir);

$images = list_images($imagesDir, 500);

// files: file_index.json を優先して表示（無い場合は file.json の履歴から復元）
$files = [];
if (is_file($fileIndexPath)) {
  $files = parse_file_index_json($fileIndexPath);
} else {
  $filesHistory = parse_file_history_jsonl($fileHistoryPath, 2000);
  $seen = [];
  foreach ($filesHistory as $row) {
    $stored = (string)($row['filename'] ?? '');
    if ($stored === '') continue;
    if (isset($seen[$stored])) continue;
    $seen[$stored] = true;
    $files[] = $row;
    if (count($files) >= 500) break;
  }
}


// toggles/back/logout
$u = nm_ui_toggle_urls('/media_files.php', $lang, $theme);
$logoutUrl = nm_ui_url('/logout.php');
$base = nm_auth_base_url();
$backUrl = rtrim($base, '/') . '/';
?>
<!doctype html>
<html lang="<?=htmlspecialchars($lang, ENT_QUOTES, 'UTF-8')?>" data-theme="<?=htmlspecialchars($theme, ENT_QUOTES, 'UTF-8')?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script>
    (function(){
      try{
        var u = new URL(window.location.href);
        if(!u.searchParams.has('lang')){
          var sel = localStorage.getItem('selectedLanguage') || '';
          var lang = (sel === 'JA') ? 'ja' : 'en';
          u.searchParams.set('lang', lang);
          window.location.replace(u.toString());
        }
      }catch(e){}
    })();
  </script>
  <title><?=htmlspecialchars($t[$lang]['title'], ENT_QUOTES, 'UTF-8')?></title>
  <style>
    html[data-theme="dark"]{
      --bg0:#070b14; --bg1:#0b1222;
      --card:rgba(15,23,42,.78);
      --card2:rgba(2,6,23,.22);
      --line:rgba(148,163,184,.20);
      --text:#e5e7eb; --muted:#a3b0c2;
      --accent:#a2c1f4; --ok:#34d399; --danger:#fb7185;
      --shadow: 0 18px 50px rgba(0,0,0,.55);
    }
    html[data-theme="light"]{
      --bg0:#f6f8fc; --bg1:#eef2ff;
      --card:rgba(255,255,255,.82);
      --card2:rgba(255,255,255,.70);
      --line:rgba(15,23,42,.12);
      --text:#0b1222; --muted:#4b5563;
      --accent:#2563eb; --ok:#10b981; --danger:#e11d48;
      --shadow: 0 18px 50px rgba(15,23,42,.14);
    }
    :root{ --r:18px; }
    *{box-sizing:border-box}
    body{
      margin:0; min-height:100vh;
      display:flex; align-items:center; justify-content:center;
      font-family: system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans JP",sans-serif;
      color:var(--text);
      background:
        radial-gradient(900px 600px at 20% 10%, color-mix(in srgb, var(--accent) 18%, transparent), transparent 60%),
        radial-gradient(800px 600px at 80% 30%, rgba(16,185,129,.10), transparent 55%),
        linear-gradient(180deg, var(--bg0), var(--bg1));
      padding:18px;
    }
    .wrap{ width:min(1024px, 100%); display:grid; gap:14px; }
    .card{
      background:var(--card);
      border:1px solid var(--line);
      border-radius:var(--r);
      box-shadow:var(--shadow);
      overflow:hidden;
      backdrop-filter: blur(10px);
      position: relative;
    }
    .head{
      padding:18px;
      background:linear-gradient(180deg, color-mix(in srgb, var(--accent) 10%, transparent), transparent);
      border-bottom:1px solid var(--line);
      display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;
      padding-bottom: 10px;
    }
    .title{ font-weight:900; letter-spacing:.3px; font-size:18px; margin:0; }
    .sub{ color:var(--muted); font-size:12px; margin-top:6px; }
    .left{ display:flex; flex-direction:column; gap:4px; }
    .head .right{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
    .btn{
      display:inline-flex; align-items:center; gap:8px;
      padding:10px 12px;
      border-radius:999px;
      border:1px solid var(--line);
      background:color-mix(in srgb, var(--card2) 70%, transparent);
      color:var(--text);
      cursor:pointer; text-decoration:none;
      font-size:13px; font-weight:700;
      transition: .15s ease;
      user-select:none;
    }
    .btn:hover{ transform: translateY(-1px); border-color: color-mix(in srgb, var(--accent) 38%, var(--line)); }
    .btn.red{ border-color: color-mix(in srgb, var(--danger) 35%, var(--line)); color: color-mix(in srgb, var(--danger) 75%, var(--text)); }
    .btn.red:hover{ border-color: color-mix(in srgb, var(--danger) 60%, var(--line)); }
    .body{ padding:18px; display:grid; gap:14px; }
    .pill{
      display:inline-flex; gap:10px; align-items:center; flex-wrap:wrap;
      padding:10px 12px;
      border-radius:999px;
      border:1px solid var(--line);
      background:color-mix(in srgb, var(--card2) 75%, transparent);
      font-size:13px;
    }
    .pill b{ font-weight:900; }
    .muted{ color:var(--muted); font-size:12px; }
    .toggles{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .toggle-row{ display:flex; align-items:center; gap:8px; }
.toggle-row span{ font-size:12px; color:var(--muted); }
    .pill a{
      text-decoration:none; color:var(--muted);
      font-weight:800; font-size:12px;
      padding:6px 8px; border-radius:999px;
      border:1px solid transparent;
    }
    .pill a.active{
      color:var(--text);
      border-color: color-mix(in srgb, var(--accent) 45%, var(--line));
      background: color-mix(in srgb, var(--accent) 12%, transparent);
    }
    details summary{ cursor:pointer; font-weight:900; }
    details summary::-webkit-details-marker{ display:none; }
    details summary:before{ content:"▸"; margin-right:8px; color:var(--muted); }
    details[open] summary:before{ content:"▾"; }
    table{ width:100%; border-collapse: collapse; min-width: 720px; }
    th, td{ border-bottom:1px solid var(--line); padding:10px 10px; font-size:13px; vertical-align:middle; }
    th{
      text-align:left;
      color:color-mix(in srgb, var(--text) 80%, var(--muted));
      background: color-mix(in srgb, var(--card2) 85%, transparent);
      position: sticky; top:0; z-index:1;
    }
    td.right{ text-align:right; }
    .listbox{
      border:1px solid var(--line);
      border-radius:14px;
      overflow:auto;
      max-height: 520px;
      background: color-mix(in srgb, var(--card2) 75%, transparent);
    }
    .thumb{
      width:64px; height:64px; object-fit:cover;
      border-radius:12px;
      border:1px solid var(--line);
      background: rgba(255,255,255,.06);
    }
    .dropzone{
      border:2px dashed color-mix(in srgb, var(--muted) 55%, transparent);
      border-radius:16px;
      padding:16px;
      text-align:center;
      background: color-mix(in srgb, var(--card2) 70%, transparent);
      cursor:pointer;
      transition:.12s ease;
    }
    .dropzone.drag{
      border-color: color-mix(in srgb, var(--accent) 65%, var(--line));
      background: color-mix(in srgb, var(--accent) 10%, transparent);
      transform: translateY(-1px);
    }
    .small{ font-size:12px; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="head">
        <div class="left">
          <h1 class="title"><?=htmlspecialchars($t[$lang]['title'], ENT_QUOTES, 'UTF-8')?></h1>
          <div class="sub">
            <?=htmlspecialchars($t[$lang]['logged_as'], ENT_QUOTES, 'UTF-8')?> <b><?=htmlspecialchars($user, ENT_QUOTES, 'UTF-8')?></b>
          </div>
        </div>

        <div class="right">
          <a class="btn" href="<?=htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8')?>">← <?=htmlspecialchars($t[$lang]['back'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="btn red" href="<?=htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['logout'], ENT_QUOTES, 'UTF-8')?></a>


        <div class="toggles">
          <div class="toggle-row">
            <span><?=htmlspecialchars($t[$lang]['lang_label'], ENT_QUOTES, 'UTF-8')?></span>
            <div class="pill">
              <a href="<?=htmlspecialchars($u['langJa'], ENT_QUOTES, 'UTF-8')?>" class="<?= $lang==='ja'?'active':'' ?>">JP</a>
              <a href="<?=htmlspecialchars($u['langEn'], ENT_QUOTES, 'UTF-8')?>" class="<?= $lang==='en'?'active':'' ?>">EN</a>
            </div>
        </div>
          </div>

          <div class="toggle-row">
            <span><?=htmlspecialchars($t[$lang]['theme_label'], ENT_QUOTES, 'UTF-8')?></span>
            <div class="pill">
              <a href="<?=htmlspecialchars($u['dark'], ENT_QUOTES, 'UTF-8')?>" class="<?= $theme==='dark'?'active':'' ?>"><?=htmlspecialchars($t[$lang]['dark'], ENT_QUOTES, 'UTF-8')?></a>
              <a href="<?=htmlspecialchars($u['light'], ENT_QUOTES, 'UTF-8')?>" class="<?= $theme==='light'?'active':'' ?>"><?=htmlspecialchars($t[$lang]['light'], ENT_QUOTES, 'UTF-8')?></a>
            </div>
          </div>
        </div>
      </div>

      <div class="body">
        <div class="pill">
          <span><?=htmlspecialchars($t[$lang]['images'], ENT_QUOTES, 'UTF-8')?>: <b><?=htmlspecialchars((string)$imageCount, ENT_QUOTES, 'UTF-8')?></b></span>
          <span><?=htmlspecialchars($t[$lang]['files'], ENT_QUOTES, 'UTF-8')?>: <b><?=htmlspecialchars((string)$fileCount, ENT_QUOTES, 'UTF-8')?></b></span>
          <span class="muted">User: <b><?=htmlspecialchars($USERNAME, ENT_QUOTES, 'UTF-8')?></b></span>
          <span class="muted">TZ: <b><?=htmlspecialchars($TIMEZONE, ENT_QUOTES, 'UTF-8')?></b></span>
        </div>

        <?php if ($ADMIN_TOKEN === ''): ?>
          <div class="muted"><?=htmlspecialchars($t[$lang]['no_admin_token'], ENT_QUOTES, 'UTF-8')?></div>
        <?php endif; ?>

        <div class="card" style="box-shadow:none">
          <div class="body">
            <details>
              <summary><?=htmlspecialchars($t[$lang]['section_env'], ENT_QUOTES, 'UTF-8')?></summary>
              <div style="margin-top:12px" class="listbox">
                <table>
                  <thead><tr><th>Key</th><th>Value</th></tr></thead>
                  <tbody>
                    <?php foreach ($ini as $k=>$v): ?>
                      <tr><td><?=h($k)?></td><td><?=h((string)$v)?></td></tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <div class="muted" style="margin-top:10px"><?=htmlspecialchars($t[$lang]['env_help'], ENT_QUOTES, 'UTF-8')?></div>
            </details>
          </div>
        </div>

        <div class="card" style="box-shadow:none">
          <div class="body">
            <div style="font-weight:900"><?=htmlspecialchars($t[$lang]['section_images'], ENT_QUOTES, 'UTF-8')?></div>
            <div class="listbox">
              <table>
                <thead>
                  <tr>
                    <th style="width:70px"><?=htmlspecialchars($t[$lang]['col_select'] ?? 'Select', ENT_QUOTES, 'UTF-8')?></th>
                    <th style="width:90px"><?=htmlspecialchars($t[$lang]['col_thumb'], ENT_QUOTES, 'UTF-8')?></th>
                    <th><?=htmlspecialchars($t[$lang]['col_filename'], ENT_QUOTES, 'UTF-8')?></th>
                    <th style="width:160px"><?=htmlspecialchars($t[$lang]['col_created'], ENT_QUOTES, 'UTF-8')?></th>
                    <th style="width:120px" class="right"><?=htmlspecialchars($t[$lang]['col_size'], ENT_QUOTES, 'UTF-8')?></th>
                    <th style="width:80px"><?=htmlspecialchars($t[$lang]['col_ext'], ENT_QUOTES, 'UTF-8')?></th>
                    <th style="width:120px"><?=htmlspecialchars($t[$lang]['col_dl'], ENT_QUOTES, 'UTF-8')?></th>
                  </tr>
                </thead>
                <tbody>
                <?php if (count($images) === 0): ?>
                  <tr><td colspan="7" class="muted"><?=htmlspecialchars($t[$lang]['no_images'], ENT_QUOTES, 'UTF-8')?></td></tr>
                <?php else: foreach ($images as $img): ?>
                  <?php
                    $fname = (string)$img['stored_filename'];
                    $mtime = (int)$img['mtime'];
                    $size  = (int)$img['size'];
                    $ext   = (string)$img['ext'];
                  ?>
                  <tr>
                    <td><input type="checkbox" class="media-check media-check-image" value="<?=h($fname)?>"></td>
                    <td>
                      <img class="thumb copy-thumb" style="cursor:pointer;" title="<?=htmlspecialchars($t[$lang]['copied_image'], ENT_QUOTES, 'UTF-8')?>" src="<?=h($_SERVER['PHP_SELF'])?>?thumb=1&f=<?=h(urlencode($fname))?>" alt="" data-filename="<?=h($fname)?>">
                    </td>
                    <td><?=h($fname)?></td>
                    <td><?=h(fmt_local_time_from_unix($mtime))?></td>
                    <td class="right"><?=h(number_format($size))?></td>
                    <td><?=h($ext)?></td>
                    <td>
                      <form method="POST" action="<?=h($_SERVER['PHP_SELF'])?>" style="margin:0">
  <input type="hidden" name="action" value="download">
  <input type="hidden" name="token" value="<?=h($EXPECTED_TOKEN)?>">
  <input type="hidden" name="kind" value="image">
  <input type="hidden" name="stored" value="<?=h($fname)?>">
  <input type="hidden" name="orig" value="<?=h($fname)?>">
  <button class="btn" type="submit"><?=htmlspecialchars($t[$lang]['btn_download'], ENT_QUOTES, 'UTF-8')?></button>
</form>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>

            <div class="dropzone" id="dzImage" style="margin-top:12px">
              <div style="font-weight:900"><?=htmlspecialchars($t[$lang]['drop_images'], ENT_QUOTES, 'UTF-8')?></div>
              <div class="muted small"><?=htmlspecialchars($t[$lang]['or_click'], ENT_QUOTES, 'UTF-8')?></div>
              <input type="file" id="pickImage" accept="image/*" multiple style="display:none">
            </div>

            <div style="margin-top:12px">
              <button class="btn red" type="button" id="btnDeleteAllImages"><?=htmlspecialchars($t[$lang]['btn_delete_all_images'], ENT_QUOTES, 'UTF-8')?></button>
            </div>
          </div>
        </div>

        <div class="card" style="box-shadow:none">
          <div class="body">
            <div style="font-weight:900"><?=htmlspecialchars($t[$lang]['section_files'], ENT_QUOTES, 'UTF-8')?></div>
            <div class="listbox">
              <table>
                <thead>
                  <tr>
                    <th style="width:70px"><?=htmlspecialchars($t[$lang]['col_select'] ?? 'Select', ENT_QUOTES, 'UTF-8')?></th>
                    <th><?=htmlspecialchars($t[$lang]['col_orig'], ENT_QUOTES, 'UTF-8')?></th>
                    <th><?=htmlspecialchars($t[$lang]['col_stored'], ENT_QUOTES, 'UTF-8')?></th>
                    <th style="width:160px"><?=htmlspecialchars($t[$lang]['col_created'], ENT_QUOTES, 'UTF-8')?></th>
                    <th style="width:120px" class="right"><?=htmlspecialchars($t[$lang]['col_size'], ENT_QUOTES, 'UTF-8')?></th>
                    <th style="width:80px"><?=htmlspecialchars($t[$lang]['col_ext'], ENT_QUOTES, 'UTF-8')?></th>
                    <th style="width:120px"><?=htmlspecialchars($t[$lang]['col_dl'], ENT_QUOTES, 'UTF-8')?></th>
                  </tr>
                </thead>
                <tbody>
                <?php if (count($files) === 0): ?>
                  <tr><td colspan="7" class="muted"><?=htmlspecialchars($t[$lang]['no_files'], ENT_QUOTES, 'UTF-8')?></td></tr>
                <?php else: foreach ($files as $row): ?>
                  <?php
                    $orig = (string)($row['original_name'] ?? '');
                    $stored = (string)($row['filename'] ?? '');
                    $createdAt = (string)($row['created_at'] ?? '');
                    $size  = (int)($row['size'] ?? 0);
                    $ext   = (string)($row['ext'] ?? '');
                  ?>
                  <tr>
                    <td><input type="checkbox" class="media-check media-check-file" value="<?=h($stored)?>"></td>
                    <td><?=h($orig)?></td>
                    <td class="muted"><?=h($stored)?></td>
                    <td><?=h(fmt_local_time_from_iso($createdAt))?></td>
                    <td class="right"><?=h(number_format($size))?></td>
                    <td><?=h($ext)?></td>
                    <td>
                      <form method="POST" action="<?=h($_SERVER['PHP_SELF'])?>" style="margin:0">
  <input type="hidden" name="action" value="download">
  <input type="hidden" name="token" value="<?=h($EXPECTED_TOKEN)?>">
  <input type="hidden" name="kind" value="file">
  <input type="hidden" name="stored" value="<?=h($stored)?>">
  <input type="hidden" name="orig" value="<?=h($orig !== '' ? $orig : $stored)?>">
  <button class="btn" type="submit"><?=htmlspecialchars($t[$lang]['btn_download'], ENT_QUOTES, 'UTF-8')?></button>
</form>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>

            <div class="dropzone" id="dzFile" style="margin-top:12px">
              <div style="font-weight:900"><?=htmlspecialchars($t[$lang]['drop_files'], ENT_QUOTES, 'UTF-8')?></div>
              <div class="muted small"><?=htmlspecialchars($t[$lang]['or_click'], ENT_QUOTES, 'UTF-8')?></div>
              <input type="file" id="pickFile" multiple style="display:none">
            </div>

            <div style="margin-top:12px">
              <button class="btn red" type="button" id="btnDeleteAllFiles"><?=htmlspecialchars($t[$lang]['btn_delete_all_files'], ENT_QUOTES, 'UTF-8')?></button>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

<script>
const EXPECTED_TOKEN = <?=json_encode($EXPECTED_TOKEN)?>;
const API_UPLOAD_URL = <?=json_encode($apiUploadUrl)?>;
const API_CLEANUP_URL = <?=json_encode($apiCleanupUrl)?>;
const ADMIN_TOKEN = <?=json_encode($ADMIN_TOKEN)?>;

const TEXT_UPLOAD_DONE = <?=json_encode($t[$lang]['upload_done'])?>;
const TEXT_UPLOAD_FAILED = <?=json_encode($t[$lang]['upload_failed'])?>;
const TEXT_DOWNLOAD_FAILED = <?=json_encode($t[$lang]['download_failed'])?>;
const TEXT_CLEANUP_DONE = <?=json_encode($t[$lang]['cleanup_done'])?>;
const TEXT_CLEANUP_FAILED = <?=json_encode($t[$lang]['cleanup_failed'])?>;
const TEXT_CONFIRM_DELETE_ALL_IMAGES = <?=json_encode($t[$lang]['confirm_delete_all_images'])?>;
const TEXT_CONFIRM_DELETE_ALL_FILES = <?=json_encode($t[$lang]['confirm_delete_all_files'])?>;
const TEXT_BTN_DELETE_ALL_IMAGES = <?=json_encode($t[$lang]['btn_delete_all_images'])?>;
const TEXT_BTN_DELETE_ALL_FILES = <?=json_encode($t[$lang]['btn_delete_all_files'])?>;
const TEXT_BTN_DELETE_SELECTED_IMAGES = <?=json_encode($t[$lang]['btn_delete_selected_images'])?>;
const TEXT_BTN_DELETE_SELECTED_FILES = <?=json_encode($t[$lang]['btn_delete_selected_files'])?>;
const TEXT_CONFIRM_DELETE_SELECTED_IMAGES = <?=json_encode($t[$lang]['confirm_delete_selected_images'])?>;
const TEXT_CONFIRM_DELETE_SELECTED_FILES = <?=json_encode($t[$lang]['confirm_delete_selected_files'])?>;

// --- 画像コピーツール用変数 ---
const IMAGE_API_BASE = <?=json_encode($apiDirUrl . '/image_api.php')?>;
const CURRENT_USER = <?=json_encode($USERNAME)?>;
const TEXT_COPIED_IMAGE = <?=json_encode($t[$lang]['copied_image'])?>;
// ------------------------------

function humanErr(e) {
  try { return (typeof e === 'string') ? e : JSON.stringify(e); } catch { return String(e); }
}

async function postDownload(kind, stored, orig) {
  const fd = new FormData();
  fd.append('action', 'download');
  fd.append('token', EXPECTED_TOKEN);
  fd.append('kind', kind);
  fd.append('stored', stored);
  fd.append('orig', orig || '');

  const res = await fetch(location.href, { method: 'POST', body: fd });
  if (!res.ok) {
    const t = await res.text();
    throw new Error(`download failed: ${res.status} ${t}`);
  }
  const blob = await res.blob();
  const url = URL.createObjectURL(blob);

  const a = document.createElement('a');
  a.href = url;
  a.download = orig || stored || 'download.bin';
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

function downloadMedia(kind, stored, orig) {
  postDownload(kind, stored, orig).catch(err => alert(TEXT_DOWNLOAD_FAILED + "\n" + humanErr(err)));
}

async function uploadToApi(type, fileObj) {
  const fd = new FormData();
  fd.append('token', EXPECTED_TOKEN);
  fd.append('type', type);
  if (type === 'image') {
    fd.append('image', fileObj, fileObj.name || 'upload');
  } else if (type === 'file') {
    fd.append('file', fileObj, fileObj.name || 'upload');
  } else {
    throw new Error('unknown type');
  }
  const res = await fetch(API_UPLOAD_URL, { method: 'POST', body: fd });
  const txt = await res.text();
  if (!res.ok) throw new Error(`upload failed: ${res.status} ${txt}`);
  return txt;
}

async function callCleanup(formData) {
  if (!ADMIN_TOKEN) {
    throw new Error('ADMIN_TOKEN is empty');
  }
  formData.append('token', ADMIN_TOKEN);
  formData.append('confirm', 'YES');

  const res = await fetch(API_CLEANUP_URL, { method: 'POST', body: formData });
  const txt = await res.text();
  let json = null;
  try { json = JSON.parse(txt); } catch (_) {}
  if (!res.ok) {
    throw new Error(`cleanup failed: ${res.status} ${txt}`);
  }
  if (json && json.status && json.status !== 'ok') {
    throw new Error(json.message || txt || 'cleanup failed');
  }
  return json || txt;
}

function getCheckedValues(selector) {
  return Array.from(document.querySelectorAll(selector + ':checked'))
    .map(el => (el.value || '').trim())
    .filter(Boolean);
}

function updateDeleteButtons() {
  const imageChecked = getCheckedValues('.media-check-image').length;
  const fileChecked = getCheckedValues('.media-check-file').length;

  const btnImages = document.getElementById('btnDeleteAllImages');
  const btnFiles = document.getElementById('btnDeleteAllFiles');

  if (btnImages) {
    btnImages.textContent = imageChecked > 0
      ? TEXT_BTN_DELETE_SELECTED_IMAGES
      : TEXT_BTN_DELETE_ALL_IMAGES;
  }
  if (btnFiles) {
    btnFiles.textContent = fileChecked > 0
      ? TEXT_BTN_DELETE_SELECTED_FILES
      : TEXT_BTN_DELETE_ALL_FILES;
  }
}

async function handleDeleteImages() {
  const selected = getCheckedValues('.media-check-image');
  const fd = new FormData();

  if (selected.length > 0) {
    if (!confirm(TEXT_CONFIRM_DELETE_SELECTED_IMAGES)) return;
    for (const name of selected) fd.append('delete_images[]', name);
  } else {
    if (!confirm(TEXT_CONFIRM_DELETE_ALL_IMAGES)) return;
    fd.append('purge_images', '1');
  }

  try {
    await callCleanup(fd);
    alert(TEXT_CLEANUP_DONE);
    location.reload();
  } catch (e) {
    alert(TEXT_CLEANUP_FAILED + "\n" + humanErr(e));
  }
}

async function handleDeleteFiles() {
  const selected = getCheckedValues('.media-check-file');
  const fd = new FormData();

  if (selected.length > 0) {
    if (!confirm(TEXT_CONFIRM_DELETE_SELECTED_FILES)) return;
    for (const name of selected) fd.append('delete_files[]', name);
  } else {
    if (!confirm(TEXT_CONFIRM_DELETE_ALL_FILES)) return;
    fd.append('purge_files', '1');
  }

  try {
    await callCleanup(fd);
    alert(TEXT_CLEANUP_DONE);
    location.reload();
  } catch (e) {
    alert(TEXT_CLEANUP_FAILED + "\n" + humanErr(e));
  }
}

function wireDropzone(el, picker, type) {
  const onPick = async (files) => {
    if (!files || files.length === 0) return;
    try {
      el.classList.add('drag');
      for (const f of files) {
        await uploadToApi(type, f);
      }
      // alert(TEXT_UPLOAD_DONE);
      location.reload();
    } catch (e) {
      alert(TEXT_UPLOAD_FAILED + "\n" + humanErr(e));
    } finally {
      el.classList.remove('drag');
    }
  };

  el.addEventListener('click', () => picker.click());
  picker.addEventListener('change', (ev) => onPick(ev.target.files));

  el.addEventListener('dragenter', (e) => { e.preventDefault(); el.classList.add('drag'); });
  el.addEventListener('dragover', (e) => { e.preventDefault(); el.classList.add('drag'); });
  el.addEventListener('dragleave', (e) => { e.preventDefault(); el.classList.remove('drag'); });
  el.addEventListener('drop', (e) => {
    e.preventDefault();
    el.classList.remove('drag');
    onPick(e.dataTransfer.files);
  });
}

wireDropzone(document.getElementById('dzImage'), document.getElementById('pickImage'), 'image');
wireDropzone(document.getElementById('dzFile'), document.getElementById('pickFile'), 'file');

document.querySelectorAll('.media-check').forEach(el => {
  el.addEventListener('change', updateDeleteButtons);
});

updateDeleteButtons();

document.getElementById('btnDeleteAllImages')?.addEventListener('click', handleDeleteImages);
document.getElementById('btnDeleteAllFiles')?.addEventListener('click', handleDeleteFiles);

// --- 画像コピーツールの処理開始 ---
function showToast(msg) {
  let toast = document.getElementById('img-copy-toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'img-copy-toast';
    toast.style.cssText = 'position:fixed; bottom:30px; left:50%; transform:translateX(-50%); background:var(--ok); color:#fff; padding:10px 20px; border-radius:999px; font-weight:bold; z-index:9999; box-shadow:var(--shadow); opacity:0; transition:opacity 0.3s ease; pointer-events:none;';
    document.body.appendChild(toast);
  }
  toast.textContent = msg;
  toast.style.opacity = '1';
  setTimeout(() => { toast.style.opacity = '0'; }, 3000);
}

async function copyImageToClipboard(filename) {
  try {
    const url = `${IMAGE_API_BASE}?user=${encodeURIComponent(CURRENT_USER)}&file=${encodeURIComponent(filename)}`;
    
    const response = await fetch(url);
    if (!response.ok) throw new Error('Network response was not ok');
    const blob = await response.blob();

    // クリップボードAPIの仕様でPNGが最も確実なため、canvasを経由してPNGバイナリに変換する
    const img = new Image();
    const imgLoadPromise = new Promise((resolve, reject) => {
      img.onload = resolve;
      img.onerror = reject;
    });
    img.src = URL.createObjectURL(blob);
    await imgLoadPromise;

    const canvas = document.createElement('canvas');
    canvas.width = img.width;
    canvas.height = img.height;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0);

    canvas.toBlob(async (pngBlob) => {
      try {
        const item = new ClipboardItem({ "image/png": pngBlob });
        await navigator.clipboard.write([item]);
        showToast(TEXT_COPIED_IMAGE);
      } catch (err) {
        console.error("Clipboard write failed:", err);
        alert("クリップボードへのコピーに失敗しました。\n(ブラウザが対応していないか、HTTPS環境でない可能性があります)");
      }
    }, 'image/png');
  } catch (e) {
    console.error(e);
    alert("画像取得に失敗しました");
  }
}

document.querySelectorAll('.copy-thumb').forEach(el => {
  el.addEventListener('click', () => {
    const filename = el.getAttribute('data-filename');
    if (filename) copyImageToClipboard(filename);
  });
});
// --- 画像コピーツールの処理終了 ---

</script>
</body>
</html>