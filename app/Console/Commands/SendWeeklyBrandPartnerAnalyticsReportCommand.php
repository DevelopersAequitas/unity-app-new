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
        $prevWeekStart = $weekStart->copy()->subDays(7)->startOfDay();

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

            // Current week (last 7 days) vs Previous week (7-14 days ago) metrics
            $weeklyViews = Schema::hasTable('brand_partner_views')
                ? BrandPartnerView::where('brand_partner_id', $partner->id)
                    ->where('viewed_at', '>=', $weekStart)
                    ->count()
                : 0;
            $prevWeeklyViews = Schema::hasTable('brand_partner_views')
                ? BrandPartnerView::where('brand_partner_id', $partner->id)
                    ->where('viewed_at', '>=', $prevWeekStart)
                    ->where('viewed_at', '<', $weekStart)
                    ->count()
                : 0;

            $weeklyUniqueViews = 0;
            $prevWeeklyUniqueViews = 0;
            if (Schema::hasTable('brand_partner_views')) {
                $uvRow = BrandPartnerView::where('brand_partner_id', $partner->id)
                    ->where('viewed_at', '>=', $weekStart)
                    ->selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')
                    ->first();
                $weeklyUniqueViews = (int) ($uvRow->count ?? 0);

                $prevUvRow = BrandPartnerView::where('brand_partner_id', $partner->id)
                    ->where('viewed_at', '>=', $prevWeekStart)
                    ->where('viewed_at', '<', $weekStart)
                    ->selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')
                    ->first();
                $prevWeeklyUniqueViews = (int) ($prevUvRow->count ?? 0);
            }

            $weeklyClicks = Schema::hasTable('brand_partner_clicks')
                ? BrandPartnerClick::where('brand_partner_id', $partner->id)
                    ->where('created_at', '>=', $weekStart)
                    ->count()
                : 0;
            $prevWeeklyClicks = Schema::hasTable('brand_partner_clicks')
                ? BrandPartnerClick::where('brand_partner_id', $partner->id)
                    ->where('created_at', '>=', $prevWeekStart)
                    ->where('created_at', '<', $weekStart)
                    ->count()
                : 0;

            $weeklyCallbacks = Schema::hasTable('brand_partner_inquiries')
                ? BrandPartnerInquiry::where('brand_partner_id', $partner->id)
                    ->where('created_at', '>=', $weekStart)
                    ->count()
                : 0;
            $prevWeeklyCallbacks = Schema::hasTable('brand_partner_inquiries')
                ? BrandPartnerInquiry::where('brand_partner_id', $partner->id)
                    ->where('created_at', '>=', $prevWeekStart)
                    ->where('created_at', '<', $weekStart)
                    ->count()
                : 0;

            // CTR = Clicks ÷ Views; Callback Rate = Callback Requests ÷ Clicks
            $weeklyCtr = $weeklyViews > 0
                ? min(100.0, round(($weeklyClicks / $weeklyViews) * 100, 2))
                : 0.0;
            $weeklyCallbackRate = $weeklyClicks > 0
                ? min(100.0, round(($weeklyCallbacks / $weeklyClicks) * 100, 2))
                : 0.0;

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

            $packageStartDate = $partner->valid_from
                ? $partner->valid_from->copy()->setTimezone('Asia/Kolkata')->format('d M Y')
                : ($partner->created_at
                    ? $partner->created_at->copy()->setTimezone('Asia/Kolkata')->format('d M Y')
                    : $weekStart->copy()->setTimezone('Asia/Kolkata')->format('d M Y'));

            $expiryDate = $partner->valid_to
                ? $partner->valid_to->copy()->setTimezone('Asia/Kolkata')->format('d M Y')
                : 'Active';

            $daysLeft = $partner->valid_to
                ? max(0, (int) $now->copy()->setTimezone('Asia/Kolkata')->startOfDay()->diffInDays($partner->valid_to->copy()->setTimezone('Asia/Kolkata')->startOfDay(), false))
                : 30;

            $weeklyStats = [
                'from' => $weekStart->copy()->setTimezone('Asia/Kolkata')->format('d M Y'),
                'to' => $now->copy()->setTimezone('Asia/Kolkata')->format('d M Y'),
                'total_views' => $weeklyViews,
                'views_change' => $this->formatPercentChange($weeklyViews, $prevWeeklyViews),
                'unique_reach' => $weeklyUniqueViews,
                'unique_change' => $this->formatPercentChange($weeklyUniqueViews, $prevWeeklyUniqueViews).' vs last week',
                'total_clicks' => $weeklyClicks,
                'clicks_change' => $this->formatPercentChange($weeklyClicks, $prevWeeklyClicks).' vs last week',
                'ctr_percent' => $weeklyCtr,
                'callback_requests' => $weeklyCallbacks,
                'callbacks_change' => $this->formatCountOrPercentChange($weeklyCallbacks, $prevWeeklyCallbacks).' vs last week',
                'callback_rate_percent' => $weeklyCallbackRate,
                'package_start_date' => $packageStartDate,
                'expiry_date' => $expiryDate,
                'days_left' => $daysLeft,
                'dashboard_link' => rtrim((string) config('app.public_url', config('app.url', 'https://peersunity.com')), '/').'/advertiser/dashboard',
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

    protected function formatPercentChange(int $current, int $previous): string
    {
        if ($previous <= 0) {
            return $current > 0 ? '+100%' : '0%';
        }

        $diffPercent = (int) round((($current - $previous) / $previous) * 100);

        return ($diffPercent >= 0 ? '+' : '').$diffPercent.'%';
    }

    protected function formatCountOrPercentChange(int $current, int $previous): string
    {
        $diff = $current - $previous;

        return ($diff >= 0 ? '+' : '').$diff;
    }
}
