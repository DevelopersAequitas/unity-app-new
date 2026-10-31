<?php

declare(strict_types=1);

namespace App\Services\Advertiser;

use App\Models\AdvertiserApplication;
use App\Models\AdvertiserCampaign;
use App\Models\AdvertiserCampaignStatusHistory;
use App\Models\AdvertiserSubscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdvertiserCampaignService
{
    public function __construct(
        protected AdvertiserPublicationService $publicationService,
        protected AdvertiserNotificationService $notificationService
    ) {}

    /**
     * Create a new campaign draft for an authenticated advertiser user.
     */
    public function createDraft(User $user, array $data): AdvertiserCampaign
    {
        return DB::transaction(function () use ($user, $data) {
            // 1. Resolve Subscription
            $subscription = null;
            if (!empty($data['subscription_id'])) {
                $subscription = AdvertiserSubscription::where('id', $data['subscription_id'])
                    ->where('user_id', $user->id)
                    ->first();
            }

            if (!$subscription) {
                // Find latest active or approved subscription
                $subscription = AdvertiserSubscription::where('user_id', $user->id)
                    ->whereIn('status', [AdvertiserSubscription::STATUS_ACTIVE, AdvertiserSubscription::STATUS_PENDING_PAYMENT])
                    ->latest('created_at')
                    ->first();
            }

            if (!$subscription) {
                throw ValidationException::withMessages([
                    'subscription_id' => ['No eligible advertising subscription found for your account.'],
                ]);
            }

            // Generate unique campaign number
            $campaignNumber = $this->generateCampaignNumber();

            $campaign = AdvertiserCampaign::create([
                'application_id' => $subscription->application_id,
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'brand_partner_id' => $subscription->brand_partner_id,
                'campaign_number' => $campaignNumber,
                'name' => $data['name'],
                'objective' => $data['objective'] ?? 'brand_awareness',
                'description' => $data['description'] ?? null,
                'target_audience' => $data['target_audience'] ?? null,
                'target_locations' => $data['target_locations'] ?? [],
                'requested_placements' => $data['requested_placements'] ?? ($data['placement_ids'] ?? ['feed']),
                'cta_text' => $data['cta_text'] ?? 'Learn More',
                'destination_url' => $data['destination_url'] ?? null,
                'status' => AdvertiserCampaign::STATUS_DRAFT,
                'requested_starts_at' => !empty($data['requested_starts_at']) ? Carbon::parse($data['requested_starts_at']) : null,
                'requested_ends_at' => !empty($data['requested_ends_at']) ? Carbon::parse($data['requested_ends_at']) : null,
                'created_by' => $user->id,
                'metadata' => [
                    'asset_ids' => $data['asset_ids'] ?? [],
                    'source' => $data['source'] ?? 'member_portal',
                ],
            ]);

            AdvertiserCampaignStatusHistory::create([
                'campaign_id' => $campaign->id,
                'old_status' => null,
                'new_status' => AdvertiserCampaign::STATUS_DRAFT,
                'changed_by' => $user->id,
                'actor_type' => 'advertiser',
                'notes' => 'Campaign draft initialized',
                'metadata' => ['initial_data' => true],
            ]);

            return $campaign;
        });
    }

    /**
     * Update an existing campaign draft.
     */
    public function updateDraft(AdvertiserCampaign $campaign, array $data, User $user): AdvertiserCampaign
    {
        if ($campaign->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'authorization' => ['You are not authorized to modify this campaign.'],
            ]);
        }

        if (!in_array($campaign->status, [AdvertiserCampaign::STATUS_DRAFT, AdvertiserCampaign::STATUS_CHANGES_REQUESTED], true)) {
            throw ValidationException::withMessages([
                'status' => ["Campaign in '{$campaign->status}' state cannot be edited directly."],
            ]);
        }

        $fillable = array_intersect_key($data, array_flip([
            'name',
            'objective',
            'description',
            'target_audience',
            'target_locations',
            'requested_placements',
            'cta_text',
            'destination_url',
            'requested_starts_at',
            'requested_ends_at',
        ]));

        if (isset($fillable['requested_starts_at']) && !empty($fillable['requested_starts_at'])) {
            $fillable['requested_starts_at'] = Carbon::parse($fillable['requested_starts_at']);
        }
        if (isset($fillable['requested_ends_at']) && !empty($fillable['requested_ends_at'])) {
            $fillable['requested_ends_at'] = Carbon::parse($fillable['requested_ends_at']);
        }

        if (!empty($data['asset_ids'])) {
            $meta = $campaign->metadata ?? [];
            $meta['asset_ids'] = $data['asset_ids'];
            $campaign->metadata = $meta;
        }

        $campaign->fill($fillable);
        $campaign->save();

        return $campaign;
    }

    /**
     * Submit a campaign for administrative review.
     */
    public function submitForReview(AdvertiserCampaign $campaign, ?string $note, User $user): AdvertiserCampaign
    {
        if ($campaign->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'authorization' => ['You do not have permission to submit this campaign.'],
            ]);
        }

        if (!in_array($campaign->status, [AdvertiserCampaign::STATUS_DRAFT, AdvertiserCampaign::STATUS_CHANGES_REQUESTED], true)) {
            throw ValidationException::withMessages([
                'status' => ["Campaign cannot be submitted from status '{$campaign->status}'."],
            ]);
        }

        // Validate essentials
        if (empty($campaign->name) || empty($campaign->destination_url)) {
            throw ValidationException::withMessages([
                'validation' => ['Campaign name and destination URL are required before submitting for review.'],
            ]);
        }

        return DB::transaction(function () use ($campaign, $note, $user) {
            $oldStatus = $campaign->status;
            $campaign->status = AdvertiserCampaign::STATUS_PENDING_REVIEW;
            $campaign->save();

            AdvertiserCampaignStatusHistory::create([
                'campaign_id' => $campaign->id,
                'old_status' => $oldStatus,
                'new_status' => AdvertiserCampaign::STATUS_PENDING_REVIEW,
                'changed_by' => $user->id,
                'actor_type' => 'advertiser',
                'notes' => $note ?: 'Submitted for admin review',
                'metadata' => ['submission_timestamp' => now()->toIso8601String()],
            ]);

            return $campaign;
        });
    }

    /**
     * Admin review action (approve, reject, request corrections).
     */
    public function reviewCampaign(AdvertiserCampaign $campaign, string $decision, ?string $notes, ?User $reviewer): AdvertiserCampaign
    {
        $allowedDecisions = ['approved', 'rejected', 'changes_requested'];
        if (!in_array($decision, $allowedDecisions, true)) {
            throw ValidationException::withMessages([
                'decision' => ["Invalid decision '{$decision}'. Allowed: " . implode(', ', $allowedDecisions)],
            ]);
        }

        return DB::transaction(function () use ($campaign, $decision, $notes, $reviewer) {
            $oldStatus = $campaign->status;
            $reviewerId = $reviewer?->id;

            $campaign->reviewed_by = $reviewerId;
            $campaign->reviewed_at = now();

            if ($decision === 'approved') {
                $campaign->status = AdvertiserCampaign::STATUS_APPROVED;
                $campaign->approved_at = now();
                $campaign->admin_notes = $notes;
                $campaign->save();

                // Publish or schedule ad record in existing ads engine
                $this->publicationService->publishCampaignAd($campaign, $reviewer);
            } elseif ($decision === 'changes_requested') {
                $campaign->status = AdvertiserCampaign::STATUS_CHANGES_REQUESTED;
                $campaign->applicant_feedback = $notes;
                $campaign->admin_notes = $notes;
                $campaign->save();
            } elseif ($decision === 'rejected') {
                $campaign->status = AdvertiserCampaign::STATUS_REJECTED;
                $campaign->applicant_feedback = $notes;
                $campaign->admin_notes = $notes;
                $campaign->save();
            }

            AdvertiserCampaignStatusHistory::create([
                'campaign_id' => $campaign->id,
                'old_status' => $oldStatus,
                'new_status' => $campaign->status,
                'changed_by' => $reviewerId,
                'actor_type' => 'admin',
                'notes' => $notes ?: "Admin review: {$decision}",
                'metadata' => [
                    'decision' => $decision,
                    'reviewed_by' => $reviewerId,
                ],
            ]);

            return $campaign;
        });
    }

    /**
     * Pause a published campaign.
     */
    public function pauseCampaign(AdvertiserCampaign $campaign, ?string $reason, ?User $actor): AdvertiserCampaign
    {
        return DB::transaction(function () use ($campaign, $reason, $actor) {
            $oldStatus = $campaign->status;
            $campaign->status = AdvertiserCampaign::STATUS_PAUSED;
            $campaign->paused_at = now();
            $campaign->save();

            // Deactivate linked ads in ads engine
            $this->publicationService->pauseCampaignAds($campaign);

            AdvertiserCampaignStatusHistory::create([
                'campaign_id' => $campaign->id,
                'old_status' => $oldStatus,
                'new_status' => AdvertiserCampaign::STATUS_PAUSED,
                'changed_by' => $actor?->id,
                'actor_type' => $actor && $actor->id === $campaign->user_id ? 'advertiser' : 'admin',
                'notes' => $reason ?: 'Campaign paused',
            ]);

            return $campaign;
        });
    }

    /**
     * Resume a paused campaign.
     */
    public function resumeCampaign(AdvertiserCampaign $campaign, ?User $actor): AdvertiserCampaign
    {
        return DB::transaction(function () use ($campaign, $actor) {
            $oldStatus = $campaign->status;

            // Re-validate subscription
            $subscription = $campaign->subscription;
            if (!$subscription || $subscription->status !== AdvertiserSubscription::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'subscription' => ['Cannot resume campaign because the linked subscription is not active.'],
                ]);
            }

            $campaign->status = AdvertiserCampaign::STATUS_ACTIVE;
            $campaign->save();

            // Reactivate linked ads
            $this->publicationService->resumeCampaignAds($campaign);

            AdvertiserCampaignStatusHistory::create([
                'campaign_id' => $campaign->id,
                'old_status' => $oldStatus,
                'new_status' => AdvertiserCampaign::STATUS_ACTIVE,
                'changed_by' => $actor?->id,
                'actor_type' => $actor && $actor->id === $campaign->user_id ? 'advertiser' : 'admin',
                'notes' => 'Campaign resumed',
            ]);

            return $campaign;
        });
    }

    /**
     * Cancel campaign.
     */
    public function cancelCampaign(AdvertiserCampaign $campaign, ?string $reason, User $user): AdvertiserCampaign
    {
        if ($campaign->user_id !== $user->id && !($user->is_admin ?? false)) {
            throw ValidationException::withMessages([
                'authorization' => ['Unauthorized to cancel this campaign.'],
            ]);
        }

        return DB::transaction(function () use ($campaign, $reason, $user) {
            $oldStatus = $campaign->status;
            $campaign->status = AdvertiserCampaign::STATUS_CANCELLED;
            $campaign->save();

            $this->publicationService->pauseCampaignAds($campaign);

            AdvertiserCampaignStatusHistory::create([
                'campaign_id' => $campaign->id,
                'old_status' => $oldStatus,
                'new_status' => AdvertiserCampaign::STATUS_CANCELLED,
                'changed_by' => $user->id,
                'actor_type' => $user->id === $campaign->user_id ? 'advertiser' : 'admin',
                'notes' => $reason ?: 'Campaign cancelled',
            ]);

            return $campaign;
        });
    }

    private function generateCampaignNumber(): string
    {
        $year = date('Y');
        $prefix = "CMP-{$year}-";

        $latest = AdvertiserCampaign::where('campaign_number', 'LIKE', "{$prefix}%")
            ->orderByDesc('campaign_number')
            ->first();

        $nextSeq = 1;
        if ($latest && preg_match('/CMP-\d{4}-(\d+)/', $latest->campaign_number, $matches)) {
            $nextSeq = ((int) $matches[1]) + 1;
        }

        return sprintf('%s%06d', $prefix, $nextSeq);
    }
}
