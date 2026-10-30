<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Config\ProjectConfig;
use App\Core\Http\HttpError;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Security\Auth;
use App\Core\Security\Csrf;
use App\Repositories\OrderRepository;
use App\Services\OrderService;

final class OrderController
{
    public static function index(Request $request): void
    {
        self::requireAuth($request);
        $repo   = new OrderRepository();
        $result = $repo->listForAdmin([
            'status'      => (string) ($request->get('status') ?? ''),
            'location_id' => (string) ($request->get('location_id') ?? ''),
            'date'        => (string) ($request->get('date') ?? ''),
            'q'           => mb_substr((string) ($request->get('q') ?? ''), 0, 60),
        ], min(200, max(1, (int) ($request->get('limit') ?? 100))));

        Response::ok([
            'orders'   => $result['items'],
            'total'    => $result['total'],
            'statuses' => array_map(
                static fn(string $k, array $def): array => ['key' => $k, 'label' => $def['label'], 'color' => $def['color']],
                array_keys(ProjectConfig::ORDER_STATUSES),
                ProjectConfig::ORDER_STATUSES
            ),
        ]);
    }

    public static function show(Request $request): void
    {
        self::requireAuth($request);
        $id    = (int) ($request->get('id') ?? 0);
        $order = (new OrderRepository())->find($id);
        if ($order === null) {
            throw new HttpError('not_found', 'الطلب غير موجود.', 404);
        }
        Response::ok(['order' => $order]);
    }

    public static function changeStatus(Request $request): void
    {
        self::requireAdmin($request);
        $data = self::body($request);
        $id   = (int) ($data['id'] ?? 0);
        $to   = (string) ($data['status'] ?? '');
        $note = mb_substr((string) ($data['note'] ?? ''), 0, 300);

        if (!isset(ProjectConfig::ORDER_STATUSES[$to])) {
            throw new HttpError('invalid_status', 'حالة غير معروفة.', 422);
        }

        $order = (new OrderService())->changeStatus($id, $to, 'admin:admin', $note);
        Response::ok(['order' => $order, 'stats' => (new OrderRepository())->stats()]);
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