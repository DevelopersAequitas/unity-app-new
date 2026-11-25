<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Leader\Services\LeaderPermissionService;
use App\Shared\Services\UserRoleResolverService;
use Tests\TestCase;

class UserRoleResolverUnitTest extends TestCase
{
    private UserRoleResolverService $resolver;

    private LeaderPermissionService $permissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new UserRoleResolverService;
        $this->permissions = new LeaderPermissionService($this->resolver);
    }

    public function test_all_canonical_roles_normalize_and_are_recognized_as_leaders(): void
    {
        $cases = [
            'super_admin' => 'superAdmin',
            'superAdmin' => 'superAdmin',
            'global_admin' => 'superAdmin',
            'global_founder' => 'globalFounder',
            'globalFounder' => 'globalFounder',
            'country_director' => 'countryDirector',
            'eed' => 'countryDirector',
            'ded' => 'districtExecDirector',
            'district_exec_director' => 'districtExecDirector',
            'industry_director' => 'industryDirector',
            'id' => 'industryDirector',
            'circle_founder' => 'circleFounder',
            'cf' => 'circleFounder',
            'circle_director' => 'circleDirector',
            'cd' => 'circleDirector',
            'chair_business_growth_committee' => 'chairBusinessGrowth',
            'chair_membership_growth_committee' => 'chairMembership',
            'chair_events_impacts_committee' => 'chairEventsPrograms',
            'circle_chair' => 'circleChair',
            'chair' => 'circleChair',
            'committee_leader' => 'committeeLeader',
            'committeeLeader' => 'committeeLeader',
            'vice_chair' => 'viceChair',
            'secretary' => 'secretary',
        ];

        foreach ($cases as $raw => $expected) {
            $this->assertSame($expected, $this->resolver->normalizeRoleKey($raw), "Failed normalizing {$raw}");
            $this->assertTrue($this->resolver->isLeaderRole($raw), "Expected {$raw} to be leader role");
        }
    }

    public function test_unknown_and_member_roles_fail_safely(): void
    {
        foreach (['', 'member', 'guest', 'random_display_label', 'fake_super_admin_wannabe', 'not_a_global_founder'] as $nonLeader) {
            $this->assertFalse($this->resolver->isLeaderRole($nonLeader), "Unexpectedly treated {$nonLeader} as leader");
        }
    }

    public function test_chair_variants_remain_distinct(): void
    {
        $bg = $this->resolver->normalizeRoleKey('chair_business_growth_committee');
        $mg = $this->resolver->normalizeRoleKey('chair_membership_growth_committee');
        $ei = $this->resolver->normalizeRoleKey('chair_events_impacts_committee');
        $cc = $this->resolver->normalizeRoleKey('circle_chair');

        $this->assertCount(4, array_unique([$bg, $mg, $ei, $cc]));
    }

    public function test_default_capabilities_and_regional_scope_cover_all_canonical_roles(): void
    {
        $defaults = $this->permissions->getDefaultRoleCapabilities();

        foreach (array_keys(UserRoleResolverService::ROLE_HIERARCHY_RANK) as $role) {
            $this->assertArrayHasKey($role, $defaults, "Missing default capabilities for {$role}");
            $this->assertContains('access_dashboard', $defaults[$role]);
        }

        $this->assertSame('Global Scope', $this->resolver->resolveRegionalScope('superAdmin'));
        $this->assertSame('Global Scope', $this->resolver->resolveRegionalScope('globalFounder'));
        $this->assertSame('Country Scope', $this->resolver->resolveRegionalScope('countryDirector'));
        $this->assertSame('District Scope', $this->resolver->resolveRegionalScope('districtExecDirector'));
        $this->assertSame('Industry Scope', $this->resolver->resolveRegionalScope('industryDirector'));
        $this->assertSame('Own Circle', $this->resolver->resolveRegionalScope('committeeLeader'));
        $this->assertSame('Own Circle', $this->resolver->resolveRegionalScope('viceChair'));
        $this->assertSame('Own Circle', $this->resolver->resolveRegionalScope('secretary'));
    }
}
