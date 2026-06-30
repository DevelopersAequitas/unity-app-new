<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContactVisibilityProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_visibility_valid_values_are_accepted_persisted_and_returned(): void
    {
        $user = User::factory()->create(['contact_visibility' => 'everyone']);
        Sanctum::actingAs($user);

        foreach (['everyone', 'connected_only', 'circle_only', 'hidden'] as $visibility) {
            $this->patchJson('/api/v1/profile', ['contact_visibility' => $visibility])
                ->assertOk()
                ->assertJsonPath('data.contact_visibility', $visibility);

            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'contact_visibility' => $visibility,
            ]);
        }
    }

    public function test_contact_visibility_invalid_value_is_rejected(): void
    {
        $user = User::factory()->create(['contact_visibility' => 'everyone']);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/profile', ['contact_visibility' => 'friends_only'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contact_visibility']);
    }

    public function test_legacy_contact_visibility_values_are_normalized_for_backward_compatibility(): void
    {
        $user = User::factory()->create(['contact_visibility' => 'everyone']);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/profile', ['contact_visibility' => 'connections'])
            ->assertOk()
            ->assertJsonPath('data.contact_visibility', 'connected_only');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'contact_visibility' => 'connected_only',
        ]);
    }

    public function test_profile_api_returns_current_contact_visibility(): void
    {
        $user = User::factory()->create(['contact_visibility' => 'circle_only']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.contact_visibility', 'circle_only');
    }
}
