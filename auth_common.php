<?php
declare(strict_types=1);

defined('NM_APP_VERSION') || define('NM_APP_VERSION', '1.4.7');
defined('NM_REPOSITORY_URL') || define('NM_REPOSITORY_URL', 'https://github.com/StayHomeLabNet/Notemod-selfhosted');

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
// Password policy helpers
// ==============================

function nm_password_min_length(): int
{
    return 10;
}

function nm_password_character_length(string $password): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($password, 'UTF-8');
    }

    $count = preg_match_all('/./us', $password, $matches);
    return $count === false ? strlen($password) : $count;
}

function nm_password_meets_minimum_length(string $password): bool
{
    return nm_password_character_length($password) >= nm_password_min_length();
}

// ==============================
// Storage path helpers
// ==============================

/**
 * Runtime storage root. Set NM_STORAGE_ROOT to an absolute path outside the
 * web document root in production. The project directory remains the legacy
 * default so existing installations continue to work without migration.
 */
function nm_storage_root(): string
{
    $configured = getenv('NM_STORAGE_ROOT');
    if ($configured === false || trim($configured) === '') {
        $configured = (string)($_SERVER['NM_STORAGE_ROOT'] ?? '');
    }

    $configured = trim((string)$configured);
    if ($configured === '') {
        return __DIR__;
    }

    $isAbsolute = str_starts_with($configured, '/')
        || str_starts_with($configured, '\\\\')
        || (bool)preg_match('/^[A-Za-z]:[\\\\\/]/', $configured);
    if (!$isAbsolute) {
        throw new RuntimeException('NM_STORAGE_ROOT must be an absolute path.');
    }

    $configured = rtrim($configured, "/\\");
    if ($configured === '' || (bool)preg_match('/^[A-Za-z]:$/', $configured)) {
        throw new RuntimeException('NM_STORAGE_ROOT must not be a filesystem root.');
    }

    return $configured;
}

function nm_config_root(): string
{
    return nm_storage_root() . '/config';
}

function nm_data_root(): string
{
    return nm_storage_root() . '/notemod-data';
}

function nm_logs_root(): string
{
    return nm_storage_root() . '/logs';
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

function nm_configured_base_url(string $environmentName): string
{
    $value = getenv($environmentName);
    if ($value === false || trim($value) === '') {
        $value = (string)($_SERVER[$environmentName] ?? '');
    }

    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/[\x00-\x20\\\\]/', $value)) {
        throw new RuntimeException($environmentName . ' contains invalid characters.');
    }

    $parts = parse_url($value);
    if (!is_array($parts)) {
        throw new RuntimeException($environmentName . ' must be a valid absolute URL.');
    }

    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = (string)($parts['host'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        throw new RuntimeException($environmentName . ' must use an http or https URL with a host.');
    }
    if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
        throw new RuntimeException($environmentName . ' must not include credentials, a query, or a fragment.');
    }
    if (preg_match('/[\s\\\\\/@]/', $host)) {
        throw new RuntimeException($environmentName . ' contains an invalid host.');
    }
    $hostForValidation = $host;
    if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
        $hostForValidation = substr($host, 1, -1);
    }
    $validHost = filter_var($hostForValidation, FILTER_VALIDATE_IP) !== false
        || filter_var($hostForValidation, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    if (!$validHost) {
        throw new RuntimeException($environmentName . ' contains an invalid host.');
    }

    $port = isset($parts['port']) ? (int)$parts['port'] : null;
    if ($port !== null && ($port < 1 || $port > 65535)) {
        throw new RuntimeException($environmentName . ' contains an invalid port.');
    }

    $path = (string)($parts['path'] ?? '');
    if ($path !== '' && $path[0] !== '/') {
        throw new RuntimeException($environmentName . ' contains an invalid path.');
    }
    $decodedPath = rawurldecode($path);
    if (preg_match('/[\x00-\x20\\\\]/', $decodedPath)) {
        throw new RuntimeException($environmentName . ' contains an invalid path.');
    }
    foreach (explode('/', $decodedPath) as $segment) {
        if ($segment === '..') {
            throw new RuntimeException($environmentName . ' must not contain parent path segments.');
        }
    }

    $authority = $host;
    if ($port !== null) {
        $authority .= ':' . $port;
    }

    return rtrim($scheme . '://' . $authority . $path, '/');
}

function nm_public_base_url(): string
{
    return nm_configured_base_url('NM_PUBLIC_BASE_URL');
}

function nm_internal_base_url(): string
{
    $internal = nm_configured_base_url('NM_INTERNAL_BASE_URL');
    return $internal !== '' ? $internal : nm_public_base_url();
}

function nm_join_base_url(string $baseUrl, string $path): string
{
    if ($baseUrl === '') {
        return '';
    }
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

function nm_public_url(string $path = ''): string
{
    return nm_join_base_url(nm_public_base_url(), $path);
}

function nm_internal_url(string $path = ''): string
{
    return nm_join_base_url(nm_internal_base_url(), $path);
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

if (!function_exists('nm_common_config_defaults')) {
function nm_common_config_defaults(): array
{
    return [
        'TIMEZONE' => 'Asia/Tokyo',
        'DEBUG' => false,
        'LOGGER_FILE_ENABLED' => true,
        'LOGGER_NOTEMOD_ENABLED' => false,
        'SYNC_PRE_SAVE_BACKUP_ENABLED' => true,
        'SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED' => false,
        'DATA_ENCRYPTION_ENABLED' => false,
        'SESSION_COOKIE_LIFETIME' => 0,
        'MAX_IMAGE_UPLOAD_BYTES' => 10 * 1024 * 1024,
        'MAX_FILE_UPLOAD_BYTES' => 25 * 1024 * 1024,
        'MAX_IMAGE_DIMENSION' => 10000,
        'MAX_IMAGE_PIXELS' => 25000000,
        'MAX_RESIZE_DIMENSION' => 2000,
        'MAX_RESIZE_PIXELS' => 4000000,
        'IP_ALERT_ENABLED' => false,
        'IP_ALERT_TO' => 'YOUR_EMAIL',
        'IP_ALERT_FROM' => 'no-reply@notemod',
        'IP_ALERT_SUBJECT' => 'Notemod: First-time IP access',
        'IP_ALERT_IGNORE_BOTS' => true,
        'IP_ALERT_IGNORE_IPS' => [],
        'LOGGER_FILE_MAX_LINES' => 500,
        'LOGGER_NOTEMOD_MAX_LINES' => 50,
    ];
}
}

if (!function_exists('nm_common_config_with_defaults')) {
function nm_common_config_with_defaults(array $config): array
{
    return array_replace(nm_common_config_defaults(), $config);
}
}

if (!function_exists('nm_read_common_config_for_dir_user')) {
function nm_read_common_config_for_dir_user(?string $dirUser = null): array
{
    $dirUser = nm_guess_dir_user_for_cookie($dirUser);
    if ($dirUser === '') {
        return nm_common_config_defaults();
    }

    $configPath = nm_config_root() . '/' . $dirUser . '/config.php';
    return nm_common_config_with_defaults(nm_read_php_config_array($configPath));
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
    $secure = nm_is_https_request();
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
    $secure = nm_is_https_request();

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
    $secure = nm_is_https_request();
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
// Security headers helpers
// ==============================

if (!function_exists('nm_send_security_headers_base')) {
function nm_send_security_headers_base(string $frameOptions, string $referrerPolicy): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: ' . $frameOptions);
    header('Referrer-Policy: ' . $referrerPolicy);
    header('Permissions-Policy: camera=(), geolocation=(), microphone=(), payment=(), usb=()');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
}

if (!function_exists('nm_send_security_headers_html')) {
function nm_send_security_headers_html(): void
{
    nm_send_security_headers_base('SAMEORIGIN', 'same-origin');
    if (!headers_sent()) {
        header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; worker-src 'self' blob:; manifest-src 'self'; media-src 'self' blob:");
    }
}
}

if (!function_exists('nm_send_security_headers_json')) {
function nm_send_security_headers_json(): void
{
    nm_send_security_headers_base('DENY', 'no-referrer');
    if (!headers_sent()) {
        header("Content-Security-Policy: default-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; sandbox");
    }
}
}

if (!function_exists('nm_send_security_headers_binary')) {
function nm_send_security_headers_binary(): void
{
    nm_send_security_headers_base('DENY', 'no-referrer');
    if (!headers_sent()) {
        header("Content-Security-Policy: default-src 'none'; base-uri 'none'; frame-ancestors 'none'; sandbox");
    }
}
}

// ==============================
// CSRF helpers
// ==============================

if (!function_exists('nm_csrf_token_get')) {
function nm_csrf_token_get(): string
{
    nm_auth_start_session();

    $token = (string)($_SESSION['nm_csrf_token'] ?? '');
    if ($token !== '') {
        return $token;
    }

    try {
        $token = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        $token = hash('sha256', uniqid('', true) . mt_rand());
    }

    $_SESSION['nm_csrf_token'] = $token;
    return $token;
}
}

if (!function_exists('nm_csrf_rotate_token')) {
function nm_csrf_rotate_token(): string
{
    nm_auth_start_session();

    try {
        $token = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        $token = hash('sha256', uniqid('', true) . mt_rand());
    }

    $_SESSION['nm_csrf_token'] = $token;
    return $token;
}
}

if (!function_exists('nm_csrf_input_html')) {
function nm_csrf_input_html(): string
{
    $token = nm_csrf_token_get();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}
}

if (!function_exists('nm_csrf_validate_or_die')) {
function nm_csrf_validate_or_die(?string $token = null): void
{
    nm_auth_start_session();

    $sessionToken = (string)($_SESSION['nm_csrf_token'] ?? '');
    $requestToken = $token;

    if ($requestToken === null) {
        if (isset($_POST['csrf_token'])) {
            $requestToken = (string)$_POST['csrf_token'];
        } elseif (isset($_REQUEST['csrf_token'])) {
            $requestToken = (string)$_REQUEST['csrf_token'];
        } else {
            $requestToken = '';
        }
    }

    if ($sessionToken === '' || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
        http_response_code(403);

        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }
        exit('Invalid CSRF token');
    }
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
    return nm_config_root() . '/' . $dirUser;
}

function nm_data_dir(?string $dirUser = null): string
{
    $dirUser = nm_resolve_effective_dir_user($dirUser);
    return nm_data_root() . '/' . $dirUser;
}

function nm_logs_dir(?string $dirUser = null): string
{
    $dirUser = nm_resolve_effective_dir_user($dirUser);
    return nm_logs_root() . '/' . $dirUser;
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

// ==============================
// Upload / image processing limits
// ==============================

function nm_media_limit_value(array $config, string $key, int $default): int
{
    $value = $config[$key] ?? $default;
    if (is_string($value) && ctype_digit($value)) {
        $value = (int)$value;
    }

    return is_int($value) && $value > 0 ? $value : $default;
}

function nm_media_limits(?string $dirUser = null): array
{
    $defaults = nm_common_config_defaults();
    $config = nm_common_config_with_defaults(nm_read_php_config_array(nm_config_path($dirUser)));

    return [
        'image_upload_bytes' => nm_media_limit_value($config, 'MAX_IMAGE_UPLOAD_BYTES', $defaults['MAX_IMAGE_UPLOAD_BYTES']),
        'file_upload_bytes' => nm_media_limit_value($config, 'MAX_FILE_UPLOAD_BYTES', $defaults['MAX_FILE_UPLOAD_BYTES']),
        'image_dimension' => nm_media_limit_value($config, 'MAX_IMAGE_DIMENSION', $defaults['MAX_IMAGE_DIMENSION']),
        'image_pixels' => nm_media_limit_value($config, 'MAX_IMAGE_PIXELS', $defaults['MAX_IMAGE_PIXELS']),
        'resize_dimension' => nm_media_limit_value($config, 'MAX_RESIZE_DIMENSION', $defaults['MAX_RESIZE_DIMENSION']),
        'resize_pixels' => nm_media_limit_value($config, 'MAX_RESIZE_PIXELS', $defaults['MAX_RESIZE_PIXELS']),
    ];
}

function nm_file_size_bytes(string $path): ?int
{
    clearstatcache(true, $path);
    $size = @filesize($path);
    return is_int($size) && $size >= 0 ? $size : null;
}

function nm_image_dimensions_allowed(int $width, int $height, int $maxDimension, int $maxPixels): bool
{
    if ($width < 1 || $height < 1 || $width > $maxDimension || $height > $maxDimension) {
        return false;
    }

    return $width <= intdiv($maxPixels, $height);
}

function nm_calculate_resize_dimensions(int $sourceWidth, int $sourceHeight, int $requestedWidth, int $requestedHeight): ?array
{
    if ($sourceWidth < 1 || $sourceHeight < 1 || $requestedWidth < 0 || $requestedHeight < 0) {
        return null;
    }

    if ($requestedWidth === 0 && $requestedHeight === 0) {
        return [$sourceWidth, $sourceHeight];
    }
    if ($requestedWidth === 0) {
        $requestedWidth = (int)round($sourceWidth * ($requestedHeight / $sourceHeight));
    } elseif ($requestedHeight === 0) {
        $requestedHeight = (int)round($sourceHeight * ($requestedWidth / $sourceWidth));
    }

    if ($requestedWidth < 1 || $requestedHeight < 1) {
        return null;
    }

    return [$requestedWidth, $requestedHeight];
}

function nm_release_gd_image(&$image): void
{
    if ($image !== null && $image !== false && PHP_VERSION_ID < 80500 && function_exists('imagedestroy')) {
        @imagedestroy($image);
    }
    $image = null;
}

function nm_with_index_lock(string $indexPath, callable $operation)
{
    $lockPath = $indexPath . '.lock';
    $lockHandle = @fopen($lockPath, 'c+');
    if ($lockHandle === false) {
        return null;
    }
    if (!@flock($lockHandle, LOCK_EX)) {
        @fclose($lockHandle);
        return null;
    }

    try {
        return $operation();
    } finally {
        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);
    }
}

// Legacy root-path helpers (do not use in normal flow)
function nm_legacy_root_config_dir(): string { return __DIR__ . '/config'; }
function nm_legacy_root_data_dir(): string { return __DIR__ . '/notemod-data'; }
function nm_legacy_root_logs_dir(): string { return __DIR__ . '/logs'; }

// ==============================
// .htaccess helpers
// ==============================

if (!function_exists('nm_default_deny_htaccess_content')) {
function nm_default_deny_htaccess_content(): string
{
    return <<<HT
Options -Indexes
<IfModule mod_authz_core.c>
  Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
  Order allow,deny
  Deny from all
</IfModule>

HT;
}
}

if (!function_exists('nm_default_api_htaccess_content')) {
function nm_default_api_htaccess_content(): string
{
    return <<<HT
Options -Indexes
<IfModule mod_authz_core.c>
  Require all granted
</IfModule>
<IfModule !mod_authz_core.c>
  Order allow,deny
  Allow from all
</IfModule>

HT;
}
}

if (!function_exists('nm_write_htaccess_content')) {
function nm_write_htaccess_content(string $dir, string $content, bool $overwrite = false, bool $createDir = true): bool
{
    $dir = rtrim($dir, "/\\");
    if ($dir === '') {
        return false;
    }

    if (!is_dir($dir)) {
        if (!$createDir) {
            return false;
        }
        if (!@mkdir($dir, 0755, true)) {
            return false;
        }
    }

    $path = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!$overwrite && is_file($path)) {
        return true;
    }

    try {
        $suffix = bin2hex(random_bytes(4));
    } catch (Throwable $e) {
        $suffix = dechex(mt_rand());
    }

    $tmp = $path . '.tmp-' . $suffix;
    if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
        @unlink($tmp);
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
}

if (!function_exists('nm_ensure_core_protection_htaccess')) {
function nm_ensure_core_protection_htaccess(?string $dirUser = null, bool $overwrite = false): bool
{
    $ok = true;

    $rootConfig = nm_config_root();
    $rootLogs   = nm_logs_root();
    $rootData   = nm_data_root();
    $apiDir     = __DIR__ . '/api';

    $ok = nm_write_htaccess_content($rootConfig, nm_default_deny_htaccess_content(), $overwrite, true) && $ok;
    $ok = nm_write_htaccess_content($rootLogs, nm_default_deny_htaccess_content(), $overwrite, true) && $ok;
    $ok = nm_write_htaccess_content($rootData, nm_default_deny_htaccess_content(), $overwrite, true) && $ok;
    $ok = nm_write_htaccess_content($apiDir, nm_default_api_htaccess_content(), $overwrite, true) && $ok;

    $resolvedDirUser = normalize_username((string)$dirUser);
    if ($resolvedDirUser !== '') {
        $ok = nm_write_htaccess_content(nm_config_dir($resolvedDirUser), nm_default_deny_htaccess_content(), $overwrite, true) && $ok;
        $ok = nm_write_htaccess_content(nm_logs_dir($resolvedDirUser), nm_default_deny_htaccess_content(), $overwrite, true) && $ok;
        $ok = nm_write_htaccess_content(nm_data_dir($resolvedDirUser), nm_default_deny_htaccess_content(), $overwrite, true) && $ok;
    }

    return $ok;
}
}

// ==============================
// Rate limit helpers
// ==============================

if (!function_exists('nm_rate_limit_file_path')) {
function nm_rate_limit_file_path(): string
{
    return nm_logs_root() . '/rate_limit.json';
}
}

if (!function_exists('nm_rate_limit_lock_path')) {
function nm_rate_limit_lock_path(): string
{
    return nm_rate_limit_file_path() . '.lock';
}
}

if (!function_exists('nm_rate_limit_open_lock')) {
function nm_rate_limit_open_lock()
{
    $lockPath = nm_rate_limit_lock_path();
    $dir = dirname($lockPath);

    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }

    nm_write_htaccess_content($dir, nm_default_deny_htaccess_content(), false, true);

    $handle = @fopen($lockPath, 'c');
    if ($handle !== false) {
        @chmod($lockPath, 0644);
    }
    return $handle;
}
}

if (!function_exists('nm_rate_limit_load_unlocked')) {
function nm_rate_limit_load_unlocked(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
}

if (!function_exists('nm_rate_limit_save_unlocked')) {
function nm_rate_limit_save_unlocked(string $path, array $data): bool
{
    $dir = dirname($path);

    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($json)) {
        return false;
    }

    try {
        $suffix = bin2hex(random_bytes(4));
    } catch (Throwable $e) {
        $suffix = dechex(mt_rand());
    }

    $tmp = $path . '.tmp-' . $suffix;
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        @unlink($tmp);
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
}

if (!function_exists('nm_rate_limit_load')) {
function nm_rate_limit_load(): array
{
    $lock = nm_rate_limit_open_lock();
    if ($lock === false || !@flock($lock, LOCK_SH)) {
        if (is_resource($lock)) {
            @fclose($lock);
        }
        return [];
    }

    try {
        return nm_rate_limit_load_unlocked(nm_rate_limit_file_path());
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}
}

if (!function_exists('nm_rate_limit_mutate')) {
function nm_rate_limit_mutate(callable $mutator): bool
{
    $lock = nm_rate_limit_open_lock();
    if ($lock === false || !@flock($lock, LOCK_EX)) {
        if (is_resource($lock)) {
            @fclose($lock);
        }
        return false;
    }

    try {
        $path = nm_rate_limit_file_path();
        $current = nm_rate_limit_load_unlocked($path);
        $updated = $mutator($current);
        if ($updated === null) {
            return true;
        }
        if (!is_array($updated)) {
            return false;
        }
        return nm_rate_limit_save_unlocked($path, $updated);
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}
}

if (!function_exists('nm_rate_limit_save')) {
function nm_rate_limit_save(array $data): bool
{
    return nm_rate_limit_mutate(static function (array $current) use ($data): array {
        return $data;
    });
}
}

if (!function_exists('nm_rate_limit_check')) {
function nm_rate_limit_check(string $bucket, int $max, int $window): array
{
    $bucket = trim($bucket);
    $max = max(1, $max);
    $window = max(1, $window);
    $now = time();

    $all = nm_rate_limit_load();
    $hits = [];

    if (isset($all[$bucket]) && is_array($all[$bucket])) {
        foreach ($all[$bucket] as $ts) {
            $ts = (int)$ts;
            if ($ts > 0 && ($now - $ts) < $window) {
                $hits[] = $ts;
            }
        }
    }

    $count = count($hits);
    $allowed = $count < $max;
    $retryAfter = 0;

    if (!$allowed && !empty($hits)) {
        $oldest = min($hits);
        $retryAfter = max(1, $window - ($now - $oldest));
    }

    return [
        'allowed' => $allowed,
        'count' => $count,
        'remaining' => max(0, $max - $count),
        'retry_after' => $retryAfter,
        'window' => $window,
        'max' => $max,
    ];
}
}

if (!function_exists('nm_rate_limit_record_failure')) {
function nm_rate_limit_record_failure(string $bucket, int $window): void
{
    $bucket = trim($bucket);
    if ($bucket === '') {
        return;
    }

    $window = max(1, $window);
    $now = time();

    nm_rate_limit_mutate(static function (array $all) use ($bucket, $window, $now): array {
        $hits = [];

        if (isset($all[$bucket]) && is_array($all[$bucket])) {
            foreach ($all[$bucket] as $ts) {
                $ts = (int)$ts;
                if ($ts > 0 && ($now - $ts) < $window) {
                    $hits[] = $ts;
                }
            }
        }

        $hits[] = $now;
        $all[$bucket] = array_values($hits);
        return $all;
    });
}
}

if (!function_exists('nm_rate_limit_clear')) {
function nm_rate_limit_clear(string $bucket): void
{
    $bucket = trim($bucket);
    if ($bucket === '') {
        return;
    }

    nm_rate_limit_mutate(static function (array $all) use ($bucket): ?array {
        if (!array_key_exists($bucket, $all)) {
            return null;
        }

        unset($all[$bucket]);
        return $all;
    });
}
}

// ==============================
// Audit log helpers
// ==============================

if (!function_exists('nm_audit_log_path')) {
function nm_audit_log_path(): string
{
    return nm_logs_root() . '/audit.log';
}
}

if (!function_exists('nm_normalize_ip')) {
function nm_normalize_ip(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if ($value[0] === '[' && preg_match('/^\[([^\]]+)\](?::\d+)?$/', $value, $match)) {
        $value = $match[1];
    } elseif (preg_match('/^((?:\d{1,3}\.){3}\d{1,3}):\d+$/', $value, $match)) {
        $value = $match[1];
    }

    return filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : null;
}
}

if (!function_exists('nm_trusted_proxy_entries')) {
function nm_trusted_proxy_entries(): array
{
    $configured = getenv('NM_TRUSTED_PROXIES');
    if ($configured === false || trim($configured) === '') {
        $configured = (string)($_SERVER['NM_TRUSTED_PROXIES'] ?? '');
    }

    $entries = preg_split('/[\s,]+/', trim((string)$configured)) ?: [];
    return array_values(array_filter($entries, static function ($entry): bool {
        return trim((string)$entry) !== '';
    }));
}
}

if (!function_exists('nm_ip_matches_proxy_entry')) {
function nm_ip_matches_proxy_entry(string $ip, string $entry): bool
{
    $entry = trim($entry);
    if ($entry === '') {
        return false;
    }

    if (strpos($entry, '/') === false) {
        $proxyIp = nm_normalize_ip($entry);
        if ($proxyIp === null) {
            return false;
        }
        return @inet_pton($proxyIp) === @inet_pton($ip);
    }

    [$network, $prefixText] = array_pad(explode('/', $entry, 2), 2, '');
    $network = nm_normalize_ip($network) ?? '';
    if ($network === '' || $prefixText === '' || !ctype_digit($prefixText)) {
        return false;
    }

    $ipBinary = @inet_pton($ip);
    $networkBinary = @inet_pton($network);
    if ($ipBinary === false || $networkBinary === false || strlen($ipBinary) !== strlen($networkBinary)) {
        return false;
    }

    $prefix = (int)$prefixText;
    $maxBits = strlen($ipBinary) * 8;
    if ($prefix < 0 || $prefix > $maxBits) {
        return false;
    }

    $fullBytes = intdiv($prefix, 8);
    $remainingBits = $prefix % 8;
    if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($networkBinary, 0, $fullBytes)) {
        return false;
    }
    if ($remainingBits === 0) {
        return true;
    }

    $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
    return (ord($ipBinary[$fullBytes]) & $mask) === (ord($networkBinary[$fullBytes]) & $mask);
}
}

if (!function_exists('nm_is_trusted_proxy')) {
function nm_is_trusted_proxy(string $ip): bool
{
    foreach (nm_trusted_proxy_entries() as $entry) {
        if (nm_ip_matches_proxy_entry($ip, (string)$entry)) {
            return true;
        }
    }
    return false;
}
}

if (!function_exists('nm_request_ip')) {
function nm_request_ip(): string
{
    $remoteAddr = nm_normalize_ip((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($remoteAddr === null) {
        return 'UNKNOWN';
    }

    if (!nm_is_trusted_proxy($remoteAddr)) {
        return $remoteAddr;
    }

    $forwardedFor = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($forwardedFor === '') {
        return $remoteAddr;
    }

    $chain = [];
    foreach (explode(',', $forwardedFor) as $value) {
        $ip = nm_normalize_ip($value);
        if ($ip !== null) {
            $chain[] = $ip;
        }
    }

    for ($i = count($chain) - 1; $i >= 0; $i--) {
        if (!nm_is_trusted_proxy($chain[$i])) {
            return $chain[$i];
        }
    }

    if ($chain === []) {
        return $remoteAddr;
    }

    return $chain[0];
}
}

if (!function_exists('nm_is_https_request')) {
function nm_is_https_request(): bool
{
    $https = strtolower(trim((string)($_SERVER['HTTPS'] ?? '')));
    if ($https !== '' && !in_array($https, ['off', '0', 'false'], true)) {
        return true;
    }
    if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }

    $remoteAddr = nm_normalize_ip((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($remoteAddr === null || !nm_is_trusted_proxy($remoteAddr)) {
        return false;
    }

    $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($forwardedProto === '') {
        return false;
    }

    $values = array_map('trim', explode(',', $forwardedProto));
    foreach ($values as $value) {
        if ($value !== 'https') {
            return false;
        }
    }

    return true;
}
}

if (!function_exists('nm_current_actor_username')) {
function nm_current_actor_username(): string
{
    nm_auth_start_session();
    return (string)($_SESSION['nm_username'] ?? $_SESSION['nm_login_user'] ?? $_SESSION['nm_user'] ?? '');
}
}

if (!function_exists('nm_current_actor_dir_user')) {
function nm_current_actor_dir_user(): string
{
    nm_auth_start_session();
    return normalize_username((string)($_SESSION['nm_dir_user'] ?? ''));
}
}

if (!function_exists('nm_write_audit_log')) {
function nm_write_audit_log(string $event, array $context = []): void
{
    $path = nm_audit_log_path();
    $dir = dirname($path);

    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return;
    }

    nm_write_htaccess_content($dir, nm_default_deny_htaccess_content(), false, true);

    $line = json_encode([
        'ts' => date('c'),
        'event' => $event,
        'ip' => nm_request_ip(),
        'actor_username' => nm_current_actor_username(),
        'actor_dir_user' => nm_current_actor_dir_user(),
        'context' => $context,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!is_string($line)) {
        return;
    }

    @file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
}
}


if (!function_exists('nm_mask_email_for_audit')) {
function nm_mask_email_for_audit(string $email): string
{
    $email = trim($email);
    if ($email === '' || strpos($email, '@') === false) {
        return '';
    }

    [$local, $domain] = explode('@', $email, 2);
    $localLen = strlen($local);

    if ($localLen <= 1) {
        $maskedLocal = '*';
    } elseif ($localLen === 2) {
        $maskedLocal = substr($local, 0, 1) . '*';
    } else {
        $maskedLocal = substr($local, 0, 1) . str_repeat('*', max(1, $localLen - 2)) . substr($local, -1);
    }

    return $maskedLocal . '@' . $domain;
}
}

if (!function_exists('nm_write_auth_event')) {
function nm_write_auth_event(string $event, array $context = []): void
{
    if (!isset($context['ip']) || trim((string)$context['ip']) === '') {
        $context['ip'] = function_exists('nm_request_ip')
            ? nm_request_ip()
            : (string)($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
    }

    nm_write_audit_log($event, $context);
}
}

// ==============================
// Common mail config / send helpers
// ==============================

function nm_mail_config_path(): string
{
    return nm_config_root() . '/mail.php';
}

function nm_mail_default_config(): array
{
    return array(
        'MAIL_TRANSPORT' => 'mail',
        'SMTP_ENABLED' => 0,
        'SMTP_HOST' => '',
        'SMTP_PORT' => 587,
        'SMTP_ENCRYPTION' => 'ssl',
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

    clearstatcache(true, $path);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($path, true);
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

    $base = nm_config_root();
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

    clearstatcache(true, $p);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($p, true);
    }

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
        if (function_exists('nm_send_security_headers_html')) {
            nm_send_security_headers_html();
        }
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
        if (function_exists('nm_send_security_headers_html')) {
            nm_send_security_headers_html();
        }
        header('Location: ' . nm_ui_url('/login.php'));
        exit;
    }

    if (!nm_auth_is_ready($dirUser)) {
        if (function_exists('nm_send_security_headers_html')) {
            nm_send_security_headers_html();
        }
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
