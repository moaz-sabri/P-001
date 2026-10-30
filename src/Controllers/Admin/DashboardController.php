<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Http\HttpError;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Security\Auth;
use App\Repositories\OrderRepository;

final class DashboardController
{
    public static function dashboard(Request $request): void
    {
        self::requireAuth($request);
        $orders = new OrderRepository();
        [$latest] = array_values($orders->listForAdmin([], 8));

        Response::json([
            'ok'   => true,
            'data' => [
                'stats'  => $orders->stats(),
                'latest' => $latest,
                'time'   => date('H:i'),
            ],
        ], 200, ['Cache-Control' => 'no-store']);
    }

    public static function ordersActive(Request $request): void
    {
        self::requireAuth($request);
        $orders = new OrderRepository();
        Response::json([
            'ok'   => true,
            'data' => [
                'orders' => $orders->activeSince(date('Y-m-d H:i:s')),
                'stats'  => $orders->stats(),
                'time'   => date('H:i'),
            ],
        ], 200, ['Cache-Control' => 'no-store']);
    }

    private static function requireAuth(Request $request): void
    {
        unset($request);
        if (!Auth::check()) {
            throw new HttpError('unauthenticated', 'الجلسة منتهية. سجّل الدخول مجددًا.', 401);
        }
    }
}