<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_common.php';
nm_send_security_headers_html();

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
$currentUser = function_exists('nm_get_current_user') ? (string)nm_get_current_user() : '';
$currentDirUser = function_exists('nm_get_current_dir_user') ? (string)nm_get_current_dir_user() : '';
if ($currentDirUser === '' && $currentUser !== '') {
  $currentDirUser = normalize_username($currentUser);
}
$cfgAuth = nm_auth_load($currentDirUser !== '' ? $currentDirUser : null);
$user = $currentUser !== '' ? $currentUser : (string)($cfgAuth['USERNAME'] ?? '');
if ($user === '') $user = 'unknown';

// i18n
$t = [
  'ja' => [
    'title' => 'メディア / ファイル',
    'logged_as' => 'ログイン中:',
    'storage_dir_user' => '保存ディレクトリ:',
    'back' => '戻る',
    'go_account' => 'アカウント設定へ',
    'go_setup_auth' => '認証設定へ',
    'go_log_settings' => 'ログ設定へ',
    'go_bak_settings' => 'バックアップ設定へ',
    'go_clipboard_sync' => 'クリップボード同期へ',

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
    'images_click_help' => 'サムネイルをクリックすると画像をコピーできます。ファイル名をクリックするとURLをコピーできます。横幅 / 縦幅 を使ってサイズパラメータを追加できます。',
    'thumb_width' => '横幅',
    'thumb_height' => '縦幅',
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
    'storage_dir_user' => 'Storage directory:',
    'back' => 'Back',
    'go_account' => 'Go to Account',
    'go_setup_auth' => 'Go to Auth settings',
    'go_log_settings' => 'Go to Log settings',
    'go_bak_settings' => 'Go to Backup settings',
    'go_clipboard_sync' => 'Go to Clipboard sync',

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
    'images_click_help' => 'Click the thumbnail to copy the image, click the filename to copy the URL, and use Width / Height to add size parameters.',
    'thumb_width' => 'Width',
    'thumb_height' => 'Height',
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
$configApiPath = nm_api_config_path($currentDirUser !== '' ? $currentDirUser : null);
if (!is_file($configApiPath)) {
  http_response_code(500);
  echo "config/" . ($currentDirUser !== '' ? $currentDirUser : '<USER_NAME>') . "/config.api.php not found";
  exit;
}
$cfg = require $configApiPath;
if (!is_array($cfg)) $cfg = [];

$EXPECTED_TOKEN = (string)($cfg['EXPECTED_TOKEN'] ?? '');
$ADMIN_TOKEN    = (string)($cfg['ADMIN_TOKEN'] ?? '');
$dataJsonPath = nm_data_json_path($currentDirUser !== '' ? $currentDirUser : null);

if ($dataJsonPath === '') { http_response_code(500); echo "Server not configured (data.json path)"; exit; }

// TIMEZONE (config/<USER_NAME>/config.php) - 画面表示の日時に反映
$TIMEZONE = 'UTC';
$configPath = nm_config_path($currentDirUser !== '' ? $currentDirUser : null);
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


// USERNAME / DIR_USER
$USERNAME = $user;
$DIR_USER = $currentDirUser !== '' ? $currentDirUser : normalize_username($USERNAME);
$CURRENT_DIR_USER_FOR_POST = $DIR_USER;

// user directories
$userDir   = nm_data_dir($DIR_USER !== '' ? $DIR_USER : null);
$imagesDir = nm_images_dir($DIR_USER !== '' ? $DIR_USER : null);
$filesDir  = nm_files_dir($DIR_USER !== '' ? $DIR_USER : null);
$fileHistoryPath = $userDir . DIRECTORY_SEPARATOR . 'file.json';
$fileIndexPath  = $userDir . DIRECTORY_SEPARATOR . 'file_index.json';
$imageIndexPath = $userDir . DIRECTORY_SEPARATOR . 'image_index.json';


// URLs
$uBase = nm_auth_base_url();
$apiDirUrl     = rtrim($uBase, '/') . '/api';
$apiUploadUrl  = $apiDirUrl . '/api.php';
$apiCleanupUrl = $apiDirUrl . '/cleanup_api.php';

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
  || ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$scheme = $isHttps ? 'https' : 'http';
$host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
$apiCleanupUrlAbs = $scheme . '://' . $host . $apiCleanupUrl;
$apiUploadUrlAbs = $scheme . '://' . $host . $apiUploadUrl;

// helpers
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function lock_icon_svg(bool $locked): string {
  if ($locked) {
    return '<svg class="lock-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M17 9h-1V7a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2Zm-6 6.73V17a1 1 0 1 0 2 0v-1.27a2 2 0 1 0-2 0ZM10 9V7a2 2 0 1 1 4 0v2h-4Z"/></svg>';
  }
  return '<svg class="lock-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M17 9h-5V7a4 4 0 1 1 8 0h-2a2 2 0 1 0-4 0v2h3a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h10Zm-5 6.73V17a1 1 0 1 0 2 0v-1.27a2 2 0 1 0-2 0Z"/></svg>';
}

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
      'filename' => $f,
      'ext' => strtolower(pathinfo($f, PATHINFO_EXTENSION)),
      'size' => filesize($path) ?: 0,
      'mtime' => filemtime($path) ?: 0,
      'lock' => false,
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


function parse_image_index_json(string $path): array {
  if (!is_file($path) || !is_readable($path)) return [];
  $raw = @file_get_contents($path);
  if (!is_string($raw) || $raw === '') return [];
  $json = json_decode($raw, true);
  if (!is_array($json) || !isset($json['images']) || !is_array($json['images'])) return [];
  $out = [];
  foreach ($json['images'] as $row) {
    if (!is_array($row)) continue;
    $filename = (string)($row['filename'] ?? '');
    if ($filename === '') continue;
    $out[] = [
      'stored_filename' => $filename,
      'filename' => $filename,
      'ext' => (string)($row['ext'] ?? strtolower(pathinfo($filename, PATHINFO_EXTENSION))),
      'size' => (int)($row['size'] ?? 0),
      'mtime' => (int)($row['created_at_unix'] ?? 0),
      'lock' => !empty($row['lock']),
    ];
    if (count($out) >= 500) break;
  }
  return $out;
}

function parse_file_index_json(string $path): array {
  // file_index.json の形式:
  // { v, generated_at(_unix), count, files:[ {filename, original_name, ext, mime, size, created_at, created_at_unix, sha256, file_id?, lock}, ... ] }
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
    if (!array_key_exists('lock', $row)) $row['lock'] = false;
    $row['lock'] = !empty($row['lock']);
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


function call_cleanup_action(string $url, string $adminToken, array $params): array {
  if ($adminToken === '') {
    return ['ok' => false, 'message' => 'ADMIN_TOKEN is empty'];
  }

  $params = array_merge($params, [
    'token' => $adminToken,
    'user' => (string)(function_exists('nm_get_current_dir_user') ? nm_get_current_dir_user() : ''),
    'confirm' => 'YES',
  ]);

  $post = http_build_query($params);
  $opts = [
    'http' => [
      'method' => 'POST',
      'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                  "Content-Length: " . strlen($post) . "\r\n",
      'content' => $post,
      'timeout' => 15,
    ]
  ];
  $ctx = stream_context_create($opts);
  $raw = @file_get_contents($url, false, $ctx);
  if (!is_string($raw) || $raw === '') {
    return ['ok' => false, 'message' => 'empty response'];
  }

  $json = json_decode($raw, true);
  if (!is_array($json)) {
    return ['ok' => false, 'message' => $raw];
  }

  if (($json['status'] ?? '') !== 'ok') {
    return ['ok' => false, 'message' => (string)($json['message'] ?? 'cleanup failed'), 'json' => $json];
  }

  return ['ok' => true, 'json' => $json];
}


function call_upload_action(string $url, string $expectedToken, string $dirUser, string $type, array $fileInfo): array {
  if ($expectedToken === '') {
    return ['ok' => false, 'message' => 'EXPECTED_TOKEN is empty'];
  }
  if (!isset($fileInfo['tmp_name']) || !is_uploaded_file((string)$fileInfo['tmp_name'])) {
    return ['ok' => false, 'message' => 'No uploaded file'];
  }

  $boundary = '----NotemodBoundary' . bin2hex(random_bytes(8));
  $eol = "\r\n";
  $filename = (string)($fileInfo['name'] ?? 'upload.bin');
  $tmpName = (string)$fileInfo['tmp_name'];
  $mime = (string)($fileInfo['type'] ?? '');
  if ($mime === '') {
    $mime = 'application/octet-stream';
  }
  $content = @file_get_contents($tmpName);
  if ($content === false) {
    return ['ok' => false, 'message' => 'Failed to read upload tmp file'];
  }

  $fieldName = $type === 'image' ? 'image' : 'file';

  $body = '';
  foreach ([
    'token' => $expectedToken,
    'user' => $dirUser,
    'type' => $type,
  ] as $name => $value) {
    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="' . $name . '"' . $eol . $eol;
    $body .= $value . $eol;
  }

  $safeFilename = str_replace(["\r", "\n", '"'], ['','','_'], $filename);
  $body .= '--' . $boundary . $eol;
  $body .= 'Content-Disposition: form-data; name="' . $fieldName . '"; filename="' . $safeFilename . '"' . $eol;
  $body .= 'Content-Type: ' . $mime . $eol . $eol;
  $body .= $content . $eol;
  $body .= '--' . $boundary . '--' . $eol;

  $opts = [
    'http' => [
      'method' => 'POST',
      'header' => "Content-Type: multipart/form-data; boundary=" . $boundary . "\r\n" .
                  "Content-Length: " . strlen($body) . "\r\n",
      'content' => $body,
      'timeout' => 60,
    ]
  ];
  $ctx = stream_context_create($opts);
  $raw = @file_get_contents($url, false, $ctx);
  if (!is_string($raw) || $raw === '') {
    return ['ok' => false, 'message' => 'empty response'];
  }

  $json = json_decode($raw, true);
  if (!is_array($json)) {
    return ['ok' => false, 'message' => $raw];
  }
  if (($json['status'] ?? '') !== 'ok') {
    return ['ok' => false, 'message' => (string)($json['message'] ?? 'upload failed'), 'json' => $json];
  }
  return ['ok' => true, 'json' => $json];
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

if (isset($_GET['image_copy']) && $_GET['image_copy'] === '1') {
  $f = safe_basename((string)($_GET['f'] ?? ''));
  $f = urldecode($f);
  $real = real_under($imagesDir, $f);
  if (!$real) { http_response_code(404); exit; }

  $w = isset($_GET['w']) ? (int)$_GET['w'] : 0;
  $h = isset($_GET['h']) ? (int)$_GET['h'] : 0;

  if ($w > 0 || $h > 0) {
    $mime = detect_mime($real);
    $imgInfo = @getimagesize($real);
    if (is_array($imgInfo)) {
      $srcW = (int)($imgInfo[0] ?? 0);
      $srcH = (int)($imgInfo[1] ?? 0);
      $imgType = (int)($imgInfo[2] ?? 0);

      if ($srcW > 0 && $srcH > 0) {
        if ($w <= 0 && $h > 0) {
          $w = (int)round($srcW * ($h / $srcH));
        } elseif ($h <= 0 && $w > 0) {
          $h = (int)round($srcH * ($w / $srcW));
        }

        $w = max(1, $w);
        $h = max(1, $h);

        $src = null;
        switch ($imgType) {
          case IMAGETYPE_JPEG:
            $src = @imagecreatefromjpeg($real);
            break;
          case IMAGETYPE_PNG:
            $src = @imagecreatefrompng($real);
            break;
          case IMAGETYPE_GIF:
            $src = @imagecreatefromgif($real);
            break;
          case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
              $src = @imagecreatefromwebp($real);
            }
            break;
        }

        if ($src) {
          $dst = imagecreatetruecolor($w, $h);

          if ($imgType === IMAGETYPE_PNG || $imgType === IMAGETYPE_GIF || $imgType === IMAGETYPE_WEBP) {
            @imagealphablending($dst, false);
            @imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $w, $h, $transparent);
          }

          imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $srcW, $srcH);

          header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

          switch ($imgType) {
            case IMAGETYPE_JPEG:
              header('Content-Type: image/jpeg');
              imagejpeg($dst, null, 90);
              imagedestroy($dst);
              imagedestroy($src);
              exit;
            case IMAGETYPE_PNG:
              header('Content-Type: image/png');
              imagepng($dst);
              imagedestroy($dst);
              imagedestroy($src);
              exit;
            case IMAGETYPE_GIF:
              header('Content-Type: image/gif');
              imagegif($dst);
              imagedestroy($dst);
              imagedestroy($src);
              exit;
            case IMAGETYPE_WEBP:
              if (function_exists('imagewebp')) {
                header('Content-Type: image/webp');
                imagewebp($dst, null, 90);
                imagedestroy($dst);
                imagedestroy($src);
                exit;
              }
              break;
          }

          imagedestroy($dst);
          imagedestroy($src);
        }
      }
    }
  }

  $mime = detect_mime($real);
  header('Content-Type: ' . $mime);
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  readfile($real);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'download') {
  try {
    nm_csrf_validate_or_die();
  } catch (Throwable $e) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status'=>'error','message'=>'Invalid CSRF token'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_media') {
  try {
    nm_csrf_validate_or_die();
  } catch (Throwable $e) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status'=>'error','message'=>'Invalid CSRF token'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
  header('Content-Type: application/json; charset=utf-8');

  $type = (string)($_POST['upload_type'] ?? '');
  if ($type !== 'image' && $type !== 'file') {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Invalid upload type'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  $fieldName = $type === 'image' ? 'image' : 'file';
  if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'No upload file'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  $fileInfo = $_FILES[$fieldName];
  if ((int)($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Upload error'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  $result = call_upload_action($apiUploadUrlAbs, $EXPECTED_TOKEN, $CURRENT_DIR_USER_FOR_POST ?? '', $type, $fileInfo);
  if (empty($result['ok'])) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>(string)($result['message'] ?? 'upload failed')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  echo json_encode(['status'=>'ok'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cleanup_action') {
  try {
    nm_csrf_validate_or_die();
  } catch (Throwable $e) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status'=>'error','message'=>'Invalid CSRF token'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
  header('Content-Type: application/json; charset=utf-8');

  $mode = (string)($_POST['mode'] ?? '');
  $params = [];

  if ($mode === 'delete_all_images') {
    $params['purge_images'] = '1';
  } elseif ($mode === 'delete_all_files') {
    $params['purge_files'] = '1';
  } elseif ($mode === 'delete_selected_images') {
    $items = $_POST['delete_images'] ?? [];
    if (!is_array($items) || count($items) === 0) {
      http_response_code(400);
      echo json_encode(['status'=>'error','message'=>'No image files selected'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }
    $params['delete_images'] = array_values(array_filter(array_map(function($name){
      return safe_basename((string)$name);
    }, $items), function($name){
      return $name !== '';
    }));
  } elseif ($mode === 'delete_selected_files') {
    $items = $_POST['delete_files'] ?? [];
    if (!is_array($items) || count($items) === 0) {
      http_response_code(400);
      echo json_encode(['status'=>'error','message'=>'No files selected'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }
    $params['delete_files'] = array_values(array_filter(array_map(function($name){
      return safe_basename((string)$name);
    }, $items), function($name){
      return $name !== '';
    }));
  } else {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Unknown cleanup mode'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  $result = call_cleanup_action($apiCleanupUrlAbs, $ADMIN_TOKEN, $params);
  if (empty($result['ok'])) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>(string)($result['message'] ?? 'cleanup failed')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  echo json_encode(['status'=>'ok'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lock_media') {
  try {
    nm_csrf_validate_or_die();
  } catch (Throwable $e) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status'=>'error','message'=>'Invalid CSRF token'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
  header('Content-Type: application/json; charset=utf-8');

  $kind = (string)($_POST['kind'] ?? '');
  $filename = safe_basename((string)($_POST['filename'] ?? ''));
  $lockValue = !empty($_POST['lock']) ? '1' : '0';

  if (($kind !== 'image' && $kind !== 'file') || $filename === '') {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Invalid lock request'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  $result = call_cleanup_action($apiCleanupUrlAbs, $ADMIN_TOKEN, [
    'lock_target_type' => $kind,
    'lock_filename' => $filename,
    'lock_value' => $lockValue,
  ]);

  if (empty($result['ok'])) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>(string)($result['message'] ?? 'lock update failed')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  echo json_encode(['status'=>'ok'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

$imageCount = call_cleanup_count($apiCleanupUrlAbs, $ADMIN_TOKEN, 'purge_images');
$fileCount  = call_cleanup_count($apiCleanupUrlAbs, $ADMIN_TOKEN, 'purge_files');
if ($imageCount === null) $imageCount = count_dir_files($imagesDir, '/\.(png|jpg|jpeg|webp|gif|heic|heif)$/i');
if ($fileCount  === null) $fileCount  = count_dir_files($filesDir);

$images = is_file($imageIndexPath) ? parse_image_index_json($imageIndexPath) : [];
if (count($images) === 0) {
  $images = list_images($imagesDir, 500);
}

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
$accountUrl = nm_ui_url('/account.php');
$setupauthUrl = nm_ui_url('/setup_auth.php');
$logsettingsUrl = nm_ui_url('/log_settings.php');
$baksettingsUrl = nm_ui_url('/bak_settings.php');
$clipboardsyncUrl = nm_ui_url('/clipboard_sync.php');
$csrfToken = function_exists('nm_csrf_token_get') ? nm_csrf_token_get() : '';
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
    details summary:before{ content:""; margin-right:8px; color:var(--muted); }
    details[open] summary:before{ content:""; }
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

/* サイズ指定用のカスタム入力スタイル */
    .param-pill {
      display: inline-flex;
      align-items: center;
      gap: 12px;
      padding: 6px 16px;
      border-radius: 999px;
      border: 1px solid var(--line);
      background: color-mix(in srgb, var(--card2) 60%, transparent);
    }
    .param-label {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      color: var(--muted);
      font-weight: 700;
      cursor: pointer;
    }
    .param-input {
      width: 64px;
      background: color-mix(in srgb, var(--bg0) 50%, transparent);
      border: 1px solid var(--line);
      border-radius: 6px;
      color: var(--text);
      font-size: 13px;
      padding: 4px 8px;
      outline: none;
      transition: .15s ease;
      font-family: inherit;
      text-align: center;
    }
    .param-input:focus {
      border-color: var(--accent);
      background: color-mix(in srgb, var(--accent) 10%, transparent);
      box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent) 25%, transparent);
    }
    .param-divider {
      width: 1px;
      height: 18px;
      background: var(--line);
    }
    /* スピンボタン（上下の矢印）を非表示にしてスッキリさせる */
    .param-input::-webkit-outer-spin-button,
    .param-input::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }
    .param-input[type=number] {
      -moz-appearance: textfield;
    }


    .lock-cell{ display:flex; align-items:center; gap:10px; }
    .lock-btn{
      width:34px; height:34px;
      display:inline-flex; align-items:center; justify-content:center;
      border-radius:999px;
      border:1px solid var(--line);
      background: color-mix(in srgb, var(--card2) 82%, transparent);
      color: var(--muted);
      cursor:pointer;
      transition:.15s ease;
      padding:0;
      flex:0 0 auto;
    }
    .lock-btn:hover{
      transform: translateY(-1px);
      border-color: color-mix(in srgb, var(--accent) 35%, var(--line));
      color: color-mix(in srgb, var(--text) 80%, var(--accent));
      background: color-mix(in srgb, var(--accent) 7%, var(--card2));
    }
    .lock-btn:disabled{ opacity:.6; cursor:wait; transform:none; }
    .lock-btn.is-locked{
      color: var(--accent);
      border-color: color-mix(in srgb, var(--accent) 42%, var(--line));
      background: color-mix(in srgb, var(--accent) 12%, var(--card2));
      box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--accent) 14%, transparent);
    }
    .lock-btn.is-locked:hover{
      border-color: color-mix(in srgb, var(--accent) 62%, var(--line));
      background: color-mix(in srgb, var(--accent) 16%, var(--card2));
    }
    .lock-icon{ width:16px; height:16px; display:block; }
    .media-check:disabled{ opacity:.45; cursor:not-allowed; }

    .row-links{ display:flex; gap:12px; flex-wrap:wrap;}
    .row-links a{ font-size:13px; color:var(--accent); }

  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="head">
        <div class="left">
          <h1 class="title"><?=htmlspecialchars($t[$lang]['title'], ENT_QUOTES, 'UTF-8')?></h1>
          <div class="sub">
            <?=htmlspecialchars($t[$lang]['logged_as'], ENT_QUOTES, 'UTF-8')?> <b><?=htmlspecialchars($currentUser !== '' ? $currentUser : $user, ENT_QUOTES, 'UTF-8')?></b> &nbsp; | &nbsp; <?=htmlspecialchars($t[$lang]['storage_dir_user'], ENT_QUOTES, 'UTF-8')?> <b><?=htmlspecialchars($currentDirUser !== '' ? $currentDirUser : normalize_username((string)($currentUser !== '' ? $currentUser : $user)), ENT_QUOTES, 'UTF-8')?></b>
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
            <div style="display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap;">
              <div style="font-weight:900"><?=htmlspecialchars($t[$lang]['section_images'], ENT_QUOTES, 'UTF-8')?></div>


<div class="param-pill" style="margin-left:auto;">
                <label class="param-label">
                  <span><?=htmlspecialchars($t[$lang]['thumb_width'], ENT_QUOTES, 'UTF-8')?></span>
                  <input id="imgCopyWidth" class="param-input" type="number" min="1" step="1" placeholder="px">
                </label>
                
                <div class="param-divider"></div>
                
                <label class="param-label">
                  <span><?=htmlspecialchars($t[$lang]['thumb_height'], ENT_QUOTES, 'UTF-8')?></span>
                  <input id="imgCopyHeight" class="param-input" type="number" min="1" step="1" placeholder="px">
                </label>
              </div>


            </div>
            <div class="notice" style="margin-top:10px; margin-bottom:10px; font-size:12px;"><?=htmlspecialchars($t[$lang]['images_click_help'], ENT_QUOTES, 'UTF-8')?></div>
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
                    <td><div class="lock-cell"><input type="checkbox" class="media-check media-check-image" value="<?=h($fname)?>" <?=!empty($img['lock']) ? 'disabled' : ''?>><button type="button" class="lock-btn <?=!empty($img['lock']) ? 'is-locked' : ''?>" data-lock-kind="image" data-filename="<?=h($fname)?>" data-lock="<?=!empty($img['lock']) ? '1' : '0'?>" title="<?=!empty($img['lock']) ? 'Unlock' : 'Lock'?>" aria-label="<?=!empty($img['lock']) ? 'Unlock' : 'Lock'?>"><?=lock_icon_svg(!empty($img['lock']))?></button></div></td>
                    <td>
                      <img class="thumb copy-thumb" style="cursor:pointer;" title="<?=htmlspecialchars($t[$lang]['copied_image'], ENT_QUOTES, 'UTF-8')?>" src="<?=h($_SERVER['PHP_SELF'])?>?thumb=1&f=<?=h(urlencode($fname))?>" alt="" data-filename="<?=h($fname)?>">
                    </td>
                    <td><span class="copy-image-url" data-filename="<?=h($fname)?>" style="cursor:pointer; text-decoration:underline;"><?=h($fname)?></span></td>
                    <td><?=h(fmt_local_time_from_unix($mtime))?></td>
                    <td class="right"><?=h(number_format($size))?></td>
                    <td><?=h($ext)?></td>
                    <td>
                      <form method="POST" action="<?=h($_SERVER['PHP_SELF'])?>" style="margin:0">
  <input type="hidden" name="action" value="download">
  <input type="hidden" name="csrf_token" value="<?=h($csrfToken)?>">
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
                    <td><div class="lock-cell"><input type="checkbox" class="media-check media-check-file" value="<?=h($stored)?>" <?=!empty($row['lock']) ? 'disabled' : ''?>><button type="button" class="lock-btn <?=!empty($row['lock']) ? 'is-locked' : ''?>" data-lock-kind="file" data-filename="<?=h($stored)?>" data-lock="<?=!empty($row['lock']) ? '1' : '0'?>" title="<?=!empty($row['lock']) ? 'Unlock' : 'Lock'?>" aria-label="<?=!empty($row['lock']) ? 'Unlock' : 'Lock'?>"><?=lock_icon_svg(!empty($row['lock']))?></button></div></td>
                    <td><?=h($orig)?></td>
                    <td class="muted"><?=h($stored)?></td>
                    <td><?=h(fmt_local_time_from_iso($createdAt))?></td>
                    <td class="right"><?=h(number_format($size))?></td>
                    <td><?=h($ext)?></td>
                    <td>
                      <form method="POST" action="<?=h($_SERVER['PHP_SELF'])?>" style="margin:0">
  <input type="hidden" name="action" value="download">
  <input type="hidden" name="csrf_token" value="<?=h($csrfToken)?>">
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

    <div class="row-links">
      <a class="btn" href="<?=htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8')?>">← <?=htmlspecialchars($t[$lang]['back'], ENT_QUOTES, 'UTF-8')?></a>
      <a class="btn red" href="<?=htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['logout'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="btn" href="<?=htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_account'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="btn" href="<?=htmlspecialchars($setupauthUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_setup_auth'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="btn" href="<?=htmlspecialchars($logsettingsUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_log_settings'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="btn" href="<?=htmlspecialchars($baksettingsUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_bak_settings'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="btn" href="<?=htmlspecialchars($clipboardsyncUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_clipboard_sync'], ENT_QUOTES, 'UTF-8')?></a>
    </div>

  </div>

<script>

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
const SITE_ORIGIN = <?=json_encode(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') || ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http') . '://' . (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'))?>;
const CURRENT_USER = <?=json_encode(isset($currentDirUser) && $currentDirUser !== '' ? $currentDirUser : $USERNAME)?>;
const TEXT_COPIED_IMAGE = <?=json_encode($t[$lang]['copied_image'])?>;
const TEXT_COPIED_IMAGE_URL = <?=json_encode('Image URL copied to clipboard')?>;
const CSRF_TOKEN = <?=json_encode($csrfToken)?>;
// ------------------------------

function humanErr(e) {
  try {
    if (typeof e === 'string') return e;
    if (e && typeof e.message === 'string' && e.message !== '') return e.message;
    const j = JSON.stringify(e);
    return (j && j !== '{}') ? j : String(e);
  } catch {
    return String(e);
  }
}

async function postDownload(kind, stored, orig) {
  const fd = new FormData();
  fd.append('action', 'download');
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
  fd.append('action', 'upload_media');
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('upload_type', type);
  if (type === 'image') {
    fd.append('image', fileObj, fileObj.name || 'upload');
  } else if (type === 'file') {
    fd.append('file', fileObj, fileObj.name || 'upload');
  } else {
    throw new Error('unknown type');
  }
  return postLocalAction(fd);
}

async function postLocalAction(formData) {
  const res = await fetch(location.href, { method: 'POST', body: formData });
  const txt = await res.text();
  let json = null;
  try { json = JSON.parse(txt); } catch (_) {}
  if (!res.ok) {
    throw new Error((json && json.message) ? json.message : `request failed: ${res.status} ${txt}`);
  }
  if (json && json.status && json.status !== 'ok') {
    throw new Error(json.message || txt || 'request failed');
  }
  return json || txt;
}

async function callCleanup(mode, names) {
  const fd = new FormData();
  fd.append('action', 'cleanup_action');
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('mode', mode);

  if (Array.isArray(names)) {
    const key = mode.includes('images') ? 'delete_images' : 'delete_files';
    names.forEach(name => fd.append(key + '[]', name));
  }

  return postLocalAction(fd);
}

async function setMediaLock(kind, filename, lockValue) {
  const fd = new FormData();
  fd.append('action', 'lock_media');
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('kind', kind);
  fd.append('filename', filename);
  fd.append('lock', lockValue ? '1' : '0');
  return postLocalAction(fd);
}

function renderLockIcon(locked) {
  if (locked) {
    return '<svg class="lock-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M17 9h-1V7a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2Zm-6 6.73V17a1 1 0 1 0 2 0v-1.27a2 2 0 1 0-2 0ZM10 9V7a2 2 0 1 1 4 0v2h-4Z"/></svg>';
  }
  return '<svg class="lock-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M17 9h-5V7a4 4 0 1 1 8 0h-2a2 2 0 1 0-4 0v2h3a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h10Zm-5 6.73V17a1 1 0 1 0 2 0v-1.27a2 2 0 1 0-2 0Z"/></svg>';
}

function applyLockUi(button, locked) {
  if (!button) return;
  const wrap = button.closest('.lock-cell');
  const cb = wrap ? wrap.querySelector('.media-check') : null;
  button.dataset.lock = locked ? '1' : '0';
  button.innerHTML = renderLockIcon(locked);
  button.classList.toggle('is-locked', locked);
  button.title = locked ? 'Unlock' : 'Lock';
  button.setAttribute('aria-label', locked ? 'Unlock' : 'Lock');
  if (cb) {
    cb.disabled = !!locked;
    if (locked) cb.checked = false;
  }
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

  if (selected.length > 0) {
    if (!confirm(TEXT_CONFIRM_DELETE_SELECTED_IMAGES)) return;
  } else {
    if (!confirm(TEXT_CONFIRM_DELETE_ALL_IMAGES)) return;
  }

  try {
    await callCleanup(selected.length > 0 ? 'delete_selected_images' : 'delete_all_images', selected.length > 0 ? selected : null);
    alert(TEXT_CLEANUP_DONE);
    location.reload();
  } catch (e) {
    alert(TEXT_CLEANUP_FAILED + "\n" + humanErr(e));
  }
}

async function handleDeleteFiles() {
  const selected = getCheckedValues('.media-check-file');
  if (selected.length > 0) {
    if (!confirm(TEXT_CONFIRM_DELETE_SELECTED_FILES)) return;
  } else {
    if (!confirm(TEXT_CONFIRM_DELETE_ALL_FILES)) return;
  }

  try {
    await callCleanup(selected.length > 0 ? 'delete_selected_files' : 'delete_all_files', selected.length > 0 ? selected : null);
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

document.querySelectorAll('.lock-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    const kind = btn.dataset.lockKind || '';
    const filename = btn.dataset.filename || '';
    const current = btn.dataset.lock === '1';
    btn.disabled = true;
    try {
      await setMediaLock(kind, filename, !current);
      applyLockUi(btn, !current);
      updateDeleteButtons();
    } catch (e) {
      alert(TEXT_CLEANUP_FAILED + "\n" + humanErr(e));
    } finally {
      btn.disabled = false;
    }
  });
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

function buildImageUrl(filename) {
  const rel = `<?=h($_SERVER['PHP_SELF'])?>?image_copy=1&f=${encodeURIComponent(filename)}`;
  const url = new URL(rel.startsWith('http://') || rel.startsWith('https://') ? rel : (SITE_ORIGIN + rel));

  const widthEl = document.getElementById('imgCopyWidth');
  const heightEl = document.getElementById('imgCopyHeight');

  const w = widthEl ? String(widthEl.value || '').trim() : '';
  const h = heightEl ? String(heightEl.value || '').trim() : '';

  if (w !== '') url.searchParams.set('w', w);
  if (h !== '') url.searchParams.set('h', h);

  return url.toString();
}

async function copyImageUrlToClipboard(filename) {
  try {
    const url = buildImageUrl(filename);
    await navigator.clipboard.writeText(url);
    showToast(TEXT_COPIED_IMAGE_URL);
  } catch (e) {
    console.error(e);
    alert("URLのコピーに失敗しました");
  }
}

async function copyImageToClipboard(filename) {
  try {
    const url = buildImageUrl(filename);
    
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

document.querySelectorAll('.copy-image-url').forEach(el => {
  el.addEventListener('click', async (ev) => {
    ev.preventDefault();
    ev.stopPropagation();
    const filename = el.getAttribute('data-filename');
    if (!filename) return;
    await copyImageUrlToClipboard(filename);
  });
});

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