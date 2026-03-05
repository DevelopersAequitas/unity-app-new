<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\CircleSubscriptionPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CircleSubscriptionPriceController extends Controller
{
    public function index(Request $request, Circle $circle): JsonResponse
    {
        $query = CircleSubscriptionPrice::query()
            ->where('circle_id', $circle->id)
            ->orderBy('duration_months');

        if (Schema::hasColumn('circle_subscription_prices', 'is_active')) {
            $query->where('is_active', true);
        }

        $prices = $query->get([
            'id',
            'circle_id',
            'duration_months',
            'price',
            'currency',
            'zoho_addon_id',
            'zoho_addon_code',
            'zoho_addon_name',
            'payload',
        ]);

        Log::info('Circle subscription prices fetched', [
            'circle_id' => $circle->id,
            'count' => $prices->count(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Circle subscription prices fetched successfully.',
            'data' => $prices,
            'meta' => null,
        ]);
    }
}
