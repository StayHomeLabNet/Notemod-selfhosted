<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_common.php';

header('Content-Type: text/html; charset=utf-8');

$ui = nm_ui_bootstrap();
$lang = $ui['lang'];
$theme = $ui['theme'];

$t = [
  'ja' => [
    'title' => 'Notemod-selfhosted ログイン',
    'brand' => 'Notemod-selfhosted',
    'subtitle' => 'ログインしてメモを開きます',
    'username' => 'ユーザー名',
    'password' => 'パスワード',
    'login' => 'ログイン',
    'account' => 'アカウント',
    'need_setup' => '認証が未設定です。セットアップ画面へ移動します…',
    'login_failed' => 'ユーザー名またはパスワードが違います',
    'lang_label' => 'Language',
    'theme_label' => 'Theme',
    'dark' => 'Dark',
    'light' => 'Light',
    'go_setup' => '今すぐセットアップを開く',
  ],
  'en' => [
    'title' => 'Notemod-selfhosted Login',
    'brand' => 'Notemod-selfhosted',
    'subtitle' => 'Log in to open your notes',
    'username' => 'Username',
    'password' => 'Password',
    'login' => 'Login',
    'account' => 'Account',
    'need_setup' => 'Auth not configured. Redirecting to setup…',
    'login_failed' => 'Username or password is incorrect',
    'lang_label' => 'Language',
    'theme_label' => 'Theme',
    'dark' => 'Dark',
    'light' => 'Light',
    'go_setup' => 'Open setup now',
  ],
];

// 未設定なら 3秒後に setup_auth.php へ
if (!nm_auth_is_ready()) {
    $setupUrl = nm_ui_url('/setup_auth.php');
    header('Refresh: 3; url=' . $setupUrl);
    ?>
<!doctype html>
<html lang="<?=htmlspecialchars($lang, ENT_QUOTES, 'UTF-8')?>" data-theme="<?=htmlspecialchars($theme, ENT_QUOTES, 'UTF-8')?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="refresh" content="3; url=<?=htmlspecialchars($setupUrl, ENT_QUOTES, 'UTF-8')?>">
  <title>Setup required</title>
  <style>
    html[data-theme="dark"]{--bg0:#070b14;--bg1:#0b1222;--card:rgba(15,23,42,.78);--card2:rgba(2,6,23,.22);--line:rgba(148,163,184,.20);--text:#e5e7eb;--muted:#a3b0c2;--accent:#a2c1f4;--shadow:0 18px 50px rgba(0,0,0,.55);}
    html[data-theme="light"]{--bg0:#f6f8fc;--bg1:#eef2ff;--card:rgba(255,255,255,.82);--card2:rgba(255,255,255,.70);--line:rgba(15,23,42,.12);--text:#0b1222;--muted:#4b5563;--accent:#2563eb;--shadow:0 18px 50px rgba(15,23,42,.14);}
    :root{--r:18px}*{box-sizing:border-box}
    body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans JP",sans-serif;color:var(--text);background:radial-gradient(900px 600px at 20% 10%, color-mix(in srgb, var(--accent) 18%, transparent), transparent 60%),radial-gradient(800px 600px at 80% 30%, rgba(16,185,129,.10), transparent 55%),linear-gradient(180deg,var(--bg0),var(--bg1));padding:18px}
    .card{width:min(520px,100%);background:var(--card);border:1px solid var(--line);border-radius:var(--r);box-shadow:var(--shadow);backdrop-filter:blur(10px);padding:18px}
    .muted{color:var(--muted);font-size:13px;line-height:1.5}
    a{color:var(--accent);text-decoration:none}a:hover{text-decoration:underline}
    .bar{height:6px;border-radius:999px;background:rgba(148,163,184,.18);overflow:hidden;margin-top:14px}
    .bar>div{height:100%;width:0%;background:color-mix(in srgb,var(--accent) 70%,transparent);animation:fill 3s linear forwards}
    @keyframes fill{to{width:100%}}
  </style>
</head>
<body>
  <div class="card">
    <div style="font-weight:900;letter-spacing:.2px;">Setup required</div>
    <p class="muted" style="margin:8px 0 0;">
      <?=htmlspecialchars($t[$lang]['need_setup'], ENT_QUOTES, 'UTF-8')?><br>
      <a href="<?=htmlspecialchars($setupUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_setup'], ENT_QUOTES, 'UTF-8')?></a>
    </p>
    <div class="bar"><div></div></div>
  </div>
  <script>
    setTimeout(function(){ location.href = <?=json_encode($setupUrl, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>; }, 3000);
  </script>
</body>
</html>
    <?php
    exit;
}

$cfg = nm_auth_load();
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    nm_auth_start_session();
    $u = (string)($_POST['username'] ?? '');
    $p = (string)($_POST['password'] ?? '');

    $okUser = hash_equals((string)($cfg['USERNAME'] ?? ''), $u);
    $okPass = password_verify($p, (string)($cfg['PASSWORD_HASH'] ?? ''));

    if ($okUser && $okPass) {
        $_SESSION['nm_logged_in'] = true;
        $_SESSION['nm_user'] = (string)($cfg['USERNAME'] ?? $u);
        header('Location: ' . nm_auth_base_url() . '/');
        exit;
    }
    $error = $t[$lang]['login_failed'];
}

$u = nm_ui_toggle_urls('/login.php', $lang, $theme);
$accountUrl = nm_ui_url('/account.php');
?>
<!doctype html>
<html lang="<?=htmlspecialchars($lang, ENT_QUOTES, 'UTF-8')?>" data-theme="<?=htmlspecialchars($theme, ENT_QUOTES, 'UTF-8')?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?=htmlspecialchars($t[$lang]['title'], ENT_QUOTES, 'UTF-8')?></title>
  <style>
    html[data-theme="dark"]{--bg0:#070b14;--bg1:#0b1222;--card:rgba(15,23,42,.78);--card2:rgba(2,6,23,.22);--line:rgba(148,163,184,.20);--text:#e5e7eb;--muted:#a3b0c2;--accent:#a2c1f4;--danger:#fb7185;--shadow:0 18px 50px rgba(0,0,0,.55);}
    html[data-theme="light"]{--bg0:#f6f8fc;--bg1:#eef2ff;--card:rgba(255,255,255,.82);--card2:rgba(255,255,255,.70);--line:rgba(15,23,42,.12);--text:#0b1222;--muted:#4b5563;--accent:#2563eb;--danger:#e11d48;--shadow:0 18px 50px rgba(15,23,42,.14);}
    :root{--r:18px}*{box-sizing:border-box}
    body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans JP",sans-serif;color:var(--text);background:radial-gradient(900px 600px at 20% 10%, color-mix(in srgb, var(--accent) 18%, transparent), transparent 60%),radial-gradient(800px 600px at 80% 30%, rgba(16,185,129,.10), transparent 55%),linear-gradient(180deg,var(--bg0),var(--bg1));padding:18px}

    .wrap{width:min(420px,100%)}
    .card{
      background:var(--card);
      border:1px solid var(--line);
      border-radius:var(--r);
      box-shadow:var(--shadow);
      overflow:hidden;
      backdrop-filter:blur(10px);
      position:relative
    }
    .head{
      padding:18px 18px 14px;
      background:linear-gradient(180deg,color-mix(in srgb,var(--accent) 10%,transparent),transparent);
      border-bottom:1px solid var(--line)
    }
    .brand{display:flex;align-items:center;gap:10px;font-weight:800;letter-spacing:.3px}
    .dot{width:10px;height:10px;border-radius:999px;background:var(--accent);box-shadow:0 0 0 4px color-mix(in srgb,var(--accent) 15%,transparent)}
    .sub{margin:8px 0 0;color:var(--muted);font-size:13px;line-height:1.45}
    .body{padding:16px 18px 18px}
    .err{background:color-mix(in srgb,var(--danger) 12%,transparent);border:1px solid color-mix(in srgb,var(--danger) 25%,transparent);color:color-mix(in srgb,var(--danger) 70%,var(--text));padding:10px 12px;border-radius:14px;font-size:13px;margin-bottom:12px}
    label{display:block;font-size:12px;color:var(--muted);margin:10px 0 6px}
    input{width:100%;padding:12px 12px;border-radius:14px;border:1px solid color-mix(in srgb,var(--line) 140%,transparent);background:var(--card2);color:var(--text);outline:none}
    input:focus{border-color:color-mix(in srgb,var(--accent) 70%,transparent);box-shadow:0 0 0 4px color-mix(in srgb,var(--accent) 14%,transparent)}
    .btn{width:100%;border:none;border-radius:14px;padding:12px 14px;font-weight:800;cursor:pointer;background:linear-gradient(135deg,color-mix(in srgb,var(--accent) 100%,#fff),color-mix(in srgb,var(--accent) 70%,#6366f1));color:color-mix(in srgb,var(--text) 0%,#061021);box-shadow:0 10px 25px color-mix(in srgb,var(--accent) 20%,transparent);transition:transform .12s ease,filter .12s ease;margin-top:12px}
    .btn:hover{transform:translateY(-1px);filter:brightness(1.03)}
    .btn:active{transform:translateY(0);filter:brightness(.98)}
    .foot{padding:12px 18px 16px;border-top:1px solid var(--line);background:color-mix(in srgb,var(--card2) 70%,transparent);color:var(--muted);font-size:12px;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap}
    a{color:var(--accent);text-decoration:none}
    a:hover{text-decoration:underline}

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
      transform: scale(.92);
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
      .toggles{
        top:6px;
        right:6px;
        transform: scale(.86);
      }
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
        <div class="brand"><span class="dot"></span> <?=htmlspecialchars($t[$lang]['brand'], ENT_QUOTES, 'UTF-8')?></div>
        <p class="sub"><?=htmlspecialchars($t[$lang]['subtitle'], ENT_QUOTES, 'UTF-8')?></p>
      </div>

      <div class="body">
        <?php if ($error): ?><div class="err"><?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>

        <form method="post" autocomplete="on">
          <label><?=htmlspecialchars($t[$lang]['username'], ENT_QUOTES, 'UTF-8')?></label>
          <input name="username" required autocomplete="username">

          <label><?=htmlspecialchars($t[$lang]['password'], ENT_QUOTES, 'UTF-8')?></label>
          <input name="password" type="password" required autocomplete="current-password">

          <button class="btn" type="submit"><?=htmlspecialchars($t[$lang]['login'], ENT_QUOTES, 'UTF-8')?></button>
        </form>
      </div>

      <div class="foot">
        <span>Notemod-selfhosted Screen Login</span>
        <span><a href="<?=htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['account'], ENT_QUOTES, 'UTF-8')?></a></span>
      </div>
    </div>
  </div>
</body>
</html>