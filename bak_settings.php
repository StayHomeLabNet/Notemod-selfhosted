<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_common.php';

/*
 * bak_settings.php
 * - config/config.api.php の CLEANUP_BACKUP_ENABLED をUIで編集（チェックボックス）
 * - バックアップファイル数 / 最新バックアップ作成日時を表示
 * - 「n個残す」を指定して、最新からn個を残し残りを削除（n=0で全削除）
 * - UIは account.php と揃える（JP/EN, Dark/Light、右上トグル、上部にログイン中ユーザー名）
 */

nm_auth_require_login();
header('Content-Type: text/html; charset=utf-8');

// ★ 共通化：lang/theme の確定（GET→SESSION反映含む）
$ui    = nm_ui_bootstrap();
$lang  = $ui['lang'] ?? 'ja';
$theme = $ui['theme'] ?? 'dark';

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

    'lang_label'  => 'Language',
    'theme_label' => 'Theme',
    'dark'  => 'Dark',
    'light' => 'Light',

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

    'keep_label' => '最新から n個のバックアップを残す（n=0で全削除）',
    'keep_help'  => '削除ボタンを押すと、最新からn個を残して残りをすべて削除します',
    'btn_save'   => '保存',
    'btn_delete' => '削除（最新からn個残す）',

    'confirm_delete' => '最新から指定数を残して削除します。よろしいですか？',
    'note_suffix' => '※ バックアップは DATA_JSON + CLEANUP_BACKUP_SUFFIX + タイムスタンプ の形式を想定しています',
  ],
  'en' => [
    'title' => 'Backup settings',
    'logged_as' => 'Logged in as:',
    'back' => 'Back',
    'logout' => 'Logout',

    'lang_label'  => 'Language',
    'theme_label' => 'Theme',
    'dark'  => 'Dark',
    'light' => 'Light',

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

    'keep_label' => 'Keep the latest n backups (n=0 deletes all)',
    'keep_help'  => 'When you press Delete, it keeps the newest n backups and deletes the rest',
    'btn_save'   => 'Save',
    'btn_delete' => 'Delete (keep latest n)',

    'confirm_delete' => 'This will delete backups except the newest n. Continue?',
    'note_suffix' => 'Note: Backup filename pattern is assumed as DATA_JSON + CLEANUP_BACKUP_SUFFIX + timestamp.',
  ],
];

if (!isset($t[$lang])) $lang = 'ja';

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
 * config/config.api.php を「コメント等を保持したまま」指定キーだけ更新/追記
 * - 既に同じキーがあれば上書き（値が数値/true/false/stringでもOK）
 */
function nm_update_config_api_values_preserve(string $configApiPath, array $updates): bool
{
    $dir = dirname($configApiPath);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) return false;
    }

    // 無い場合は最小テンプレを作る（壊れ防止）
    if (!file_exists($configApiPath)) {
        $tpl = "<?php\nreturn [\n];\n";
        if (@file_put_contents($configApiPath, $tpl, LOCK_EX) === false) return false;
        @chmod($configApiPath, 0644);
    }

    $raw = (string)@file_get_contents($configApiPath);
    if ($raw === '') return false;

    $replaceValueAnyType = function(string $content, string $key, $newVal): array {
        if ($newVal === null) return [$content, false];

        $export = var_export($newVal, true);

        // 'KEY' => 123, / true, / 'str', / null, など全部拾う（次のカンマまで）
        $pattern = '/([\'"]' . preg_quote($key, '/') . '[\'"]\s*=>\s*)([^,]*)(\s*,)/u';

        if (preg_match($pattern, $content)) {
        $pattern = '/([\'"]' . preg_quote($key, '/') . '[\'"]\s*=>\s*)(.*?)(\s*,\s*(?:\/\/[^\n]*)?\R)/u';
        $repl    = '${1}' . $export . '${3}';
        $content2 = preg_replace($pattern, $repl, $content, 1);
            return [$content2 ?? $content, true];
        }

        // 無ければ末尾に追記（return [ ... ]; の直前）
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
$dataJson = (string)($cfgApi['DATA_JSON'] ?? (__DIR__ . '/notemod-data/data.json'));
$suffix   = (string)($cfgApi['CLEANUP_BACKUP_SUFFIX'] ?? '.bak-');

$prefEnabled = (bool)($cfgApi['CLEANUP_BACKUP_ENABLED'] ?? true);
$prefKeep    = (int)($cfgApi['CLEANUP_BACKUP_KEEP'] ?? 20);

// --------------------
// Handle POST
// --------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $err === '') {
    $mode = (string)($_POST['mode'] ?? '');

    $newEnabled = nm_bool_from_post('CLEANUP_BACKUP_ENABLED');

    // keepはUIから。空や不正は prefKeep を使う
    $newKeep = nm_int_from_post('CLEANUP_BACKUP_KEEP', $prefKeep);
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

// --------------------
// Backup info
// --------------------
$backups = nm_backup_files($dataJson, $suffix);
$backupCount = count($backups);
$latestTs = ($backupCount > 0) ? (int)$backups[0]['mtime'] : 0;
$latestText = ($latestTs > 0) ? date('Y-m-d H:i:s', $latestTs) : $t[$lang]['backup_none'];

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
  </style>
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

          <form method="post" id="saveForm">
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
            <input type="number" min="0" step="1" name="CLEANUP_BACKUP_KEEP" id="keepInput"
                   value="<?=htmlspecialchars((string)$prefKeep, ENT_QUOTES, 'UTF-8')?>">

            <div class="notice" style="margin-top:10px;"><?=htmlspecialchars($t[$lang]['keep_help'], ENT_QUOTES, 'UTF-8')?></div>
            <div class="notice" style="margin-top:10px;"><?=htmlspecialchars($t[$lang]['note_suffix'], ENT_QUOTES, 'UTF-8')?></div>

            <button class="btn" type="submit"><?=htmlspecialchars($t[$lang]['btn_save'], ENT_QUOTES, 'UTF-8')?></button>
          </form>

          <form method="post" id="deleteForm" style="margin-top:10px;">
            <input type="hidden" name="mode" value="delete">
            <input type="hidden" name="CLEANUP_BACKUP_ENABLED" id="delEnabled" value="<?= $prefEnabled ? '1' : '0' ?>">
            <input type="hidden" name="CLEANUP_BACKUP_KEEP" id="delKeep" value="<?=htmlspecialchars((string)$prefKeep, ENT_QUOTES, 'UTF-8')?>">

            <button class="btn btn-danger" type="submit"
              onclick="return confirm('<?=htmlspecialchars($t[$lang]['confirm_delete'], ENT_QUOTES, 'UTF-8')?>');">
              <?=htmlspecialchars($t[$lang]['btn_delete'], ENT_QUOTES, 'UTF-8')?>
            </button>
          </form>

          <script>
            (function(){
              const keepInput = document.getElementById('keepInput');
              const delKeep   = document.getElementById('delKeep');
              const delEnabled= document.getElementById('delEnabled');
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

        </div>
      </div>
    </div>
  </div>
</body>
</html>