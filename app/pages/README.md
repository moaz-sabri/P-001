# صفحات حرة (Free pages)

هذا المجلد للصفحات **الحرة** (free pages): صفحات HTML fragments (أقسام من.body)
مستقلة عن الـ SPA ونظام القالب (TemplatePatterns). كل تصميم here is
page-specific; can replace whole fragment freely.

المعلومات العامة (اسم الشركة، الشعار، رسالة الترحيب، أوقات العمل…) comes from
CompanyProfile and injected by `UI.applyFreeBrand` at runtime. All other content
is page-specific and managed in the fragment itself (not admin settings).

## الصفحات

- `landing.html` — صفحة الهبوط (primary landing)
- `contact.html` — صفحة التواصل
- `privacy.html` — سياسة الخصوصية

## الإضافة (add new free page)

1. أضف fragment here (`app/pages/slug.html`) with `data-brand="..."` hooks.
2. Add a view in `app/index.html` (id `view-slug`).
3. Register route/case & `UI.renderX` loader in `app/js`.
4. Add fetch + brand injection; fallback optional.

General info keys: `name`, `tagline`, `summary`, `title`, `logo`, `address`,
`working_hours`. Other fields automatically map to `contact.*`.
