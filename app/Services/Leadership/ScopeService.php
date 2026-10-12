<?php

declare(strict_types=1);

namespace App\Services\Leadership;

use App\Models\Leadership\LeadershipCampaign;
use App\Models\Leadership\LeadershipCampaignScope;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class ScopeService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Get active scopes for a campaign (public).
     *
     * @return Collection<int, LeadershipCampaignScope>
     */
    public function getPublicScopes(string $campaignId): Collection
    {
        return LeadershipCampaignScope::query()
            ->where('campaign_id', $campaignId)
            ->where('status', 'active')
            ->orderBy('scope_name', 'asc')
            ->get();
    }

    /**
     * List all scopes for a campaign (admin).
     *
     * @return Collection<int, LeadershipCampaignScope>
     */
    public function getAdminScopes(string $campaignId): Collection
    {
        return LeadershipCampaignScope::query()
            ->with(['parentScope'])
            ->where('campaign_id', $campaignId)
            ->orderBy('scope_type', 'asc')
            ->orderBy('scope_name', 'asc')
            ->get();
    }

    /**
     * Create a new campaign scope.
     *
     * @param  array<string, mixed>  $data
     */
    public function createScope(string $campaignId, array $data): LeadershipCampaignScope
    {
        $campaign = LeadershipCampaign::findOrFail($campaignId);

        $exists = LeadershipCampaignScope::query()
            ->where('campaign_id', $campaign->id)
            ->where('scope_type', $data['scope_type'])
            ->where('scope_name', $data['scope_name'])
            ->exists();

        if ($exists) {
            throw new RuntimeException('A scope with this type and name already exists for this campaign.');
        }

        $data['campaign_id'] = $campaign->id;
        $data['status'] = $data['status'] ?? 'active';

        /** @var LeadershipCampaignScope $scope */
        $scope = LeadershipCampaignScope::create($data);

        $this->auditService->log(
            action: 'campaign.scope.created',
            entityType: 'LeadershipCampaignScope',
            entityId: $scope->id,
            campaignId: $campaign->id,
            afterData: $scope->toArray(),
            remarks: "Scope {$scope->scope_name} ({$scope->scope_type}) added"
        );

        return $scope;
    }

    /**
     * Update an existing campaign scope.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateScope(string $scopeId, array $data): LeadershipCampaignScope
    {
        /** @var LeadershipCampaignScope $scope */
        $scope = LeadershipCampaignScope::findOrFail($scopeId);
        $before = $scope->toArray();

        $scope->update($data);

        $this->auditService->log(
            action: 'campaign.scope.updated',
            entityType: 'LeadershipCampaignScope',
            entityId: $scope->id,
            campaignId: $scope->campaign_id,
            beforeData: $before,
            afterData: $scope->fresh()->toArray(),
            remarks: "Scope {$scope->scope_name} updated"
        );

        return $scope->fresh();
    }

    /**
     * Deactivate a scope.
     */
    public function deactivateScope(string $scopeId): LeadershipCampaignScope
    {
        /** @var LeadershipCampaignScope $scope */
        $scope = LeadershipCampaignScope::findOrFail($scopeId);
        $before = $scope->toArray();

        $scope->update(['status' => 'inactive']);

        $this->auditService->log(
            action: 'campaign.scope.deactivated',
            entityType: 'LeadershipCampaignScope',
            entityId: $scope->id,
            campaignId: $scope->campaign_id,
            beforeData: $before,
            afterData: $scope->fresh()->toArray(),
            remarks: "Scope {$scope->scope_name} deactivated"
        );

        return $scope->fresh();
    }
}
