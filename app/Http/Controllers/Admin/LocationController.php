<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class LocationController extends Controller
{
    public function districts(string $state): JsonResponse
    {
        if (! Schema::hasTable('states') || ! Schema::hasTable('districts')) {
            return response()->json(['data' => []]);
        }

        $stateRecord = State::query()
            ->where('id', $state)
            ->where('status', 'active')
            ->firstOrFail();

        $districts = District::query()
            ->where('state_id', $stateRecord->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'data' => $districts->map(fn (District $district) => [
                'id' => $district->id,
                'name' => $district->name,
            ])->values(),
        ]);
    }
}
