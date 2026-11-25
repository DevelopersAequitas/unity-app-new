<?php

declare(strict_types=1);

namespace Tests\Feature\Leader;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use App\Shared\Services\UserRoleResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Batch2RoleResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_founder_and_committee_leader_resolve_correctly(): void
    {
        $resolver = app(UserRoleResolverService::class);

        $gf = User::factory()->create(['role' => 'global_founder']);
        $gfResolved = $resolver->resolveUserRole($gf);
        $this->assertSame('globalFounder', $gfResolved['role']);
        $this->assertSame('Global Scope', $gfResolved['regional_scope']);
        $this->assertTrue($gfResolved['is_leader']);

        $cl = User::factory()->create(['role' => 'committee_leader']);
        $clResolved = $resolver->resolveUserRole($cl);
        $this->assertSame('committeeLeader', $clResolved['role']);
        $this->assertSame('Own Circle', $clResolved['regional_scope']);
        $this->assertTrue($clResolved['is_leader']);
    }

    public function test_pending_rejected_and_departed_circle_memberships_do_not_grant_leadership(): void
    {
        $resolver = app(UserRoleResolverService::class);
        $circle = Circle::query()->create(['id' => (string) Str::uuid(), 'name' => 'Alpha Circle']);

        $pendingUser = User::factory()->create();
        CircleMember::query()->create([
            'id' => (string) Str::uuid(),
            'circle_id' => $circle->id,
            'user_id' => $pendingUser->id,
            'role' => 'circle_director',
            'status' => 'pending',
        ]);

        $departedUser = User::factory()->create();
        CircleMember::query()->create([
            'id' => (string) Str::uuid(),
            'circle_id' => $circle->id,
            'user_id' => $departedUser->id,
            'role' => 'circle_founder',
            'status' => 'approved',
            'left_at' => now()->subDay(),
        ]);

        $this->assertFalse($resolver->isLeader($pendingUser));
        $this->assertFalse($resolver->isLeader($departedUser));
    }
}
