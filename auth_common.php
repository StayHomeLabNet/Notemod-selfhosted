<?php
declare(strict_types=1);

// ==============================
// Auth config path
// ==============================
function nm_auth_config_path(): string {
    return __DIR__ . '/config/auth.php';
}

/**
 * Notemod の設置パス（Cookie Path 用）
 * 例:
 *  - /notemod/login.php      -> /notemod/
 *  - /notemod/setup_auth.php -> /notemod/
 *  - /index.php              -> /
 */
function nm_auth_cookie_path(): string
{
    // SCRIPT_NAME: "/notemod/login.php" のような想定
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/');
    $script = str_replace('\\', '/', $script);

    $dir = rtrim(dirname($script), '/');
    if ($dir === '' || $dir === '.') $dir = '/';

    // "/notemod" -> "/notemod/"
    if ($dir !== '/') $dir .= '/';
    return $dir;
}

/**
 * Base URL（/notemod の部分だけ）を返す
 * - nm_auth_cookie_path() を基準にするので、login.php だけ未ログインになる事故を防ぎやすい
 */
function nm_auth_base_url(): string {
    $p = nm_auth_cookie_path(); // "/notemod/"
    return rtrim($p, '/');      // "/notemod"
}

function nm_auth_start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // shared hostingでも安全寄り
    @ini_set('session.use_strict_mode', '1');

    // ★ここが重要：CookieのPathを Notemod ルートに固定する
    $cookiePath = nm_auth_cookie_path();

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    // PHP 7.3+ は配列が使える。古い環境は fallback。
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        // samesite は旧版だと直接指定しづらいので path に付ける
        session_set_cookie_params(0, $cookiePath . '; samesite=Lax', '', $secure, true);
    }

    @session_start();
}

function nm_auth_is_ready(): bool {
    $p = nm_auth_config_path();
    return file_exists($p) && is_readable($p);
}

function nm_auth_load(): array {
    $p = nm_auth_config_path();
    if (!file_exists($p)) return [];
    $cfg = require $p;
    return is_array($cfg) ? $cfg : [];
}

function nm_auth_write_config(string $username, string $passwordHash): bool {
    $p = nm_auth_config_path();
    $dir = dirname($p);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) return false;
    }

    $data = "<?php\nreturn " . var_export([
        'USERNAME' => $username,
        'PASSWORD_HASH' => $passwordHash,
        'UPDATED_AT' => gmdate('c'),
    ], true) . ";\n";

    $tmp = $p . '.tmp-' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $data, LOCK_EX) === false) return false;
    @chmod($tmp, 0644);
    if (!@rename($tmp, $p)) {
        @unlink($tmp);
        return false;
    }
    @chmod($p, 0644);
    return true;
}

/**
 * ログイン状態チェック（setup_auth.php が参照するために用意）
 */
function nm_auth_is_logged_in(): bool
{
    nm_auth_start_session();
    return (bool)($_SESSION['nm_logged_in'] ?? false);
}

function nm_auth_require_login(): void {
    nm_auth_start_session();

    // auth未設定なら setup へ
    if (!nm_auth_is_ready()) {
        $u = nm_ui_url('/setup_auth.php');
        header('Location: ' . $u);
        exit;
    }

    if (!nm_auth_is_logged_in()) {
        $u = nm_ui_url('/login.php');
        header('Location: ' . $u);
        exit;
    }
}

// ==============================
// ★ UI: lang/theme 共通化
// ==============================

/**
 * GET (?lang=ja|en, ?theme=dark|light) をセッションへ反映し、現在値を返す
 * 戻り値: ['lang'=>'ja|en', 'theme'=>'dark|light']
 */
function nm_ui_bootstrap(): array {
    nm_auth_start_session();

    if (isset($_GET['lang'])) {
        $q = strtolower((string)$_GET['lang']);
        if (in_array($q, ['ja', 'en'], true)) $_SESSION['nm_lang'] = $q;
    }
    if (isset($_GET['theme'])) {
        $q = strtolower((string)$_GET['theme']);
        if (in_array($q, ['dark', 'light'], true)) $_SESSION['nm_theme'] = $q;
    }

    $lang  = (string)($_SESSION['nm_lang'] ?? 'ja');
    $theme = (string)($_SESSION['nm_theme'] ?? 'dark');

    if (!in_array($lang, ['ja', 'en'], true)) $lang = 'ja';
    if (!in_array($theme, ['dark', 'light'], true)) $theme = 'dark';

    $_SESSION['nm_lang'] = $lang;
    $_SESSION['nm_theme'] = $theme;

    return ['lang' => $lang, 'theme' => $theme];
}

/**
 * 現在の lang/theme を付けてURL生成
 * $path は "/login.php" のように先頭スラッシュ推奨
 */
function nm_ui_url(string $path, ?string $lang = null, ?string $theme = null): string {
    nm_auth_start_session();
    $base = nm_auth_base_url();

    $lang  = $lang  ?? (string)($_SESSION['nm_lang'] ?? 'ja');
    $theme = $theme ?? (string)($_SESSION['nm_theme'] ?? 'dark');

    if (!in_array($lang, ['ja', 'en'], true)) $lang = 'ja';
    if (!in_array($theme, ['dark', 'light'], true)) $theme = 'dark';

    $q = http_build_query(['lang' => $lang, 'theme' => $theme]);
    return $base . $path . '?' . $q;
}

/**
 * トグル用URLセット
 */
function nm_ui_toggle_urls(string $path, string $lang, string $theme): array {
    return [
        'langJa' => nm_ui_url($path, 'ja', $theme),
        'langEn' => nm_ui_url($path, 'en', $theme),
        'dark'   => nm_ui_url($path, $lang, 'dark'),
        'light'  => nm_ui_url($path, $lang, 'light'),
    ];
}