/* ==========================================================================
   sw.js — Service Worker (نطاق الموقع الجذري)
   يعمل على المستوى الجذري حتى يتمكن من اعتراض طلبات الـ API وتخزينها
   للعمل دون اتصال، مع استثناء أي استجابة حساسة.

   ملاحظات:
   - يخزّن ملفات الواجهة وبيانات الكتالوج فقط.
   - لا يخزّن لوحة الإدارة ولا متابعة الطلبات ولا طلبات POST إطلاقًا.
   - لا يوجد طابور إرسال: الطلب دون اتصال لا يُرسل ولا يُؤجَّل.

   قاعدة أساسية (إصلاح جذري):
   كل ما يُعرض من الكاش يُوسم بترويسة X-LC-Offline، وكل فشل شبكة حقيقي
   يُترك ليصل إلى الصفحة كفشل شبكة (لا نختلقه كاستجابة JSON ناجحة)، حتى
   تستطيع الواجهة التمييز بين «لا اتصال» و«خطأ في الخادم» بدقة.
   ========================================================================== */

const VERSION = 'v1.6.4';
const SHELL_CACHE = `lc-shell-${VERSION}`;
const DATA_CACHE = `lc-data-${VERSION}`;

// جذر التطبيق: هو نفسه جذر الموقع ("/")، وملفات الواجهة داخل /app/
const SCOPE = self.registration.scope;
const APP_BASE = new URL('./app/', SCOPE).pathname;

// ملفات هيكل الواجهة.
// ملاحظة: صفحة الهيكل نفسها هي جذر الموقع ("/") فهي لم تعد تُقدَّم من
// app/index.html — الروابط النظيفة مثل /cart و /checkout تبدأ من الجذر.

const shellUrl = SCOPE;

const SHELL_ASSETS = [
  SCOPE,
  APP_BASE + 'css/app.css',
  APP_BASE + 'js/theme.js',
  APP_BASE + 'js/api.js',
  APP_BASE + 'js/store.js',
  APP_BASE + 'js/ui.js',
  APP_BASE + 'js/app.js',
  APP_BASE + 'manifest.webmanifest',
  APP_BASE + 'assets/icons/icon.svg',
  APP_BASE + 'assets/icons/icon-192.png',
  APP_BASE + 'pages/landing.html',
  APP_BASE + 'pages/contact.html',
  APP_BASE + 'pages/privacy.html',
  APP_BASE + 'assets/icons/icon-512.png',
];

/**
 * تخزين ملفات الهيكل مسبقًا.
 *
 * ملاحظة مهمة: لا نستخدم cache.addAll لأنها ترسل كل الطلبات دفعة واحدة
 * وتفشل كليًا إذا تعذّر ملف واحد. الفشل شائع على الخوادم قليلة العمّال
 * (مثل خادم PHP المدمج أحادي العملية) حيث تتزاحم الطلبات المتوازية.
 * هنا نخزّن كل ملف على حدة، ونعيد محاولة الفاشل، فالمنصة تعمل ولو جزئيًا.
 */
async function precache() {
  const cache = await caches.open(SHELL_CACHE);

  const results = await Promise.allSettled(
    SHELL_ASSETS.map((asset) => cache.add(new Request(asset, { cache: 'reload' })))
  );

  const failed = results
    .map((result, index) => (result.status === 'rejected' ? SHELL_ASSETS[index] : null))
    .filter(Boolean);

  if (failed.length > 0) {
    // محاولة ثانية للأصول التي تعذّر تخزينها في الجولة الأولى
    await Promise.allSettled(failed.map((asset) => cache.add(asset)));
  }

  // تسجيل ما لم يُخزَّن لتشخيص مبكر
  const stored = await cache.keys();
  if (stored.length < SHELL_ASSETS.length) {
    console.warn('[sw] تم تخزين ' + stored.length + ' من ' + SHELL_ASSETS.length + ' ملف هيكل');
  }
}

self.addEventListener('install', (event) => {
  // لا نمنع التفعيل أبدًا حتى لو فشل التخزين كليًا
  event.waitUntil(precache().then(() => self.skipWaiting()).catch(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys
          .filter((key) => key !== SHELL_CACHE && key !== DATA_CACHE)
          .map((key) => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('message', (event) => {
  if (event.data === 'skip-waiting') self.skipWaiting();
});

self.addEventListener('fetch', (event) => {
  const request = event.request;

  // لا نتدخل في أي عملية كتابة أو تعديل
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  const route = url.searchParams.get('r') || '';

  // استثناء كل مسارات الإدارة (بيانات حساسة لا تُخزَّن إطلاقًا)
  if (url.pathname.includes('/admin/') || route.startsWith('admin.')) return;

  // استجابة متابعة الطلب تُقرأ مباشرة ولا تُخزَّن
  if (route === 'order.status' || route === 'ping') return;

  // صور المنتجات: الكاش أولًا
  if (url.pathname.includes('/app/uploads/')) {
    event.respondWith(cacheFirst(request, DATA_CACHE));
    return;
  }

  // تنقّل إلى أي صفحة من صفحات التطبيق (/products، /contact، /cart، /order/...):
  // الشبكة أولًا كي تصل أحدث نسخة من الواجهة (وأرقام إصدار الأصول الجديدة)
  // فورًا، ثم كاش الهيكل عند غياب الاتصال.
  if (request.mode === 'navigate') {
    event.respondWith(networkFirst(request, SHELL_CACHE, shellUrl, false, true));
    return;
  }

  // ملفات هيكل التطبيق: الشبكة أولًا أيضًا. الاعتماد على الكاش أولًا هنا
  // كان يسمح لنسخة قديمة من app.js بالبقاء بعد التحديث.
  if (isShellAsset(url.pathname)) {
    event.respondWith(networkFirst(request, SHELL_CACHE, null, false, true));
    return;
  }

  // بيانات الكتالوج: الشبكة أولًا (لعرض أحدث المنتجات فورًا) مع الرجوع إلى
  // الكاش عند غياب الاتصال — حتى لا تبقى منتجات قديمة/فارغة على الشاشة.
  // نرفض تخزين أي استجابة ليست JSON صالحة، فلا يتحول خطأ HTML إلى كتالوج.
  if (route === 'catalog.snapshot' || route === 'product.get' || route === 'locations.list') {
    event.respondWith(networkFirst(request, DATA_CACHE, null, true));
  }
});

/* --------------------------- الاستراتيجيات --------------------------- */

/**
 * مطابقة دقيقة لمسار ضمن هيكل التطبيق.
 * ملاحظة: لا نستخدم endsWith('') لأن ذلك يطابق كل المسارات.
 */
function isShellAsset(pathname) {
  return SHELL_ASSETS.some((asset) => {
    if (asset.endsWith('/')) {
      return pathname === asset || pathname === asset.slice(0, -1);
    }
    return pathname === asset;
  });
}

/**
 * يُعيد استجابة من الكاش مع وسم صريح بأنها نسخة محفوظة.
 * تستخدمه الواجهة لتمييز «الكتالوج المحفوظ» عن «كتالوج الشبكة».
 */
async function cachedResponse(response) {
  const headers = new Headers(response.headers);
  // الجسم من Cache API مفكوك الضغط؛ إبقاء ترويسة الضغط يفسد الاستجابة.
  headers.delete('content-encoding');
  headers.delete('content-length');
  headers.set('X-LC-Offline', '1');
  return new Response(await response.blob(), {
    status: response.status,
    statusText: response.statusText,
    headers,
  });
}

// ملاحظة مهمة: لا نستخدم ignoreSearch مع مسارات الـ API،
// لأن ذلك قد يجعل استجابة مسار تُعاد لبند مسار آخر.
async function cacheFirst(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  if (cached) return cached;

  try {
    const response = await fetch(request);
    if (response && response.ok) cache.put(request, response.clone());
    return response;
  } catch (error) {
    // صورة مفقودة دون اتصال: استجابة فارغة أفضل من رمي خطأ في الشبكة
    return new Response('', { status: 504, statusText: 'Offline' });
  }
}

/**
 * شبكة أولًا (network-first): يُطلب من الخادم أحدث البيانات (المنتجات) فورًا،
 * ويُخزَّن النجاح في الكاش. عند غياب الاتصال (أو فشل الطلب) يُستخدم الكاش
 * المحفوظ للعمل دون اتصال. هذا يمنع بقاء نسخة قديمة/فارغة على الشاشة.
 *
 * عند فشل الشبكة ولا يوجد كاش نُعيد رمي الخطأ بدل اختلاق استجابة JSON،
 * فتصل الحالة إلى الواجهة كفشل شبكة حقيقي وتُعرض رسالة «لا اتصال» الصحيحة.
 *
 * @param {Request} request
 * @param {string} cacheName
 * @param {string|null} fallbackUrl مفتاح بديل للتخزين (جذر الموقع للتنقّل)
 * @param {boolean} requireJson لا تخزّن إلا استجابات JSON الصالحة
 * @param {boolean} fallbackOnServerError ارجع للكاش عند 5xx (واجهة/تنقّل)
 */
async function networkFirst(
  request,
  cacheName,
  fallbackUrl = null,
  requireJson = false,
  fallbackOnServerError = false
) {
  const cache = await caches.open(cacheName);
  const cacheKey = fallbackUrl || request;

  const cachedCopy = async () => {
    const cached =
      (await cache.match(request)) ||
      (fallbackUrl ? await cache.match(fallbackUrl) : null) ||
      (request.mode === 'navigate' ? await cache.match(shellUrl) : null);
    return cached ? cachedResponse(cached) : null;
  };

  try {
    const response = await fetch(request);

    /*
     * لا نفترض أن «الخادم ردّ» يعني «الخادم سليم». البوابات والاستضافات
     * المشتركة ترجع 502/503 عند تعطّل الخدمة، وكان تمريرها للزبون يعني
     * صفحة خطأ بدل واجهة محفوظة. للتنقّل والأصول نرجع للكاش عند 5xx.
     */
    if (fallbackOnServerError && response && response.status >= 500) {
      const fallback = await cachedCopy();
      if (fallback) return fallback;
      return response;
    }

    const contentType = response.headers.get('content-type') || '';
    const cacheable =
      response &&
      response.ok &&
      (!requireJson || contentType.indexOf('application/json') !== -1);

    if (cacheable) {
      cache.put(cacheKey, response.clone());
    }
    return response;
  } catch (error) {
    const fallback = await cachedCopy();
    if (fallback) return fallback;
    throw error;
  }
}
