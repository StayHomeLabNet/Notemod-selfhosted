<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_common.php';
require_once __DIR__ . '/data_crypto.php';

/*
 * bak_settings.php
 * - config/<USER_NAME>/config.api.php の CLEANUP_BACKUP_ENABLED をUIで編集（チェックボックス）
 * - バックアップファイル数 / 最新バックアップ作成日時を表示
 * - 「n個残す」を指定して、最新からn個を残し残りを削除（n=0で全削除）
 * - バックアップ一覧から data.json をリストア
 * - 暗号化バックアップ命名:
 *   - data.json.bak-YYYYMMDD-HHMMSS
 *   - data.enc.json.bak-YYYYMMDD-HHMMSS
 * - リストア時は、選択したバックアップを読み込み、
 *   現在の DATA_ENCRYPTION_ENABLED に従って data.json に保存する
 */

nm_auth_require_login();
header('Content-Type: text/html; charset=utf-8');

// ★ 共通化：lang/theme の確定（GET→SESSION反映含む）
$ui    = nm_ui_bootstrap();
$lang  = $ui['lang'];
$theme = $ui['theme'];

// --------------------
// Paths
// --------------------
$loginUser      = (string)nm_get_current_user();
$currentDirUser = (string)nm_get_current_dir_user();
$configDir      = dirname((string)nm_api_config_path($currentDirUser !== '' ? $currentDirUser : null));
$configApiPath  = (string)nm_api_config_path($currentDirUser !== '' ? $currentDirUser : null);
$configPath     = (string)nm_config_path($currentDirUser !== '' ? $currentDirUser : null);

// --------------------
// i18n (JP/EN)
// --------------------
$t = [
  'ja' => [
    'title' => 'バックアップ設定',
    'logged_as' => 'ログイン中:',
    'storage_dir_user' => '保存ディレクトリ:',
    'back' => '戻る',
    'logout' => 'ログアウト',
    'lang_label' => '言語',
    'theme_label' => 'テーマ',
    'dark' => 'Dark',
    'light' => 'Light',

    'saved' => '保存しました',
    'save_failed' => '設定ファイルの保存に失敗しました（権限を確認）',
    'read_failed' => '設定ファイルの読み込みに失敗しました',
    'deleted' => '削除しました',
    'delete_failed' => '削除に失敗したファイルがあります',
    'nothing_to_delete' => '削除対象のバックアップがありません',

    'section_backup' => 'バックアップ',
    'backup_enabled' => 'バックアップを有効（CLEANUP_BACKUP_ENABLED）',
    'backup_count' => '現在のバックアップ数',
    'backup_latest' => '最新バックアップ',
    'backup_none' => 'なし',

    'backup_now' => '今すぐバックアップを作成',
    'backup_now_done' => 'バックアップを作成しました',
    'backup_now_failed' => 'バックアップの作成に失敗しました',
    'keep_label' => '最新から n個のバックアップを残す（n=0で全削除）',
    'keep_help'  => '削除ボタンを押すと、最新からn個を残して残りをすべて削除します',
    'btn_save'   => '保存',
    'btn_delete' => '削除（最新からn個残す）',

    'section_restore' => 'リストア',
    'restore_list' => 'バックアップ一覧',
    'restore_help' => '選択したバックアップファイルを data.json に復元します（復元前に現在の data.json を自動バックアップします）',
    'restore_select_required' => 'リストアするバックアップを選択してください',
    'restore_done' => 'リストアしました',
    'restore_failed' => 'リストアに失敗しました',
    'restore_backup_created' => '復元前バックアップを作成しました',
    'btn_restore' => 'リストア',
    'confirm_restore' => '選択したバックアップを data.json に復元します。よろしいですか？',

    'note_suffix' => '※ バックアップ形式は data.json.bak-YYYYMMDD-HHMMSS / data.enc.json.bak-YYYYMMDD-HHMMSS です',
  ],
  'en' => [
    'title' => 'Backup settings',
    'logged_as' => 'Logged in as:',
    'storage_dir_user' => 'Storage directory user:',
    'back' => 'Back',
    'logout' => 'Logout',
    'lang_label' => 'Language',
    'theme_label' => 'Theme',
    'dark' => 'Dark',
    'light' => 'Light',

    'saved' => 'Saved',
    'save_failed' => 'Failed to write the config file (permission?)',
    'read_failed' => 'Failed to read the config file',
    'deleted' => 'Deleted',
    'delete_failed' => 'Some files could not be deleted',
    'nothing_to_delete' => 'No backup files to delete',

    'section_backup' => 'Backups',
    'backup_enabled' => 'Enable backup (CLEANUP_BACKUP_ENABLED)',
    'backup_count' => 'Current backup count',
    'backup_latest' => 'Latest backup',
    'backup_none' => 'None',

    'backup_now' => 'Create backup now',
    'backup_now_done' => 'Backup created',
    'backup_now_failed' => 'Failed to create backup',
    'keep_label' => 'Keep the latest n backups (n=0 deletes all)',
    'keep_help'  => 'When you press Delete, it keeps the newest n backups and deletes the rest',
    'btn_save'   => 'Save',
    'btn_delete' => 'Delete (keep latest n)',

    'section_restore' => 'Restore',
    'restore_list' => 'Backup list',
    'restore_help' => 'Restores the selected backup file into data.json (it also creates a backup of the current data.json first).',
    'restore_select_required' => 'Please select a backup file to restore',
    'restore_done' => 'Restored',
    'restore_failed' => 'Failed to restore',
    'restore_backup_created' => 'Created a pre-restore backup',
    'btn_restore' => 'Restore',
    'confirm_restore' => 'This will restore the selected backup into data.json. Continue?',

    'note_suffix' => 'Note: Backup filename patterns are data.json.bak-YYYYMMDD-HHMMSS and data.enc.json.bak-YYYYMMDD-HHMMSS.',
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

/**
 * config/<USER_NAME>/config.api.php を指定キーだけ更新/追記
 */
function nm_update_config_api_values_preserve(string $configApiPath, array $updates): bool
{
    $dir = dirname($configApiPath);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) return false;
    }

    $cfg = [];
    if (file_exists($configApiPath)) {
        $cfg = nm_read_php_config_array($configApiPath);
        if (!is_array($cfg)) {
            return false;
        }
    }

    $changed = false;
    foreach ($updates as $k => $v) {
        $k = (string)$k;
        if ($k === '' || $v === null) continue;

        if (!array_key_exists($k, $cfg) || $cfg[$k] !== $v) {
            $cfg[$k] = $v;
            $changed = true;
        }
    }

    if (!$changed && file_exists($configApiPath)) {
        return true;
    }

    $php = "<?php\nreturn " . var_export($cfg, true) . ";\n";
    $tmp = $configApiPath . '.tmp';

    $ok = @file_put_contents($tmp, $php, LOCK_EX);
    if ($ok === false) return false;

    @chmod($tmp, 0644);
    if (!@rename($tmp, $configApiPath)) {
        @unlink($tmp);
        return false;
    }

    @chmod($configApiPath, 0644);
    nm_invalidate_php_cache($configApiPath);
    return true;
}

function nm_int_from_post(string $key, int $default = 0): int {
    $v = trim((string)($_POST[$key] ?? ''));
    if ($v === '') return $default;
    if (!preg_match('/^-?\d+$/', $v)) return $default;
    return (int)$v;
}

function nm_bool_from_post(string $key): bool {
    return isset($_POST[$key]) && $_POST[$key] === '1';
}

function nm_backup_files(string $dataJsonPath): array {
    $dir = dirname($dataJsonPath);
    if (!is_dir($dir)) return [];

    $files = @scandir($dir);
    if (!is_array($files)) return [];

    $out = [];
    foreach ($files as $name) {
        if ($name === '.' || $name === '..') continue;
        if (!nm_is_supported_backup_filename($name)) continue;

        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path)) continue;

        $mt = @filemtime($path);
        $out[] = ['path' => $path, 'mtime' => $mt ?: 0];
    }

    usort($out, fn($a, $b) => ($b['mtime'] <=> $a['mtime']));
    return $out;
}

/**
 * Format Unix timestamp in a specific timezone.
 */
function nm_format_ts(int $ts, string $tzName): string {
    if ($ts <= 0) return '-';
    try {
        $dt = new DateTime('@' . $ts);
        $dt->setTimezone(new DateTimeZone($tzName));
        return $dt->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return date('Y-m-d H:i:s', $ts);
    }
}

/**
 * 最新から keep 個残して残り削除（keep=0で全削除）
 */
function nm_delete_old_backups(string $dataJsonPath, int $keep, int &$deletedCount, array &$failed): void {
    $deletedCount = 0;
    $failed = [];

    $list = nm_backup_files($dataJsonPath);
    if (empty($list)) return;

    $keep = max(0, $keep);
    $toDelete = ($keep === 0) ? $list : array_slice($list, $keep);

    foreach ($toDelete as $item) {
        $p = (string)$item['path'];
        if (!is_file($p)) continue;
        if (@unlink($p)) $deletedCount++;
        else $failed[] = $p;
    }
}

/**
 * ファイルサイズを人間向けに表示
 */
function nm_format_bytes(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    $units = ['KB','MB','GB','TB'];
    $v = $bytes / 1024;
    foreach ($units as $u) {
        if ($v < 1024) return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . ' ' . $u;
        $v /= 1024;
    }
    return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . ' PB';
}

function nm_with_data_lock(string $dataJsonPath, callable $callback): bool
{
    $lockPath = $dataJsonPath . '.lock';
    $fp = @fopen($lockPath, 'c+');
    if (!$fp) {
        return false;
    }

    try {
        if (!@flock($fp, LOCK_EX)) {
            @fclose($fp);
            return false;
        }

        $result = $callback();

        @flock($fp, LOCK_UN);
        @fclose($fp);

        return (bool)$result;
    } catch (Throwable $e) {
        @flock($fp, LOCK_UN);
        @fclose($fp);
        return false;
    }
}

function nm_detect_current_data_encrypted(string $dataJsonPath): bool
{
    if (!is_file($dataJsonPath)) {
        return nm_data_encryption_enabled();
    }

    $raw = @file_get_contents($dataJsonPath);
    if ($raw === false || trim($raw) === '') {
        return nm_data_encryption_enabled();
    }

    return nm_is_encrypted_data_json($raw);
}

/**
 * data.json の現在状態をそのままバックアップ
 */
function nm_backup_current_datajson(string $dataJsonPath, string &$backupPath, string $tzName): bool {
    $backupPath = '';
    if (!is_file($dataJsonPath) || !is_readable($dataJsonPath)) return false;

    try {
        $ts = (new DateTime('now', new DateTimeZone($tzName)))->format('Ymd-His');
    } catch (Throwable $e) {
        $ts = date('Ymd-His');
    }

    $isEncrypted = nm_detect_current_data_encrypted($dataJsonPath);
    $backupPath = nm_get_backup_file_path($dataJsonPath, $isEncrypted, $ts);

    if (file_exists($backupPath)) {
        $backupPath .= '-' . bin2hex(random_bytes(2));
    }

    $tmp = $backupPath . '.tmp-' . bin2hex(random_bytes(4));
    if (!@copy($dataJsonPath, $tmp)) { @unlink($tmp); return false; }
    @chmod($tmp, 0644);
    if (!@rename($tmp, $backupPath)) { @unlink($tmp); return false; }
    return true;
}

// --------------------
// Auth / user
// --------------------
$user = $loginUser;
$dirUser = $currentDirUser;

// --------------------
// UI links (toggles + back/logout)
// --------------------
$u = nm_ui_toggle_urls('/bak_settings.php', $lang, $theme);
$logoutUrl = nm_ui_url('/logout.php');

$base = nm_auth_base_url();
$backUrl = rtrim($base, '/') . '/';

// --------------------
// Load config/<USER_NAME>/config.php (array style)
// --------------------
$cfg = [];
if (is_file($configPath)) {
    try {
        $cfg = nm_read_php_config_array($configPath);
    } catch (Throwable $e) {
        $cfg = [];
    }
}
$GLOBALS['cfg'] = is_array($cfg) ? $cfg : [];

// --------------------
// TIMEZONE from config/<USER_NAME>/config.php
// --------------------
$tzName = (string)($GLOBALS['cfg']['TIMEZONE'] ?? 'Pacific/Auckland');

// Validate timezone
try {
    new DateTimeZone($tzName);
} catch (Throwable $e) {
    $tzName = 'Pacific/Auckland';
}

// --------------------
// Load config.api.php
// --------------------
$msg = '';
$err = '';

$cfgApi = [];
try {
    $cfgApi = nm_read_php_config_array($configApiPath);
} catch (Throwable $e) {
    $cfgApi = [];
    $err = $t[$lang]['read_failed'];
}

// Defaults
$dataJson = (string)($cfgApi['DATA_JSON'] ?? nm_data_json_path($currentDirUser !== '' ? $currentDirUser : null));
$prefEnabled = (bool)($cfgApi['CLEANUP_BACKUP_ENABLED'] ?? true);
$prefKeep    = (int)($cfgApi['CLEANUP_BACKUP_KEEP'] ?? 20);

// --------------------
// Handle POST
// --------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $err === '') {
    $mode = (string)($_POST['mode'] ?? '');

    if ($mode === 'save' || $mode === 'delete') {
        $newEnabled = nm_bool_from_post('CLEANUP_BACKUP_ENABLED');
        $newKeep    = nm_int_from_post('CLEANUP_BACKUP_KEEP', $prefKeep);
        if ($newKeep < 0) $newKeep = 0;

        $updates = [
            'CLEANUP_BACKUP_ENABLED' => $newEnabled,
            'CLEANUP_BACKUP_KEEP'    => $newKeep,
        ];

        if (!nm_update_config_api_values_preserve($configApiPath, $updates)) {
            $err = $t[$lang]['save_failed'];
        } else {
            $msg = $t[$lang]['saved'];

            if ($mode === 'delete') {
                $deletedCount = 0;
                $failed = [];
                nm_delete_old_backups($dataJson, $newKeep, $deletedCount, $failed);

                if ($deletedCount === 0 && empty($failed)) {
                    $msg = $t[$lang]['nothing_to_delete'];
                } else {
                    $msg = $t[$lang]['deleted'] . ': ' . $deletedCount;
                    if (!empty($failed)) {
                        $err = $t[$lang]['delete_failed'] . "\n" . implode("\n", $failed);
                    }
                }
            }

            $cfgApi = nm_read_php_config_array($configApiPath);
            $prefEnabled = (bool)($cfgApi['CLEANUP_BACKUP_ENABLED'] ?? $newEnabled);
            $prefKeep    = (int)($cfgApi['CLEANUP_BACKUP_KEEP'] ?? $newKeep);
            $dataJson    = (string)($cfgApi['DATA_JSON'] ?? $dataJson);
        }
    }

    if ($mode === 'backup_now' && $err === '') {
        $manualBackupPath = '';
        $ok = nm_with_data_lock($dataJson, function() use ($dataJson, &$manualBackupPath, $tzName) {
            return nm_backup_current_datajson($dataJson, $manualBackupPath, $tzName);
        });

        if ($ok) {
            $msg = $t[$lang]['backup_now_done'] . ': ' . basename($manualBackupPath);
        } else {
            $err = $t[$lang]['backup_now_failed'];
        }
    }

    if ($mode === 'restore' && $err === '') {
        $restoreName = trim((string)($_POST['restore_file'] ?? ''));
        if ($restoreName === '') {
            $err = $t[$lang]['restore_select_required'];
        } else {
            $list = nm_backup_files($dataJson);
            $map = [];
            foreach ($list as $item) {
                $map[basename((string)$item['path'])] = (string)$item['path'];
            }

            $src = $map[$restoreName] ?? '';
            if ($src === '' || !is_file($src)) {
                $err = $t[$lang]['restore_failed'];
            } else {
                $preBackupPath = '';
                $restoreOk = nm_with_data_lock($dataJson, function() use ($dataJson, $src, &$preBackupPath, $tzName) {
                    if (!nm_backup_current_datajson($dataJson, $preBackupPath, $tzName)) {
                        return false;
                    }
                    list($ok, $reason) = nm_restore_backup_to_mode($src, $dataJson, nm_data_encryption_enabled());
                    return $ok;
                });

                if (!$restoreOk) {
                    $err = $t[$lang]['restore_failed'];
                } else {
                    $msg = $t[$lang]['restore_done'];
                    if ($preBackupPath !== '') {
                        $msg .= " (" . $t[$lang]['restore_backup_created'] . ": " . basename($preBackupPath) . ")";
                    }
                }
            }
        }

        $cfgApi = nm_read_php_config_array($configApiPath);
        $prefEnabled = (bool)($cfgApi['CLEANUP_BACKUP_ENABLED'] ?? $prefEnabled);
        $prefKeep    = (int)($cfgApi['CLEANUP_BACKUP_KEEP'] ?? $prefKeep);
        $dataJson    = (string)($cfgApi['DATA_JSON'] ?? $dataJson);
    }
}

// --------------------
// Backup info
// --------------------
$backups = nm_backup_files($dataJson);
$backupCount = count($backups);
$latestTs = ($backupCount > 0) ? (int)$backups[0]['mtime'] : 0;
$latestText = ($latestTs > 0) ? nm_format_ts($latestTs, $tzName) : $t[$lang]['backup_none'];

?>
<!doctype html>
<html lang="<?=htmlspecialchars($lang, ENT_QUOTES, 'UTF-8')?>" data-theme="<?=htmlspecialchars($theme, ENT_QUOTES, 'UTF-8')?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <script>
    (function(){
      try{
        var u = new URL(window.location.href);
        if(!u.searchParams.has('lang')){
          var sel = localStorage.getItem('selectedLanguage') || '';
          var lang = (sel === 'JA') ? 'ja' : 'en';
          u.searchParams.set('lang', lang);
          window.location.replace(u.toString());
        }
      }catch(e){}
    })();
  </script>
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
      padding-bottom: 10px;
    }
    .title{ font-weight:900; letter-spacing:.3px; font-size:18px; margin:0; }
    .sub{ color:var(--muted); font-size:12px; margin-top:6px; }
    .left{ display:flex; flex-direction:column; gap:4px; }
    .head .right{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }

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

    .btn{
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
      width:auto;
      box-shadow:none;
      margin-top:0;
    }
    .btn:hover{ transform: translateY(-1px); filter:none; border-color: color-mix(in srgb, var(--accent) 38%, var(--line)); text-decoration:none; }
    .btn:active{ transform: translateY(0); filter:none; }
    .btn.red{
      border-color: color-mix(in srgb, var(--danger) 35%, var(--line));
      color: color-mix(in srgb, var(--danger) 75%, var(--text));
      background:color-mix(in srgb, var(--card2) 70%, transparent);
    }
    .btn.red:hover{ border-color: color-mix(in srgb, var(--danger) 60%, var(--line)); }

    .btn-primary{
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
    .btn-primary:hover{ transform: translateY(-1px); filter: brightness(1.03); }
    .btn-primary:active{ transform: translateY(0); filter: brightness(.98); }

    .btn-danger{
      background: linear-gradient(135deg, color-mix(in srgb, var(--danger) 85%, #fff), color-mix(in srgb, var(--danger) 65%, #fb7185));
      box-shadow: 0 10px 25px color-mix(in srgb, var(--danger) 20%, transparent);
      color: color-mix(in srgb, var(--text) 0%, #061021);
    }

    .notice{
      border-radius:16px;
      padding:10px 12px;
      font-size:13px;
      border:1px solid color-mix(in srgb, var(--line) 120%, transparent);
      background: var(--card2);
      line-height: 1.5;
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

    .pill{
      display:inline-flex; gap:10px; align-items:center; flex-wrap:wrap;
      padding:10px 12px;
      border-radius:999px;
      border:1px solid var(--line);
      background:color-mix(in srgb, var(--card2) 75%, transparent);
      font-size:13px;
    }
    .toggles{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .toggle-row{ display:flex; align-items:center; gap:8px; }
    .toggle-row span{ font-size:12px; color:var(--muted); }
    .pill a{
      text-decoration:none; color:var(--muted);
      font-weight:800; font-size:12px;
      padding:6px 8px; border-radius:999px;
      border:1px solid transparent;
      white-space:nowrap;
    }
    .pill a.active{
      color:var(--text);
      border-color: color-mix(in srgb, var(--accent) 45%, var(--line));
      background: color-mix(in srgb, var(--accent) 12%, transparent);
    }
    @media (max-width: 600px){
      .wrap{ width:min(720px, 100%); }
    }

    .row-links{ display:flex; gap:12px; flex-wrap:wrap; padding: 20px 0px;}
    .row-links a{ font-size:13px; color:var(--accent); }

    .check{
      display:flex;
      align-items:flex-start;
      gap:10px;
      padding:10px 12px;
      border-radius:14px;
      border:1px solid color-mix(in srgb, var(--line) 120%, transparent);
      background: color-mix(in srgb, var(--card2) 70%, transparent);
    }
    .check input[type="checkbox"]{
      width:18px; height:18px;
      accent-color: var(--accent);
      margin-top: 2px;
    }
    .check-main{
      display:flex;
      flex-direction:column;
      gap:6px;
      flex:1;
      min-width: 0;
    }
    .check-title{
      font-size:13px;
      color:var(--text);
      line-height: 1.35;
    }
    .check-sub{
      display:flex;
      flex-wrap:wrap;
      gap:10px 14px;
      font-size:12px;
      color:var(--muted);
    }

    .pill-mini{
      display:inline-flex;
      gap:6px;
      align-items:center;
      padding:4px 8px;
      border-radius:999px;
      border:1px solid color-mix(in srgb, var(--line) 120%, transparent);
      background: color-mix(in srgb, var(--card2) 75%, transparent);
      max-width: 100%;
    }
    .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }

    .listbox-wrap{
      border:1px solid color-mix(in srgb, var(--line) 120%, transparent);
      border-radius:14px;
      background: color-mix(in srgb, var(--card2) 75%, transparent);
      padding:8px;
      resize: vertical;
      overflow: auto;
      min-height: 240px;
      max-height: 520px;
    }
    .listbox-wrap select{
      width:100%;
      height:100%;
      min-height: 224px;
      border:0;
      background: transparent;
      color: var(--text);
      outline: none;
      font-size: 12px;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">

      <div class="head">
        <div class="left">
          <h1 class="title"><?=htmlspecialchars($t[$lang]['title'], ENT_QUOTES, 'UTF-8')?></h1>
          <div class="sub">
            <?=htmlspecialchars($t[$lang]['logged_as'], ENT_QUOTES, 'UTF-8')?> <b><?=htmlspecialchars($user, ENT_QUOTES, 'UTF-8')?></b>
            &nbsp;|&nbsp;
            <?=htmlspecialchars($t[$lang]['storage_dir_user'], ENT_QUOTES, 'UTF-8')?> <b><?=htmlspecialchars($dirUser, ENT_QUOTES, 'UTF-8')?></b>
          </div>
        </div>

        <div class="right">
          <a class="btn" href="<?=htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8')?>">← <?=htmlspecialchars($t[$lang]['back'], ENT_QUOTES, 'UTF-8')?></a>
          <a class="btn red" href="<?=htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['logout'], ENT_QUOTES, 'UTF-8')?></a>

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
          <h3><?=htmlspecialchars($t[$lang]['section_backup'], ENT_QUOTES, 'UTF-8')?></h3>

          <form method="post">
            <input type="hidden" name="mode" value="save">

            <label class="check">
              <input type="checkbox" name="CLEANUP_BACKUP_ENABLED" value="1" <?= $prefEnabled ? 'checked' : '' ?>>
              <div class="check-main">
                <div class="check-title"><?=htmlspecialchars($t[$lang]['backup_enabled'], ENT_QUOTES, 'UTF-8')?></div>
                <div class="check-sub">
                  <span class="pill-mini">
                    <?=htmlspecialchars($t[$lang]['backup_count'], ENT_QUOTES, 'UTF-8')?>:
                    <b class="mono"><?=htmlspecialchars((string)$backupCount, ENT_QUOTES, 'UTF-8')?></b>
                  </span>
                  <span class="pill-mini">
                    <?=htmlspecialchars($t[$lang]['backup_latest'], ENT_QUOTES, 'UTF-8')?>:
                    <b class="mono"><?=htmlspecialchars($latestText, ENT_QUOTES, 'UTF-8')?></b>
                  </span>
                </div>
              </div>
            </label>

            <label><?=htmlspecialchars($t[$lang]['keep_label'], ENT_QUOTES, 'UTF-8')?></label>
            <input type="number" min="0" step="1" name="CLEANUP_BACKUP_KEEP"
                   value="<?=htmlspecialchars((string)$prefKeep, ENT_QUOTES, 'UTF-8')?>">

            <div class="notice" style="margin-top:10px;"><?=htmlspecialchars($t[$lang]['keep_help'], ENT_QUOTES, 'UTF-8')?></div>
            <div class="notice" style="margin-top:10px;"><?=htmlspecialchars($t[$lang]['note_suffix'], ENT_QUOTES, 'UTF-8')?></div>

            <button class="btn" type="submit"><?=htmlspecialchars($t[$lang]['btn_save'], ENT_QUOTES, 'UTF-8')?></button>
          </form>

          <form method="post" class="inline-form" style="margin: 8px 0 14px 0;">
            <input type="hidden" name="mode" value="backup_now">
            <button type="submit" class="btn-primary" style="width:100%; padding:10px 12px; font-size:13px; border-radius:12px;">
              <?=htmlspecialchars($t[$lang]['backup_now'], ENT_QUOTES, 'UTF-8')?>
            </button>
          </form>

          <form method="post" style="margin-top:10px;">
            <input type="hidden" name="mode" value="delete">
            <input type="hidden" name="CLEANUP_BACKUP_ENABLED" value="<?= $prefEnabled ? '1' : '0' ?>">
            <input type="hidden" name="CLEANUP_BACKUP_KEEP" value="<?=htmlspecialchars((string)$prefKeep, ENT_QUOTES, 'UTF-8')?>">

            <button class="btn-primary btn-danger" type="submit"
              onclick="return confirm('<?=htmlspecialchars($lang==='ja' ? '最新から指定数を残して削除します。よろしいですか？' : 'This will delete backups except the newest n. Continue?', ENT_QUOTES, 'UTF-8')?>');">
              <?=htmlspecialchars($t[$lang]['btn_delete'], ENT_QUOTES, 'UTF-8')?>
            </button>
          </form>

          <div style="margin-top:18px;"></div>
          <h3><?=htmlspecialchars($t[$lang]['section_restore'], ENT_QUOTES, 'UTF-8')?></h3>
          <div class="notice" style="margin-top:8px;"><?=htmlspecialchars($t[$lang]['restore_help'], ENT_QUOTES, 'UTF-8')?></div>

          <form method="post" id="restoreForm" style="margin-top:10px;">
            <input type="hidden" name="mode" value="restore">

            <label><?=htmlspecialchars($t[$lang]['restore_list'], ENT_QUOTES, 'UTF-8')?></label>

            <div class="listbox-wrap">
              <select name="restore_file" size="10">
                <?php if (empty($backups)): ?>
                  <option value="" disabled>(no backups)</option>
                <?php else: ?>
                  <?php foreach (array_slice($backups, 0, 500) as $b): ?>
                    <?php
                      $p = (string)$b['path'];
                      $bn = basename($p);
                      $mt = (int)($b['mtime'] ?? 0);
                      $dt = $mt > 0 ? nm_format_ts($mt, $tzName) : '-';
                      $sz = @filesize($p);
                      $szText = is_int($sz) ? nm_format_bytes($sz) : '-';
                      $label = $bn . '  |  ' . $dt . '  |  ' . $szText;
                    ?>
                    <option value="<?=htmlspecialchars($bn, ENT_QUOTES, 'UTF-8')?>">
                      <?=htmlspecialchars($label, ENT_QUOTES, 'UTF-8')?>
                    </option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
            </div>

            <button class="btn-primary" type="submit"
              onclick="return confirm('<?=htmlspecialchars($t[$lang]['confirm_restore'], ENT_QUOTES, 'UTF-8')?>');"
              style="width:100%; padding:10px 12px; font-size:13px; border-radius:12px; margin-top:12px;">
              <?=htmlspecialchars($t[$lang]['btn_restore'], ENT_QUOTES, 'UTF-8')?>
            </button>
          </form>

          <script>
            (function(){
              const keepInput = document.querySelector('input[name="CLEANUP_BACKUP_KEEP"]');
              const forms = document.querySelectorAll('form');
              let deleteForm = null;
              for (const f of forms) {
                const m = f.querySelector('input[name="mode"]');
                if (m && m.value === 'delete') deleteForm = f;
              }
              const delKeep = deleteForm ? deleteForm.querySelector('input[name="CLEANUP_BACKUP_KEEP"]') : null;
              const delEnabled = deleteForm ? deleteForm.querySelector('input[name="CLEANUP_BACKUP_ENABLED"]') : null;

              const enabledCheckbox = document.querySelector('input[name="CLEANUP_BACKUP_ENABLED"][type="checkbox"]');

              function sync(){
                if (delKeep && keepInput) delKeep.value = keepInput.value;
                if (delEnabled && enabledCheckbox) delEnabled.value = enabledCheckbox.checked ? '1' : '0';
              }
              if (keepInput) keepInput.addEventListener('input', sync);
              if (enabledCheckbox) enabledCheckbox.addEventListener('change', sync);
              sync();
            })();
          </script>

          <div class="row-links">
            <a class="btn" href="<?=htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8')?>">← <?=htmlspecialchars($t[$lang]['back'], ENT_QUOTES, 'UTF-8')?></a>
            <a class="btn red" href="<?=htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['logout'], ENT_QUOTES, 'UTF-8')?></a>
          </div>

        </div>
      </div>
    </div>
  </div>
</body>
</html>