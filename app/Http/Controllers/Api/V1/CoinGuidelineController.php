<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CoinGuideline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class CoinGuidelineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $guidelines = CoinGuideline::query()
                ->active()
                ->ordered()
                ->get();

            $firstItem = $guidelines->first();
            $title = $firstItem?->title ?? 'The Coin Reward System';
            $description = $firstItem?->description ?? 'Coins are rewards for being an active community builder. They reflect your engagement and contributions to the network.';
            $icon = $firstItem?->icon ? (filter_var($firstItem->icon, FILTER_VALIDATE_URL) ? $firstItem->icon : asset($firstItem->icon)) : null;

            $data = [
                'title' => $title,
                'description' => $description,
                'icon' => $icon,
                'guidelines' => $guidelines->map(fn (CoinGuideline $item) => [
                    'id' => (string) $item->id,
                    'activity' => $item->activity,
                    'coins' => (int) $item->coins,
                    'display_order' => (int) $item->display_order,
                ])->values()->all(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Coin guidelines fetched successfully.',
                'data' => $data,
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch coin guidelines.',
                'data' => null,
            ], 500);
        }
    }
}
