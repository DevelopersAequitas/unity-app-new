<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Models\City;
use App\Models\User;
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

    /**
     * Public active cities for jurisdiction scope fallback.
     */
    public function cities(): JsonResponse
    {
        $cities = City::query()
            ->select(['id', 'name', 'state', 'district', 'country'])
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

        $cleanPhone = preg_replace('/\D+/', '', $phone);
        $last10 = strlen($cleanPhone) >= 10 ? substr($cleanPhone, -10) : $cleanPhone;

        $user = User::query()
            ->with('city')
            ->where(function ($q) use ($email, $phone, $last10): void {
                $hasCondition = false;

                if (! empty($email)) {
                    $q->whereRaw('LOWER(email) = ?', [$email]);
                    $hasCondition = true;
                }

                if (! empty($phone)) {
                    $phoneClosure = function ($sub) use ($phone, $last10): void {
                        $sub->where('phone', $phone);
                        if (! empty($last10)) {
                            $sub->orWhere('phone', 'like', '%'.$last10);
                        }
                    };

                    if ($hasCondition) {
                        $q->orWhere($phoneClosure);
                    } else {
                        $phoneClosure($q);
                    }
                }
            })
            ->first();

        if (! $user) {
            return response()->json([
                'success' => true,
                'exists' => false,
                'message' => 'Member not found. Please continue with manual entry.',
                'profile' => null,
            ], 200);
        }

        $fullName = $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->name ?? $user->email;
        $company = $user->company_name ?: ($user->business_name ?? ($user->company ?? ''));
        $designation = $user->designation ?: ($user->job_title ?? '');
        $cityName = $user->cityRelation?->name ?: (is_string($user->city) ? $user->city : '');
        $photoUrl = null;
        try {
            $photoUrl = $user->profile_photo_url;
        } catch (\Throwable) {
            $photoUrl = null;
        }

        $profile = [
            'user_id' => (string) $user->id,
            'full_name' => $fullName,
            'company' => $company,
            'designation' => $designation,
            'email' => $user->email,
            'mobile' => $user->phone ?: '',
            'phone' => $user->phone ?: '',
            'city' => $cityName,
            'photo_url' => $photoUrl,
        ];

        return response()->json([
            'success' => true,
            'exists' => true,
            'message' => 'Member profile verified successfully.',
            'profile' => $profile,
            'data' => $profile,
        ], 200);
    }
}
