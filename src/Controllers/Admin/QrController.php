<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Http\HttpError;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Security\Auth;
use App\Core\Security\Csrf;
use App\Repositories\LocationRepository;
use App\Repositories\OrderRepository;
use App\Core\RuntimeSettings;
use App\Services\QrService;

final class QrController
{
    public static function qr(Request $request): void
    {
        self::requireAuth($request);
        $type  = (string) ($request->get('type') ?? 'location');
        $value = (string) ($request->get('value') ?? '');
        $size  = min(16, max(4, (int) ($request->get('size') ?? 8)));
        $base  = app_config('app.base_url', '');
        if ($base === '') {
            throw new HttpError('missing_base_url', 'لم يُضبط رابط الموقع بعد. اضبطه من إعدادات الاستضافة.', 500);
        }

        $service = new QrService();
        try {
            if ($type === 'order') {
                $svg = $service->orderSvg($base, $value, $size);
            } else {
                $svg = $service->locationSvg($base, $value, $size);
            }
        } catch (\RuntimeException $e) {
            throw new HttpError('qr_failed', $e->getMessage(), 422);
        }

        header('Content-Type: image/svg+xml; charset=utf-8');
        header('Cache-Control: no-store');
        header('Content-Disposition: inline; filename="qr-' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $type) . '.svg"');
        echo $svg;
        exit;
    }

    public static function sheet(Request $request): void
    {
        self::requireAuth($request);
        $repo   = new LocationRepository();
        $base   = app_config('app.base_url', '');
        $html   = (new QrService())->printSheetHtml($base, $repo->all(), RuntimeSettings::publicPayload());
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        echo $html;
        exit;
    }

    public static function orderQr(Request $request): void
    {
        self::requireAuth($request);
        $order = (new OrderRepository())->find((int) ($request->get('id') ?? 0));
        if ($order === null) {
            throw new HttpError('not_found', 'الطلب غير موجود.', 404);
        }
        $base = app_config('app.base_url', '');
        Response::ok([
            'qr_url' => $base !== '' ? (new QrService())->orderUrl($base, $order['tracking_token']) : '',
            'number'  => $order['number'],
            'token'   => $order['tracking_token'],
        ]);
    }

    private static function requireAuth(Request $request): void
    {
        unset($request);
        if (!Auth::check()) {
            throw new HttpError('unauthenticated', 'الجلسة منتهية. سجّل الدخول مجددًا.', 401);
        }
    }
}