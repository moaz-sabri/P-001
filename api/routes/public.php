<?php

declare(strict_types=1);

/**
 * المسارات العامة (واجهة الزبون).
 * لا تتطلب أي مصادقة، لكن جميع العمليات الحسّاسة (إنشاء الطلب) محدودة ومتحقَّق منها.
 */

use App\Controllers\CatalogController;
use App\Controllers\HealthController;
use App\Controllers\LocationController;
use App\Controllers\OrderController;

/** الحمولة الكاملة: إعدادات + تصنيفات + منتجات + مواقع. */
$router->get('catalog.snapshot', [CatalogController::class, 'snapshot']);

/** تفاصيل منتج. */
$router->get('product.get', [CatalogController::class, 'product']);

/** المواقع النشطة. */
$router->get('locations.list', [LocationController::class, 'publicList']);

/** إنشاء طلب. */
$router->post('order.create', [OrderController::class, 'create']);

/** متابعة حالة الطلب برمزه. */
$router->get('order.status', [OrderController::class, 'status']);

/** التوفر. */
$router->get('ping', [HealthController::class, 'ping']);