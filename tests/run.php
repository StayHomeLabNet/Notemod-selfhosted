<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$testStorage = sys_get_temp_dir() . '/notemod-tests-' . bin2hex(random_bytes(6));

if (!mkdir($testStorage, 0700, true) && !is_dir($testStorage)) {
    fwrite(STDERR, "Failed to create test storage.\n");
    exit(1);
}

putenv('NM_STORAGE_ROOT=' . $testStorage);
$_SERVER['NM_STORAGE_ROOT'] = $testStorage;

require_once $projectRoot . '/auth_common.php';
require_once $projectRoot . '/data_crypto.php';

function test_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($child) && !is_link($child)) {
            test_remove_tree($child);
        } else {
            @unlink($child);
        }
    }
    @rmdir($path);
}

register_shutdown_function(static function () use ($testStorage): void {
    test_remove_tree($testStorage);
});

final class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;

    public function test(string $name, callable $test): void
    {
        try {
            $test();
            $this->passed++;
            echo '[PASS] ' . $name . PHP_EOL;
        } catch (Throwable $e) {
            $this->failed++;
            fwrite(STDERR, '[FAIL] ' . $name . ': ' . $e->getMessage() . PHP_EOL);
        }
    }

    public function same(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(($message !== '' ? $message . ': ' : '')
                . 'expected ' . var_export($expected, true)
                . ', got ' . var_export($actual, true));
        }
    }

    public function true(bool $actual, string $message = ''): void
    {
        $this->same(true, $actual, $message);
    }

    public function false(bool $actual, string $message = ''): void
    {
        $this->same(false, $actual, $message);
    }

    public function throws(callable $operation, string $exceptionClass): void
    {
        try {
            $operation();
        } catch (Throwable $e) {
            if ($e instanceof $exceptionClass) {
                return;
            }
            throw new RuntimeException('Unexpected exception: ' . get_class($e));
        }
        throw new RuntimeException('Expected ' . $exceptionClass . ' was not thrown');
    }

    public function finish(): never
    {
        echo PHP_EOL . $this->passed . ' passed, ' . $this->failed . ' failed' . PHP_EOL;
        exit($this->failed === 0 ? 0 : 1);
    }
}

$runner = new TestRunner();

$runner->test('username normalization', static function () use ($runner): void {
    $runner->same('aliceexample', normalize_username(' Alice@example '));
    $runner->same('username', normalize_username('user name'));
});

$runner->test('Unicode password minimum length', static function () use ($runner): void {
    $runner->same(10, nm_password_character_length('123456789a'));
    $runner->same(10, nm_password_character_length("123456789\u{3042}"));
    $runner->true(nm_password_meets_minimum_length("123456789\u{3042}"));
    $runner->false(nm_password_meets_minimum_length("12345678\u{3042}"));
});

$runner->test('release metadata matches both README badges', static function () use ($runner, $projectRoot): void {
    $runner->same('https://github.com/StayHomeLabNet/Notemod-selfhosted', NM_REPOSITORY_URL);
    foreach (['README.md', 'README.ja.md'] as $filename) {
        $readme = file_get_contents($projectRoot . '/' . $filename);
        $runner->true(is_string($readme), $filename . ' is readable');
        $runner->true(
            str_contains($readme, 'version-' . NM_APP_VERSION . '-'),
            $filename . ' badge matches NM_APP_VERSION'
        );
    }
});

$runner->test('common defaults match both samples', static function () use ($runner, $projectRoot): void {
    $defaults = nm_common_config_defaults();
    foreach (['config.sample.php', 'config.sample.ja.php'] as $filename) {
        $sample = require $projectRoot . '/' . $filename;
        foreach ($defaults as $key => $value) {
            $runner->true(array_key_exists($key, $sample), $filename . ' contains ' . $key);
            $runner->same($value, $sample[$key], $filename . ' value for ' . $key);
        }
    }

    $merged = nm_common_config_with_defaults(['TIMEZONE' => 'UTC']);
    $runner->same('UTC', $merged['TIMEZONE']);
    $runner->same(false, $merged['LOGGER_NOTEMOD_ENABLED']);
    $runner->same(500, $merged['LOGGER_FILE_MAX_LINES']);
});

$runner->test('configured public URL validation', static function () use ($runner): void {
    putenv('NM_PUBLIC_BASE_URL=https://notes.example.com/notemod/');
    $runner->same('https://notes.example.com/notemod', nm_public_base_url());
    $runner->same('https://notes.example.com/notemod/reset_password.php', nm_public_url('/reset_password.php'));

    putenv('NM_PUBLIC_BASE_URL=https://user:pass@notes.example.com/notemod');
    $runner->throws(static fn(): string => nm_public_base_url(), RuntimeException::class);
    putenv('NM_PUBLIC_BASE_URL');
});

$runner->test('forwarded client IP requires a trusted proxy', static function () use ($runner): void {
    putenv('NM_TRUSTED_PROXIES=10.0.0.0/8');
    $_SERVER['REMOTE_ADDR'] = '198.51.100.20';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';
    $runner->same('198.51.100.20', nm_request_ip());

    $_SERVER['REMOTE_ADDR'] = '10.0.0.2';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7, 10.0.0.1';
    $runner->same('203.0.113.7', nm_request_ip());
});

$runner->test('forwarded HTTPS requires a trusted proxy', static function () use ($runner): void {
    unset($_SERVER['HTTPS']);
    $_SERVER['SERVER_PORT'] = '80';
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

    $_SERVER['REMOTE_ADDR'] = '198.51.100.20';
    $runner->false(nm_is_https_request());

    $_SERVER['REMOTE_ADDR'] = '10.0.0.2';
    $runner->true(nm_is_https_request());

    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https, http';
    $runner->false(nm_is_https_request());
    putenv('NM_TRUSTED_PROXIES');
});

$runner->test('external request IP override remains compatible', static function () use ($runner, $projectRoot): void {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($projectRoot . '/tests/request_ip_override.php');
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);
    $runner->same(0, $exitCode, implode("\n", $output));
});

$runner->test('media dimensions and aspect ratio limits', static function () use ($runner): void {
    $runner->true(nm_image_dimensions_allowed(5000, 5000, 10000, 25000000));
    $runner->false(nm_image_dimensions_allowed(5001, 5000, 10000, 25000000));
    $runner->false(nm_image_dimensions_allowed(10001, 1, 10000, 25000000));
    $runner->same([1000, 500], nm_calculate_resize_dimensions(2000, 1000, 1000, 0));
    $runner->same([1000, 500], nm_calculate_resize_dimensions(2000, 1000, 0, 500));
});

$runner->test('rate limit state can be recorded and cleared', static function () use ($runner): void {
    $runner->true(nm_rate_limit_save([]));
    nm_rate_limit_record_failure('login:test', 60);
    nm_rate_limit_record_failure('login:test', 60);

    $state = nm_rate_limit_check('login:test', 2, 60);
    $runner->false($state['allowed']);
    $runner->same(2, $state['count']);
    $runner->true($state['retry_after'] > 0);

    nm_rate_limit_clear('login:test');
    $runner->true(nm_rate_limit_check('login:test', 2, 60)['allowed']);
});

$runner->test('concurrent rate limit updates are not lost', static function () use ($runner, $projectRoot): void {
    if (!function_exists('proc_open')) {
        throw new RuntimeException('proc_open is required for the concurrency test');
    }

    $bucket = 'concurrent:test';
    nm_rate_limit_clear($bucket);
    $processes = [];
    for ($i = 0; $i < 6; $i++) {
        $command = [PHP_BINARY, $projectRoot . '/tests/rate_limit_worker.php', $bucket, '20'];
        $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $projectRoot);
        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start rate limit worker');
        }
        fclose($pipes[0]);
        $processes[] = [$process, $pipes[1], $pipes[2]];
    }

    foreach ($processes as [$process, $stdout, $stderr]) {
        $output = stream_get_contents($stdout) . stream_get_contents($stderr);
        fclose($stdout);
        fclose($stderr);
        $runner->same(0, proc_close($process), trim($output));
    }

    $state = nm_rate_limit_check($bucket, 120, 300);
    $runner->same(120, $state['count']);
    $runner->false($state['allowed']);
    nm_rate_limit_clear($bucket);
});

$runner->test('index lock returns the operation result', static function () use ($runner, $testStorage): void {
    $result = nm_with_index_lock($testStorage . '/image_index.json', static fn(): string => 'locked');
    $runner->same('locked', $result);
    $runner->true(is_file($testStorage . '/image_index.json.lock'));
});

$runner->test('authentication config writes atomically and preserves fields', static function () use ($runner): void {
    $dirUser = 'test-user';
    $path = nm_auth_config_path($dirUser);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create authentication test directory');
    }
    file_put_contents($path, "<?php\nreturn ['EMAIL' => 'test@example.com'];\n");

    $hash = password_hash('test-password', PASSWORD_DEFAULT);
    $runner->true(is_string($hash));
    $runner->true(nm_auth_write_config('Test User', $hash, $dirUser));

    $stored = nm_auth_load($dirUser);
    $runner->same('test@example.com', $stored['EMAIL']);
    $runner->same('Test User', $stored['USERNAME']);
    $runner->same($dirUser, $stored['DIR_USER']);
    $runner->same($hash, $stored['PASSWORD_HASH']);
});

$runner->test('encrypted data round trip rejects tampering', static function () use ($runner): void {
    $GLOBALS['cfg'] = [
        'DATA_ENCRYPTION_ENABLED' => true,
        'DATA_ENCRYPTION_KEY' => 'test-only-encryption-key',
    ];
    $plain = json_encode(['notes' => [['content' => "\u{79d8}\u{5bc6}"]]], JSON_UNESCAPED_UNICODE);
    $encrypted = nm_encrypt_json_string($plain);
    $runner->true(is_string($encrypted));
    $runner->same($plain, nm_decrypt_json_string($encrypted));

    $payload = json_decode($encrypted, true);
    $payload['data'] = base64_encode((string)base64_decode($payload['data'], true) . 'x');
    $tampered = json_encode($payload);
    $runner->same(false, nm_decrypt_json_string($tampered));
});

$runner->finish();
