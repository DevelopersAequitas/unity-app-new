<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\IndustryTreeResource;
use App\Models\Industry;
use Illuminate\Http\JsonResponse;

class IndustryController extends Controller
{
    public function tree(): JsonResponse
    {
        $hasSortOrder = \Illuminate\Support\Facades\Schema::hasColumn('industries', 'sort_order');

        $query = Industry::query()
            ->active()
            ->whereNull('parent_id')
            ->with([
                'children' => function ($query) use ($hasSortOrder) {
                    $q = $query->active();
                    if ($hasSortOrder) {
                        $q->orderBy('sort_order');
                    }

                    return $q->orderBy('name');
                },
            ]);

        if ($hasSortOrder) {
            $query->orderBy('sort_order');
        }

        $industries = $query->orderBy('name')->get();

        return response()->json([
            'status' => true,
            'message' => 'Industry tree fetched successfully.',
            'data' => IndustryTreeResource::collection($industries),
        ]);
    }
}
