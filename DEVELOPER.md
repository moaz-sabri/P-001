# دليل المطور — القالب التجاري الخفيف

> **Light Commerce Template** · الإصدار 1.6.1 · PHP 8.1+ · SQLite · PWA  
> وثيقة موجهة لمن **يعرّض القالب** أو **يضيف عليه** (مطور، مشرف، بناي).  
> للاستخدام (نشر، تخصيص no-code) راجع [`README.md`](README.md) و[`docs/BUILD_GUIDE.md`](docs/BUILD_GUIDE.md).

---

## 1. فلسفة المشروع

| 원칙 | Implementación |
|---|---|
| **مصدر واحد لـ "كيف يُبنى الموقع"** | `src/Config/ProjectConfig.php` — ثوابت + دوال التغطية (overrides) |
| **مصدر واحد لـ "ماذا يُفعل"** | `src/Config/TemplatePatterns.php` (أنماط القدرات) + `BusinessPresets.php` (للتوافق) |
| **مصدر واحد لـ "مَن الشركة"** | `app/data/company.json` (صفحتا Home/Contact) |
| **مصدر واحد لـ "البيانات التشغيلية"** | SQLite (categories, products, orders, …) + جدول `settings` |
| **لا اعتماديات خارجية** | PHP stdlib + GD (اختياري) — بلا Composer/bundler |

- **البنية ثابتة، المحتوى يُملأ، السلوك يُختار.**
- الإعدادات **البنيوية** (حقول، currency، مناطق زمنية) تُغيّر بالكود.
- الإعدادات **التشغيلية** (اسم النشاط، هاتف، طريقة عرض) تُغيّر من Louche de contrôle أو `settings`.

---

## 2. دورة حياة الطلب (HTTP)

```
الزبون                                                              الخادم
  │   GET /  ─────────────────────────────────────────────►  index.php  (يعيد هيكل app/index.html)
  │   GET /app/... css/js                                    (静止 في الجذر? files)
  │   GET /api/index.php?r=catalog.snapshot  ─────────────►  api/index.php → public.php → CatalogService
  │                                                          └── JSON {ok, data}
  │   POST /api/index.php?r=order.create                     →  OrderService::create (transaction)
  │        Header: Idempotency-Key ........................►  Database idempotency unique
  │
المدير
  │   GET  /admin/ ─► admin/index.php ((handler 세션 + CSP)
  │   POST /api/index.php?r=admin.login  (CSRF token)
  │   mutations → admin.php → repositories/services (세션 + CSRF required)
```

- نقطة الدخول **الوحيدة** للAPI: `api/index.php`.
- المسارات (routes) تُسجَّل في `api/routes/public.php` و `admin.php`.
- جميع الردود `{ ok: bool, data|error }`.

---

## 3. هيكل المستودع (`src/`)

```
src/
├── Autoloader.php            PSR-4的手動 (App\ → src/)
├── bootstrap.php             وقت التحميل: تهيئة DB، timezone، locale، overrides
├── Config/
│   ├── ProjectConfig.php     ★ كل التخصيص البنيوي (850+ سطر، هذا الملف = "schema of behaviour")
│   ├── TemplatePatterns.php  أنماط القدرات الجاهزة (orders_tables … custom)
│   ├── BusinessPresets.php    توافق legacy (restaurant, store, …)
│   ├── CompanyProfile.php     قارئ company.json
│   ├── ReleaseManifest.php    حدود بناء dist/
├── Core/
│   ├── Database.php         PDO Sqlite wrapper, prepare/exec/transaction + now()
│   ├── Migrations.php       migrate/fresh + version tracking
│   ├── RuntimeSettings.php  جدول settings (get/set)
│   ├── Money.php            integer minor + formatting
│   ├── Auth.php / Csrf.php  session security
│   ├── Logger.php
│   ├── CompanyProfile, StorageWriter
│   ├── Http/  (Request, Response, Router, Validator, HttpError)
│   ├── Security/ (ImageUploader, RateLimiter, ExposureCheck)
│   ├── Qr/ QrCode.php       QR generator ISO/IEC 18004
│   └── Web/ Paths.php       webRoot() helper
├── Repositories/            Catalog, Products, Categories, Orders, Locations
├── Services/                CatalogService, OrderService, InstanceService, BackupService, QrService
└── Tools/                   DemoSeeder, IconGenerator
```

**قاعدة:** Controllers (routes) **تُنزّل** بالـ services. Services تحمل governor logic. Repositories هي **الوصول DB (prepared)** فقط.

---

## 4. إضافة ميزة (المخاطر (M 5 ن paths)

| Level | مَن | Scope | مثال |
|---|---|---|---|
| 1. **محتوى** | أي شخص | `app/data/company.json` | أسم، slogan، تواصل، sections |
| 2. **سلوك** | Admin/coder-lite | Setup/`preset apply` | أنتقل من `showcase` إلى `orders` |
| 3. **بنية** | مطور | `ProjectConfig` constants + migrations | currency, new custom field |
| 4. **ميزة جديدة** | مطور | New repository + service + route + UI + tests | "الخصومات" |
| 5. **تأثیر architectural** | core team | migration, version bump, CI | تغيير routing |

### مثال تطبيق ميزة "كود خصم" (Level 4)

1. Migration: جدول `coupons` + أعمدة `orders.coupon_code` (via Migrations).
2. Repository: `CouponRepository::findValid(string $code)`.
3. Service: `CheckoutService::applyCoupon()` validates, computes discount.
4. Route: `admin.coupon.*`; public: `coupon.validate`.
5. UI: checkout screen + admin tab.
6. Tests: `tests/suites/orders` new case.
7. Security review: rate limit validation endpoint; CSRF on admin mutations.

**لا تضع secrets في `/app/data` أو FE; لا تثق client prices; server computes.**

---

## 5. قواعد الـ Money / Date

- كل **أسعار** بـ `price_minor` (int). NEVER float.
- `Money::format()` ينسق بـ `CURRENCY`, position, decimals.
- كل **تواريخ** via `Database::now()` (locale tz), NOT `date('Y-m-d H:i:s')` (UTC risk).
- Water marks: migration version stored via Migrations; check `PRAGMA user_version`.

---

## 6. DB Schema (9 جداول)

| Table | Purpose |
|---|---|
| `settings` | runtime config key-value |
| `categories` | name, order, active |
| `products` | name, price_minor, image, desc, category, avail, extra_json |
| `locations` | id, code, name, type, status |
| `orders` | number, customer, location, status, total_minor, idempotency key, token |
| `order_items` | order_id, product_id, name snapshot, price_minor snapshot, qty |
| `order_events` | status audit |
| `daily_counters` | per-day numbering |
| `login_attempts` | rate-limit audit |

**Critical invariants:**
- `order_items` prices are **snapshots** at order time (never recalc from `products`).
- `orders` total computed on server from DB prices.
- Status transition per `ORDER_STATUSES` `next` array (server enforced).

---

## 7. Config Overrides Pipeline

1. `bootstrap.php` calls `ProjectConfig::applyStoredPreset()`.
2. Reads `business_preset` and `template_blueprint` from `settings`.
3. `TemplatePatterns::exists(id)` then `::toOverrides(id, answers)` returns array.
4. `ProjectConfig::setOverrides(overrides)`.
5. All `ProjectConfig::*()` read via `ov(key, default)` — fallback to constants.

**When adding new configurable behaviour:**
- Create constant default in ProjectConfig.
- Add `...Singular()`-style function using `ov(...)`.
- Ensure `TemplatePatterns::blueprintFromOverrides` / `normalizeBlueprint` includes key.
- Ensure setup UI captures and stores blueprint.
- Test constants + template pattern normalisation.

---

## 8. Routing & Webroot

- `ProjectConfig::webRoot()` inferred from `SCRIPT_NAME`.
- Supports deployment in subfolder (automatic relative paths).
- Root `index.php` serves SPA for clean URLs (`/cart`, `/checkout`, `/order/{token}`).
- Assets relatively loader: `app.js` uses `webRoot()`.
- `webRoot()` in CLI returns "/".
- For no URL rewrite support (cheap shared host), app falls back to hash routing.

---

## 9. Image Upload Security

`Security/ImageUploader.php`:
- Verify real MIME via `getimagesize` (not extension).
- Validate extension whitelist (png/jpg/webp).
- Size & dimension caps.
- Random filename; store in `app/uploads/`.
- nginx blocks script execution in uploads (`location ~ \.php$ { return 404; }`).
- Product image stored; logo optional.

**Never** `move_uploaded_file` without checks; use uploader.

---

## 10. QR Generation

- `Core/Qr/QrCode.php` implements ISO/IEC 18004 with Reed-Solomon — **zero external dependencies**.
- `bin/console qr "text" --out=file.svg` for admin CLI help? Actually admin uses via `admin.qr`/`admin.qr.sheet` routes.
- Location codes prefix: `locationCodePrefix()` returns T/C/L.
- Order token: 32 hex chars from `random_bytes(16)`, TTL `ORDER_TOKEN_TTL_MINUTES`.
- **Never** leak internal IDs in QR payload—only tokens.

---

## 11. Security checklist (before every release)

1. `php tools/code-review.php` (29 checks).
2. `php tools/security-probe.php <url>` (52 live checks) on staging only (locks account).
3. `php bin/console security:check` returns protected ✔.
4. Ensure `storage/` not downloadable from browser (nginx or move-outside-webroot).
5. Verify `.gitignore` excludes `storage/config/local.php`, `storage/database/*.sqlite`, uploads.
6. Password hashing bcrypt/argon via `password_hash`.
7. CSRF on all mutating admin routes; session HttpOnly/SameSite=Strict/Secure (HTTPS).
8. Rate-limit `ORDER_RATE_LIMIT_PER_MINUTE` for orders + `ADMIN_MAX_LOGIN_ATTEMPTS`.
9. Idempotency-Key unique constraint prevents duplicate.
10. Server recomputes total; ignores client price.

---

## 12. Testing

- `php tests/run.php` runs 574+ tests on temp DB; never touches live DB.
- Suites: orders, security, catalog, pwa, routing.
- http checks: `tests/http/check.php <url>` (50 checks) on a deployed instance.
- workflow: `tools/workflow-test.php <url>` full 69-step end-to-end (saves cookie).
- Load: `tools/load-test.php 50000 90`.
- Build: `tools/build-release.php` → `dist/` + 81 checks (`tools/deploy-check.php`).
- CI: `.github/workflows/verify.yml` on dev source (unit, nginx real binary, package).

**Adding test:** add function(s) to `tests/suites/<group>.php`; runner auto-includes suite folders.

---

## 13. Release / Versioning

- Bump `ProjectConfig::VERSION` (sole reference).
- Sync in `app/index.html` (cache bust `?v=`), `sw.js` `VERSION`, CHANGELOG.
- CI `package` job verifies version sync.
- `tools/build-release.php` drops tests/tools/docs, writes `RELEASE.txt`, validates (81 checks in ReleaseManifest).
- `deploy/nginx/site.conf` is the single source for edge rules.

---

## 14. Front-end Architecture

Files in `app/js/`:
- `api.js` — fetch wrapper, error envelope, CSRF.
- `store.js` — cart + state.
- `ui.js` — DOM helpers, i18n RTL helpers.
- `app.js` — router (clean URLs or hash fallback), views.

Features toggled by `ProjectConfig::feature()` exposed via `catalog.snapshot` settings → FE toggles UI (but server still 404s mode=catalog ordering).

Cache busting: `?v=VERSION` on asset URLs in `app/index.html`. Service worker `sw.js` (VERSION) controls offline cache.

---

## 15. Common Developer Tasks

### Add a new product field
1. Edit `PRODUCT_EXTRA_FIELDS` (or override via pattern blueprint).
2. `php bin/console migrate:fresh` if DB schema changes? Extra fields stored in `extra_json` — no migration needed, only display code.
3. Update admin UI to input new field (admin.js).

### Add new order status
1. Edit `ORDER_STATUSES` with `next` transition array.
2. Update admin filter in `admin.js`.
3. Add tests for transitions.

### Add new route
1. Add case in `api/routes/public.php` or `admin.php`.
2. Implement in service/repository.
3. Document in README §9.
4. Add http check if applicable.

### Change business type at runtime
`php bin/console preset apply <id>`; older ids map automatically. `migrate` not required (settings store).

---

## 16. Debugging & Logs

- `storage/logs/app-YYYY-MM-DD.log` (Logger).
- Set local config `'debug' => true`.
- Admin sessions: `lc_admin` cookie; idle 2h, max 12h.
- Login lockout 15 min after 6 failures; clear `login_attempts` table to reset.
- `php tools/check-admin-pass.php` to verify stored hash.
- DB inspect: `sqlite3 storage/database/app.sqlite` (avoid writing while app runs; read-only okay).

---

## 17. Operational Migrations in Production

1. Back up: `php bin/console backup`.
2. Pull new code.
3. `php bin/console migrate` (idempotent).
4. Verify version: `php bin/console status`.
5. Run smoke: `php tools/workflow-test.php <url>` (may lock admin; wait 15m or use staging).
6. Invalidate caches: bump `?v=` automatically via VERSION (deploy ships new file).

---

## 18. Where Secrets Live

- `storage/config/local.php` (admin hash, app_key, base_url, db path). **Never commit** (`.gitignore`).
- `.env` is NOT used (intentional — see `.env.example`).
- In CI/containers, generated at install time.

---

## 19. Contributing conventions

- PHP 8.1+; strict types; PSR-4 (namespace `App\`).
- Functions in ProjectConfig return values; no direct constant reads in business code.
- Repositories: prepared statements only.
- Services wrap DB in transactions for multi-write.
- Tests must pass on 8.1/8.3/8.4.
- New config knobs must update `ReleaseManifest` exclusions only if code-only.
- Update docs when adding.

---

*هذا الملف مكمل لـ README/BUILD_GUIDE/AUDIT_REPORT/SPEC_v1.*