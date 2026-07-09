<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\CityResource;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityController extends BaseApiController
{
    /**
     * Display a paginated listing of cities, optionally filtered by search query or state.
     */
    public function index(Request $request): JsonResponse
    {
        $query = City::query();

        if ($search = $request->input('search')) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        if ($state = $request->input('state')) {
            $query->where('state', $state);
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $cities = $query->orderBy('name')->paginate($perPage);

        $data = [
            'items' => CityResource::collection($cities),
            'pagination' => [
                'current_page' => $cities->currentPage(),
                'last_page' => $cities->lastPage(),
                'per_page' => $cities->perPage(),
                'total' => $cities->total(),
            ],
        ];

        return $this->success($data, 'Cities fetched successfully.');
    }
}
