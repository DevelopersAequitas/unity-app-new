<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdvertiserCampaign;
use App\Services\Advertiser\AdvertiserAnalyticsService;
use App\Services\Advertiser\AdvertiserCampaignService;
use App\Services\Advertiser\AdvertiserPublicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdvertiserCampaignAdminController extends Controller
{
    public function __construct(
        protected AdvertiserCampaignService $campaignService,
        protected AdvertiserPublicationService $publicationService,
        protected AdvertiserAnalyticsService $analyticsService
    ) {}

    public function index(Request $request): View
    {
        $query = AdvertiserCampaign::with(['application', 'subscription', 'user', 'campaignAds.ad'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->query('q'));
            $query->where(function ($b) use ($q) {
                $b->where('name', 'ILIKE', "%{$q}%")
                    ->orWhere('campaign_number', 'ILIKE', "%{$q}%")
                    ->orWhereHas('application', function ($sub) use ($q) {
                        $sub->where('company_name', 'ILIKE', "%{$q}%");
                    });
            });
        }

        $campaigns = $query->paginate(20)->appends($request->query());

        $counts = [
            'total' => AdvertiserCampaign::count(),
            'pending_review' => AdvertiserCampaign::where('status', AdvertiserCampaign::STATUS_PENDING_REVIEW)->count(),
            'active' => AdvertiserCampaign::where('status', AdvertiserCampaign::STATUS_ACTIVE)->count(),
            'paused' => AdvertiserCampaign::where('status', AdvertiserCampaign::STATUS_PAUSED)->count(),
            'changes_requested' => AdvertiserCampaign::where('status', AdvertiserCampaign::STATUS_CHANGES_REQUESTED)->count(),
            'rejected' => AdvertiserCampaign::where('status', AdvertiserCampaign::STATUS_REJECTED)->count(),
            'completed' => AdvertiserCampaign::where('status', AdvertiserCampaign::STATUS_COMPLETED)->count(),
        ];

        return view('admin.advertiser.campaigns.index', compact('campaigns', 'counts'));
    }

    public function show(AdvertiserCampaign $campaign): View
    {
        $campaign->loadMissing([
            'application.assets',
            'subscription.package',
            'user',
            'campaignAds.ad',
            'statusHistory.user',
            'reviewer',
        ]);

        $analytics = $this->analyticsService->getCampaignAnalytics($campaign);

        return view('admin.advertiser.campaigns.show', compact('campaign', 'analytics'));
    }

    public function review(Request $request, AdvertiserCampaign $campaign): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:approved,changes_requested,rejected'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->campaignService->reviewCampaign(
            $campaign,
            $validated['decision'],
            $validated['notes'] ?? null,
            $request->user()
        );

        return redirect()->route('admin.advertiser.campaigns.show', $campaign->id)
            ->with('success', "Campaign review decision '{$validated['decision']}' applied successfully.");
    }

    public function publish(Request $request, AdvertiserCampaign $campaign): RedirectResponse
    {
        $this->publicationService->publishCampaignAd($campaign, $request->user());

        return redirect()->route('admin.advertiser.campaigns.show', $campaign->id)
            ->with('success', 'Campaign advertisement published to feed successfully.');
    }

    public function pause(Request $request, AdvertiserCampaign $campaign): RedirectResponse
    {
        $reason = $request->input('reason', 'Paused by administrator');
        $this->campaignService->pauseCampaign($campaign, $reason, $request->user());

        return redirect()->route('admin.advertiser.campaigns.show', $campaign->id)
            ->with('success', 'Campaign paused successfully.');
    }

    public function resume(Request $request, AdvertiserCampaign $campaign): RedirectResponse
    {
        $this->campaignService->resumeCampaign($campaign, $request->user());

        return redirect()->route('admin.advertiser.campaigns.show', $campaign->id)
            ->with('success', 'Campaign resumed successfully.');
    }
}
