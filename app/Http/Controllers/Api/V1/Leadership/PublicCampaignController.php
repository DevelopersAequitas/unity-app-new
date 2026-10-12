<?php

declare(strict_types=1);

namespace App\Services\Leadership;

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Services\Leadership\CampaignService;
use App\Services\Leadership\ScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCampaignController extends LeadershipBaseController
{
    public function __construct(
        protected CampaignService $campaignService,
        protected ScopeService $scopeService
    ) {}

    /**
     * A1. Get active leadership campaigns.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $campaigns = $this->campaignService->getActivePublicCampaigns($request->all(), $perPage);

        return $this->paginate($campaigns, 'Campaigns fetched successfully.');
    }

    /**
     * A2. Get campaign details.
     */
    public function show(string $campaignId): JsonResponse
    {
        try {
            $campaign = $this->campaignService->getPublicCampaignDetails($campaignId);

            return $this->success($campaign, 'Campaign details fetched successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * A3. Get campaign scopes.
     */
    public function scopes(string $campaignId): JsonResponse
    {
        $scopes = $this->scopeService->getPublicScopes($campaignId);

        return $this->success($scopes, 'Scopes fetched successfully.');
    }

    /**
     * A4. Get approved public candidates.
     */
    public function candidates(Request $request, string $campaignId): JsonResponse
    {
        $scopeId = $request->query('scope_id');
        $perPage = (int) $request->query('per_page', 20);

        $candidates = $this->campaignService->getApprovedPublicCandidates($campaignId, $scopeId ? (string) $scopeId : null, $perPage);

        return $this->paginate($candidates, 'Candidates fetched successfully.');
    }

    /**
     * A5. Get published winners.
     */
    public function winners(string $campaignId): JsonResponse
    {
        $winners = $this->campaignService->getPublishedWinners($campaignId);

        return $this->success($winners, 'Published winners fetched successfully.');
    }
}
