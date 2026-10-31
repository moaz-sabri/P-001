<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Config\ProjectConfig;
use App\Core\Cache;
use App\Core\CompanyProfile;
use App\Core\Http\HttpError;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Validator;
use App\Core\RuntimeSettings;
use App\Core\Security\Auth;
use App\Core\Security\Csrf;
use App\Core\Security\ImageUploader;
use App\Services\BackupService;
use App\Services\CatalogService;

final class SettingsController
{
    public static function index(Request $request): void
    {
        self::requireAuth($request);
        $service = new CatalogService();
        Response::ok([
            'settings'    => RuntimeSettings::adminPayload(),
            'config'      => $service->configPayload(),
            'base_url'    => app_config('app.base_url', ''),
            'company'     => [
                'path'       => 'app/data/company.json',
                'readme'     => 'app/data/README.md',
                'exists'     => CompanyProfile::exists(),
                'sections'   => CompanyProfile::sectionCount(),
                'has_contact' => CompanyProfile::hasContactWays(),
            ],
        ]);
    }

    public static function update(Request $request): void
    {
        self::requireAdmin($request);
        $data = self::body($request);

        $v = Validator::make($data);
        $v->string('app_name', 'اسم النشاط', 1, 80)
          ->string('app_tagline', 'الوصف', 0, 160)
          ->string('about_summary', 'نبذة الصفحة الرئيسية', 0, 1200)
          ->string('welcome_message', 'رسالة الترحيب', 0, 500)
          ->string('contact_phone', 'الهاتف', 0, 30)
          ->string('contact_instagram', 'حساب إنستغرام', 0, 60)
          ->boolean('show_welcome')
          ->boolean('show_search')
          ->boolean('show_contact')
          ->boolean('show_prices')
          ->string('home_hero_eyebrow', 'النص العلوي للغلاف', 0, 120)
          ->string('home_hero_title', 'عنوان الغلاف', 0, 120)
          ->string('home_hero_text', 'نص الغلاف', 0, 1200)
          ->string('home_primary_label', 'نص الزر الأساسي', 0, 60)
          ->string('home_secondary_label', 'نص الزر الثانوي', 0, 60)
          ->string('home_primary_url', 'رابط الزر الأساسي', 0, 300)
          ->string('home_secondary_url', 'رابط الزر الثانوي', 0, 300)
          ->in('home_layout', ['', 'classic', 'split', 'cover', 'spotlight'], 'تخطيط الصفحة الرئيسية')
          ->in('home_height', ['', 'compact', 'large', 'full'], 'ارتفاع الغلاف')
          ->throwIfFails();

        $homeFlags = [
            'home_show_highlights', 'home_show_sections', 'home_show_gallery',
            'home_show_stats', 'home_show_testimonials', 'home_show_faq',
            'home_show_cta', 'home_show_contact',
        ];
        $flagValidator = Validator::make($data);
        foreach ($homeFlags as $flag) {
            $flagValidator->in($flag, ['', '0', '1'], $flag);
        }
        $flagValidator->throwIfFails();

        $allowed = ['app_name', 'app_tagline', 'welcome_message', 'contact_phone', 'contact_instagram'];
        $pairs   = [];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $pairs[$key] = mb_substr(trim((string) $data[$key]), 0, 500);
            }
        }
        if (array_key_exists('about_summary', $data)) {
            $pairs['about_summary'] = mb_substr(trim((string) $data['about_summary']), 0, 1200);
        }
        foreach (['show_welcome', 'show_search', 'show_contact', 'show_prices'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $pairs[$flag] = Validator::toBool($data[$flag]) ? '1' : '0';
            }
        }

        /* --- تخصيص الصفحة الرئيسية --- */
        $homeText = [
            'home_hero_eyebrow'   => 120,
            'home_hero_title'     => 120,
            'home_hero_text'      => 1200,
            'home_primary_label'  => 60,
            'home_secondary_label' => 60,
            'home_layout'         => 20,
            'home_height'         => 20,
        ];
        foreach ($homeText as $key => $max) {
            if (array_key_exists($key, $data)) {
                $pairs[$key] = mb_substr(trim((string) $data[$key]), 0, $max);
            }
        }
        foreach ($homeFlags as $flag) {
            if (array_key_exists($flag, $data)) {
                $value = trim((string) $data[$flag]);
                $pairs[$flag] = $value === '1' ? '1' : ($value === '0' ? '0' : '');
            }
        }
        // الروابط: نطبّعها ونرفض ما ليس http/https أو مسارًا داخليًا
        foreach (['home_primary_url', 'home_secondary_url'] as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $url = trim((string) $data[$key]);
            if ($url === '') {
                $pairs[$key] = '';
                continue;
            }
            if (preg_match('~^(https?://|mailto:|tel:)~i', $url)
                || preg_match('~^(/(?!/)|\#)[^\s]*$~', $url)
                || preg_match('~^[a-z][a-z0-9_-]*(?:/[a-z0-9_-]+)*$~i', $url)) {
                $pairs[$key] = $url;
            } elseif (preg_match('~^www\.[^\s]+$~i', $url)) {
                $pairs[$key] = 'https://' . $url;
            } else {
                throw new HttpError('invalid_url', 'رابط الزر يجب أن يبدأ بـ https:// أو يكون مسارًا داخليًا مثل /products.', 422);
            }
        }

        // الشعار يُرفع كصورة عادية
        if (!empty($data['logo_path'])) {
            $logoPath = is_scalar($data['logo_path']) ? trim((string) $data['logo_path']) : '';
            if ($logoPath !== ''
                && (str_contains($logoPath, '..') || str_starts_with($logoPath, '/') || !preg_match('#^(?:brand|products|app)/[A-Za-z0-9._-]+\.(?:jpe?g|png|webp|gif)$#i', $logoPath))) {
                throw new HttpError('invalid_logo_path', 'مسار الشعار غير صالح.', 422);
            }
            $pairs['logo_path'] = $logoPath;
        }

        if (ProjectConfig::feature('admin_logo')) {
            $file = $request->file('logo');
            if ($file !== null) {
                ImageUploader::delete(RuntimeSettings::get('logo_path'));
                $pairs['logo_path'] = ImageUploader::store($file, 'brand');
            }
        }

        /* --- صورة غلاف الصفحة الرئيسية --- */
        $hero = $request->file('hero');
        if ($hero !== null) {
            ImageUploader::delete(RuntimeSettings::get('hero_image_path'));
            $pairs['hero_image_path'] = ImageUploader::store($hero, 'brand');
        } elseif (!empty($data['remove_hero'])) {
            ImageUploader::delete(RuntimeSettings::get('hero_image_path'));
            $pairs['hero_image_path'] = '';
        }

        RuntimeSettings::setMany($pairs);
        RuntimeSettings::flush();
        \App\Core\Cache::flush();

        Response::ok(['settings' => RuntimeSettings::adminPayload()]);
    }

    public static function export(Request $request): void
    {
        self::requireAdmin($request);
        $data = (new BackupService())->exportSettings();
        if ($data === '') {
            throw new HttpError('not_found', 'ملف الإعدادات غير موجود.', 404);
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="app-config-masked.php"');
        header('Cache-Control: no-store');
        echo $data;
        exit;
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