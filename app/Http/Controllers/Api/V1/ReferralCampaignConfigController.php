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
     * GET /api/referral/campaign-config
     */
    public function show(): JsonResponse
    {
        try {
            $config = $this->campaignConfigService->getCampaignConfig();

            $payload = array_merge($config, [
                'success' => true,
                'data' => $config,
            ]);

            return response()->json($payload, 200);
        } catch (Throwable $exception) {
            report($exception);

            $default = $this->campaignConfigService->getDefaultConfig();
            $payload = array_merge($default, [
                'success' => true,
                'data' => $default,
            ]);

            return response()->json($payload, 200);
        }
    }
}
