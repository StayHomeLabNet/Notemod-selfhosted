<?php
// logger.php

// 多重実行防止
if (defined('LOGGER_ALREADY_RAN')) {
    return;
}
define('LOGGER_ALREADY_RAN', true);

require_once __DIR__ . '/auth_common.php';
require_once __DIR__ . '/data_crypto.php';

$currentDirUser = null;
if (function_exists('nm_get_current_dir_user')) {
    $currentDirUser = nm_get_current_dir_user();
}
if ($currentDirUser === null || $currentDirUser === '') {
    $currentDirUser = normalize_username($_REQUEST['user'] ?? $_GET['user'] ?? $_POST['user'] ?? '');
}
if ($currentDirUser === null || $currentDirUser === '') {
    return;
}

// -----------------------------
// 0) config.php から設定を読む
// -----------------------------
$cfg = [];
$configFile = function_exists('nm_config_path')
    ? nm_config_path($currentDirUser)
    : (__DIR__ . '/config/' . $currentDirUser . '/config.php');
if (file_exists($configFile)) {
    $tmp = require $configFile;
    if (is_array($tmp)) {
        $cfg = $tmp;
    }
}

// TIMEZONE（無ければ既定）
$timezone = (string)($cfg['TIMEZONE'] ?? $cfg['timezone'] ?? (defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Pacific/Auckland'));
if ($timezone === '') {
    $timezone = 'Asia/Tokyo';
}
@date_default_timezone_set($timezone);

// ★logger のON/OFF（個別）
$logFileEnabled    = (bool)($cfg['LOGGER_FILE_ENABLED'] ?? true);
$logNotemodEnabled = (bool)($cfg['LOGGER_NOTEMOD_ENABLED'] ?? true);

// ★最大行数（0以下なら無制限）
$maxFileLines    = (int)($cfg['LOGGER_FILE_MAX_LINES'] ?? 0);
$maxNotemodLines = (int)($cfg['LOGGER_NOTEMOD_MAX_LINES'] ?? 0);

// 両方OFFなら何もしない
if (!$logFileEnabled && !$logNotemodEnabled) {
    return;
}

// -----------------------------
// 互換：PHP7系でも動くように polyfill
// -----------------------------
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

// -----------------------------
// 共通：フォルダを作って .htaccess を無ければ作成
// -----------------------------
if (!function_exists('nm_ensure_log_dir_and_htaccess')) {
    function nm_ensure_log_dir_and_htaccess(string $dir, bool $debug = false, string $debugFile = ''): bool
    {
        $dbg = function(string $msg, array $ctx = []) use ($debug, $debugFile) {
            if (!$debug) return;
            $line = '[' . date('c') . '] ' . $msg . ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            if ($debugFile !== '') {
                @file_put_contents($debugFile, $line, FILE_APPEND | LOCK_EX);
            } else {
                error_log('logger.php: ' . $line);
            }
        };

        $dir = rtrim($dir, "/\\");
        $dbg('ensure_dir_start', ['dir' => $dir]);

        if (file_exists($dir) && !is_dir($dir)) {
            $dbg('path_exists_but_not_dir', ['dir' => $dir]);
            error_log('logger.php: log path exists but is not a directory: ' . $dir);
            return false;
        }

        $parent = dirname($dir);
        $dbg('parent_check', ['parent' => $parent, 'parent_exists' => file_exists($parent), 'parent_is_dir' => is_dir($parent)]);

        if (!is_dir($dir)) {
            $ok = mkdir($dir, 0755, true);
            if (!$ok) {
                $dbg('mkdir_failed', ['dir' => $dir, 'err' => error_get_last()]);

                $ok2 = @mkdir($dir, 0775, true);
                if (!$ok2) {
                    $dbg('mkdir_failed_0775', ['dir' => $dir, 'err' => error_get_last()]);
                    error_log('logger.php: failed to create log directory: ' . $dir);
                    return false;
                }
            }
        }

        clearstatcache(true, $dir);
        if (!is_dir($dir)) {
            $dbg('dir_still_missing_after_mkdir', ['dir' => $dir]);
            error_log('logger.php: directory creation reported success but directory not found: ' . $dir);
            return false;
        }

        $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccess)) {
            $content = <<<HT
<IfModule mod_authz_core.c>
  Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
  Order allow,deny
  Deny from all
</IfModule>

HT;

            $tmp = $htaccess . '.tmp-' . bin2hex(random_bytes(4));
            $w = @file_put_contents($tmp, $content, LOCK_EX);
            if ($w === false) {
                $dbg('htaccess_write_failed', ['tmp' => $tmp, 'err' => error_get_last()]);
                @unlink($tmp);
            } else {
                @chmod($tmp, 0644);
                if (!@rename($tmp, $htaccess)) {
                    $dbg('htaccess_rename_failed', ['tmp' => $tmp, 'dst' => $htaccess, 'err' => error_get_last()]);
                    @unlink($tmp);
                } else {
                    $dbg('htaccess_created', ['path' => $htaccess]);
                }
            }
        } else {
            $dbg('htaccess_exists', ['path' => $htaccess]);
        }

        $dbg('ensure_dir_done', ['dir' => $dir, 'writable' => is_writable($dir)]);
        return true;
    }
}

if (!function_exists('nm_keep_last_n_lines_in_file')) {
    function nm_keep_last_n_lines_in_file(string $path, int $maxLines): void
    {
        if ($maxLines <= 0) return;
        if (!file_exists($path) || !is_readable($path) || !is_writable($path)) return;
        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) return;
        if (count($lines) <= $maxLines) return;
        $tail = array_slice($lines, -$maxLines);
        $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
        $ok = @file_put_contents($tmp, implode("\n", $tail) . "\n", LOCK_EX);
        if ($ok === false) {
            @unlink($tmp);
            return;
        }
        @chmod($tmp, 0644);
        @rename($tmp, $path);
    }
}

if (!function_exists('nm_keep_latest_n_lines_in_notemod_log_content')) {
    function nm_keep_latest_n_lines_in_notemod_log_content(string $html, int $maxLines): string
    {
        if ($maxLines <= 0) return $html;
        $s = str_replace("\r\n", "\n", $html);
        $s = str_replace("\r", "\n", $s);
        $parts = preg_split("/<br>\n|<br\s*\/?>\n?/i", $s);
        if (!is_array($parts)) return $html;
        while (!empty($parts) && trim((string) end($parts)) === '') {
            array_pop($parts);
        }
        if (count($parts) <= $maxLines) return $html;
        $kept = array_slice($parts, 0, $maxLines);
        $out = '';
        foreach ($kept as $line) {
            $out .= $line . "<br>\n";
        }
        return $out;
    }
}

if (!function_exists('getClientIp')) {
    function getClientIp(): string
    {
        $keys = [
            'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR',
        ];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ipList = explode(',', $_SERVER[$key]);
                foreach ($ipList as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }
        return 'UNKNOWN';
    }
}

if (!function_exists('uaSummary')) {
    function uaSummary(string $ua): string
    {
        $ua_l = strtolower($ua);
        if (str_contains($ua_l, 'shortcuts') || str_contains($ua_l, 'cfnetwork') || str_contains($ua_l, 'darwin')) return 'API / Shortcut';
        if (str_contains($ua_l, 'curl/')) return 'Tool / curl';
        if (str_contains($ua_l, 'wget/')) return 'Tool / wget';
        if (str_contains($ua_l, 'postmanruntime')) return 'Tool / Postman';
        if (str_contains($ua_l, 'googlebot')) return 'Bot / Googlebot';
        if (str_contains($ua_l, 'bingbot')) return 'Bot / Bingbot';
        if (str_contains($ua_l, 'duckduckbot')) return 'Bot / DuckDuckGo';
        if (str_contains($ua_l, 'bot') || str_contains($ua_l, 'crawler') || str_contains($ua_l, 'spider')) return 'Bot';

        if (str_contains($ua_l, 'iphone')) $os = 'iPhone';
        elseif (str_contains($ua_l, 'ipad')) $os = 'iPad';
        elseif (str_contains($ua_l, 'android')) $os = 'Android';
        elseif (str_contains($ua_l, 'windows nt')) $os = 'Windows';
        elseif (str_contains($ua_l, 'macintosh') || str_contains($ua_l, 'mac os x')) $os = 'Mac';
        elseif (str_contains($ua_l, 'linux')) $os = 'Linux';
        else $os = 'Other';

        if (str_contains($ua_l, 'edg/')) $browser = 'Edge';
        elseif (str_contains($ua_l, 'opr/') || str_contains($ua_l, 'opera')) $browser = 'Opera';
        elseif (str_contains($ua_l, 'chrome/') && !str_contains($ua_l, 'edg/') && !str_contains($ua_l, 'opr/')) $browser = 'Chrome';
        elseif (str_contains($ua_l, 'safari/') && !str_contains($ua_l, 'chrome/')) $browser = 'Safari';
        elseif (str_contains($ua_l, 'firefox/')) $browser = 'Firefox';
        else $browser = 'Browser';

        return "{$os} / {$browser}";
    }
}

if (!function_exists('uaEmoji')) {
    function uaEmoji(string $summary): string
    {
        if (str_starts_with($summary, 'API')) return '?';
        if (str_starts_with($summary, 'Bot')) return '?';
        if (str_starts_with($summary, 'Tool')) return '?';
        if (str_starts_with($summary, 'iPhone') || str_starts_with($summary, 'iPad') || str_starts_with($summary, 'Android')) return '?';
        if (str_starts_with($summary, 'Windows') || str_starts_with($summary, 'Mac') || str_starts_with($summary, 'Linux')) return '?';
        return '?';
    }
}

if (!function_exists('nm_is_bot_ua')) {
    function nm_is_bot_ua(string $ua): bool
    {
        $l = strtolower($ua);
        return ($ua !== '' && (strpos($l, 'bot') !== false || strpos($l, 'crawler') !== false || strpos($l, 'spider') !== false));
    }
}

if (!function_exists('nm_ip_first_seen_notify')) {
    function nm_ip_first_seen_notify(string $ip, array $ctx, array $cfg): void
    {
        $enabled = (bool)($cfg['IP_ALERT_ENABLED'] ?? false);
        if (!$enabled || $ip === '' || $ip === 'UNKNOWN') return;

        $ignore = $cfg['IP_ALERT_IGNORE_IPS'] ?? [];
        $ignoreList = [];
        if (is_string($ignore)) {
            foreach (explode(',', $ignore) as $x) {
                $x = trim($x);
                if ($x !== '') $ignoreList[] = $x;
            }
        } elseif (is_array($ignore)) {
            foreach ($ignore as $x) {
                $x = trim((string)$x);
                if ($x !== '') $ignoreList[] = $x;
            }
        }
        if (in_array($ip, $ignoreList, true)) return;

        $ignoreBots = (bool)($cfg['IP_ALERT_IGNORE_BOTS'] ?? true);
        if ($ignoreBots && !empty($ctx['ua_raw']) && nm_is_bot_ua((string)$ctx['ua_raw'])) return;

        $store = (string)($cfg['IP_ALERT_STORE'] ?? '');
        if ($store === '') {
            $store = function_exists('nm_user_data_dir')
                ? rtrim(nm_user_data_dir($ctx['dir_user'] ?? null), '/\\') . '/_known_ips.json'
                : (__DIR__ . '/notemod-data/' . ($ctx['dir_user'] ?? '') . '/_known_ips.json');
        }

        $dir = dirname($store);
        nm_ensure_log_dir_and_htaccess($dir);

        $known = [];
        if (file_exists($store)) {
            $raw = (string)@file_get_contents($store);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $known = $decoded;
        }
        if (isset($known[$ip])) return;

        $known[$ip] = [
            'first_seen' => date('c'),
            'uri'        => (string)($ctx['uri'] ?? ''),
            'method'     => (string)($ctx['method'] ?? ''),
            'ua'         => (string)($ctx['ua_short'] ?? ''),
        ];

        $tmp = $store . '.tmp-' . bin2hex(random_bytes(4));
        $ok = @file_put_contents($tmp, json_encode($known, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
        if ($ok !== false) {
            @chmod($tmp, 0644);
            @rename($tmp, $store);
        } else {
            @unlink($tmp);
            return;
        }

        $to = trim((string)($cfg['IP_ALERT_TO'] ?? ''));
        if ($to === '') return;
        $from = trim((string)($cfg['IP_ALERT_FROM'] ?? ''));
        $subject = (string)($cfg['IP_ALERT_SUBJECT'] ?? 'Notemod: First-time IP access');

        $host = (string)($ctx['host'] ?? '');
        $lines = [];
        $lines[] = 'A new IP accessed your Notemod.';
        $lines[] = '';
        $lines[] = 'Time   : ' . (string)($ctx['datetime'] ?? date('c'));
        $lines[] = 'IP     : ' . $ip;
        $lines[] = 'Host   : ' . $host;
        $lines[] = 'Method : ' . (string)($ctx['method'] ?? '');
        $lines[] = 'URI    : ' . (string)($ctx['uri'] ?? '');
        $lines[] = 'UA     : ' . (string)($ctx['ua_raw'] ?? '');
        $lines[] = '';
        $lines[] = 'This notification is sent only once per IP (stored in _known_ips.json).';
        $body = implode("\n", $lines);

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        if ($from !== '') $headers[] = 'From: ' . $from;
        @mail($to, $subject, $body, implode("\r\n", $headers));
    }
}

if (!function_exists('nm_logger_locked_load_notemod')) {
    function nm_logger_locked_load_notemod(string $path)
    {
        $lockPath = $path . '.lock';
        $lockFp = @fopen($lockPath, 'c');
        if ($lockFp === false) {
            return [null, null, 'lock_open_failed'];
        }
        if (!@flock($lockFp, LOCK_EX)) {
            @fclose($lockFp);
            return [null, null, 'lock_failed'];
        }

        list($ok, $data, $reason) = nm_try_load_data_file($path);
        if (!$ok || !is_array($data)) {
            @flock($lockFp, LOCK_UN);
            @fclose($lockFp);
            return [null, null, $reason ?: 'load_failed'];
        }

        return [$lockFp, $data, null];
    }
}

if (!function_exists('nm_logger_locked_save_notemod')) {
    function nm_logger_locked_save_notemod($lockFp, string $path, array $data): bool
    {
        $ok = nm_save_data_file($path, $data);
        @flock($lockFp, LOCK_UN);
        @fclose($lockFp);
        return $ok;
    }
}

$datetime = date('Y-m-d H:i:s');
$ip       = getClientIp();
$uri      = $_SERVER['REQUEST_URI'] ?? '-';
$method   = $_SERVER['REQUEST_METHOD'] ?? '';
$host     = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
$uaRaw    = $_SERVER['HTTP_USER_AGENT'] ?? '';
$uaShort  = uaSummary($uaRaw);

$logsDir = function_exists('nm_logs_dir')
    ? nm_logs_dir($currentDirUser)
    : (__DIR__ . '/logs/' . $currentDirUser);
$dataDir = function_exists('nm_user_data_dir')
    ? nm_user_data_dir($currentDirUser)
    : (__DIR__ . '/notemod-data/' . $currentDirUser);
$notemodFile = function_exists('nm_data_json_path')
    ? nm_data_json_path($currentDirUser)
    : ($dataDir . '/data.json');
$knownIpsFile = rtrim($dataDir, '/\\') . '/_known_ips.json';
$debugFile = rtrim($logsDir, '/\\') . '/_logger_debug.log';

nm_ip_first_seen_notify($ip, [
    'datetime' => $datetime,
    'uri'      => $uri,
    'method'   => $method,
    'host'     => $host,
    'ua_raw'   => $uaRaw,
    'ua_short' => $uaShort,
    'dir_user' => $currentDirUser,
], $cfg);

$result = null;

if ($logFileEnabled) {
    if (!nm_ensure_log_dir_and_htaccess($logsDir)) {
        $logFileEnabled = false;
    }

    if ($logFileEnabled) {
        if (!is_writable($logsDir)) {
            error_log('logger.php: log directory is not writable: ' . $logsDir);
            $logFileEnabled = false;
        } else {
            $logFile = rtrim($logsDir, '/\\') . '/access-' . date('Y-m') . '.log';
            $line = sprintf('[%s] %s %s\n', $datetime, $ip, $uri);
            $result = @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
            if ($result === false) {
                error_log('logger.php: failed to write log file: ' . $logFile);
            } else {
                nm_keep_last_n_lines_in_file($logFile, $maxFileLines);
            }
        }
    }

    nm_ensure_log_dir_and_htaccess($logsDir, false, $debugFile);
}

if ($logNotemodEnabled) {
    nm_ensure_log_dir_and_htaccess($dataDir);

    if (is_dir($dataDir)) {
        list($lockFp, $data, $loadError) = nm_logger_locked_load_notemod($notemodFile);
        if ($lockFp !== null && is_array($data)) {
            if (!isset($data['categories'])) $data['categories'] = '[]';
            if (!isset($data['notes'])) $data['notes'] = '[]';

            $categoriesVal = $data['categories'];
            $notesVal = $data['notes'];

            $categoriesArr = is_string($categoriesVal) ? (json_decode($categoriesVal, true) ?: []) : (is_array($categoriesVal) ? $categoriesVal : []);
            $notesArr = is_string($notesVal) ? (json_decode($notesVal, true) ?: []) : (is_array($notesVal) ? $notesVal : []);

            $logsCategoryId = null;
            $logsColor = 'b1653d';
            foreach ($categoriesArr as $cat) {
                if (($cat['name'] ?? '') === 'Logs') {
                    $logsCategoryId = $cat['id'] ?? null;
                    break;
                }
            }

            if ($logsCategoryId === null) {
                $logsCategoryId = (int) round(microtime(true) * 1000);
                $categoriesArr[] = ['id' => $logsCategoryId, 'name' => 'Logs', 'color' => $logsColor];
            }

            $noteTitle = 'access-' . date('Y-m');
            $logsNoteIndex = null;
            foreach ($notesArr as $i => $note) {
                if (($note['title'] ?? '') === $noteTitle && in_array($logsCategoryId, $note['categories'] ?? [], true)) {
                    $logsNoteIndex = $i;
                    break;
                }
            }

            if ($logsNoteIndex === null) {
                $nowZ = gmdate('Y-m-d\\TH:i:s\\Z');
                $notesArr[] = [
                    'id' => (string) round(microtime(true) * 1000),
                    'title' => $noteTitle,
                    'color' => $logsColor,
                    'task_content' => null,
                    'content' => '',
                    'categories' => [$logsCategoryId],
                    'createdAt' => $nowZ,
                    'updatedAt' => $nowZ,
                ];
                $logsNoteIndex = array_key_last($notesArr);
            }

            $uaIcon = uaEmoji($uaShort);
            $humanLine = sprintf('[%s] %s %s %s %s', $datetime, $uaIcon, $uaShort, $ip, $uri);
            $existing = (string)($notesArr[$logsNoteIndex]['content'] ?? '');
            $newContent = htmlspecialchars($humanLine, ENT_QUOTES, 'UTF-8') . "<br>\n" . $existing;
            $newContent = nm_keep_latest_n_lines_in_notemod_log_content($newContent, $maxNotemodLines);

            $notesArr[$logsNoteIndex]['content'] = $newContent;
            $notesArr[$logsNoteIndex]['updatedAt'] = gmdate('Y-m-d\\TH:i:s\\Z');

            $data['categories'] = json_encode($categoriesArr, JSON_UNESCAPED_UNICODE);
            $data['notes'] = json_encode($notesArr, JSON_UNESCAPED_UNICODE);

            if (!nm_logger_locked_save_notemod($lockFp, $notemodFile, $data)) {
                error_log('logger.php: failed to save notemod log data: ' . $notemodFile);
            }
        } elseif ($loadError !== 'missing') {
            error_log('logger.php: failed to load notemod log data: ' . $notemodFile . ' (' . $loadError . ')');
        }
    }
}