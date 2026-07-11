<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class MyCirclesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $roleKeys = [
            'member',
            'circle_founder',
            'circle_director',
            'industry_director',
            'ded',
            'eed',
            'chair',
            'vice_chair',
            'secretary',
            'committee_leader',
        ];

        foreach ($roleKeys as $key) {
            $role = \App\Models\Role::where('key', $key)->first();
            if (! $role) {
                $role = new \App\Models\Role;
                $role->id = (string) \Illuminate\Support\Str::uuid();
                $role->key = $key;
                $role->name = ucfirst(str_replace('_', ' ', $key));
                $role->save();
            }
        }
    }

    public function test_it_returns_empty_items_when_user_has_no_active_memberships(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/my-circles');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'My circles fetched successfully.')
            ->assertJsonPath('data.items', []);
    }

    public function test_it_returns_only_current_users_active_memberships(): void
    {
        Carbon::setTestNow('2026-03-28 12:00:00');

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $founder = User::factory()->create();
        $director = User::factory()->create();

        $activeCircle = Circle::create([
            'name' => 'Active Circle',
            'slug' => 'active-circle-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'circle_founder_user_id' => $founder->id,
        ]);

        $expiredCircle = Circle::create([
            'name' => 'Expired Circle',
            'slug' => 'expired-circle-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'circle_founder_user_id' => $founder->id,
        ]);

        $leftCircle = Circle::create([
            'name' => 'Left Circle',
            'slug' => 'left-circle-'.Str::lower(Str::random(6)),
            'status' => 'active',
        ]);

        $activeMembership = CircleMember::create([
            'circle_id' => $activeCircle->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'approved',
            'joined_at' => now()->subDays(5),
            'joined_via' => 'payment',
            'joined_via_payment' => true,
            'payment_id' => 'pay_123',
            'payment_status' => 'paid',
            'billing_term' => 'yearly',
            'paid_at' => now()->subDays(5),
            'paid_starts_at' => now()->subDays(5),
            'paid_ends_at' => now()->addMonth(),
            'zoho_subscription_id' => 'sub_123',
            'zoho_addon_code' => 'addon_123',
            'meta' => ['source' => 'test'],
        ]);

        CircleMember::create([
            'circle_id' => $expiredCircle->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'approved',
            'joined_at' => now()->subMonths(2),
            'paid_starts_at' => now()->subMonths(2),
            'paid_ends_at' => now()->subDay(),
        ]);

        CircleMember::create([
            'circle_id' => $leftCircle->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'approved',
            'joined_at' => now()->subDays(10),
            'left_at' => now()->subDays(1),
        ]);

        CircleMember::create([
            'circle_id' => $activeCircle->id,
            'user_id' => $otherUser->id,
            'role' => 'member',
            'status' => 'approved',
            'joined_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/my-circles');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.membership_id', $activeMembership->id)
            ->assertJsonPath('data.items.0.circle_id', $activeCircle->id)
            ->assertJsonPath('data.items.0.circle_name', 'Active Circle')
            ->assertJsonPath('data.items.0.role', 'member')
            ->assertJsonPath('data.items.0.status', 'approved')
            ->assertJsonPath('data.items.0.joined_at', $activeMembership->joined_at ? $activeMembership->joined_at->toIso8601String() : null)
            ->assertJsonPath('data.items.0.type', $activeCircle->type)
            ->assertJsonPath('data.items.1.circle_id', $expiredCircle->id)
            ->assertJsonPath('data.items.1.circle_name', 'Expired Circle');

        Carbon::setTestNow();
    }
}
