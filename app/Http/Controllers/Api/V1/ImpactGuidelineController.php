<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ImpactGuideline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ImpactGuidelineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $guidelines = ImpactGuideline::query()
                ->active()
                ->ordered()
                ->get();

            $firstItem = $guidelines->first();
            $title = $firstItem?->title ?? 'Your Life Impact Score';
            $description = $firstItem?->description ?? 'Study this. Know it. Start counting from today. Every action below earns you impact — tracked in the Unity App.';
            $icon = $firstItem?->icon ? (filter_var($firstItem->icon, FILTER_VALIDATE_URL) ? $firstItem->icon : asset($firstItem->icon)) : null;

            $data = [
                'title' => $title,
                'description' => $description,
                'icon' => $icon,
                'guidelines' => $guidelines->map(fn (ImpactGuideline $item) => [
                    'id' => (string) $item->id,
                    'action' => $item->action,
                    'category' => $item->category,
                    'impact_value' => (int) $item->impact_value,
                    'impact_unit' => $item->impact_unit,
                    'display_order' => (int) $item->display_order,
                ])->values()->all(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Impact guidelines fetched successfully.',
                'data' => $data,
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch impact guidelines.',
                'data' => null,
            ], 500);
        }
    }
}
