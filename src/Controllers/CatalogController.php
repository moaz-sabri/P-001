<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\ProjectConfig;
use App\Core\Http\HttpError;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Services\CatalogService;

final class CatalogController
{
    public static function snapshot(Request $request): void
    {
        $service = new CatalogService();
        $search  = (string) ($request->get('q') ?? '');
        $payload = $service->snapshot(mb_substr(trim($search), 0, 60) ?: null);

        Response::json([
            'ok'   => true,
            'data' => $payload,
            'meta' => ['generated_at' => date('c')],
        ], 200, [
            'Cache-Control' => 'public, max-age=60, stale-while-revalidate=86400',
        ]);
    }

    public static function product(Request $request): void
    {
        $id      = (int) ($request->get('id') ?? 0);
        $service = new CatalogService();
        $product = $id > 0 ? $service->productDetails($id) : null;

        if ($product === null) {
            throw new HttpError('not_found', 'المنتج غير موجود أو لم يعد متاحًا.', 404);
        }
        Response::json(['ok' => true, 'data' => $product], 200, ['Cache-Control' => 'public, max-age=300']);
    }
}