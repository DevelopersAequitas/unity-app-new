<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\CityResource;
use App\Services\CityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityController extends BaseApiController
{
    /**
     * Display a paginated listing of cities, optionally filtered by country, state, or search.
     */
    public function index(Request $request, CityService $cityService): JsonResponse
    {
        $filters = [
            'country_code' => $request->input('country_code'),
            'country' => $request->input('country'),
            'state_code' => $request->input('state_code'),
            'state' => $request->input('state'),
            'search' => $request->input('search'),
        ];

        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        $cities = $cityService->getPaginatedCities($filters, $page, $perPage);

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
