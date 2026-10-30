<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\ProjectConfig;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Repositories\LocationRepository;

final class LocationController
{
    public static function publicList(Request $request): void
    {
        unset($request);
        if (!ProjectConfig::locationsEnabled()) {
            Response::ok(['locations' => []]);
        }
        $repo = new LocationRepository();
        Response::json([
            'ok'   => true,
            'data' => ['locations' => $repo->all(true)],
        ], 200, ['Cache-Control' => 'public, max-age=120']);
    }
}