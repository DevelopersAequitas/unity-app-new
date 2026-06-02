<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DedLocationService;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function districts(string $state, DedLocationService $dedLocationService): JsonResponse
    {
        return response()->json([
            'data' => $dedLocationService->getAvailableDistrictsByState($state)->values(),
        ]);
    }
}
