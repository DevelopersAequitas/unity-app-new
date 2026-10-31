<?php

declare(strict_types=1);

namespace App\Shared\Authorization;

use App\Models\AdminUser;
use App\Models\Role;
use App\Models\User;
use App\Modules\DynamicAccess\Models\RoleFieldAccessRule;
use App\Shared\OrganizationalScope\OrganizationalScopeService;
use Illuminate\Support\Facades\Schema;

class FieldAccessService
{
    public function __construct(
        private readonly OrganizationalScopeService $scopeService,
    ) {}

    /**
     * Get effective field access rules for a role ID or array of role keys.
     *
     * @param  array<int, string>  $roleKeys
     * @return array<string, array<string, array{can_view: bool, can_edit: bool, mask_strategy: string}>>
     */
    public function getRulesForRoleKeys(array $roleKeys): array
    {
        $rules = [];

        if (Schema::hasTable('role_field_access_rules') && Schema::hasTable('roles') && ! empty($roleKeys)) {
            $roleIds = Role::query()->whereIn('key', $roleKeys)->pluck('id')->all();
            if (! empty($roleIds)) {
                $rows = RoleFieldAccessRule::query()->whereIn('role_id', $roleIds)->get();
                foreach ($rows as $row) {
                    $res = (string) $row->resource_key;
                    $field = (string) $row->field_key;
                    $existing = $rules[$res][$field] ?? null;

                    if (! $existing) {
                        $rules[$res][$field] = [
                            'can_view' => (bool) $row->can_view,
                            'can_edit' => (bool) $row->can_edit,
                            'mask_strategy' => (string) ($row->mask_strategy ?: 'none'),
                        ];
                    } else {
                        // Union of multiple roles: most permissive wins
                        $rules[$res][$field] = [
                            'can_view' => $existing['can_view'] || (bool) $row->can_view,
                            'can_edit' => $existing['can_edit'] || (bool) $row->can_edit,
                            'mask_strategy' => ($existing['mask_strategy'] === 'none' || $row->mask_strategy === 'none')
                                ? 'none'
                                : (string) $row->mask_strategy,
                        ];
                    }
                }
            }
        }

        return $rules;
    }

    /**
     * Filter and mask a single resource payload according to the actor's field access rules.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function filterRecord(User|AdminUser|null $actor, string $resourceKey, array $record): array
    {
        $scope = $this->scopeService->resolveForActor($actor, $resourceKey);
        if ($scope['is_global']) {
            return $record;
        }

        $allRules = $this->getRulesForRoleKeys($scope['effective_role_keys']);
        $resourceRules = $allRules[$resourceKey] ?? [];

        if (empty($resourceRules)) {
            return $record;
        }

        foreach ($resourceRules as $fieldKey => $rule) {
            if (! array_key_exists($fieldKey, $record)) {
                continue;
            }

            if (! $rule['can_view'] || $rule['mask_strategy'] === 'hidden') {
                unset($record[$fieldKey]);

                continue;
            }

            $value = $record[$fieldKey];
            if ($value === null || $value === '' || $rule['mask_strategy'] === 'none') {
                continue;
            }

            $record[$fieldKey] = $this->applyMask((string) $value, $rule['mask_strategy']);
        }

        return $record;
    }

    /**
     * Filter a list of resource records.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    public function filterCollection(User|AdminUser|null $actor, string $resourceKey, array $records): array
    {
        $scope = $this->scopeService->resolveForActor($actor, $resourceKey);
        if ($scope['is_global']) {
            return $records;
        }

        $allRules = $this->getRulesForRoleKeys($scope['effective_role_keys']);
        if (empty($allRules[$resourceKey])) {
            return $records;
        }

        return array_map(fn (array $item): array => $this->filterRecord($actor, $resourceKey, $item), $records);
    }

    public function applyMask(string $value, string $strategy): string
    {
        return match ($strategy) {
            'partial_phone' => strlen($value) > 4
                ? substr($value, 0, min(3, strlen($value) - 4)).str_repeat('*', max(4, strlen($value) - 7)).substr($value, -4)
                : '****',
            'partial_email' => $this->maskEmail($value),
            'redacted' => '[REDACTED]',
            default => $value,
        };
    }

    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return '***';
        }

        [$local, $domain] = explode('@', $email, 2);
        $prefix = substr($local, 0, min(2, strlen($local)));

        return $prefix.'***@'.$domain;
    }
}

