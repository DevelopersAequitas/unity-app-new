<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Services\Leadership\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminCampaignController extends LeadershipBaseController
{
    public function __construct(
        protected CampaignService $campaignService
    ) {}

    /**
     * D1. List campaigns.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $campaigns = $this->campaignService->listAdminCampaigns($request->all(), $perPage);

        return $this->paginate($campaigns, 'Campaigns fetched successfully.');
    }

    /**
     * D2. Create campaign.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => 'required|uuid',
            'name' => 'required|string|max:200',
            'slug' => 'nullable|string|max:220',
            'description' => 'nullable|string',
            'campaign_year' => 'required|integer',
            'nomination_starts_at' => 'nullable|date',
            'nomination_ends_at' => 'nullable|date|after_or_equal:nomination_starts_at',
            'voting_starts_at' => 'nullable|date',
            'voting_ends_at' => 'nullable|date|after_or_equal:voting_starts_at',
            'jury_starts_at' => 'nullable|date',
            'jury_ends_at' => 'nullable|date|after_or_equal:jury_starts_at',
            'allow_multiple_winners' => 'nullable|boolean',
            'eligibility_rules' => 'nullable|array',
            'settings' => 'nullable|array',
        ]);

        try {
            $campaign = $this->campaignService->createCampaign($validated, Auth::id());

            return $this->success($campaign, 'Campaign created successfully in draft mode.', 201);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * D3. Get campaign details.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $campaign = $this->campaignService->getAdminCampaignDetails($id);

            return $this->success($campaign, 'Campaign details fetched successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * D4. Update campaign.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $campaign = $this->campaignService->updateCampaign($id, $request->all(), Auth::id());

            return $this->success($campaign, 'Campaign updated successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * D5. Publish campaign.
     */
    public function publish(Request $request, string $id): JsonResponse
    {
        $remarks = (string) $request->input('remarks', '');

        try {
            $campaign = $this->campaignService->publishCampaign($id, $remarks, Auth::id());

            return $this->success($campaign, 'Campaign published successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * D6. Pause campaign.
     */
    public function pause(Request $request, string $id): JsonResponse
    {
        $reason = (string) $request->input('reason', 'Administrative pause');

        try {
            $campaign = $this->campaignService->pauseCampaign($id, $reason, Auth::id());

            return $this->success($campaign, 'Campaign paused successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * D7. Resume campaign.
     */
    public function resume(Request $request, string $id): JsonResponse
    {
        $remarks = (string) $request->input('remarks', 'Campaign resumed');

        try {
            $campaign = $this->campaignService->resumeCampaign($id, $remarks, Auth::id());

            return $this->success($campaign, 'Campaign resumed successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * D8. Get dynamic roles.
     */
    public function roles(): JsonResponse
    {
        $roles = $this->campaignService->getDynamicRoles();

        return $this->success($roles, 'Roles fetched successfully.');
    }
}
