<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTimezoneApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_their_timezone(): void
    {
        $user = User::factory()->create([
            'timezone' => 'Europe/London',
            'first_name' => 'Original',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/profile/timezone', [
            'timezone' => 'Asia/Kolkata',
            'first_name' => 'Changed',
        ]);

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Timezone updated successfully.',
                'data' => [
                    'timezone' => 'Asia/Kolkata',
                ],
            ]);

        $user->refresh();

        $this->assertSame('Asia/Kolkata', $user->timezone);
        $this->assertSame('Original', $user->first_name);
    }

    public function test_timezone_update_rejects_invalid_timezones(): void
    {
        $user = User::factory()->create([
            'timezone' => 'Europe/London',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/profile/timezone', [
            'timezone' => 'Invalid/Timezone',
        ]);

        $response->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'message' => 'Invalid timezone.',
            ]);

        $this->assertSame('Europe/London', $user->refresh()->timezone);
    }

    public function test_timezone_update_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/profile/timezone', [
            'timezone' => 'Asia/Kolkata',
        ]);

        $response->assertUnauthorized();
    }
}
