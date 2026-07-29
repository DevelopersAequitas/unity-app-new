<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ImpactApiTest extends TestCase
{
    public function test_life_impact_endpoint_route_is_registered(): void
    {
        $user = new User(['id' => '00000000-0000-0000-0000-000000000001']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/life-impact', []);

        $response->assertStatus(422);
    }

    public function test_impacts_endpoint_route_is_registered(): void
    {
        $user = new User(['id' => '00000000-0000-0000-0000-000000000001']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/impacts', []);

        $response->assertStatus(422);
    }

    public function test_story_to_share_and_additional_remarks_are_optional(): void
    {
        $user = new User(['id' => '00000000-0000-0000-0000-000000000001']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/life-impact', [
                'date' => '2026-07-30',
            ]);

        $response->assertStatus(422)
            ->assertJsonMissingValidationErrors(['story_to_share', 'additional_remarks']);
    }
}
