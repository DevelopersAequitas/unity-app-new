<?php

declare(strict_types=1);

namespace App\Services\Leadership;

use App\Models\Leadership\LeadershipCampaign;
use App\Models\Leadership\LeadershipFinalDecision;
use App\Models\Leadership\LeadershipNomination;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class CampaignService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Get active public campaigns.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getActivePublicCampaigns(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = LeadershipCampaign::query()
            ->with(['role', 'scopes'])
            ->where('status', 'active');

        if (! empty($filters['role_id'])) {
            $query->where('role_id', $filters['role_id']);
        }

        if (! empty($filters['scope_type']) || ! empty($filters['scope_name'])) {
            $query->whereHas('scopes', function ($q) use ($filters): void {
                if (! empty($filters['scope_type'])) {
                    $q->where('scope_type', $filters['scope_type']);
                }
                if (! empty($filters['scope_name'])) {
                    $q->where('scope_name', 'ilike', '%'.$filters['scope_name'].'%');
                }
                $q->where('status', 'active');
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get campaign public details.
     */
    public function getPublicCampaignDetails(string $campaignId): LeadershipCampaign
    {
        /** @var LeadershipCampaign $campaign */
        $campaign = LeadershipCampaign::query()
            ->with(['role', 'scopes' => fn ($q) => $q->where('status', 'active')])
            ->where('status', 'active')
            ->findOrFail($campaignId);

        return $campaign;
    }

    /**
     * Get approved public candidates for a campaign.
     */
    public function getApprovedPublicCandidates(string $campaignId, ?string $scopeId = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = LeadershipNomination::query()
            ->with(['scope', 'campaign.role'])
            ->where('campaign_id', $campaignId)
            ->whereIn('status', ['approved', 'shortlisted', 'winner']);

        if ($scopeId) {
            $query->where('scope_id', $scopeId);
        }

        return $query->orderBy('full_name', 'asc')->paginate($perPage);
    }

    /**
     * Get published winners for a campaign.
     *
     * @return Collection<int, LeadershipFinalDecision>
     */
    public function getPublishedWinners(string $campaignId): Collection
    {
        return LeadershipFinalDecision::query()
            ->with(['nomination.scope', 'nomination.campaign.role', 'creatives' => fn ($q) => $q->where('publication_status', 'published')])
            ->where('campaign_id', $campaignId)
            ->where('decision', 'selected')
            ->where('is_winner', true)
            ->where('publication_status', 'published')
            ->get();
    }

    /**
     * List campaigns for admin panel.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listAdminCampaigns(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = LeadershipCampaign::query()
            ->with(['role', 'scopes'])
            ->withCount([
                'nominations as total_nominations',
                'nominations as shortlisted_count' => fn ($q) => $q->where('status', 'shortlisted'),
            ]);

        if (! empty($filters['role_id'])) {
            $query->where('role_id', $filters['role_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['campaign_year'])) {
            $query->where('campaign_year', (int) $filters['campaign_year']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(fn ($q) => $q->where('name', 'ilike', $term)->orWhere('slug', 'ilike', $term));
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = strtolower((string) ($filters['sort_order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['name', 'campaign_year', 'status', 'created_at', 'published_at'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Create campaign.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCampaign(array $data, ?string $userId = null): LeadershipCampaign
    {
        $role = Role::query()->where('id', $data['role_id'])->where('status', 'active')->first();
        if (! $role) {
            throw new RuntimeException('Selected role does not exist or is inactive.');
        }

        $slug = $data['slug'] ?? Str::slug($data['name'].'-'.($data['campaign_year'] ?? date('Y')));
        if (LeadershipCampaign::query()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::random(5);
        }

        $data['slug'] = $slug;
        $data['status'] = 'draft';
        $data['created_by'] = ($userId && User::where('id', $userId)->exists()) ? (string) $userId : null;

        /** @var LeadershipCampaign $campaign */
        $campaign = LeadershipCampaign::create($data);

        $this->auditService->log(
            action: 'campaign.created',
            entityType: 'LeadershipCampaign',
            entityId: $campaign->id,
            campaignId: $campaign->id,
            afterData: $campaign->toArray(),
            remarks: 'Campaign created in draft mode'
        );

        return $campaign->load(['role', 'scopes']);
    }

    /**
     * Get admin campaign details.
     */
    public function getAdminCampaignDetails(string $campaignId): LeadershipCampaign
    {
        /** @var LeadershipCampaign $campaign */
        $campaign = LeadershipCampaign::query()
            ->with(['role', 'scopes', 'formTemplates', 'evaluationCriteria'])
            ->withCount(['nominations', 'votes'])
            ->findOrFail($campaignId);

        return $campaign;
    }

    /**
     * Update campaign settings.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCampaign(string $campaignId, array $data, ?string $userId = null): LeadershipCampaign
    {
        /** @var LeadershipCampaign $campaign */
        $campaign = LeadershipCampaign::findOrFail($campaignId);
        $before = $campaign->toArray();

        // Validate role if changing
        if (! empty($data['role_id']) && $data['role_id'] !== $campaign->role_id) {
            $role = Role::query()->where('id', $data['role_id'])->where('status', 'active')->first();
            if (! $role) {
                throw new RuntimeException('Selected role is invalid.');
            }
        }

        $campaign->update($data);

        $this->auditService->log(
            action: 'campaign.updated',
            entityType: 'LeadershipCampaign',
            entityId: $campaign->id,
            campaignId: $campaign->id,
            beforeData: $before,
            afterData: $campaign->fresh()->toArray(),
            remarks: $data['remarks'] ?? 'Campaign configuration updated'
        );

        return $campaign->fresh(['role', 'scopes']);
    }

    /**
     * Publish campaign.
     */
    public function publishCampaign(string $campaignId, string $remarks = '', ?string $userId = null): LeadershipCampaign
    {
        /** @var LeadershipCampaign $campaign */
        $campaign = LeadershipCampaign::findOrFail($campaignId);

        if ($campaign->status === 'active') {
            throw new RuntimeException('Campaign is already published and active.');
        }

        $before = $campaign->toArray();

        $campaign->update([
            'status' => 'active',
            'published_at' => Carbon::now(),
        ]);

        $this->auditService->log(
            action: 'campaign.published',
            entityType: 'LeadershipCampaign',
            entityId: $campaign->id,
            campaignId: $campaign->id,
            beforeData: $before,
            afterData: $campaign->fresh()->toArray(),
            remarks: $remarks ?: 'Campaign published for public access'
        );

        return $campaign->fresh(['role', 'scopes']);
    }

    /**
     * Pause campaign.
     */
    public function pauseCampaign(string $campaignId, string $reason, ?string $userId = null): LeadershipCampaign
    {
        /** @var LeadershipCampaign $campaign */
        $campaign = LeadershipCampaign::findOrFail($campaignId);

        if ($campaign->status !== 'active') {
            throw new RuntimeException('Only active campaigns can be paused.');
        }

        $before = $campaign->toArray();

        $campaign->update([
            'status' => 'paused',
        ]);

        $this->auditService->log(
            action: 'campaign.paused',
            entityType: 'LeadershipCampaign',
            entityId: $campaign->id,
            campaignId: $campaign->id,
            beforeData: $before,
            afterData: $campaign->fresh()->toArray(),
            remarks: $reason
        );

        return $campaign->fresh(['role', 'scopes']);
    }

    /**
     * Resume campaign.
     */
    public function resumeCampaign(string $campaignId, string $remarks = '', ?string $userId = null): LeadershipCampaign
    {
        /** @var LeadershipCampaign $campaign */
        $campaign = LeadershipCampaign::findOrFail($campaignId);

        if ($campaign->status !== 'paused') {
            throw new RuntimeException('Only paused campaigns can be resumed.');
        }

        $before = $campaign->toArray();

        $campaign->update([
            'status' => 'active',
        ]);

        $this->auditService->log(
            action: 'campaign.resumed',
            entityType: 'LeadershipCampaign',
            entityId: $campaign->id,
            campaignId: $campaign->id,
            beforeData: $before,
            afterData: $campaign->fresh()->toArray(),
            remarks: $remarks ?: 'Campaign resumed'
        );

        return $campaign->fresh(['role', 'scopes']);
    }

    /**
     * Fetch dynamic assignable roles from existing roles table.
     *
     * @return Collection<int, Role>
     */
    public function getDynamicRoles(): Collection
    {
        return Role::query()
            ->where('status', 'active')
            ->where('is_assignable', true)
            ->orderBy('name', 'asc')
            ->get();
    }
}
