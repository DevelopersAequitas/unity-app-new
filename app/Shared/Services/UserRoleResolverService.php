<?php

declare(strict_types=1);

namespace App\Shared\Services;

use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserRoleResolverService
{
    public const ROLE_HIERARCHY_RANK = [
        'superAdmin' => 100,
        'countryDirector' => 90,
        'districtExecDirector' => 80,
        'industryDirector' => 70,
        'circleDirector' => 60,
        'circleFounder' => 50,
        'chairBusinessGrowth' => 40,
        'chairMembership' => 38,
        'chairEventsPrograms' => 36,
        'circleChair' => 34,
        'viceChair' => 30,
        'secretary' => 20,
    ];

    /**
     * Map database role key/name to canonical standard role key.
     */
    public function normalizeRoleKey(string $rawRole): string
    {
        $cleaned = trim($rawRole);
        $lower = strtolower($cleaned);
        $lowerNormalized = str_replace(['_', '-'], ' ', $lower);

        return match (true) {
            in_array($lower, ['superadmin', 'super_admin', 'global_admin', 'admin'], true) || str_contains($lowerNormalized, 'super admin') || str_contains($lowerNormalized, 'global admin') => 'superAdmin',
            in_array($lower, ['countrydirector', 'country_director', 'eed'], true) || str_contains($lowerNormalized, 'country director') => 'countryDirector',
            in_array($lower, ['districtexecdirector', 'district_exec_director', 'ded'], true) || str_contains($lowerNormalized, 'district exec director') || str_contains($lowerNormalized, 'district director') => 'districtExecDirector',
            in_array($lower, ['industrydirector', 'industry_director', 'id', 'ied'], true) || str_contains($lowerNormalized, 'industry director') => 'industryDirector',
            in_array($lower, ['circledirector', 'circle_director', 'cd', 'director'], true) || str_contains($lowerNormalized, 'circle director') => 'circleDirector',
            in_array($lower, ['circlefounder', 'circle_founder', 'cf', 'founder'], true) || str_contains($lowerNormalized, 'circle founder') => 'circleFounder',
            in_array($lower, ['business_growth_committee', 'business growth committee', 'chairbusinessgrowth', 'chair_business_growth', 'businessgrowthcommitteechair', 'business_growth_committee_chair', 'business_growth_chair', 'chair - business growth committee', 'business_growth'], true) || str_contains($lowerNormalized, 'business growth') => 'chairBusinessGrowth',
            in_array($lower, ['membership_growth_committee', 'membership growth committee', 'chairmembership', 'chair_membership', 'membershipgrowthcommitteechair', 'membership_growth_committee_chair', 'membership_committee_chair', 'membershipgrowthchair', 'membership_chair', 'chair - membership committee', 'chair - membership growth committee', 'membership_growth'], true) || str_contains($lowerNormalized, 'membership growth') || str_contains($lowerNormalized, 'membership committee') => 'chairMembership',
            in_array($lower, ['events_impacts_committee', 'events impacts committee', 'chaireventsprograms', 'chair_events_programs', 'eventsimpactscommitteechair', 'events_impacts_committee_chair', 'events_programs_committee_chair', 'eventsprogramscommitteechair', 'events_impacts_chair', 'events_chair', 'chair - events & programs committee', 'chair - events & impacts committee', 'events_programs_committee', 'events_impacts'], true) || str_contains($lowerNormalized, 'events impacts') || str_contains($lowerNormalized, 'events programs') || str_contains($lowerNormalized, 'events & programs') || str_contains($lowerNormalized, 'events & impacts') => 'chairEventsPrograms',
            in_array($lower, ['circlechair', 'circle_chair', 'chair'], true) => 'chairBusinessGrowth',
            in_array($lower, ['vice_chair', 'vicechair', 'vice chair'], true) || str_contains($lowerNormalized, 'vice chair') => 'viceChair',
            in_array($lower, ['secretary', 'circle_secretary'], true) || str_contains($lowerNormalized, 'secretary') => 'secretary',
            default => $cleaned,
        };
    }

    /**
     * Determine if a given role key is an authorized leadership role.
     */
    public function isLeaderRole(string $rawRole): bool
    {
        $normalized = $this->normalizeRoleKey($rawRole);

        return in_array($normalized, [
            'superAdmin',
            'countryDirector',
            'districtExecDirector',
            'industryDirector',
            'circleDirector',
            'circleFounder',
            'chairBusinessGrowth',
            'chairMembership',
            'chairEventsPrograms',
            'circleChair',
            'viceChair',
            'secretary',
        ], true);
    }

    /**
     * Check if a given User is an authorized Leader.
     */
    public function isLeader(User $user): bool
    {
        $roleInfo = $this->resolveUserRole($user);

        return (bool) ($roleInfo['is_leader'] ?? false);
    }

    /**
     * Canonical display label for a role.
     */
    public function getRoleLabel(string $canonicalRole): string
    {
        return match ($canonicalRole) {
            'superAdmin' => 'Super Admin',
            'countryDirector' => 'Country Director',
            'districtExecDirector' => 'District Exec Director',
            'industryDirector' => 'Industry Director',
            'circleDirector' => 'Circle Director',
            'circleFounder' => 'Circle Founder',
            'chairBusinessGrowth' => 'Chair - Business Growth Committee',
            'chairMembership' => 'Chair - Membership Committee',
            'chairEventsPrograms' => 'Chair - Events & Programs Committee',
            'circleChair' => 'Chair - Business Growth Committee',
            'viceChair' => 'Vice Chair',
            'secretary' => 'Secretary',
            'member' => 'Peer Member',
            default => ucwords(str_replace(['_', '-'], ' ', $canonicalRole)),
        };
    }

    /**
     * Detect the user's primary leader role and custom label.
     *
     * @return array{role: string, custom_role_label: ?string, regional_scope: string, is_leader: bool}
     */
    public function resolveUserRole(User $user): array
    {
        $userId = (string) $user->id;
        $userEmail = strtolower(trim((string) ($user->email ?? '')));

        // 1. Check AdminUser roles & direct user role assignments from Dynamic RBAC
        $adminUser = AdminUser::query()
            ->where('id', $userId)
            ->when($userEmail !== '', fn ($q) => $q->orWhereRaw('LOWER(email) = ?', [$userEmail]))
            ->first();

        $candidateRoles = [];
        $adminUserIds = array_values(array_filter(array_unique([$userId, $adminUser?->id])));

        // Immediate Fast-Path: If admin user is designated Global Admin / Super in AdminAccess or via direct role column
        if ($adminUser) {
            $directAdminRole = $this->normalizeRoleKey((string) ($adminUser->role ?? ''));
            if ($directAdminRole === 'superAdmin' || AdminAccess::isGlobalAdmin($adminUser) || AdminAccess::isSuper($adminUser)) {
                return [
                    'role' => 'superAdmin',
                    'custom_role_label' => 'Super Admin',
                    'regional_scope' => 'Global Scope',
                    'is_leader' => true,
                ];
            }
            if ($this->isLeaderRole((string) ($adminUser->role ?? ''))) {
                $candidateRoles[] = $directAdminRole;
            }
        }

        if (! empty($user->role) && $this->isLeaderRole((string) $user->role)) {
            $userDirectRole = $this->normalizeRoleKey((string) $user->role);
            if ($userDirectRole === 'superAdmin') {
                return [
                    'role' => 'superAdmin',
                    'custom_role_label' => 'Super Admin',
                    'regional_scope' => 'Global Scope',
                    'is_leader' => true,
                ];
            }
            $candidateRoles[] = $userDirectRole;
        }

        if (! empty($adminUserIds) && Schema::hasTable('admin_user_roles') && Schema::hasTable('roles')) {
            $roleRows = DB::table('admin_user_roles')
                ->join('roles', 'admin_user_roles.role_id', '=', 'roles.id')
                ->whereIn('admin_user_roles.user_id', $adminUserIds)
                ->select(['roles.key', 'roles.name', 'roles.role_code'])
                ->get();

            foreach ($roleRows as $row) {
                foreach ([$row->key, $row->name, $row->role_code] as $field) {
                    if ($field && $this->isLeaderRole((string) $field)) {
                        $norm = $this->normalizeRoleKey((string) $field);
                        if ($norm === 'superAdmin') {
                            return [
                                'role' => 'superAdmin',
                                'custom_role_label' => 'Super Admin',
                                'regional_scope' => 'Global Scope',
                                'is_leader' => true,
                            ];
                        }
                        $candidateRoles[] = $norm;
                    }
                }
            }
        }

        // 2. Check Circle direct assignment columns & calendar JSON
        if (Schema::hasTable('circles')) {
            if (Circle::query()->where('circle_founder_user_id', $userId)->orWhere('founder_user_id', $userId)->exists()) {
                $candidateRoles[] = 'circleFounder';
            }

            if (Circle::query()->where('circle_director_user_id', $userId)->orWhere('director_user_id', $userId)->exists()) {
                $candidateRoles[] = 'circleDirector';
            }

            if (Circle::query()->where('industry_director_user_id', $userId)->exists()) {
                $candidateRoles[] = 'industryDirector';
            }

            if (Circle::query()->where('ded_user_id', $userId)->exists()) {
                $candidateRoles[] = 'districtExecDirector';
            }

            if (Circle::query()->where('eed_user_id', $userId)->exists()) {
                $candidateRoles[] = 'countryDirector';
            }

            // Check committee chairs in circles (via column, json calendar, or chair_user_id)
            $circles = Circle::query()->whereNull('deleted_at')->get();
            foreach ($circles as $circle) {
                $bgId = data_get($circle->calendar, 'leadership.business_growth_committee_chair_user_id')
                    ?? data_get($circle->calendar, 'leadership.business_growth_committee_chair.id')
                    ?? data_get($circle->calendar, 'business_growth_committee_chair.id')
                    ?? ($circle->business_growth_committee_chair_user_id ?? null);
                if ($bgId && (string) $bgId === $userId) {
                    $candidateRoles[] = 'chairBusinessGrowth';
                }

                $mgId = data_get($circle->calendar, 'leadership.membership_growth_committee_chair_user_id')
                    ?? data_get($circle->calendar, 'leadership.membership_growth_committee_chair.id')
                    ?? data_get($circle->calendar, 'membership_growth_committee_chair.id')
                    ?? ($circle->membership_growth_committee_chair_user_id ?? null);
                if ($mgId && (string) $mgId === $userId) {
                    $candidateRoles[] = 'chairMembership';
                }

                $eiId = data_get($circle->calendar, 'leadership.events_impacts_committee_chair_user_id')
                    ?? data_get($circle->calendar, 'leadership.events_impacts_committee_chair.id')
                    ?? data_get($circle->calendar, 'events_impacts_committee_chair.id')
                    ?? ($circle->events_impacts_committee_chair_user_id ?? null);
                if ($eiId && (string) $eiId === $userId) {
                    $candidateRoles[] = 'chairEventsPrograms';
                }

                $vcId = data_get($circle->calendar, 'leadership.vice_chair_user_id')
                    ?? ($circle->vice_chair_user_id ?? null);
                if ($vcId && (string) $vcId === $userId) {
                    $candidateRoles[] = 'viceChair';
                }

                $secId = data_get($circle->calendar, 'leadership.secretary_user_id')
                    ?? ($circle->secretary_user_id ?? null);
                if ($secId && (string) $secId === $userId) {
                    $candidateRoles[] = 'secretary';
                }

                if ((string) $circle->chair_user_id === $userId || (string) data_get($circle->calendar, 'leadership.chair_user_id') === $userId) {
                    $candidateRoles[] = 'chairBusinessGrowth';
                }
            }
        }

        // 3. Check circle_members role & roleRef
        if (Schema::hasTable('circle_members')) {
            $circleMembers = DB::table('circle_members')
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->get();

            foreach ($circleMembers as $cm) {
                $rawRole = (string) ($cm->role ?? '');
                $refRoleKey = '';
                if (! empty($cm->role_id) && Schema::hasTable('roles')) {
                    $refRoleKey = (string) (DB::table('roles')->where('id', $cm->role_id)->value('key') ?? '');
                }

                $targetRole = $this->isLeaderRole($rawRole) ? $rawRole : $refRoleKey;
                if ($this->isLeaderRole($targetRole)) {
                    $candidateRoles[] = $this->normalizeRoleKey($targetRole);
                }
            }
        }

        // 4. Check District / Industry Director dedicated tables
        if (Schema::hasTable('admin_ded_districts') && DB::table('admin_ded_districts')->where(function ($q) use ($userId, $adminUserIds): void {
            $q->where('user_id', $userId)->orWhereIn('admin_user_id', $adminUserIds);
        })->exists()) {
            $candidateRoles[] = 'districtExecDirector';
        }

        if (Schema::hasTable('industry_director_assignments') && DB::table('industry_director_assignments')->where('is_active', true)->whereIn('admin_user_id', $adminUserIds)->exists()) {
            $candidateRoles[] = 'industryDirector';
        }

        // Evaluate highest ranked leadership role
        if (! empty($candidateRoles)) {
            $candidateRoles = array_values(array_unique($candidateRoles));
            usort($candidateRoles, fn ($a, $b) => (self::ROLE_HIERARCHY_RANK[$b] ?? 0) <=> (self::ROLE_HIERARCHY_RANK[$a] ?? 0));
            $bestRole = $candidateRoles[0];

            return [
                'role' => $bestRole,
                'custom_role_label' => $this->getRoleLabel($bestRole),
                'regional_scope' => $this->resolveRegionalScope($bestRole),
                'is_leader' => true,
            ];
        }

        // Non-leader: user has no assigned leadership role
        return [
            'role' => 'member',
            'custom_role_label' => 'Peer Member',
            'regional_scope' => 'Own Circle',
            'is_leader' => false,
        ];
    }

    /**
     * Resolve regional scope string.
     */
    public function resolveRegionalScope(string $role): string
    {
        return match ($role) {
            'superAdmin' => 'Global Scope',
            'countryDirector' => 'Country Scope',
            'districtExecDirector' => 'District Scope',
            'industryDirector' => 'Industry Scope',
            default => 'Own Circle',
        };
    }
}
