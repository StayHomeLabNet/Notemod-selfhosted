<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_common.php';

nm_auth_require_login();
header('Content-Type: text/html; charset=utf-8');

// UI bootstrap
$ui = nm_ui_bootstrap();
$lang  = $ui['lang'];
$theme = $ui['theme'];

$t = [
  'ja' => [
    'title' => 'アカウント設定',
    'logged_as' => 'ログイン中:',
    'storage_dir_user' => '保存ディレクトリユーザー:',
    'back' => '戻る',
    'logout' => 'ログアウト',
    'updated' => '更新しました',
    'bad_current' => '現在のパスワードが違います',
    'save_failed' => '設定の保存に失敗しました（権限を確認）',
    'unknown' => '不明な操作です',
    'change_username' => 'ユーザー名を変更',
    'new_username' => '新しいユーザー名',
    'username_note' => 'ログイン名を変更しても、保存先ディレクトリ名は変更されません。',
    'change_password' => 'パスワードを変更',
    'new_password' => '新しいパスワード（10文字以上）',
    'repeat_password' => '新しいパスワード（再入力）',
    'current_password' => '現在のパスワード',
    'btn_username' => 'ユーザー名を更新',
    'btn_password' => 'パスワードを更新',
    'pw_mismatch' => '新しいパスワードが一致しません',
    'pw_short' => '新しいパスワードは10文字以上にしてください',
    'pw_hash_fail' => 'パスワードの保存に失敗しました',
    'note_api' => 'API ディレクトリに Basic 認証を使用することをおすすめします。この画面は Notemod-selfhosted へのログインにのみ使用されます',
    'show_storage' => '現在の主要ディレクトリとファイルを表示',
    'storage_note_1' => 'ログイン名を変更しても、保存先ディレクトリ名は変更されません。',
    'storage_note_2' => '現在の物理保存先は以下の通りです。',
    'lang_label' => '言語',
    'theme_label' => 'テーマ',
    'dark' => 'Dark',
    'light' => 'Light',
    'new_username_empty' => '新しいユーザー名を入力してください',
  ],
  'en' => [
    'title' => 'Account',
    'logged_as' => 'Logged in as:',
    'storage_dir_user' => 'Storage directory user:',
    'back' => 'Back',
    'logout' => 'Logout',
    'updated' => 'Updated',
    'bad_current' => 'Current password is incorrect',
    'save_failed' => 'Failed to save settings (permission?)',
    'unknown' => 'Unknown operation',
    'change_username' => 'Change Username',
    'new_username' => 'New username',
    'username_note' => 'Changing the login name does not change the storage directory name.',
    'change_password' => 'Change Password',
    'new_password' => 'New password (min 10 chars)',
    'repeat_password' => 'Repeat new password',
    'current_password' => 'Current password',
    'btn_username' => 'Update Username',
    'btn_password' => 'Update Password',
    'pw_mismatch' => 'New passwords do not match',
    'pw_short' => 'New password must be at least 10 characters',
    'pw_hash_fail' => 'Failed to hash password',
    'note_api' => 'It is recommended to use Basic Authentication for the API directory. This screen is only used for logging in to Notemod-selfhosted.',
    'show_storage' => 'Show current main directories and files',
    'storage_note_1' => 'Changing the login name does not change the storage directory name.',
    'storage_note_2' => 'Your current physical storage paths are:',
    'lang_label' => 'Language',
    'theme_label' => 'Theme',
    'dark' => 'Dark',
    'light' => 'Light',
    'new_username_empty' => 'Please enter a new username',
  ],
];

$cfg = nm_auth_load();
$msg = '';
$err = '';

$loginUser = (string)(nm_get_current_user() ?: ($cfg['USERNAME'] ?? ''));
$currentDirUser = (string)(nm_get_current_dir_user() ?: ($cfg['DIR_USER'] ?? normalize_username($loginUser)));



function nm_force_logout_after_account_change(): void
{
    nm_auth_start_session();

    unset(
        $_SESSION['nm_logged_in'],
        $_SESSION['nm_user'],
        $_SESSION['nm_dir_user'],
        $_SESSION['nm_username'],
        $_SESSION['nm_login_user']
    );

    $logoutUrl = function_exists('nm_url') ? nm_url('logout.php') : 'logout.php';
    header('Location: ' . $logoutUrl);
    exit;
}

if ($currentDirUser === '') {
    $currentDirUser = normalize_username($loginUser);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $mode = (string)($_POST['mode'] ?? '');
    $currentPass = (string)($_POST['current_password'] ?? '');

    if (!password_verify($currentPass, (string)($cfg['PASSWORD_HASH'] ?? ''))) {
        $err = $t[$lang]['bad_current'];
    } else {
        $newUser = (string)($cfg['USERNAME'] ?? '');
        $newHash = (string)($cfg['PASSWORD_HASH'] ?? '');

        if ($mode === 'change_username') {
            $uu = trim((string)($_POST['new_username'] ?? ''));
            if ($uu === '') {
                $err = $t[$lang]['new_username_empty'];
            } else {
                $newUser = $uu; // USERNAME only. DIR_USER remains unchanged.
            }

        } elseif ($mode === 'change_password') {
            $p1 = (string)($_POST['new_password'] ?? '');
            $p2 = (string)($_POST['new_password2'] ?? '');

            if ($p1 !== $p2) {
                $err = $t[$lang]['pw_mismatch'];
            } elseif (strlen($p1) < 10) {
                $err = $t[$lang]['pw_short'];
            } else {
                $h = password_hash($p1, PASSWORD_DEFAULT);
                if (!$h) {
                    $err = $t[$lang]['pw_hash_fail'];
                } else {
                    $newHash = $h;
                }
            }

        } else {
            $err = $t[$lang]['unknown'];
        }

        if ($err === '') {
            if (!nm_auth_write_config($newUser, $newHash, $currentDirUser)) {
                $err = $t[$lang]['save_failed'];
            } else {
                $_SESSION['nm_user'] = $newUser;
                $_SESSION['nm_dir_user'] = $currentDirUser;
                $cfg = nm_auth_load();
                $loginUser = (string)($cfg['USERNAME'] ?? $newUser);
                nm_force_logout_after_account_change();
            }
        }
    }
}

$authPathRel      = 'config/' . $currentDirUser . '/auth.php';
$configPathRel    = 'config/' . $currentDirUser . '/config.php';
$configApiPathRel = 'config/' . $currentDirUser . '/config.api.php';
$dataJsonRel      = 'notemod-data/' . $currentDirUser . '/data.json';
$imagesDirRel     = 'notemod-data/' . $currentDirUser . '/images/';
$filesDirRel      = 'notemod-data/' . $currentDirUser . '/files/';
$logsDirRel       = 'logs/' . $currentDirUser . '/';

$base = function_exists('nm_auth_base_url') ? nm_auth_base_url() : nm_base_path();
$user = $loginUser;

// Toggle URLs
$u = nm_ui_toggle_urls('/account.php', $lang, $theme);

// Links
$logoutUrl = nm_ui_url('/logout.php');
$backUrl   = rtrim((string)$base, '/') . '/';
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
    .head-left{ display:flex; flex-direction:column; gap:4px; }
    .head-right{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
    .title{ font-weight:900; letter-spacing:.3px; font-size:18px; margin:0; }
    .meta{ color:var(--muted); font-size:13px; margin-top:6px; }
    .toolbar-label{ font-size:12px; color:var(--muted); }
    .header-btn{
      display:inline-flex; align-items:center; gap:8px;
      padding:10px 12px;
      border-radius:999px;
      border:1px solid var(--line);
      background:color-mix(in srgb, var(--card2) 70%, transparent);
      color:var(--text);
      cursor:pointer; text-decoration:none;
      font-size:13px; font-weight:700;
      transition:.15s ease;
      user-select:none; white-space:nowrap;
    }
    .header-btn:hover{ transform:translateY(-1px); border-color:color-mix(in srgb, var(--accent) 38%, var(--line)); text-decoration:none; }
    .header-btn.red{ border-color:color-mix(in srgb, var(--danger) 35%, var(--line)); color:color-mix(in srgb, var(--danger) 75%, var(--text)); }
    .header-btn.red:hover{ border-color:color-mix(in srgb, var(--danger) 60%, var(--line)); }
    .header-pill{
      display:inline-flex; gap:10px; align-items:center; flex-wrap:wrap;
      padding:10px 12px; border-radius:999px; border:1px solid var(--line);
      background:color-mix(in srgb, var(--card2) 75%, transparent); font-size:13px;
    }
    .header-pill a{
      text-decoration:none; color:var(--muted); font-weight:800; font-size:12px;
      padding:6px 8px; border-radius:999px; border:1px solid transparent; white-space:nowrap;
    }
    .header-pill a.active{
      color:var(--text);
      border-color:color-mix(in srgb, var(--accent) 45%, var(--line));
      background:color-mix(in srgb, var(--accent) 12%, transparent);
    }

    .body{ padding:16px 18px 18px; display:grid; gap:14px; }
    .grid{ display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    @media (max-width:760px){ .grid{ grid-template-columns:1fr; } }
    .box{
      border:1px solid color-mix(in srgb, var(--line) 120%, transparent);
      border-radius:16px;
      padding:14px;
      background:var(--card2);
    }
    h3{ margin:0 0 10px; font-size:14px; }
    label{ display:block; font-size:12px; color:var(--muted); margin:10px 0 6px; }
    input{
      width:100%;
      padding:12px 12px;
      border-radius:14px;
      border:1px solid color-mix(in srgb, var(--line) 140%, transparent);
      background: color-mix(in srgb, var(--card2) 85%, transparent);
      color:var(--text);
      outline:none;
    }
    input:focus{
      border-color: color-mix(in srgb, var(--accent) 70%, transparent);
      box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 14%, transparent);
    }
    .btn{
      border:none; border-radius:14px;
      padding:12px 14px;
      font-weight:900;
      cursor:pointer;
      transition: transform .12s ease, filter .12s ease;
      width:100%;
      background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 100%, #fff), color-mix(in srgb, var(--accent) 70%, #6366f1));
      color: color-mix(in srgb, var(--text) 0%, #061021);
      box-shadow: 0 10px 25px color-mix(in srgb, var(--accent) 20%, transparent);
      margin-top:12px;
    }
    .btn:hover{ transform: translateY(-1px); filter: brightness(1.03); }
    .btn:active{ transform: translateY(0); filter: brightness(.98); }

    .notice{
      border-radius:16px;
      padding:10px 12px;
      font-size:13px;
      border:1px solid color-mix(in srgb, var(--line) 120%, transparent);
      background: var(--card2);
      line-height: 1.5;
    }
    .ok{
      border-color: color-mix(in srgb, var(--ok) 28%, transparent);
      background: color-mix(in srgb, var(--ok) 12%, transparent);
      color: color-mix(in srgb, var(--ok) 65%, var(--text));
    }
    .bad{
      border-color: color-mix(in srgb, var(--danger) 28%, transparent);
      background: color-mix(in srgb, var(--danger) 12%, transparent);
      color: color-mix(in srgb, var(--danger) 65%, var(--text));
    }

    .subnote{
      margin-top:8px;
      color:var(--muted);
      font-size:12px;
      line-height:1.5;
    }

    .storage-details{
      border:1px solid color-mix(in srgb, var(--line) 120%, transparent);
      border-radius:16px;
      background:var(--card2);
      overflow:hidden;
    }
    .storage-summary{
      list-style:none;
      cursor:pointer;
      padding:14px 16px;
      font-weight:800;
      user-select:none;
    }
    .storage-summary::-webkit-details-marker{ display:none; }
    .storage-content{
      padding:0 16px 16px;
      color:var(--text);
      font-size:13px;
      line-height:1.65;
    }
    .storage-list{
      margin:10px 0 0;
      padding-left:18px;
    }
    .storage-list code{
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size:12px;
      word-break:break-all;
    }

    a{ color:var(--accent); text-decoration:none; }
    a:hover{ text-decoration:underline; }

    .row-links{ display:flex; gap:12px; flex-wrap:wrap; }
    .row-links a{ font-size:13px; color:var(--accent); }
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
        <div class="head-left">
          <div class="title"><?=htmlspecialchars($t[$lang]['title'], ENT_QUOTES, 'UTF-8')?></div>
          <div class="meta" style="white-space: nowrap;"><?=htmlspecialchars($t[$lang]['logged_as'], ENT_QUOTES, 'UTF-8')?> <b><?=htmlspecialchars($user, ENT_QUOTES, 'UTF-8')?></b> &nbsp; | &nbsp; <?=htmlspecialchars($t[$lang]['storage_dir_user'], ENT_QUOTES, 'UTF-8')?> <b><?=htmlspecialchars($currentDirUser !== '' ? $currentDirUser : normalize_username((string)$user), ENT_QUOTES, 'UTF-8')?></b></div>
        </div>
        <div class="head-right">
          <a class="header-btn" href="<?=htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8')?>">← <?=htmlspecialchars($t[$lang]['back'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="header-btn red" href="<?=htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['logout'], ENT_QUOTES, 'UTF-8')?></a>
          <span class="toolbar-label"><?=htmlspecialchars($t[$lang]['lang_label'], ENT_QUOTES, 'UTF-8')?></span>
          <div class="header-pill">
            <a href="<?=htmlspecialchars($u['langJa'], ENT_QUOTES, 'UTF-8')?>" class="<?= $lang==='ja'?'active':'' ?>">JP</a>
            <a href="<?=htmlspecialchars($u['langEn'], ENT_QUOTES, 'UTF-8')?>" class="<?= $lang==='en'?'active':'' ?>">EN</a>
          </div>
          <span class="toolbar-label"><?=htmlspecialchars($t[$lang]['theme_label'], ENT_QUOTES, 'UTF-8')?></span>
          <div class="header-pill">
            <a href="<?=htmlspecialchars($u['dark'], ENT_QUOTES, 'UTF-8')?>" class="<?= $theme==='dark'?'active':'' ?>"><?=htmlspecialchars($t[$lang]['dark'], ENT_QUOTES, 'UTF-8')?></a>
            <a href="<?=htmlspecialchars($u['light'], ENT_QUOTES, 'UTF-8')?>" class="<?= $theme==='light'?'active':'' ?>"><?=htmlspecialchars($t[$lang]['light'], ENT_QUOTES, 'UTF-8')?></a>
          </div>
        </div>
      </div>

      <div class="body">
        <?php if ($msg): ?><div class="notice ok"><?=htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
        <?php if ($err): ?><div class="notice bad"><?=htmlspecialchars($err, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>

        <div class="grid">
          <div class="box">
            <h3><?=htmlspecialchars($t[$lang]['change_username'], ENT_QUOTES, 'UTF-8')?></h3>
            <form method="post">
              <input type="hidden" name="mode" value="change_username">
              <label><?=htmlspecialchars($t[$lang]['new_username'], ENT_QUOTES, 'UTF-8')?></label>
              <input name="new_username" required value="<?=htmlspecialchars($user, ENT_QUOTES, 'UTF-8')?>">
              <label><?=htmlspecialchars($t[$lang]['current_password'], ENT_QUOTES, 'UTF-8')?></label>
              <input name="current_password" type="password" required autocomplete="current-password">
              <button class="btn" type="submit"><?=htmlspecialchars($t[$lang]['btn_username'], ENT_QUOTES, 'UTF-8')?></button>
            </form>
            <div class="subnote"><?=htmlspecialchars($t[$lang]['username_note'], ENT_QUOTES, 'UTF-8')?></div>
          </div>

          <div class="box">
            <h3><?=htmlspecialchars($t[$lang]['change_password'], ENT_QUOTES, 'UTF-8')?></h3>
            <form method="post">
              <input type="hidden" name="mode" value="change_password">
              <label><?=htmlspecialchars($t[$lang]['new_password'], ENT_QUOTES, 'UTF-8')?></label>
              <input name="new_password" type="password" required>
              <label><?=htmlspecialchars($t[$lang]['repeat_password'], ENT_QUOTES, 'UTF-8')?></label>
              <input name="new_password2" type="password" required>
              <label><?=htmlspecialchars($t[$lang]['current_password'], ENT_QUOTES, 'UTF-8')?></label>
              <input name="current_password" type="password" required autocomplete="current-password">
              <button class="btn" type="submit"><?=htmlspecialchars($t[$lang]['btn_password'], ENT_QUOTES, 'UTF-8')?></button>
            </form>
          </div>
        </div>

        <details class="storage-details">
          <summary class="storage-summary"><?=htmlspecialchars($t[$lang]['show_storage'], ENT_QUOTES, 'UTF-8')?></summary>
          <div class="storage-content">
            <div><?=htmlspecialchars($t[$lang]['storage_note_1'], ENT_QUOTES, 'UTF-8')?></div>
            <div><?=htmlspecialchars($t[$lang]['storage_note_2'], ENT_QUOTES, 'UTF-8')?></div>
            <ul class="storage-list">
              <li><code><?=htmlspecialchars($authPathRel, ENT_QUOTES, 'UTF-8')?></code></li>
              <li><code><?=htmlspecialchars($configPathRel, ENT_QUOTES, 'UTF-8')?></code></li>
              <li><code><?=htmlspecialchars($configApiPathRel, ENT_QUOTES, 'UTF-8')?></code></li>
              <li><code><?=htmlspecialchars($dataJsonRel, ENT_QUOTES, 'UTF-8')?></code></li>
              <li><code><?=htmlspecialchars($imagesDirRel, ENT_QUOTES, 'UTF-8')?></code></li>
              <li><code><?=htmlspecialchars($filesDirRel, ENT_QUOTES, 'UTF-8')?></code></li>
              <li><code><?=htmlspecialchars($logsDirRel, ENT_QUOTES, 'UTF-8')?></code></li>
            </ul>
          </div>
        </details>

        <div class="notice"><?=htmlspecialchars($t[$lang]['note_api'], ENT_QUOTES, 'UTF-8')?></div>
      </div>
    </div>
    
    <div class="row-links">
      <a class="header-btn" href="<?=htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8')?>">← <?=htmlspecialchars($t[$lang]['back'], ENT_QUOTES, 'UTF-8')?></a>
      <a class="header-btn red" href="<?=htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['logout'], ENT_QUOTES, 'UTF-8')?></a>
    </div>
    
  </div>
</body>
</html>