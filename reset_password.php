<?php
declare(strict_types=1);

$authCommon = __DIR__ . '/auth_common.php';
if (is_file($authCommon)) {
    require_once $authCommon;
}

nm_send_security_headers_html();
header('Content-Type: text/html; charset=utf-8');

if (!function_exists('nm_rp_h')) {
    function nm_rp_h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('nm_rp_lang')) {
    function nm_rp_lang(): string {
        if (function_exists('nm_ui_bootstrap')) {
            $ui = nm_ui_bootstrap();
            return (string)($ui['lang'] ?? 'ja');
        }
        $lang = (string)($_GET['lang'] ?? $_POST['lang'] ?? 'ja');
        return $lang === 'en' ? 'en' : 'ja';
    }
}

if (!function_exists('nm_rp_theme')) {
    function nm_rp_theme(): string {
        if (function_exists('nm_ui_bootstrap')) {
            $ui = nm_ui_bootstrap();
            return (string)($ui['theme'] ?? 'dark');
        }
        $theme = (string)($_GET['theme'] ?? $_POST['theme'] ?? 'dark');
        return $theme === 'light' ? 'light' : 'dark';
    }
}

if (!function_exists('nm_rp_url')) {
    function nm_rp_url(string $path): string {
        if (function_exists('nm_ui_url')) {
            return nm_ui_url($path);
        }
        return $path;
    }
}

if (!function_exists('nm_rp_toggle_urls')) {
    function nm_rp_toggle_urls(string $path, string $lang, string $theme, array $extra = []): array {
        if (function_exists('nm_ui_toggle_urls') && empty($extra)) {
            return nm_ui_toggle_urls($path, $lang, $theme);
        }
        $mk = function(string $newLang, string $newTheme) use ($path, $extra): string {
            $params = array_merge($extra, ['lang' => $newLang, 'theme' => $newTheme]);
            return $path . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        };
        return [
            'langJa' => $mk('ja', $theme),
            'langEn' => $mk('en', $theme),
            'dark'   => $mk($lang, 'dark'),
            'light'  => $mk($lang, 'light'),
        ];
    }
}

if (!function_exists('nm_rp_normalize_username')) {
    function nm_rp_normalize_username(string $value): string {
        if (function_exists('normalize_username')) {
            return (string)normalize_username($value);
        }
        return strtolower(trim($value));
    }
}

if (!function_exists('nm_rp_is_valid_username')) {
    function nm_rp_is_valid_username(string $name): bool {
        return (bool)preg_match('/^[a-z0-9_-]+$/', $name);
    }
}

if (!function_exists('nm_rp_auth_path')) {
    function nm_rp_auth_path(string $dirUser): string {
        return __DIR__ . '/config/' . $dirUser . '/auth.php';
    }
}

if (!function_exists('nm_rp_load_auth_file')) {
    function nm_rp_load_auth_file(string $path): ?array {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }
        $data = include $path;
        return is_array($data) ? $data : null;
    }
}

if (!function_exists('nm_rp_save_auth_file')) {
    function nm_rp_save_auth_file(string $dirUser, array $auth): bool {
        $path = nm_rp_auth_path($dirUser);
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
        $export = var_export($auth, true);
        $php = "<?php\nreturn " . $export . ";\n";
        $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $php, LOCK_EX) === false) {
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

if (!function_exists('nm_rp_find_user_by_user_param')) {
    function nm_rp_find_user_by_user_param(string $input): ?array {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        $dirUser = nm_rp_normalize_username($input);
        if ($dirUser !== '') {
            $path = nm_rp_auth_path($dirUser);
            $auth = nm_rp_load_auth_file($path);
            if (is_array($auth)) {
                return [
                    'dir_user' => $dirUser,
                    'auth' => $auth,
                ];
            }
        }

        if (function_exists('nm_find_user_by_username')) {
            $cfg = nm_find_user_by_username($input);
            if (is_array($cfg) && !empty($cfg['DIR_USER'])) {
                return [
                    'dir_user' => nm_rp_normalize_username((string)$cfg['DIR_USER']),
                    'auth' => $cfg,
                ];
            }
        }

        return null;
    }
}

if (!function_exists('nm_rp_log')) {
    function nm_rp_log(string $message): void {
        $dir = __DIR__ . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @file_put_contents($dir . '/reset_password.log', '[' . gmdate('c') . '] ' . $message . PHP_EOL, FILE_APPEND);
    }
}

if (!function_exists('nm_rp_request_ip')) {
    function nm_rp_request_ip(): string {
        return function_exists('nm_request_ip') ? nm_request_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
    }
}

if (!function_exists('nm_rp_retry_message')) {
    function nm_rp_retry_message(int $retryAfter, string $lang): string {
        if ($lang === 'ja') {
            return '試行回数が多すぎます。' . max(1, $retryAfter) . '秒後にもう一度お試しください。';
        }
        return 'Too many attempts. Please try again in ' . max(1, $retryAfter) . ' seconds.';
    }
}

header('Content-Type: text/html; charset=utf-8');

$lang = nm_rp_lang();
$theme = nm_rp_theme();
$userParam = trim((string)($_GET['user'] ?? $_POST['user'] ?? $_GET['username'] ?? $_POST['username'] ?? ''));
$tokenParam = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$extraParams = [];
if ($userParam !== '') {
    $extraParams['user'] = $userParam;
}
if ($tokenParam !== '') {
    $extraParams['token'] = $tokenParam;
}
$u = nm_rp_toggle_urls('/reset_password.php', $lang, $theme, $extraParams);
$loginUrl = nm_rp_url('/login.php');

$t = [
    'ja' => [
        'title' => 'Notemod-selfhosted パスワード再設定',
        'brand' => 'Notemod-selfhosted',
        'subtitle' => '新しいパスワードを設定してください',
        'password' => '新しいパスワード',
        'password_confirm' => '新しいパスワード（確認）',
        'submit' => 'パスワードを更新',
        'back' => 'ログイン画面に戻る',
        'lang_label' => 'Language',
        'theme_label' => 'Theme',
        'dark' => 'Dark',
        'light' => 'Light',
        'screen' => 'Notemod-selfhosted Screen Reset Password',
        'success' => 'パスワードを更新しました。新しいパスワードでログインしてください。',
        'invalid' => '無効なリセットリンクです。',
        'expired' => 'このリセットリンクの有効期限は切れています。',
        'used' => 'このリセットリンクはすでに使用されています。',
        'mismatch' => 'パスワード確認が一致しません。',
        'required' => '新しいパスワードを入力してください。',
        'min_length' => 'パスワードは10文字以上で入力してください。',
        'save_failed' => 'パスワードの保存に失敗しました。',
        'note' => 'このリンクは30分間有効です。リンクが無効または期限切れの場合は、再度パスワードリセットを申請してください。',
        'csrf_invalid' => 'CSRFトークンが無効です。ページを再読み込みしてからもう一度お試しください。',
    ],
    'en' => [
        'title' => 'Notemod-selfhosted Password Reset',
        'brand' => 'Notemod-selfhosted',
        'subtitle' => 'Set your new password',
        'password' => 'New password',
        'password_confirm' => 'Confirm new password',
        'submit' => 'Update password',
        'back' => 'Back to login',
        'lang_label' => 'Language',
        'theme_label' => 'Theme',
        'dark' => 'Dark',
        'light' => 'Light',
        'screen' => 'Notemod-selfhosted Screen Reset Password',
        'success' => 'Your password has been updated. Please log in with your new password.',
        'invalid' => 'This reset link is invalid.',
        'expired' => 'This reset link has expired.',
        'used' => 'This reset link has already been used.',
        'mismatch' => 'Password confirmation does not match.',
        'required' => 'Please enter a new password.',
        'min_length' => 'Password must be at least 10 characters long.',
        'save_failed' => 'Failed to save the new password.',
        'note' => 'This link is valid for 30 minutes. If the link is invalid or expired, please request a new password reset.',
        'csrf_invalid' => 'Invalid CSRF token. Please reload the page and try again.',
    ],
];

$errorMessage = '';
$statusType = '';
$validLink = false;
$matchedUser = null;
$auth = null;

try {
    $matchedUser = nm_rp_find_user_by_user_param($userParam);
    if (!is_array($matchedUser) || empty($matchedUser['auth']) || empty($matchedUser['dir_user'])) {
        $errorMessage = $t[$lang]['invalid'];
    } else {
        $dirUser = (string)$matchedUser['dir_user'];
        $auth = (array)$matchedUser['auth'];

        $expiresAt = (int)($auth['PASSWORD_RESET_TOKEN_EXPIRES_AT'] ?? 0);
        if ($expiresAt <= 0) {
            $expiresStr = (string)($auth['PASSWORD_RESET_TOKEN_EXPIRES_AT'] ?? '');
            $parsed = strtotime($expiresStr);
            $expiresAt = $parsed !== false ? $parsed : 0;
        }

        if ($expiresAt <= 0 || $expiresAt < time()) {
            $errorMessage = $t[$lang]['expired'];
        } else {
            $storedHash = trim((string)($auth['PASSWORD_RESET_TOKEN_HASH'] ?? ''));
            $storedPlain = trim((string)($auth['PASSWORD_RESET_TOKEN'] ?? ''));

            $hashOk = ($storedHash !== '' && hash_equals($storedHash, hash('sha256', $tokenParam)));
            $plainOk = ($storedHash === '' && $storedPlain !== '' && hash_equals($storedPlain, $tokenParam));

            if (!$hashOk && !$plainOk) {
                $errorMessage = $t[$lang]['invalid'];
            } else {
                $validLink = true;
            }
        }
    }
} catch (Throwable $e) {
    nm_rp_log('precheck exception: ' . $e->getMessage());
    $errorMessage = $t[$lang]['invalid'];
}

if ($validLink && (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')) {
    try {
        nm_csrf_validate_or_die();
    } catch (Throwable $e) {
        $errorMessage = $t[$lang]['csrf_invalid'];
        $statusType = 'err';
    }

    if ($errorMessage === '') {
        $ip = nm_rp_request_ip();
        $bucketIp = 'reset_password:ip:' . $ip;
        $bucketUser = 'reset_password:user:' . $ip . ':' . $dirUser;

        $limitIp = nm_rate_limit_check($bucketIp, 10, 1800);
        if (!$limitIp['allowed']) {
            nm_write_auth_event('password_reset_rate_limited', [
                'dir_user' => $dirUser,
                'username' => (string)($auth['USERNAME'] ?? ''),
                'reason' => 'ip_limit',
                'retry_after' => (int)$limitIp['retry_after'],
            ]);
            $errorMessage = nm_rp_retry_message((int)$limitIp['retry_after'], $lang);
            $statusType = 'err';
        }

        if ($errorMessage === '') {
            $limitUser = nm_rate_limit_check($bucketUser, 5, 1800);
            if (!$limitUser['allowed']) {
                nm_write_auth_event('password_reset_rate_limited', [
                    'dir_user' => $dirUser,
                    'username' => (string)($auth['USERNAME'] ?? ''),
                    'reason' => 'user_limit',
                    'retry_after' => (int)$limitUser['retry_after'],
                ]);
                $errorMessage = nm_rp_retry_message((int)$limitUser['retry_after'], $lang);
                $statusType = 'err';
            }
        }

        if ($errorMessage === '') {
            $newPassword = (string)($_POST['password'] ?? '');
            $confirmPassword = (string)($_POST['password_confirm'] ?? '');

            if ($newPassword === '') {
                nm_rate_limit_record_failure($bucketIp, 1800);
                nm_rate_limit_record_failure($bucketUser, 1800);
                nm_write_auth_event('password_reset_failed', [
                    'dir_user' => $dirUser,
                    'username' => (string)($auth['USERNAME'] ?? ''),
                    'reason' => 'required',
                ]);
                $errorMessage = $t[$lang]['required'];
                $statusType = 'err';
            } elseif (function_exists('mb_strlen') ? (mb_strlen($newPassword, 'UTF-8') < 10) : (strlen($newPassword) < 10)) {
                nm_rate_limit_record_failure($bucketIp, 1800);
                nm_rate_limit_record_failure($bucketUser, 1800);
                nm_write_auth_event('password_reset_failed', [
                    'dir_user' => $dirUser,
                    'username' => (string)($auth['USERNAME'] ?? ''),
                    'reason' => 'min_length',
                ]);
                $errorMessage = $t[$lang]['min_length'];
                $statusType = 'err';
            } elseif ($newPassword !== $confirmPassword) {
                nm_rate_limit_record_failure($bucketIp, 1800);
                nm_rate_limit_record_failure($bucketUser, 1800);
                nm_write_auth_event('password_reset_failed', [
                    'dir_user' => $dirUser,
                    'username' => (string)($auth['USERNAME'] ?? ''),
                    'reason' => 'mismatch',
                ]);
                $errorMessage = $t[$lang]['mismatch'];
                $statusType = 'err';
            } else {
                try {
                    $auth['PASSWORD_HASH'] = password_hash($newPassword, PASSWORD_DEFAULT);
                    $auth['UPDATED_AT'] = gmdate('c');
                    if (empty($auth['DIR_USER'])) {
                        $auth['DIR_USER'] = $dirUser;
                    }

                    $auth['PASSWORD_RESET_TOKEN'] = '';
                    $auth['PASSWORD_RESET_TOKEN_HASH'] = '';
                    $auth['PASSWORD_RESET_TOKEN_EXPIRES_AT'] = 0;

                    if (!nm_rp_save_auth_file($dirUser, $auth)) {
                        nm_rate_limit_record_failure($bucketIp, 1800);
                        nm_rate_limit_record_failure($bucketUser, 1800);
                        nm_write_auth_event('password_reset_failed', [
                            'dir_user' => $dirUser,
                            'username' => (string)($auth['USERNAME'] ?? ''),
                            'reason' => 'save_failed',
                        ]);
                        $errorMessage = $t[$lang]['save_failed'];
                        $statusType = 'err';
                    } else {
                        nm_rate_limit_clear($bucketIp);
                        nm_rate_limit_clear($bucketUser);

                        nm_write_auth_event('password_reset_completed', [
                            'dir_user' => $dirUser,
                            'username' => (string)($auth['USERNAME'] ?? ''),
                        ]);

                        header('Location: ' . nm_rp_url('/login.php?reset=success'));
                        exit;
                    }
                } catch (Throwable $e) {
                    nm_rp_log('reset exception: ' . $e->getMessage());
                    nm_rate_limit_record_failure($bucketIp, 1800);
                    nm_rate_limit_record_failure($bucketUser, 1800);
                    $errorMessage = $t[$lang]['save_failed'];
                    $statusType = 'err';
                }
            }
        }
    }
}

if ($errorMessage !== '' && $statusType === '') {
    $statusType = 'err';
}
?>
<!doctype html>
<html lang="<?=nm_rp_h($lang)?>" data-theme="<?=nm_rp_h($theme)?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?=nm_rp_h($t[$lang]['title'])?></title>
  <style>
    html[data-theme="dark"]{--bg0:#070b14;--bg1:#0b1222;--card:rgba(15,23,42,.78);--card2:rgba(2,6,23,.22);--line:rgba(148,163,184,.20);--text:#e5e7eb;--muted:#a3b0c2;--accent:#a2c1f4;--danger:#fb7185;--success:#34d399;--shadow:0 18px 50px rgba(0,0,0,.55);}
    html[data-theme="light"]{--bg0:#f6f8fc;--bg1:#eef2ff;--card:rgba(255,255,255,.82);--card2:rgba(255,255,255,.70);--line:rgba(15,23,42,.12);--text:#0b1222;--muted:#4b5563;--accent:#2563eb;--danger:#e11d48;--success:#059669;--shadow:0 18px 50px rgba(15,23,42,.14);}
    :root{--r:18px}*{box-sizing:border-box}
    body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans JP",sans-serif;color:var(--text);background:radial-gradient(900px 600px at 20% 10%, color-mix(in srgb, var(--accent) 18%, transparent), transparent 60%),radial-gradient(800px 600px at 80% 30%, rgba(16,185,129,.10), transparent 55%),linear-gradient(180deg,var(--bg0),var(--bg1));padding:18px}
    .wrap{width:min(760px,100%)}
    .card{background:var(--card);border:1px solid var(--line);border-radius:28px;box-shadow:var(--shadow);overflow:hidden;backdrop-filter:blur(10px);position:relative}
    .head{padding:18px 18px 14px;background:linear-gradient(180deg,color-mix(in srgb,var(--accent) 10%,transparent),transparent);border-bottom:1px solid var(--line);display:flex;align-items:flex-start;justify-content:space-between;gap:18px;flex-wrap:nowrap;}
    .head-left{min-width:0; flex:1 1 auto}
    .title{margin:0; font-size:20px; font-weight:900; letter-spacing:.2px}
    .sub{margin:10px 0 0;color:var(--muted);font-size:13px;line-height:1.55}
    .body{padding:16px 18px 18px}
    .err{background:color-mix(in srgb,var(--danger) 12%,transparent);border:1px solid color-mix(in srgb,var(--danger) 25%,transparent);color:color-mix(in srgb,var(--danger) 70%,var(--text));padding:10px 12px;border-radius:14px;font-size:13px;margin-bottom:12px}
    .note{background:color-mix(in srgb,var(--accent) 8%,transparent);border:1px solid color-mix(in srgb,var(--accent) 18%,transparent);color:var(--muted);padding:10px 12px;border-radius:14px;font-size:13px;line-height:1.6;margin-bottom:12px}
    label{display:block;font-size:12px;color:var(--muted);margin:10px 0 6px}
    input{width:100%;padding:12px 12px;border-radius:14px;border:1px solid color-mix(in srgb,var(--line) 140%,transparent);background:var(--card2);color:var(--text);outline:none}
    input:focus{border-color:color-mix(in srgb,var(--accent) 70%,transparent);box-shadow:0 0 0 4px color-mix(in srgb,var(--accent) 14%,transparent)}
    .btn{width:100%;border:none;border-radius:14px;padding:12px 14px;font-weight:800;cursor:pointer;background:linear-gradient(135deg,color-mix(in srgb,var(--accent) 100%,#fff),color-mix(in srgb,var(--accent) 70%,#6366f1));color:color-mix(in srgb,var(--text) 0%,#061021);box-shadow:0 10px 25px color-mix(in srgb,var(--accent) 20%,transparent);transition:transform .12s ease,filter .12s ease;margin-top:12px}
    .btn:hover{transform:translateY(-1px);filter:brightness(1.03)}
    .btn:active{transform:translateY(0);filter:brightness(.98)}
    .helper-links{margin-top:12px;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}
    .foot{padding:12px 18px 16px;border-top:1px solid var(--line);background:color-mix(in srgb,var(--card2) 70%,transparent);color:var(--muted);font-size:12px;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap}
    a{color:var(--accent);text-decoration:none}
    a:hover{text-decoration:underline}
    .head-right{display:flex;align-items:center;justify-content:flex-end;gap:14px;flex-wrap:nowrap;flex:0 0 auto;}
    .toggles{display:flex;gap:12px;align-items:center;flex-wrap:nowrap;user-select:none;opacity:.95;}
    .toggle-row{display:flex;gap:8px;align-items:center;justify-content:flex-end;white-space:nowrap;}
    .toggle-row span{font-size:12px;color:var(--muted);line-height:1;}
    .pill{display:inline-flex;gap:10px;align-items:center;flex-wrap:nowrap;padding:10px 12px;border-radius:999px;border:1px solid var(--line);background: color-mix(in srgb, var(--card2) 75%, transparent);font-size:13px;}
    .pill a{font-size:12px;font-weight:800;padding:6px 8px;border-radius:999px;color:var(--muted);text-decoration:none;border:1px solid transparent;white-space:nowrap;line-height:1.1;}
    .pill a.active{background: color-mix(in srgb, var(--accent) 12%, transparent);color: var(--text);border-color: color-mix(in srgb, var(--accent) 45%, var(--line));}
    @media (max-width: 760px){.wrap{width:min(760px,100%)}.head{flex-wrap:wrap}.head-right{width:100%; justify-content:flex-start}.toggles{flex-wrap:wrap}.helper-links{justify-content:flex-start}}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="head">
        <div class="head-left">
          <h1 class="title"><?=nm_rp_h($t[$lang]['title'])?></h1>
          <p class="sub"><?=nm_rp_h($t[$lang]['subtitle'])?></p>
        </div>
        <div class="head-right">
          <div class="toggles">
            <div class="toggle-row">
              <span><?=nm_rp_h($t[$lang]['lang_label'])?></span>
              <div class="pill">
                <a href="<?=nm_rp_h($u['langJa'])?>" class="<?= $lang==='ja'?'active':'' ?>">JP</a>
                <a href="<?=nm_rp_h($u['langEn'])?>" class="<?= $lang==='en'?'active':'' ?>">EN</a>
              </div>
            </div>
            <div class="toggle-row">
              <span><?=nm_rp_h($t[$lang]['theme_label'])?></span>
              <div class="pill">
                <a href="<?=nm_rp_h($u['dark'])?>" class="<?= $theme==='dark'?'active':'' ?>"><?=nm_rp_h($t[$lang]['dark'])?></a>
                <a href="<?=nm_rp_h($u['light'])?>" class="<?= $theme==='light'?'active':'' ?>"><?=nm_rp_h($t[$lang]['light'])?></a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="body">
        <?php if ($validLink): ?>
          <div class="note"><?=nm_rp_h($t[$lang]['note'])?></div>
          <?php if ($errorMessage !== ''): ?><div class="err"><?=nm_rp_h($errorMessage)?></div><?php endif; ?>
          <form method="post" autocomplete="off">
            <?= nm_csrf_input_html() ?>
            <input type="hidden" name="user" value="<?=nm_rp_h($userParam)?>">
            <input type="hidden" name="token" value="<?=nm_rp_h($tokenParam)?>">
            <input type="hidden" name="lang" value="<?=nm_rp_h($lang)?>">
            <input type="hidden" name="theme" value="<?=nm_rp_h($theme)?>">
            <label><?=nm_rp_h($t[$lang]['password'])?></label>
            <input name="password" type="password" required autocomplete="new-password">
            <label><?=nm_rp_h($t[$lang]['password_confirm'])?></label>
            <input name="password_confirm" type="password" required autocomplete="new-password">
            <button class="btn" type="submit"><?=nm_rp_h($t[$lang]['submit'])?></button>
          </form>
        <?php else: ?>
          <div class="err"><?=nm_rp_h($errorMessage)?></div>
        <?php endif; ?>
        <div class="helper-links">
          <a href="<?=nm_rp_h($loginUrl)?>"><?=nm_rp_h($t[$lang]['back'])?></a>
        </div>
      </div>
      <div class="foot">
        <span><?=nm_rp_h($t[$lang]['screen'])?></span>
        <span><a href="<?=nm_rp_h($loginUrl)?>"><?=nm_rp_h($t[$lang]['back'])?></a></span>
      </div>
    </div>
  </div>
</body>
</html>