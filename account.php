<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_common.php';

nm_auth_require_login();
header('Content-Type: text/html; charset=utf-8');

// ★ 共通化：lang/theme の確定（GET→SESSION反映含む）
$ui = nm_ui_bootstrap();
$lang  = $ui['lang'];
$theme = $ui['theme'];

$t = [
  'ja' => [
    'title' => 'アカウント設定',
    'logged_as' => 'ログイン中:',
    'back' => '戻る',
    'logout' => 'ログアウト',
    'updated' => '更新しました',
    'bad_current' => '現在のパスワードが違います',
    'save_failed' => 'config/auth.php の保存に失敗しました（権限を確認）',
    'unknown' => '不明な操作です',
    'change_username' => 'ユーザー名を変更',
    'new_username' => '新しいユーザー名',
    'change_password' => 'パスワードを変更',
    'new_password' => '新しいパスワード（10文字以上）',
    'repeat_password' => '新しいパスワード（再入力）',
    'current_password' => '現在のパスワード',
    'btn_username' => 'ユーザー名を更新',
    'btn_password' => 'パスワードを更新',
    'pw_mismatch' => '新しいパスワードが一致しません',
    'pw_short' => '新しいパスワードは10文字以上にしてください',
    'pw_hash_fail' => 'パスワードの保存に失敗しました',
    'note_api' => 'API は Basic 認証を使用してください。この画面は Notemod-selfhosted へのログインにのみ使用されます',
    'lang_label' => 'Language',
    'theme_label' => 'Theme',
    'dark' => 'Dark',
    'light' => 'Light',
  ],
  'en' => [
    'title' => 'Account',
    'logged_as' => 'Logged in as:',
    'back' => 'Back',
    'logout' => 'Logout',
    'updated' => 'Updated',
    'bad_current' => 'Current password is incorrect',
    'save_failed' => 'Failed to write config/auth.php (permission?)',
    'unknown' => 'Unknown operation',
    'change_username' => 'Change Username',
    'new_username' => 'New username',
    'change_password' => 'Change Password',
    'new_password' => 'New password (min 10 chars)',
    'repeat_password' => 'Repeat new password',
    'current_password' => 'Current password',
    'btn_username' => 'Update Username',
    'btn_password' => 'Update Password',
    'pw_mismatch' => 'New passwords do not match',
    'pw_short' => 'New password must be at least 10 characters',
    'pw_hash_fail' => 'Failed to hash password',
    'note_api' => 'Please use Basic Authentication for the API. This screen is only used for logging in to Notemod-selfhosted.',
    'lang_label' => 'Language',
    'theme_label' => 'Theme',
    'dark' => 'Dark',
    'light' => 'Light',
  ],
];

$cfg = nm_auth_load();
$msg = '';
$err = '';

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
            if ($uu === '') $err = $t[$lang]['new_username'] . ' is empty';
            else $newUser = $uu;

        } elseif ($mode === 'change_password') {
            $p1 = (string)($_POST['new_password'] ?? '');
            $p2 = (string)($_POST['new_password2'] ?? '');

            if ($p1 !== $p2) $err = $t[$lang]['pw_mismatch'];
            elseif (strlen($p1) < 10) $err = $t[$lang]['pw_short'];
            else {
                $h = password_hash($p1, PASSWORD_DEFAULT);
                if (!$h) $err = $t[$lang]['pw_hash_fail'];
                else $newHash = $h;
            }

        } else {
            $err = $t[$lang]['unknown'];
        }

        if ($err === '') {
            if (!nm_auth_write_config($newUser, $newHash)) {
                $err = $t[$lang]['save_failed'];
            } else {
                $_SESSION['nm_user'] = $newUser;
                $cfg = nm_auth_load();
                $msg = $t[$lang]['updated'];
            }
        }
    }
}

$base = nm_auth_base_url();
$user = (string)($cfg['USERNAME'] ?? '');

// ★ 共通化：トグルURL（このページ用）
$u = nm_ui_toggle_urls('/account.php', $lang, $theme);

// ★ 共通化：logoutリンク（lang/theme付き）
$logoutUrl = nm_ui_url('/logout.php');
$backUrl   = rtrim($base, '/') . '/';
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

    .wrap{ width:min(720px, 100%); display:grid; gap:14px; }
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
      padding-right: 120px; /* トグルとかぶりにくく */
      padding-bottom: 10px;
    }
    .title{ font-weight:900; letter-spacing:.3px; }
    .meta{ color:var(--muted); font-size:13px; margin-top:6px; }

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

    a{ color:var(--accent); text-decoration:none; }
    a:hover{ text-decoration:underline; }

    /* Top-right toggles (smaller + right aligned) */
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
      opacity:.96;
      z-index: 5;
    }
    .toggle-row{ display:flex; gap:6px; align-items:center; justify-content:flex-end; }
    .toggle-row span{ font-size:10px; color:var(--muted); margin-right:2px; line-height:1; }
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
      .toggles{ top:6px; right:6px; transform: scale(.82); }
      .head{ padding-right: 18px; }
    }

    /* Back/Logout row under notice */
    .action-row{
      display:flex;
      gap:10px;
      justify-content:flex-end;
      flex-wrap:wrap;
    }
    .action-row a{
      font-size:13px;
      padding:8px 10px;
      border-radius:12px;
      background: color-mix(in srgb, var(--card2) 70%, transparent);
      border:1px solid color-mix(in srgb, var(--line) 120%, transparent);
      text-decoration:none;
    }
    .action-row a:hover{
      text-decoration:none;
      filter: brightness(1.02);
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">

      <div class="toggles">
        <div class="toggle-row">
          <span><?=htmlspecialchars($t[$lang]['lang_label'], ENT_QUOTES, 'UTF-8')?></span>
          <div class="pill">
            <a href="<?=htmlspecialchars($u['langJa'], ENT_QUOTES, 'UTF-8')?>" class="<?= $lang==='ja'?'active':'' ?>">JP</a>
            <a href="<?=htmlspecialchars($u['langEn'], ENT_QUOTES, 'UTF-8')?>" class="<?= $lang==='en'?'active':'' ?>">EN</a>
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

      <div class="head">
        <div>
          <div class="title"><?=htmlspecialchars($t[$lang]['title'], ENT_QUOTES, 'UTF-8')?></div>
          <div class="meta"><?=htmlspecialchars($t[$lang]['logged_as'], ENT_QUOTES, 'UTF-8')?> <b><?=htmlspecialchars($user, ENT_QUOTES, 'UTF-8')?></b></div>
        </div>
      </div>

      <div class="body">
        <?php if ($msg): ?><div class="notice ok"><?=htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
        <?php if ($err): ?><div class="notice bad"><?=htmlspecialchars($err, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>

        <?php if ($msg || $err): ?>
          <div class="action-row">
            <a href="<?=htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['back'], ENT_QUOTES, 'UTF-8')?></a>
            <a href="<?=htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['logout'], ENT_QUOTES, 'UTF-8')?></a>
          </div>
        <?php else: ?>
          <div class="action-row" style="margin-top:-4px;">
            <a href="<?=htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['back'], ENT_QUOTES, 'UTF-8')?></a>
            <a href="<?=htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['logout'], ENT_QUOTES, 'UTF-8')?></a>
          </div>
        <?php endif; ?>

        <div class="grid">
          <div class="box">
            <h3><?=htmlspecialchars($t[$lang]['change_username'], ENT_QUOTES, 'UTF-8')?></h3>
            <form method="post">
              <input type="hidden" name="mode" value="change_username">
              <label><?=htmlspecialchars($t[$lang]['new_username'], ENT_QUOTES, 'UTF-8')?></label>
              <input name="new_username" required>
              <label><?=htmlspecialchars($t[$lang]['current_password'], ENT_QUOTES, 'UTF-8')?></label>
              <input name="current_password" type="password" required autocomplete="current-password">
              <button class="btn" type="submit"><?=htmlspecialchars($t[$lang]['btn_username'], ENT_QUOTES, 'UTF-8')?></button>
            </form>
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

        <div class="notice"><?=htmlspecialchars($t[$lang]['note_api'], ENT_QUOTES, 'UTF-8')?></div>
      </div>
    </div>
  </div>
</body>
</html>