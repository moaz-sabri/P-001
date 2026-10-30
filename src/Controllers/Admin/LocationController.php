<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Http\HttpError;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Validator;
use App\Core\Security\Auth;
use App\Core\Security\Csrf;
use App\Repositories\LocationRepository;
use App\Services\QrService;

final class LocationController
{
    public static function index(Request $request): void
    {
        self::requireAuth($request);
        $repo = new LocationRepository();
        $base = app_config('app.base_url', '');
        $locations = $repo->all();
        foreach ($locations as &$location) {
            $location['qr_url'] = $base !== '' ? (new QrService())->locationUrl($base, $location['code']) : '';
        }
        unset($location);

        Response::ok(['locations' => $locations, 'suggested_code' => $repo->suggestCode()]);
    }

    public static function save(Request $request): void
    {
        self::requireAdmin($request);
        $data = self::body($request);
        $repo = new LocationRepository();

        $v = Validator::make($data);
        $v->required('name', 'اسم الموقع')
          ->string('name', 'اسم الموقع', 1, 60)
          ->string('code', 'الرمز', 0, 24)
          ->string('type', 'النوع', 0, 40)
          ->string('note', 'ملاحظة', 0, 200)
          ->integer('sort_order', 'الترتيب', 0, 100000)
          ->boolean('is_active')
          ->pattern('code', '/^[A-Za-z0-9_\-]{1,24}$/', 'الرمز يقبل حروفًا إنجليزية وأرقامًا فقط.')
          ->throwIfFails();
        $clean = $v->validated();

        $id   = (int) ($clean['id'] ?? 0);
        $code = strtoupper(trim((string) ($clean['code'] ?? '')));
        if ($code === '') {
            $code = $repo->suggestCode();
        }
        if ($repo->codeExists($code, $id > 0 ? $id : null)) {
            throw new HttpError('duplicate_code', 'هذا الرمز مستخدم لموقع آخر.', 422);
        }
        $clean['code'] = $code;
        $repo->save($id > 0 ? $id : null, $clean);

        Response::ok(['locations' => $repo->all()]);
    }

    public static function delete(Request $request): void
    {
        self::requireAdmin($request);
        $id = (int) (self::body($request)['id'] ?? 0);
        (new LocationRepository())->delete($id);
        Response::ok(['locations' => (new LocationRepository())->all()]);
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