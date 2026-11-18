<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BrandPartner;
use App\Models\BrandPartnerClick;
use App\Models\BrandPartnerInquiry;
use App\Models\BrandPartnerView;
use App\Services\Advertiser\AdvertiserNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class SendWeeklyBrandPartnerAnalyticsReportCommand extends Command
{
    protected $signature = 'PGU:brand-partner-weekly-report';

    protected $description = 'Send weekly performance and callback request summary emails to all active published Brand Partners.';

    public function __construct(
        protected AdvertiserNotificationService $notificationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! Schema::hasTable('brand_partners')) {
            return self::SUCCESS;
        }

        BrandPartnerInquiry::ensureTableExists();

        $now = Carbon::now();
        $weekStart = $now->copy()->subDays(7)->startOfDay();

        $activePartners = BrandPartner::query()
            ->where('is_active', true)
            ->where(function ($q) use ($now): void {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function ($q) use ($now): void {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $now);
            })
            ->get();

        $sentCount = 0;

        foreach ($activePartners as $partner) {
            $resolved = $this->notificationService->resolveBrandPartnerRecipient($partner);
            $recipientEmail = $resolved['email'];
            $recipientName = $resolved['name'];

            if ($recipientEmail === '' || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            // Weekly (last 7 days) metrics
            $weeklyViews = Schema::hasTable('brand_partner_views')
                ? BrandPartnerView::where('brand_partner_id', $partner->id)
                    ->where('viewed_at', '>=', $weekStart)
                    ->count()
                : 0;

            $weeklyUniqueViews = 0;
            if (Schema::hasTable('brand_partner_views')) {
                $uvRow = BrandPartnerView::where('brand_partner_id', $partner->id)
                    ->where('viewed_at', '>=', $weekStart)
                    ->selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')
                    ->first();
                $weeklyUniqueViews = (int) ($uvRow->count ?? 0);
            }

            $weeklyClicks = Schema::hasTable('brand_partner_clicks')
                ? BrandPartnerClick::where('brand_partner_id', $partner->id)
                    ->where('created_at', '>=', $weekStart)
                    ->count()
                : 0;

            $weeklyUniqueClicks = 0;
            if (Schema::hasTable('brand_partner_clicks')) {
                $ucRow = BrandPartnerClick::where('brand_partner_id', $partner->id)
                    ->where('created_at', '>=', $weekStart)
                    ->selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')
                    ->first();
                $weeklyUniqueClicks = (int) ($ucRow->count ?? 0);
            }
            if ($weeklyUniqueClicks > $weeklyUniqueViews) {
                $weeklyUniqueClicks = $weeklyUniqueViews;
            }

            $weeklyCtr = $weeklyUniqueViews > 0
                ? min(100.0, round(($weeklyUniqueClicks / $weeklyUniqueViews) * 100, 2))
                : 0.0;

            $weeklyCallbacks = Schema::hasTable('brand_partner_inquiries')
                ? BrandPartnerInquiry::where('brand_partner_id', $partner->id)
                    ->where('created_at', '>=', $weekStart)
                    ->count()
                : 0;

            // Lifetime metrics
            $lifetimeViews = Schema::hasTable('brand_partner_views')
                ? BrandPartnerView::where('brand_partner_id', $partner->id)->count()
                : 0;
            $lifetimeClicks = Schema::hasTable('brand_partner_clicks')
                ? BrandPartnerClick::where('brand_partner_id', $partner->id)->count()
                : 0;
            $lifetimeCallbacks = Schema::hasTable('brand_partner_inquiries')
                ? BrandPartnerInquiry::where('brand_partner_id', $partner->id)->count()
                : 0;

            $weeklyStats = [
                'from' => $weekStart->toFormattedDateString(),
                'to' => $now->toFormattedDateString(),
                'total_views' => $weeklyViews,
                'unique_reach' => $weeklyUniqueViews,
                'total_clicks' => $weeklyClicks,
                'ctr_percent' => $weeklyCtr,
                'callback_requests' => $weeklyCallbacks,
            ];

            $lifetimeStats = [
                'total_views' => $lifetimeViews,
                'total_clicks' => $lifetimeClicks,
                'callback_requests' => $lifetimeCallbacks,
            ];

            if ($this->notificationService->sendWeeklyBrandPartnerReport($partner, $weeklyStats, $lifetimeStats, $recipientEmail, $recipientName)) {
                $sentCount++;
            }
        }

        $this->info("Sent weekly Brand Partner performance report to {$sentCount} active partner(s).");

        return self::SUCCESS;
    }
}
