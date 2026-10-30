<?php

declare(strict_types=1);

/**
 * مسارات لوحة الإدارة — كلها تتطلب جلسة صالحة (داخل methods)، وكل عملية تعديل تتطلب رمز CSRF.
 */

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\BackupController;
use App\Controllers\Admin\CategoryController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\InstanceController;
use App\Controllers\Admin\LocationController;
use App\Controllers\Admin\OrderController;
use App\Controllers\Admin\ProductController;
use App\Controllers\Admin\QrController;
use App\Controllers\Admin\SettingsController;

/* ============================ المصادقة ============================ */

$router->post('admin.login', [AuthController::class, 'login']);
$router->post('admin.logout', [AuthController::class, 'logout']);
$router->get('admin.session', [AuthController::class, 'session']);
$router->post('admin.password', [AuthController::class, 'password']);

/* ============================ لوحة الإدارة ============================ */

$router->get('admin.dashboard', [DashboardController::class, 'dashboard']);
$router->get('admin.orders.active', [DashboardController::class, 'ordersActive']);

/* ---------------------------- التصنيفات ---------------------------- */

$router->get('admin.categories', [CategoryController::class, 'index']);
$router->post('admin.category.create', [CategoryController::class, 'create']);
$router->post('admin.category.update', [CategoryController::class, 'update']);
$router->post('admin.category.delete', [CategoryController::class, 'delete']);
$router->post('admin.categories.reorder', [CategoryController::class, 'reorder']);

/* ---------------------------- المنتجات ---------------------------- */

$router->get('admin.products', [ProductController::class, 'index']);
$router->post('admin.product.create', [ProductController::class, 'create']);
$router->post('admin.product.update', [ProductController::class, 'update']);
$router->post('admin.product.delete', [ProductController::class, 'delete']);
$router->post('admin.product.image', [ProductController::class, 'image']);
$router->post('admin.product.gallery', [ProductController::class, 'gallery']);

/* ---------------------------- الطلبات ---------------------------- */

$router->get('admin.orders', [OrderController::class, 'index']);
$router->get('admin.order', [OrderController::class, 'show']);
$router->post('admin.order.status', [OrderController::class, 'changeStatus']);

/* ---------------------------- المواقع وQR ---------------------------- */

$router->get('admin.locations', [LocationController::class, 'index']);
$router->post('admin.location.save', [LocationController::class, 'save']);
$router->post('admin.location.delete', [LocationController::class, 'delete']);

$router->get('admin.qr', [QrController::class, 'qr']);
$router->get('admin.qr.sheet', [QrController::class, 'sheet']);
$router->get('admin.order.qr', [QrController::class, 'orderQr']);

/* ---------------------------- إعدادات العرض ---------------------------- */

$router->get('admin.settings', [SettingsController::class, 'index']);
$router->post('admin.settings.update', [SettingsController::class, 'update']);
$router->post('admin.settings.export', [SettingsController::class, 'export']);

/* ---------------------------- النسخ الاحتياطي ---------------------------- */

$router->get('admin.backups', [BackupController::class, 'index']);
$router->post('admin.backup.create', [BackupController::class, 'create']);
$router->get('admin.backup.download', [BackupController::class, 'download']);

/* ---------------------------- دورة حياة النسخة ---------------------------- */

$router->get('admin.instance.state', [InstanceController::class, 'state']);
$router->post('admin.instance.destroy', [InstanceController::class, 'destroy']);