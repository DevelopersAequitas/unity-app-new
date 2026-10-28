<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ToggleReferralCampaignConfigRequest;
use App\Http\Requests\Admin\UpdateReferralCampaignConfigRequest;
use App\Services\Referrals\ReferralCampaignConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ReferralCampaignConfigAdminController extends Controller
{
    public function __construct(
        private readonly ReferralCampaignConfigService $campaignConfigService
    ) {}

    public function toggle(ToggleReferralCampaignConfigRequest $request): JsonResponse|RedirectResponse
    {
        $this->ensureAuthorized();

        $validated = $request->validated();
        $isActive = array_key_exists('is_active', $validated)
            ? (bool) $validated['is_active']
            : null;

        $config = $this->campaignConfigService->toggleActive($isActive);

        $message = $config->is_active
            ? 'Referral campaign popup is now enabled and visible on mobile.'
            : 'Referral campaign popup is now disabled and hidden from mobile.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'id' => $config->id,
                    'is_active' => (bool) $config->is_active,
                    'show_floating_badge' => (bool) $config->show_floating_badge,
                    'auto_open_badge_screen' => (bool) $config->auto_open_badge_screen,
                    'reward_title' => $config->reward_title,
                    'reward_subtitle' => $config->reward_subtitle,
                    'reward_image_url' => $config->reward_image_url,
                ],
            ]);
        }

        return back()->with('success', $message);
    }

    public function update(UpdateReferralCampaignConfigRequest $request): JsonResponse|RedirectResponse
    {
        $this->ensureAuthorized();

        $validated = $request->validated();
        $payload = [
            'is_active' => $request->boolean('is_active'),
            'show_floating_badge' => $request->boolean('show_floating_badge'),
            'auto_open_badge_screen' => $request->boolean('auto_open_badge_screen'),
            'show_send_invite_button' => $request->boolean('show_send_invite_button'),
            'floating_badge_delay_seconds' => (int) ($validated['floating_badge_delay_seconds'] ?? 10),
            'auto_open_delay_seconds' => (int) ($validated['auto_open_delay_seconds'] ?? 30),
            'reward_title' => $validated['reward_title'] ?? null,
            'reward_subtitle' => $validated['reward_subtitle'] ?? null,
            'reward_image_url' => $validated['reward_image_url'] ?? null,
        ];

        $config = $this->campaignConfigService->updateConfig($payload);

        $message = 'Referral campaign configuration updated successfully.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $config,
            ]);
        }

        return back()->with('success', $message);
    }

    private function ensureAuthorized(): void
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            abort(403);
        }
    }
}

