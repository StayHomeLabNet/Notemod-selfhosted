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
// Session helpers
// ==============================

function nm_auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    @ini_set('session.use_strict_mode', '1');

    $cookiePath = nm_auth_cookie_path();
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, $cookiePath . '; samesite=Lax', '', $secure, true);
    }

    @session_start();
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

    nm_auth_start_session();

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

    $data = "<?php\nreturn " . var_export([
        'USERNAME' => $username,
        'DIR_USER' => $dirUser,
        'PASSWORD_HASH' => $passwordHash,
        'UPDATED_AT' => gmdate('c'),
    ], true) . ";\n";

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
