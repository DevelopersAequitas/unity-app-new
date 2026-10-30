<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdvertiserApplication;
use App\Models\AdvertiserCampaign;
use App\Models\AdvertiserPayment;
use App\Models\AdvertiserSubscription;
use App\Services\Advertiser\AdvertiserAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdvertiserAnalyticsAdminController extends Controller
{
    public function __construct(
        protected AdvertiserAnalyticsService $analyticsService
    ) {}

    public function index(Request $request): View
    {
        $from = $request->filled('from') ? Carbon::parse($request->query('from'))->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $request->filled('to') ? Carbon::parse($request->query('to'))->endOfDay() : now()->endOfDay();

        $metrics = $this->analyticsService->getAdminAdvertiserSummary($from, $to);

        // Recent Campaigns
        $recentCampaigns = AdvertiserCampaign::with(['application', 'subscription', 'campaignAds.ad'])
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        // Recent Payments
        $recentPayments = AdvertiserPayment::with('subscription.application')
            ->where('status', AdvertiserPayment::STATUS_CAPTURED)
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        return view('admin.advertiser.analytics.index', compact('metrics', 'from', 'to', 'recentCampaigns', 'recentPayments'));
    }

    public function export(Request $request): StreamedResponse
    {
        $from = $request->filled('from') ? Carbon::parse($request->query('from'))->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $request->filled('to') ? Carbon::parse($request->query('to'))->endOfDay() : now()->endOfDay();

        $filename = 'advertiser_analytics_' . now()->format('Y_m_d_His') . '.csv';

        $campaigns = AdvertiserCampaign::with(['application', 'subscription', 'campaignAds.ad'])
            ->whereBetween('created_at', [$from, $to])
            ->get();

        return response()->streamDownload(function () use ($campaigns, $from, $to) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Campaign Number',
                'Campaign Name',
                'Company Name',
                'Status',
                'Views',
                'Clicks',
                'CTR (%)',
                'Subscription Status',
                'Created At',
            ]);

            foreach ($campaigns as $camp) {
                $stats = $this->analyticsService->getCampaignAnalytics($camp, $from, $to);
                fputcsv($handle, [
                    $camp->campaign_number,
                    $camp->name,
                    $camp->application?->company_name ?? 'N/A',
                    $camp->status,
                    $stats['views'],
                    $stats['clicks'],
                    $stats['ctr_percent'] . '%',
                    $camp->subscription?->status ?? 'N/A',
                    $camp->created_at?->format('Y-m-d H:i:s') ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
