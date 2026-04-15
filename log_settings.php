<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_common.php';
nm_auth_require_login();
nm_send_security_headers_html();

/*
 * log_settings.php
 * - config/<DIR_USER>/config.php の各設定をUIで編集
 * - UIは setup_auth.php と同じ配置（JP/EN, Dark/Light）
 * - 運用中はログイン必須（未ログインなら表示のみ/編集不可）
 *
 * 実装追加
 * (1) 保存後に config/<DIR_USER>/config.php が壊れていないか検証（壊れてたら自動でロールバック）
 * (2) 未ログインで POST された場合はサーバ側で弾く（DevTools対策）
 * (3) IP_ALERT_IGNORE_IPS が空の場合はキーを書かない（更新しない＝スッキリ運用向け）
 *
 * 追加要望
 * - account.php と同様に、ログイン中ユーザー名を左上（ヘッダ）に表示（ログイン中のみ）
 */

// --------------------
// Paths
// --------------------
$currentDirUser = function_exists('nm_get_current_dir_user') ? (string)nm_get_current_dir_user() : '';
$configDir  = dirname((string)(function_exists('nm_config_path') ? nm_config_path($currentDirUser) : (nm_config_path($currentDirUser ?: null))));
$configPath = (string)(function_exists('nm_config_path') ? nm_config_path($currentDirUser) : (nm_config_path($currentDirUser ?: null)));

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

// ログイン中ユーザー名 / 保存先ユーザー（ログイン中のみ表示）
$loggedUser = '';
$currentDirUser = function_exists('nm_get_current_dir_user') ? (string)nm_get_current_dir_user() : $currentDirUser;
if ($isLoggedIn) {
    $loggedUser = function_exists('nm_get_current_user') ? (string)nm_get_current_user() : (string)($_SESSION['nm_username'] ?? $_SESSION['nm_user'] ?? '');
    if ($loggedUser === '' && function_exists('nm_auth_load')) {
        try {
            $ac = nm_auth_load($currentDirUser !== '' ? $currentDirUser : null);
            $loggedUser = (string)($ac['USERNAME'] ?? '');
            if ($currentDirUser === '') {
                $currentDirUser = (string)($ac['DIR_USER'] ?? '');
            }
        } catch (Throwable $e) {
            $loggedUser = '';
        }
    }
}

// Auth email for reflect button
$authEmail = '';
if ($currentDirUser !== '' && function_exists('nm_auth_load')) {
    try {
        $authCfg = nm_auth_load($currentDirUser);
        $authEmail = trim((string)($authCfg['EMAIL'] ?? ''));
    } catch (Throwable $e) {
        $authEmail = '';
    }
}
$authEmailAvailable = ($authEmail !== '');

// Global mail config (config/mail.php)
$mailCfg = function_exists('nm_load_mail_config') ? nm_load_mail_config() : array();
$mailPref = array(
    'MAIL_TRANSPORT' => (string)($mailCfg['MAIL_TRANSPORT'] ?? 'mail'),
    'SMTP_ENABLED' => !empty($mailCfg['SMTP_ENABLED']),
    'SMTP_HOST' => (string)($mailCfg['SMTP_HOST'] ?? ''),
    'SMTP_PORT' => (int)($mailCfg['SMTP_PORT'] ?? 587),
    'SMTP_ENCRYPTION' => (string)($mailCfg['SMTP_ENCRYPTION'] ?? 'ssl'),
    'SMTP_AUTH' => !empty($mailCfg['SMTP_AUTH']),
    'SMTP_USERNAME' => (string)($mailCfg['SMTP_USERNAME'] ?? ''),
    'SMTP_PASSWORD' => (string)($mailCfg['SMTP_PASSWORD'] ?? ''),
    'SMTP_FROM' => (string)($mailCfg['SMTP_FROM'] ?? ''),
    'SMTP_FROM_NAME' => (string)($mailCfg['SMTP_FROM_NAME'] ?? ''),
    'SMTP_FALLBACK_TO_MAIL' => !empty($mailCfg['SMTP_FALLBACK_TO_MAIL']),
);
$smtpDetailsOpen = false;

// --------------------
// i18n (JP/EN only like other pages)
// --------------------
$t = [
  'ja' => [
    'title' => 'ログ / セッション設定',
    'desc'  => 'config/<DIR_USER>/config.php のログ・通知関連の設定を変更します。',
    'logged_as' => 'ログイン中:',
    'storage_user' => '保存先ディレクトリ:',
    'back' => '戻る',
    'logout' => 'ログアウト',
    'btn'   => '保存',
    'ok'    => '保存しました',
    'err_write' => 'config/<DIR_USER>/config.php の保存に失敗しました（権限を確認）',
    'err_read'  => 'config/<DIR_USER>/config.php の読み込みに失敗しました',
    'err_broken'=> 'config/<DIR_USER>/config.php が壊れた可能性があります（配列として読み込めません）',
    'err_login' => 'Login required.',
    'csrf_invalid' => 'CSRFトークンが無効です。ページを再読み込みしてからもう一度お試しください。',

    'lang_label'  => '言語',
    'theme_label' => 'テーマ',
    'dark'  => 'Dark',
    'light' => 'Light',

    'section_general' => '一般',
    'timezone' => 'TIMEZONE（例：Asia/Tokyo / Australia/Sydney / Pacific/Auckland / America/New_York）',
    'section_session' => 'セッション',
    'session_cookie_lifetime' => 'セッションクッキー保持期間',
    'session_server_gc' => 'サーバー側 PHP セッション保存期限（確認用）',
    'session_server_gc_value' => 'session.gc_maxlifetime',
    'session_warning' => 'ブラウザ側の保持期間です。サーバー側設定によっては、それより早くログインが切れる場合があります',

    'section_logger' => 'ログ',
    'logger_file_enabled' => 'LOGGER_FILE_ENABLED（Raw access log を出力）',
    'logger_notemod_enabled' => 'LOGGER_NOTEMOD_ENABLED（Notemod Logs カテゴリへ書く）',
    'logger_file_max_lines' => 'LOGGER_FILE_MAX_LINES（Raw log の最大行数 / 0=無制限）',
    'logger_notemod_max_lines' => 'LOGGER_NOTEMOD_MAX_LINES（Notemod Logs の最大行数 / 0=無制限）',

    'section_ip' => 'IPアクセス通知（メール）',
    'ip_alert_enabled' => 'IP_ALERT_ENABLED（通知を有効）',
    'ip_alert_to' => 'IP_ALERT_TO（宛先）',
    'use_auth_email' => '認証用メールを反映',
    'auth_email_not_set' => '認証用メールアドレスが未設定です。setup_auth.php で設定してください。',
    'section_smtp' => 'SMTP設定（全ユーザー共通）',
    'show_smtp' => 'SMTP設定を表示',
    'mail_transport' => '送信方式',
    'mail_transport_mail' => 'mail() を使う',
    'mail_transport_smtp' => 'SMTP を使う',
    'smtp_enabled' => 'SMTP を有効にする',
    'smtp_host' => 'SMTP_HOST',
    'smtp_port' => 'SMTP_PORT',
    'smtp_encryption' => 'SMTP_ENCRYPTION',
    'smtp_encryption_none' => 'なし',
    'smtp_encryption_tls' => 'TLS (STARTTLS)',
    'smtp_encryption_ssl' => 'SSL/TLS',
    'smtp_auth' => 'SMTP認証を使う',
    'smtp_username' => 'SMTP_USERNAME',
    'smtp_password' => 'SMTP_PASSWORD',
    'smtp_from' => 'SMTP_FROM（空欄なら SMTP_USERNAME を使用）',
    'smtp_from_name' => 'SMTP_FROM_NAME（任意）',
    'smtp_fallback' => 'SMTP失敗時に mail() へフォールバック',
    'smtp_test_to' => 'テスト送信先メールアドレス',
    'smtp_test_btn' => 'SMTPテスト送信',
    'smtp_saved' => 'SMTP設定を保存しました',
    'smtp_test_ok' => 'SMTPテストメールを送信しました',
    'smtp_test_ng' => 'SMTPテストメールの送信に失敗しました',
    'smtp_test_subject' => '[Notemod] SMTP Test',
    'smtp_test_body' => 'これは Notemod のSMTPテストメールです。',
    'smtp_test_invalid_to' => 'テスト送信先メールアドレスを正しく入力してください。',
    'smtp_save_failed' => 'config/mail.php の保存に失敗しました',
    'ip_alert_from' => 'IP_ALERT_FROM（From：任意/初期値：no-reply@notemod）',
    'ip_alert_subject' => 'IP_ALERT_SUBJECT（件名：任意/初期値：Notemod: First-time IP access）',
    'ip_alert_ignore_ips' => 'IP_ALERT_IGNORE_IPS（無視するIPアドレス、カンマ/改行区切り）',
    'ip_alert_ignore_bots' => 'IP_ALERT_IGNORE_BOTS（ボットっぽいUAを無視）',

    'go_back' => '戻る',
    'go_account' => 'アカウント設定へ',
    'go_setup_auth' => '認証設定へ',
    'go_bak_settings' => 'バックアップ設定へ',
    'go_clipboard_sync' => 'クリップボード同期へ',
    'go_media_files' => 'メディア＆ファイルへ',
  ],
  'en' => [
    'title' => 'Log / Session Settings',
    'desc'  => 'Edit logging / notification settings in config/<DIR_USER>/config.php.',
    'logged_as' => 'Logged in as:',
    'storage_user' => 'Storage directory user:',
    'back' => 'Back',
    'logout' => 'Logout',
    'btn'   => 'Save',
    'ok'    => 'Saved',
    'err_write' => 'Failed to write config/<DIR_USER>/config.php (permission?)',
    'err_read'  => 'Failed to read config/<DIR_USER>/config.php',
    'err_broken'=> 'config/<DIR_USER>/config.php may be broken (cannot be loaded as array).',
    'err_login' => 'Login required.',
    'csrf_invalid' => 'Invalid CSRF token. Please reload the page and try again.',

    'lang_label'  => 'Language',
    'theme_label' => 'Theme',
    'dark'  => 'Dark',
    'light' => 'Light',

    'section_general' => 'General',
    'timezone' => 'TIMEZONE (e.g. Asia/Tokyo / Australia/Sydney / Pacific/Auckland / America/New_York)',
    'section_session' => 'Session',
    'session_cookie_lifetime' => 'Session cookie lifetime',
    'session_server_gc' => 'Server-side PHP session retention (reference)',
    'session_server_gc_value' => 'session.gc_maxlifetime',
    'session_warning' => 'This is the browser-side retention period. Depending on the server-side settings, your login may expire earlier.',

    'section_logger' => 'Logger',
    'logger_file_enabled' => 'LOGGER_FILE_ENABLED (write raw access logs)',
    'logger_notemod_enabled' => 'LOGGER_NOTEMOD_ENABLED (write to Notemod Logs category)',
    'logger_file_max_lines' => 'LOGGER_FILE_MAX_LINES (max lines for raw logs / 0=no limit)',
    'logger_notemod_max_lines' => 'LOGGER_NOTEMOD_MAX_LINES (max lines for Notemod Logs / 0=no limit)',

    'section_ip' => 'IP access alert (Email)',
    'ip_alert_enabled' => 'IP_ALERT_ENABLED (enable)',
    'ip_alert_to' => 'IP_ALERT_TO (to)',
    'use_auth_email' => 'Use auth email',
    'auth_email_not_set' => 'Auth email is not set. Please configure it in setup_auth.php.',
    'section_smtp' => 'SMTP settings (global)',
    'show_smtp' => 'Show SMTP settings',
    'mail_transport' => 'Transport',
    'mail_transport_mail' => 'Use mail()',
    'mail_transport_smtp' => 'Use SMTP',
    'smtp_enabled' => 'Enable SMTP',
    'smtp_host' => 'SMTP_HOST',
    'smtp_port' => 'SMTP_PORT',
    'smtp_encryption' => 'SMTP_ENCRYPTION',
    'smtp_encryption_none' => 'None',
    'smtp_encryption_tls' => 'TLS (STARTTLS)',
    'smtp_encryption_ssl' => 'SSL/TLS',
    'smtp_auth' => 'Use SMTP authentication',
    'smtp_username' => 'SMTP_USERNAME',
    'smtp_password' => 'SMTP_PASSWORD',
    'smtp_from' => 'SMTP_FROM (leave blank to use SMTP_USERNAME)',
    'smtp_from_name' => 'SMTP_FROM_NAME (optional)',
    'smtp_fallback' => 'Fallback to mail() if SMTP fails',
    'smtp_test_to' => 'Test recipient email',
    'smtp_test_btn' => 'Send SMTP test',
    'smtp_saved' => 'SMTP settings saved',
    'smtp_test_ok' => 'SMTP test email sent',
    'smtp_test_ng' => 'Failed to send SMTP test email',
    'smtp_test_subject' => '[Notemod] SMTP Test',
    'smtp_test_body' => 'This is a test email from Notemod SMTP settings.',
    'smtp_test_invalid_to' => 'Please enter a valid test recipient email address.',
    'smtp_save_failed' => 'Failed to write config/mail.php',
    'ip_alert_from' => 'IP_ALERT_FROM (from: optional/default：no-reply@notemod)',
    'ip_alert_subject' => 'IP_ALERT_SUBJECT (subject: optional/default：Notemod: First-time IP access)',
    'ip_alert_ignore_ips' => 'IP_ALERT_IGNORE_IPS (ignore IPs, comma/newline separated)',
    'ip_alert_ignore_bots' => 'IP_ALERT_IGNORE_BOTS (ignore bot-like user agents)',

    'go_back' => 'Back',
    'go_account' => 'Go to Account',
    'go_setup_auth' => 'Go to Auth settings',
    'go_bak_settings' => 'Go to Backup settings',
    'go_clipboard_sync' => 'Go to Clipboard sync',
    'go_media_files' => 'Go to Media & Files',
  ],
];

// --------------------
// Helpers
// --------------------
function ls_invalidate_php_cache(string $path): void {
    clearstatcache(true, $path);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($path, true);
    }
}

function ls_read_php_config_array(string $path): array {
    if (!file_exists($path)) return [];
    ls_invalidate_php_cache($path);
    $arr = @require $path;
    return is_array($arr) ? $arr : [];
}

function ls_bool_from_post(string $key): bool {
    return isset($_POST[$key]) && $_POST[$key] === '1';
}

function ls_int_from_post(string $key, int $default = 0): int {
    $v = trim((string)($_POST[$key] ?? ''));
    if ($v === '') return $default;
    if (!preg_match('/^-?\d+$/', $v)) return $default;
    return (int)$v;
}

function ls_str_from_post(string $key, string $default = ''): string {
    $v = (string)($_POST[$key] ?? '');
    $v = trim($v);
    return $v === '' ? $default : $v;
}

function ls_parse_ignore_ips(string $raw): array {
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

function ls_php_value_literal(mixed $v): string {
    if (is_bool($v)) return $v ? 'true' : 'false';
    if (is_int($v)) return (string)$v;
    if (is_float($v)) return (string)$v;
    if (is_array($v)) return var_export($v, true);
    return var_export((string)$v, true);
}

function ls_session_seconds_to_label(int $seconds, string $lang): string {
    if ($seconds <= 0) {
        return $lang === 'ja' ? 'ブラウザを閉じるまで' : 'Until browser is closed';
    }
    $days = (int) floor($seconds / 86400);
    if ($lang === 'ja') {
        return $days . '日';
    }
    return $days . ' day' . ($days === 1 ? '' : 's');
}

/**
 * config/config.php を「既存のコメント/他設定を保持」しつつ更新する
 * - 既存キーがあれば置換（行末コメントも保持）
 * - 無ければ closing '];' の直前に追記
 */
function ls_update_config_php_preserve(string $configDir, string $configPath, array $updates): bool
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
        ls_invalidate_php_cache($configPath);
    }

    $raw = (string)@file_get_contents($configPath);
    if ($raw === '') return false;

    foreach ($updates as $key => $value) {
        $literal = ls_php_value_literal($value);

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
    ls_invalidate_php_cache($configPath);
    return true;
}

/**
 * (1) 保存後の健全性チェック + ロールバック
 * - 保存前の生テキストを保持し、保存後 require で array を確認
 * - 壊れていたら元に戻す
 */
function ls_write_config_with_validation(string $configDir, string $configPath, array $updates, string &$errMsg): bool
{
    $errMsg = '';

    $beforeRaw = file_exists($configPath) ? (string)@file_get_contents($configPath) : '';

    if (!ls_update_config_php_preserve($configDir, $configPath, $updates)) {
        $errMsg = 'write_failed';
        return false;
    }

    ls_invalidate_php_cache($configPath);
    $test = @require $configPath;
    if (!is_array($test)) {
        if ($beforeRaw !== '') {
            @file_put_contents($configPath, $beforeRaw, LOCK_EX);
            @chmod($configPath, 0644);
            ls_invalidate_php_cache($configPath);
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
$auditEvent = '';
$auditContext = [];

$cfg = [];
try {
    $cfg = ls_read_php_config_array($configPath);
} catch (Throwable $e) {
    $cfg = [];
    $err = $t[$lang]['err_read'];
}

// Prefill
$pref = [
    'TIMEZONE' => (string)($cfg['TIMEZONE'] ?? 'Asia/Tokyo'),
    'SESSION_COOKIE_LIFETIME' => (int)($cfg['SESSION_COOKIE_LIFETIME'] ?? 0),

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
$sessionLifetimeOptions = array(
    0 => ($lang === 'ja' ? 'ブラウザを閉じるまで' : 'Until browser is closed'),
    86400 => ($lang === 'ja' ? '1日' : '1 day'),
    604800 => ($lang === 'ja' ? '7日' : '7 days'),
    2592000 => ($lang === 'ja' ? '30日' : '30 days'),
);
$serverGcMaxLifetime = function_exists('nm_server_session_gc_maxlifetime')
    ? (int)nm_server_session_gc_maxlifetime()
    : (int)ini_get('session.gc_maxlifetime');

// --------------------
// Handle POST
// --------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    try {
        nm_csrf_validate_or_die();
    } catch (Throwable $e) {
        $err = $t[$lang]['csrf_invalid'];
    }

    $action = (string)($_POST['_action'] ?? 'save_config');
    if (in_array($action, array('save_mail', 'smtp_test'), true)) {
        $smtpDetailsOpen = true;
    }

    if ($err !== '') {
        // keep current state, just show error
    } elseif (!$canEdit) {
        // (2) 未ログイン POST をサーバ側で弾く（DevTools対策）
        $err = $t[$lang]['err_login'];
        $auditEvent = 'log_settings_update_failed';
        $auditContext = ['username' => $loggedUser, 'dir_user' => $currentDirUser, 'reason' => 'not_logged_in', 'action' => $action];
    } elseif ($action === 'save_mail' || $action === 'smtp_test') {
        $mailUpdates = array(
            'MAIL_TRANSPORT' => ls_str_from_post('MAIL_TRANSPORT', (string)$mailPref['MAIL_TRANSPORT']),
            'SMTP_ENABLED' => ls_bool_from_post('SMTP_ENABLED') ? 1 : 0,
            'SMTP_HOST' => trim((string)($_POST['SMTP_HOST'] ?? '')),
            'SMTP_PORT' => ls_int_from_post('SMTP_PORT', (int)$mailPref['SMTP_PORT']),
            'SMTP_ENCRYPTION' => trim((string)($_POST['SMTP_ENCRYPTION'] ?? $mailPref['SMTP_ENCRYPTION'])),
            'SMTP_AUTH' => ls_bool_from_post('SMTP_AUTH') ? 1 : 0,
            'SMTP_USERNAME' => trim((string)($_POST['SMTP_USERNAME'] ?? '')),
            'SMTP_PASSWORD' => (string)($_POST['SMTP_PASSWORD'] ?? ''),
            'SMTP_FROM' => trim((string)($_POST['SMTP_FROM'] ?? '')),
            'SMTP_FROM_NAME' => trim((string)($_POST['SMTP_FROM_NAME'] ?? '')),
            'SMTP_FALLBACK_TO_MAIL' => ls_bool_from_post('SMTP_FALLBACK_TO_MAIL') ? 1 : 0,
        );

        if ($mailUpdates['SMTP_PASSWORD'] === '') {
            $mailUpdates['SMTP_PASSWORD'] = (string)($mailPref['SMTP_PASSWORD'] ?? '');
        }

        if (!function_exists('nm_save_mail_config') || !nm_save_mail_config($mailUpdates)) {
            $err = $t[$lang]['smtp_save_failed'];
            $auditEvent = 'smtp_settings_save_failed';
            $auditContext = ['username' => $loggedUser, 'dir_user' => $currentDirUser, 'reason' => 'save_failed'];
        } else {
            $mailCfg = function_exists('nm_load_mail_config') ? nm_load_mail_config() : $mailUpdates;
            $mailPref = array(
                'MAIL_TRANSPORT' => (string)($mailCfg['MAIL_TRANSPORT'] ?? 'mail'),
                'SMTP_ENABLED' => !empty($mailCfg['SMTP_ENABLED']),
                'SMTP_HOST' => (string)($mailCfg['SMTP_HOST'] ?? ''),
                'SMTP_PORT' => (int)($mailCfg['SMTP_PORT'] ?? 587),
                'SMTP_ENCRYPTION' => (string)($mailCfg['SMTP_ENCRYPTION'] ?? 'ssl'),
                'SMTP_AUTH' => !empty($mailCfg['SMTP_AUTH']),
                'SMTP_USERNAME' => (string)($mailCfg['SMTP_USERNAME'] ?? ''),
                'SMTP_PASSWORD' => (string)($mailCfg['SMTP_PASSWORD'] ?? ''),
                'SMTP_FROM' => (string)($mailCfg['SMTP_FROM'] ?? ''),
                'SMTP_FROM_NAME' => (string)($mailCfg['SMTP_FROM_NAME'] ?? ''),
                'SMTP_FALLBACK_TO_MAIL' => !empty($mailCfg['SMTP_FALLBACK_TO_MAIL']),
            );
            $msg = $t[$lang]['smtp_saved'];
            $auditEvent = 'smtp_settings_saved';
            $auditContext = ['username' => $loggedUser, 'dir_user' => $currentDirUser, 'transport' => (string)$mailPref['MAIL_TRANSPORT'], 'smtp_enabled' => !empty($mailPref['SMTP_ENABLED'])];

            if ($action === 'smtp_test') {
                $testTo = trim((string)($_POST['SMTP_TEST_TO'] ?? ''));
                if ($testTo === '' || !filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
                    $err = $t[$lang]['smtp_test_invalid_to'];
                    $msg = '';
                    $auditEvent = 'smtp_test_failed';
                    $auditContext = ['username' => $loggedUser, 'dir_user' => $currentDirUser, 'reason' => 'invalid_recipient'];
                } else {
                    $fromForSend = trim((string)($mailPref['SMTP_FROM'] ?? ''));
                    if ($fromForSend === '') {
                        $fromForSend = trim((string)($mailPref['SMTP_USERNAME'] ?? ''));
                    }
                    $mailErr = null;
                    $ok = function_exists('nm_send_mail_common')
                        ? nm_send_mail_common($testTo, $t[$lang]['smtp_test_subject'], $t[$lang]['smtp_test_body'], $fromForSend, $mailErr)
                        : false;
                    if ($ok) {
                        $msg = $t[$lang]['smtp_test_ok'];
                        $auditEvent = 'smtp_test_sent';
                        $auditContext = ['username' => $loggedUser, 'dir_user' => $currentDirUser, 'to_masked' => function_exists('nm_mask_email_for_audit') ? nm_mask_email_for_audit($testTo) : ''];
                    } else {
                        $err = $t[$lang]['smtp_test_ng'];
                        $auditEvent = 'smtp_test_failed';
                        $auditContext = ['username' => $loggedUser, 'dir_user' => $currentDirUser, 'to_masked' => function_exists('nm_mask_email_for_audit') ? nm_mask_email_for_audit($testTo) : '', 'reason' => 'send_failed'];
                        if (is_string($mailErr) && $mailErr !== '') {
                            @error_log('[Notemod SMTP Test] ' . $mailErr);
                        }
                        $msg = '';
                    }
                }
            }
        }
    } else {
        $timezone = ls_str_from_post('TIMEZONE', $pref['TIMEZONE']);
        $ignoreIpsParsed = ls_parse_ignore_ips((string)($_POST['IP_ALERT_IGNORE_IPS'] ?? ''));

        $sessionCookieLifetime = ls_int_from_post('SESSION_COOKIE_LIFETIME', $pref['SESSION_COOKIE_LIFETIME']);
        if (!array_key_exists($sessionCookieLifetime, $sessionLifetimeOptions)) {
            $sessionCookieLifetime = 0;
        }

        $updates = [
            'TIMEZONE' => $timezone,
            'SESSION_COOKIE_LIFETIME' => $sessionCookieLifetime,

            'LOGGER_FILE_ENABLED' => ls_bool_from_post('LOGGER_FILE_ENABLED'),
            'LOGGER_NOTEMOD_ENABLED' => ls_bool_from_post('LOGGER_NOTEMOD_ENABLED'),

            'IP_ALERT_ENABLED' => ls_bool_from_post('IP_ALERT_ENABLED'),
            'IP_ALERT_TO' => (string)($_POST['IP_ALERT_TO'] ?? ''),
            'IP_ALERT_FROM' => (string)($_POST['IP_ALERT_FROM'] ?? ''),
            'IP_ALERT_SUBJECT' => (string)($_POST['IP_ALERT_SUBJECT'] ?? ''),

            'IP_ALERT_IGNORE_BOTS' => ls_bool_from_post('IP_ALERT_IGNORE_BOTS'),

            'LOGGER_FILE_MAX_LINES' => ls_int_from_post('LOGGER_FILE_MAX_LINES', $pref['LOGGER_FILE_MAX_LINES']),
            'LOGGER_NOTEMOD_MAX_LINES' => ls_int_from_post('LOGGER_NOTEMOD_MAX_LINES', $pref['LOGGER_NOTEMOD_MAX_LINES']),
        ];

        if (count($ignoreIpsParsed) > 0) {
            $updates['IP_ALERT_IGNORE_IPS'] = $ignoreIpsParsed;
        }

        $saveErrKey = '';
        if (!ls_write_config_with_validation($configDir, $configPath, $updates, $saveErrKey)) {
            $err = ($saveErrKey === 'broken') ? $t[$lang]['err_broken'] : $t[$lang]['err_write'];
            $auditEvent = 'log_settings_update_failed';
            $auditContext = ['username' => $loggedUser, 'dir_user' => $currentDirUser, 'reason' => $saveErrKey === 'broken' ? 'broken_config' : 'write_failed'];
        } else {
            $msg = $t[$lang]['ok'];
            $auditEvent = 'log_settings_saved';
            $auditContext = ['username' => $loggedUser, 'dir_user' => $currentDirUser, 'timezone' => $timezone, 'session_cookie_lifetime' => $sessionCookieLifetime, 'ip_alert_enabled' => !empty($updates['IP_ALERT_ENABLED'])];

            $cfg = ls_read_php_config_array($configPath);

            $pref['TIMEZONE'] = (string)($cfg['TIMEZONE'] ?? $updates['TIMEZONE']);
            $pref['SESSION_COOKIE_LIFETIME'] = (int)($cfg['SESSION_COOKIE_LIFETIME'] ?? $updates['SESSION_COOKIE_LIFETIME']);

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

if ($auditEvent !== '' && function_exists('nm_write_auth_event')) {
    nm_write_auth_event($auditEvent, $auditContext);
}

// --------------------
// Links (toggles + back)
// --------------------
$u = nm_ui_toggle_urls('/log_settings.php', $lang, $theme);
$backUrl = nm_ui_url('/');
$logoutUrl = nm_ui_url('/logout.php');
$accountUrl = nm_ui_url('/account.php');
$setupauthUrl = nm_ui_url('/setup_auth.php');
$baksettingsUrl = nm_ui_url('/bak_settings.php');
$clipboardsyncUrl = nm_ui_url('/clipboard_sync.php');
$mediafilesUrl = nm_ui_url('/media_files.php');
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
    .head-left{ display:flex; flex-direction:column; gap:4px; }
    .head-right{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
    .title{ font-weight:900; letter-spacing:.3px; font-size:18px; margin:0; }
    .meta{ color:var(--muted); font-size:13px; margin-top:6px; }
    .head-meta{ color:var(--muted); font-size:12px; margin-top:6px; }
    .nav-btn{
      display:inline-flex; align-items:center; gap:8px;
      padding:10px 12px;
      border-radius:999px;
      border:1px solid var(--line);
      background:color-mix(in srgb, var(--card2) 70%, transparent);
      color:var(--text);
      cursor:pointer; text-decoration:none;
      font-size:13px; font-weight:700;
      transition:.15s ease;
      user-select:none;
      white-space:nowrap;
    }
    .nav-btn:hover{ transform:translateY(-1px); border-color: color-mix(in srgb, var(--accent) 38%, var(--line)); text-decoration:none; }
    .nav-btn.red{ border-color: color-mix(in srgb, var(--danger) 35%, var(--line)); color: color-mix(in srgb, var(--danger) 75%, var(--text)); }
    .nav-btn.red:hover{ border-color: color-mix(in srgb, var(--danger) 60%, var(--line)); }
    .toggles-inline{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .toggle-row{ display:flex; align-items:center; gap:8px; }
    .toggle-row span{ font-size:12px; color:var(--muted); }
    .head-pill{
      display:inline-flex; gap:10px; align-items:center; flex-wrap:wrap;
      padding:10px 12px;
      border-radius:999px;
      border:1px solid var(--line);
      background:color-mix(in srgb, var(--card2) 75%, transparent);
      font-size:13px;
    }
    .head-pill a{
      text-decoration:none; color:var(--muted);
      font-weight:800; font-size:12px;
      padding:6px 8px; border-radius:999px;
      border:1px solid transparent;
      white-space:nowrap;
    }
    .head-pill a.active{
      color:var(--text);
      border-color: color-mix(in srgb, var(--accent) 45%, var(--line));
      background: color-mix(in srgb, var(--accent) 12%, transparent);
    }

    .body{ padding:16px 18px 18px; display:grid; gap:14px; }
    .box{
      border:1px solid color-mix(in srgb, var(--line) 120%, transparent);
      border-radius:16px;
      padding:14px;
      background:var(--card2);
    }
    h3{ margin:0 0 10px; font-size:14px; }
    label{ display:block; font-size:12px; color:var(--muted); margin:10px 0 6px; }
    input, textarea, select{
      width:100%;
      padding:12px 12px;
      border-radius:14px;
      border:1px solid color-mix(in srgb, var(--line) 140%, transparent);
      background: color-mix(in srgb, var(--card2) 85%, transparent);
      color:var(--text);
      outline:none;
    }
    textarea{ min-height: 64px; resize: vertical; }
    input:focus, textarea:focus, select:focus{
      border-color: color-mix(in srgb, var(--accent) 70%, transparent);
      box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 14%, transparent);
    }
    select{
      appearance:auto;
      -webkit-appearance:menulist;
      -moz-appearance:menulist;
    }
    select option,
    select optgroup{
      background-color: color-mix(in srgb, var(--bg1) 92%, #000 8%);
      color: var(--text);
    }
    html[data-theme="light"] select option,
    html[data-theme="light"] select optgroup{
      background-color: #ffffff;
      color: var(--text);
    }
    html[data-theme="dark"] select option,
    html[data-theme="dark"] select optgroup{
      background-color: #0b1222;
      color: var(--text);
    }
    input[disabled], textarea[disabled], select[disabled]{ opacity:.75; cursor:not-allowed; }
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
    .btn-inline{ width:auto; margin-top:0; white-space:nowrap; padding:12px 14px; }
    .field-inline{ display:flex; gap:10px; align-items:center; }
    .field-inline input{ flex:1 1 auto; }
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
      .head{ padding-right: 18px; }
    }

    .row-links{ display:flex; gap:12px; flex-wrap:wrap; padding: 20px 10px;}
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
    details.smtp-panel{
      border:1px solid color-mix(in srgb, var(--line) 120%, transparent);
      border-radius:18px;
      background: color-mix(in srgb, var(--card2) 70%, transparent);
      overflow:hidden;
      transition: border-color .18s ease, box-shadow .18s ease, background .18s ease, transform .18s ease;
    }
    details.smtp-panel > summary{
      list-style:none;
      cursor:pointer;
      padding:16px 18px;
      font-weight:900;
      user-select:none;
      outline:none;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      background: linear-gradient(180deg, color-mix(in srgb, var(--accent) 8%, transparent), transparent);
    }
    details.smtp-panel > summary::after{
      content:'＋';
      display:inline-flex;
      align-items:center;
      justify-content:center;
      width:28px;
      height:28px;
      border-radius:999px;
      border:1px solid color-mix(in srgb, var(--accent) 35%, var(--line));
      background: color-mix(in srgb, var(--accent) 10%, transparent);
      color:var(--accent);
      font-size:18px;
      line-height:1;
      font-weight:900;
      flex:0 0 auto;
    }
    details.smtp-panel > summary::-webkit-details-marker{ display:none; }
    details.smtp-panel[open]{
      border-color: color-mix(in srgb, var(--accent) 55%, var(--line));
      background:
        linear-gradient(180deg, color-mix(in srgb, var(--accent) 10%, transparent), transparent 120px),
        color-mix(in srgb, var(--card2) 92%, transparent);
      box-shadow: 0 14px 34px color-mix(in srgb, var(--accent) 16%, transparent);
      transform: translateY(-1px);
    }
    details.smtp-panel[open] > summary{
      border-bottom:1px solid color-mix(in srgb, var(--accent) 28%, var(--line));
      background: linear-gradient(180deg, color-mix(in srgb, var(--accent) 20%, transparent), color-mix(in srgb, var(--accent) 6%, transparent));
    }
    details.smtp-panel[open] > summary::after{
      content:'−';
      background: color-mix(in srgb, var(--accent) 18%, transparent);
      border-color: color-mix(in srgb, var(--accent) 55%, var(--line));
      color: color-mix(in srgb, var(--accent) 85%, var(--text));
    }
    .smtp-inner{
      padding:16px;
      display:grid;
      gap:16px;
      background: linear-gradient(180deg, color-mix(in srgb, var(--accent) 7%, transparent), transparent 180px);
    }
    .smtp-group{
      border:1px solid color-mix(in srgb, var(--accent) 20%, var(--line));
      border-radius:16px;
      padding:14px;
      background: color-mix(in srgb, var(--card) 82%, transparent);
      box-shadow: inset 0 1px 0 color-mix(in srgb, #fff 4%, transparent);
    }
    .smtp-group h4{
      margin:0 0 10px;
      font-size:13px;
      letter-spacing:.2px;
      color: color-mix(in srgb, var(--accent) 65%, var(--text));
    }
    .smtp-lead{
      margin:0 0 10px;
      font-size:12px;
      color:var(--muted);
    }
    .field-inline{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .field-inline input{ flex:1 1 320px; }
    .mini-btn{ white-space:nowrap; width:auto; min-width:180px; margin-top:0; }
    .smtp-accent-note{
      font-size:12px;
      color: color-mix(in srgb, var(--accent) 70%, var(--text));
      margin-top:8px;
    }
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
        <div class="head-left">
          <h1 class="title"><?=htmlspecialchars($t[$lang]['title'], ENT_QUOTES, 'UTF-8')?></h1>
          <?php if ($isLoggedIn && $loggedUser !== ''): ?>
            <div class="head-meta" style="white-space: nowrap;"><?=htmlspecialchars($t[$lang]['logged_as'], ENT_QUOTES, 'UTF-8')?> <b><?=htmlspecialchars($loggedUser, ENT_QUOTES, 'UTF-8')?></b> &nbsp; | &nbsp; <?=htmlspecialchars($t[$lang]['storage_user'], ENT_QUOTES, 'UTF-8')?> <b><?=htmlspecialchars($currentDirUser !== '' ? $currentDirUser : normalize_username((string)$loggedUser), ENT_QUOTES, 'UTF-8')?></b></div>
          <?php endif; ?>
          <div class="meta"><?=htmlspecialchars($t[$lang]['desc'], ENT_QUOTES, 'UTF-8')?></div>
        </div>

        <div class="head-right">
          <a class="nav-btn" href="<?=htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8')?>">← <?=htmlspecialchars($t[$lang]['back'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="nav-btn red" href="<?=htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['logout'], ENT_QUOTES, 'UTF-8')?></a>

          <div class="toggles-inline">
            <div class="toggle-row">
              <span><?=htmlspecialchars($t[$lang]['lang_label'], ENT_QUOTES, 'UTF-8')?></span>
              <div class="head-pill">
                <a href="<?=htmlspecialchars($u['langJa'], ENT_QUOTES, 'UTF-8')?>" class="<?= $lang==='ja'?'active':'' ?>">JP</a>
                <a href="<?=htmlspecialchars($u['langEn'], ENT_QUOTES, 'UTF-8')?>" class="<?= $lang==='en'?'active':'' ?>">EN</a>
              </div>
            </div>

            <div class="toggle-row">
              <span><?=htmlspecialchars($t[$lang]['theme_label'], ENT_QUOTES, 'UTF-8')?></span>
              <div class="head-pill">
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

        <?php if (!$canEdit): ?>
          <div class="notice bad"><?=htmlspecialchars($t[$lang]['err_login'], ENT_QUOTES, 'UTF-8')?></div>
        <?php endif; ?>

        <form method="post">
          <?= nm_csrf_input_html() ?>

          <div class="box">
            <h3><?=htmlspecialchars($t[$lang]['section_general'], ENT_QUOTES, 'UTF-8')?></h3>

            <label><?=htmlspecialchars($t[$lang]['timezone'], ENT_QUOTES, 'UTF-8')?></label>
            <input name="TIMEZONE"
                   value="<?=htmlspecialchars($pref['TIMEZONE'], ENT_QUOTES, 'UTF-8')?>"
                   <?= $canEdit ? '' : 'disabled' ?>>
          </div>

          <div class="box">
            <h3><?=htmlspecialchars($t[$lang]['section_session'], ENT_QUOTES, 'UTF-8')?></h3>

            <label><?=htmlspecialchars($t[$lang]['session_cookie_lifetime'], ENT_QUOTES, 'UTF-8')?></label>
            <select name="SESSION_COOKIE_LIFETIME" <?= $canEdit ? '' : 'disabled' ?>>
              <?php foreach ($sessionLifetimeOptions as $optValue => $optLabel): ?>
                <option value="<?=htmlspecialchars((string)$optValue, ENT_QUOTES, 'UTF-8')?>"
                        <?= ((int)$pref['SESSION_COOKIE_LIFETIME'] === (int)$optValue) ? 'selected' : '' ?>>
                  <?=htmlspecialchars((string)$optLabel, ENT_QUOTES, 'UTF-8')?>
                </option>
              <?php endforeach; ?>
            </select>

            <div class="notice" style="margin-top:10px;">
              <?=htmlspecialchars($t[$lang]['session_server_gc'], ENT_QUOTES, 'UTF-8')?><br>
              <b><?=htmlspecialchars($t[$lang]['session_server_gc_value'], ENT_QUOTES, 'UTF-8')?>:</b>
              <?=htmlspecialchars((string)$serverGcMaxLifetime, ENT_QUOTES, 'UTF-8')?> sec
              (<?=htmlspecialchars(ls_session_seconds_to_label((int)$serverGcMaxLifetime, $lang), ENT_QUOTES, 'UTF-8')?>)
            </div>

            <div class="notice" style="margin-top:10px;">
              <?=htmlspecialchars($t[$lang]['session_warning'], ENT_QUOTES, 'UTF-8')?>
            </div>
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
            <div class="field-inline">
              <input id="ip_alert_to_input" name="IP_ALERT_TO"
                     value="<?=htmlspecialchars($pref['IP_ALERT_TO'], ENT_QUOTES, 'UTF-8')?>"
                     <?= $canEdit ? '' : 'disabled' ?>>
              <button class="btn btn-inline" type="button"
                      id="use_auth_email_btn"
                      data-auth-email="<?=htmlspecialchars($authEmail, ENT_QUOTES, 'UTF-8')?>"
                      <?= ($canEdit && $authEmailAvailable) ? '' : 'disabled' ?>><?=htmlspecialchars($t[$lang]['use_auth_email'], ENT_QUOTES, 'UTF-8')?></button>
            </div>
            <?php if (!$authEmailAvailable): ?>
              <div class="notice" style="margin-top:10px;"><?=htmlspecialchars($t[$lang]['auth_email_not_set'], ENT_QUOTES, 'UTF-8')?></div>
            <?php endif; ?>

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

            <button class="btn" type="submit" name="_action" value="save_config" <?= $canEdit ? '' : 'disabled' ?>><?=htmlspecialchars($t[$lang]['btn'], ENT_QUOTES, 'UTF-8')?></button>
          </div>

            <details class="smtp-panel" <?= $smtpDetailsOpen ? 'open' : '' ?>>
              <summary><?=htmlspecialchars($t[$lang]['show_smtp'], ENT_QUOTES, 'UTF-8')?></summary>
              <div class="smtp-inner">
                <div class="smtp-group">
                  <h3><?=htmlspecialchars($t[$lang]['section_smtp'], ENT_QUOTES, 'UTF-8')?></h3>
                  <p class="smtp-lead"><?= htmlspecialchars($lang === 'ja' ? '普段は閉じておけますが、開いた時は SMTP 関連の設定とテスト送信をまとめて確認できます。' : 'You can keep this closed normally. When opened, all SMTP settings and the test-send tools are grouped here for quick review.', ENT_QUOTES, 'UTF-8') ?></p>

                  <label><?=htmlspecialchars($t[$lang]['mail_transport'], ENT_QUOTES, 'UTF-8')?></label>
                  <select name="MAIL_TRANSPORT" <?= $canEdit ? '' : 'disabled' ?>>
                    <option value="mail" <?= ((string)$mailPref['MAIL_TRANSPORT'] === 'mail') ? 'selected' : '' ?>><?=htmlspecialchars($t[$lang]['mail_transport_mail'], ENT_QUOTES, 'UTF-8')?></option>
                    <option value="smtp" <?= ((string)$mailPref['MAIL_TRANSPORT'] === 'smtp') ? 'selected' : '' ?>><?=htmlspecialchars($t[$lang]['mail_transport_smtp'], ENT_QUOTES, 'UTF-8')?></option>
                  </select>

                  <label class="check">
                    <input type="checkbox" name="SMTP_ENABLED" value="1" <?= !empty($mailPref['SMTP_ENABLED']) ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                    <span><?=htmlspecialchars($t[$lang]['smtp_enabled'], ENT_QUOTES, 'UTF-8')?></span>
                  </label>

                  <label><?=htmlspecialchars($t[$lang]['smtp_host'], ENT_QUOTES, 'UTF-8')?></label>
                  <input name="SMTP_HOST" value="<?=htmlspecialchars((string)$mailPref['SMTP_HOST'], ENT_QUOTES, 'UTF-8')?>" <?= $canEdit ? '' : 'disabled' ?>>

                  <label><?=htmlspecialchars($t[$lang]['smtp_port'], ENT_QUOTES, 'UTF-8')?></label>
                  <input type="number" min="1" step="1" name="SMTP_PORT" value="<?=htmlspecialchars((string)$mailPref['SMTP_PORT'], ENT_QUOTES, 'UTF-8')?>" <?= $canEdit ? '' : 'disabled' ?>>

                  <label><?=htmlspecialchars($t[$lang]['smtp_encryption'], ENT_QUOTES, 'UTF-8')?></label>
                  <select name="SMTP_ENCRYPTION" <?= $canEdit ? '' : 'disabled' ?>>
                    <option value="" <?= ((string)$mailPref['SMTP_ENCRYPTION'] === '') ? 'selected' : '' ?>><?=htmlspecialchars($t[$lang]['smtp_encryption_none'], ENT_QUOTES, 'UTF-8')?></option>
                    <option value="tls" <?= ((string)$mailPref['SMTP_ENCRYPTION'] === 'tls') ? 'selected' : '' ?>><?=htmlspecialchars($t[$lang]['smtp_encryption_tls'], ENT_QUOTES, 'UTF-8')?></option>
                    <option value="ssl" <?= ((string)$mailPref['SMTP_ENCRYPTION'] === 'ssl') ? 'selected' : '' ?>><?=htmlspecialchars($t[$lang]['smtp_encryption_ssl'], ENT_QUOTES, 'UTF-8')?></option>
                  </select>

                  <label class="check">
                    <input type="checkbox" name="SMTP_AUTH" value="1" <?= !empty($mailPref['SMTP_AUTH']) ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                    <span><?=htmlspecialchars($t[$lang]['smtp_auth'], ENT_QUOTES, 'UTF-8')?></span>
                  </label>

                  <label><?=htmlspecialchars($t[$lang]['smtp_username'], ENT_QUOTES, 'UTF-8')?></label>
                  <input name="SMTP_USERNAME" value="<?=htmlspecialchars((string)$mailPref['SMTP_USERNAME'], ENT_QUOTES, 'UTF-8')?>" <?= $canEdit ? '' : 'disabled' ?>>

                  <label><?=htmlspecialchars($t[$lang]['smtp_password'], ENT_QUOTES, 'UTF-8')?></label>
                  <input type="password" name="SMTP_PASSWORD" value="" autocomplete="new-password" placeholder="<?= htmlspecialchars($lang === 'ja' ? '変更する時だけ入力。空欄なら現在のパスワードを保持' : 'Enter only to change. Leave blank to keep the current password.', ENT_QUOTES, 'UTF-8') ?>" <?= $canEdit ? '' : 'disabled' ?>>

                  <label><?=htmlspecialchars($t[$lang]['smtp_from'], ENT_QUOTES, 'UTF-8')?></label>
                  <input name="SMTP_FROM" value="<?=htmlspecialchars((string)$mailPref['SMTP_FROM'], ENT_QUOTES, 'UTF-8')?>" <?= $canEdit ? '' : 'disabled' ?>>

                  <label><?=htmlspecialchars($t[$lang]['smtp_from_name'], ENT_QUOTES, 'UTF-8')?></label>
                  <input name="SMTP_FROM_NAME" value="<?=htmlspecialchars((string)$mailPref['SMTP_FROM_NAME'], ENT_QUOTES, 'UTF-8')?>" <?= $canEdit ? '' : 'disabled' ?>>

                  <label class="check">
                    <input type="checkbox" name="SMTP_FALLBACK_TO_MAIL" value="1" <?= !empty($mailPref['SMTP_FALLBACK_TO_MAIL']) ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                    <span><?=htmlspecialchars($t[$lang]['smtp_fallback'], ENT_QUOTES, 'UTF-8')?></span>
                  </label>

                  <div class="smtp-accent-note"><?= htmlspecialchars($lang === 'ja' ? 'SMTP_FROM が空欄の場合は、SMTP_USERNAME を送信元として使用します。' : 'If SMTP_FROM is blank, SMTP_USERNAME is used as the sender address.', ENT_QUOTES, 'UTF-8') ?></div>

                  <div class="field-inline" style="margin-top:12px;">
                    <button class="btn mini-btn" type="submit" name="_action" value="save_mail" <?= $canEdit ? '' : 'disabled' ?>><?=htmlspecialchars($t[$lang]['btn'], ENT_QUOTES, 'UTF-8')?></button>
                  </div>
                </div>

                <div class="smtp-group">
                  <h4><?= htmlspecialchars($lang === 'ja' ? 'SMTPテスト送信' : 'SMTP test send', ENT_QUOTES, 'UTF-8') ?></h4>
                  <p class="smtp-lead"><?= htmlspecialchars($lang === 'ja' ? '保存済みの config/mail.php 設定を使ってテストメールを送信します。' : 'Sends a test message using the currently saved config/mail.php settings.', ENT_QUOTES, 'UTF-8') ?></p>
                  <label><?=htmlspecialchars($t[$lang]['smtp_test_to'], ENT_QUOTES, 'UTF-8')?></label>
                  <div class="field-inline">
                    <input type="email" name="SMTP_TEST_TO" value="<?=htmlspecialchars((string)($_POST['SMTP_TEST_TO'] ?? ''), ENT_QUOTES, 'UTF-8')?>" <?= $canEdit ? '' : 'disabled' ?>>
                    <button class="btn mini-btn" type="submit" name="_action" value="smtp_test" <?= $canEdit ? '' : 'disabled' ?>><?=htmlspecialchars($t[$lang]['smtp_test_btn'], ENT_QUOTES, 'UTF-8')?></button>
                  </div>
                </div>
              </div>
            </details>

        </form>

        <div class="row-links">
          <a class="nav-btn" href="<?=htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8')?>">← <?=htmlspecialchars($t[$lang]['back'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="nav-btn red" href="<?=htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['logout'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="nav-btn" href="<?=htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_account'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="nav-btn" href="<?=htmlspecialchars($setupauthUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_setup_auth'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="nav-btn" href="<?=htmlspecialchars($baksettingsUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_bak_settings'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="nav-btn" href="<?=htmlspecialchars($clipboardsyncUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_clipboard_sync'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="nav-btn" href="<?=htmlspecialchars($mediafilesUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['go_media_files'], ENT_QUOTES, 'UTF-8')?></a>
        </div>

      </div>
    </div>
  </div>

<script>
(function(){
  var btn = document.getElementById('use_auth_email_btn');
  var input = document.getElementById('ip_alert_to_input');
  if (!btn || !input) return;
  btn.addEventListener('click', function(){
    if (btn.disabled) return;
    var email = (btn.getAttribute('data-auth-email') || '').trim();
    if (!email) return;
    input.value = email;
    try { input.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
    try { input.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) {}
    input.focus();
  });
})();
</script>
</body>
</html>