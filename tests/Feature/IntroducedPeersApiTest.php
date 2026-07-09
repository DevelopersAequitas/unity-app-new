<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IntroducedPeersApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/profile/introduced-peers')
            ->assertUnauthorized();

        $this->getJson('/api/v1/profile/introducer')
            ->assertUnauthorized();

        $this->postJson('/api/v1/profile/introduced-peers', ['peer_id' => 'some-uuid'])
            ->assertUnauthorized();
    }

    public function test_get_profile_introducer_returns_null_when_none(): void
    {
        $user = User::factory()->create(['introduced_by' => null]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile/introducer');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'No introducer set for this peer.');
    }

    public function test_can_get_profile_introducer(): void
    {
        $introducer = User::factory()->create();
        $user = User::factory()->create(['introduced_by' => $introducer->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile/introducer');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $introducer->id);
    }

    public function test_can_introduce_a_peer_and_list_them(): void
    {
        $user = User::factory()->create([
            'members_introduced_count' => 0,
        ]);
        $peer = User::factory()->create([
            'introduced_by' => null,
        ]);

        Sanctum::actingAs($user);

        // 1. POST to introduce the peer
        $response = $this->postJson('/api/v1/profile/introduced-peers', [
            'peer_id' => $peer->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $peer->id);

        $peer->refresh();
        $user->refresh();

        $this->assertSame($user->id, $peer->introduced_by);
        $this->assertEquals(1, $user->members_introduced_count);

        // 2. GET to list introduced peers
        $listResponse = $this->getJson('/api/v1/profile/introduced-peers');
        $listResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $peer->id);
    }

    public function test_cannot_introduce_self(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/profile/introduced-peers', [
            'peer_id' => $user->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You cannot introduce yourself.');
    }

    public function test_cannot_introduce_peer_already_introduced(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $peer = User::factory()->create([
            'introduced_by' => $user1->id,
        ]);

        Sanctum::actingAs($user2);

        $response = $this->postJson('/api/v1/profile/introduced-peers', [
            'peer_id' => $peer->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This peer has already been introduced by another member.');
    }
}
