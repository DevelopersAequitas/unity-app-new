<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileIntroVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_update_intro_video_id_and_receive_it(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $videoUuid = '019e1c11-a32e-709e-9694-a887466c6cfc';

        // 1. Update intro_video_id via PUT /api/v1/profile
        $response = $this->putJson('/api/v1/profile', [
            'intro_video_id' => $videoUuid,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.intro_video_id', $videoUuid)
            ->assertJsonPath('data.intro_video_url', url('/api/v1/files/'.$videoUuid))
            ->assertJsonPath('data.profile_video_id', $videoUuid)
            ->assertJsonPath('data.profile_video_url', url('/api/v1/files/'.$videoUuid));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'profile_video_id' => $videoUuid,
        ]);

        // 2. Fetch profile via GET /api/v1/profile and verify fields
        $response = $this->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJsonPath('data.intro_video_id', $videoUuid)
            ->assertJsonPath('data.intro_video_url', url('/api/v1/files/'.$videoUuid))
            ->assertJsonPath('data.profile_video_id', $videoUuid)
            ->assertJsonPath('data.profile_video_url', url('/api/v1/files/'.$videoUuid));
    }
}
