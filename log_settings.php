<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_common.php';

/*
 * log_settings.php
 * - config/config.php の各設定をUIで編集
 * - UIは setup_auth.php と同じ配置（JP/EN, Dark/Light）
 * - 運用中はログイン必須（未ログインなら表示のみ/編集不可）
 *
 * 実装追加
 * (1) 保存後に config.php が壊れていないか検証（壊れてたら自動でロールバック）
 * (2) 未ログインで POST された場合はサーバ側で弾く（DevTools対策）
 * (3) IP_ALERT_IGNORE_IPS が空の場合はキーを書かない（更新しない＝スッキリ運用向け）
 *
 * 追加要望
 * - account.php と同様に、ログイン中ユーザー名を左上（ヘッダ）に表示（ログイン中のみ）
 */

// --------------------
// Paths
// --------------------
$configDir  = __DIR__ . '/config';
$configPath = $configDir . '/config.php';

header('Content-Type: text/html; charset=utf-8');

// Session (login check)
nm_auth_start_session();

// --------------------
// UI bootstrap (lang/theme)
// --------------------
$ui    = nm_ui_bootstrap();
$lang  = $ui['lang'];
$theme = $ui['theme'];

// --------------------
// Auth gate
// --------------------
$isLoggedIn = function_exists('nm_auth_is_logged_in') ? (bool)nm_auth_is_logged_in() : false;
$canEdit = $isLoggedIn;

// ログイン中ユーザー名（ログイン中のみ表示）
$loggedUser = '';
if ($isLoggedIn) {
    $loggedUser = (string)($_SESSION['nm_user'] ?? '');
    if ($loggedUser === '' && function_exists('nm_auth_load')) {
        try {
            $ac = nm_auth_load();
            $loggedUser = (string)($ac['USERNAME'] ?? '');
        } catch (Throwable $e) {
            $loggedUser = '';
        }
    }
}

// --------------------
// i18n (JP/EN only like other pages)
// --------------------
$t = [
  'ja' => [
    'title' => 'ログ設定',
    'desc'  => 'config/config.php のログ・通知関連の設定を変更します。',
    'logged_as' => 'ログイン中:',
    'btn'   => '保存',
    'ok'    => '保存しました',
    'err_write' => 'config/config.php の保存に失敗しました（権限を確認）',
    'err_read'  => 'config/config.php の読み込みに失敗しました',
    'err_broken'=> 'config/config.php が壊れた可能性があります（配列として読み込めません）',
    'err_login' => 'Login required.',

    'lang_label'  => 'Language',
    'theme_label' => 'Theme',
    'dark'  => 'Dark',
    'light' => 'Light',

    'section_general' => '一般',
    'timezone' => 'TIMEZONE（例：Asia/Tokyo / Australia/Sydney / Pacific/Auckland / America/New_York）',

    'section_logger' => 'ロガー',
    'logger_file_enabled' => 'LOGGER_FILE_ENABLED（Raw access log を出力）',
    'logger_notemod_enabled' => 'LOGGER_NOTEMOD_ENABLED（Notemod Logs カテゴリへ書く）',
    'logger_file_max_lines' => 'LOGGER_FILE_MAX_LINES（Raw log の最大行数 / 0=無制限）',
    'logger_notemod_max_lines' => 'LOGGER_NOTEMOD_MAX_LINES（Notemod Logs の最大行数 / 0=無制限）',

    'section_ip' => 'IPアクセス通知（メール）',
    'ip_alert_enabled' => 'IP_ALERT_ENABLED（通知を有効）',
    'ip_alert_to' => 'IP_ALERT_TO（宛先）',
    'ip_alert_from' => 'IP_ALERT_FROM（From：任意/初期値：notemod@localhost）',
    'ip_alert_subject' => 'IP_ALERT_SUBJECT（件名：任意/初期値：Notemod: First-time IP access）',
    'ip_alert_ignore_ips' => 'IP_ALERT_IGNORE_IPS（無視するIPアドレス、カンマ/改行区切り）',
    'ip_alert_ignore_bots' => 'IP_ALERT_IGNORE_BOTS（ボットっぽいUAを無視）',

    'go_back' => '戻る',
  ],
  'en' => [
    'title' => 'Log settings',
    'desc'  => 'Edit logging / notification settings in config/config.php.',
    'logged_as' => 'Logged in as:',
    'btn'   => 'Save',
    'ok'    => 'Saved',
    'err_write' => 'Failed to write config/config.php (permission?)',
    'err_read'  => 'Failed to read config/config.php',
    'err_broken'=> 'config/config.php may be broken (cannot be loaded as array).',
    'err_login' => 'Login required.',

    'lang_label'  => 'Language',
    'theme_label' => 'Theme',
    'dark'  => 'Dark',
    'light' => 'Light',

    'section_general' => 'General',
    'timezone' => 'TIMEZONE (e.g. Asia/Tokyo / Australia/Sydney / Pacific/Auckland / America/New_York)',

    'section_logger' => 'Logger',
    'logger_file_enabled' => 'LOGGER_FILE_ENABLED (write raw access logs)',
    'logger_notemod_enabled' => 'LOGGER_NOTEMOD_ENABLED (write to Notemod Logs category)',
    'logger_file_max_lines' => 'LOGGER_FILE_MAX_LINES (max lines for raw logs / 0=no limit)',
    'logger_notemod_max_lines' => 'LOGGER_NOTEMOD_MAX_LINES (max lines for Notemod Logs / 0=no limit)',

    'section_ip' => 'IP access alert (Email)',
    'ip_alert_enabled' => 'IP_ALERT_ENABLED (enable)',
    'ip_alert_to' => 'IP_ALERT_TO (to)',
    'ip_alert_from' => 'IP_ALERT_FROM (from: optional/default：notemod@localhost)',
    'ip_alert_subject' => 'IP_ALERT_SUBJECT (subject: optional/default：Notemod: First-time IP access)',
    'ip_alert_ignore_ips' => 'IP_ALERT_IGNORE_IPS (ignore IPs, comma/newline separated)',
    'ip_alert_ignore_bots' => 'IP_ALERT_IGNORE_BOTS (ignore bot-like user agents)',

    'go_back' => 'Back',
  ],
];

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
    if (!file_exists($path)) return [];
    nm_invalidate_php_cache($path);
    $arr = @require $path;
    return is_array($arr) ? $arr : [];
}

function nm_bool_from_post(string $key): bool {
    return isset($_POST[$key]) && $_POST[$key] === '1';
}

function nm_int_from_post(string $key, int $default = 0): int {
    $v = trim((string)($_POST[$key] ?? ''));
    if ($v === '') return $default;
    if (!preg_match('/^-?\d+$/', $v)) return $default;
    return (int)$v;
}

function nm_str_from_post(string $key, string $default = ''): string {
    $v = (string)($_POST[$key] ?? '');
    $v = trim($v);
    return $v === '' ? $default : $v;
}

function nm_parse_ignore_ips(string $raw): array {
    $raw = trim($raw);
    if ($raw === '') return [];
    $parts = preg_split('/[,\s]+/u', $raw) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $p = trim((string)$p);
        if ($p === '') continue;
        $out[] = $p;
    }
    $uniq = [];
    foreach ($out as $ip) {
        if (!in_array($ip, $uniq, true)) $uniq[] = $ip;
    }
    return $uniq;
}

function nm_php_value_literal(mixed $v): string {
    if (is_bool($v)) return $v ? 'true' : 'false';
    if (is_int($v)) return (string)$v;
    if (is_float($v)) return (string)$v;
    if (is_array($v)) return var_export($v, true);
    return var_export((string)$v, true);
}

/**
 * config/config.php を「既存のコメント/他設定を保持」しつつ更新する
 * - 既存キーがあれば置換（行末コメントも保持）
 * - 無ければ closing '];' の直前に追記
 */
function nm_update_config_php_preserve(string $configDir, string $configPath, array $updates): bool
{
    if (!is_dir($configDir)) {
        if (!@mkdir($configDir, 0755, true)) return false;
    }

    if (!file_exists($configPath)) {
        $tpl = "<?php\n"
             . "// config/config.php\n"
             . "// This config/config.php file was automatically generated by log_settings.php\n"
             . "return [\n"
             . "    'TIMEZONE' => 'Asia/Tokyo',\n"
             . "];\n";
        $ok = @file_put_contents($configPath, $tpl, LOCK_EX);
        if ($ok === false) return false;
        @chmod($configPath, 0644);
        nm_invalidate_php_cache($configPath);
    }

    $raw = (string)@file_get_contents($configPath);
    if ($raw === '') return false;

    foreach ($updates as $key => $value) {
        $literal = nm_php_value_literal($value);

        $pattern = '/(^\s*[\'"]' . preg_quote($key, '/') . '[\'"]\s*=>\s*)(.*?)(\s*,\s*)(\s*(?:(?:\/\/|#).*)?)$/mu';
        if (preg_match($pattern, $raw)) {
            $raw = preg_replace_callback(
                $pattern,
                function ($m) use ($literal) {
                    return $m[1] . $literal . $m[3] . $m[4];
                },
                $raw,
                1
            );
            continue;
        }

        $pos = strrpos($raw, '];');
        if ($pos === false) {
            $raw .= "\n\n// Appended by log_settings.php\n";
            $raw .= "return [\n    '" . $key . "' => " . $literal . ",\n];\n";
            continue;
        }

        $insert = "    '" . $key . "' => " . $literal . ",\n";
        $raw = substr($raw, 0, $pos) . $insert . substr($raw, $pos);
    }

    $ok = @file_put_contents($configPath, $raw, LOCK_EX);
    if ($ok === false) return false;

    @chmod($configPath, 0644);
    nm_invalidate_php_cache($configPath);
    return true;
}

/**
 * (1) 保存後の健全性チェック + ロールバック
 * - 保存前の生テキストを保持し、保存後 require で array を確認
 * - 壊れていたら元に戻す
 */
function nm_write_config_with_validation(string $configDir, string $configPath, array $updates, string &$errMsg): bool
{
    $errMsg = '';

    $beforeRaw = file_exists($configPath) ? (string)@file_get_contents($configPath) : '';

    if (!nm_update_config_php_preserve($configDir, $configPath, $updates)) {
        $errMsg = 'write_failed';
        return false;
    }

    nm_invalidate_php_cache($configPath);
    $test = @require $configPath;
    if (!is_array($test)) {
        if ($beforeRaw !== '') {
            @file_put_contents($configPath, $beforeRaw, LOCK_EX);
            @chmod($configPath, 0644);
            nm_invalidate_php_cache($configPath);
        }
        $errMsg = 'broken';
        return false;
    }

    return true;
}

// --------------------
// Load current config
// --------------------
$msg = '';
$err = '';

$cfg = [];
try {
    $cfg = nm_read_php_config_array($configPath);
} catch (Throwable $e) {
    $cfg = [];
    $err = $t[$lang]['err_read'];
}

// Prefill
$pref = [
    'TIMEZONE' => (string)($cfg['TIMEZONE'] ?? 'Asia/Tokyo'),

    'LOGGER_FILE_ENABLED' => (bool)($cfg['LOGGER_FILE_ENABLED'] ?? true),
    'LOGGER_NOTEMOD_ENABLED' => (bool)($cfg['LOGGER_NOTEMOD_ENABLED'] ?? true),

    'IP_ALERT_ENABLED' => (bool)($cfg['IP_ALERT_ENABLED'] ?? true),
    'IP_ALERT_TO' => (string)($cfg['IP_ALERT_TO'] ?? ''),
    'IP_ALERT_FROM' => (string)($cfg['IP_ALERT_FROM'] ?? ''),
    'IP_ALERT_SUBJECT' => (string)($cfg['IP_ALERT_SUBJECT'] ?? ''),
    'IP_ALERT_IGNORE_BOTS' => (bool)($cfg['IP_ALERT_IGNORE_BOTS'] ?? true),

    'IP_ALERT_IGNORE_IPS' => is_array($cfg['IP_ALERT_IGNORE_IPS'] ?? null) ? $cfg['IP_ALERT_IGNORE_IPS'] : [],

    'LOGGER_FILE_MAX_LINES' => (int)($cfg['LOGGER_FILE_MAX_LINES'] ?? 2000),
    'LOGGER_NOTEMOD_MAX_LINES' => (int)($cfg['LOGGER_NOTEMOD_MAX_LINES'] ?? 50),
];

$prefIgnoreIpsText = '';
if (!empty($pref['IP_ALERT_IGNORE_IPS']) && is_array($pref['IP_ALERT_IGNORE_IPS'])) {
    $prefIgnoreIpsText = implode(", ", array_map('strval', $pref['IP_ALERT_IGNORE_IPS']));
}

// --------------------
// Handle POST
// --------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    // (2) 未ログイン POST をサーバ側で弾く（DevTools対策）
    if (!$canEdit) {
        $err = $t[$lang]['err_login'];
    } else {
        $timezone = nm_str_from_post('TIMEZONE', $pref['TIMEZONE']);
        $ignoreIpsParsed = nm_parse_ignore_ips((string)($_POST['IP_ALERT_IGNORE_IPS'] ?? ''));

        $updates = [
            'TIMEZONE' => $timezone,

            'LOGGER_FILE_ENABLED' => nm_bool_from_post('LOGGER_FILE_ENABLED'),
            'LOGGER_NOTEMOD_ENABLED' => nm_bool_from_post('LOGGER_NOTEMOD_ENABLED'),

            'IP_ALERT_ENABLED' => nm_bool_from_post('IP_ALERT_ENABLED'),
            'IP_ALERT_TO' => (string)($_POST['IP_ALERT_TO'] ?? ''),
            'IP_ALERT_FROM' => (string)($_POST['IP_ALERT_FROM'] ?? ''),
            'IP_ALERT_SUBJECT' => (string)($_POST['IP_ALERT_SUBJECT'] ?? ''),

            'IP_ALERT_IGNORE_BOTS' => nm_bool_from_post('IP_ALERT_IGNORE_BOTS'),

            'LOGGER_FILE_MAX_LINES' => nm_int_from_post('LOGGER_FILE_MAX_LINES', $pref['LOGGER_FILE_MAX_LINES']),
            'LOGGER_NOTEMOD_MAX_LINES' => nm_int_from_post('LOGGER_NOTEMOD_MAX_LINES', $pref['LOGGER_NOTEMOD_MAX_LINES']),
        ];

        // (3) 空ならキーを書かない（更新しない）
        if (count($ignoreIpsParsed) > 0) {
            $updates['IP_ALERT_IGNORE_IPS'] = $ignoreIpsParsed;
        }

        // (1) 保存後検証 + 壊れてたらロールバック
        $saveErrKey = '';
        if (!nm_write_config_with_validation($configDir, $configPath, $updates, $saveErrKey)) {
            $err = ($saveErrKey === 'broken') ? $t[$lang]['err_broken'] : $t[$lang]['err_write'];
        } else {
            $msg = $t[$lang]['ok'];

            // reload to reflect saved values
            $cfg = nm_read_php_config_array($configPath);

            $pref['TIMEZONE'] = (string)($cfg['TIMEZONE'] ?? $updates['TIMEZONE']);

            $pref['LOGGER_FILE_ENABLED'] = (bool)($cfg['LOGGER_FILE_ENABLED'] ?? $updates['LOGGER_FILE_ENABLED']);
            $pref['LOGGER_NOTEMOD_ENABLED'] = (bool)($cfg['LOGGER_NOTEMOD_ENABLED'] ?? $updates['LOGGER_NOTEMOD_ENABLED']);

            $pref['IP_ALERT_ENABLED'] = (bool)($cfg['IP_ALERT_ENABLED'] ?? $updates['IP_ALERT_ENABLED']);
            $pref['IP_ALERT_TO'] = (string)($cfg['IP_ALERT_TO'] ?? $updates['IP_ALERT_TO']);
            $pref['IP_ALERT_FROM'] = (string)($cfg['IP_ALERT_FROM'] ?? $updates['IP_ALERT_FROM']);
            $pref['IP_ALERT_SUBJECT'] = (string)($cfg['IP_ALERT_SUBJECT'] ?? $updates['IP_ALERT_SUBJECT']);
            $pref['IP_ALERT_IGNORE_BOTS'] = (bool)($cfg['IP_ALERT_IGNORE_BOTS'] ?? $updates['IP_ALERT_IGNORE_BOTS']);

            $ips = $cfg['IP_ALERT_IGNORE_IPS'] ?? $pref['IP_ALERT_IGNORE_IPS'];
            $pref['IP_ALERT_IGNORE_IPS'] = is_array($ips) ? $ips : [];
            $prefIgnoreIpsText = implode(", ", array_map('strval', $pref['IP_ALERT_IGNORE_IPS']));

            $pref['LOGGER_FILE_MAX_LINES'] = (int)($cfg['LOGGER_FILE_MAX_LINES'] ?? $updates['LOGGER_FILE_MAX_LINES']);
            $pref['LOGGER_NOTEMOD_MAX_LINES'] = (int)($cfg['LOGGER_NOTEMOD_MAX_LINES'] ?? $updates['LOGGER_NOTEMOD_MAX_LINES']);
        }
    }
}

// --------------------
// Links (toggles + back)
// --------------------
$u = nm_ui_toggle_urls('/log_settings.php', $lang, $theme);
$backUrl = nm_ui_url('/');
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
      padding-right: 120px; /* account.php と同様：トグルとかぶりにくく */
      padding-bottom: 10px;
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
    label{ display:block; font-size:12px; color:var(--muted); margin:10px 0 6px; }
    input, textarea{
      width:100%;
      padding:12px 12px;
      border-radius:14px;
      border:1px solid color-mix(in srgb, var(--line) 140%, transparent);
      background: color-mix(in srgb, var(--card2) 85%, transparent);
      color:var(--text);
      outline:none;
    }
    textarea{ min-height: 64px; resize: vertical; }
    input:focus, textarea:focus{
      border-color: color-mix(in srgb, var(--accent) 70%, transparent);
      box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 14%, transparent);
    }
    input[disabled], textarea[disabled]{ opacity:.75; cursor:not-allowed; }
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
      white-space: pre-line;
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
      z-index: 5;
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
      .head{ padding-right: 18px; }
    }

    .row-links{ display:flex; gap:12px; flex-wrap:wrap; }
    .row-links a{ font-size:13px; color:var(--accent); }

    .check{
      display:flex;
      align-items:center;
      gap:10px;
      padding:10px 12px;
      border-radius:14px;
      border:1px solid color-mix(in srgb, var(--line) 120%, transparent);
      background: color-mix(in srgb, var(--card2) 70%, transparent);
    }
    .check input[type="checkbox"]{
      width:18px; height:18px;
      accent-color: var(--accent);
    }
    .check span{
      font-size:13px;
      color:var(--text);
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

          <div class="meta">
            <?=htmlspecialchars($t[$lang]['desc'], ENT_QUOTES, 'UTF-8')?>
            <?php if ($isLoggedIn && $loggedUser !== ''): ?>
              <br><?=htmlspecialchars($t[$lang]['logged_as'], ENT_QUOTES, 'UTF-8')?> <b><?=htmlspecialchars($loggedUser, ENT_QUOTES, 'UTF-8')?></b>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="body">
        <?php if ($msg): ?><div class="notice ok"><?=htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
        <?php if ($err): ?><div class="notice bad"><?=htmlspecialchars($err, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>

        <?php if (!$canEdit): ?>
          <div class="notice bad"><?=htmlspecialchars($t[$lang]['err_login'], ENT_QUOTES, 'UTF-8')?></div>
        <?php endif; ?>

        <form method="post">
          <div class="box">
            <h3><?=htmlspecialchars($t[$lang]['section_general'], ENT_QUOTES, 'UTF-8')?></h3>

            <label><?=htmlspecialchars($t[$lang]['timezone'], ENT_QUOTES, 'UTF-8')?></label>
            <input name="TIMEZONE"
                   value="<?=htmlspecialchars($pref['TIMEZONE'], ENT_QUOTES, 'UTF-8')?>"
                   <?= $canEdit ? '' : 'disabled' ?>>
          </div>

          <div class="box">
            <h3><?=htmlspecialchars($t[$lang]['section_logger'], ENT_QUOTES, 'UTF-8')?></h3>

            <label class="check">
              <input type="checkbox" name="LOGGER_FILE_ENABLED" value="1"
                     <?= $pref['LOGGER_FILE_ENABLED'] ? 'checked' : '' ?>
                     <?= $canEdit ? '' : 'disabled' ?>>
              <span><?=htmlspecialchars($t[$lang]['logger_file_enabled'], ENT_QUOTES, 'UTF-8')?></span>
            </label>

            <label class="check">
              <input type="checkbox" name="LOGGER_NOTEMOD_ENABLED" value="1"
                     <?= $pref['LOGGER_NOTEMOD_ENABLED'] ? 'checked' : '' ?>
                     <?= $canEdit ? '' : 'disabled' ?>>
              <span><?=htmlspecialchars($t[$lang]['logger_notemod_enabled'], ENT_QUOTES, 'UTF-8')?></span>
            </label>

            <label><?=htmlspecialchars($t[$lang]['logger_file_max_lines'], ENT_QUOTES, 'UTF-8')?></label>
            <input type="number" min="0" step="1" name="LOGGER_FILE_MAX_LINES"
                   value="<?=htmlspecialchars((string)$pref['LOGGER_FILE_MAX_LINES'], ENT_QUOTES, 'UTF-8')?>"
                   <?= $canEdit ? '' : 'disabled' ?>>

            <label><?=htmlspecialchars($t[$lang]['logger_notemod_max_lines'], ENT_QUOTES, 'UTF-8')?></label>
            <input type="number" min="0" step="1" name="LOGGER_NOTEMOD_MAX_LINES"
                   value="<?=htmlspecialchars((string)$pref['LOGGER_NOTEMOD_MAX_LINES'], ENT_QUOTES, 'UTF-8')?>"
                   <?= $canEdit ? '' : 'disabled' ?>>
          </div>

          <div class="box">
            <h3><?=htmlspecialchars($t[$lang]['section_ip'], ENT_QUOTES, 'UTF-8')?></h3>

            <label class="check">
              <input type="checkbox" name="IP_ALERT_ENABLED" value="1"
                     <?= $pref['IP_ALERT_ENABLED'] ? 'checked' : '' ?>
                     <?= $canEdit ? '' : 'disabled' ?>>
              <span><?=htmlspecialchars($t[$lang]['ip_alert_enabled'], ENT_QUOTES, 'UTF-8')?></span>
            </label>

            <label><?=htmlspecialchars($t[$lang]['ip_alert_to'], ENT_QUOTES, 'UTF-8')?></label>
            <input name="IP_ALERT_TO"
                   value="<?=htmlspecialchars($pref['IP_ALERT_TO'], ENT_QUOTES, 'UTF-8')?>"
                   <?= $canEdit ? '' : 'disabled' ?>>

            <label><?=htmlspecialchars($t[$lang]['ip_alert_from'], ENT_QUOTES, 'UTF-8')?></label>
            <input name="IP_ALERT_FROM"
                   value="<?=htmlspecialchars($pref['IP_ALERT_FROM'], ENT_QUOTES, 'UTF-8')?>"
                   <?= $canEdit ? '' : 'disabled' ?>>

            <label><?=htmlspecialchars($t[$lang]['ip_alert_subject'], ENT_QUOTES, 'UTF-8')?></label>
            <input name="IP_ALERT_SUBJECT"
                   value="<?=htmlspecialchars($pref['IP_ALERT_SUBJECT'], ENT_QUOTES, 'UTF-8')?>"
                   <?= $canEdit ? '' : 'disabled' ?>>

            <label><?=htmlspecialchars($t[$lang]['ip_alert_ignore_ips'], ENT_QUOTES, 'UTF-8')?></label>
            <textarea name="IP_ALERT_IGNORE_IPS" <?= $canEdit ? '' : 'disabled' ?>><?=htmlspecialchars($prefIgnoreIpsText, ENT_QUOTES, 'UTF-8')?></textarea>

            <label class="check">
              <input type="checkbox" name="IP_ALERT_IGNORE_BOTS" value="1"
                     <?= $pref['IP_ALERT_IGNORE_BOTS'] ? 'checked' : '' ?>
                     <?= $canEdit ? '' : 'disabled' ?>>
              <span><?=htmlspecialchars($t[$lang]['ip_alert_ignore_bots'], ENT_QUOTES, 'UTF-8')?></span>
            </label>

            <button class="btn" type="submit" <?= $canEdit ? '' : 'disabled' ?>><?=htmlspecialchars($t[$lang]['btn'], ENT_QUOTES, 'UTF-8')?></button>
          </div>
        </form>

        <div class="row-links">
          <a href="<?=htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_back'], ENT_QUOTES, 'UTF-8')?></a>
        </div>

      </div>
    </div>
  </div>
</body>
</html>