<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Http\HttpError;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Validator;
use App\Core\Security\Auth;
use App\Core\Security\Csrf;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;

final class CategoryController
{
    public static function index(Request $request): void
    {
        self::requireAuth($request);
        $repo = new CategoryRepository();
        Response::ok(['categories' => $repo->all()]);
    }

    public static function create(Request $request): void
    {
        self::requireAdmin($request);
        $data = self::validateCategory(self::body($request));
        $id   = (new CategoryRepository())->create($data);
        Response::ok(['id' => $id, 'categories' => (new CategoryRepository())->all()], 201);
    }

    public static function update(Request $request): void
    {
        self::requireAdmin($request);
        $data = self::validateCategory(self::body($request));
        $id   = (int) ($data['id'] ?? 0);
        if ($id <= 0 || (new CategoryRepository())->find($id) === null) {
            throw new HttpError('not_found', 'التصنيف غير موجود.', 404);
        }
        (new CategoryRepository())->update($id, $data);
        Response::ok(['categories' => (new CategoryRepository())->all()]);
    }

    public static function delete(Request $request): void
    {
        self::requireAdmin($request);
        $data = self::body($request);
        $id   = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            throw new HttpError('not_found', 'التصنيف غير موجود.', 404);
        }
        $repo = new CategoryRepository();
        $repo->delete($id);
        Response::ok(['categories' => $repo->all(), 'products' => (new ProductRepository())->allAdmin()]);
    }

    public static function reorder(Request $request): void
    {
        self::requireAdmin($request);
        $ids = (array) (self::body($request)['ids'] ?? []);
        if ($ids === []) {
            throw new HttpError('invalid_input', 'قائمة الترتيب فارغة.', 422);
        }
        (new CategoryRepository())->reorder(array_map('intval', $ids));
        Response::ok(['categories' => (new CategoryRepository())->all()]);
    }

    private static function validateCategory(array $data): array
    {
        $v = Validator::make($data);
        $v->required('name', 'اسم التصنيف')
          ->string('name', 'اسم التصنيف', 1, 80)
          ->string('description', 'الوصف', 0, 300)
          ->integer('sort_order', 'الترتيب', 0, 100000)
          ->boolean('is_active');

        return $v->throwIfFails();
    }

    private static function body(Request $request): array
    {
        $data = $request->body();
        return $data === [] ? $request->json() : $data;
    }

    private static function requireAuth(Request $request): void
    {
        unset($request);
        if (!\App\Core\Security\Auth::check()) {
            throw new HttpError('unauthenticated', 'الجلسة منتهية. سجّل الدخول مجددًا.', 401);
        }
    }

    private static function requireAdmin(Request $request): void
    {
        self::requireAuth($request);
        Csrf::verify($request);
    }
}