<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_common.php';

/*
 * setup_auth.php
 * - auth.php が無い初回: 誰でもアクセス可（初期ユーザー作成 + SECRET追記 + APIトークン設定OK）
 * - auth.php がある運用中: ページ自体は見れるが、未ログイン時は APIトークンは伏字＆編集不可
 * - 運用中でログイン済み: APIトークン編集可
 *
 * 追加要望:
 * - account.php と同じように、ログイン中のユーザー名を表示（他の機能はそのまま）
 */

// --------------------
// Paths
// --------------------
$configDir      = __DIR__ . '/config';
$authPath       = $configDir . '/auth.php';
$configPath     = $configDir . '/config.php';
$configApiPath  = $configDir . '/config.api.php';

header('Content-Type: text/html; charset=utf-8');

// セッション（ログイン判定のため）
nm_auth_start_session();

// --------------------
// UI bootstrap (lang/theme)
// --------------------
$ui    = nm_ui_bootstrap();
$lang  = $ui['lang'];
$theme = $ui['theme'];

// --------------------
// i18n
// --------------------
$t = [
  'ja' => [
    'title' => '初期セットアップ',
    'desc'  => 'Notemod-selfhosted のログイン用ユーザー/パスワードを作成します。',
    'desc2' => 'さらに、API用トークンと config/config.php の SECRET をセットできます。',

    // ★追加（ログイン中ユーザー表示）
    'logged_as' => 'ログイン中:',

    'username' => 'ユーザー名',
    'password' => 'パスワード（10文字以上）',
    'password2'=> 'パスワード（再入力）',
    'btn' => '保存',
    'ok' => '保存しました',
    'err_write_auth' => 'config/auth.php の保存に失敗しました（権限を確認）',
    'err_pw_mismatch' => 'パスワードが一致しません',
    'err_pw_short' => 'パスワードは10文字以上にしてください',
    'err_user_empty' => 'ユーザー名が空です',
    'exists' => 'auth.php は既に設定済みです。必要ならアカウント画面から変更してください。',
    'go_back' => '戻る',
    'go_login' => 'ログインへ',
    'go_account' => 'アカウント設定へ',

    'lang_label' => 'Language',
    'theme_label' => 'Theme',
    'dark' => 'Dark',
    'light' => 'Light',

    'secret_section' => 'サーバー同期用 SECRET（config/config.php）',
    'secret_note' => "config/config.php が存在しない場合は、自動で作成します\nSECRET が config/config.php に無い場合、自動で追記します（既にあれば変更しません）",
    'secret_status_ok' => 'SECRET は設定済みです',
    'secret_status_added' => 'SECRET を config/config.php に追記しました',
    'secret_write_failed' => 'config/config.php の更新に失敗しました（権限を確認）',

    'api_section' => 'API トークン（config/config.api.php）',
    'expected_token' => 'EXPECTED_TOKEN（通常アクセス用）',
    'admin_token' => 'ADMIN_TOKEN（管理用/cleanup用）',
    'api_note' => "config/config.api.php が存在しない場合は、自動で作成します\n空で保存すると、その項目は更新しません（既存値は維持）",
    'api_saved' => 'config/config.api.php を更新しました',
    'api_save_failed' => 'config/config.api.php の保存に失敗しました（権限を確認）',
    'api_locked' => '※ トークンの閲覧/変更はログイン後のみ可能です（初回セットアップ時を除く）',

    'auth_section' => '画面ログイン（config/auth.php）',
  ],
  'en' => [
    'title' => 'Initial Setup',
    'desc'  => 'Create username/password for Notemod-selfhosted login.',
    'desc2' => 'You can also set API tokens and SECRET in config/config.php.',

    // ★追加（ログイン中ユーザー表示）
    'logged_as' => 'Logged in as:',

    'username' => 'Username',
    'password' => 'Password (min 10 chars)',
    'password2'=> 'Repeat password',
    'btn' => 'Save',
    'ok' => 'Saved',
    'err_write_auth' => 'Failed to write config/auth.php (permission?)',
    'err_pw_mismatch' => 'Passwords do not match',
    'err_pw_short' => 'Password must be at least 10 characters',
    'err_user_empty' => 'Username is empty',
    'exists' => 'auth.php is already configured. Use Account page to change it.',
    'go_back' => 'Back',
    'go_login' => 'Go to Login',
    'go_account' => 'Go to Account',

    'lang_label' => 'Language',
    'theme_label' => 'Theme',
    'dark' => 'Dark',
    'light' => 'Light',

    'secret_section' => 'SECRET for sync (config/config.php)',
    'secret_note' => "If config/config.php does not exist, it will be created automatically.\nIf SECRET is missing in config/config.php, it will be appended automatically (won’t overwrite existing).",
    'secret_status_ok' => 'SECRET is already set',
    'secret_status_added' => 'SECRET was appended to config/config.php',
    'secret_write_failed' => 'Failed to update config/config.php (permission?)',

    'api_section' => 'API tokens (config/config.api.php)',
    'expected_token' => 'EXPECTED_TOKEN',
    'admin_token' => 'ADMIN_TOKEN (admin/cleanup)',
    'api_note' => "If config/config.api.php does not exist, it will be created automatically.\nIf left empty, it won’t update that field (existing values kept).",
    'api_saved' => 'Updated config/config.api.php',
    'api_save_failed' => 'Failed to write config/config.api.php (permission?)',
    'api_locked' => 'Token view/edit is available only after login (except initial setup).',

    'auth_section' => 'Screen login (config/auth.php)',
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
function nm_random_secret(int $bytes = 32): string {
    return bin2hex(random_bytes($bytes));
}
function nm_mask_token(string $s): string {
    $s = (string)$s;
    if ($s === '') return '';
    $len = strlen($s);
    if ($len <= 4) return str_repeat('•', max(4, $len));
    $tail = substr($s, -4);
    return str_repeat('•', 8) . $tail;
}
function nm_reload_api_tokens(string $configApiPath): array {
    $cfgApi = nm_read_php_config_array($configApiPath);
    return [
        'EXPECTED_TOKEN' => (string)($cfgApi['EXPECTED_TOKEN'] ?? ''),
        'ADMIN_TOKEN'    => (string)($cfgApi['ADMIN_TOKEN'] ?? ''),
    ];
}

/**
 * config/config.php に SECRET が無ければ追記（既存SECRETは上書きしない）
 */
function nm_ensure_secret_in_config(string $configDir, string $configPath, bool &$didAdd): bool
{
    $didAdd = false;

    if (!is_dir($configDir)) {
        if (!@mkdir($configDir, 0755, true)) return false;
    }

    if (!file_exists($configPath)) {
        $secret = nm_random_secret(32);
        $content =
            "<?php\n"
          . "// config/config.php\n"
          . "// This config/config.php file was automatically generated by setup_auth.php\n"
          . "return [\n"
          . "    // PHP timezone setting (examples)\n"
          . "    // Japan : Asia/Tokyo\n"
          . "    // NZ    : Pacific/Auckland\n"
          . "    // AU    : Australia/Sydney\n"
          . "    // US    : America/Los_Angeles / America/New_York\n"
          . "    // Canada: America/Toronto / America/Vancouver\n"
          . "    // Turkey: Europe/Istanbul\n"
          . "    'TIMEZONE' => 'Asia/Tokyo',\n\n"
          . "    // Set to true to enable debug logging\n"
          . "    'DEBUG' => false,\n\n"
          . "    // Enable/disable logger\n"
          . "    // Raw access logs (/logs/access-YYYY-MM.log)\n"
          . "    'LOGGER_FILE_ENABLED' => true,\n\n"
          . "    // Notemod Logs category (monthly note: access-YYYY-MM)\n"
          . "    'LOGGER_NOTEMOD_ENABLED' => true,\n\n"
          . "    // (Optional) Change the logs directory name\n"
          . "    // 'LOGGER_LOGS_DIRNAME' => 'logs',\n\n"
          . "    // Optional: customize Notemod initial snapshot\n"
          . "    // (Must be stored as a JSON string)\n"
          . "    // 'INITIAL_SNAPSHOT' => '{\"categories\":null,\"hasSelectedLanguage\":null,\"notes\":null,\"selectedLanguage\":null}',\n\n"
          . "    // Initial IP Access Notification (Email)\n"
          . "    'IP_ALERT_ENABLED' => true,                      // Enable: true\n"
          . "    'IP_ALERT_TO'      => 'YOUR_EMAIL', // Send To:\n"
          . "    'IP_ALERT_FROM' => 'notemod@localhost',    // Recipient: Optional (if configurable)\n"
          . "    'IP_ALERT_SUBJECT' => 'Notemod: First-time IP access', // Email subject\n"
          . "    'IP_ALERT_IGNORE_BOTS' => true,                  // Ignore user agents that are likely bots\n"
          . "    'IP_ALERT_IGNORE_IPS' => [''],                   // Exclude your own fixed IP addresses, etc.\n"
          . "    // 'IP_ALERT_STORE' => __DIR__ . '/../notemod-data/_known_ips.json', // Optional\n\n"
          . "    // 0 = No limit (do nothing)\n"
          . "    // Example: Monthly raw logs (access-YYYY-MM.log) — up to 2,000 lines\n"
          . "    // Notemod Logs — Notes limited to 50 lines\n"
          . "    'LOGGER_FILE_MAX_LINES' => 500,\n"
          . "    'LOGGER_NOTEMOD_MAX_LINES' => 50,\n\n"
          . "    // Application secret used as a private value\n"
          . "    // (signing, encryption, fixed keys, etc.)\n"
          . "    // If not specified, setup_auth.php will append it automatically\n"
          . "    'SECRET' => " . var_export($secret, true) . ",\n"
          . "];\n";
        $ok = @file_put_contents($configPath, $content, LOCK_EX);
        if ($ok === false) return false;
        @chmod($configPath, 0644);
        $didAdd = true;
        nm_invalidate_php_cache($configPath);
        return true;
    }

    $cfg = nm_read_php_config_array($configPath);
    if (isset($cfg['SECRET']) && is_string($cfg['SECRET']) && trim($cfg['SECRET']) !== '') {
        return true;
    }

    $raw = (string)@file_get_contents($configPath);
    if ($raw === '') return false;

    $secret = nm_random_secret(32);

    $pos = strrpos($raw, '];');
    if ($pos === false) {
        $raw .= "\n\n// Appended by setup_auth.php\n";
        $raw .= "return [\n    'SECRET' => " . var_export($secret, true) . ",\n];\n";
        $ok = @file_put_contents($configPath, $raw, LOCK_EX);
        if ($ok === false) return false;
        $didAdd = true;
        nm_invalidate_php_cache($configPath);
        return true;
    }

    $insert = "    // Appended by setup_auth.php\n    'SECRET' => " . var_export($secret, true) . ",\n";
    $new = substr($raw, 0, $pos) . $insert . substr($raw, $pos);

    $ok = @file_put_contents($configPath, $new, LOCK_EX);
    if ($ok === false) return false;

    $didAdd = true;
    nm_invalidate_php_cache($configPath);
    return true;
}

/**
 * config/config.api.php を「コメント等を保持したまま」トークンだけ更新/追記
 * - 空文字は更新しない（既存維持）
 */
function nm_update_config_api_tokens_preserve(string $configApiPath, string $expectedToken, string $adminToken): bool
{
    $dir = dirname($configApiPath);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) return false;
    }

    if (!file_exists($configApiPath)) {
        $tpl = "<?php\n"
             . "// config/config.api.php\n"
             . "// This config/config.api.php file was automatically generated by setup_auth.php\n"
             . "// ADMIN_TOKEN    : Strong token for cleanup (destructive operations)\n"
             . "// DATA_JSON      : Absolute path to data.json\n"
             . "return [\n"
             . "    'EXPECTED_TOKEN' => " . var_export($expectedToken !== '' ? $expectedToken : 'CHANGE_ME', true) . ",\n"
             . "    'ADMIN_TOKEN' => " . var_export($adminToken !== '' ? $adminToken : 'CHANGE_ME', true) . ",\n\n"
             . "    // Absolute path to data.json as seen from api.php / read_api.php / cleanup_api.php\n"
             . "    // In this sample, it assumes \"notemod-data\" exists one level above \"config/\"\n"
             . "    'DATA_JSON'      => dirname(__DIR__) . '/notemod-data/data.json',\n\n"
             . "    // Default color for newly created categories/notes\n"
             . "    // (Hex-like string, following Notemod’s internal format)\n"
             . "    'DEFAULT_COLOR'  => '3478bd',\n\n"
             . "    // Whether to create a backup when running cleanup\n"
             . "    // true  : Save data.json as .bak-YYYYmmdd-HHiiSS before execution\n"
             . "    // false : Do not create a backup\n"
             . "    'CLEANUP_BACKUP_ENABLED' => true,\n\n"
             . "    // (Optional) Backup filename suffix\n"
             . "    // Example: use 'data.json.bak-' if you want that format\n"
             . "    // Do NOT change this if you want ClipboardSender\n"
             . "    // to bulk-delete backup files\n"
             . "    'CLEANUP_BACKUP_SUFFIX' => '.bak-',\n"
             . "    'CLEANUP_BACKUP_KEEP' => 10,"
             . "];\n";

        $ok = @file_put_contents($configApiPath, $tpl, LOCK_EX);
        if ($ok === false) return false;
        @chmod($configApiPath, 0644);
        nm_invalidate_php_cache($configApiPath);
        return true;
    }

    $raw = (string)@file_get_contents($configApiPath);
    if ($raw === '') return false;

    $replaceValue = function(string $content, string $key, string $newVal): array {
        if ($newVal === '') return [$content, false];

        $pattern = '/([\'"]' . preg_quote($key, '/') . '[\'"]\s*=>\s*)([\'"])(.*?)(\2)/u';
        if (preg_match($pattern, $content)) {
            $repl = '$1' . "'" . addslashes($newVal) . "'";
            $content2 = preg_replace($pattern, $repl, $content, 1);
            return [$content2 ?? $content, true];
        }

        $pos = strrpos($content, '];');
        if ($pos === false) {
            $content .= "\n// Appended by setup_auth.php\n";
            $content .= "return [\n    '" . $key . "' => '" . addslashes($newVal) . "',\n];\n";
            return [$content, true];
        }

        $insert = "    '" . $key . "' => '" . addslashes($newVal) . "',\n";
        $content2 = substr($content, 0, $pos) . $insert . substr($content, $pos);
        return [$content2, true];
    };

    $changed = false;
    [$raw, $c1] = $replaceValue($raw, 'EXPECTED_TOKEN', $expectedToken);
    $changed = $changed || $c1;
    [$raw, $c2] = $replaceValue($raw, 'ADMIN_TOKEN', $adminToken);
    $changed = $changed || $c2;

    if (!$changed) return true;

    $ok = @file_put_contents($configApiPath, $raw, LOCK_EX);
    if ($ok === false) return false;

    @chmod($configApiPath, 0644);
    nm_invalidate_php_cache($configApiPath);
    return true;
}

// --------------------
// State: auth exists & login status
// --------------------
$already = file_exists($authPath);

// 重要：nm_auth_is_logged_in() は session_start 後で正しく判定できる前提
$isLoggedIn = function_exists('nm_auth_is_logged_in') ? (bool)nm_auth_is_logged_in() : false;

// ★追加：ログイン中ユーザー名（ログイン中のみ表示）
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

// 初回セットアップ（auth無し）は編集OK、auth有りの場合はログイン時のみ編集OK
$canEditTokens  = (!$already) || $isLoggedIn;
$showRealTokens = $canEditTokens;

// --------------------
// Prefill API tokens (always reload from file)
// --------------------
$tokens = nm_reload_api_tokens($configApiPath);
$prefExpected = $tokens['EXPECTED_TOKEN'];
$prefAdmin    = $tokens['ADMIN_TOKEN'];

// 表示用（未ログイン時は伏字）
$displayExpected = $showRealTokens ? $prefExpected : nm_mask_token($prefExpected);
$displayAdmin    = $showRealTokens ? $prefAdmin    : nm_mask_token($prefAdmin);

// --------------------
// Handle POST
// --------------------
$msg = '';
$err = '';
$secretInfo = '';
$apiInfo = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    // 1) Create auth.php if missing (keep current behavior)
    if (!$already) {
        $u = trim((string)($_POST['username'] ?? ''));
        $p1 = (string)($_POST['password'] ?? '');
        $p2 = (string)($_POST['password2'] ?? '');

        if ($u === '') $err = $t[$lang]['err_user_empty'];
        elseif ($p1 !== $p2) $err = $t[$lang]['err_pw_mismatch'];
        elseif (strlen($p1) < 10) $err = $t[$lang]['err_pw_short'];
        else {
            $h = password_hash($p1, PASSWORD_DEFAULT);
            if (!$h) $err = $t[$lang]['err_write_auth'];
            else {
                if (!nm_auth_write_config($u, $h)) $err = $t[$lang]['err_write_auth'];
                else {
                    $already = true; // 初回作成
                    // 初回セットアップはこのリクエストでは編集OKのまま
                }
            }
        }
    }

    // 2) Ensure SECRET in config/config.php
    if ($err === '') {
        $didAdd = false;
        if (!nm_ensure_secret_in_config($configDir, $configPath, $didAdd)) {
            $err = $t[$lang]['secret_write_failed'];
        } else {
            $secretInfo = $didAdd ? $t[$lang]['secret_status_added'] : $t[$lang]['secret_status_ok'];
        }
    }

    // 3) Update tokens (ONLY when allowed)
    // 未ログイン時に DevTools で disabled を外されても、ここで弾く
    if ($err === '' && $canEditTokens) {
        $expected = trim((string)($_POST['expected_token'] ?? ''));
        $admin    = trim((string)($_POST['admin_token'] ?? ''));

        if (!nm_update_config_api_tokens_preserve($configApiPath, $expected, $admin)) {
            $err = $t[$lang]['api_save_failed'];
        } else {
            $apiInfo = $t[$lang]['api_saved'];
        }
    }

    if ($err === '') $msg = $t[$lang]['ok'];

    // ★重要：保存後は必ずファイルから再読込して画面表示を確定させる
    $tokens = nm_reload_api_tokens($configApiPath);
    $prefExpected = $tokens['EXPECTED_TOKEN'];
    $prefAdmin    = $tokens['ADMIN_TOKEN'];

    // showRealTokens/canEditTokens は最初に決めた値を使う（初回セットアップは true のまま）
    $displayExpected = $showRealTokens ? $prefExpected : nm_mask_token($prefExpected);
    $displayAdmin    = $showRealTokens ? $prefAdmin    : nm_mask_token($prefAdmin);
}

// --------------------
// Links
// --------------------
$u = nm_ui_toggle_urls('/setup_auth.php', $lang, $theme);
$backUrl   = nm_ui_url('/');
$loginUrl   = nm_ui_url('/login.php');
$accountUrl = nm_ui_url('/account.php');

// Secret status (when not posted)
if ($secretInfo === '') {
    try {
        $cfg = nm_read_php_config_array($configPath);
        if (isset($cfg['SECRET']) && is_string($cfg['SECRET']) && trim($cfg['SECRET']) !== '') {
            $secretInfo = $t[$lang]['secret_status_ok'];
        } else {
            $secretInfo = $t[$lang]['secret_note'];
        }
    } catch (Throwable $e) {
        $secretInfo = $t[$lang]['secret_note'];
    }
}
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
      padding-right:120px; /* トグルと干渉しにくく */
      padding-bottom:10px;
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
    input[disabled]{ opacity:.75; cursor:not-allowed; }

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
      .head{ padding-right:18px; }
    }

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
            <?=htmlspecialchars($t[$lang]['desc'], ENT_QUOTES, 'UTF-8')?><br>
            <?=htmlspecialchars($t[$lang]['desc2'], ENT_QUOTES, 'UTF-8')?>

            <?php if ($isLoggedIn && $loggedUser !== ''): ?>
              <br><?=htmlspecialchars($t[$lang]['logged_as'], ENT_QUOTES, 'UTF-8')?> <b><?=htmlspecialchars($loggedUser, ENT_QUOTES, 'UTF-8')?></b>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="body">
        
        <div class="row-links body-top-right" style="justify-content:flex-end; margin:-2px 0 14px;">
          <a href="<?=htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_back'], ENT_QUOTES, 'UTF-8')?></a>
          <a href="<?=htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_login'], ENT_QUOTES, 'UTF-8')?></a>
          <a href="<?=htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_account'], ENT_QUOTES, 'UTF-8')?></a>
        </div>
<?php if ($msg): ?><div class="notice ok"><?=htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
        <?php if ($err): ?><div class="notice bad"><?=htmlspecialchars($err, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>

        <div class="box">
          <h3><?=htmlspecialchars($t[$lang]['secret_section'], ENT_QUOTES, 'UTF-8')?></h3>
          <div class="notice"><?=htmlspecialchars($secretInfo, ENT_QUOTES, 'UTF-8')?></div>
        </div>

        <div class="box">
          <form method="post">
            <h3><?=htmlspecialchars($t[$lang]['auth_section'], ENT_QUOTES, 'UTF-8')?></h3>

            <?php if (!$already): ?>
              <label><?=htmlspecialchars($t[$lang]['username'], ENT_QUOTES, 'UTF-8')?></label>
              <input name="username" required>

              <label><?=htmlspecialchars($t[$lang]['password'], ENT_QUOTES, 'UTF-8')?></label>
              <input name="password" type="password" required autocomplete="new-password">

              <label><?=htmlspecialchars($t[$lang]['password2'], ENT_QUOTES, 'UTF-8')?></label>
              <input name="password2" type="password" required autocomplete="new-password">
            <?php else: ?>
              <div class="notice"><?=htmlspecialchars($t[$lang]['exists'], ENT_QUOTES, 'UTF-8')?></div>
            <?php endif; ?>

            <h3 style="margin-top:14px;"><?=htmlspecialchars($t[$lang]['api_section'], ENT_QUOTES, 'UTF-8')?></h3>

            <?php if ($already && !$isLoggedIn): ?>
              <div class="notice"><?=htmlspecialchars($t[$lang]['api_locked'], ENT_QUOTES, 'UTF-8')?></div>
            <?php else: ?>
              <div class="notice"><?=htmlspecialchars($t[$lang]['api_note'], ENT_QUOTES, 'UTF-8')?></div>
            <?php endif; ?>

            <label><?=htmlspecialchars($t[$lang]['expected_token'], ENT_QUOTES, 'UTF-8')?></label>
            <input
              name="expected_token"
              value="<?=htmlspecialchars($displayExpected, ENT_QUOTES, 'UTF-8')?>"
              <?= $canEditTokens ? '' : 'disabled' ?>
              autocomplete="off"
            >

            <label><?=htmlspecialchars($t[$lang]['admin_token'], ENT_QUOTES, 'UTF-8')?></label>
            <input
              name="admin_token"
              value="<?=htmlspecialchars($displayAdmin, ENT_QUOTES, 'UTF-8')?>"
              <?= $canEditTokens ? '' : 'disabled' ?>
              autocomplete="off"
            >

            <button class="btn" type="submit"><?=htmlspecialchars($t[$lang]['btn'], ENT_QUOTES, 'UTF-8')?></button>

            <?php if ($apiInfo): ?>
              <div class="notice ok" style="margin-top:12px;"><?=htmlspecialchars($apiInfo, ENT_QUOTES, 'UTF-8')?></div>
            <?php endif; ?>
          </form>
        </div>

        <div class="row-links">
          <a href="<?=htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_back'], ENT_QUOTES, 'UTF-8')?></a>
          <a href="<?=htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_login'], ENT_QUOTES, 'UTF-8')?></a>
          <a href="<?=htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_account'], ENT_QUOTES, 'UTF-8')?></a>
        </div>

      </div>
    </div>
  </div>
</body>
</html>