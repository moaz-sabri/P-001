<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Config\ProjectConfig;
use App\Core\Http\HttpError;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Security\Auth;
use App\Core\Security\Csrf;
use App\Services\BackupService;

final class BackupController
{
    public static function index(Request $request): void
    {
        self::requireAuth($request);
        if (!ProjectConfig::feature('admin_backups')) {
            throw new HttpError('disabled', 'هذه الميزة غير مفعّلة.', 404);
        }
        Response::ok(['backups' => (new BackupService())->listBackups()]);
    }

    public static function create(Request $request): void
    {
        self::requireAdmin($request);
        $path = (new BackupService())->create();
        Response::ok([
            'message' => 'تم إنشاء النسخة الاحتياطية.',
            'file'    => basename($path),
            'backups' => (new BackupService())->listBackups(),
        ], 201);
    }

    public static function download(Request $request): void
    {
        self::requireAuth($request);
        $name = (string) ($request->get('name') ?? '');
        $data = (new BackupService())->read($name);
        if ($data === null) {
            throw new HttpError('not_found', 'ملف النسخة غير موجود.', 404);
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . strlen($data));
        header('Cache-Control: no-store');
        echo $data;
        exit;
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