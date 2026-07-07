<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationFeatureController extends BaseApiController
{
    public function dailySummary(Request $request): JsonResponse
    {
        return $this->success(null, 'Daily summary retrieved successfully.');
    }

    public function industryInsight(Request $request): JsonResponse
    {
        return $this->success(null, 'Industry insight retrieved successfully.');
    }

    public function rewardItems(Request $request): JsonResponse
    {
        return $this->success(null, 'Reward items retrieved successfully.');
    }

    public function latestNewsletter(Request $request): JsonResponse
    {
        return $this->success(null, 'Latest newsletter retrieved successfully.');
    }


    public function activeLifeImpactCycle(Request $request): JsonResponse
    {
        return $this->success(null, 'Active life impact cycle retrieved successfully.');
    }
}
