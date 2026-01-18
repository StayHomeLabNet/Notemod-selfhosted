<?php
declare(strict_types=1);

/**
 * sw.php (Service Worker)
 * - index.php と同じ階層に置く想定
 * - 設置先ディレクトリが変わっても、このファイル自身のURLから basePath を決める
 */

header('Content-Type: application/javascript; charset=utf-8');

function nm_base_path_from_this_file(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/sw.php'));
    $dir = rtrim(dirname($script), '/');
    return ($dir === '' || $dir === '.') ? '/' : $dir;
}

$basePath = nm_base_path_from_this_file(); // "/notemod" or "/"

// キャッシュ更新したい時は v を変える
$cacheName = 'nm-pwa-v1';
?>
/* Notemod PWA Service Worker */
const CACHE_NAME = "<?= $cacheName ?>";
const BASE = "<?= $basePath ?>";

const CORE_ASSETS = [
  BASE + "/",
  BASE + "/index.php",
  BASE + "/manifest.php",
  BASE + "/pwa/icon-192.png",
  BASE + "/pwa/icon-512.png",
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(CORE_ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.map((k) => (k !== CACHE_NAME ? caches.delete(k) : null)))
    )
  );
  self.clients.claim();
});

self.addEventListener("fetch", (event) => {
  const req = event.request;
  if (req.method !== "GET") return; // POST/PUT等(API)は絶対に触らない

  event.respondWith(
    fetch(req)
      .then((res) => {
        // 静的っぽいものだけキャッシュ更新（HTMLは更新頻度高いので原則キャッシュしない）
        const url = new URL(req.url);
        const p = url.pathname;

        const isStatic =
          p.endsWith(".png") ||
          p.endsWith(".jpg") ||
          p.endsWith(".jpeg") ||
          p.endsWith(".webp") ||
          p.endsWith(".svg") ||
          p.endsWith(".css") ||
          p.endsWith(".js") ||
          p.endsWith("manifest.php");

        if (isStatic) {
          const copy = res.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
        }
        return res;
      })
      .catch(() =>
        caches.match(req).then((cached) => cached || caches.match(BASE + "/index.php"))
      )
  );
});