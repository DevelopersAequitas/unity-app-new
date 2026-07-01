<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AdsBannerResource;
use App\Models\AdsBanner;
use Illuminate\Http\Request;

class AdsController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = AdsBanner::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $now = now();
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) {
                $now = now();
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });

        if ($position = $request->input('position')) {
            $query->where('position', $position);
        }

        $limit = (int) $request->input('limit', 10);
        $limit = max(1, min($limit, 50));

        $banners = $query
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $this->success(AdsBannerResource::collection($banners));
    }
}
