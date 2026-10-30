<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Config\ProjectConfig;
use App\Core\Http\HttpError;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Security\Auth;
use App\Core\Security\Csrf;
use App\Core\Security\RateLimiter;
use App\Services\InstanceService;

final class InstanceController
{
    public static function state(Request $request): void
    {
        self::requireAuth($request);
        if (!ProjectConfig::feature('admin_rebuild')) {
            throw new HttpError('disabled', 'إدارة دورة حياة النسخة غير مفعّلة في هذه النسخة.', 404);
        }
        Response::json([
            'ok'   => true,
            'data' => (new InstanceService())->state(),
        ], 200, ['Cache-Control' => 'no-store']);
    }

    public static function destroy(Request $request): void
    {
        self::requireAdmin($request);

        if (!ProjectConfig::feature('admin_rebuild')) {
            throw new HttpError('disabled', 'إدارة دورة حياة النسخة غير مفعّلة في هذه النسخة.', 404);
        }

        if (RateLimiter::tooManyLoginAttempts($request)) {
            throw new HttpError(
                'locked',
                'تم إيقاف المحاولات مؤقتًا بعد محاولات فاشلة. حاول بعد '
                . ProjectConfig::ADMIN_LOCKOUT_MINUTES . ' دقيقة.',
                429
            );
        }

        $data    = self::body($request);
        $service = new InstanceService();

        $password = (string) ($data['password'] ?? '');
        $confirm  = trim((string) ($data['confirm'] ?? ''));

        if (!Auth::checkCredentials(Auth::username(), $password)) {
            RateLimiter::recordLogin($request, Auth::username(), false);
            \App\Core\Logger::warning('Instance destroy rejected: wrong password', [
                'ip'   => $request->ip(),
                'user' => Auth::username(),
            ]);
            throw new HttpError('invalid_credentials', 'كلمة المرور غير صحيحة. لم يُحذف شيء.', 403);
        }

        if (!hash_equals(InstanceService::CONFIRM_WORD, $confirm)) {
            throw new HttpError(
                'confirm_mismatch',
                'عبارة التأكيد غير مطابقة. لم يُحذف شيء. اكتب: ' . InstanceService::CONFIRM_WORD,
                422
            );
        }

        RateLimiter::recordLogin($request, Auth::username(), true);
        RateLimiter::clearLoginAttempts($request);

        $before = $service->state();

        $report = $service->destroy('', [
            'confirm' => $confirm,
            'archive' => !isset($data['archive']) || (string) $data['archive'] !== '0',
        ]);

        Auth::logout();

        \App\Core\Logger::info('Instance destroyed', [
            'ip'      => $request->ip(),
            'user'    => $before['preset'] ?? '',
            'orders'  => $report['counts']['orders'] ?? 0,
            'archive' => $report['archive'],
        ]);

        Response::json([
            'ok'   => true,
            'data' => [
                'message'         => 'تم تدمير النسخة. الخادم الآن في حالة ما قبل أول تهيئة.',
                'archive'         => $report['archive'],
                'removed'         => $report['removed'],
                'destroyed'       => $before['counts'],
                'setup_available' => is_file(BASE_PATH . '/setup/index.php'),
                'rebuild_url'     => (string) $report['rebuildUrl'],
                'rebuild_key'     => (string) $report['rebuildKey'],
            ],
        ], 200, ['Cache-Control' => 'no-store']);
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