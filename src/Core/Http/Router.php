<?php

declare(strict_types=1);

namespace App\Core\Http;

/**
 * موجّه بسيط ( nearer to Slim 4 ): key "method route" → callable أو [Controller::class, 'method'].
 * لا حاجة لمكتبة توجيه. إمكان استدعاء م controlling tasks (methods).only،
 * وحل lazy للكنترولات lazy (بدون container/composer).
 */
final class Router
{
    /** @var array<string, mixed> */
    private array $routes = [];

    public function get(string $name, mixed $handler): void
    {
        $this->add('GET', $name, $handler);
    }

    public function post(string $name, mixed $handler): void
    {
        $this->add('POST', $name, $handler);
    }

    public function put(string $name, mixed $handler): void
    {
        $this->add('PUT', $name, $handler);
    }

    public function patch(string $name, mixed $handler): void
    {
        $this->add('PATCH', $name, $handler);
    }

    public function delete(string $name, mixed $handler): void
    {
        $this->add('DELETE', $name, $handler);
    }

    public function add(string $method, string $name, mixed $handler): void
    {
        $this->routes[strtoupper($method) . ' ' . $name] = $handler;
    }

    public function dispatch(Request $request): void
    {
        $route = (string) ($request->input('r') ?? $request->input('route') ?? '');
        if ($route === '') {
            $path = trim($request->path(), '/');
            // دعم اختياري: /api/index.php/route أو /api/route
            if (str_contains($path, 'api/')) {
                $route = trim(substr($path, (int) strpos($path, 'api/') + 4), '/');
            }
        }
        $route = strtolower(trim($route, '/'));
        $key   = strtoupper($request->method()) . ' ' . $route;

        if (!isset($this->routes[$key])) {
            Response::error('not_found', 'المسار المطلوب غير موجود.', 404);
        }

        $handler = $this->resolve($this->routes[$key]);
        if (is_callable($handler)) {
            $handler($request);
            return;
        }

        Response::error('server_error', 'Handler غير قابل للاستدعاء.', 500);
    }

    /**
     * حل مرجع controller/method (أسلوب Slim 4) без container/composer.
     * يدعم: closure، [Class::class, 'method']، 'Class@method', 'Class::method'.
     */
    private function resolve(mixed $handler): mixed
    {
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            $handler = [$class, $method];
        } elseif (is_string($handler) && str_contains($handler, '::')) {
            [$class, $method] = explode('::', $handler, 2);
            $handler = [$class, $method];
        } elseif (is_string($handler) && str_contains($handler, ':')) {
            [$class, $method] = explode(':', $handler, 2);
            $handler = [$class, $method];
        }

        if (is_array($handler) && count($handler) === 2 && is_string($handler[0]) && class_exists($handler[0])) {
            $class  = $handler[0];
            $method = (string) $handler[1];
            if (!method_exists($class, $method)) {
                return null;
            }
            $reflection = new \ReflectionMethod($class, $method);
            if (!$reflection->isStatic()) {
                try {
                    $instance = new $class();
                } catch (\Throwable $e) {
                    return null;
                }
                return [$instance, $method];
            }
            return $handler;
        }

        return $handler;
    }
}