<?php

declare(strict_types=1);

namespace App\Services\Leadership;

use App\Models\City;
use App\Models\Leadership\LeadershipCampaign;
use App\Models\Leadership\LeadershipCampaignScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ScopeService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Get active scopes for a campaign (public).
     *
     * @return Collection<int, LeadershipCampaignScope>|Collection<int, mixed>
     */
    public function getPublicScopes(string $campaignId): Collection
    {
        if (Schema::hasTable('web_leadership_campaign_scopes')) {
            $scopes = LeadershipCampaignScope::query()
                ->where('campaign_id', $campaignId)
                ->where('status', 'active')
                ->orderBy('name', 'asc')
                ->get();

            if ($scopes->isNotEmpty()) {
                return $scopes;
            }
        }

        // Targeted fallback: If no explicit scopes saved yet in table, check campaign settings for participating cities
        $campaign = LeadershipCampaign::find($campaignId);
        $campaignCityIds = $campaign?->settings['city_ids'] ?? ($campaign?->settings['participating_cities'] ?? []);

        if (! empty($campaignCityIds) && is_array($campaignCityIds) && Schema::hasTable('cities')) {
            $cities = City::query()
                ->whereIn('id', $campaignCityIds)
                ->select(['id', 'name', 'state', 'district'])
                ->orderBy('name', 'asc')
                ->get();

            return $cities->map(function (City $c) use ($campaignId): LeadershipCampaignScope {
                $scope = new LeadershipCampaignScope;
                $scope->id = (string) $c->id;
                $scope->campaign_id = $campaignId;
                $scope->city_id = (string) $c->id;
                $scope->name = ! empty($c->district) ? "{$c->district} District" : $c->name;
                $scope->scope_name = $scope->name;
                $scope->scope_type = 'district';
                $scope->city = $c->name;
                $scope->district = $c->district ?: $c->name;
                $scope->state = $c->state;
                $scope->seats_available = 1;
                $scope->status = 'active';

                return $scope;
            });
        }

        // Never dump all global cities from cities table; return empty collection if campaign has no defined scopes
        return new Collection;
    }

    /**
     * List all scopes for a campaign (admin).
     *
     * @return Collection<int, LeadershipCampaignScope>
     */
    public function getAdminScopes(string $campaignId): Collection
    {
        if (! Schema::hasTable('web_leadership_campaign_scopes')) {
            return new Collection;
        }

        return LeadershipCampaignScope::query()
            ->with(['parentScope', 'city'])
            ->where('campaign_id', $campaignId)
            ->where('status', 'active')
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Sync campaign participating cities.
     *
     * @param  array<string>  $cityIds
     * @return Collection<int, LeadershipCampaignScope>
     */
    public function syncCampaignCities(string $campaignId, array $cityIds): Collection
    {
        if (! Schema::hasTable('web_leadership_campaign_scopes')) {
            return new Collection;
        }

        $campaign = LeadershipCampaign::findOrFail($campaignId);

        // Deactivate scopes not in provided cityIds
        LeadershipCampaignScope::where('campaign_id', $campaign->id)
            ->whereNotIn('city_id', $cityIds)
            ->update(['status' => 'inactive']);

        if (! empty($cityIds) && Schema::hasTable('cities')) {
            $cities = City::whereIn('id', $cityIds)->get();

            foreach ($cities as $city) {
                $scopeName = ! empty($city->district) ? "{$city->district} District" : $city->name;

                LeadershipCampaignScope::updateOrCreate(
                    [
                        'campaign_id' => $campaign->id,
                        'city_id' => $city->id,
                    ],
                    [
                        'name' => $scopeName,
                        'scope_name' => $scopeName,
                        'scope_type' => 'district',
                        'city' => $city->name,
                        'district' => $city->district ?: $city->name,
                        'state' => $city->state,
                        'seats_available' => 1,
                        'status' => 'active',
                    ]
                );
            }
        }

        return $this->getAdminScopes($campaign->id);
    }

    /**
     * Create a new campaign scope.
     *
     * @param  array<string, mixed>  $data
     */
    public function createScope(string $campaignId, array $data): LeadershipCampaignScope
    {
        $campaign = LeadershipCampaign::findOrFail($campaignId);

        $name = $data['name'] ?? ($data['scope_name'] ?? '');
        $scopeType = $data['scope_type'] ?? 'district';

        $exists = LeadershipCampaignScope::query()
            ->where('campaign_id', $campaign->id)
            ->where('scope_type', $scopeType)
            ->where(function ($q) use ($name): void {
                $q->where('scope_name', $name)->orWhere('name', $name);
            })
            ->exists();

        if ($exists) {
            throw new RuntimeException('A scope with this type and name already exists for this campaign.');
        }

        $data['campaign_id'] = $campaign->id;
        $data['name'] = $name;
        $data['scope_name'] = $name;
        $data['status'] = $data['status'] ?? 'active';

        if (! empty($data['city_id']) && Schema::hasTable('cities')) {
            $city = City::find($data['city_id']);
            if ($city) {
                $data['city'] = $data['city'] ?? $city->name;
                $data['district'] = $data['district'] ?? ($city->district ?: $city->name);
                $data['state'] = $data['state'] ?? $city->state;
            }
        }

        /** @var LeadershipCampaignScope $scope */
        $scope = LeadershipCampaignScope::create($data);

        $this->auditService->log(
            action: 'campaign.scope.created',
            entityType: 'LeadershipCampaignScope',
            entityId: $scope->id,
            campaignId: $campaign->id,
            afterData: $scope->toArray(),
            remarks: "Scope {$scope->name} ({$scope->scope_type}) added"
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

        if (isset($data['name']) && ! isset($data['scope_name'])) {
            $data['scope_name'] = $data['name'];
        }

        $scope->update($data);

        $this->auditService->log(
            action: 'campaign.scope.updated',
            entityType: 'LeadershipCampaignScope',
            entityId: $scope->id,
            campaignId: $scope->campaign_id,
            beforeData: $before,
            afterData: $scope->fresh()->toArray(),
            remarks: "Scope {$scope->name} updated"
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
            remarks: "Scope {$scope->name} deactivated"
        );

        return $scope->fresh();
    }
}
