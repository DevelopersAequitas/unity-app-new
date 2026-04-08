<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\CircleCategoryResource;
use App\Models\CircleCategory;
use Illuminate\Http\Request;

class CircleCategoryController extends BaseApiController
{
    public function index(Request $request)
    {
        $search = trim((string) ($request->query('search', $request->query('q', ''))));

        $items = CircleCategory::query()
            ->where('level', 1)
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'ILIKE', '%' . $search . '%');
            })
            ->orderByRaw('COALESCE(sort_order, 2147483647) ASC')
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'slug',
                'circle_key',
                'level',
                'sort_order',
                'is_active',
            ]);

        return $this->success([
            'items' => $items,
        ]);
    }

    public function show(string $idOrSlug)
    {
        $query = CircleCategory::query()
            ->where('is_active', true);

        $category = ctype_digit($idOrSlug)
            ? (clone $query)->where('id', (int) $idOrSlug)->first()
            : (clone $query)->where('slug', $idOrSlug)->first();

        if (! $category) {
            return $this->error('Circle category not found', 404);
        }

        return $this->success(new CircleCategoryResource($category));
    }
}
