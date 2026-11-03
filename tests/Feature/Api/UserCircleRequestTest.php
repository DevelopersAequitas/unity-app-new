<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\UserCircleRequest;
use App\Models\UserCircleRequestNominee;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserCircleRequestTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private User $appPeer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'phone'        => '9999888877',
            'email'        => 'founder@test.com',
            'first_name'   => 'Founder',
            'last_name'    => 'Test',
            'display_name' => 'Founder Test',
        ]);

        $this->appPeer = User::factory()->create([
            'phone'         => '9825012345',
            'email'         => 'rajesh@example.com',
            'first_name'    => 'Rajesh',
            'last_name'     => 'Patel',
            'display_name'  => 'Rajesh Patel',
            'company_name'  => 'Patel Textiles',
        ]);
    }

    public function test_can_submit_circle_request_with_less_than_40_peers_and_different_sources(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'circle_name'        => 'Surat Diamond & Textile Circle',
            'city'               => 'Surat',
            'country'            => 'India',
            'meeting_mode'       => 'hybrid',
            'meeting_frequency'  => 'monthly',
            'description'        => 'A collaborative business growth circle.',
            'recommended_peers'  => [
                [
                    'source'  => 'app',
                    'peer_id' => (string) $this->appPeer->id,
                    'name'    => 'Rajesh Patel',
                    // phone and email intentionally omitted to test auto-resolution
                ],
                [
                    'source' => 'contacts',
                    'name'   => 'Amit Shah',
                    'phone'  => '+919879011223',
                    'email'  => 'amit.shah@gmail.com',
                ],
                [
                    'source' => 'manual',
                    'name'   => 'Pooja Mehta',
                    'phone'  => '9988776655',
                    'email'  => null,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/user-circle-requests', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.recommended_peers_count', 3)
            ->assertJsonPath('data.minimum_required', 40)
            ->assertJsonPath('data.has_minimum_recommended_peers', false)
            ->assertJsonPath('data.remaining_recommended_peers', 37);

        $this->assertDatabaseHas('user_circle_requests', [
            'user_id'     => $this->user->id,
            'circle_name' => 'Surat Diamond & Textile Circle',
            'status'      => 'pending',
        ]);

        // Verify app peer was auto-resolved with phone & email
        $this->assertDatabaseHas('user_circle_request_nominees', [
            'nominee_user_id' => (string) $this->appPeer->id,
            'source'          => 'app',
            'phone'           => $this->appPeer->phone,
            'email'           => $this->appPeer->email,
        ]);

        // Verify contacts and manual peers
        $this->assertDatabaseHas('user_circle_request_nominees', [
            'name'   => 'Amit Shah',
            'source' => 'contacts',
        ]);

        $this->assertDatabaseHas('user_circle_request_nominees', [
            'name'   => 'Pooja Mehta',
            'source' => 'manual',
        ]);
    }

    public function test_self_recommendation_validations_and_no_undefined_variable(): void
    {
        Sanctum::actingAs($this->user);

        // Self email
        $payloadEmail = [
            'circle_name'       => 'Test Circle',
            'recommended_peers' => [
                [
                    'source' => 'manual',
                    'name'   => 'Self Email Test',
                    'phone'  => '9876543210',
                    'email'  => 'founder@test.com', // same as $this->user->email
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/user-circle-requests', $payloadEmail);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recommended_peers.0.email']);

        // Self phone
        $payloadPhone = [
            'circle_name'       => 'Test Circle',
            'recommended_peers' => [
                [
                    'source' => 'manual',
                    'name'   => 'Self Phone Test',
                    'phone'  => '9999888877', // same as $this->user->phone
                    'email'  => 'other@test.com',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/user-circle-requests', $payloadPhone);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recommended_peers.0.phone']);

        // Self peer_id
        $payloadPeerId = [
            'circle_name'       => 'Test Circle',
            'recommended_peers' => [
                [
                    'source'  => 'app',
                    'peer_id' => (string) $this->user->id,
                    'name'    => 'Self Peer Test',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/user-circle-requests', $payloadPeerId);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recommended_peers.0.peer_id']);
    }

    public function test_update_recommended_peers_works_with_flexible_counts(): void
    {
        Sanctum::actingAs($this->user);

        $circleRequest = UserCircleRequest::create([
            'user_id'                => $this->user->id,
            'circle_name'            => 'Initial Circle',
            'status'                 => UserCircleRequest::STATUS_PENDING,
            'minimum_peers_required' => 40,
        ]);

        $payload = [
            'recommended_peers' => [
                [
                    'source'  => 'app',
                    'peer_id' => (string) $this->appPeer->id,
                    'name'    => 'Rajesh Patel',
                ],
            ],
        ];

        $response = $this->putJson("/api/v1/user-circle-requests/{$circleRequest->id}/recommended-peers", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.recommended_peers_count', 1)
            ->assertJsonPath('data.minimum_required', 40)
            ->assertJsonPath('data.has_minimum_recommended_peers', false)
            ->assertJsonPath('data.remaining_recommended_peers', 39);

        // Verify GET /api/v1/user-circle-requests/{id} returns resolved fields
        $showResponse = $this->getJson("/api/v1/user-circle-requests/{$circleRequest->id}");
        $showResponse->assertStatus(200)
            ->assertJsonPath('data.recommended_peers_count', 1)
            ->assertJsonPath('data.has_minimum_recommended_peers', false)
            ->assertJsonPath('data.remaining_recommended_peers', 39)
            ->assertJsonPath('data.recommended_peers.0.source', 'app')
            ->assertJsonPath('data.recommended_peers.0.has_joined_unity', true)
            ->assertJsonPath('data.recommended_peers.0.unity_join_status', 'joined');
    }
}
