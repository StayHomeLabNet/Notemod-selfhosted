<?php
declare(strict_types=1);

function nm_request_ip(): string
{
    return 'custom-request-ip';
}

require_once dirname(__DIR__) . '/auth_common.php';

putenv('NM_TRUSTED_PROXIES=10.0.0.0/8');
$_SERVER['REMOTE_ADDR'] = '10.0.0.2';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

if (!function_exists('nm_normalize_ip') || !function_exists('nm_is_trusted_proxy')) {
    fwrite(STDERR, "Trusted-proxy helpers were not defined.\n");
    exit(1);
}
if (nm_request_ip() !== 'custom-request-ip') {
    fwrite(STDERR, "The external nm_request_ip override was replaced.\n");
    exit(1);
}
if (!nm_is_https_request()) {
    fwrite(STDERR, "Trusted forwarded HTTPS was not detected.\n");
    exit(1);
}

echo "Request IP override compatibility: PASS\n";
