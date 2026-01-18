/* service-worker.js - Notemod PWA (directory-relative, API excluded) */

// キャッシュのバージョン（更新したらここを変える）
const CACHE_NAME = "nm-pwa-v2";

// このSWファイルが置かれているディレクトリを base にする
// 例: https://example.com/notemod/service-worker.js -> /notemod/
const BASE = new URL("./", self.location).pathname.replace(/\/$/, "");

// キャッシュしたい最低限（オフライン起動用）
const CORE_ASSETS = [
  BASE + "/",             // /notemod/
  BASE + "/index.php",
  BASE + "/manifest.php",
  BASE + "/pwa/icon-192.png",
  BASE + "/pwa/icon-512.png",
];

// --- install: core assets を入れる ---
self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(async (cache) => {
      for (const url of CORE_ASSETS) {
        try {
          await cache.add(new Request(url, { cache: "reload" }));
        } catch (e) {
          // 失敗しても継続
        }
      }
    })
  );
  self.skipWaiting();
});

// --- activate: 古いキャッシュ削除 ---
self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.map((k) => (k !== CACHE_NAME ? caches.delete(k) : null)))
    )
  );
  self.clients.claim();
});

// URLが「同一オリジン & BASE配下」か
function isInScope(urlObj) {
  return urlObj.origin === self.location.origin && urlObj.pathname.startsWith(BASE + "/");
}

// ★ API除外: /api/ 配下 or notemod_sync.php を完全スルー
function isExcludedEndpoint(pathname) {
  // /notemod/api/... を除外したいので BASE を含めて判定
  const apiPrefix = BASE + "/api/";
  if (pathname.startsWith(apiPrefix)) return true;

  // /notemod/notemod_sync.php を除外
  if (pathname === BASE + "/notemod_sync.php") return true;

  return false;
}

// 静的ファイルっぽいものか（キャッシュ更新対象）
function isStaticAsset(pathname) {
  return (
    pathname.endsWith(".png") ||
    pathname.endsWith(".jpg") ||
    pathname.endsWith(".jpeg") ||
    pathname.endsWith(".webp") ||
    pathname.endsWith(".svg") ||
    pathname.endsWith(".css") ||
    pathname.endsWith(".js") ||
    pathname.endsWith(".woff2") ||
    pathname.endsWith(".woff") ||
    pathname.endsWith(".ttf") ||
    pathname.endsWith("/manifest.php")
  );
}

// HTML(ページ)っぽいリクエストか
function isHTMLRequest(req) {
  const accept = req.headers.get("accept") || "";
  return accept.includes("text/html");
}

// --- fetch ---
self.addEventListener("fetch", (event) => {
  const req = event.request;

  // GET以外は触らない（POST/PUT/DELETE等）
  if (req.method !== "GET") return;

  const url = new URL(req.url);

  // 範囲外は触らない（WordPress領域等に干渉しない）
  if (!isInScope(url)) return;

  // ★ 除外対象は完全に触らない
  if (isExcludedEndpoint(url.pathname)) return;

  // --- 1) 静的アセット：Stale-While-Revalidate ---
  if (isStaticAsset(url.pathname)) {
    event.respondWith(
      caches.match(req).then((cached) => {
        const fetchPromise = fetch(req)
          .then((res) => {
            if (res && res.ok) {
              const copy = res.clone();
              caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
            }
            return res;
          })
          .catch(() => null);

        return cached || fetchPromise || caches.match(BASE + "/index.php");
      })
    );
    return;
  }

  // --- 2) HTML：Network First ---
  if (isHTMLRequest(req) || url.pathname === BASE + "/" || url.pathname === BASE + "/index.php") {
    event.respondWith(
      fetch(req)
        .then((res) => res)
        .catch(() => caches.match(req).then((c) => c || caches.match(BASE + "/index.php")))
    );
    return;
  }

  // --- 3) その他：Network First + fallback cache ---
  event.respondWith(
    fetch(req)
      .then((res) => res)
      .catch(() => caches.match(req).then((c) => c || caches.match(BASE + "/index.php")))
  );
});