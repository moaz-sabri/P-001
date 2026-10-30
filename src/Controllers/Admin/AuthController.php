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

final class AuthController
{
    public static function login(Request $request): void
    {
        $data = self::body($request);

        if (RateLimiter::tooManyLoginAttempts($request)) {
            throw new HttpError('locked', 'تم إيقاف المحاولات مؤقتًا. حاول بعد ' . ProjectConfig::ADMIN_LOCKOUT_MINUTES . ' دقيقة.', 429);
        }

        $username = mb_substr(trim((string) ($data['username'] ?? '')), 0, 60);
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || $password === '') {
            throw new HttpError('missing_credentials', 'أدخل اسم المستخدم وكلمة المرور.', 422);
        }

        if (!Auth::checkCredentials($username, $password)) {
            RateLimiter::recordLogin($request, $username, false);
            \App\Core\Logger::warning('Failed admin login', ['username' => $username, 'ip' => $request->ip()]);
            throw new HttpError('invalid_credentials', 'بيانات الدخول غير صحيحة.', 401);
        }

        Auth::login($username);
        RateLimiter::recordLogin($request, $username, true);
        RateLimiter::clearLoginAttempts($request);

        Response::json([
            'ok'   => true,
            'data' => [
                'username'  => Auth::username(),
                'csrf'      => Auth::token(),
                'locations' => ProjectConfig::locationPlural(),
            ],
        ], 200, ['Cache-Control' => 'no-store']);
    }

    public static function logout(Request $request): void
    {
        self::requireAuth($request);
        Auth::logout();
        Response::json(['ok' => true, 'data' => ['message' => 'تم تسجيل الخروج.']], 200, ['Cache-Control' => 'no-store']);
    }

    public static function session(Request $request): void
    {
        self::requireAuth($request);
        Response::json([
            'ok'   => true,
            'data' => ['username' => Auth::username(), 'csrf' => Auth::token()],
        ], 200, ['Cache-Control' => 'no-store']);
    }

    public static function password(Request $request): void
    {
        self::requireAdmin($request);
        $data    = self::body($request);
        $current = (string) ($data['current_password'] ?? '');
        $new     = (string) ($data['new_password'] ?? '');

        if (!Auth::checkCredentials(Auth::username(), $current)) {
            throw new HttpError('invalid_credentials', 'كلمة المرور الحالية غير صحيحة.', 401);
        }
        if (mb_strlen($new) < 8) {
            throw new HttpError('weak_password', 'كلمة المرور الجديدة يجب ألا تقل عن 8 محارف.', 422);
        }
        if (mb_strlen($new) > 200) {
            throw new HttpError('weak_password', 'كلمة المرور طويلة جدًا.', 422);
        }

        Auth::changePassword($new);
        \App\Core\Logger::info('Admin password changed', ['ip' => $request->ip()]);
        Response::ok(['message' => 'تم تغيير كلمة المرور.']);
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