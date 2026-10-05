<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Referrals\ReferralCampaignConfigService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ReferralCampaignConfigController extends Controller
{
    public function __construct(
        private readonly ReferralCampaignConfigService $campaignConfigService
    ) {}

    /**
     * Fetch Referral Campaign Configuration.
     *
     * GET /api/v1/referral/campaign-config
     */
    public function show(): JsonResponse
    {
        try {
            $config = $this->campaignConfigService->getCampaignConfig();

            return response()->json($config, 200);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json($this->campaignConfigService->getDefaultConfig(), 200);
        }
    }
}
