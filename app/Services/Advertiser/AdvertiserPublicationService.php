<?php

declare(strict_types=1);

namespace App\Services\Advertiser;

use App\Jobs\SendBulkPartnerNotificationJob;
use App\Models\Ad;
use App\Models\AdvertiserApplication;
use App\Models\AdvertiserApplicationAsset;
use App\Models\AdvertiserApplicationStatusHistory;
use App\Models\AdvertiserCampaign;
use App\Models\AdvertiserCampaignAd;
use App\Models\AdvertiserPackage;
use App\Models\AdvertiserSubscription;
use App\Models\BrandPartner;
use App\Models\BrandPartnerCategory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdvertiserPublicationService
{
    public function __construct(
        protected AdvertiserNotificationService $notificationService
    ) {}

    /**
     * Activate subscription and publish or update linked BrandPartner record.
     */
    public function activateAndPublish(AdvertiserSubscription $subscription): BrandPartner
    {
        $subscription->loadMissing(['application.assets', 'user', 'package']);
        $application = $subscription->application;

        if (!$application) {
            throw ValidationException::withMessages([
                'subscription' => ['Linked application was not found.'],
            ]);
        }

        if ($application->status !== AdvertiserApplication::STATUS_APPROVED && $application->status !== AdvertiserApplication::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'publication' => ['Campaign cannot be published because the application has not been approved.'],
            ]);
        }

        return DB::transaction(function () use ($subscription, $application): BrandPartner {
            // 1. Resolve or create BrandPartner
            $partner = null;
            if (!empty($application->brand_partner_id)) {
                $partner = BrandPartner::find($application->brand_partner_id);
            }

            // Find assets with robust fallback across asset types
            $imageAssets = $application->assets->filter(function ($a) {
                return in_array($a->asset_type, [
                    AdvertiserApplicationAsset::TYPE_LOGO,
                    AdvertiserApplicationAsset::TYPE_BANNER,
                    AdvertiserApplicationAsset::TYPE_COVER,
                    AdvertiserApplicationAsset::TYPE_CREATIVE,
                    'image',
                    'other'
                ], true) || Str::startsWith((string)$a->mime_type, 'image/');
            })->values();

            $logoAsset = $application->assets->firstWhere('asset_type', AdvertiserApplicationAsset::TYPE_LOGO)
                ?? $application->assets->firstWhere('asset_type', 'image')
                ?? $imageAssets->first();

            $bannerAsset = $application->assets->firstWhere('asset_type', AdvertiserApplicationAsset::TYPE_BANNER)
                ?? $application->assets->firstWhere('asset_type', AdvertiserApplicationAsset::TYPE_COVER)
                ?? $application->assets->firstWhere('asset_type', AdvertiserApplicationAsset::TYPE_CREATIVE)
                ?? ($imageAssets->count() > 1 ? $imageAssets->get(1) : $imageAssets->first());

            // Resolve Category
            $categoryId = null;
            if (Str::isUuid($application->industry ?? '')) {
                $categoryId = $application->industry;
            } else {
                $cat = BrandPartnerCategory::where('name', 'ILIKE', '%' . ($application->industry ?? 'General') . '%')->first();
                $categoryId = $cat?->id;
            }

            $durationMonths = (int) ($subscription->duration_months ?: 1);
            $startDate = now();
            $endDate = now()->addMonths($durationMonths);

            // created_by and updated_by must reference admin_users(id) due to fk_brand_partners_created_by constraint
            $adminUserId = null;
            if (!empty($subscription->user_id) && DB::table('admin_users')->where('id', $subscription->user_id)->exists()) {
                $adminUserId = $subscription->user_id;
            } else {
                $adminUserId = DB::table('admin_users')->value('id');
            }

            $partnerData = [
                'name' => $application->company_name,
                'short_description' => Str::limit($application->brand_description, 250),
                'description' => $application->brand_description,
                'category_id' => $categoryId,
                'website' => $application->company_website,
                'contact_email' => $application->company_email ?: $application->applicant_email,
                'contact_number' => $application->company_phone ?: $application->applicant_phone,
                'whatsapp' => $application->whatsapp_number,
                'address' => $application->address_line,
                'is_active' => true,
                'is_verified' => true,
                'is_sponsored' => true,
                'valid_from' => $startDate,
                'valid_to' => $endDate,
                'created_by' => $adminUserId,
                'updated_by' => $adminUserId,
                'metadata' => [
                    'source' => 'advertiser_onboarding',
                    'subscription_id' => $subscription->id,
                    'application_id' => $application->id,
                    'applicant_user_id' => $subscription->user_id,
                ],
            ];

            if ($logoAsset && !empty($logoAsset->storage_key)) {
                $partnerData['logo'] = $logoAsset->storage_key;
            }
            if ($bannerAsset && !empty($bannerAsset->storage_key)) {
                $partnerData['cover_image'] = $bannerAsset->storage_key;
            }

            if ($partner) {
                $partner->update($partnerData);
            } else {
                // Generate unique slug
                $baseSlug = Str::slug($application->company_name);
                $slug = $baseSlug;
                $counter = 1;
                while (BrandPartner::where('slug', $slug)->exists()) {
                    $slug = "{$baseSlug}-" . Str::lower(Str::random(4));
                    $counter++;
                }

                $partnerData['slug'] = $slug;
                $partnerData['uuid'] = (string) Str::uuid();
                $partner = BrandPartner::create($partnerData);

                $application->brand_partner_id = $partner->id;
                $application->save();
            }

            // 2. Activate Subscription
            $subscription->brand_partner_id = $partner->id;
            $subscription->status = AdvertiserSubscription::STATUS_ACTIVE;
            $subscription->starts_at = $startDate;
            $subscription->expires_at = $endDate;
            $subscription->activated_at = now();
            $subscription->save();

            return $partner;
        });

        // 3. Dispatch Notifications outside transaction so it never blocks or aborts publication
        try {
            $this->notificationService->sendCampaignActivated($subscription);
        } catch (\Throwable $e) {
            Log::warning('[AdvertiserPublication] Notification dispatch skipped: ' . $e->getMessage());
        }

        // Avoid blocking local testing with synchronous bulk notification loop over all users
        if (!app()->environment('local', 'testing')) {
            try {
                SendBulkPartnerNotificationJob::dispatch($partner, 'joined');
            } catch (\Throwable $e) {
                Log::warning('[AdvertiserPublication] Bulk partner notification skipped: ' . $e->getMessage());
            }
        }

        return $partner;
    }

    /**
     * Extend or renew an existing brand partner subscription.
     */
    public function extendSubscription(
        AdvertiserSubscription $subscription,
        int $durationMonths = 1,
        ?AdvertiserPackage $package = null
    ): BrandPartner {
        $subscription->loadMissing(['application', 'user', 'brandPartner']);

        return DB::transaction(function () use ($subscription, $durationMonths, $package): BrandPartner {
            $baseDate = ($subscription->status === AdvertiserSubscription::STATUS_ACTIVE && $subscription->expires_at && $subscription->expires_at->isFuture())
                ? $subscription->expires_at
                : now();

            $newExpiresAt = $baseDate->copy()->addMonths($durationMonths);

            // If subscription was expired or had no start date, start from now
            if (!$subscription->starts_at || ($subscription->expires_at && $subscription->expires_at->isPast())) {
                $subscription->starts_at = now();
            }

            $subscription->expires_at = $newExpiresAt;
            $subscription->status = AdvertiserSubscription::STATUS_ACTIVE;
            if ($package && $package->duration_months > 0) {
                $subscription->duration_months = (int) $package->duration_months;
                $subscription->package_id = $package->id;
                $subscription->package_name_snapshot = $package->name;
            }
            $subscription->save();

            // Update Brand Partner
            $partner = $subscription->brandPartner;
            if (!$partner && !empty($subscription->brand_partner_id)) {
                $partner = BrandPartner::find($subscription->brand_partner_id);
            }

            if ($partner) {
                $partnerUpdate = [
                    'is_active' => true,
                    'is_sponsored' => true,
                    'valid_to' => $newExpiresAt,
                ];
                if (!$partner->valid_from || Carbon::parse($partner->valid_to)->isPast()) {
                    $partnerUpdate['valid_from'] = now();
                }
                $partner->update($partnerUpdate);
            }

            return $partner ?? new BrandPartner();
        });
    }

    /**
     * Publish an approved campaign to the existing Ads engine.
     */
    public function publishCampaignAd(AdvertiserCampaign $campaign, ?User $actor = null): Ad
    {
        $campaign->loadMissing(['application.assets', 'subscription', 'campaignAds.ad']);

        return DB::transaction(function () use ($campaign, $actor) {
            $application = $campaign->application;
            $subscription = $campaign->subscription;

            // Resolve creative image path
            $imagePath = null;
            if ($application && $application->assets->isNotEmpty()) {
                $bannerAsset = $application->assets->firstWhere('asset_type', AdvertiserApplicationAsset::TYPE_BANNER)
                    ?? $application->assets->firstWhere('asset_type', AdvertiserApplicationAsset::TYPE_CREATIVE)
                    ?? $application->assets->firstWhere('asset_type', AdvertiserApplicationAsset::TYPE_LOGO)
                    ?? $application->assets->first();

                $imagePath = $bannerAsset?->storage_key;
            }

            // Check if an ad is already linked to avoid duplicates
            $existingLink = $campaign->campaignAds()->where('is_primary', true)->first()
                ?? $campaign->campaignAds()->first();

            $ad = null;
            if ($existingLink && $existingLink->ad_id) {
                $ad = Ad::find($existingLink->ad_id);
            }

            $adData = [
                'title' => $campaign->name,
                'subtitle' => Str::limit((string) ($campaign->objective ?: ($application?->company_name ?? 'Sponsored')), 250),
                'description' => $campaign->description ?: ($application?->brand_description ?? ''),
                'redirect_url' => $campaign->destination_url ?: ($application?->company_website ?? ''),
                'button_text' => $campaign->cta_text ?: 'Learn More',
                'placement' => is_array($campaign->requested_placements) && !empty($campaign->requested_placements) ? $campaign->requested_placements[0] : 'feed',
                'page_name' => 'feed',
                'is_active' => true,
                'starts_at' => $campaign->requested_starts_at ?: now(),
                'ends_at' => $campaign->requested_ends_at ?: $subscription?->expires_at,
                'created_by' => $campaign->user_id,
            ];

            if ($imagePath) {
                $adData['image_path'] = $imagePath;
            }

            if ($ad) {
                $ad->update($adData);
            } else {
                $ad = Ad::create($adData);
            }

            // Link campaign to ad
            AdvertiserCampaignAd::updateOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'ad_id' => $ad->id,
                ],
                [
                    'creative_role' => AdvertiserCampaignAd::ROLE_PRIMARY,
                    'status' => AdvertiserCampaignAd::STATUS_PUBLISHED,
                    'is_primary' => true,
                    'approved_by' => $actor?->id,
                    'approved_at' => now(),
                    'published_at' => now(),
                ]
            );

            // Update campaign status to active/published
            $campaign->status = AdvertiserCampaign::STATUS_ACTIVE;
            $campaign->published_at = now();
            $campaign->save();

            return $ad;
        });
    }

    /**
     * Pause ads belonging to a campaign without deleting records.
     */
    public function pauseCampaignAds(AdvertiserCampaign $campaign): void
    {
        $campaignAds = AdvertiserCampaignAd::where('campaign_id', $campaign->id)->get();
        foreach ($campaignAds as $link) {
            $link->status = AdvertiserCampaignAd::STATUS_PAUSED;
            $link->save();

            Ad::where('id', $link->ad_id)->update(['is_active' => false]);
        }
    }

    /**
     * Resume ads belonging to a campaign.
     */
    public function resumeCampaignAds(AdvertiserCampaign $campaign): void
    {
        $campaignAds = AdvertiserCampaignAd::where('campaign_id', $campaign->id)->get();
        foreach ($campaignAds as $link) {
            $link->status = AdvertiserCampaignAd::STATUS_PUBLISHED;
            $link->save();

            Ad::where('id', $link->ad_id)->update(['is_active' => true]);
        }
    }

    /**
     * Handle expiration of a subscription.
     */
    public function expireSubscription(AdvertiserSubscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $subscription->status = AdvertiserSubscription::STATUS_EXPIRED;
            $subscription->save();

            // Deactivate linked campaigns and their ads
            $campaigns = AdvertiserCampaign::where('subscription_id', $subscription->id)->get();
            foreach ($campaigns as $campaign) {
                if ($campaign->status === AdvertiserCampaign::STATUS_ACTIVE) {
                    $campaign->status = AdvertiserCampaign::STATUS_EXPIRED;
                    $campaign->completed_at = now();
                    $campaign->save();

                    $this->pauseCampaignAds($campaign);
                }
            }

            if ($subscription->brand_partner_id) {
                $partner = BrandPartner::find($subscription->brand_partner_id);
                if ($partner) {
                    // Check if partner has another active subscription
                    $hasOtherActive = AdvertiserSubscription::where('brand_partner_id', $partner->id)
                        ->where('id', '<>', $subscription->id)
                        ->where('status', AdvertiserSubscription::STATUS_ACTIVE)
                        ->where('expires_at', '>', now())
                        ->exists();

                    if (!$hasOtherActive) {
                        // Deactivate sponsored/featured placement without deleting data
                        $partner->update([
                            'is_sponsored' => false,
                            'is_active' => false,
                        ]);
                    }
                }
            }
        });
    }
}

