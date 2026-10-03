<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/auth_common.php';

$user = '';

if (isset($_GET['user'])) {
    $user = normalize_username((string)$_GET['user']);
}
if ($user === '' && isset($_GET['dir_user'])) {
    $user = normalize_username((string)$_GET['dir_user']);
}
if ($user === '' && isset($_GET['username'])) {
    $found = nm_find_user_by_username((string)$_GET['username']);
    if (is_array($found) && !empty($found['DIR_USER'])) {
        $user = normalize_username((string)$found['DIR_USER']);
    }
}

// ---------------------------------
// image_api.php
// 指定ユーザー配下の画像を認証後にバイナリで返す
// 例:
// オリジナル: /api/image_api.php?user=takeshi&file=photo.png
// 幅300px:   /api/image_api.php?user=takeshi&file=photo.png&w=300
// 高さ300px: /api/image_api.php?user=takeshi&file=photo.png&h=300
// 幅/高さ両方:/api/image_api.php?user=takeshi&file=photo.png&w=300&h=300
// ---------------------------------

function respond_error(int $status, string $message): void {
    http_response_code($status);
    nm_send_security_headers_json();
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    if ($status === 401) {
        header('WWW-Authenticate: Bearer realm="Notemod image API"');
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'status'  => 'error',
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function image_api_request_token(): string {
    $authorization = trim((string)(
        $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? ''
    ));
    if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $match)) {
        return trim($match[1]);
    }
    return trim((string)($_SERVER['HTTP_X_NOTEMOD_TOKEN'] ?? ''));
}

function image_api_session_authorized(string $dirUser): bool {
    $sessionCookiePresent = isset($_COOKIE[session_name()]);
    if (session_status() !== PHP_SESSION_ACTIVE && !$sessionCookiePresent) {
        return false;
    }

    nm_auth_start_session($dirUser);
    if (!nm_auth_is_logged_in()) {
        return false;
    }

    return normalize_username(nm_get_current_dir_user()) === $dirUser;
}

function detect_mime(string $path): string {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    $expectedMime = match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png'         => 'image/png',
        'gif'         => 'image/gif',
        'webp'        => 'image/webp',
        'bmp'         => 'image/bmp',
        'svg'         => 'image/svg+xml',
        default       => 'application/octet-stream',
    };

    if ($expectedMime === 'application/octet-stream') {
        return $expectedMime;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = $finfo ? finfo_file($finfo, $path) : '';

    if ($ext === 'svg' && str_contains((string)$realMime, 'xml')) {
        return 'image/svg+xml';
    }
    if (str_starts_with((string)$realMime, 'image/')) {
        return $expectedMime;
    }

    return 'application/octet-stream';
}

// リサイズ処理関数 (縦横指定対応)
function resize_image(string $sourcePath, string $destPath, int $targetWidth, int $targetHeight, string $mime): bool {
    switch ($mime) {
        case 'image/jpeg': $src = @imagecreatefromjpeg($sourcePath); break;
        case 'image/png':  $src = @imagecreatefrompng($sourcePath); break;
        case 'image/gif':  $src = @imagecreatefromgif($sourcePath); break;
        case 'image/webp': $src = @imagecreatefromwebp($sourcePath); break;
        default: return false;
    }

    if (!$src) return false;

    $srcW = imagesx($src);
    $srcH = imagesy($src);

    $dst = imagecreatetruecolor($targetWidth, $targetHeight);
    if (!$dst) {
        nm_release_gd_image($src);
        return false;
    }

    // 透過処理（PNG / WebP / GIF 対応）
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $targetWidth, $targetHeight, $transparent);
    } elseif ($mime === 'image/gif') {
        $transparentIndex = imagecolortransparent($src);
        if ($transparentIndex >= 0) {
            $transparentColor = imagecolorsforindex($src, $transparentIndex);
            $transparentIndex = imagecolorallocate($dst, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
            imagefill($dst, 0, 0, $transparentIndex);
            imagecolortransparent($dst, $transparentIndex);
        }
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $srcW, $srcH);

    $result = false;
    switch ($mime) {
        case 'image/jpeg': $result = imagejpeg($dst, $destPath, 85); break;
        case 'image/png':  $result = imagepng($dst, $destPath); break;
        case 'image/gif':  $result = imagegif($dst, $destPath); break;
        case 'image/webp': $result = imagewebp($dst, $destPath, 85); break;
    }

    nm_release_gd_image($src);
    nm_release_gd_image($dst);

    return $result;
}


// --- メイン処理 ---
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'GET' && $method !== 'HEAD') {
    header('Allow: GET, HEAD');
    respond_error(405, 'Method not allowed');
}

$user = normalize_username($user);
$file = trim((string)($_GET['file'] ?? ''));
$width = (int)($_GET['w'] ?? 0);
$height = (int)($_GET['h'] ?? 0);

if ($user === '' || $file === '') {
    respond_error(400, 'Missing required parameters');
}

if (!preg_match('/^[A-Za-z0-9_-]+$/', $user)) {
    respond_error(400, 'Invalid user parameter');
}

$apiConfig = nm_read_php_config_array(nm_api_config_path($user));
$expectedToken = (string)($apiConfig['EXPECTED_TOKEN'] ?? '');
$providedToken = image_api_request_token();
$tokenAuthorized = $expectedToken !== ''
    && $providedToken !== ''
    && hash_equals($expectedToken, $providedToken);

if (!$tokenAuthorized && !image_api_session_authorized($user)) {
    respond_error(401, 'Authentication required');
}

$file = basename($file);
if ($file === '' || $file === '.' || $file === '..') {
    respond_error(400, 'Invalid file parameter');
}

$mediaLimits = nm_media_limits($user);

// 幅・高さ指定の制限 (DoS対策)
if ($width > 0)  $width  = max(10, min($mediaLimits['resize_dimension'], $width));
if ($height > 0) $height = max(10, min($mediaLimits['resize_dimension'], $height));

$baseDir  = nm_images_dir($user !== '' ? $user : null);
$fullPath = $baseDir . '/' . $file;

if (!is_file($fullPath) || !is_readable($fullPath)) {
    respond_error(404, 'Image not found or not readable');
}

$mime = detect_mime($fullPath);
$servePath = $fullPath;

$resizableMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

// 幅か高さのどちらかが指定されており、かつリサイズ可能な形式である場合
if (($width > 0 || $height > 0) && in_array($mime, $resizableMimes, true)) {
    $sourceSize = nm_file_size_bytes($fullPath);
    if ($sourceSize === null || $sourceSize > $mediaLimits['image_upload_bytes']) {
        respond_error(413, 'Image exceeds the application processing limit');
    }

    $imageInfo = @getimagesize($fullPath);
    if (!is_array($imageInfo)) {
        respond_error(415, 'Failed to read image dimensions');
    }
    $sourceWidth = (int)($imageInfo[0] ?? 0);
    $sourceHeight = (int)($imageInfo[1] ?? 0);
    if (!nm_image_dimensions_allowed(
        $sourceWidth,
        $sourceHeight,
        $mediaLimits['image_dimension'],
        $mediaLimits['image_pixels']
    )) {
        respond_error(413, 'Source image dimensions exceed the application processing limit');
    }

    $target = nm_calculate_resize_dimensions($sourceWidth, $sourceHeight, $width, $height);
    if ($target === null || !nm_image_dimensions_allowed(
        $target[0],
        $target[1],
        $mediaLimits['resize_dimension'],
        $mediaLimits['resize_pixels']
    )) {
        respond_error(413, 'Requested image dimensions exceed the application processing limit');
    }
    [$targetWidth, $targetHeight] = $target;

    $cacheDir = $baseDir . '/.cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }

    // キャッシュファイル名: 例) 300x0_photo.png / 0x300_photo.png
    $cacheFile = $cacheDir . '/' . $width . 'x' . $height . '_' . $file;

    // キャッシュが存在しない、またはオリジナル画像が更新されている場合は再生成
    if (!is_file($cacheFile) || filemtime($fullPath) > filemtime($cacheFile)) {
        $success = resize_image($fullPath, $cacheFile, $targetWidth, $targetHeight, $mime);
        if (!$success) {
            $cacheFile = $fullPath; // 失敗時はオリジナル
        }
    }

    if (is_file($cacheFile)) {
        $servePath = $cacheFile;
    }
}

$size = filesize($servePath);
$size = $size !== false ? $size : 0;

nm_send_security_headers_binary();
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)$size);
header('Content-Disposition: inline; filename="' . rawurlencode($file) . '"');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Vary: Cookie, Authorization, X-Notemod-Token');

if ($method !== 'HEAD') {
    readfile($servePath);
}
exit;
