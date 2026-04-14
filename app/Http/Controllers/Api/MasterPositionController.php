<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MasterPositionResource;
use App\Models\MasterPosition;
use Illuminate\Http\JsonResponse;

class MasterPositionController extends Controller
{
    public function index(): JsonResponse
    {
        $items = MasterPosition::query()
            ->select(['id', 'name', 'slug'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => [
                'items' => MasterPositionResource::collection($items),
            ],
            'meta' => null,
        ]);
    }
}
