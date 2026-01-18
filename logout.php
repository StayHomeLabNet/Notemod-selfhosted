<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_common.php';

header('Content-Type: text/html; charset=utf-8');

$ui = nm_ui_bootstrap();
$lang = $ui['lang'];
$theme = $ui['theme'];

$t = [
  'ja' => [
    'title' => 'ログアウト',
    'brand' => 'Notemod',
    'msg' => 'ログアウトしました',
    'sub' => 'ログイン画面へ移動します…',
    'go' => 'ログインへ',
    'lang_label' => 'Language',
    'theme_label' => 'Theme',
    'dark' => 'Dark',
    'light' => 'Light',
  ],
  'en' => [
    'title' => 'Logout',
    'brand' => 'Notemod',
    'msg' => 'You are logged out',
    'sub' => 'Redirecting to login…',
    'go' => 'Go to Login',
    'lang_label' => 'Language',
    'theme_label' => 'Theme',
    'dark' => 'Dark',
    'light' => 'Light',
  ],
];

$loginUrl = nm_ui_url('/login.php');

// ログアウト（lang/themeは保持）
unset($_SESSION['nm_logged_in'], $_SESSION['nm_user']);

header('Refresh: 1.2; url=' . $loginUrl);

$u = nm_ui_toggle_urls('/logout.php', $lang, $theme);
?>
<!doctype html>
<html lang="<?=htmlspecialchars($lang, ENT_QUOTES, 'UTF-8')?>" data-theme="<?=htmlspecialchars($theme, ENT_QUOTES, 'UTF-8')?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="refresh" content="1.2; url=<?=htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8')?>">
  <title><?=htmlspecialchars($t[$lang]['title'], ENT_QUOTES, 'UTF-8')?></title>
  <style>
    html[data-theme="dark"]{--bg0:#070b14;--bg1:#0b1222;--card:rgba(15,23,42,.78);--card2:rgba(2,6,23,.22);--line:rgba(148,163,184,.20);--text:#e5e7eb;--muted:#a3b0c2;--accent:#a2c1f4;--ok:#34d399;--shadow:0 18px 50px rgba(0,0,0,.55);}
    html[data-theme="light"]{--bg0:#f6f8fc;--bg1:#eef2ff;--card:rgba(255,255,255,.82);--card2:rgba(255,255,255,.70);--line:rgba(15,23,42,.12);--text:#0b1222;--muted:#4b5563;--accent:#2563eb;--ok:#10b981;--shadow:0 18px 50px rgba(15,23,42,.14);}
    :root{--r:18px}*{box-sizing:border-box}
    body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans JP",sans-serif;color:var(--text);background:radial-gradient(900px 600px at 20% 10%, color-mix(in srgb, var(--accent) 18%, transparent), transparent 60%),radial-gradient(800px 600px at 80% 30%, rgba(16,185,129,.10), transparent 55%),linear-gradient(180deg,var(--bg0),var(--bg1));padding:18px}
    .wrap{width:min(460px,100%)}.card{background:var(--card);border:1px solid var(--line);border-radius:var(--r);box-shadow:var(--shadow);overflow:hidden;backdrop-filter:blur(10px);position:relative}
    .head{padding:18px 18px 28px;background:linear-gradient(180deg,color-mix(in srgb,var(--accent) 10%,transparent),transparent);border-bottom:1px solid var(--line)}
    .brand{display:flex;align-items:center;gap:10px;font-weight:900;letter-spacing:.3px}.dot{width:10px;height:10px;border-radius:999px;background:var(--accent);box-shadow:0 0 0 4px color-mix(in srgb,var(--accent) 15%,transparent)}
    .body{padding:16px 18px 18px}
    .ok{border-radius:16px;padding:10px 12px;font-size:13px;border:1px solid color-mix(in srgb,var(--ok) 28%,transparent);background:color-mix(in srgb,var(--ok) 12%,transparent);color:color-mix(in srgb,var(--ok) 65%,var(--text));line-height:1.5}
    .muted{color:var(--muted);font-size:13px;margin-top:10px;line-height:1.5}
    a{color:var(--accent);text-decoration:none}a:hover{text-decoration:underline}
    .bar{height:6px;border-radius:999px;background:rgba(148,163,184,.18);overflow:hidden;margin-top:14px}
    .bar>div{height:100%;width:0%;background:color-mix(in srgb,var(--accent) 70%,transparent);animation:fill 1.2s linear forwards}
    @keyframes fill{to{width:100%}}

    /* Top-right toggles (smaller + right-aligned) */
    .toggles{
      position:absolute;
      top:8px;
      right:8px;
      display:flex;
      flex-direction:column;
      gap:6px;
      align-items:flex-end;
      user-select:none;
      transform: scale(.88);
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
      background:color-mix(in srgb,var(--card2) 60%,transparent);
      border:1px solid color-mix(in srgb,var(--line) 105%,transparent);
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
      background:color-mix(in srgb,var(--accent) 16%,transparent);
      color:var(--text);
      border-color:color-mix(in srgb,var(--accent) 26%,transparent);
    }
    @media (max-width: 600px){
      .toggles{ top:6px; right:6px; transform: scale(.82); }
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
      </div>

      <div class="body">
        <div class="ok"><?=htmlspecialchars($t[$lang]['msg'], ENT_QUOTES, 'UTF-8')?></div>
        <div class="muted">
          <?=htmlspecialchars($t[$lang]['sub'], ENT_QUOTES, 'UTF-8')?> <br>
          <a href="<?=htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go'], ENT_QUOTES, 'UTF-8')?></a>
        </div>
        <div class="bar"><div></div></div>
      </div>

    </div>
  </div>

  <script>
    setTimeout(function () {
      location.href = <?=json_encode($loginUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
    }, 1200);
  </script>
</body>
</html>