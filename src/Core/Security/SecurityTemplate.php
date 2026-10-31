<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Core\Http\HttpError;
use App\Core\Http\Request;
use App\Core\Http\Response;

/** */
class SecurityTemplate
{
    /** */
    public const MAX_REQUEST_BODY_BYTES = 2 * 1024 * 1024; // 2 MB

    /** */
    public const MAX_URI_LENGTH = 2048;

    /** */
    public const ALLOWED_METHODS = ['GET', 'POST', 'HEAD', 'OPTIONS'];

    /** */
    public const DEFAULT_RATE_LIMIT_PER_MINUTE = 120;

    /** */
    public const PASSWORD_MIN_LENGTH = 8;
    public const PASSWORD_MAX_LENGTH = 200;

    /** */
    public static function guard(Request $request, int $maxBytes = self::MAX_REQUEST_BODY_BYTES): void
    {
        self::validateRequest($request, $maxBytes);
        self::applyHeaders();
    }

    /** */
    public static function protect(Request $request, int $maxBytes = self::MAX_REQUEST_BODY_BYTES): void
    {
        self::guard($request, $maxBytes);
    }

    public static function enforce(Request $request, int $maxBytes = self::MAX_REQUEST_BODY_BYTES): void
    {
        self::guard($request, $maxBytes);
    }

    public static function check(Request $request, int $maxBytes = self::MAX_REQUEST_BODY_BYTES): void
    {
        self::guard($request, $maxBytes);
    }

    /** */
    public static function validateRequest(Request $request, int $maxBytes = self::MAX_REQUEST_BODY_BYTES): void
    {
        $method = $request->method();
        if (!in_array($method, self::ALLOWED_METHODS, true)) {
            throw new HttpError('method_not_allowed', 'طريقة الطلب غير مسموحة.', 405);
        }

        $path = (string) ($request->path() ?? '');
        if (strlen($path) > self::MAX_URI_LENGTH) {
            throw new HttpError('uri_too_long', 'URI ط·آ·ط¢آ·ط·آ¸ط«â€ ط·آ¸ط¸آ¹ط·آ¸أ¢â‚¬â€چ ط·آ·ط¢آ¬ط·آ·ط¢آ¯ط·آ·ط¢آ§ط·آ¸أ¢â‚¬آ¹.', 414);
        }

        $contentLength = $request->header('Content-Length');
        if (is_numeric($contentLength) && (int) $contentLength > $maxBytes) {
            throw new HttpError('payload_too_large', 'حجم الطلب تجاوز الحد المسموح.', 413);
        }
    }

    /** */
    public static function applyHeaders(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Permitted-Cross-Domain-Policies: none');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        $https = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');
        if ($https) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /** */
    public static function headers(): void
    {
        self::applyHeaders();
    }

    public static function secureHeaders(): void
    {
        self::applyHeaders();
    }

    /* ------------------------- Access control ------------------------- */

    public static function requireAuth(): void
    {
        if (!Auth::check()) {
            throw new HttpError('unauthenticated', 'غير مصرح. رجاء تسجيل الدخول.', 401);
        }
    }

    public static function requireAdmin(Request $request): void
    {
        self::requireAuth();
        Csrf::verify($request);
    }

    public static function requireCsrf(Request $request): void
    {
        Csrf::verify($request);
    }

    /* ------------------------- Rate limiting ------------------------- */

    public static function throttle(
        Request $request,
        string $bucket,
        int $limitPerMinute = self::DEFAULT_RATE_LIMIT_PER_MINUTE
    ): void {
        if (!RateLimiter::hitPublicApi($request, $bucket, $limitPerMinute)) {
            throw new HttpError('too_many_requests', 'تجاوزت حد الطلبات. حاول لاحقا.', 429);
        }
    }

    public static function rateLimit(
        Request $request,
        string $bucket,
        int $limitPerMinute = self::DEFAULT_RATE_LIMIT_PER_MINUTE
    ): void {
        self::throttle($request, $bucket, $limitPerMinute);
    }

    public static function hit(Request $request, string $bucket, int $limitPerMinute = self::DEFAULT_RATE_LIMIT_PER_MINUTE): bool
    {
        return RateLimiter::hitPublicApi($request, $bucket, $limitPerMinute);
    }

    /* ------------------------- Input handling ------------------------- */

    /** */
    public static function cleanText(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = strip_tags($value);
        return trim($value);
    }

    public static function clean(string $value): string
    {
        return self::cleanText($value);
    }

    public static function sanitizeString(string $value, int $max = 500): string
    {
        $value = self::cleanText($value);
        if (mb_strlen($value) > $max) {
            $value = mb_substr($value, 0, $max);
        }
        return $value;
    }

    /** */
    public static function sanitize(mixed $input, int $maxString = 10000): mixed
    {
        if (is_string($input)) {
            return self::sanitizeString($input, $maxString);
        }
        if (is_array($input)) {
            $out = [];
            foreach ($input as $k => $v) {
                $key = is_string($k) ? self::sanitizeString($k, 200) : $k;
                $out[$key] = self::sanitize($v, $maxString);
            }
            return $out;
        }
        return $input;
    }

    public static function validateInput(array $data): array
    {
        return self::sanitize($data);
    }

    public static function input(array $data): array
    {
        return self::sanitize($data);
    }

    /* ------------------------- Output handling ------------------------- */

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function html(string $value): string
    {
        return self::escape($value);
    }

    public static function json(mixed $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        return is_string($encoded) ? $encoded : 'null';
    }

    /* ------------------------- Token / hash ------------------------- */

    public static function token(int $bytes = 32): string
    {
        return bin2hex(random_bytes(max(16, $bytes)));
    }

    public static function hash(string $value): string
    {
        return password_hash($value, PASSWORD_DEFAULT);
    }

    public static function verifyHash(string $value, string $hash): bool
    {
        return password_verify($value, $hash);
    }

    public static function verifyToken(string $sent, string $real): bool
    {
        if ($sent === '' || $real === '') {
            return false;
        }
        return hash_equals($real, $sent);
    }

    /* ------------------------- Password policy ------------------------- */

    public static function strongPassword(string $password): bool
    {
        $len = mb_strlen($password);
        return $len >= self::PASSWORD_MIN_LENGTH && $len <= self::PASSWORD_MAX_LENGTH;
    }
}
