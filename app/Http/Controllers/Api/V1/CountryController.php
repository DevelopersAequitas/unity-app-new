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
     * Get worldwide countries with ISO-2 codes, calling dial codes, and flag emojis.
     */
    public function index(Request $request, CountryService $countryService): JsonResponse
    {
        $search = $request->input('search');
        $countries = $countryService->getCountries(is_string($search) ? $search : null);

        return $this->success($countries, 'Countries fetched successfully.');
    }
}
