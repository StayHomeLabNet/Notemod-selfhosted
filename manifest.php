<?php
declare(strict_types=1);

/**
 * manifest.php
 * - index.php と同じ階層に置く
 * - このファイル自身のURLから basePath を決めるので、
 *   /notemod/ 以外の場所に設置しても壊れない
 */

header('Content-Type: application/manifest+json; charset=utf-8');

/**
 * このファイル(=manifest.php)が置かれているURLディレクトリを返す
 * 例: "/notemod/manifest.php" -> "/notemod"
 * 例: "/manifest.php"         -> "/"
 */
function nm_base_path_from_this_file(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/manifest.php'));
    $dir = rtrim(dirname($script), '/');
    return ($dir === '' || $dir === '.') ? '/' : $dir;
}

$basePath = nm_base_path_from_this_file(); // "/notemod" or "/"

// PWA名はここで調整OK
$name = 'Notemod';

// start_url は「相対」でも良いが、PWA的に scope と揃えて絶対パスで出す
// ※ クエリは付けない方が安定（lang/theme は通常のUI側で処理）
$manifest = [
    'name' => $name,
    'short_name' => $name,
    'start_url' => $basePath . '/',        // index.php を省略してOK
    'scope' => $basePath . '/',            // この配下だけをPWA対象に
    'display' => 'standalone',
    'orientation' => 'any',
    'background_color' => '#0b1222',
    'theme_color' => '#0b1222',

    // アイコン（pwa/ 配下）
    'icons' => [
        [
            'src' => $basePath . '/pwa/icon-192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => $basePath . '/pwa/icon-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        // Androidのマスク対応（任意だけど入れておくと綺麗）
        [
            'src' => $basePath . '/pwa/icon-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable',
        ],
    ],
];

// きれいに出力（Git diff も見やすい）
echo json_encode(
    $manifest,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
);