<?php

namespace Tests\Feature;

use App\Models\ContactPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserContactsPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_returns_true_when_user_has_no_contacts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/user/contacts/permission');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => $user->id,
                    'contacts_allowed' => true,
                    'android_contacts_permission' => 'yes',
                    'ios_contacts_permission' => 'yes',
                ]
            ]);
    }

    public function test_permission_returns_false_when_user_has_existing_contacts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create a contact post for this user
        ContactPost::create([
            'user_id' => $user->id,
            'full_name' => 'John Doe',
            'phone' => '1234567890',
        ]);

        $response = $this->getJson('/api/v1/user/contacts/permission');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => $user->id,
                    'contacts_allowed' => false,
                    'android_contacts_permission' => 'no',
                    'ios_contacts_permission' => 'no',
                ]
            ]);
    }
}
