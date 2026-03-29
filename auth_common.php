<?php
declare(strict_types=1);

// ==============================
// Username / DIR_USER helpers
// ==============================

/**
 * ログイン名・DIR_USER 用の正規化
 * - 小文字化
 * - 前後空白除去
 * - a-z / 0-9 / _ / - のみ許可
 */
function normalize_username(string $username): string
{
    $username = trim($username);
    $username = strtolower($username);
    $username = preg_replace('/[^a-z0-9_-]/', '', $username) ?? '';
    return $username;
}

// ==============================
// URL / Cookie helpers
// ==============================

/**
 * Notemod の設置パス（Cookie Path 用）
 * 例:
 *  - /notemod/login.php      -> /notemod/
 *  - /notemod/setup_auth.php -> /notemod/
 *  - /index.php              -> /
 */
function nm_auth_cookie_path(): string
{
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/');
    $script = str_replace('\\', '/', $script);

    $dir = rtrim(dirname($script), '/');
    if ($dir === '' || $dir === '.') {
        $dir = '/';
    }

    if ($dir !== '/') {
        $dir .= '/';
    }
    return $dir;
}

/**
 * Base URL（/notemod の部分だけ）を返す
 */
function nm_auth_base_url(): string
{
    $p = nm_auth_cookie_path();
    return rtrim($p, '/');
}

/**
 * index.php が / と /notemod/ のどちらでも動くための base path
 */
function nm_base_path(): string
{
    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $dir = str_replace('\\', '/', dirname($scriptName));
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        return '';
    }
    return rtrim($dir, '/');
}

function nm_url(string $path = ''): string
{
    $base = nm_base_path();
    return $base . '/' . ltrim($path, '/');
}

// ==============================
// Session / Config helpers
// ==============================

/**
 * セッション開始前でも安全に推定できる DIR_USER
 * 優先順位:
 * 1. 明示引数
 * 2. 補助Cookie nm_dir_user
 * 3. リクエストの user / username / dir_user
 */
function nm_guess_dir_user_for_cookie(?string $dirUser = null): string
{
    if ($dirUser !== null && $dirUser !== '') {
        return normalize_username($dirUser);
    }

    $cookieDirUser = normalize_username((string)($_COOKIE['nm_dir_user'] ?? ''));
    if ($cookieDirUser !== '') {
        return $cookieDirUser;
    }

    foreach (['dir_user', 'user', 'username'] as $key) {
        if (isset($_REQUEST[$key])) {
            $v = normalize_username((string)$_REQUEST[$key]);
            if ($v !== '') {
                return $v;
            }
        }
    }

    return '';
}

if (!function_exists('nm_read_php_config_array')) {
function nm_read_php_config_array(string $configPath): array
{
    if (!is_file($configPath)) {
        return [];
    }

    $cfg = require $configPath;
    return is_array($cfg) ? $cfg : [];
}
}


if (!function_exists('nm_read_common_config_for_dir_user')) {
function nm_read_common_config_for_dir_user(?string $dirUser = null): array
{
    $dirUser = nm_guess_dir_user_for_cookie($dirUser);
    if ($dirUser === '') {
        return [];
    }

    $configPath = __DIR__ . '/config/' . $dirUser . '/config.php';
    return nm_read_php_config_array($configPath);
}
}


if (!function_exists('nm_session_cookie_lifetime_value')) {
function nm_session_cookie_lifetime_value(?string $dirUser = null): int
{
    $cfg = nm_read_common_config_for_dir_user($dirUser);
    $value = $cfg['SESSION_COOKIE_LIFETIME'] ?? 0;

    if (is_string($value) && ctype_digit($value)) {
        $value = (int)$value;
    }

    if (!is_int($value) || $value < 0) {
        $value = 0;
    }

    return $value;
}
}


if (!function_exists('nm_server_session_gc_maxlifetime')) {
function nm_server_session_gc_maxlifetime(): int
{
    $v = ini_get('session.gc_maxlifetime');
    if ($v === false || $v === null || $v === '') {
        return 0;
    }
    return max(0, (int)$v);
}
}


if (!function_exists('nm_effective_session_cookie_lifetime')) {
function nm_effective_session_cookie_lifetime(?string $dirUser = null): int
{
    $cookieLifetime = nm_session_cookie_lifetime_value($dirUser);
    if ($cookieLifetime <= 0) {
        return 0;
    }

    $gc = nm_server_session_gc_maxlifetime();
    if ($gc > 0 && $gc < $cookieLifetime) {
        return $gc;
    }

    return $cookieLifetime;
}
}


if (!function_exists('nm_session_cookie_lifetime_options')) {
function nm_session_cookie_lifetime_options(): array
{
    return [
        0 => 'ブラウザを閉じるまで',
        86400 => '1日',
        604800 => '7日',
        2592000 => '30日',
    ];
}
}


if (!function_exists('nm_refresh_dir_user_cookie')) {
function nm_refresh_dir_user_cookie(?string $dirUser = null, ?int $lifetime = null): void
{
    $dirUser = normalize_username((string)$dirUser);
    if ($dirUser === '') {
        return;
    }

    $cookiePath = nm_auth_cookie_path();
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $lifetime = $lifetime ?? nm_session_cookie_lifetime_value($dirUser);
    $expires = $lifetime > 0 ? (time() + $lifetime) : 0;

    if (PHP_VERSION_ID >= 70300) {
        setcookie('nm_dir_user', $dirUser, [
            'expires' => $expires,
            'path' => $cookiePath,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        setcookie('nm_dir_user', $dirUser, $expires, $cookiePath . '; samesite=Lax', '', $secure, true);
    }

    $_COOKIE['nm_dir_user'] = $dirUser;
}
}


function nm_clear_dir_user_cookie(): void
{
    $cookiePath = nm_auth_cookie_path();
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    if (PHP_VERSION_ID >= 70300) {
        setcookie('nm_dir_user', '', [
            'expires' => time() - 3600,
            'path' => $cookiePath,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        setcookie('nm_dir_user', '', time() - 3600, $cookiePath . '; samesite=Lax', '', $secure, true);
    }

    unset($_COOKIE['nm_dir_user']);
}

// ==============================
// Session helpers
// ==============================

function nm_auth_start_session(?string $dirUser = null): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    @ini_set('session.use_strict_mode', '1');

    $cookiePath = nm_auth_cookie_path();
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $lifetime = nm_session_cookie_lifetime_value($dirUser);

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $cookiePath,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params($lifetime, $cookiePath . '; samesite=Lax', '', $secure, true);
    }

    @session_start();

    $sessionDirUser = normalize_username((string)($_SESSION['nm_dir_user'] ?? ''));
    if ($sessionDirUser !== '') {
        nm_refresh_dir_user_cookie($sessionDirUser, $lifetime);
    }
}

// ==============================
// Current user helpers
// ==============================

/**
 * 現在の DIR_USER を推定して返す
 * 優先順位:
 * 1. 明示引数
 * 2. セッションの nm_dir_user
 * 3. リクエストの user / username / dir_user
 */
function nm_get_current_dir_user(?string $dirUser = null): string
{
    if ($dirUser !== null && $dirUser !== '') {
        return normalize_username($dirUser);
    }

    nm_auth_start_session($dirUser);

    $s = (string)($_SESSION['nm_dir_user'] ?? '');
    if ($s !== '') {
        return normalize_username($s);
    }

    foreach (['dir_user', 'user', 'username'] as $key) {
        if (isset($_REQUEST[$key])) {
            $v = normalize_username((string)$_REQUEST[$key]);
            if ($v !== '') {
                return $v;
            }
        }
    }

    return '';
}

/**
 * 現在の表示用ユーザー名（USERNAME）を返す
 */
function nm_get_current_user(): string
{
    nm_auth_start_session();

    $u = (string)($_SESSION['nm_username'] ?? $_SESSION['nm_login_user'] ?? $_SESSION['nm_user'] ?? '');
    if ($u !== '') {
        return $u;
    }

    $dirUser = (string)($_SESSION['nm_dir_user'] ?? '');
    if ($dirUser !== '') {
        $cfg = nm_auth_load($dirUser);
        if (is_array($cfg) && !empty($cfg['USERNAME'])) {
            $u = (string)$cfg['USERNAME'];
            $_SESSION['nm_username'] = $u;
            return $u;
        }
    }

    return '';
}

// ==============================
// Path helpers
// ==============================

function nm_resolve_effective_dir_user(?string $dirUser = null): string
{
    if (is_string($dirUser) && $dirUser !== '') {
        $resolved = normalize_username($dirUser);
        if ($resolved !== '') return $resolved;
    }

    $currentDir = nm_get_current_dir_user();
    if ($currentDir !== '') {
        $resolved = normalize_username($currentDir);
        if ($resolved !== '') return $resolved;
    }

    $currentUser = nm_get_current_user();
    if ($currentUser !== '') {
        $resolved = normalize_username($currentUser);
        if ($resolved !== '') return $resolved;
    }

    foreach (['dir_user', 'user', 'username'] as $key) {
        if (isset($_REQUEST[$key]) && (string)$_REQUEST[$key] !== '') {
            $resolved = normalize_username((string)$_REQUEST[$key]);
            if ($resolved !== '') return $resolved;
        }
    }

    return 'default';
}


function nm_config_dir(?string $dirUser = null): string
{
    $dirUser = nm_resolve_effective_dir_user($dirUser);
    return __DIR__ . '/config/' . $dirUser;
}

function nm_data_dir(?string $dirUser = null): string
{
    $dirUser = nm_resolve_effective_dir_user($dirUser);
    return __DIR__ . '/notemod-data/' . $dirUser;
}

function nm_logs_dir(?string $dirUser = null): string
{
    $dirUser = nm_resolve_effective_dir_user($dirUser);
    return __DIR__ . '/logs/' . $dirUser;
}

// 互換名（マルチユーザー版寄せ）
function nm_user_config_dir(?string $dirUser = null): string
{
    return nm_config_dir($dirUser);
}

function nm_user_data_dir(?string $dirUser = null): string
{
    return nm_data_dir($dirUser);
}

function nm_user_logs_dir(?string $dirUser = null): string
{
    return nm_logs_dir($dirUser);
}

function nm_auth_config_path(?string $dirUser = null): string
{
    $dirUser = nm_resolve_effective_dir_user($dirUser);
    return nm_config_dir($dirUser) . '/auth.php';
}

function nm_config_path(?string $dirUser = null): string
{
    $dirUser = nm_resolve_effective_dir_user($dirUser);
    return nm_config_dir($dirUser) . '/config.php';
}

function nm_api_config_path(?string $dirUser = null): string
{
    $dirUser = nm_resolve_effective_dir_user($dirUser);
    return nm_config_dir($dirUser) . '/config.api.php';
}


// ==============================
// Common mail config / send helpers
// ==============================

function nm_mail_config_path(): string
{
    return __DIR__ . '/config/mail.php';
}

function nm_mail_default_config(): array
{
    return array(
        'MAIL_TRANSPORT' => 'mail',
        'SMTP_ENABLED' => 0,
        'SMTP_HOST' => '',
        'SMTP_PORT' => 587,
        'SMTP_ENCRYPTION' => 'tls',
        'SMTP_AUTH' => 1,
        'SMTP_USERNAME' => '',
        'SMTP_PASSWORD' => '',
        'SMTP_FROM' => '',
        'SMTP_FROM_NAME' => '',
        'SMTP_FALLBACK_TO_MAIL' => 0,
        'UPDATED_AT' => '',
    );
}

function nm_load_mail_config(): array
{
    $path = nm_mail_config_path();
    $cfg = nm_mail_default_config();

    if (!is_file($path)) {
        return $cfg;
    }

    $loaded = require $path;
    if (!is_array($loaded)) {
        return $cfg;
    }

    $cfg = array_merge($cfg, $loaded);
    $cfg['MAIL_TRANSPORT'] = strtolower(trim((string)($cfg['MAIL_TRANSPORT'] ?? 'mail')));
    if (!in_array($cfg['MAIL_TRANSPORT'], array('mail', 'smtp'), true)) {
        $cfg['MAIL_TRANSPORT'] = 'mail';
    }

    $cfg['SMTP_ENABLED'] = !empty($cfg['SMTP_ENABLED']) ? 1 : 0;
    $cfg['SMTP_PORT'] = (int)($cfg['SMTP_PORT'] ?? 0);
    if ($cfg['SMTP_PORT'] <= 0) {
        $cfg['SMTP_PORT'] = 587;
    }

    $enc = strtolower(trim((string)($cfg['SMTP_ENCRYPTION'] ?? '')));
    if (!in_array($enc, array('', 'tls', 'ssl'), true)) {
        $enc = '';
    }
    $cfg['SMTP_ENCRYPTION'] = $enc;
    $cfg['SMTP_AUTH'] = !empty($cfg['SMTP_AUTH']) ? 1 : 0;
    $cfg['SMTP_FALLBACK_TO_MAIL'] = !empty($cfg['SMTP_FALLBACK_TO_MAIL']) ? 1 : 0;
    $cfg['SMTP_HOST'] = trim((string)($cfg['SMTP_HOST'] ?? ''));
    $cfg['SMTP_USERNAME'] = trim((string)($cfg['SMTP_USERNAME'] ?? ''));
    $cfg['SMTP_PASSWORD'] = (string)($cfg['SMTP_PASSWORD'] ?? '');
    $cfg['SMTP_FROM'] = trim((string)($cfg['SMTP_FROM'] ?? ''));
    $cfg['SMTP_FROM_NAME'] = trim((string)($cfg['SMTP_FROM_NAME'] ?? ''));
    $cfg['UPDATED_AT'] = trim((string)($cfg['UPDATED_AT'] ?? ''));

    return $cfg;
}

function nm_save_mail_config(array $config): bool
{
    $path = nm_mail_config_path();
    $dir = dirname($path);

    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return false;
    }

    $data = array_merge(nm_mail_default_config(), $config);
    $data['MAIL_TRANSPORT'] = strtolower(trim((string)($data['MAIL_TRANSPORT'] ?? 'mail')));
    if (!in_array($data['MAIL_TRANSPORT'], array('mail', 'smtp'), true)) {
        $data['MAIL_TRANSPORT'] = 'mail';
    }
    $data['SMTP_ENABLED'] = !empty($data['SMTP_ENABLED']) ? 1 : 0;
    $data['SMTP_PORT'] = (int)($data['SMTP_PORT'] ?? 587);
    if ($data['SMTP_PORT'] <= 0) {
        $data['SMTP_PORT'] = 587;
    }
    $enc = strtolower(trim((string)($data['SMTP_ENCRYPTION'] ?? '')));
    if (!in_array($enc, array('', 'tls', 'ssl'), true)) {
        $enc = '';
    }
    $data['SMTP_ENCRYPTION'] = $enc;
    $data['SMTP_AUTH'] = !empty($data['SMTP_AUTH']) ? 1 : 0;
    $data['SMTP_HOST'] = trim((string)($data['SMTP_HOST'] ?? ''));
    $data['SMTP_USERNAME'] = trim((string)($data['SMTP_USERNAME'] ?? ''));
    $data['SMTP_PASSWORD'] = (string)($data['SMTP_PASSWORD'] ?? '');
    $data['SMTP_FROM'] = trim((string)($data['SMTP_FROM'] ?? ''));
    $data['SMTP_FROM_NAME'] = trim((string)($data['SMTP_FROM_NAME'] ?? ''));
    $data['SMTP_FALLBACK_TO_MAIL'] = !empty($data['SMTP_FALLBACK_TO_MAIL']) ? 1 : 0;
    $data['UPDATED_AT'] = gmdate('c');

    $php = "<?php\nreturn " . var_export($data, true) . ";\n";

    try {
        $suffix = bin2hex(random_bytes(4));
    } catch (Throwable $e) {
        $suffix = dechex(mt_rand());
    }

    $tmp = $path . '.tmp-' . $suffix;
    if (@file_put_contents($tmp, $php, LOCK_EX) === false) {
        return false;
    }
    @chmod($tmp, 0644);

    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }

    @chmod($path, 0644);
    return true;
}

function nm_build_mail_headers($from = ''): string
{
    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
    );

    $from = trim((string)$from);
    if ($from !== '') {
        $headers[] = 'From: ' . $from;
    }

    return implode("\r\n", $headers);
}

function nm_send_mail_php_mail($to, $subject, $body, $from = '', &$errorMessage = null): bool
{
    $to = trim((string)$to);
    $subject = (string)$subject;
    $body = (string)$body;
    $from = trim((string)$from);

    if ($to === '') {
        $errorMessage = 'Empty recipient.';
        return false;
    }

    $headers = nm_build_mail_headers($from);
    $ok = @mail($to, $subject, $body, $headers);

    if (!$ok) {
        $errorMessage = 'mail() failed.';
        return false;
    }

    return true;
}

function nm_encode_mail_header_utf8(string $value): string
{
    if ($value === '') {
        return '';
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function nm_smtp_normalize_eol(string $text): string
{
    return preg_replace("/\r\n|\r|\n/", "\r\n", $text) ?? $text;
}

function nm_smtp_read_response($socket, ?int &$code = null): string
{
    $response = '';
    $code = null;

    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }
        $response .= $line;

        if (preg_match('/^(\d{3})([ \-])/', $line, $m)) {
            $code = (int)$m[1];
            if ($m[2] === ' ') {
                break;
            }
        } else {
            break;
        }
    }

    return $response;
}

function nm_smtp_expect($socket, array $expectedCodes, &$errorMessage, string $context = ''): bool
{
    $code = null;
    $response = nm_smtp_read_response($socket, $code);

    if ($code !== null && in_array($code, $expectedCodes, true)) {
        return true;
    }

    $context = trim($context);
    $prefix = $context !== '' ? ($context . ': ') : '';
    $errorMessage = $prefix . trim($response) . ($response === '' ? 'No SMTP response.' : '');
    return false;
}

function nm_smtp_write_line($socket, string $line): bool
{
    $written = @fwrite($socket, $line . "\r\n");
    return $written !== false;
}

function nm_smtp_command($socket, string $command, array $expectedCodes, &$errorMessage, string $context = ''): bool
{
    if (!nm_smtp_write_line($socket, $command)) {
        $errorMessage = ($context !== '' ? $context . ': ' : '') . 'Failed to write SMTP command.';
        return false;
    }
    return nm_smtp_expect($socket, $expectedCodes, $errorMessage, $context);
}

function nm_smtp_data_body(string $headers, string $body): string
{
    $headers = nm_smtp_normalize_eol($headers);
    $body = nm_smtp_normalize_eol($body);

    $lines = explode("\r\n", $body);
    foreach ($lines as &$line) {
        if (isset($line[0]) && $line[0] === '.') {
            $line = '.' . $line;
        }
    }
    unset($line);

    return $headers . "\r\n\r\n" . implode("\r\n", $lines) . "\r\n.";
}

function nm_send_mail_smtp($to, $subject, $body, $from = '', ?array $settings = null, &$errorMessage = null): bool
{
    $to = trim((string)$to);
    $subject = (string)$subject;
    $body = (string)$body;
    $fallbackFrom = trim((string)$from);
    $settings = is_array($settings) ? $settings : nm_load_mail_config();

    if ($to === '') {
        $errorMessage = 'Empty recipient.';
        return false;
    }

    $host = trim((string)($settings['SMTP_HOST'] ?? ''));
    $port = (int)($settings['SMTP_PORT'] ?? 0);
    $encryption = strtolower(trim((string)($settings['SMTP_ENCRYPTION'] ?? '')));
    $useAuth = !empty($settings['SMTP_AUTH']);
    $username = trim((string)($settings['SMTP_USERNAME'] ?? ''));
    $password = (string)($settings['SMTP_PASSWORD'] ?? '');
    $smtpFrom = trim((string)($settings['SMTP_FROM'] ?? ''));
    $fromName = trim((string)($settings['SMTP_FROM_NAME'] ?? ''));

    if ($host === '' || $port <= 0) {
        $errorMessage = 'SMTP host/port is not configured.';
        return false;
    }

    $effectiveFrom = $smtpFrom !== '' ? $smtpFrom : $fallbackFrom;
    if ($effectiveFrom === '') {
        $effectiveFrom = $username;
    }
    if ($effectiveFrom === '') {
        $errorMessage = 'SMTP from address is empty.';
        return false;
    }

    $remoteHost = $host;
    if ($encryption === 'ssl') {
        $remoteHost = 'ssl://' . $host;
    }

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($remoteHost . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
    if (!is_resource($socket)) {
        $errorMessage = 'SMTP connect failed: ' . $errstr;
        return false;
    }

    @stream_set_timeout($socket, 20);

    try {
        if (!nm_smtp_expect($socket, array(220), $errorMessage, 'SMTP connect')) {
            return false;
        }

        $ehloHost = trim((string)($_SERVER['SERVER_NAME'] ?? 'localhost'));
        if ($ehloHost === '') {
            $ehloHost = 'localhost';
        }

        if (!nm_smtp_command($socket, 'EHLO ' . $ehloHost, array(250), $errorMessage, 'EHLO')) {
            return false;
        }

        if ($encryption === 'tls') {
            if (!nm_smtp_command($socket, 'STARTTLS', array(220), $errorMessage, 'STARTTLS')) {
                return false;
            }

            $cryptoOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoOk !== true) {
                $errorMessage = 'Failed to enable STARTTLS.';
                return false;
            }

            if (!nm_smtp_command($socket, 'EHLO ' . $ehloHost, array(250), $errorMessage, 'EHLO after STARTTLS')) {
                return false;
            }
        }

        if ($useAuth) {
            if (!nm_smtp_command($socket, 'AUTH LOGIN', array(334), $errorMessage, 'AUTH LOGIN')) {
                return false;
            }
            if (!nm_smtp_command($socket, base64_encode($username), array(334), $errorMessage, 'SMTP username')) {
                return false;
            }
            if (!nm_smtp_command($socket, base64_encode($password), array(235), $errorMessage, 'SMTP password')) {
                return false;
            }
        }

        if (!nm_smtp_command($socket, 'MAIL FROM:<' . $effectiveFrom . '>', array(250), $errorMessage, 'MAIL FROM')) {
            return false;
        }

        if (!nm_smtp_command($socket, 'RCPT TO:<' . $to . '>', array(250, 251), $errorMessage, 'RCPT TO')) {
            return false;
        }

        if (!nm_smtp_command($socket, 'DATA', array(354), $errorMessage, 'DATA')) {
            return false;
        }

        $fromHeader = $effectiveFrom;
        if ($fromName !== '') {
            $fromHeader = nm_encode_mail_header_utf8($fromName) . ' <' . $effectiveFrom . '>';
        }

        $headers = array(
            'Date: ' . date('r'),
            'From: ' . $fromHeader,
            'To: ' . $to,
            'Subject: ' . nm_encode_mail_header_utf8($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        );

        $dataBody = nm_smtp_data_body(implode("\r\n", $headers), $body);
        if (@fwrite($socket, $dataBody . "\r\n") === false) {
            $errorMessage = 'Failed to write SMTP DATA.';
            return false;
        }

        if (!nm_smtp_expect($socket, array(250), $errorMessage, 'SMTP DATA end')) {
            return false;
        }

        nm_smtp_write_line($socket, 'QUIT');
        return true;
    } finally {
        if (is_resource($socket)) {
            @fclose($socket);
        }
    }
}

function nm_send_mail_common($to, $subject, $body, $from = '', &$errorMessage = null): bool
{
    $settings = nm_load_mail_config();

    $useSmtp = (
        !empty($settings['SMTP_ENABLED'])
        && strtolower((string)($settings['MAIL_TRANSPORT'] ?? 'mail')) === 'smtp'
    );

    if ($useSmtp) {
        $ok = nm_send_mail_smtp($to, $subject, $body, $from, $settings, $errorMessage);
        if ($ok) {
            return true;
        }

        if (empty($settings['SMTP_FALLBACK_TO_MAIL'])) {
            return false;
        }
    }

    return nm_send_mail_php_mail($to, $subject, $body, $from, $errorMessage);
}

function nm_data_json_path(?string $dirUser = null): string
{
    $dirUser = nm_resolve_effective_dir_user($dirUser);
    return nm_data_dir($dirUser) . '/data.json';
}

function nm_images_dir(?string $dirUser = null): string
{
    $dirUser = nm_resolve_effective_dir_user($dirUser);
    return nm_data_dir($dirUser) . '/images';
}

function nm_files_dir(?string $dirUser = null): string
{
    $dirUser = nm_resolve_effective_dir_user($dirUser);
    return nm_data_dir($dirUser) . '/files';
}

// Legacy root-path helpers (do not use in normal flow)
function nm_legacy_root_config_dir(): string { return __DIR__ . '/config'; }
function nm_legacy_root_data_dir(): string { return __DIR__ . '/notemod-data'; }
function nm_legacy_root_logs_dir(): string { return __DIR__ . '/logs'; }



// ==============================
// Auth config
// ==============================

function nm_auth_is_ready(?string $dirUser = null): bool
{
    $p = nm_auth_config_path($dirUser);
    return file_exists($p) && is_readable($p);
}

function nm_auth_load(?string $dirUser = null): array
{
    $p = nm_auth_config_path($dirUser);
    if (!file_exists($p)) {
        return [];
    }
    $cfg = require $p;
    return is_array($cfg) ? $cfg : [];
}

/**
 * USERNAME から該当ユーザーの auth.php を探す
 * 戻り値:
 *   ['DIR_USER' => 'alice', 'USERNAME' => 'Alice', ...auth config...]
 * 見つからなければ null
 */
function nm_find_user_by_username(string $username): ?array
{
    $normalized = normalize_username($username);
    if ($normalized === '') {
        return null;
    }

    $base = __DIR__ . '/config';
    if (!is_dir($base)) {
        return null;
    }

    $direct = $base . '/' . $normalized . '/auth.php';
    if (is_file($direct)) {
        $cfg = require $direct;
        if (is_array($cfg)) {
            $cfg['DIR_USER'] = $normalized;
            if (empty($cfg['USERNAME'])) {
                $cfg['USERNAME'] = $normalized;
            }
            return $cfg;
        }
    }

    foreach (glob($base . '/*/auth.php') ?: [] as $authFile) {
        $cfg = require $authFile;
        if (!is_array($cfg)) {
            continue;
        }
        $dirUser = basename(dirname($authFile));
        $cfgUser = normalize_username((string)($cfg['USERNAME'] ?? ''));
        if ($cfgUser !== '' && $cfgUser === $normalized) {
            $cfg['DIR_USER'] = $dirUser;
            return $cfg;
        }
    }

    return null;
}

function nm_auth_write_config(string $username, string $passwordHash, ?string $dirUser = null): bool
{
    $dirUser = nm_get_current_dir_user($dirUser);
    if ($dirUser === '') {
        $dirUser = normalize_username($username);
    }

    $p = nm_auth_config_path($dirUser);
    $dir = dirname($p);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) {
            return false;
        }
    }

    $existing = array();
    if (is_file($p)) {
        $loaded = require $p;
        if (is_array($loaded)) {
            $existing = $loaded;
        }
    }

    $merged = $existing;
    $merged['USERNAME'] = $username;
    $merged['DIR_USER'] = $dirUser;
    $merged['PASSWORD_HASH'] = $passwordHash;
    $merged['UPDATED_AT'] = gmdate('c');

    $data = "<?php\nreturn " . var_export($merged, true) . ";\n";

    try {
        $suffix = bin2hex(random_bytes(4));
    } catch (Throwable $e) {
        $suffix = dechex(mt_rand());
    }

    $tmp = $p . '.tmp-' . $suffix;
    if (@file_put_contents($tmp, $data, LOCK_EX) === false) {
        return false;
    }
    @chmod($tmp, 0644);
    if (!@rename($tmp, $p)) {
        @unlink($tmp);
        return false;
    }
    @chmod($p, 0644);
    return true;
}

/**
 * ログイン状態チェック
 */
function nm_auth_is_logged_in(): bool
{
    nm_auth_start_session();
    return (bool)($_SESSION['nm_logged_in'] ?? false);
}

function nm_auth_require_login(): void
{
    nm_auth_start_session();

    if (!nm_auth_is_logged_in()) {
        header('Location: ' . nm_ui_url('/login.php'));
        exit;
    }

    $dirUser = nm_get_current_dir_user();

    // 旧セッションからの自動復元
    if ($dirUser === '') {
        $loginUser = (string)($_SESSION['nm_username'] ?? $_SESSION['nm_login_user'] ?? $_SESSION['nm_user'] ?? '');
        if ($loginUser !== '') {
            $found = nm_find_user_by_username($loginUser);
            if (is_array($found) && !empty($found['DIR_USER'])) {
                $dirUser = normalize_username((string)$found['DIR_USER']);
                $_SESSION['nm_dir_user'] = $dirUser;
                if (!empty($found['USERNAME'])) {
                    $_SESSION['nm_username'] = (string)$found['USERNAME'];
                }
                nm_refresh_dir_user_cookie($dirUser);
            }
        }
    }

    if ($dirUser === '') {
        header('Location: ' . nm_ui_url('/login.php'));
        exit;
    }

    if (!nm_auth_is_ready($dirUser)) {
        header('Location: ' . nm_ui_url('/setup_auth.php'));
        exit;
    }
}

// ==============================
// UI: lang/theme 共通化
// ==============================

/**
 * GET (?lang=ja|en, ?theme=dark|light) をセッションへ反映し、現在値を返す
 * 戻り値: ['lang'=>'ja|en', 'theme'=>'dark|light']
 */
function nm_ui_bootstrap(): array
{
    nm_auth_start_session();

    if (isset($_GET['lang'])) {
        $q = strtolower((string)$_GET['lang']);
        if (in_array($q, ['ja', 'en'], true)) {
            $_SESSION['nm_lang'] = $q;
        }
    }
    if (isset($_GET['theme'])) {
        $q = strtolower((string)$_GET['theme']);
        if (in_array($q, ['dark', 'light'], true)) {
            $_SESSION['nm_theme'] = $q;
        }
    }

    $lang  = (string)($_SESSION['nm_lang'] ?? 'ja');
    $theme = (string)($_SESSION['nm_theme'] ?? 'dark');

    if (!in_array($lang, ['ja', 'en'], true)) {
        $lang = 'ja';
    }
    if (!in_array($theme, ['dark', 'light'], true)) {
        $theme = 'dark';
    }

    $_SESSION['nm_lang'] = $lang;
    $_SESSION['nm_theme'] = $theme;

    return ['lang' => $lang, 'theme' => $theme];
}

/**
 * 現在の lang/theme を付けてURL生成
 * $path は "/login.php" のように先頭スラッシュ推奨
 */
function nm_ui_url(string $path, ?string $lang = null, ?string $theme = null): string
{
    nm_auth_start_session();
    $base = nm_auth_base_url();

    $lang  = $lang  ?? (string)($_SESSION['nm_lang'] ?? 'ja');
    $theme = $theme ?? (string)($_SESSION['nm_theme'] ?? 'dark');

    if (!in_array($lang, ['ja', 'en'], true)) {
        $lang = 'ja';
    }
    if (!in_array($theme, ['dark', 'light'], true)) {
        $theme = 'dark';
    }

    $q = http_build_query(['lang' => $lang, 'theme' => $theme]);
    return $base . $path . '?' . $q;
}

/**
 * トグル用URLセット
 */
function nm_ui_toggle_urls(string $path, string $lang, string $theme): array
{
    return [
        'langJa' => nm_ui_url($path, 'ja', $theme),
        'langEn' => nm_ui_url($path, 'en', $theme),
        'dark'   => nm_ui_url($path, $lang, 'dark'),
        'light'  => nm_ui_url($path, $lang, 'light'),
    ];
}