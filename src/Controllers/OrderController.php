<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\ProjectConfig;
use App\Core\Http\HttpError;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Repositories\OrderRepository;
use App\Services\OrderService;
use App\Services\QrService;

final class OrderController
{
    public static function create(Request $request): void
    {
        // وضع العرض_only: القالب بلا نظام طلبات أصلًا. الرفض من الخادم هو
        // الحماية الحقيقية — إخفاء الزر في الواجهة ليس حماية كافية.
        if (!ProjectConfig::hasOrdering()) {
            throw new HttpError(
                'ordering_disabled',
                'هذا الموقع للعرض فقط ولا يستقبل طلبات. تواصل معنا مباشرة وسنخدمك.',
                404
            );
        }

        $payload = array_merge(self::readBody($request), [
            'idempotency_key' => $request->header('Idempotency-Key') ?? ($request->input('idempotency_key') ?? ''),
        ]);

        $service = new OrderService();
        $order   = $service->create($payload, $request);

        // رابط متابعة الطلب يُبنى من QrService (مصدر واحد لكل روابط الموقع)
        $baseUrl = app_config('app.base_url', '');
        $trackUrl = ProjectConfig::feature('order_tracking') && $baseUrl !== ''
            ? (new QrService())->orderUrl($baseUrl, $order['tracking_token'])
            : null;

        Response::json([
            'ok'   => true,
            'data' => [
                'number'         => $order['number'],
                'total'          => $order['total'],
                'status'         => $order['status'],
                'status_label'   => $order['status_label'],
                'location'       => $order['location'],
                'items'          => $order['items'],
                'created_at'     => $order['created_at'],
                'tracking_token' => $order['tracking_token'],
                'tracking_url'   => $trackUrl,
                'replayed'       => (bool) ($order['idempotent_replay'] ?? false),
            ],
        ], 201, ['Cache-Control' => 'no-store']);
    }

    public static function status(Request $request): void
    {
        if (!ProjectConfig::hasOrdering()) {
            throw new HttpError('ordering_disabled', 'هذا الموقع للعرض فقط ولا يتابع طلبات.', 404);
        }

        $token = (string) ($request->get('token') ?? '');
        $repo  = new OrderRepository();
        $order = $repo->findByToken($token);

        if ($order === null) {
            throw new HttpError('not_found', 'الطلب غير موجود أو انتهت صلاحية الرمز.', 404);
        }

        $full = $repo->find($order['id']);
        Response::json([
            'ok'   => true,
            'data' => [
                'number'       => $full['number'],
                'status'       => $full['status'],
                'status_label' => $full['status_label'],
                'status_color' => $full['status_color'],
                'location'     => $full['location'],
                'order_type'   => $full['order_type_label'],
                'customer'     => $full['customer_name'],
                'total'        => $full['total'],
                'items'        => array_map(
                    static fn(array $i): array => [
                        'name'       => $i['name'],
                        'quantity'   => $i['quantity'],
                        'unit_price' => $i['unit_price'],
                        'line_total' => $i['line_total'],
                    ],
                    $full['items']
                ),
                'notes'       => $full['notes'],
                'created_at'  => $full['created_at'],
                'time_label'  => $full['time_label'],
            ],
        ], 200, ['Cache-Control' => 'no-store']);
    }

    /** قراءة جسم الطلب: JSON أو form-encoded. */
    private static function readBody(Request $request): array
    {
        $body = $request->body();
        if ($body === []) {
            $json = $request->json();
            if ($json !== []) {
                return $json;
            }
        }
        return $body;
    }
}