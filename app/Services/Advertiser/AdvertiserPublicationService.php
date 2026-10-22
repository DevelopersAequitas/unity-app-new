<?php

declare(strict_types=1);

namespace App\Services\Advertiser;

use App\Jobs\SendBulkPartnerNotificationJob;
use App\Models\AdvertiserApplication;
use App\Models\AdvertiserApplicationAsset;
use App\Models\AdvertiserSubscription;
use App\Models\BrandPartner;
use App\Models\BrandPartnerCategory;
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

        if ($application->status !== AdvertiserApplication::STATUS_APPROVED) {
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

            // Find assets
            $logoAsset = $application->assets->firstWhere('asset_type', AdvertiserApplicationAsset::TYPE_LOGO)
                ?? $application->assets->firstWhere('asset_type', 'image');
            $bannerAsset = $application->assets->firstWhere('asset_type', AdvertiserApplicationAsset::TYPE_BANNER)
                ?? $application->assets->firstWhere('asset_type', AdvertiserApplicationAsset::TYPE_COVER);

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
     * Handle expiration of a subscription.
     */
    public function expireSubscription(AdvertiserSubscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $subscription->status = AdvertiserSubscription::STATUS_EXPIRED;
            $subscription->save();

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
