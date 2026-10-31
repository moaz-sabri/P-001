<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Config\ProjectConfig;
use App\Core\Http\HttpError;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Validator;
use App\Core\Money;
use App\Core\Security\Auth;
use App\Core\Security\Csrf;
use App\Core\Security\ImageUploader;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;

final class ProductController
{
    public static function index(Request $request): void
    {
        self::requireAuth($request);
        Response::ok([
            'products'        => (new ProductRepository())->allAdmin(),
            'categories'      => (new CategoryRepository())->all(),
            'fields'          => ProjectConfig::productExtraFields(),
            'detail_fields'   => ProjectConfig::productDetailFields(),
            'gallery_enabled' => ProjectConfig::feature('product_gallery'),
            'labels'          => [
                'singular'  => ProjectConfig::productSingular(),
                'plural'    => ProjectConfig::productPlural(),
                'catalog'   => ProjectConfig::catalogTitle(),
                'max_items' => ProjectConfig::MAX_CATALOG_ITEMS,
            ],
        ]);
    }

    public static function create(Request $request): void
    {
        self::requireAdmin($request);
        $data = self::validateProduct(self::body($request));

        $repo = new ProductRepository();
        if ($repo->count() >= ProjectConfig::MAX_CATALOG_ITEMS) {
            throw new HttpError(
                'catalog_limit',
                'بلغت القائمة حدّها الأقصى (' . ProjectConfig::MAX_CATALOG_ITEMS . ' عنصرًا). احذف عنصرًا قبل إضافة آخر.',
                422
            );
        }

        $id = $repo->create($data);
        Response::ok(['id' => $id, 'products' => $repo->allAdmin()], 201);
    }

    public static function update(Request $request): void
    {
        self::requireAdmin($request);
        $data = self::validateProduct(self::body($request));
        $id   = (int) ($data['id'] ?? 0);
        $repo = new ProductRepository();
        $existing = $repo->find($id);
        if ($id <= 0 || $existing === null) {
            throw new HttpError('not_found', 'المنتج غير موجود.', 404);
        }
        if (empty($data['image_path'])) {
            $data['image_path'] = $existing['image_path'];
        }
        $repo->update($id, $data);

        $oldGallery = (array) ($existing['gallery_paths'] ?? []);
        $newGallery = (array) ($data['extra']['gallery'] ?? []);
        foreach (array_diff($oldGallery, $newGallery) as $removed) {
            ImageUploader::delete($removed);
        }

        Response::ok(['products' => $repo->allAdmin()]);
    }

    public static function delete(Request $request): void
    {
        self::requireAdmin($request);
        $id   = (int) (self::body($request)['id'] ?? 0);
        $repo = new ProductRepository();
        $existing = $repo->find($id);
        if ($id <= 0 || $existing === null) {
            throw new HttpError('not_found', 'المنتج غير موجود.', 404);
        }
        ImageUploader::delete($existing['image_path']);
        foreach ((array) ($existing['gallery_paths'] ?? []) as $galleryPath) {
            ImageUploader::delete($galleryPath);
        }
        $repo->delete($id);
        Response::ok(['products' => $repo->allAdmin()]);
    }

    public static function image(Request $request): void
    {
        self::requireAdmin($request);
        $file = $request->file('image');
        if ($file === null) {
            throw new HttpError('missing_file', 'لم يتم اختيار صورة.', 422);
        }
        $path = ImageUploader::store($file, 'products');
        Response::ok(['image_path' => $path, 'image' => ProjectConfig::uploadsUrl($path)], 201);
    }

    public static function gallery(Request $request): void
    {
        self::requireAdmin($request);
        if (!ProjectConfig::feature('product_gallery')) {
            throw new HttpError('disabled', 'معرض الصور غير مفعّل في هذا النمط.', 404);
        }
        $files = $request->files('gallery');
        if ($files === []) {
            throw new HttpError('missing_file', 'لم يتم اختيار صور.', 422);
        }
        if (count($files) > 8) {
            throw new HttpError('too_many_files', 'الحد الأقصى 8 صور للمنتج الواحد.', 422);
        }

        $images = [];
        foreach ($files as $file) {
            $path = ImageUploader::store($file, 'products');
            $images[] = ['path' => $path, 'url' => ProjectConfig::uploadsUrl($path)];
        }
        Response::ok(['images' => $images], 201);
    }

    private static function validateProduct(array $data): array
    {
        $v = Validator::make($data);
        $v->required('name', 'اسم المنتج')
          ->string('name', 'اسم المنتج', 1, 120)
          ->string('description', 'الوصف', 0, 3000)
          ->integer('category_id', 'التصنيف', 0, PHP_INT_MAX)
          ->integer('sort_order', 'الترتيب', 0, 100000)
          ->boolean('is_available')
          ->boolean('is_featured');

        $out = $v->throwIfFails();
        $rawPrice    = $out['price'] ?? '0';
        $out['price_minor'] = Money::parseToMinor(is_scalar($rawPrice) ? (string) $rawPrice : '0');
        if ($out['price_minor'] < 0) {
            $out['price_minor'] = 0;
        }
        if ($out['price_minor'] > 999999999999) {
            throw new HttpError('invalid_price', 'السعر كبير جدًا.', 422);
        }

        // الحقول الإضافية: نقبلها ونخزّنها فقط وفق تعريف ProjectConfig
        $extra = [];
        $extraInput = $data['extra'] ?? [];
        if (is_array($extraInput)) {
            foreach (ProjectConfig::productExtraFields() as $field) {
                $key = $field['key'];
                $val = $extraInput[$key] ?? null;
                if ($field['type'] === 'boolean') {
                    $extra[$key] = Validator::toBool($val) ? 1 : 0;
                } elseif ($field['type'] === 'number') {
                    if ($val === null || $val === '') {
                        $extra[$key] = '';
                    } elseif (is_numeric($val)) {
                        $extra[$key] = (int) $val;
                    } else {
                        throw new HttpError('invalid_field', $field['label'] . ' يجب أن يكون رقمًا.', 422);
                    }
                } else {
                    $val = is_scalar($val) ? mb_substr(trim((string) $val), 0, 400) : '';
                    if ($val !== '' && !empty($field['required'])) {
                        // مطلوب فقط عند التعبئة؛ لا نرفض الحذف
                    }
                    $extra[$key] = $val;
                }
            }
        }
        // حقول التفاصيل تُحفظ ضمن نفس البنية
        if (isset($data['details']) && is_array($data['details'])) {
            foreach (ProjectConfig::productDetailFields() as $field) {
                $key = $field['key'];
                $val = (string) ($data['details'][$key] ?? '');
                $extra[$key] = mb_substr(trim($val), 0, 1200);
            }
        }

        /*
         * معرض المنتج: مسارات رفع فقط (products/xxx.ext)، بلا روابط خارجية.
         * يُخزَّن داخل extra_json فلا يحتاج المخطط إلى هجرة جديدة.
         */
        if (ProjectConfig::feature('product_gallery')) {
            $gallery = [];
            $galleryInput = is_array($data['extra'] ?? null) ? ($data['extra']['gallery'] ?? []) : [];
            if (is_array($galleryInput)) {
                foreach (array_slice($galleryInput, 0, 8) as $item) {
                    $path = is_scalar($item) ? trim((string) $item) : '';
                    if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
                        continue;
                    }
                    if (!preg_match('#^products/[A-Za-z0-9._-]+\.(?:jpe?g|png|webp|gif)$#i', $path)) {
                        continue;
                    }
                    $gallery[] = $path;
                }
            }
            $extra['gallery'] = array_values(array_unique($gallery));
        }

        // Validate image_path: only safe relative paths or empty.
        $rawImagePath = $data['image_path'] ?? null;
        if ($rawImagePath === null) {
            unset($out['image_path']);
        } else {
            $imagePath = is_scalar($rawImagePath) ? trim((string) $rawImagePath) : '';
            if ($imagePath !== ''
                && (str_contains($imagePath, '..') || str_starts_with($imagePath, '/') || !preg_match('#^[A-Za-z0-9_./-]+\.(?:jpe?g|png|webp|gif)$#i', $imagePath))) {
                throw new HttpError('invalid_image_path', 'مسار الصورة غير صالح.', 422);
            }
            $out['image_path'] = $imagePath;
        }

        $out['extra'] = $extra;
        return $out;
    }

    private static function body(Request $request): array
    {
        $data = $request->body();
        return $data === [] ? $request->json() : $data;
    }

    private static function requireAuth(Request $request): void
    {
        unset($request);
        if (!Auth::check()) {
            throw new HttpError('unauthenticated', 'الجلسة منتهية. سجّل الدخول مجددًا.', 401);
        }
    }

    private static function requireAdmin(Request $request): void
    {
        self::requireAuth($request);
        Csrf::verify($request);
    }
}