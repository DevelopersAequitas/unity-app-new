<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\BusinessDeal;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessDealsStatsApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    protected function createSchema(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('business_deals');

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
            $table->string('profile_photo')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('business_deals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('from_user_id');
            $table->uuid('to_user_id');
            $table->date('deal_date')->nullable();
            $table->decimal('deal_amount', 15, 2)->nullable();
            $table->string('business_type')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_stats_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/users/b5b7e465-fd81-494f-9ede-2dd0286e2b5c/business-deals/stats');
        $response->assertStatus(401);
    }

    public function test_stats_user_not_found(): void
    {
        $user = User::create([
            'id' => '11111111-1111-1111-1111-111111111111',
            'display_name' => 'Tester',
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/users/6c93d927-1219-47f0-84df-f43ef0c4eb32/business-deals/stats');
        $response->assertStatus(404);
    }

    public function test_stats_success(): void
    {
        $user1 = User::create([
            'id' => 'b5b7e465-fd81-494f-9ede-2dd0286e2b5c',
            'first_name' => 'Vinit',
            'last_name' => 'Chavda',
            'display_name' => 'Vinit Chavda',
            'email' => 'vinitchavda222@gmail.com',
            'phone' => '9904978744',
            'company_name' => 'Aequitas Information Technology Pvt Ltd',
            'designation' => 'Technical Consultant',
            'profile_photo_url' => null,
        ]);

        $user2 = User::create([
            'id' => '6c93d927-1219-47f0-84df-f43ef0c4eb32',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'display_name' => 'Jane Smith',
        ]);

        // Given 2 deals
        $deal1 = new BusinessDeal([
            'from_user_id' => $user1->id,
            'to_user_id' => $user2->id,
            'deal_amount' => 5000,
            'deal_date' => '2026-07-01',
        ]);
        $deal1->id = '33333333-3333-3333-3333-333333333333';
        $deal1->save();

        $deal2 = new BusinessDeal([
            'from_user_id' => $user1->id,
            'to_user_id' => $user2->id,
            'deal_amount' => 10000,
            'deal_date' => '2026-07-01',
        ]);
        $deal2->id = '44444444-4444-4444-4444-444444444444';
        $deal2->save();

        // Received 1 deal
        $deal3 = new BusinessDeal([
            'from_user_id' => $user2->id,
            'to_user_id' => $user1->id,
            'deal_amount' => 20000,
            'deal_date' => '2026-07-01',
        ]);
        $deal3->id = '55555555-5555-5555-5555-555555555555';
        $deal3->save();

        // Deleted deal (should not be counted)
        $deal4 = new BusinessDeal([
            'from_user_id' => $user1->id,
            'to_user_id' => $user2->id,
            'deal_amount' => 100,
            'deal_date' => '2026-07-01',
        ]);
        $deal4->id = '66666666-6666-6666-6666-666666666666';
        $deal4->is_deleted = true;
        $deal4->save();

        Sanctum::actingAs($user1);

        $response = $this->getJson('/api/v1/users/b5b7e465-fd81-494f-9ede-2dd0286e2b5c/business-deals/stats');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => 'b5b7e465-fd81-494f-9ede-2dd0286e2b5c',
                        'first_name' => 'Vinit',
                        'last_name' => 'Chavda',
                        'display_name' => 'Vinit Chavda',
                        'email' => 'vinitchavda222@gmail.com',
                        'phone' => '9904978744',
                        'company_name' => 'Aequitas Information Technology Pvt Ltd',
                        'designation' => 'Technical Consultant',
                        'profile_photo_url' => null,
                    ],
                    'business_deals_given' => 2,
                    'business_deals_received' => 1,
                    'total_business_deals' => 3,
                ],
            ]);
    }

    public function test_list_success(): void
    {
        $user1 = User::create([
            'id' => 'b5b7e465-fd81-494f-9ede-2dd0286e2b5c',
            'display_name' => 'Vinit Chavda',
        ]);

        $user2 = User::create([
            'id' => '6c93d927-1219-47f0-84df-f43ef0c4eb32',
            'display_name' => 'Jane Smith',
        ]);

        $deal = new BusinessDeal([
            'from_user_id' => $user1->id,
            'to_user_id' => $user2->id,
            'deal_amount' => 5000,
            'deal_date' => '2026-07-01',
        ]);
        $deal->id = '33333333-3333-3333-3333-333333333333';
        $deal->save();

        Sanctum::actingAs($user1);

        $response = $this->getJson('/api/v1/users/b5b7e465-fd81-494f-9ede-2dd0286e2b5c/business-deals');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'business_deals_given' => 1,
                    'business_deals_received' => 0,
                    'total_business_deals' => 1,
                    'items' => [
                        [
                            'id' => '33333333-3333-3333-3333-333333333333',
                            'from_user_id' => 'b5b7e465-fd81-494f-9ede-2dd0286e2b5c',
                            'to_user_id' => '6c93d927-1219-47f0-84df-f43ef0c4eb32',
                            'deal_amount' => 5000,
                            'from_user' => [
                                'id' => 'b5b7e465-fd81-494f-9ede-2dd0286e2b5c',
                            ],
                            'to_user' => [
                                'id' => '6c93d927-1219-47f0-84df-f43ef0c4eb32',
                            ],
                        ],
                    ],
                ],
            ]);
    }
}
