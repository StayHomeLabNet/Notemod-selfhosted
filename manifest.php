<?php
declare(strict_types=1);

/**
 * manifest.php
 * - index.php と同じ階層に置く
 * - このファイル自身のURLから basePath を決める
 */

header('Content-Type: application/manifest+json; charset=utf-8');

function nm_base_path_from_this_file(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/manifest.php'));
    $dir = rtrim(dirname($script), '/');
    return ($dir === '' || $dir === '.') ? '/' : $dir; // "/" or "/notemod"
}

/**
 * basePath と suffix を “//” にならないように結合する
 * 例: ("/", "/") -> "/"
 * 例: ("/", "/pwa/icon.png") -> "/pwa/icon.png"
 * 例: ("/notemod", "/") -> "/notemod/"
 * 例: ("/notemod", "/pwa/icon.png") -> "/notemod/pwa/icon.png"
 */
function nm_join_path(string $basePath, string $suffix): string
{
    $base = ($basePath === '/') ? '' : rtrim($basePath, '/');
    $suf  = '/' . ltrim($suffix, '/');
    $out  = $base . $suf;
    return ($out === '') ? '/' : $out;
}

$basePath = nm_base_path_from_this_file();
$name = 'Notemod';

$manifest = [
    'name' => $name,
    'short_name' => $name,

    // ★ここが今回の修正ポイント（"//" を作らない）
    'start_url' => nm_join_path($basePath, '/'),
    'scope'     => nm_join_path($basePath, '/'),

    'display' => 'standalone',
    'orientation' => 'any',
    'background_color' => '#0b1222',
    'theme_color' => '#0b1222',

    'icons' => [
        [
            'src' => nm_join_path($basePath, '/pwa/icon-192.png'),
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => nm_join_path($basePath, '/pwa/icon-512.png'),
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => nm_join_path($basePath, '/pwa/icon-512.png'),
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable',
        ],
    ],
];

echo json_encode(
    $manifest,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
);