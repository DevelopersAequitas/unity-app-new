<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Models\City;
use App\Models\User;
use App\Services\Leadership\CampaignService;
use App\Services\Leadership\ScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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

    /**
     * Public active cities for jurisdiction scope fallback.
     * Orders Indian cities first in alphabetical order, followed by international cities.
     */
    public function cities(): JsonResponse
    {
        $indianStates = [
            'andhra pradesh', 'arunachal pradesh', 'assam', 'bihar', 'chhattisgarh', 'goa', 'gujarat',
            'haryana', 'himachal pradesh', 'jharkhand', 'karnataka', 'kerala', 'madhya pradesh', 'maharashtra',
            'manipur', 'meghalaya', 'mizoram', 'nagaland', 'odisha', 'orissa', 'punjab', 'rajasthan', 'sikkim',
            'tamil nadu', 'telangana', 'tripura', 'uttarakhand', 'uttaranchal', 'uttar pradesh', 'west bengal',
            'delhi', 'new delhi', 'chandigarh', 'jammu and kashmir', 'ladakh', 'puducherry', 'pondicherry',
            'andaman and nicobar islands', 'dadra and nagar haveli and daman and diu', 'lakshadweep',
        ];

        $indianStatesList = "'".implode("','", $indianStates)."'";

        $hasCountryCode = Schema::hasColumn('cities', 'country_code');
        $hasStateCode = Schema::hasColumn('cities', 'state_code');

        $selectColumns = ['id', 'name', 'state', 'district', 'country'];
        if ($hasCountryCode) {
            $selectColumns[] = 'country_code';
        }
        if ($hasStateCode) {
            $selectColumns[] = 'state_code';
        }

        $countryCheckSql = $hasCountryCode
            ? "UPPER(COALESCE(country_code, '')) IN ('IN', 'IND') OR LOWER(COALESCE(country, '')) LIKE '%india%'"
            : "LOWER(COALESCE(country, '')) LIKE '%india%'";

        $cities = City::query()
            ->select($selectColumns)
            ->orderByRaw("CASE 
                WHEN {$countryCheckSql} 
                     OR LOWER(COALESCE(state, '')) IN ({$indianStatesList})
                THEN 0 
                ELSE 1 
            END ASC")
            ->orderBy('name', 'asc')
            ->get();

        return $this->success($cities, 'Active cities fetched successfully.');
    }

    /**
     * Check if a member exists by email or phone and return their profile details for form pre-fill.
     */
    public function checkMember(Request $request): JsonResponse
    {
        $email = strtolower(trim((string) ($request->input('email') ?: $request->query('email', ''))));
        $phone = trim((string) ($request->input('phone') ?: $request->input('mobile') ?: $request->query('phone', $request->query('mobile', ''))));

        if (empty($email) && empty($phone)) {
            return response()->json([
                'success' => false,
                'exists' => false,
                'message' => 'Email or phone number is required to look up a member.',
                'profile' => null,
            ], 422);
        }

        $user = User::findByEmailOrPhone($email, $phone);

        if (! $user) {
            return response()->json([
                'success' => true,
                'exists' => false,
                'message' => 'Member not found. Please continue with manual entry.',
                'profile' => null,
            ], 200);
        }

        $profile = User::buildNominationProfile($user, $email);

        return response()->json([
            'success' => true,
            'exists' => true,
            'message' => 'Member profile verified successfully.',
            'profile' => $profile,
            'data' => $profile,
        ], 200);
    }
}
