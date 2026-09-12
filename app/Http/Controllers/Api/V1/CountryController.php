<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\CountryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CountryController extends BaseApiController
{
    /**
     * Get worldwide countries with ISO-2 codes, calling dial codes, flag emojis, and pagination.
     */
    public function index(Request $request, CountryService $countryService): JsonResponse
    {
        $search = $request->input('search');
        $searchQuery = is_string($search) ? $search : null;

        if ($request->input('paginate') === 'false' || $request->input('paginate') === '0' || $request->boolean('all') || $request->input('per_page') === 'all') {
            $countries = $countryService->getCountries($searchQuery);

            return $this->success($countries, 'Countries fetched successfully.');
        }

        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        $paginated = $countryService->getPaginatedCountries($searchQuery, $page, $perPage);

        return $this->success($paginated, 'Countries fetched successfully.');
    }
}
