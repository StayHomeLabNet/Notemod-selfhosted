<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_common.php';

/*
 * clipboard_sync.php
 * - ClipboardSync（旧ClipboardSender）向けの設定情報を一覧表示
 * - ClipboardSync DLは「GitHubからダウンロード」ボタン表示（URLは表示しない）
 * - api/read/cleanup URL はクリックでコピー
 * - EXPECTED_TOKEN / ADMIN_TOKEN もクリックでコピー
 * - JP/EN + Dark/Light + ログインユーザー表示
 */

header('Content-Type: text/html; charset=utf-8');

// session
nm_auth_start_session();

// UI bootstrap
$ui    = nm_ui_bootstrap();
$lang  = $ui['lang'] ?? 'ja';
$theme = $ui['theme'] ?? 'dark';

// auth
$isLoggedIn = function_exists('nm_auth_is_logged_in') ? (bool)nm_auth_is_logged_in() : false;

// --------------------
// ★ あなたのリンクに合わせて編集する場所
// --------------------
$LINKS = [
  // ClipboardSync (Windows) ダウンロードリンク（URLは画面に表示せずボタンにする）
  'download_win_x64'   => 'https://github.com/StayHomeLabNet/ClipboardSync/releases/download/v1.0.1/ClipboardSync.exe', // TODO: GitHub Releases など
  'download_win_arm64' => 'https://github.com/StayHomeLabNet/ClipboardSync/releases/download/v1.0.1/ClipboardSenderARM64.exe', // TODO

  // arm64説明（JP/EN）
  'arm64_note' => [
    'ja' => 'Windows on ARM（例：Snapdragon搭載PC）向けです。通常のIntel/AMD PCは win-x64 を使用してください。',
    'en' => 'For Windows on ARM (e.g., Snapdragon PCs). Typical Intel/AMD PCs should use win-x64.',
  ],

  // iPhoneショートカットDLリンク（言語で切替）
  // 1行2つ表示：通常版 / BASIC認証対応版
  'shortcuts' => [
    'ja' => [
      'pc_to_iphone' => ['label'=>'PC→iPhone', 'normal'=>'https://www.icloud.com/shortcuts/d47c8c55a73544dca641455fedc6bc99', 'basic'=>'https://www.icloud.com/shortcuts/9c52aec32bf845579df874e40c1fc588'],
      'iphone_to_pc' => ['label'=>'iPhone→PC', 'normal'=>'https://www.icloud.com/shortcuts/13e05e4e61184d3981f219a6fc1bcd51', 'basic'=>'https://www.icloud.com/shortcuts/164e49e144df48df8350c6c38eac4a69'],
      'text_memo' => ['label'=>'テキストメモ', 'normal'=>'https://www.icloud.com/shortcuts/543147bbfdbf4a12867e5c8fe8cb16f3', 'basic'=>'https://www.icloud.com/shortcuts/76733675ddb541a38930d5fcdd9368a8'],
      'voice_text_memo' => ['label'=>'音声テキストメモ', 'normal'=>'https://www.icloud.com/shortcuts/9bed01c187564a18ae79a1c6ef3402c3', 'basic'=>'https://www.icloud.com/shortcuts/e403298c5a544d86bd0db3007f939c69'],
      'camera_text' => ['label'=>'カメラからテキスト', 'normal'=>'https://www.icloud.com/shortcuts/eeaf6141e166475ca3fc9df84bc4a52d', 'basic'=>'https://www.icloud.com/shortcuts/2334c413578b4897a4c9df5c98d1c2e5'],
    ],
    'en' => [
      'pc_to_iphone' => ['label'=>'PC → iPhone', 'normal'=>'https://www.icloud.com/shortcuts/bb3ee1cf87944d53b2afa1510eef2431', 'basic'=>'https://www.icloud.com/shortcuts/dd7c1cd6bb2c473880e3d5d226d7ff4e'],
      'iphone_to_pc' => ['label'=>'iPhone → PC', 'normal'=>'https://www.icloud.com/shortcuts/95c6a708badb4459ab72f021875d0cfa', 'basic'=>'https://www.icloud.com/shortcuts/a38da767ffdf4c30b9c7dbb6f8b26ebf'],
      'text_memo' => ['label'=>'Text memo', 'normal'=>'https://www.icloud.com/shortcuts/243fcfeb6d884c86b070a5e0e36c0fd7', 'basic'=>'https://www.icloud.com/shortcuts/eda98a5240264addbd7592f62bae7682'],
      'voice_text_memo' => ['label'=>'Voice text memo', 'normal'=>'https://www.icloud.com/shortcuts/8b2cf52412c348508c4da3b9c6492003', 'basic'=>'https://www.icloud.com/shortcuts/c7c5f2aec3eb4682a6cad8573dff5993'],
      'camera_text' => ['label'=>'Camera to text', 'normal'=>'https://www.icloud.com/shortcuts/8c407b7bbb7e43a48089be6ea069b967', 'basic'=>'https://www.icloud.com/shortcuts/cb1ada0de51446a69c7a739d797f24a1'],
    ],
  ],
];

// --------------------
// i18n
// --------------------
$t = [
  'ja' => [
    'title' => 'クリップボード同期',
    'desc'  => 'ClipboardSync（旧ClipboardSender）の設定に必要な情報を表示します。',
    'login_as' => 'ログイン中:',
    'section_app' => 'ClipboardSync（Windowsアプリ）',
    'dl_x64' => 'ClipboardSync（win-x64）',
    'dl_arm64' => 'ClipboardSync（win-arm64）',
    'btn_github' => 'GitHubからダウンロード',
    'not_set' => '未設定',
    'arm64_note' => 'win-arm64について',

    'section_api' => 'Notemod API（クリックでコピー）',
    'api_php' => 'api.php URL',
    'read_api_php' => 'read_api.php URL',
    'cleanup_api_php' => 'cleanup_api.php URL',

    'section_tokens' => 'API トークン（クリックでコピー）',
    'expected' => 'EXPECTED_TOKEN（通常アクセス用）',
    'admin' => 'ADMIN_TOKEN（管理用/cleanup用）',
    'login_required_tokens' => '※ トークンの表示/コピーはログイン後のみです',

    'section_shortcuts' => 'iPhoneショートカット',
    'basic_note' => 'APIフォルダーをBASIC認証で保護している場合は、BASIC認証対応版を使用してください。',
    'normal' => '通常版',
    'basic' => 'BASIC認証対応',

    'copied' => 'コピーしました',
    'copy_failed' => 'コピーに失敗しました（手動でコピーしてください）',

    'go_back' => '戻る',
  ],
  'en' => [
    'title' => 'Clipboard sync',
    'desc'  => 'Shows information required to configure ClipboardSync (formerly ClipboardSender).',
    'login_as' => 'Logged in as:',
    'section_app' => 'ClipboardSync (Windows app)',
    'dl_x64' => 'ClipboardSync (win-x64)',
    'dl_arm64' => 'ClipboardSync (win-arm64)',
    'btn_github' => 'Download from GitHub',
    'not_set' => 'Not set',
    'arm64_note' => 'About win-arm64',

    'section_api' => 'Notemod API (click to copy)',
    'api_php' => 'api.php URL',
    'read_api_php' => 'read_api.php URL',
    'cleanup_api_php' => 'cleanup_api.php URL',

    'section_tokens' => 'API tokens (click to copy)',
    'expected' => 'EXPECTED_TOKEN (standard access)',
    'admin' => 'ADMIN_TOKEN (admin/cleanup)',
    'login_required_tokens' => 'Tokens are shown/copiable only after login.',

    'section_shortcuts' => 'iPhone Shortcuts',
    'basic_note' => 'If your /api folder is protected with BASIC auth, use the BASIC-compatible versions.',
    'normal' => 'Normal',
    'basic' => 'BASIC auth',

    'copied' => 'Copied',
    'copy_failed' => 'Copy failed. Please copy manually.',

    'go_back' => 'Back',
  ],
];

if (!isset($t[$lang])) $lang = 'ja';

// --------------------
// Helpers
// --------------------
function nm_invalidate_php_cache(string $path): void {
  clearstatcache(true, $path);
  if (function_exists('opcache_invalidate')) @opcache_invalidate($path, true);
}
function nm_read_php_config_array(string $path): array {
  if (!file_exists($path)) return [];
  nm_invalidate_php_cache($path);
  $arr = @require $path;
  return is_array($arr) ? $arr : [];
}
function nm_mask_token(string $s): string {
  $s = (string)$s;
  if ($s === '') return '';
  $len = strlen($s);
  if ($len <= 4) return str_repeat('•', max(4, $len));
  return str_repeat('•', 8) . substr($s, -4);
}
function nm_https_base_url(): string {
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/');
  $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
  if ($dir === '.' || $dir === '/') $dir = '';
  return 'https://' . $host . $dir;
}
function nm_join_url(string $base, string $path): string {
  $base = rtrim($base, '/');
  $path = '/' . ltrim($path, '/');
  return $base . $path;
}
function nm_current_username_safe(): string {
  if (function_exists('nm_auth_current_username')) {
    $u = (string)nm_auth_current_username();
    if ($u !== '') return $u;
  }
  if (function_exists('nm_auth_get_username')) {
    $u = (string)nm_auth_get_username();
    if ($u !== '') return $u;
  }
  if (session_status() === PHP_SESSION_ACTIVE) {
    foreach (['nm_username','username','user','nm_user','auth_user','NM_USER'] as $k) {
      if (isset($_SESSION[$k]) && is_string($_SESSION[$k]) && trim($_SESSION[$k]) !== '') {
        return (string)$_SESSION[$k];
      }
    }
  }
  return '';
}

// --------------------
// Read config.api.php tokens
// --------------------
$configApiPath = __DIR__ . '/config/config.api.php';
$cfgApi = nm_read_php_config_array($configApiPath);

$expectedToken = (string)($cfgApi['EXPECTED_TOKEN'] ?? '');
$adminToken    = (string)($cfgApi['ADMIN_TOKEN'] ?? '');
if ($adminToken === '') $adminToken = $expectedToken;

// 表示（未ログインなら伏字）
$displayExpected = $isLoggedIn ? $expectedToken : nm_mask_token($expectedToken);
$displayAdmin    = $isLoggedIn ? $adminToken    : nm_mask_token($adminToken);

// --------------------
// Build API URLs (https)
// --------------------
$base = nm_https_base_url();
$apiUrl        = nm_join_url($base, '/api/api.php');
$readApiUrl    = nm_join_url($base, '/api/read_api.php');
$cleanupApiUrl = nm_join_url($base, '/api/cleanup_api.php');

// UI links
$u = nm_ui_toggle_urls('/clipboard_sync.php', $lang, $theme);
$backUrl = nm_ui_url('/');

// user display
$loginUser = $isLoggedIn ? nm_current_username_safe() : '';
?>
<!doctype html>
<html lang="<?=htmlspecialchars($lang, ENT_QUOTES, 'UTF-8')?>" data-theme="<?=htmlspecialchars($theme, ENT_QUOTES, 'UTF-8')?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
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
    .wrap{ width:min(760px, 100%); display:grid; gap:14px; }
    .card{
      background:var(--card);
      border:1px solid var(--line);
      border-radius:var(--r);
      box-shadow:var(--shadow);
      overflow:hidden;
      backdrop-filter: blur(10px);
      position: relative;
    }

    /* Top-left login user badge */
    .userbadge{
      position:absolute;
      top:10px; left:10px;
      display:flex; align-items:center; gap:8px;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid color-mix(in srgb, var(--line) 120%, transparent);
      background: color-mix(in srgb, var(--card2) 70%, transparent);
      font-size:12px;
      color:var(--muted);
      user-select:none;
    }
    .userbadge b{ color:var(--text); font-weight:800; }

    .head{
      padding:18px;
      padding-top:44px; /* user badge space */
      background:linear-gradient(180deg, color-mix(in srgb, var(--accent) 10%, transparent), transparent);
      border-bottom:1px solid var(--line);
      display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;
    }
    .title{ font-weight:900; letter-spacing:.3px; }
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

    /* Top-right toggles */
    .toggles{
      position:absolute;
      top:8px;
      right:8px;
      display:flex;
      flex-direction:column;
      gap:6px;
      align-items:flex-end;
      user-select:none;
      transform: scale(.86);
      transform-origin: top right;
      opacity:.95;
    }
    .toggle-row{
      display:flex;
      gap:6px;
      align-items:center;
      justify-content:flex-end;
    }
    .toggle-row span{
      font-size:10px;
      color:var(--muted);
      margin-right:2px;
      line-height:1;
    }
    .pill{
      display:inline-flex;
      gap:3px;
      background: color-mix(in srgb, var(--card2) 60%, transparent);
      border:1px solid color-mix(in srgb, var(--line) 105%, transparent);
      padding:2px;
      border-radius:999px;
    }
    .pill a{
      font-size:10px;
      padding:4px 8px;
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
    @media (max-width: 600px){
      .toggles{ top:6px; right:6px; transform: scale(.80); }
      .head{ padding-top:52px; }
      .userbadge{ top:8px; left:8px; }
    }

    .kv{ display:grid; gap:10px; }
    .row{ display:grid; gap:6px; }
    .k{ font-size:12px; color:var(--muted); }

    /* Copyable pill */
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

    .row-links{ display:flex; gap:12px; flex-wrap:wrap; }
    .row-links a{ font-size:13px; color:var(--accent); }

    /* toast */
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
  </style>
  <script>
  // Notemod main language -> custom pages (JA only, otherwise EN)
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

</head>
<body>
  <div class="wrap">
    <div class="card">

      <?php if ($isLoggedIn && $loginUser !== ''): ?>
        <div class="userbadge"><?=htmlspecialchars($t[$lang]['login_as'], ENT_QUOTES, 'UTF-8')?> <b><?=htmlspecialchars($loginUser, ENT_QUOTES, 'UTF-8')?></b></div>
      <?php endif; ?>

      <div class="toggles">
        <div class="toggle-row">
          <span><?=htmlspecialchars('Language', ENT_QUOTES, 'UTF-8')?></span>
          <div class="pill">
            <a href="<?=htmlspecialchars($u['langJa'], ENT_QUOTES, 'UTF-8')?>" class="<?= $lang==='ja'?'active':'' ?>">JP</a>
            <a href="<?=htmlspecialchars($u['langEn'], ENT_QUOTES, 'UTF-8')?>" class="<?= $lang==='en'?'active':'' ?>">EN</a>
          </div>
        </div>

        <div class="toggle-row">
          <span><?=htmlspecialchars('Theme', ENT_QUOTES, 'UTF-8')?></span>
          <div class="pill">
            <a href="<?=htmlspecialchars($u['dark'], ENT_QUOTES, 'UTF-8')?>" class="<?= $theme==='dark'?'active':'' ?>">Dark</a>
            <a href="<?=htmlspecialchars($u['light'], ENT_QUOTES, 'UTF-8')?>" class="<?= $theme==='light'?'active':'' ?>">Light</a>
          </div>
        </div>
      </div>

      <div class="head">
        <div>
          <div class="title"><?=htmlspecialchars($t[$lang]['title'], ENT_QUOTES, 'UTF-8')?></div>
          <div class="meta"><?=htmlspecialchars($t[$lang]['desc'], ENT_QUOTES, 'UTF-8')?></div>
        </div>
      </div>

      <div class="body">

        
        <div class="row-links body-top-right" style="justify-content:flex-end; margin:-2px 0 14px;">
          <a href="<?=htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_back'], ENT_QUOTES, 'UTF-8')?></a>
        </div>
<div class="box">
          <h3><?=htmlspecialchars($t[$lang]['section_app'], ENT_QUOTES, 'UTF-8')?></h3>

          <div class="grid2">
            <div class="row">
              <div class="k"><?=htmlspecialchars($t[$lang]['dl_x64'], ENT_QUOTES, 'UTF-8')?></div>
              <?php if (trim((string)$LINKS['download_win_x64']) !== ''): ?>
                <a class="btn" href="<?=htmlspecialchars($LINKS['download_win_x64'], ENT_QUOTES, 'UTF-8')?>" target="_blank" rel="noopener">
                  <?=htmlspecialchars($t[$lang]['btn_github'], ENT_QUOTES, 'UTF-8')?>
                </a>
              <?php else: ?>
                <a class="btn" aria-disabled="true"><?=htmlspecialchars($t[$lang]['not_set'], ENT_QUOTES, 'UTF-8')?></a>
              <?php endif; ?>
            </div>

            <div class="row">
              <div class="k"><?=htmlspecialchars($t[$lang]['dl_arm64'], ENT_QUOTES, 'UTF-8')?></div>
              <?php if (trim((string)$LINKS['download_win_arm64']) !== ''): ?>
                <a class="btn" href="<?=htmlspecialchars($LINKS['download_win_arm64'], ENT_QUOTES, 'UTF-8')?>" target="_blank" rel="noopener">
                  <?=htmlspecialchars($t[$lang]['btn_github'], ENT_QUOTES, 'UTF-8')?>
                </a>
              <?php else: ?>
                <a class="btn" aria-disabled="true"><?=htmlspecialchars($t[$lang]['not_set'], ENT_QUOTES, 'UTF-8')?></a>
              <?php endif; ?>
            </div>
          </div>

          <div class="row" style="margin-top:12px;">
            <div class="k"><?=htmlspecialchars($t[$lang]['arm64_note'], ENT_QUOTES, 'UTF-8')?></div>
            <div class="notice"><?=htmlspecialchars($LINKS['arm64_note'][$lang] ?? '', ENT_QUOTES, 'UTF-8')?></div>
          </div>
        </div>

        <div class="box">
          <h3><?=htmlspecialchars($t[$lang]['section_api'], ENT_QUOTES, 'UTF-8')?></h3>

          <div class="kv">
            <div class="row">
              <div class="k"><?=htmlspecialchars($t[$lang]['api_php'], ENT_QUOTES, 'UTF-8')?></div>
              <div class="copy" data-copy="<?=htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8')?>">
                <?=htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8')?>
                <small>click to copy</small>
              </div>
            </div>
            <div class="row">
              <div class="k"><?=htmlspecialchars($t[$lang]['read_api_php'], ENT_QUOTES, 'UTF-8')?></div>
              <div class="copy" data-copy="<?=htmlspecialchars($readApiUrl, ENT_QUOTES, 'UTF-8')?>">
                <?=htmlspecialchars($readApiUrl, ENT_QUOTES, 'UTF-8')?>
                <small>click to copy</small>
              </div>
            </div>
            <div class="row">
              <div class="k"><?=htmlspecialchars($t[$lang]['cleanup_api_php'], ENT_QUOTES, 'UTF-8')?></div>
              <div class="copy" data-copy="<?=htmlspecialchars($cleanupApiUrl, ENT_QUOTES, 'UTF-8')?>">
                <?=htmlspecialchars($cleanupApiUrl, ENT_QUOTES, 'UTF-8')?>
                <small>click to copy</small>
              </div>
            </div>
          </div>
        </div>

        <div class="box">
          <h3><?=htmlspecialchars($t[$lang]['section_tokens'], ENT_QUOTES, 'UTF-8')?></h3>

          <?php if (!$isLoggedIn): ?>
            <div class="notice bad"><?=htmlspecialchars($t[$lang]['login_required_tokens'], ENT_QUOTES, 'UTF-8')?></div>
          <?php endif; ?>

          <div class="kv" style="margin-top:10px;">
            <div class="row">
              <div class="k"><?=htmlspecialchars($t[$lang]['expected'], ENT_QUOTES, 'UTF-8')?></div>
              <div class="copy" data-copy="<?=htmlspecialchars($displayExpected, ENT_QUOTES, 'UTF-8')?>">
                <?=htmlspecialchars($displayExpected !== '' ? $displayExpected : '—', ENT_QUOTES, 'UTF-8')?>
                <small>click to copy</small>
              </div>
            </div>
            <div class="row">
              <div class="k"><?=htmlspecialchars($t[$lang]['admin'], ENT_QUOTES, 'UTF-8')?></div>
              <div class="copy" data-copy="<?=htmlspecialchars($displayAdmin, ENT_QUOTES, 'UTF-8')?>">
                <?=htmlspecialchars($displayAdmin !== '' ? $displayAdmin : '—', ENT_QUOTES, 'UTF-8')?>
                <small>click to copy</small>
              </div>
            </div>
          </div>
        </div>

        <div class="box">
          <h3><?=htmlspecialchars($t[$lang]['section_shortcuts'], ENT_QUOTES, 'UTF-8')?></h3>

          <div class="notice"><?=htmlspecialchars($t[$lang]['basic_note'], ENT_QUOTES, 'UTF-8')?></div>

          <?php
            $shortcutSet = $LINKS['shortcuts'][$lang] ?? [];
            foreach ($shortcutSet as $sc):
              $label  = (string)($sc['label'] ?? '');
              $normal = (string)($sc['normal'] ?? '');
              $basic  = (string)($sc['basic'] ?? '');
          ?>
            <div class="shortcutRow">
              <div class="label"><?=htmlspecialchars($label, ENT_QUOTES, 'UTF-8')?></div>

              <?php if (trim($normal) !== ''): ?>
                <a href="<?=htmlspecialchars($normal, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['normal'], ENT_QUOTES, 'UTF-8')?></a>
              <?php else: ?>
                <div class="na"><?=htmlspecialchars($t[$lang]['normal'] . ': ' . $t[$lang]['not_set'], ENT_QUOTES, 'UTF-8')?></div>
              <?php endif; ?>

              <?php if (trim($basic) !== ''): ?>
                <a href="<?=htmlspecialchars($basic, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['basic'], ENT_QUOTES, 'UTF-8')?></a>
              <?php else: ?>
                <div class="na"><?=htmlspecialchars($t[$lang]['basic'] . ': ' . $t[$lang]['not_set'], ENT_QUOTES, 'UTF-8')?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="row-links">
          <a href="<?=htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_back'], ENT_QUOTES, 'UTF-8')?></a>
        </div>

      </div>
    </div>
  </div>

  <div class="toast" id="toast"></div>

  <script>
    (function(){
      const toast = document.getElementById('toast');
      let timer = null;

      function showToast(msg){
        if(!toast) return;
        toast.textContent = msg;
        toast.classList.add('show');
        clearTimeout(timer);
        timer = setTimeout(()=>toast.classList.remove('show'), 1200);
      }

      async function copyText(text){
        try{
          if(navigator.clipboard && window.isSecureContext){
            await navigator.clipboard.writeText(text);
            return true;
          }
        }catch(e){ /* ignore */ }

        // fallback
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

      document.querySelectorAll('[data-copy]').forEach(el=>{
        el.addEventListener('click', async ()=>{
          const text = el.getAttribute('data-copy') || '';
          if(!text){
            showToast('<?=htmlspecialchars($t[$lang]['copy_failed'], ENT_QUOTES, 'UTF-8')?>');
            return;
          }
          const ok = await copyText(text);
          showToast(ok ? '<?=htmlspecialchars($t[$lang]['copied'], ENT_QUOTES, 'UTF-8')?>' : '<?=htmlspecialchars($t[$lang]['copy_failed'], ENT_QUOTES, 'UTF-8')?>');
        });
      });
    })();
  </script>
</body>
</html>