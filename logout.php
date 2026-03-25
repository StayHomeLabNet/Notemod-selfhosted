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
    .wrap{width:min(660px,92vw)}
    .card{background:var(--card);border:1px solid var(--line);border-radius:var(--r);box-shadow:var(--shadow);overflow:hidden;backdrop-filter:blur(10px)}
    .head{display:flex;justify-content:space-between;align-items:flex-start;gap:32px;padding:18px 22px;background:linear-gradient(180deg,color-mix(in srgb,var(--accent) 10%,transparent),transparent);border-bottom:1px solid var(--line)}
    .head-left{min-width:0;flex:1 1 auto}
    .head-right{flex:0 0 auto}
    .title{margin:0;font-size:clamp(22px,3vw,34px);line-height:1.06;font-weight:900;letter-spacing:-.02em;white-space:nowrap}
    .sub{margin:18px 0 0;color:var(--muted);font-size:clamp(14px,1.2vw,18px);line-height:1.35;white-space:normal}
    .toggles{display:flex;gap:16px;align-items:center;justify-content:flex-end;flex-wrap:wrap;user-select:none}
    .toggle-row{display:flex;gap:10px;align-items:center;white-space:nowrap}
    .toggle-row span{font-size:clamp(13px,1vw,16px);color:var(--muted)}
    .pill{display:inline-flex;gap:4px;background:color-mix(in srgb,var(--card2) 60%,transparent);border:1px solid color-mix(in srgb,var(--line) 105%,transparent);padding:4px;border-radius:999px}
    .pill a{min-width:56px;text-align:center;padding:8px 12px;border-radius:999px;color:var(--muted);text-decoration:none;border:1px solid transparent;font-size:clamp(13px,1vw,15px);font-weight:700;line-height:1.1}
    .pill a.active{background:color-mix(in srgb,var(--accent) 16%,transparent);color:var(--text);border-color:color-mix(in srgb,var(--accent) 26%,transparent)}
    .body{padding:16px 22px 20px}
    .ok{border-radius:16px;padding:14px 16px;font-size:clamp(14px,1.1vw,17px);font-weight:700;border:1px solid color-mix(in srgb,var(--ok) 28%,transparent);background:color-mix(in srgb,var(--ok) 12%,transparent);color:color-mix(in srgb,var(--ok) 65%,var(--text));line-height:1.45}
    .muted{color:var(--muted);font-size:clamp(14px,1.1vw,17px);margin-top:16px;line-height:1.5}
    a{color:var(--accent);text-decoration:none}a:hover{text-decoration:underline}
    .bar{height:10px;border-radius:999px;background:rgba(148,163,184,.18);overflow:hidden;margin-top:18px}
    .bar>div{height:100%;width:0%;background:color-mix(in srgb,var(--accent) 70%,transparent);animation:fill 1.2s linear forwards}
    @keyframes fill{to{width:100%}}
    @media (max-width: 980px){
      .wrap{width:min(660px,92vw)}
      .head{flex-direction:column;align-items:flex-start}
      .head-right{width:100%}
      .toggles{justify-content:flex-start}
      .title{white-space:normal}
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">

      <div class="head">
        <div class="head-left">
          <h1 class="title"><?=htmlspecialchars($t[$lang]['title'], ENT_QUOTES, 'UTF-8')?></h1>
          <p class="sub"><?=htmlspecialchars($t[$lang]['msg'], ENT_QUOTES, 'UTF-8')?></p>
        </div>

        <div class="head-right">
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
        </div>
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