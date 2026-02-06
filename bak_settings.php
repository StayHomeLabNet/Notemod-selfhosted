<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_common.php';

/*
 * bak_settings.php
 * - config/config.api.php の CLEANUP_BACKUP_ENABLED をUIで編集（チェックボックス）
 * - バックアップファイル数 / 最新バックアップ作成日時を表示
 * - 「n個残す」を指定して、最新からn個を残し残りを削除（n=0で全削除）
 * - ★追加：バックアップ一覧から data.json をリストア
 * - UIは account.php と揃える（JP/EN, Dark/Light、右上トグル、上部にログイン中ユーザー名）
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
$configDir     = __DIR__ . '/config';
$configApiPath = $configDir . '/config.api.php';

// --------------------
// i18n (JP/EN)
// --------------------
$t = [
  'ja' => [
    'title' => 'バックアップ設定',
    'logged_as' => 'ログイン中:',
    'back' => '戻る',
    'logout' => 'ログアウト',

    'saved' => '保存しました',
    'save_failed' => 'config/config.api.php の保存に失敗しました（権限を確認）',
    'read_failed' => 'config/config.api.php の読み込みに失敗しました',
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

    'note_suffix' => '※ バックアップは DATA_JSON + CLEANUP_BACKUP_SUFFIX + タイムスタンプ の形式を想定しています',
  ],
  'en' => [
    'title' => 'Backup settings',
    'logged_as' => 'Logged in as:',
    'back' => 'Back',
    'logout' => 'Logout',

    'saved' => 'Saved',
    'save_failed' => 'Failed to write config/config.api.php (permission?)',
    'read_failed' => 'Failed to read config/config.api.php',
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

    'note_suffix' => 'Note: Backup filename pattern is assumed as DATA_JSON + CLEANUP_BACKUP_SUFFIX + timestamp.',
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

function nm_php_value_literal(mixed $v): string {
    if (is_bool($v)) return $v ? 'true' : 'false';
    if (is_int($v)) return (string)$v;
    if (is_float($v)) return (string)$v;
    if (is_array($v)) return var_export($v, true);
    return var_export((string)$v, true);
}

/**
 * config/config.api.php を「コメント等を保持したまま」指定キーだけ更新/追記
 * - $updates: ['KEY' => mixed, ...]
 * - null を渡した場合は更新しない（必要なら変更OK）
 */
function nm_update_config_api_values_preserve(string $configApiPath, array $updates): bool
{
    $dir = dirname($configApiPath);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) return false;
    }

    if (!file_exists($configApiPath)) {
        return false;
    }

    $raw = (string)@file_get_contents($configApiPath);
    if ($raw === '') return false;

    $replaceValueAnyType = function(string $content, string $key, $newVal): array {
        if ($newVal === null) return [$content, false];

        $export = var_export($newVal, true);

        // 既存行を上書き（値の型は問わない）
        $pattern = '/([\'"]' . preg_quote($key, '/') . '[\'"]\s*=>\s*)([^,]*)(\s*,)/u';

        if (preg_match($pattern, $content)) {
            $repl = '$1' . $export . '$3';
            $content2 = preg_replace($pattern, $repl, $content, 1);
            return [$content2 ?? $content, true];
        }

        // 無ければ末尾に追記
        $pos = strrpos($content, '];');
        if ($pos === false) {
            $content .= "\n// Appended by bak_settings.php\n";
            $content .= "return [\n    '" . $key . "' => " . $export . ",\n];\n";
            return [$content, true];
        }

        $insert = "    '" . $key . "' => " . $export . ",\n";
        $content2 = substr($content, 0, $pos) . $insert . substr($content, $pos);
        return [$content2, true];
    };

    $changed = false;
    foreach ($updates as $k => $v) {
        [$raw, $c] = $replaceValueAnyType($raw, (string)$k, $v);
        $changed = $changed || $c;
    }

    if (!$changed) return true;

    $ok = @file_put_contents($configApiPath, $raw, LOCK_EX);
    if ($ok === false) return false;

    @chmod($configApiPath, 0644);
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

function nm_backup_files(string $dataJsonPath, string $suffix): array {
    $pattern = $dataJsonPath . $suffix . '*';
    $files = glob($pattern) ?: [];
    $out = [];
    foreach ($files as $f) {
        if (!is_file($f)) continue;
        $mt = @filemtime($f);
        $out[] = ['path' => $f, 'mtime' => $mt ?: 0];
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
function nm_delete_old_backups(string $dataJsonPath, string $suffix, int $keep, int &$deletedCount, array &$failed): void {
    $deletedCount = 0;
    $failed = [];

    $list = nm_backup_files($dataJsonPath, $suffix);
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

/**
 * 原子的にファイルを置き換える（tmpへ書いてから rename）
 * - $dst と同じディレクトリに tmp を作ることで rename を原子的にしやすい
 */
function nm_atomic_replace_from_file(string $src, string $dst): bool {
    if (!is_file($src) || !is_readable($src)) return false;

    $dir = dirname($dst);
    if (!is_dir($dir)) return false;

    $tmp = $dst . '.tmp-' . bin2hex(random_bytes(4));

    $in  = @fopen($src, 'rb');
    if (!$in) return false;

    $out = @fopen($tmp, 'wb');
    if (!$out) { @fclose($in); return false; }

    $ok = true;
    if (function_exists('stream_copy_to_stream')) {
        $copied = @stream_copy_to_stream($in, $out);
        if ($copied === false) $ok = false;
    } else {
        while (!feof($in)) {
            $buf = fread($in, 1024 * 1024);
            if ($buf === false) { $ok = false; break; }
            if (fwrite($out, $buf) === false) { $ok = false; break; }
        }
    }

    @fclose($in);
    @fclose($out);

    if (!$ok) { @unlink($tmp); return false; }

    @chmod($tmp, 0644);

    // Windows互換：同名があると rename が失敗する環境があるので、先に消してみる
    if (file_exists($dst)) {
        @unlink($dst);
    }

    if (!@rename($tmp, $dst)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

/**
 * data.json のバックアップを作成（既存の suffix 形式で作る）
 */
function nm_backup_current_datajson(string $dataJsonPath, string $suffix, string &$backupPath, string $tzName): bool {
    $backupPath = '';
    if (!is_file($dataJsonPath) || !is_readable($dataJsonPath)) return false;

    try {
    $ts = (new DateTime('now', new DateTimeZone($tzName)))->format('Ymd-His');
} catch (Throwable $e) {
    $ts = date('Ymd-His');
}
$backupPath = $dataJsonPath . $suffix . $ts;

    if (file_exists($backupPath)) {
        $backupPath = $dataJsonPath . $suffix . $ts . '-' . bin2hex(random_bytes(2));
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
$cfgAuth = nm_auth_load();
$user = (string)($cfgAuth['USERNAME'] ?? '');

// --------------------
// UI links (toggles + back/logout)
// --------------------
$u = nm_ui_toggle_urls('/bak_settings.php', $lang, $theme);
$logoutUrl = nm_ui_url('/logout.php');

$base = nm_auth_base_url();
$backUrl = rtrim($base, '/') . '/';


// --------------------
// TIMEZONE from config/config.php (for backup filenames & display)
// --------------------
$tzName = 'Pacific/Auckland';
$__nm_cfg_ret = null;
$__nm_cfg_path = __DIR__ . '/config/config.php';

// Prefer already-defined TIMEZONE (e.g., loaded by auth_common.php)
// If not defined, try to load config/config.php safely.
if (!defined('TIMEZONE') && is_file($__nm_cfg_path)) {
    $__nm_cfg_ret = @include_once $__nm_cfg_path;
}

if (defined('TIMEZONE')) {
    $tzName = (string)TIMEZONE;
} elseif (isset($TIMEZONE) && is_string($TIMEZONE) && $TIMEZONE !== '') {
    $tzName = (string)$TIMEZONE;
} elseif (is_array($__nm_cfg_ret) && isset($__nm_cfg_ret['TIMEZONE'])) {
    $tzName = (string)$__nm_cfg_ret['TIMEZONE'];
}

unset($__nm_cfg_ret, $__nm_cfg_path);

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
$dataJson = (string)($cfgApi['DATA_JSON'] ?? (dirname(__DIR__) . '/notemod-data/data.json'));
$suffix   = (string)($cfgApi['CLEANUP_BACKUP_SUFFIX'] ?? '.bak-');

$prefEnabled = (bool)($cfgApi['CLEANUP_BACKUP_ENABLED'] ?? true);
$prefKeep    = (int)($cfgApi['CLEANUP_BACKUP_KEEP'] ?? 20);

// --------------------
// Handle POST
// --------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $err === '') {
    $mode = (string)($_POST['mode'] ?? '');

    // ----------------
    // 1) Save / Delete は「設定更新」を伴う
    // ----------------
    if ($mode === 'save' || $mode === 'delete') {

        $newEnabled = nm_bool_from_post('CLEANUP_BACKUP_ENABLED');
        $newKeep    = nm_int_from_post('CLEANUP_BACKUP_KEEP', $prefKeep);
        if ($newKeep < 0) $newKeep = 0;

        // まず保存（キーが無ければ追記、あれば上書き）
        $updates = [
            'CLEANUP_BACKUP_ENABLED' => $newEnabled,
            'CLEANUP_BACKUP_KEEP'    => $newKeep,
        ];

        if (!nm_update_config_api_values_preserve($configApiPath, $updates)) {
            $err = $t[$lang]['save_failed'];
        } else {
            $msg = $t[$lang]['saved'];

            // 削除モード
            if ($mode === 'delete') {
                $deletedCount = 0;
                $failed = [];
                nm_delete_old_backups($dataJson, $suffix, $newKeep, $deletedCount, $failed);

                if ($deletedCount === 0 && empty($failed)) {
                    $msg = $t[$lang]['nothing_to_delete'];
                } else {
                    $msg = $t[$lang]['deleted'] . ': ' . $deletedCount;
                    if (!empty($failed)) {
                        $err = $t[$lang]['delete_failed'] . "\n" . implode("\n", $failed);
                    }
                }
            }

            // 再読込して表示反映
            $cfgApi = nm_read_php_config_array($configApiPath);
            $prefEnabled = (bool)($cfgApi['CLEANUP_BACKUP_ENABLED'] ?? $newEnabled);
            $prefKeep    = (int)($cfgApi['CLEANUP_BACKUP_KEEP'] ?? $newKeep);

            $dataJson = (string)($cfgApi['DATA_JSON'] ?? $dataJson);
            $suffix   = (string)($cfgApi['CLEANUP_BACKUP_SUFFIX'] ?? $suffix);
        }
    }

    // ----------------
    
    // ----------------
    // 1.5) Backup now (manual)
    // ----------------
    if ($mode === 'backup_now' && $err === '') {
        $manualBackupPath = '';
        if (nm_backup_current_datajson($dataJson, $suffix, $manualBackupPath, $tzName)) {
            $msg = $t[$lang]['backup_now_done'] . ': ' . basename($manualBackupPath);
        } else {
            $err = $t[$lang]['backup_now_failed'];
        }
    }

// 2) Restore は「data.json の復元」だけ行い、設定は変更しない
    // ----------------
    if ($mode === 'restore' && $err === '') {

        $restoreName = trim((string)($_POST['restore_file'] ?? ''));
        if ($restoreName === '') {
            $err = $t[$lang]['restore_select_required'];
        } else {

            // バックアップ一覧から選択名を実パスへ解決（パストラバーサル防止）
            $list = nm_backup_files($dataJson, $suffix);
            $map = [];
            foreach ($list as $item) {
                $map[basename((string)$item['path'])] = (string)$item['path'];
            }

            $src = $map[$restoreName] ?? '';
            if ($src === '' || !is_file($src)) {
                $err = $t[$lang]['restore_failed'];
            } else {

                // 復元前に「現在の data.json」をバックアップ
                $preBackupPath = '';
                $preOk = nm_backup_current_datajson($dataJson, $suffix, $preBackupPath, $tzName);
                if (!$preOk) {
                    $err = $t[$lang]['restore_failed'];
                } else {
                    // 選択されたバックアップを data.json に上書き（原子的）
                    $ok = nm_atomic_replace_from_file($src, $dataJson);
                    if (!$ok) {
                        $err = $t[$lang]['restore_failed'];
                    } else {
                        $msg = $t[$lang]['restore_done'];
                        if ($preBackupPath !== '') {
                            $msg .= " (" . $t[$lang]['restore_backup_created'] . ": " . basename($preBackupPath) . ")";
                        }
                    }
                }
            }
        }

        // 表示用：設定は再読込（復元で設定は変えてないが、OPcache対策も兼ねる）
        $cfgApi = nm_read_php_config_array($configApiPath);
        $prefEnabled = (bool)($cfgApi['CLEANUP_BACKUP_ENABLED'] ?? $prefEnabled);
        $prefKeep    = (int)($cfgApi['CLEANUP_BACKUP_KEEP'] ?? $prefKeep);

        $dataJson = (string)($cfgApi['DATA_JSON'] ?? $dataJson);
        $suffix   = (string)($cfgApi['CLEANUP_BACKUP_SUFFIX'] ?? $suffix);
    }
}

// --------------------
// Backup info
// --------------------
$backups = nm_backup_files($dataJson, $suffix);
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
      padding-right: 120px;
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
      opacity:.96;
      z-index: 5;
    }
    .toggle-row{ display:flex; gap:6px; align-items:center; justify-content:flex-end; }
    .toggle-row span{ font-size:10px; color:var(--muted); margin-right:2px; line-height:1; }
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
      .toggles{ top:6px; right:6px; transform: scale(.82); }
      .head{ padding-right: 18px; }
    }

    .action-row{
      display:flex;
      gap:10px;
      justify-content:flex-end;
      flex-wrap:wrap;
      margin-top: -4px;
    }

    .action-row.bottom-left{
      justify-content:flex-start;
      margin-top: 14px;
    }
    .action-row a{
      font-size:13px;
      padding:8px 10px;
      border-radius:12px;
      background: color-mix(in srgb, var(--card2) 70%, transparent);
      border:1px solid color-mix(in srgb, var(--line) 120%, transparent);
      text-decoration:none;
    }
    .action-row a:hover{ text-decoration:none; filter: brightness(1.02); }

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

      <div class="toggles">
        <div class="toggle-row">
          <span><?=htmlspecialchars($t[$lang]['lang_label'] ?? 'Language', ENT_QUOTES, 'UTF-8')?></span>
          <div class="pill">
            <a href="<?=htmlspecialchars($u['langJa'], ENT_QUOTES, 'UTF-8')?>" class="<?= $lang==='ja'?'active':'' ?>">JP</a>
            <a href="<?=htmlspecialchars($u['langEn'], ENT_QUOTES, 'UTF-8')?>" class="<?= $lang==='en'?'active':'' ?>">EN</a>
          </div>
        </div>

        <div class="toggle-row">
          <span><?=htmlspecialchars($t[$lang]['theme_label'] ?? 'Theme', ENT_QUOTES, 'UTF-8')?></span>
          <div class="pill">
            <a href="<?=htmlspecialchars($u['dark'], ENT_QUOTES, 'UTF-8')?>" class="<?= $theme==='dark'?'active':'' ?>"><?=htmlspecialchars($t[$lang]['dark'] ?? 'Dark', ENT_QUOTES, 'UTF-8')?></a>
            <a href="<?=htmlspecialchars($u['light'], ENT_QUOTES, 'UTF-8')?>" class="<?= $theme==='light'?'active':'' ?>"><?=htmlspecialchars($t[$lang]['light'] ?? 'Light', ENT_QUOTES, 'UTF-8')?></a>
          </div>
        </div>
      </div>

      <div class="head">
        <div>
          <div class="title"><?=htmlspecialchars($t[$lang]['title'], ENT_QUOTES, 'UTF-8')?></div>
          <div class="meta"><?=htmlspecialchars($t[$lang]['logged_as'], ENT_QUOTES, 'UTF-8')?> <b><?=htmlspecialchars($user, ENT_QUOTES, 'UTF-8')?></b></div>
        </div>
      </div>

      <div class="body">
        <?php if ($msg): ?><div class="notice ok"><?=htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
        <?php if ($err): ?><div class="notice bad"><?=htmlspecialchars($err, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>

        <div class="action-row">
          <a href="<?=htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['back'], ENT_QUOTES, 'UTF-8')?></a>
          <a href="<?=htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['logout'], ENT_QUOTES, 'UTF-8')?></a>
        </div>

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

            <form method="post" class="inline-form" style="margin: 8px 0 14px 0;">
              <input type="hidden" name="mode" value="backup_now">
              <button type="submit" class="btn" style="width:auto; padding:10px 12px; font-size:13px; border-radius:12px;">
                <?=htmlspecialchars($t[$lang]['backup_now'], ENT_QUOTES, 'UTF-8')?>
              </button>
            </form>

            <label><?=htmlspecialchars($t[$lang]['keep_label'], ENT_QUOTES, 'UTF-8')?></label>
            <input type="number" min="0" step="1" name="CLEANUP_BACKUP_KEEP"
                   value="<?=htmlspecialchars((string)$prefKeep, ENT_QUOTES, 'UTF-8')?>">

            <div class="notice" style="margin-top:10px;"><?=htmlspecialchars($t[$lang]['keep_help'], ENT_QUOTES, 'UTF-8')?></div>
            <div class="notice" style="margin-top:10px;"><?=htmlspecialchars($t[$lang]['note_suffix'], ENT_QUOTES, 'UTF-8')?></div>

            <button class="btn" type="submit"><?=htmlspecialchars($t[$lang]['btn_save'], ENT_QUOTES, 'UTF-8')?></button>
          </form>

          <form method="post" style="margin-top:10px;">
            <input type="hidden" name="mode" value="delete">
            <input type="hidden" name="CLEANUP_BACKUP_ENABLED" value="<?= $prefEnabled ? '1' : '0' ?>">
            <input type="hidden" name="CLEANUP_BACKUP_KEEP" value="<?=htmlspecialchars((string)$prefKeep, ENT_QUOTES, 'UTF-8')?>">

            <button class="btn btn-danger" type="submit"
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
                      $dt = $mt > 0 ? date('Y-m-d H:i:s', $mt) : '-';
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

            <button class="btn" type="submit"
              onclick="return confirm('<?=htmlspecialchars($t[$lang]['confirm_restore'], ENT_QUOTES, 'UTF-8')?>');"
              style="margin-top:12px;">
              <?=htmlspecialchars($t[$lang]['btn_restore'], ENT_QUOTES, 'UTF-8')?>
            </button>
          </form>

          <script>
            // keep入力を変更したら、削除フォームのhiddenにも即反映
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

          <div class="action-row bottom-left">
            <a href="<?=htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['back'], ENT_QUOTES, 'UTF-8')?></a>
            <a href="<?=htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($t[$lang]['logout'], ENT_QUOTES, 'UTF-8')?></a>
          </div>

        </div>
      </div>
    </div>
  </div>
</body>
</html>