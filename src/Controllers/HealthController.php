<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\Request;
use App\Core\Http\Response;

final class HealthController
{
    public static function ping(Request $request): void
    {
        unset($request);
        Response::json(['ok' => true, 'data' => ['time' => date('c'), 'server' => 'php']], 200, ['Cache-Control' => 'no-store']);
    }
}