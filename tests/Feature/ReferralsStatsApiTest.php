<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Referral;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferralsStatsApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    protected function createSchema(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('referrals');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('designation')->nullable();
            $table->string('profile_photo_url')->nullable();
            $table->string('membership_status')->nullable();
            $table->string('public_profile_slug')->nullable();
            $table->string('city_of_residence')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('referrals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('from_user_id');
            $table->uuid('to_user_id');
            $table->string('referral_type')->nullable();
            $table->date('referral_date')->nullable();
            $table->string('referral_of')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->integer('hot_value')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_stats_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/referrals/stats');
        $response->assertStatus(401);
    }

    public function test_stats_for_user_with_no_referrals(): void
    {
        $user = User::create([
            'id' => '11111111-1111-1111-1111-111111111111',
            'display_name' => 'User One',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/referrals/stats');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => null,
                'data' => [
                    'counts' => [
                        'referrals_given' => 0,
                        'referrals_received' => 0,
                        'total_referrals' => 0,
                    ],
                    'referrals_given' => [
                        'data' => [],
                        'meta' => [
                            'current_page' => 1,
                            'per_page' => 15,
                            'total' => 0,
                            'last_page' => 1,
                        ],
                    ],
                    'referrals_received' => [
                        'data' => [],
                        'meta' => [
                            'current_page' => 1,
                            'per_page' => 15,
                            'total' => 0,
                            'last_page' => 1,
                        ],
                    ],
                ],
            ]);
    }

    public function test_stats_with_given_and_received_referrals_and_pagination(): void
    {
        $user1 = User::create([
            'id' => '11111111-1111-1111-1111-111111111111',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'display_name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '123456',
            'company_name' => 'Acme Inc',
            'designation' => 'CEO',
            'profile_photo_url' => 'http://photo.url/john',
        ]);

        $user2 = User::create([
            'id' => '22222222-2222-2222-2222-222222222222',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'display_name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'phone' => '654321',
            'company_name' => 'Beta LLC',
            'designation' => 'CTO',
            'profile_photo_url' => 'http://photo.url/jane',
        ]);

        // User 1 gives 2 active referrals to User 2
        $ref1 = Referral::create([
            'id' => '33333333-3333-3333-3333-333333333333',
            'from_user_id' => $user1->id,
            'to_user_id' => $user2->id,
            'referral_type' => 'business',
            'referral_of' => 'Acme Project',
        ]);
        $ref1->created_at = now()->subMinutes(5);
        $ref1->save();

        $ref2 = Referral::create([
            'id' => '44444444-4444-4444-4444-444444444444',
            'from_user_id' => $user1->id,
            'to_user_id' => $user2->id,
            'referral_type' => 'service',
            'referral_of' => 'Beta Project',
        ]);
        $ref2->created_at = now();
        $ref2->save();

        // User 1 receives 1 active referral from User 2
        Referral::create([
            'id' => '55555555-5555-5555-5555-555555555555',
            'from_user_id' => $user2->id,
            'to_user_id' => $user1->id,
            'referral_type' => 'business',
            'referral_of' => 'Gamma Project',
        ]);

        // Deleted referral should not be counted
        $deletedReferral = Referral::create([
            'id' => '66666666-6666-6666-6666-666666666666',
            'from_user_id' => $user1->id,
            'to_user_id' => $user2->id,
            'referral_type' => 'business',
        ]);
        $deletedReferral->is_deleted = true;
        $deletedReferral->save();

        Sanctum::actingAs($user1);

        // Fetch with per_page = 1
        $response = $this->getJson('/api/v1/referrals/stats?per_page=1');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => null,
                'data' => [
                    'counts' => [
                        'referrals_given' => 2,
                        'referrals_received' => 1,
                        'total_referrals' => 3,
                    ],
                    'referrals_given' => [
                        'meta' => [
                            'current_page' => 1,
                            'per_page' => 1,
                            'total' => 2,
                            'last_page' => 2,
                        ],
                    ],
                    'referrals_received' => [
                        'meta' => [
                            'current_page' => 1,
                            'per_page' => 1,
                            'total' => 1,
                            'last_page' => 1,
                        ],
                    ],
                ],
            ]);

        // Verify the user details structure inside data list items
        $data = $response->json('data');
        $this->assertCount(1, $data['referrals_given']['data']);
        $this->assertCount(1, $data['referrals_received']['data']);

        $givenItem = $data['referrals_given']['data'][0];
        $this->assertEquals('Beta Project', $givenItem['title']);
        $this->assertEquals('john@example.com', $givenItem['given_by_user']['email']);
        $this->assertEquals('Jane Smith', $givenItem['received_by_user']['display_name']);
        $this->assertEquals('Beta LLC', $givenItem['received_by_user']['company_name']);
    }

    public function test_stats_by_user_not_found(): void
    {
        $user = User::create([
            'id' => '11111111-1111-1111-1111-111111111111',
            'display_name' => 'User One',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/referrals/stats/99999999-9999-9999-9999-999999999999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'User not found',
                'data' => null,
            ]);
    }

    public function test_stats_by_user_success(): void
    {
        $user1 = User::create([
            'id' => '11111111-1111-1111-1111-111111111111',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'display_name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '123456',
            'company_name' => 'Acme Inc',
            'designation' => 'CEO',
            'profile_photo_url' => 'http://photo.url/john',
        ]);

        $user2 = User::create([
            'id' => '22222222-2222-2222-2222-222222222222',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'display_name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'phone' => '654321',
            'company_name' => 'Beta LLC',
            'designation' => 'CTO',
            'profile_photo_url' => 'http://photo.url/jane',
        ]);

        // User 1 gives 2 active referrals to User 2
        $ref1 = Referral::create([
            'id' => '33333333-3333-3333-3333-333333333333',
            'from_user_id' => $user1->id,
            'to_user_id' => $user2->id,
            'referral_type' => 'business',
            'referral_of' => 'Acme Project',
        ]);
        $ref1->created_at = now()->subMinutes(5);
        $ref1->save();

        $ref2 = Referral::create([
            'id' => '44444444-4444-4444-4444-444444444444',
            'from_user_id' => $user1->id,
            'to_user_id' => $user2->id,
            'referral_type' => 'service',
            'referral_of' => 'Beta Project',
        ]);
        $ref2->created_at = now();
        $ref2->save();

        // User 1 receives 1 active referral from User 2
        Referral::create([
            'id' => '55555555-5555-5555-5555-555555555555',
            'from_user_id' => $user2->id,
            'to_user_id' => $user1->id,
            'referral_type' => 'business',
            'referral_of' => 'Gamma Project',
        ]);

        // Deleted referral should not be counted
        $deletedReferral = Referral::create([
            'id' => '66666666-6666-6666-6666-666666666666',
            'from_user_id' => $user1->id,
            'to_user_id' => $user2->id,
            'referral_type' => 'business',
        ]);
        $deletedReferral->is_deleted = true;
        $deletedReferral->save();

        // Eager load as another user to view stats for User 1
        $watcher = User::create([
            'id' => '88888888-8888-8888-8888-888888888888',
            'display_name' => 'Watcher User',
        ]);

        Sanctum::actingAs($watcher);

        $response = $this->getJson('/api/v1/referrals/stats/' . $user1->id . '?per_page=1');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => null,
                'data' => [
                    'user' => [
                        'id' => '11111111-1111-1111-1111-111111111111',
                        'display_name' => 'John Doe',
                    ],
                    'counts' => [
                        'referrals_given' => 2,
                        'referrals_received' => 1,
                        'total_referrals' => 3,
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(1, $data['referrals_given']['data']);
        $this->assertCount(1, $data['referrals_received']['data']);

        $givenItem = $data['referrals_given']['data'][0];
        $this->assertEquals('Beta Project', $givenItem['referral_of']);
        $this->assertEquals('john@example.com', $givenItem['given_by_user']['email']);
        $this->assertEquals('Jane Smith', $givenItem['received_by_user']['display_name']);
        $this->assertEquals('Beta LLC', $givenItem['received_by_user']['company_name']);
    }
}
