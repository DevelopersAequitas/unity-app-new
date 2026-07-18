<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CollaborationTypeResource;
use App\Models\CollaborationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class CollaborationTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $query = CollaborationType::query()->active();

        if (Schema::hasColumn('collaboration_types', 'sort_order')) {
            $query->orderBy('sort_order');
        }

        $types = $query->orderBy('name')->get();

        return response()->json([
            'status' => true,
            'message' => 'Collaboration types fetched successfully.',
            'data' => CollaborationTypeResource::collection($types),
        ]);
    }
}
