<?php
declare(strict_types=1);

// ---------------------------------
// image_api.php
// 指定ユーザー配下の画像をバイナリで返す (幅・高さ指定・キャッシュ・セキュリティ強化版)
// 例:
// オリジナル: /api/image_api.php?user=takeshi&file=photo.png
// 幅300px:   /api/image_api.php?user=takeshi&file=photo.png&w=300
// 高さ300px: /api/image_api.php?user=takeshi&file=photo.png&h=300
// 幅/高さ両方:/api/image_api.php?user=takeshi&file=photo.png&w=300&h=300
// ---------------------------------

function respond_error(int $status, string $message): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'status'  => 'error',
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
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
    if ($finfo) finfo_close($finfo);

    if ($ext === 'svg' && str_contains((string)$realMime, 'xml')) {
        return 'image/svg+xml';
    }
    if (str_starts_with((string)$realMime, 'image/')) {
        return $expectedMime;
    }

    return 'application/octet-stream';
}

// リサイズ処理関数 (縦横指定対応)
function resize_image(string $sourcePath, string $destPath, int $reqWidth, int $reqHeight, string $mime): bool {
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

    $targetWidth = $reqWidth;
    $targetHeight = $reqHeight;

    // wのみ指定された場合（アスペクト比維持）
    if ($reqWidth > 0 && $reqHeight === 0) {
        $targetHeight = (int)round($srcH * ($reqWidth / $srcW));
    } 
    // hのみ指定された場合（アスペクト比維持）
    elseif ($reqHeight > 0 && $reqWidth === 0) {
        $targetWidth = (int)round($srcW * ($reqHeight / $srcH));
    }
    // 両方0の場合はエラー回避（呼び出し側で弾いていますが念のため）
    elseif ($reqWidth === 0 && $reqHeight === 0) {
        $targetWidth = $srcW;
        $targetHeight = $srcH;
    }
    // wもhも指定された場合は、そのまま $targetWidth と $targetHeight を使用（強制リサイズ）

    $dst = imagecreatetruecolor($targetWidth, $targetHeight);

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

    // 再サンプリング
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $srcW, $srcH);

    $result = false;
    switch ($mime) {
        case 'image/jpeg': $result = imagejpeg($dst, $destPath, 85); break;
        case 'image/png':  $result = imagepng($dst, $destPath); break;
        case 'image/gif':  $result = imagegif($dst, $destPath); break;
        case 'image/webp': $result = imagewebp($dst, $destPath, 85); break;
    }

    imagedestroy($src);
    imagedestroy($dst);

    return $result;
}


// --- メイン処理 ---
$user = trim((string)($_GET['user'] ?? ''));
$file = trim((string)($_GET['file'] ?? ''));
$width = (int)($_GET['w'] ?? 0);  // 指定幅
$height = (int)($_GET['h'] ?? 0); // 指定高さ

if ($user === '' || $file === '') {
    respond_error(400, 'Missing required parameters');
}

if (!preg_match('/^[A-Za-z0-9_-]+$/', $user)) {
    respond_error(400, 'Invalid user parameter');
}

$file = basename($file);
if ($file === '' || $file === '.' || $file === '..') {
    respond_error(400, 'Invalid file parameter');
}

// 幅・高さ指定の制限 (DoS対策: 10px 〜 2000px)
if ($width > 0)  $width  = max(10, min(2000, $width));
if ($height > 0) $height = max(10, min(2000, $height));

$baseDir  = dirname(__DIR__) . '/notemod-data/' . $user . '/images';
$fullPath = $baseDir . '/' . $file;

if (!is_file($fullPath) || !is_readable($fullPath)) {
    respond_error(404, 'Image not found or not readable');
}

$mime = detect_mime($fullPath);
$servePath = $fullPath;

$resizableMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

// 幅か高さのどちらかが指定されており、かつリサイズ可能な形式である場合
if (($width > 0 || $height > 0) && in_array($mime, $resizableMimes, true)) {
    
    $cacheDir = $baseDir . '/.cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }

    // キャッシュファイル名: 例) 300x0_photo.png (幅300, 高さ自動), 0x300_photo.png (幅自動, 高さ300)
    $cacheFile = $cacheDir . '/' . $width . 'x' . $height . '_' . $file;

    // キャッシュが存在しない、またはオリジナル画像が更新されている場合は再生成
    if (!is_file($cacheFile) || filemtime($fullPath) > filemtime($cacheFile)) {
        $success = resize_image($fullPath, $cacheFile, $width, $height, $mime);
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

$cacheMaxAge = 604800; 

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)$size);
header('Content-Disposition: inline; filename="' . rawurlencode($file) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=' . $cacheMaxAge);
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");

readfile($servePath);
exit;