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

    public function test_it_returns_empty_items_when_user_has_no_active_memberships(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/my-circles');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'My circles fetched successfully.')
            ->assertJsonPath('data.items', []);
    }

    public function test_it_returns_only_current_users_active_memberships_with_nested_circle_data(): void
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
            'type' => 'private',
            'founder_user_id' => $founder->id,
            'director_user_id' => $director->id,
            'meeting_mode' => 'online',
            'meeting_frequency' => 'monthly',
        ]);

        $expiredCircle = Circle::create([
            'name' => 'Expired Circle',
            'slug' => 'expired-circle-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'type' => 'private',
            'founder_user_id' => $founder->id,
            'director_user_id' => $director->id,
        ]);

        $leftCircle = Circle::create([
            'name' => 'Left Circle',
            'slug' => 'left-circle-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'type' => 'public',
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
            ->assertJsonPath('data.items.0.circle.id', $activeCircle->id)
            ->assertJsonPath('data.items.0.circle.name', 'Active Circle')
            ->assertJsonPath('data.items.0.membership_started_at', $activeMembership->paid_starts_at?->toJSON())
            ->assertJsonPath('data.items.0.is_expired', false)
            ->assertJsonPath('data.items.1.circle.id', $expiredCircle->id)
            ->assertJsonPath('data.items.1.is_expired', true);

        Carbon::setTestNow();
    }
}
