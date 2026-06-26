<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LimitedUserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_limited_users_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/members/limited');
        $response->assertStatus(401);
    }

    public function test_limited_users_endpoint_returns_only_active_members_with_limited_data(): void
    {
        // 1. Create active user
        $activeUser = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'display_name' => 'John Doe',
            'company_name' => 'Acme Corp',
            'city' => 'New York',
            'life_impacted_count' => 42,
            'status' => 'active',
        ]);

        // 2. Create inactive user
        $inactiveUser = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'status' => 'inactive',
        ]);

        // Authenticate
        Sanctum::actingAs($activeUser);

        $response = $this->getJson('/api/v1/members/limited');

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'profile_photo_image',
                    'city',
                    'business_name',
                    'total_life_impact',
                    'company_name',
                ]
            ]
        ]);

        $data = $response->json('data');

        // Verify that inactive user is not returned
        $this->assertCount(1, $data);
        $this->assertSame($activeUser->id, $data[0]['id']);
        $this->assertSame('John Doe', $data[0]['name']);
        $this->assertSame('New York', $data[0]['city']);
        $this->assertSame('Acme Corp', $data[0]['business_name']);
        $this->assertSame('Acme Corp', $data[0]['company_name']);
        $this->assertSame(42, $data[0]['total_life_impact']);
    }
}
