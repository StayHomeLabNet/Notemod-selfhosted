<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_common.php';

/*
 * setup_auth.php
 * - 初回: 誰でもアクセス可（初期ユーザー作成 + SECRET設定 + APIトークン設定）
 * - 運用中: ページ自体は見れるが、未ログイン時は APIトークンは伏字＆編集不可
 * - 運用中でログイン済み: APIトークン編集可
 *
 * 今回の仕様:
 * - ユーザー名は小文字化して DIR_USER に使用
 * - 設定は config/<DIR_USER>/ に保存
 * - データは notemod-data/<DIR_USER>/ に保存
 * - ログは logs/<DIR_USER>/ に保存
 */

header('Content-Type: text/html; charset=utf-8');

// --------------------
// robots.txt auto-create
// --------------------
$robotsPath = __DIR__ . '/robots.txt';
if (!is_file($robotsPath)) {
    @file_put_contents($robotsPath, "User-agent: *\nDisallow: /\n", LOCK_EX);
    @chmod($robotsPath, 0644);
}

// --------------------
// Session / UI
// --------------------
nm_auth_start_session();
$ui    = nm_ui_bootstrap();
$lang  = $ui['lang'];
$theme = $ui['theme'];

// --------------------
// i18n
// --------------------
$t = [
  'ja' => [
    'title' => '初期セットアップ / 認証設定',
    'desc'  => 'Notemod-selfhosted のログイン用ユーザー/パスワードを作成します。',
    'desc2' => 'さらに、API用トークンと SECRET をセットできます。',
    'logged_as' => 'ログイン中:',
    'dir_user' => '保存ディレクトリ:',

    'username' => 'ユーザー名',
    'password' => 'パスワード（10文字以上）',
    'password2'=> 'パスワード（再入力）',
    'btn' => '保存',
    'ok' => '保存しました',
    'err_write_auth' => '認証設定の保存に失敗しました（権限を確認）',
    'err_pw_mismatch' => 'パスワードが一致しません',
    'err_pw_short' => 'パスワードは10文字以上にしてください',
    'err_user_empty' => 'ユーザー名が空です',
    'err_user_invalid' => 'ユーザー名が無効です（英小文字・数字・_・- を使用）',
    'exists' => 'このユーザーは既に設定済みです。必要ならアカウント画面から変更してください。',
    'go_back' => '戻る',
    'logout' => 'ログアウト',
    'go_login' => 'ログインへ',
    'go_account' => 'アカウント設定へ',
    'go_log_settings' => 'ログ設定へ',
    'go_bak_settings' => 'バックアップ設定へ',
    'go_clipboard_sync' => 'クリップボード同期へ',
    
    'lang_label' => '言語',
    'theme_label' => 'テーマ',
    'dark' => 'Dark',
    'light' => 'Light',

    'secret_section' => 'サーバー同期用 SECRET（config/<USER_NAME>/config.php）',
    'secret_note' => "config/<USER_NAME>/config.php が存在しない場合は自動で作成します\nSECRET が無い場合は自動で追記します（既存値は変更しません）",
    'secret_status_ok' => 'SECRET は設定済みです',
    'secret_status_added' => 'SECRET を config/<USER_NAME>/config.php に保存しました',
    'secret_write_failed' => 'config/<USER_NAME>/config.php の更新に失敗しました（権限を確認）',

    'api_section' => 'API トークン（config/<USER_NAME>/config.api.php）',
    'expected_token' => 'EXPECTED_TOKEN（通常アクセス用）',
    'admin_token' => 'ADMIN_TOKEN（管理用 / cleanup用）',
    'api_note' => "config/<USER_NAME>/config.api.php が存在しない場合は自動で作成します\n空で保存すると、その項目は更新しません（既存値は維持）",
    'api_saved' => 'config/<USER_NAME>/config.api.php を更新しました',
    'api_save_failed' => 'config/<USER_NAME>/config.api.php の保存に失敗しました（権限を確認）',
    'api_locked' => '※ トークンの閲覧 / 変更はログイン後のみ可能です（初回セットアップ時を除く）',

    'auth_section' => '画面ログイン（config/<USER_NAME>/auth.php）',
  ],
  'en' => [
    'title' => 'Initial Setup / Authentication settings',
    'desc'  => 'Create username/password for Notemod-selfhosted login.',
    'desc2' => 'You can also set API tokens and SECRET.',
    'logged_as' => 'Logged in as:',
    'dir_user' => 'Storage directory:',

    'username' => 'Username',
    'password' => 'Password (min 10 chars)',
    'password2'=> 'Repeat password',
    'btn' => 'Save',
    'ok' => 'Saved',
    'err_write_auth' => 'Failed to save authentication settings (permission?)',
    'err_pw_mismatch' => 'Passwords do not match',
    'err_pw_short' => 'Password must be at least 10 characters',
    'err_user_empty' => 'Username is empty',
    'err_user_invalid' => 'Invalid username (use lowercase letters, numbers, _ and -)',
    'exists' => 'This user is already configured. Use Account page to change it if needed.',
    'go_back' => 'Back',
    'logout' => 'Logout',
    'go_login' => 'Go to Login',
    'go_account' => 'Go to Account',
    'go_log_settings' => 'Go to Log settings',
    'go_bak_settings' => 'Go to Backup settings',
    'go_clipboard_sync' => 'Go to Clipboard sync',

    'lang_label' => 'Language',
    'theme_label' => 'Theme',
    'dark' => 'Dark',
    'light' => 'Light',

    'secret_section' => 'SECRET for sync (config/<USER_NAME>/config.php)',
    'secret_note' => "If config/<USER_NAME>/config.php does not exist, it will be created automatically.\nIf SECRET is missing, it will be appended automatically (existing values are kept).",
    'secret_status_ok' => 'SECRET is already set',
    'secret_status_added' => 'SECRET was saved to config/<USER_NAME>/config.php',
    'secret_write_failed' => 'Failed to update config/<USER_NAME>/config.php (permission?)',

    'api_section' => 'API tokens (config/<USER_NAME>/config.api.php)',
    'expected_token' => 'EXPECTED_TOKEN',
    'admin_token' => 'ADMIN_TOKEN (admin / cleanup)',
    'api_note' => "If config/<USER_NAME>/config.api.php does not exist, it will be created automatically.\nIf left empty, it will not update that field (existing values are kept).",
    'api_saved' => 'Updated config/<USER_NAME>/config.api.php',
    'api_save_failed' => 'Failed to write config/<USER_NAME>/config.api.php (permission?)',
    'api_locked' => 'Token view / edit is available only after login (except initial setup).',

    'auth_section' => 'Screen login (config/<USER_NAME>/auth.php)',
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
function generate_token(int $bytes = 24): string {
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
function nm_write_protected_htaccess(string $dir): bool {
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return false;
    }
    $path = rtrim($dir, '/\\') . '/.htaccess';
    $content = "Options -Indexes\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n";
    if (!file_exists($path)) {
        if (@file_put_contents($path, $content, LOCK_EX) === false) {
            return false;
        }
        @chmod($path, 0644);
    }
    return true;
}
function nm_initial_snapshot_json(string $configPath): string {
    $cfg = nm_read_php_config_array($configPath);
    $raw = (string)($cfg['INITIAL_SNAPSHOT'] ?? '');
    if ($raw !== '') {
        json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $raw;
        }
    }
    return '{"categories":null,"hasSelectedLanguage":null,"notes":null,"selectedLanguage":null}';
}
function user_exists(string $dirUser): bool {
    $dirUser = normalize_username($dirUser);
    if ($dirUser === '') return false;
    return file_exists(nm_auth_config_path($dirUser));
}

/**
 * config/<DIR_USER>/config.php に SECRET を設定
 */
function nm_ensure_secret_in_config(string $configPath, string $dirUser, bool &$didAdd): bool
{
    $didAdd = false;
    $configDir = dirname($configPath);
    if (!is_dir($configDir) && !@mkdir($configDir, 0755, true)) {
        return false;
    }

    if (!file_exists($configPath)) {
        $secret = nm_random_secret(32);
        $content =
            "<?php\n"
          . "// config/<USER_NAME>/config.php\n"
          . "// Automatically generated by setup_auth.php\n"
          . "return [\n"
          . "    'TIMEZONE' => 'Asia/Tokyo',\n\n"
          . "    'DEBUG' => false,\n\n"
          . "    'LOGGER_FILE_ENABLED' => true,\n"
          . "    'LOGGER_NOTEMOD_ENABLED' => false,\n\n"
          . "    'IP_ALERT_ENABLED' => false,\n"
          . "    'IP_ALERT_TO'      => 'YOUR_EMAIL',\n"
          . "    'IP_ALERT_FROM'    => 'no-reply@notemod',\n"
          . "    'IP_ALERT_SUBJECT' => 'Notemod: First-time IP access',\n"
          . "    'IP_ALERT_IGNORE_BOTS' => true,\n"
          . "    'IP_ALERT_IGNORE_IPS'  => [''],\n"
          . "    'IP_ALERT_STORE'   => dirname(__DIR__, 2) . '/notemod-data/' . " . var_export($dirUser, true) . " . '/_known_ips.json',\n\n"
          . "    'LOGGER_FILE_MAX_LINES' => 500,\n"
          . "    'LOGGER_NOTEMOD_MAX_LINES' => 50,\n\n"
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
    } else {
        $insert = "    // Appended by setup_auth.php\n    'SECRET' => " . var_export($secret, true) . ",\n";
        $raw = substr($raw, 0, $pos) . $insert . substr($raw, $pos);
    }

    $ok = @file_put_contents($configPath, $raw, LOCK_EX);
    if ($ok === false) return false;

    $didAdd = true;
    nm_invalidate_php_cache($configPath);
    return true;
}

/**
 * config/<DIR_USER>/config.api.php をコメント維持で更新/生成
 */
function nm_update_config_api_tokens_preserve(string $configApiPath, string $dirUser, string $expectedToken, string $adminToken): bool
{
    $dir = dirname($configApiPath);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return false;
    }

    if (!file_exists($configApiPath)) {
        $tpl = "<?php\n"
             . "// config/<USER_NAME>/config.api.php\n"
             . "// Automatically generated by setup_auth.php\n"
             . "return [\n"
             . "    'EXPECTED_TOKEN' => " . var_export($expectedToken !== '' ? $expectedToken : generate_token(), true) . ",\n"
             . "    'ADMIN_TOKEN'    => " . var_export($adminToken !== '' ? $adminToken : generate_token(), true) . ",\n\n"
             . "    'DATA_JSON'      => dirname(__DIR__, 2) . '/notemod-data/' . " . var_export($dirUser, true) . " . '/data.json',\n"
             . "    'DEFAULT_COLOR'  => '3478bd',\n"
             . "    'CLEANUP_BACKUP_ENABLED' => true,\n"
             . "    'CLEANUP_BACKUP_SUFFIX'  => '.bak-',\n"
             . "    'CLEANUP_BACKUP_KEEP'    => 10,\n"
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

    if (!preg_match('/([\'"]DATA_JSON[\'"]\s*=>\s*)(.+?)(,)/u', $raw)) {
        $pos = strrpos($raw, '];');
        if ($pos !== false) {
            $insert = "    'DATA_JSON'      => dirname(__DIR__, 2) . '/notemod-data/' . " . var_export($dirUser, true) . " . '/data.json',\n";
            $raw = substr($raw, 0, $pos) . $insert . substr($raw, $pos);
            $changed = true;
        }
    }

    if (!$changed) return true;

    $ok = @file_put_contents($configApiPath, $raw, LOCK_EX);
    if ($ok === false) return false;

    @chmod($configApiPath, 0644);
    nm_invalidate_php_cache($configApiPath);
    return true;
}

function create_user_environment(string $dirUser, string $username, string $password): bool
{
    $dirUser = normalize_username($dirUser);
    if ($dirUser === '' || $password === '') return false;

    $configDir = nm_config_dir($dirUser);
    $logsDir   = nm_logs_dir($dirUser);
    $dataDir   = nm_data_dir($dirUser);
    $imagesDir = $dataDir . '/images';
    $filesDir  = $dataDir . '/files';

    foreach ([$configDir, $logsDir, $dataDir, $imagesDir, $filesDir] as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return false;
        }
    }

    if (!nm_write_protected_htaccess($logsDir)) return false;
    if (!nm_write_protected_htaccess($dataDir)) return false;

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if (!$passwordHash) return false;
    if (!nm_auth_write_config($username, $passwordHash, $dirUser)) return false;

    $configPath = nm_config_path($dirUser);
    $configApiPath = nm_api_config_path($dirUser);

    $didAdd = false;
    if (!nm_ensure_secret_in_config($configPath, $dirUser, $didAdd)) return false;
    if (!nm_update_config_api_tokens_preserve($configApiPath, $dirUser, '', '')) return false;

    $dataJsonPath = nm_data_json_path($dirUser);
    if (!file_exists($dataJsonPath)) {
        $json = nm_initial_snapshot_json($configPath);
        if (@file_put_contents($dataJsonPath, $json, LOCK_EX) === false) {
            return false;
        }
        @chmod($dataJsonPath, 0644);
    }

    $knownIpsPath = nm_data_dir($dirUser) . '/_known_ips.json';
    if (!file_exists($knownIpsPath)) {
        if (@file_put_contents($knownIpsPath, "[]\n", LOCK_EX) === false) {
            return false;
        }
        @chmod($knownIpsPath, 0644);
    }

    $logFile = $logsDir . '/access-' . gmdate('Y-m') . '.log';
    if (!file_exists($logFile)) {
        if (@file_put_contents($logFile, '', LOCK_EX) === false) {
            return false;
        }
        @chmod($logFile, 0644);
    }

    return true;
}

// --------------------
// State
// --------------------
$previewUser    = normalize_username((string)($_POST['username'] ?? $_GET['username'] ?? ''));
$currentDirUser = nm_get_current_dir_user();
$targetDirUser  = $currentDirUser !== '' ? $currentDirUser : $previewUser;

$authGlob = glob(__DIR__ . '/config/*/auth.php');
$hasAnyUser = is_array($authGlob) && count($authGlob) > 0;

$configDir     = $targetDirUser !== '' ? __DIR__ . '/config/' . $targetDirUser : __DIR__ . '/config';
$authPath      = $targetDirUser !== '' ? __DIR__ . '/config/' . $targetDirUser . '/auth.php' : '';
$configPath    = $targetDirUser !== '' ? __DIR__ . '/config/' . $targetDirUser . '/config.php' : '';
$configApiPath = $targetDirUser !== '' ? __DIR__ . '/config/' . $targetDirUser . '/config.api.php' : '';

$isLoggedIn = function_exists('nm_auth_is_logged_in') ? (bool)nm_auth_is_logged_in() : false;
$loggedUser = $isLoggedIn ? nm_get_current_user() : '';
if ($targetDirUser === '' && $isLoggedIn) {
    $targetDirUser = nm_get_current_dir_user();
    $configDir     = __DIR__ . '/config/' . $targetDirUser;
    $authPath      = __DIR__ . '/config/' . $targetDirUser . '/auth.php';
    $configPath    = __DIR__ . '/config/' . $targetDirUser . '/config.php';
    $configApiPath = __DIR__ . '/config/' . $targetDirUser . '/config.api.php';
}

$already = ($targetDirUser !== '' && file_exists($authPath));
$canEditTokens  = (!$hasAnyUser) || $isLoggedIn;
$showRealTokens = $canEditTokens;

// --------------------
// Prefill API tokens
// --------------------
$prefExpected = '';
$prefAdmin    = '';
if ($configApiPath !== '' && file_exists($configApiPath)) {
    $tokens = nm_reload_api_tokens($configApiPath);
    $prefExpected = $tokens['EXPECTED_TOKEN'];
    $prefAdmin    = $tokens['ADMIN_TOKEN'];
}
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

    if (!$hasAnyUser) {
        $uRaw = trim((string)($_POST['username'] ?? ''));
        $uDir = normalize_username($uRaw);
        $p1   = (string)($_POST['password'] ?? '');
        $p2   = (string)($_POST['password2'] ?? '');

        if ($uRaw === '') {
            $err = $t[$lang]['err_user_empty'];
        } elseif ($uDir === '') {
            $err = $t[$lang]['err_user_invalid'];
        } elseif ($p1 !== $p2) {
            $err = $t[$lang]['err_pw_mismatch'];
        } elseif (strlen($p1) < 10) {
            $err = $t[$lang]['err_pw_short'];
        } elseif (user_exists($uDir)) {
            $err = $t[$lang]['exists'];
        } else {
            if (!create_user_environment($uDir, $uRaw, $p1)) {
                $err = $t[$lang]['err_write_auth'];
            } else {
                $targetDirUser  = $uDir;
                $configDir      = __DIR__ . '/config/' . $targetDirUser;
                $authPath       = __DIR__ . '/config/' . $targetDirUser . '/auth.php';
                $configPath     = __DIR__ . '/config/' . $targetDirUser . '/config.php';
                $configApiPath  = __DIR__ . '/config/' . $targetDirUser . '/config.api.php';
                $hasAnyUser     = true;
                $already        = true;
            }
        }
    }

    if ($err === '' && $targetDirUser !== '') {
        $didAdd = false;
        if (!nm_ensure_secret_in_config($configPath, $targetDirUser, $didAdd)) {
            $err = $t[$lang]['secret_write_failed'];
        } else {
            $secretInfo = $didAdd ? $t[$lang]['secret_status_added'] : $t[$lang]['secret_status_ok'];
        }
    }

    if ($err === '' && $canEditTokens && $targetDirUser !== '') {
        $expected = trim((string)($_POST['expected_token'] ?? ''));
        $admin    = trim((string)($_POST['admin_token'] ?? ''));

        if (!nm_update_config_api_tokens_preserve($configApiPath, $targetDirUser, $expected, $admin)) {
            $err = $t[$lang]['api_save_failed'];
        } else {
            $apiInfo = $t[$lang]['api_saved'];
        }
    }

    if ($err === '') {
        $msg = $t[$lang]['ok'];
    }

    if ($configApiPath !== '' && file_exists($configApiPath)) {
        $tokens = nm_reload_api_tokens($configApiPath);
        $prefExpected = $tokens['EXPECTED_TOKEN'];
        $prefAdmin    = $tokens['ADMIN_TOKEN'];
        $displayExpected = $showRealTokens ? $prefExpected : nm_mask_token($prefExpected);
        $displayAdmin    = $showRealTokens ? $prefAdmin    : nm_mask_token($prefAdmin);
    }
}

// --------------------
// Links
// --------------------
$u = nm_ui_toggle_urls('/setup_auth.php', $lang, $theme);
$backUrl    = nm_ui_url('/');
$logoutUrl  = nm_ui_url('/logout.php');
$loginUrl   = nm_ui_url('/login.php');
$accountUrl = nm_ui_url('/account.php');
$logsettingsUrl = nm_ui_url('/log_settings.php');
$baksettingsUrl = nm_ui_url('/bak_settings.php');
$clipboardsyncUrl = nm_ui_url('/clipboard_sync.php');

// Secret status (when not posted)
if ($secretInfo === '') {
    if ($configPath !== '' && file_exists($configPath)) {
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
    } else {
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
    .title{ font-weight:900; letter-spacing:.3px; font-size:18px; margin:0; }
    .left{ display:flex; flex-direction:column; gap:4px; }
    .sub{ color:var(--muted); font-size:12px; margin-top:6px; }
    .desc-lines{ color:var(--muted); font-size:13px; line-height:1.5; margin-top:10px; }
    .head .right{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
    .topbtn{
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
      white-space:nowrap;
    }
    .topbtn:hover{ transform: translateY(-1px); border-color: color-mix(in srgb, var(--accent) 38%, var(--line)); text-decoration:none; }
    .topbtn.red{ border-color: color-mix(in srgb, var(--danger) 35%, var(--line)); color: color-mix(in srgb, var(--danger) 75%, var(--text)); }
    .topbtn.red:hover{ border-color: color-mix(in srgb, var(--danger) 60%, var(--line)); }
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

    .toggles{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; user-select:none; }
    .toggle-row{ display:flex; gap:8px; align-items:center; }
    .toggle-row span{ font-size:12px; color:var(--muted); line-height:1.2; }
    .pill{
      display:inline-flex; gap:3px; align-items:center;
      background: color-mix(in srgb, var(--card2) 60%, transparent);
      border:1px solid color-mix(in srgb, var(--line) 105%, transparent);
      padding:2px;
      border-radius:999px;
    }
    .pill a{
      font-size:12px;
      padding:6px 10px;
      border-radius:999px;
      color:var(--muted);
      text-decoration:none;
      border:1px solid transparent;
      white-space:nowrap;
      line-height:1.1;
      font-weight:800;
    }
    .pill a.active{
      background: color-mix(in srgb, var(--accent) 16%, transparent);
      color: var(--text);
      border-color: color-mix(in srgb, var(--accent) 26%, transparent);
    }

    .row-links{ display:flex; gap:12px; flex-wrap:wrap; }
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
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="head">
        <div class="left">
          <h1 class="title"><?=htmlspecialchars($t[$lang]['title'], ENT_QUOTES, 'UTF-8')?></h1>
          <?php if ($isLoggedIn && $loggedUser !== ''): ?>
            <div class="sub" style="white-space: nowrap;"><?=htmlspecialchars($t[$lang]['logged_as'], ENT_QUOTES, 'UTF-8')?> <b><?=htmlspecialchars($loggedUser, ENT_QUOTES, 'UTF-8')?></b> &nbsp; | &nbsp; <?=htmlspecialchars($t[$lang]['dir_user'], ENT_QUOTES, 'UTF-8')?> <b><?=htmlspecialchars($targetDirUser !== '' ? $targetDirUser : normalize_username((string)$loggedUser), ENT_QUOTES, 'UTF-8')?></b></div>
          <?php endif; ?>
          <div class="desc-lines">
            <?=htmlspecialchars($t[$lang]['desc'], ENT_QUOTES, 'UTF-8')?><br>
            <?=htmlspecialchars($t[$lang]['desc2'], ENT_QUOTES, 'UTF-8')?>
          </div>
        </div>

        <div class="right">
          <a class="topbtn" href="<?=htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8')?>">← <?=htmlspecialchars($t[$lang]['go_back'], ENT_QUOTES, 'UTF-8')?></a>
          <?php if ($isLoggedIn): ?>
            <a class="topbtn red" href="<?=htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['logout'], ENT_QUOTES, 'UTF-8')?></a>
          <?php else: ?>
            <a class="topbtn red" href="<?=htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_login'], ENT_QUOTES, 'UTF-8')?></a>
          <?php endif; ?>

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
        <?php if ($msg): ?><div class="notice ok"><?=htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
        <?php if ($err): ?><div class="notice bad"><?=htmlspecialchars($err, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>

        <div class="box">
          <h3><?=htmlspecialchars($t[$lang]['secret_section'], ENT_QUOTES, 'UTF-8')?></h3>
          <div class="notice"><?=htmlspecialchars($secretInfo, ENT_QUOTES, 'UTF-8')?></div>
        </div>

        <div class="box">
          <form method="post">
            <h3><?=htmlspecialchars($t[$lang]['auth_section'], ENT_QUOTES, 'UTF-8')?></h3>

            <?php if (!$hasAnyUser): ?>
              <label><?=htmlspecialchars($t[$lang]['username'], ENT_QUOTES, 'UTF-8')?></label>
              <input name="username" required value="<?=htmlspecialchars((string)($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8')?>">

              <label><?=htmlspecialchars($t[$lang]['password'], ENT_QUOTES, 'UTF-8')?></label>
              <input name="password" type="password" required autocomplete="new-password">

              <label><?=htmlspecialchars($t[$lang]['password2'], ENT_QUOTES, 'UTF-8')?></label>
              <input name="password2" type="password" required autocomplete="new-password">
            <?php else: ?>
              <div class="notice"><?=htmlspecialchars($t[$lang]['exists'], ENT_QUOTES, 'UTF-8')?></div>
            <?php endif; ?>

            <h3 style="margin-top:14px;"><?=htmlspecialchars($t[$lang]['api_section'], ENT_QUOTES, 'UTF-8')?></h3>

            <?php if ($hasAnyUser && !$isLoggedIn): ?>
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
          <a class="topbtn" href="<?=htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8')?>">← <?=htmlspecialchars($t[$lang]['go_back'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="topbtn red" href="<?=htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_login'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="topbtn" href="<?=htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_account'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="topbtn" href="<?=htmlspecialchars($logsettingsUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_log_settings'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="topbtn" href="<?=htmlspecialchars($baksettingsUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_bak_settings'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="topbtn" href="<?=htmlspecialchars($clipboardsyncUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_clipboard_sync'], ENT_QUOTES, 'UTF-8')?></a>
        </div>
      </div>
    </div>
  </div>
</body>
</html>