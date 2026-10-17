<?php

declare(strict_types=1);
require_once __DIR__ . '/auth_common.php';
nm_send_security_headers_html();
nm_auth_require_login();

/*
 * clipboard_sync.php
 * - ClipboardSync（旧ClipboardSender）向けの設定情報を一覧表示
 * - ClipboardSync DLは「GitHubからダウンロード」ボタン表示（URLは画面に表示しない）
 * - api/read/cleanup URL はクリックでコピー
 * - EXPECTED_TOKEN / ADMIN_TOKEN は初期表示では伏字
 * - トークンはロック解除時だけサーバーから取得して10秒だけ表示
 * - 10秒後に自動再ロックされ、表示中のみコピー可能
 * - reveal_token の POST は CSRF 保護あり
 * - JP/EN + Dark/Light + ログインユーザー表示
 *
 * single-user UI refreshed using the multi-user version as the reference design.
 * - config/<DIR_USER>/config.api.php を参照
 * - USERNAME と DIR_USER を分離表示
 * - ルート設置 / サブディレクトリ設置の両対応を auth_common.php 側の nm_url() に寄せる
 */

header('Content-Type: text/html; charset=utf-8');

// session
nm_auth_start_session();

// UI bootstrap
$ui    = nm_ui_bootstrap();
$lang  = $ui['lang'] ?? 'ja';
$theme = $ui['theme'] ?? 'dark';

// auth
$isLoggedIn = function_exists('nm_auth_is_logged_in') ? (bool) nm_auth_is_logged_in() : false;
$loginUser = $isLoggedIn && function_exists('nm_get_current_user') ? (string) nm_get_current_user() : '';
$currentDirUser = $isLoggedIn && function_exists('nm_get_current_dir_user') ? (string) nm_get_current_dir_user() : '';

// --------------------
// ★ あなたのリンクに合わせて編集する場所
// --------------------
$LINKS = [
  // ClipboardSync (Windows) ダウンロードリンク（URLは画面に表示せずボタンにする）
  'download_win_x64'   => 'https://github.com/StayHomeLabNet/ClipboardSync/releases/download/v1.1.1/ClipboardSync.exe',
  'download_win_arm64' => 'https://github.com/StayHomeLabNet/ClipboardSync/releases/download/v1.1.1/ClipboardSyncARM64.exe',

  // arm64説明（JP/EN）
  'arm64_note' => [
    'ja' => 'Windows on ARM（例：Snapdragon搭載PC）向けです。通常のIntel/AMD PCは win-x64 を使用してください。',
    'en' => 'For Windows on ARM (e.g., Snapdragon PCs). Typical Intel/AMD PCs should use win-x64.',
  ],

  // iPhoneショートカットDLリンク（言語で切替）
  // 1行2つ表示：通常版 / BASIC認証対応版
  'shortcuts' => [
    'ja' => [
'pc_to_iphone' => ['label'=>'PC→iPhone', 'normal'=>'https://www.icloud.com/shortcuts/492f31cdb0bd4a84b86824e06a0c3b0a', 'basic'=>'https://www.icloud.com/shortcuts/a0c3f6bc6b0447d09d945026365cc27c'],
'iphone_to_pc' => ['label'=>'iPhone→PC', 'normal'=>'https://www.icloud.com/shortcuts/3fbc25d8c7e34b4da176ae51920150d1', 'basic'=>'https://www.icloud.com/shortcuts/7cdf6fbf90964ba3aa93f66d7b16ea73'],
'pc_to_iphone_image' => ['label'=>'PC→iPhone 画像対応', 'normal'=>'https://www.icloud.com/shortcuts/24dcd03253e24a28939755494818b36b', 'basic'=>'https://www.icloud.com/shortcuts/c2397d4057a04d87a14e176e366a3453'],
'iphone_to_pc_image' => ['label'=>'iPhone→PC 画像対応', 'normal'=>'https://www.icloud.com/shortcuts/5678d6ce2d304c1ca39512f157f55451', 'basic'=>'https://www.icloud.com/shortcuts/430d4c04ceae4652a83a7c20ea24ce7d'],
'pc_to_iphone_file' => ['label'=>'PC→iPhone ファイル', 'normal'=>'https://www.icloud.com/shortcuts/376ce21827c3447a9114dae1efe69671', 'basic'=>'https://www.icloud.com/shortcuts/a6f5a0dccb694f2c94a935b3ba5cde5b'],
'iphone_to_pc_file' => ['label'=>'iPhone→PC ファイル', 'normal'=>'https://www.icloud.com/shortcuts/eec468a682584b55934e5a8f03ebaa1e', 'basic'=>'https://www.icloud.com/shortcuts/7a91986e723a4018953173b9b1204d89'],
'text_memo' => ['label'=>'テキストメモ', 'normal'=>'https://www.icloud.com/shortcuts/4d2eafcdb884498291e5b16d5154b620', 'basic'=>'https://www.icloud.com/shortcuts/505f6496c8344339b1ffefdebb82c5b1'],
'voice_text_memo' => ['label'=>'音声テキストメモ', 'normal'=>'https://www.icloud.com/shortcuts/d1a3c901cf6242d2b0688d1fcf7591b0', 'basic'=>'https://www.icloud.com/shortcuts/8fa27d6ba51a4694aa55594056f2a31b'],
'camera_text' => ['label'=>'カメラからテキスト', 'normal'=>'https://www.icloud.com/shortcuts/0455b572865147b2aefd711c2fd7827f', 'basic'=>'https://www.icloud.com/shortcuts/92ab84b900c94edca157dcfdb33a2219'],
    ],
    'en' => [
'pc_to_iphone' => ['label'=>'PC → iPhone', 'normal'=>'https://www.icloud.com/shortcuts/3284600cac4947a5bd57ba972ac1054a', 'basic'=>'https://www.icloud.com/shortcuts/d58d90ca6db6444aa5df66dee4af3b00'],
'iphone_to_pc' => ['label'=>'iPhone → PC', 'normal'=>'https://www.icloud.com/shortcuts/c9fb822bb0344f40a27744379b3ec01c', 'basic'=>'https://www.icloud.com/shortcuts/94b820163d3b4967854fbb781ebbd651'],
'pc_to_iphone_image' => ['label'=>'PC → iPhone with Image', 'normal'=>'https://www.icloud.com/shortcuts/74e34ad62d2242819f05e20b019d042d', 'basic'=>'https://www.icloud.com/shortcuts/10e3052136db4e4d8792073bd7b136ec'],
'iphone_to_pc_image' => ['label'=>'iPhone → PC with Image', 'normal'=>'https://www.icloud.com/shortcuts', 'basic'=>'https://www.icloud.com/shortcuts/73384e6af3944425a5b87ae844baa4e7'],
'pc_to_iphone_file' => ['label'=>'PC → iPhone File', 'normal'=>'https://www.icloud.com/shortcuts/39cbccb0f60a490da86b77f8ae6b910f', 'basic'=>'https://www.icloud.com/shortcuts/8b7c0940c73e425eb397887a0a58fd23'],
'iphone_to_pc_file' => ['label'=>'iPhone → PC File', 'normal'=>'https://www.icloud.com/shortcuts/b9e0ca700e4241f2b38b47ad76315376', 'basic'=>'https://www.icloud.com/shortcuts/598f24596e4146cbabd7c26823c1c991'],
'text_memo' => ['label'=>'Text memo', 'normal'=>'https://www.icloud.com/shortcuts/a8a02ac525a44be68185aeb6960c2abb', 'basic'=>'https://www.icloud.com/shortcuts/7cd5a097fa824c909856a32ee8d7246b'],
'voice_text_memo' => ['label'=>'Voice text memo', 'normal'=>'https://www.icloud.com/shortcuts/a941139aae3f4fbfbff755d0c17928a3', 'basic'=>'https://www.icloud.com/shortcuts/c62614962aa4442aaf431d0f19c170a5'],
'camera_text' => ['label'=>'Camera to text', 'normal'=>'https://www.icloud.com/shortcuts/9df4e300737e411b9ef51e4e313a0844', 'basic'=>'https://www.icloud.com/shortcuts/4994e1d7229a43bc94b37bfa34ef0474'],
    ],
  ],
];

// --------------------
// i18n
// --------------------
$t = [
  'ja' => [
    'title' => 'クリップボード同期',
    'desc'  => 'ClipboardSync の設定に必要な情報を表示します。',
    'login_as' => 'ログイン中:',
    'storage_dir_user' => '保存先ディレクトリユーザー:',
    'logout' => 'ログアウト',
    'lang_label' => '言語',
    'theme_label' => 'テーマ',
    'dark' => 'Dark',
    'light' => 'Light',
    'section_app' => 'ClipboardSync（Windowsアプリ）',
    'dl_x64' => 'ClipboardSync（win-x64）',
    'dl_arm64' => 'ClipboardSync（win-arm64）',
    'btn_github' => 'GitHubからダウンロード',
    'not_set' => '未設定',
    'arm64_note' => 'win-arm64について',

    'section_api' => 'Notemod API（クリックでコピー）',
    'api_dir' => 'APIディレクトリURL（末尾 / まで）',
    'api_php' => 'api.php URL',
    'read_api_php' => 'read_api.php URL',
    'cleanup_api_php' => 'cleanup_api.php URL',

    'section_tokens' => 'API トークン（クリックでコピー）',
    'expected' => 'EXPECTED_TOKEN（通常アクセス用）',
    'admin' => 'ADMIN_TOKEN（管理用/cleanup用）',
    'login_required_tokens' => '※ トークンの表示/コピーはログイン後のみです',
    'token_note' => '※ 表示しているトークンは現在ログイン中ユーザーの config/<DIR_USER>/config.api.php を参照しています。',

    'section_shortcuts' => 'iPhoneショートカット',
    'basic_note' => 'APIフォルダーをBASIC認証で保護している場合は、BASIC認証対応版を使用してください。',
    'normal' => '通常版',
    'basic' => 'BASIC認証対応',

    'copied' => 'コピーしました',
    'copy_failed' => 'コピーに失敗しました（手動でコピーしてください）',
    'locked' => 'ロック中',
    'unlock' => 'ロック解除',
    'relock' => '再ロック',
    'copy' => 'コピー',
    'token_hidden' => '伏字のままではコピーできません',
    'token_fetch_failed' => 'トークンの取得に失敗しました',
    'token_unlocked' => '10秒間表示します',

    'go_back' => '戻る',
    'go_account' => 'アカウント設定へ',
    'go_setup_auth' => '認証設定へ',
    'go_log_settings' => 'ログ設定へ',
    'go_bak_settings' => 'バックアップ設定へ',
    'go_media_files' => 'メディア＆ファイルへ',
  ],
  'en' => [
    'title' => 'Clipboard sync',
    'desc'  => 'Shows information required to configure ClipboardSync.',
    'login_as' => 'Logged in as:',
    'storage_dir_user' => 'Storage directory user:',
    'logout' => 'Logout',
    'lang_label' => 'Language',
    'theme_label' => 'Theme',
    'dark' => 'Dark',
    'light' => 'Light',
    'section_app' => 'ClipboardSync (Windows app)',
    'dl_x64' => 'ClipboardSync (win-x64)',
    'dl_arm64' => 'ClipboardSync (win-arm64)',
    'btn_github' => 'Download from GitHub',
    'not_set' => 'Not set',
    'arm64_note' => 'About win-arm64',

    'section_api' => 'Notemod API (click to copy)',
    'api_dir' => 'API directory URL (ending with /)',
    'api_php' => 'api.php URL',
    'read_api_php' => 'read_api.php URL',
    'cleanup_api_php' => 'cleanup_api.php URL',

    'section_tokens' => 'API tokens (click to copy)',
    'expected' => 'EXPECTED_TOKEN (standard access)',
    'admin' => 'ADMIN_TOKEN (admin/cleanup)',
    'login_required_tokens' => 'Tokens are shown/copiable only after login.',
    'token_note' => 'The displayed tokens are read from config/<DIR_USER>/config.api.php for the currently logged-in user.',

    'section_shortcuts' => 'iPhone Shortcuts',
    'basic_note' => 'If your /api folder is protected with BASIC auth, use the BASIC-compatible versions.',
    'normal' => 'Normal',
    'basic' => 'BASIC auth',

    'copied' => 'Copied',
    'copy_failed' => 'Copy failed. Please copy manually.',
    'locked' => 'Locked',
    'unlock' => 'Unlock',
    'relock' => 'Relock',
    'copy' => 'Copy',
    'token_hidden' => 'Cannot copy while locked',
    'token_fetch_failed' => 'Failed to fetch token',
    'token_unlocked' => 'Visible for 10 seconds',

    'go_back' => 'Back',
    'go_account' => 'Go to Account',
    'go_setup_auth' => 'Go to Auth settings',
    'go_log_settings' => 'Go to Log settings',
    'go_bak_settings' => 'Go to Backup settings',
    'go_media_files' => 'Go to Media & Files',
  ],
];

if (!isset($t[$lang])) {
  $lang = 'ja';
}

// --------------------
// Helpers
// --------------------
function nm_invalidate_php_cache(string $path): void {
  clearstatcache(true, $path);
  if (function_exists('opcache_invalidate')) {
    @opcache_invalidate($path, true);
  }
}

function nm_read_php_config_array(string $path): array {
  if (!file_exists($path)) {
    return [];
  }
  nm_invalidate_php_cache($path);
  $arr = @require $path;
  return is_array($arr) ? $arr : [];
}

function nm_mask_token(string $s): string {
  $s = (string) $s;
  if ($s === '') {
    return '';
  }
  $len = strlen($s);
  if ($len <= 4) {
    return str_repeat('•', max(4, $len));
  }
  return str_repeat('•', 8) . substr($s, -4);
}

function nm_join_url_local(string $base, string $path): string {
  $base = rtrim($base, '/');
  $path = '/' . ltrim($path, '/');
  return $base . $path;
}

// --------------------
// Read user-specific config.api.php tokens
// --------------------
$configApiPath = '';
if ($currentDirUser !== '' && function_exists('nm_api_config_path')) {
  $configApiPath = (string) nm_api_config_path($currentDirUser);
}
if ($configApiPath === '' || !file_exists($configApiPath)) {
  $fallback = nm_api_config_path($currentDirUser ?: null);
  if (file_exists($fallback)) {
    $configApiPath = $fallback;
  }
}

$cfgApi = $configApiPath !== '' ? nm_read_php_config_array($configApiPath) : [];

$expectedToken = (string) ($cfgApi['EXPECTED_TOKEN'] ?? '');
$adminToken    = (string) ($cfgApi['ADMIN_TOKEN'] ?? '');
if ($adminToken === '') {
  $adminToken = $expectedToken;
}

// 常に初期表示は伏字
$displayExpected = nm_mask_token($expectedToken);
$displayAdmin    = nm_mask_token($adminToken);

// --------------------
// Build API URLs without using the request Host header.
// 期待形:
// - app base が /api のとき
//   https://host/api/
//   https://host/api/api/api.php
//   https://host/api/api/read_api.php
//   https://host/api/api/cleanup_api.php
// --------------------
$baseAppPath = function_exists('nm_base_path') ? (string) nm_base_path() : '';
if ($baseAppPath === '') {
  $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/clipboard_sync.php');
  $dir = str_replace('\\', '/', dirname($script));
  $baseAppPath = ($dir === '/' || $dir === '.' || $dir === '\\') ? '' : rtrim($dir, '/');
}

$baseAppUrl = nm_public_base_url();
if ($baseAppUrl === '') {
  $baseAppUrl = $baseAppPath;
}

$apiDirUrl     = rtrim($baseAppUrl, '/') . '/api/';
$apiUrl        = rtrim($baseAppUrl, '/') . '/api/api.php';
$readApiUrl    = rtrim($baseAppUrl, '/') . '/api/read_api.php';
$cleanupApiUrl = rtrim($baseAppUrl, '/') . '/api/cleanup_api.php';

// UI links
$csrfToken = function_exists('nm_csrf_token_get') ? nm_csrf_token_get() : '';
$u = nm_ui_toggle_urls('/clipboard_sync.php', $lang, $theme);
$backUrl = nm_ui_url('/');
$logoutUrl = nm_ui_url('/logout.php');
$accountUrl = nm_ui_url('/account.php');
$setupauthUrl = nm_ui_url('/setup_auth.php');
$logsettingsUrl = nm_ui_url('/log_settings.php');
$baksettingsUrl = nm_ui_url('/bak_settings.php');
$mediafilesUrl = nm_ui_url('/media_files.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reveal_token') {
  nm_send_security_headers_json();
  header('Content-Type: application/json; charset=utf-8');

  try {
    nm_csrf_validate_or_die();
  } catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  if (!$isLoggedIn || $currentDirUser === '') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  $kind = trim((string)($_POST['token_kind'] ?? ''));
  if ($kind !== 'expected' && $kind !== 'admin') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid token kind'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  $configPathNow = (string) nm_api_config_path($currentDirUser);
  $cfgNow = is_file($configPathNow) ? nm_read_php_config_array($configPathNow) : [];
  $expectedNow = (string)($cfgNow['EXPECTED_TOKEN'] ?? '');
  $adminNow = (string)($cfgNow['ADMIN_TOKEN'] ?? '');
  if ($adminNow === '') {
    $adminNow = $expectedNow;
  }

  $value = $kind === 'admin' ? $adminNow : $expectedNow;
  if ($value === '') {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Token not set'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  echo json_encode([
    'status' => 'ok',
    'kind' => $kind,
    'value' => $value,
    'masked' => nm_mask_token($value),
    'expires_in' => 10,
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($t[$lang]['title'], ENT_QUOTES, 'UTF-8') ?></title>
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
    .wrap{ width:min(990px, 100%); display:grid; gap:14px; }
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
      padding-bottom:10px;
    }
    .head .left{ display:flex; flex-direction:column; gap:4px; min-width:280px; }
    .head .right{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
    .title{ font-weight:900; letter-spacing:.3px; font-size:18px; margin:0; }
    .sub{ color:var(--muted); font-size:12px; margin-top:6px; }
    .meta{ color:var(--muted); font-size:13px; margin-top:6px; }
    .body{ padding:16px 18px 18px; display:grid; gap:14px; }
    .box{
      border:1px solid color-mix(in srgb, var(--line) 120%, transparent);
      border-radius:16px;
      padding:14px;
      background:var(--card2);
    }
    h3{ margin:0 0 10px; font-size:14px; }
    a{ color:var(--accent); text-decoration:none; }
    a:hover{ text-decoration:underline; }

    .notice{
      border-radius:16px;
      padding:10px 12px;
      font-size:13px;
      border:1px solid color-mix(in srgb, var(--line) 120%, transparent);
      background: var(--card2);
      white-space: pre-line;
    }
    .bad{
      border-color: color-mix(in srgb, var(--danger) 28%, transparent);
      background: color-mix(in srgb, var(--danger) 12%, transparent);
      color: color-mix(in srgb, var(--danger) 65%, var(--text));
    }

    .toggles{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; user-select:none; }
    .toggle-row{ display:flex; align-items:center; gap:8px; }
    .toggle-row span{ font-size:12px; color:var(--muted); }
    .pill{
      display:inline-flex;
      gap:3px;
      background: color-mix(in srgb, var(--card2) 60%, transparent);
      border:1px solid color-mix(in srgb, var(--line) 105%, transparent);
      padding:2px;
      border-radius:999px;
    }
    .pill a{
      font-size:12px;
      font-weight:800;
      padding:6px 8px;
      border-radius:999px;
      color:var(--muted);
      text-decoration:none;
      border:1px solid transparent;
      white-space:nowrap;
      line-height:1.1;
    }
    .pill a.active{
      background: color-mix(in srgb, var(--accent) 16%, transparent);
      color: var(--text);
      border-color: color-mix(in srgb, var(--accent) 26%, transparent);
    }
    .topbtn{
      display:inline-flex; align-items:center; gap:8px;
      padding:10px 12px;
      border-radius:999px;
      border:1px solid var(--line);
      background:color-mix(in srgb, var(--card2) 70%, transparent);
      color:var(--text);
      text-decoration:none;
      font-size:13px; font-weight:700;
      transition:.15s ease;
      user-select:none;
    }
    .topbtn:hover{ transform:translateY(-1px); border-color: color-mix(in srgb, var(--accent) 38%, var(--line)); text-decoration:none; }
    .topbtn.red{ border-color: color-mix(in srgb, var(--danger) 35%, var(--line)); color: color-mix(in srgb, var(--danger) 75%, var(--text)); }
    .topbtn.red:hover{ border-color: color-mix(in srgb, var(--danger) 60%, var(--line)); }
    @media (max-width: 700px){
      .head .right{ width:100%; justify-content:flex-start; }
    }

    .kv{ display:grid; gap:10px; }
    .row{ display:grid; gap:6px; }
    .k{ font-size:12px; color:var(--muted); }

    .copy{
      font-size:13px;
      color:var(--text);
      overflow-wrap:anywhere;
      word-break:break-word;
      padding:10px 12px;
      border-radius:14px;
      border:1px solid color-mix(in srgb, var(--line) 120%, transparent);
      background: color-mix(in srgb, var(--card2) 70%, transparent);
      cursor:pointer;
      user-select:none;
      position: relative;
    }
    .copy:hover{
      border-color: color-mix(in srgb, var(--accent) 35%, transparent);
      box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 10%, transparent);
    }
    .copy small{
      display:block;
      margin-top:6px;
      font-size:11px;
      color:var(--muted);
    }

    .grid2{ display:grid; grid-template-columns: 1fr 1fr; gap:10px; }
    @media (max-width: 680px){ .grid2{ grid-template-columns: 1fr; } }

    .btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      border:none;
      border-radius:14px;
      padding:12px 14px;
      font-weight:900;
      cursor:pointer;
      transition: transform .12s ease, filter .12s ease;
      width:100%;
      background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 100%, #fff), color-mix(in srgb, var(--accent) 70%, #6366f1));
      color: color-mix(in srgb, var(--text) 0%, #061021);
      box-shadow: 0 10px 25px color-mix(in srgb, var(--accent) 20%, transparent);
      text-decoration:none;
    }
    .btn:hover{ transform: translateY(-1px); filter: brightness(1.03); text-decoration:none; }
    .btn:active{ transform: translateY(0); filter: brightness(.98); }
    .btn[aria-disabled="true"]{
      opacity:.55;
      cursor:not-allowed;
      pointer-events:none;
    }

    .shortcutRow{
      display:grid;
      grid-template-columns: 1.1fr 1fr 1fr;
      gap:10px;
      align-items:stretch;
      margin-top:10px;
    }
    .shortcutRow .label{
      font-size:13px;
      color:var(--text);
      padding:10px 12px;
      border-radius:14px;
      border:1px solid color-mix(in srgb, var(--line) 120%, transparent);
      background: color-mix(in srgb, var(--card2) 60%, transparent);
      font-weight:800;
      display:flex;
      align-items:center;
    }
    .shortcutRow a, .shortcutRow .na{
      display:flex;
      align-items:center;
      justify-content:center;
      padding:10px 12px;
      border-radius:14px;
      border:1px solid color-mix(in srgb, var(--line) 120%, transparent);
      background: color-mix(in srgb, var(--card2) 70%, transparent);
      font-size:13px;
      text-decoration:none;
      color:var(--accent);
      font-weight:800;
    }
    .shortcutRow a:hover{
      text-decoration:none;
      filter: brightness(1.03);
      transform: translateY(-1px);
      transition: transform .12s ease, filter .12s ease;
    }
    .shortcutRow .na{
      color:var(--muted);
      font-weight:700;
      justify-content:flex-start;
    }

    .toast{
      position: fixed;
      left: 50%;
      bottom: 18px;
      transform: translateX(-50%);
      background: color-mix(in srgb, var(--card) 92%, transparent);
      border:1px solid color-mix(in srgb, var(--line) 120%, transparent);
      color:var(--text);
      padding:10px 12px;
      border-radius: 999px;
      box-shadow: var(--shadow);
      font-size: 13px;
      opacity: 0;
      pointer-events:none;
      transition: opacity .18s ease, transform .18s ease;
      z-index: 9999;
      white-space: nowrap;
    }
    .toast.show{
      opacity: 1;
      transform: translateX(-50%) translateY(-2px);
    }

    .row-links{ display:flex; gap:12px; flex-wrap:wrap;}
    .row-links a{ font-size:13px; color:var(--accent); }

  </style>
  <script>
  (function(){
    try{
      var p = new URLSearchParams(window.location.search);
      if (p.has('lang')) return;
      var sl = null;
      try { sl = localStorage.getItem('selectedLanguage'); } catch(e) {}
      var lang = (sl === 'JA') ? 'ja' : 'en';
      p.set('lang', lang);
      var newUrl = window.location.pathname + '?' + p.toString() + window.location.hash;
      window.location.replace(newUrl);
    }catch(e){}
  })();
  </script>


<script>
window.NM_BASE_PATH = <?= json_encode(function_exists('nm_base_path') ? nm_base_path() : '', JSON_UNESCAPED_SLASHES) ?>;
window.NM_CURRENT_DIR_USER = <?= json_encode($currentDirUser ?? '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
window.NM_CURRENT_USER = <?= json_encode($currentUser ?? '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
</head>
<body>
  <div class="wrap">
    <div class="card">

      <div class="head">
        <div class="left">
          <h1 class="title"><?= htmlspecialchars($t[$lang]['title'], ENT_QUOTES, 'UTF-8') ?></h1>
          <?php if ($isLoggedIn && $loginUser !== ''): ?>
            <div class="sub"><?= htmlspecialchars($t[$lang]['login_as'], ENT_QUOTES, 'UTF-8') ?> <b><?= htmlspecialchars($loginUser, ENT_QUOTES, 'UTF-8') ?></b> &nbsp; | &nbsp; <?= htmlspecialchars($t[$lang]['storage_dir_user'], ENT_QUOTES, 'UTF-8') ?> <b><?= htmlspecialchars($currentDirUser !== '' ? $currentDirUser : normalize_username((string)$loginUser), ENT_QUOTES, 'UTF-8') ?></b></div>
          <?php endif; ?>
          <div class="meta"><?= htmlspecialchars($t[$lang]['desc'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <div class="right">
          <a class="topbtn" href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">← <?= htmlspecialchars($t[$lang]['go_back'], ENT_QUOTES, 'UTF-8') ?></a>
          <a class="topbtn red" href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t[$lang]['logout'], ENT_QUOTES, 'UTF-8') ?></a>

          <div class="toggles">
            <div class="toggle-row">
              <span><?= htmlspecialchars($t[$lang]['lang_label'], ENT_QUOTES, 'UTF-8') ?></span>
              <div class="pill">
                <a href="<?= htmlspecialchars($u['langJa'], ENT_QUOTES, 'UTF-8') ?>" class="<?= $lang==='ja' ? 'active' : '' ?>">JP</a>
                <a href="<?= htmlspecialchars($u['langEn'], ENT_QUOTES, 'UTF-8') ?>" class="<?= $lang==='en' ? 'active' : '' ?>">EN</a>
              </div>
            </div>

            <div class="toggle-row">
              <span><?= htmlspecialchars($t[$lang]['theme_label'], ENT_QUOTES, 'UTF-8') ?></span>
              <div class="pill">
                <a href="<?= htmlspecialchars($u['dark'], ENT_QUOTES, 'UTF-8') ?>" class="<?= $theme==='dark' ? 'active' : '' ?>"><?= htmlspecialchars($t[$lang]['dark'], ENT_QUOTES, 'UTF-8') ?></a>
                <a href="<?= htmlspecialchars($u['light'], ENT_QUOTES, 'UTF-8') ?>" class="<?= $theme==='light' ? 'active' : '' ?>"><?= htmlspecialchars($t[$lang]['light'], ENT_QUOTES, 'UTF-8') ?></a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="body">
        <div class="box">
          <h3><?= htmlspecialchars($t[$lang]['section_app'], ENT_QUOTES, 'UTF-8') ?></h3>

          <div class="grid2">
            <div class="row">
              <div class="k"><?= htmlspecialchars($t[$lang]['dl_x64'], ENT_QUOTES, 'UTF-8') ?></div>
              <?php if (trim((string) $LINKS['download_win_x64']) !== ''): ?>
                <a class="btn" href="<?= htmlspecialchars($LINKS['download_win_x64'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                  <?= htmlspecialchars($t[$lang]['btn_github'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              <?php else: ?>
                <a class="btn" aria-disabled="true"><?= htmlspecialchars($t[$lang]['not_set'], ENT_QUOTES, 'UTF-8') ?></a>
              <?php endif; ?>
            </div>

            <div class="row">
              <div class="k"><?= htmlspecialchars($t[$lang]['dl_arm64'], ENT_QUOTES, 'UTF-8') ?></div>
              <?php if (trim((string) $LINKS['download_win_arm64']) !== ''): ?>
                <a class="btn" href="<?= htmlspecialchars($LINKS['download_win_arm64'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                  <?= htmlspecialchars($t[$lang]['btn_github'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              <?php else: ?>
                <a class="btn" aria-disabled="true"><?= htmlspecialchars($t[$lang]['not_set'], ENT_QUOTES, 'UTF-8') ?></a>
              <?php endif; ?>
            </div>
          </div>

          <div class="row" style="margin-top:12px;">
            <div class="k"><?= htmlspecialchars($t[$lang]['arm64_note'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="notice"><?= htmlspecialchars($LINKS['arm64_note'][$lang] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>

        <div class="box">
          <h3><?= htmlspecialchars($t[$lang]['section_api'], ENT_QUOTES, 'UTF-8') ?></h3>

          <div class="kv">
            <div class="row">
              <div class="k"><?= htmlspecialchars($t[$lang]['api_dir'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="copy" data-copy="<?= htmlspecialchars($apiDirUrl, ENT_QUOTES, 'UTF-8') ?>" data-public-url>
                <span class="url-value"><?= htmlspecialchars($apiDirUrl, ENT_QUOTES, 'UTF-8') ?></span>
                <small>click to copy</small>
              </div>
            </div>
            <div class="row">
              <div class="k"><?= htmlspecialchars($t[$lang]['api_php'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="copy" data-copy="<?= htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8') ?>" data-public-url>
                <span class="url-value"><?= htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8') ?></span>
                <small>click to copy</small>
              </div>
            </div>
            <div class="row">
              <div class="k"><?= htmlspecialchars($t[$lang]['read_api_php'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="copy" data-copy="<?= htmlspecialchars($readApiUrl, ENT_QUOTES, 'UTF-8') ?>" data-public-url>
                <span class="url-value"><?= htmlspecialchars($readApiUrl, ENT_QUOTES, 'UTF-8') ?></span>
                <small>click to copy</small>
              </div>
            </div>
            <div class="row">
              <div class="k"><?= htmlspecialchars($t[$lang]['cleanup_api_php'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="copy" data-copy="<?= htmlspecialchars($cleanupApiUrl, ENT_QUOTES, 'UTF-8') ?>" data-public-url>
                <span class="url-value"><?= htmlspecialchars($cleanupApiUrl, ENT_QUOTES, 'UTF-8') ?></span>
                <small>click to copy</small>
              </div>
            </div>
          </div>
        </div>

        <div class="box">
          <h3><?= htmlspecialchars($t[$lang]['section_tokens'], ENT_QUOTES, 'UTF-8') ?></h3>

          <?php if (!$isLoggedIn): ?>
            <div class="notice bad"><?= htmlspecialchars($t[$lang]['login_required_tokens'], ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>

          <?php if ($isLoggedIn): ?>
            <div class="notice" style="margin-top:10px;"><?= htmlspecialchars($t[$lang]['token_note'], ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>

          <div class="kv" style="margin-top:10px;">
            <div class="row">
              <div class="k"><?= htmlspecialchars($t[$lang]['expected'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="copy token-display" id="tokenDisplayExpected" data-kind="expected" data-masked="<?= htmlspecialchars($displayExpected !== '' ? $displayExpected : '—', ENT_QUOTES, 'UTF-8') ?>" data-locked="1">
                <?= htmlspecialchars($displayExpected !== '' ? $displayExpected : '—', ENT_QUOTES, 'UTF-8') ?>
                <small><?= htmlspecialchars($t[$lang]['locked'], ENT_QUOTES, 'UTF-8') ?></small>
              </div>
              <div style="display:flex; gap:10px; margin-top:10px; flex-wrap:wrap;">
                <button type="button" class="topbtn token-toggle" data-kind="expected"><?= htmlspecialchars($t[$lang]['unlock'], ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" class="topbtn token-copy" data-kind="expected"><?= htmlspecialchars($t[$lang]['copy'], ENT_QUOTES, 'UTF-8') ?></button>
              </div>
            </div>
            <div class="row">
              <div class="k"><?= htmlspecialchars($t[$lang]['admin'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="copy token-display" id="tokenDisplayAdmin" data-kind="admin" data-masked="<?= htmlspecialchars($displayAdmin !== '' ? $displayAdmin : '—', ENT_QUOTES, 'UTF-8') ?>" data-locked="1">
                <?= htmlspecialchars($displayAdmin !== '' ? $displayAdmin : '—', ENT_QUOTES, 'UTF-8') ?>
                <small><?= htmlspecialchars($t[$lang]['locked'], ENT_QUOTES, 'UTF-8') ?></small>
              </div>
              <div style="display:flex; gap:10px; margin-top:10px; flex-wrap:wrap;">
                <button type="button" class="topbtn token-toggle" data-kind="admin"><?= htmlspecialchars($t[$lang]['unlock'], ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" class="topbtn token-copy" data-kind="admin"><?= htmlspecialchars($t[$lang]['copy'], ENT_QUOTES, 'UTF-8') ?></button>
              </div>
            </div>
          </div>
        </div>

        <div class="box">
          <h3><?= htmlspecialchars($t[$lang]['section_shortcuts'], ENT_QUOTES, 'UTF-8') ?></h3>

          <div class="notice"><?= htmlspecialchars($t[$lang]['basic_note'], ENT_QUOTES, 'UTF-8') ?></div>

          <?php
            $shortcutSet = $LINKS['shortcuts'][$lang] ?? [];
            foreach ($shortcutSet as $sc):
              $label  = (string) ($sc['label'] ?? '');
              $normal = (string) ($sc['normal'] ?? '');
              $basic  = (string) ($sc['basic'] ?? '');
          ?>
            <div class="shortcutRow">
              <div class="label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>

              <?php if (trim($normal) !== ''): ?>
                <a href="<?= htmlspecialchars($normal, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t[$lang]['normal'], ENT_QUOTES, 'UTF-8') ?></a>
              <?php else: ?>
                <div class="na"><?= htmlspecialchars($t[$lang]['normal'] . ': ' . $t[$lang]['not_set'], ENT_QUOTES, 'UTF-8') ?></div>
              <?php endif; ?>

              <?php if (trim($basic) !== ''): ?>
                <a href="<?= htmlspecialchars($basic, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t[$lang]['basic'], ENT_QUOTES, 'UTF-8') ?></a>
              <?php else: ?>
                <div class="na"><?= htmlspecialchars($t[$lang]['basic'] . ': ' . $t[$lang]['not_set'], ENT_QUOTES, 'UTF-8') ?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="row-links">
      <a class="topbtn" href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">← <?= htmlspecialchars($t[$lang]['go_back'], ENT_QUOTES, 'UTF-8') ?></a>
      <a class="topbtn red" href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t[$lang]['logout'], ENT_QUOTES, 'UTF-8') ?></a>
          <a class="topbtn" href="<?=htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_account'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="topbtn" href="<?=htmlspecialchars($setupauthUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_setup_auth'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="topbtn" href="<?=htmlspecialchars($logsettingsUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_log_settings'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="topbtn" href="<?=htmlspecialchars($baksettingsUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_bak_settings'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="topbtn" href="<?=htmlspecialchars($mediafilesUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_media_files'], ENT_QUOTES, 'UTF-8')?></a>
    </div>

  </div>

  <div class="toast" id="toast"></div>

  <script>
    (function(){
      const toast = document.getElementById('toast');
      let timer = null;
      const tokenTimers = {};

      const TEXT_COPIED = <?= json_encode($t[$lang]['copied'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      const TEXT_COPY_FAILED = <?= json_encode($t[$lang]['copy_failed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      const TEXT_LOCKED = <?= json_encode($t[$lang]['locked'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      const TEXT_UNLOCK = <?= json_encode($t[$lang]['unlock'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      const TEXT_RELOCK = <?= json_encode($t[$lang]['relock'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      const TEXT_TOKEN_HIDDEN = <?= json_encode($t[$lang]['token_hidden'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      const TEXT_TOKEN_FETCH_FAILED = <?= json_encode($t[$lang]['token_fetch_failed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      const TEXT_TOKEN_UNLOCKED = <?= json_encode($t[$lang]['token_unlocked'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      const CSRF_TOKEN = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

      function showToast(msg){
        if(!toast) return;
        toast.textContent = msg;
        toast.classList.add('show');
        clearTimeout(timer);
        timer = setTimeout(()=>toast.classList.remove('show'), 1400);
      }

      async function copyText(text){
        try{
          if(navigator.clipboard && window.isSecureContext){
            await navigator.clipboard.writeText(text);
            return true;
          }
        }catch(e){}

        try{
          const ta = document.createElement('textarea');
          ta.value = text;
          ta.style.position='fixed';
          ta.style.left='-9999px';
          ta.setAttribute('readonly','');
          document.body.appendChild(ta);
          ta.select();
          ta.setSelectionRange(0, ta.value.length);
          const ok = document.execCommand('copy');
          document.body.removeChild(ta);
          return ok;
        }catch(e){
          return false;
        }
      }

      document.querySelectorAll('[data-public-url]').forEach(el => {
        const configured = el.getAttribute('data-copy') || '';
        if (!configured) return;
        try {
          const absolute = new URL(configured, window.location.origin).toString();
          el.setAttribute('data-copy', absolute);
          const value = el.querySelector('.url-value');
          if (value) value.textContent = absolute;
        } catch (_) {}
      });

      document.querySelectorAll('[data-copy]').forEach(el=>{
        el.addEventListener('click', async ()=>{
          const text = el.getAttribute('data-copy') || '';
          if(!text){
            showToast(TEXT_COPY_FAILED);
            return;
          }
          const ok = await copyText(text);
          showToast(ok ? TEXT_COPIED : TEXT_COPY_FAILED);
        });
      });

      function getTokenElements(kind){
        return {
          display: document.getElementById(kind === 'admin' ? 'tokenDisplayAdmin' : 'tokenDisplayExpected'),
          toggle: document.querySelector('.token-toggle[data-kind="' + kind + '"]'),
          copy: document.querySelector('.token-copy[data-kind="' + kind + '"]')
        };
      }

      function lockToken(kind){
        const els = getTokenElements(kind);
        if (!els.display) return;
        const masked = els.display.dataset.masked || '—';
        els.display.dataset.locked = '1';
        delete els.display.dataset.value;
        els.display.innerHTML = '';
        els.display.append(document.createTextNode(masked));
        const small = document.createElement('small');
        small.textContent = TEXT_LOCKED;
        els.display.appendChild(small);
        if (els.toggle) els.toggle.textContent = TEXT_UNLOCK;
      }

      function unlockToken(kind, value, expiresIn){
        const els = getTokenElements(kind);
        if (!els.display) return;
        els.display.dataset.locked = '0';
        els.display.dataset.value = value;
        els.display.innerHTML = '';
        els.display.append(document.createTextNode(value));
        const small = document.createElement('small');
        small.textContent = TEXT_TOKEN_UNLOCKED;
        els.display.appendChild(small);
        if (els.toggle) els.toggle.textContent = TEXT_RELOCK;

        if (tokenTimers[kind]) {
          clearTimeout(tokenTimers[kind]);
        }
        tokenTimers[kind] = setTimeout(() => lockToken(kind), Math.max(1, Number(expiresIn || 10)) * 1000);
      }

      async function revealToken(kind){
        const fd = new FormData();
        fd.append('action', 'reveal_token');
        fd.append('csrf_token', CSRF_TOKEN);
        fd.append('token_kind', kind);

        const res = await fetch(location.href, { method:'POST', body: fd });
        const txt = await res.text();
        let json = null;
        try { json = JSON.parse(txt); } catch (_) {}

        if (!res.ok || !json || json.status !== 'ok') {
          throw new Error((json && json.message) ? json.message : (txt || TEXT_TOKEN_FETCH_FAILED));
        }
        return json;
      }

      document.querySelectorAll('.token-toggle').forEach(btn => {
        btn.addEventListener('click', async () => {
          const kind = btn.dataset.kind || '';
          const els = getTokenElements(kind);
          if (!els.display) return;

          if (els.display.dataset.locked === '0') {
            lockToken(kind);
            return;
          }

          btn.disabled = true;
          try {
            const json = await revealToken(kind);
            unlockToken(kind, String(json.value || ''), Number(json.expires_in || 10));
            showToast(TEXT_TOKEN_UNLOCKED);
          } catch (e) {
            showToast((e && e.message) ? e.message : TEXT_TOKEN_FETCH_FAILED);
          } finally {
            btn.disabled = false;
          }
        });
      });

      document.querySelectorAll('.token-copy').forEach(btn => {
        btn.addEventListener('click', async () => {
          const kind = btn.dataset.kind || '';
          const els = getTokenElements(kind);
          if (!els.display) return;
          if (els.display.dataset.locked !== '0' || !els.display.dataset.value) {
            showToast(TEXT_TOKEN_HIDDEN);
            return;
          }
          const ok = await copyText(els.display.dataset.value);
          showToast(ok ? TEXT_COPIED : TEXT_COPY_FAILED);
        });
      });
    })();
  </script>
</body>
</html>
