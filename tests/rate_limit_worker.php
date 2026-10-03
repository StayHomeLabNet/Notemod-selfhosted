<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth_common.php';

$bucket = (string)($argv[1] ?? '');
$iterations = (int)($argv[2] ?? 0);
if ($bucket === '' || $iterations < 1) {
    fwrite(STDERR, "Usage: php rate_limit_worker.php BUCKET ITERATIONS\n");
    exit(2);
}

for ($i = 0; $i < $iterations; $i++) {
    nm_rate_limit_record_failure($bucket, 300);
}
