<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_common.php';

nm_send_security_headers_html();
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('UTC');

if (!function_exists('nm_fp_h')) {
    function nm_fp_h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('nm_fp_lang')) {
    function nm_fp_lang(): string {
        if (function_exists('nm_ui_bootstrap')) {
            $ui = nm_ui_bootstrap();
            $lang = (string)($ui['lang'] ?? 'en');
            return $lang === 'ja' ? 'ja' : 'en';
        }
        $lang = (string)($_GET['lang'] ?? $_COOKIE['selectedLanguage'] ?? 'en');
        return strtolower($lang) === 'ja' ? 'ja' : 'en';
    }
}

if (!function_exists('nm_fp_theme')) {
    function nm_fp_theme(): string {
        if (function_exists('nm_ui_bootstrap')) {
            $ui = nm_ui_bootstrap();
            $theme = (string)($ui['theme'] ?? 'dark');
            return $theme === 'light' ? 'light' : 'dark';
        }
        $theme = (string)($_GET['theme'] ?? $_COOKIE['theme'] ?? 'dark');
        return strtolower($theme) === 'light' ? 'light' : 'dark';
    }
}

if (!function_exists('nm_fp_url')) {
    function nm_fp_url(string $path): string {
        if (function_exists('nm_ui_url')) {
            return (string)nm_ui_url($path);
        }
        return $path;
    }
}

if (!function_exists('nm_fp_toggle_urls')) {
    function nm_fp_toggle_urls(string $path, string $lang, string $theme): array {
        if (function_exists('nm_ui_toggle_urls')) {
            return nm_ui_toggle_urls($path, $lang, $theme);
        }
        $mk = function(string $newLang, string $newTheme) use ($path): string {
            return $path . '?lang=' . rawurlencode($newLang) . '&theme=' . rawurlencode($newTheme);
        };
        return [
            'langJa' => $mk('ja', $theme),
            'langEn' => $mk('en', $theme),
            'dark'   => $mk($lang, 'dark'),
            'light'  => $mk($lang, 'light'),
        ];
    }
}

if (!function_exists('nm_fp_normalize_username')) {
    function nm_fp_normalize_username(string $value): string {
        if (function_exists('normalize_username')) {
            return (string)normalize_username($value);
        }
        return strtolower(trim($value));
    }
}

if (!function_exists('nm_fp_normalize_email_for_compare')) {
    function nm_fp_normalize_email_for_compare(string $email): string {
        return strtolower(trim($email));
    }
}

if (!function_exists('nm_fp_auth_path')) {
    function nm_fp_auth_path(string $dirUser): string {
        return __DIR__ . '/config/' . $dirUser . '/auth.php';
    }
}

if (!function_exists('nm_fp_load_auth_file')) {
    function nm_fp_load_auth_file(string $path): ?array {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }
        $data = include $path;
        return is_array($data) ? $data : null;
    }
}

if (!function_exists('nm_fp_find_all_auth_records')) {
    function nm_fp_find_all_auth_records(): array {
        $configRoot = __DIR__ . '/config';
        $results = [];
        if (!is_dir($configRoot)) {
            return $results;
        }
        foreach (glob($configRoot . '/*/auth.php') ?: [] as $path) {
            $auth = nm_fp_load_auth_file($path);
            if (!is_array($auth)) {
                continue;
            }
            $dirUser = nm_fp_normalize_username((string)($auth['DIR_USER'] ?? basename(dirname($path))));
            $username = trim((string)($auth['USERNAME'] ?? ''));
            $email = trim((string)($auth['EMAIL'] ?? ''));
            if ($dirUser === '' && $username === '' && $email === '') {
                continue;
            }
            $results[] = [
                'path' => $path,
                'dir_user' => $dirUser,
                'username' => $username,
                'email' => $email,
                'auth' => $auth,
            ];
        }
        return $results;
    }
}

if (!function_exists('nm_fp_find_matching_user')) {
    function nm_fp_find_matching_user(string $input): ?array {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        $records = nm_fp_find_all_auth_records();
        foreach ($records as $record) {
            if ($record['username'] !== '' && $record['username'] === $input) {
                return $record;
            }
        }
        $normalizedUser = nm_fp_normalize_username($input);
        foreach ($records as $record) {
            if ($record['dir_user'] !== '' && $record['dir_user'] === $normalizedUser) {
                return $record;
            }
        }
        $normalizedEmail = nm_fp_normalize_email_for_compare($input);
        $matched = [];
        foreach ($records as $record) {
            if ($record['email'] === '') {
                continue;
            }
            if (nm_fp_normalize_email_for_compare($record['email']) === $normalizedEmail) {
                $matched[] = $record;
            }
        }
        return count($matched) === 1 ? $matched[0] : null;
    }
}

if (!function_exists('nm_fp_generate_reset_token')) {
    function nm_fp_generate_reset_token(): string {
        return bin2hex(random_bytes(32));
    }
}

if (!function_exists('nm_fp_log')) {
    function nm_fp_log(string $message): void {
        $dir = __DIR__ . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @file_put_contents($dir . '/forgot_password.log', '[' . gmdate('c') . '] ' . $message . PHP_EOL, FILE_APPEND);
    }
}

if (!function_exists('nm_fp_send_reset_mail')) {
    function nm_fp_send_reset_mail(string $to, string $resetUrl, string $lang, string $from = ''): bool {
        $subject = $lang === 'ja' ? '[Notemod] パスワード再設定' : '[Notemod] Password Reset';
        $body = $lang === 'ja'
            ? "Notemod のパスワード再設定が要求されました。\n\n"
              . "以下のリンクを開いて、新しいパスワードを設定してください。\n"
              . "このリンクの有効期限は30分です。\n\n"
              . $resetUrl . "\n\n"
              . "このメールに心当たりがない場合は、そのまま破棄してください。\n"
            : "A password reset was requested for Notemod.\n\n"
              . "Open the link below to set a new password.\n"
              . "This link is valid for 30 minutes.\n\n"
              . $resetUrl . "\n\n"
              . "If you did not request this, you can ignore this email.\n";
        $error = null;
        $ok = nm_send_mail_common($to, $subject, $body, $from, $error);
        if (!$ok) {
            nm_fp_log('mail send failed to=' . $to . ' error=' . (string)$error);
        }
        return $ok;
    }
}

if (!function_exists('nm_fp_detect_scheme')) {
    function nm_fp_detect_scheme(): string {
        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (string)($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return 'https';
        }
        return 'http';
    }
}

if (!function_exists('nm_fp_build_reset_url')) {
    function nm_fp_build_reset_url(string $dirUser, string $token): string {
        $scheme = nm_fp_detect_scheme();
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
        if ($scriptDir === '/' || $scriptDir === '\\') {
            $scriptDir = '';
        }
        $base = rtrim($scheme . '://' . $host . $scriptDir, '/');
        return $base . '/reset_password.php?user=' . rawurlencode($dirUser) . '&token=' . rawurlencode($token);
    }
}

if (!function_exists('nm_fp_password_reset_token_hash')) {
    function nm_fp_password_reset_token_hash(string $token): string {
        return hash('sha256', $token);
    }
}

if (!function_exists('nm_fp_write_auth_full')) {
    function nm_fp_write_auth_full(string $dirUser, array $auth): bool {
        $dirUser = nm_fp_normalize_username($dirUser);
        if ($dirUser === '') {
            return false;
        }
        $path = nm_auth_config_path($dirUser);
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return false;
        }
        $auth['DIR_USER'] = $dirUser;
        $auth['UPDATED_AT'] = gmdate('c');
        $php = "<?php\nreturn " . var_export($auth, true) . ";\n";
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

if (!function_exists('nm_fp_request_ip')) {
    function nm_fp_request_ip(): string {
        return function_exists('nm_request_ip') ? nm_request_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
    }
}

if (!function_exists('nm_fp_retry_message')) {
    function nm_fp_retry_message(int $retryAfter, string $lang): string {
        if ($lang === 'ja') {
            return '試行回数が多すぎます。' . max(1, $retryAfter) . '秒後にもう一度お試しください。';
        }
        return 'Too many attempts. Please try again in ' . max(1, $retryAfter) . ' seconds.';
    }
}

if (!function_exists('nm_fp_masked_input_for_audit')) {
    function nm_fp_masked_input_for_audit(string $input): string {
        $input = trim($input);
        if ($input === '') {
            return '';
        }
        if (strpos($input, '@') !== false) {
            return function_exists('nm_mask_email_for_audit')
                ? nm_mask_email_for_audit($input)
                : $input;
        }
        return $input;
    }
}

$lang = nm_fp_lang();
$theme = nm_fp_theme();

$t = [
    'ja' => [
        'title' => 'Notemod-selfhosted パスワード再設定',
        'brand' => 'Notemod-selfhosted',
        'subtitle' => 'ユーザー名またはメールアドレスを入力してください',
        'input' => 'ユーザー名 または メールアドレス',
        'submit' => '再設定リンクを送信',
        'back' => 'ログイン画面に戻る',
        'lang_label' => 'Language',
        'theme_label' => 'Theme',
        'dark' => 'Dark',
        'light' => 'Light',
        'screen' => 'Notemod-selfhosted Screen Forgot Password',
        'success' => '該当するアカウントが存在する場合、再設定メールを送信しました。',
        'invalid' => 'ユーザー名またはメールアドレスを入力してください。',
        'csrf_invalid' => 'CSRFトークンが無効です。ページを再読み込みしてからもう一度お試しください。',
    ],
    'en' => [
        'title' => 'Notemod-selfhosted Forgot Password',
        'brand' => 'Notemod-selfhosted',
        'subtitle' => 'Enter your username or email address',
        'input' => 'Username or Email',
        'submit' => 'Send reset link',
        'back' => 'Back to login',
        'lang_label' => 'Language',
        'theme_label' => 'Theme',
        'dark' => 'Dark',
        'light' => 'Light',
        'screen' => 'Notemod-selfhosted Screen Forgot Password',
        'success' => 'If a matching account exists, a password reset email has been sent.',
        'invalid' => 'Please enter your username or email address.',
        'csrf_invalid' => 'Invalid CSRF token. Please reload the page and try again.',
    ],
];

$errorMessage = '';
$statusType = '';
$inputValue = trim((string)($_POST['user_or_email'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        nm_csrf_validate_or_die();
    } catch (Throwable $e) {
        $errorMessage = $t[$lang]['csrf_invalid'];
        $statusType = 'err';
    }

    if ($errorMessage === '') {
        $ip = nm_fp_request_ip();
        $normalizedInput = strtolower(trim($inputValue));
        $auditInput = nm_fp_masked_input_for_audit($inputValue);

        $bucketIp = 'forgot_password:ip:' . $ip;
        $bucketInput = 'forgot_password:input:' . $ip . ':' . sha1($normalizedInput);

        $limitIp = nm_rate_limit_check($bucketIp, 5, 900);
        if (!$limitIp['allowed']) {
            nm_write_auth_event('password_reset_request_rate_limited', [
                'input' => $auditInput,
                'reason' => 'ip_limit',
                'retry_after' => (int)$limitIp['retry_after'],
            ]);
            $errorMessage = nm_fp_retry_message((int)$limitIp['retry_after'], $lang);
            $statusType = 'err';
        }

        if ($errorMessage === '' && $normalizedInput !== '') {
            $limitInput = nm_rate_limit_check($bucketInput, 3, 900);
            if (!$limitInput['allowed']) {
                nm_write_auth_event('password_reset_request_rate_limited', [
                    'input' => $auditInput,
                    'reason' => 'input_limit',
                    'retry_after' => (int)$limitInput['retry_after'],
                ]);
                $errorMessage = nm_fp_retry_message((int)$limitInput['retry_after'], $lang);
                $statusType = 'err';
            }
        }

        if ($errorMessage === '' && $inputValue === '') {
            nm_rate_limit_record_failure($bucketIp, 900);
            nm_rate_limit_record_failure($bucketInput, 900);
            nm_write_auth_event('password_reset_requested', [
                'input' => $auditInput,
                'username' => '',
                'dir_user' => '',
                'email_masked' => '',
                'reason' => 'empty_input',
            ]);
            $errorMessage = $t[$lang]['invalid'];
            $statusType = 'err';
        } elseif ($errorMessage === '') {
            try {
                $matched = nm_fp_find_matching_user($inputValue);

                if (is_array($matched) && !empty($matched['dir_user']) && !empty($matched['auth'])) {
                    $dirUser = (string)$matched['dir_user'];
                    $auth = (array)$matched['auth'];
                    $email = trim((string)($auth['EMAIL'] ?? ''));
                    $username = trim((string)($auth['USERNAME'] ?? $dirUser));

                    if ($email !== '') {
                        $plainToken = nm_fp_generate_reset_token();

                        $auth['PASSWORD_RESET_TOKEN'] = '';
                        $auth['PASSWORD_RESET_TOKEN_HASH'] = nm_fp_password_reset_token_hash($plainToken);
                        $auth['PASSWORD_RESET_TOKEN_EXPIRES_AT'] = time() + 1800;

                        if (nm_fp_write_auth_full($dirUser, $auth)) {
                            $resetUrl = nm_fp_build_reset_url($dirUser, $plainToken);

                            $from = '';
                            if (function_exists('nm_load_mail_config')) {
                                $mailCfg = nm_load_mail_config();
                                if (is_array($mailCfg)) {
                                    $from = trim((string)($mailCfg['SMTP_FROM'] ?? ''));
                                }
                            }

                            nm_fp_send_reset_mail($email, $resetUrl, $lang, $from);
                            nm_write_auth_event('password_reset_requested', [
                                'input' => $auditInput,
                                'username' => $username,
                                'dir_user' => $dirUser,
                                'email_masked' => function_exists('nm_mask_email_for_audit') ? nm_mask_email_for_audit($email) : $email,
                                'reason' => 'matched',
                            ]);
                            nm_fp_log('reset requested dir_user=' . $dirUser . ' username=' . $username . ' email=' . $email);
                        } else {
                            nm_fp_log('failed to write auth reset fields dir_user=' . $dirUser);
                        }
                    }
                }

                if (!is_array($matched)) {
                    nm_write_auth_event('password_reset_requested', [
                        'input' => $auditInput,
                        'username' => '',
                        'dir_user' => '',
                        'email_masked' => '',
                        'reason' => 'no_match',
                    ]);
                }

                nm_rate_limit_record_failure($bucketIp, 900);
                nm_rate_limit_record_failure($bucketInput, 900);

                $errorMessage = $t[$lang]['success'];
                $statusType = 'ok';
                $inputValue = '';
            } catch (Throwable $e) {
                nm_fp_log('forgot password exception: ' . $e->getMessage());
                nm_rate_limit_record_failure($bucketIp, 900);
                nm_rate_limit_record_failure($bucketInput, 900);
                $errorMessage = $t[$lang]['success'];
                $statusType = 'ok';
            }
        }
    }
}

$u = nm_fp_toggle_urls('/forgot_password.php', $lang, $theme);
$loginUrl = nm_fp_url('/login.php');
?>
<!doctype html>
<html lang="<?=nm_fp_h($lang)?>" data-theme="<?=nm_fp_h($theme)?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?=nm_fp_h($t[$lang]['title'])?></title>
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
    .ok{background:color-mix(in srgb,var(--success) 12%,transparent);border:1px solid color-mix(in srgb,var(--success) 25%,transparent);color:color-mix(in srgb,var(--success) 70%,var(--text));padding:10px 12px;border-radius:14px;font-size:13px;margin-bottom:12px}
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
          <h1 class="title"><?=nm_fp_h($t[$lang]['title'])?></h1>
          <p class="sub"><?=nm_fp_h($t[$lang]['subtitle'])?></p>
        </div>
        <div class="head-right">
          <div class="toggles">
            <div class="toggle-row">
              <span><?=nm_fp_h($t[$lang]['lang_label'])?></span>
              <div class="pill">
                <a href="<?=nm_fp_h($u['langJa'])?>" class="<?= $lang==='ja'?'active':'' ?>">JP</a>
                <a href="<?=nm_fp_h($u['langEn'])?>" class="<?= $lang==='en'?'active':'' ?>">EN</a>
              </div>
            </div>
            <div class="toggle-row">
              <span><?=nm_fp_h($t[$lang]['theme_label'])?></span>
              <div class="pill">
                <a href="<?=nm_fp_h($u['dark'])?>" class="<?= $theme==='dark'?'active':'' ?>"><?=nm_fp_h($t[$lang]['dark'])?></a>
                <a href="<?=nm_fp_h($u['light'])?>" class="<?= $theme==='light'?'active':'' ?>"><?=nm_fp_h($t[$lang]['light'])?></a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="body">
        <?php if ($errorMessage !== ''): ?>
          <div class="<?= $statusType === 'ok' ? 'ok' : 'err' ?>"><?=nm_fp_h($errorMessage)?></div>
        <?php endif; ?>

        <form method="post" autocomplete="on">
          <?= nm_csrf_input_html() ?>
          <label><?=nm_fp_h($t[$lang]['input'])?></label>
          <input name="user_or_email" required value="<?=nm_fp_h($inputValue)?>">
          <button class="btn" type="submit"><?=nm_fp_h($t[$lang]['submit'])?></button>
        </form>

        <div class="helper-links">
          <a href="<?=nm_fp_h($loginUrl)?>"><?=nm_fp_h($t[$lang]['back'])?></a>
        </div>
      </div>
      <div class="foot">
        <span><?=nm_fp_h($t[$lang]['screen'])?></span>
        <span><a href="<?=nm_fp_h($loginUrl)?>"><?=nm_fp_h($t[$lang]['back'])?></a></span>
      </div>
    </div>
  </div>
</body>
</html>