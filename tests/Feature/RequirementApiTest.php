<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Requirement;
use App\Models\RequirementInterest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RequirementApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->createSchema();
    }

    protected function createSchema(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('requirements');
        Schema::dropIfExists('requirement_interests');
        Schema::dropIfExists('posts');

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
            $table->uuid('profile_photo_file_id')->nullable();
            $table->bigInteger('coins_balance')->default(0);
            $table->integer('life_impacted_count')->default(0);
            $table->string('status')->default('active');
            $table->string('membership_status')->default('premium');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('requirements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('subject');
            $table->text('description')->nullable();
            $table->json('media')->nullable();
            $table->json('region_filter')->nullable();
            $table->json('category_filter')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('requirement_interests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('requirement_id');
            $table->uuid('user_id');
            $table->string('source')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('circle_id')->nullable();
            $table->text('content_text')->nullable();
            $table->json('media')->nullable();
            $table->json('tags')->nullable();
            $table->string('visibility')->default('public');
            $table->string('moderation_status')->default('pending');
            $table->boolean('sponsored')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->string('source_type')->nullable();
            $table->uuid('source_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_requirements_summary_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/requirements/summary');
        $response->assertStatus(401);
    }

    public function test_requirements_summary_for_user_with_no_requirements(): void
    {
        $user = User::create([
            'id' => '11111111-1111-1111-1111-111111111111',
            'first_name' => 'User',
            'last_name' => 'One',
            'display_name' => 'User One',
            'email' => 'user1@example.com',
            'phone' => '1234567890',
            'company_name' => 'Test Company',
            'designation' => 'QA',
            'profile_photo_url' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/requirements/summary');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => null,
                'data' => [
                    'user' => [
                        'id' => '11111111-1111-1111-1111-111111111111',
                        'first_name' => 'User',
                        'last_name' => 'One',
                        'display_name' => 'User One',
                        'email' => 'user1@example.com',
                        'phone' => '1234567890',
                        'company_name' => 'Test Company',
                        'designation' => 'QA',
                        'profile_photo_url' => null,
                    ],
                    'total_requirements' => 0,
                    'given_requirements' => 0,
                    'received_requirements' => 0,
                ],
            ]);
    }

    public function test_requirements_summary_with_given_and_received_requirements(): void
    {
        $user1 = User::create([
            'id' => '11111111-1111-1111-1111-111111111111',
            'first_name' => 'User',
            'last_name' => 'One',
            'display_name' => 'User One',
            'email' => 'user1@example.com',
            'phone' => '1234567890',
            'company_name' => 'Test Company',
            'designation' => 'QA',
            'profile_photo_url' => null,
        ]);

        $user2 = User::create([
            'id' => '22222222-2222-2222-2222-222222222222',
            'display_name' => 'User Two',
        ]);

        // User 1 creates 3 requirements
        $req1 = Requirement::create([
            'id' => '33333333-3333-3333-3333-333333333333',
            'user_id' => $user1->id,
            'subject' => 'Req 1',
            'status' => 'open',
        ]);
        Requirement::create([
            'id' => '44444444-4444-4444-4444-444444444444',
            'user_id' => $user1->id,
            'subject' => 'Req 2',
            'status' => 'open',
        ]);
        Requirement::create([
            'id' => '55555555-5555-5555-5555-555555555555',
            'user_id' => $user1->id,
            'subject' => 'Req 3',
            'status' => 'completed',
        ]);

        // Acting as User 1
        Sanctum::actingAs($user1);

        $response = $this->getJson('/api/v1/requirements/summary');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => null,
                'data' => [
                    'user' => [
                        'id' => '11111111-1111-1111-1111-111111111111',
                    ],
                    'total_requirements' => 3,
                    'given_requirements' => 3,
                    'received_requirements' => 0,
                ],
            ]);
    }

    public function test_requirements_summary_with_specific_user_id(): void
    {
        $user1 = User::create([
            'id' => '11111111-1111-1111-1111-111111111111',
            'display_name' => 'User One',
        ]);

        $user2 = User::create([
            'id' => '22222222-2222-2222-2222-222222222222',
            'first_name' => 'User',
            'last_name' => 'Two',
            'display_name' => 'User Two',
            'email' => 'user2@example.com',
            'phone' => '0987654321',
            'company_name' => 'User 2 Corp',
            'designation' => 'Lead',
            'profile_photo_url' => 'https://example.com/photo.jpg',
        ]);

        // User 2 creates 2 requirements
        Requirement::create([
            'id' => '66666666-6666-6666-6666-666666666666',
            'user_id' => $user2->id,
            'subject' => 'Req 4',
            'status' => 'open',
        ]);
        Requirement::create([
            'id' => '77777777-7777-7777-7777-777777777777',
            'user_id' => $user2->id,
            'subject' => 'Req 5',
            'status' => 'open',
        ]);

        // Acting as User 1, but requesting User 2's summary
        Sanctum::actingAs($user1);

        $response = $this->getJson('/api/v1/requirements/summary/' . $user2->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => null,
                'data' => [
                    'user' => [
                        'id' => '22222222-2222-2222-2222-222222222222',
                        'first_name' => 'User',
                        'last_name' => 'Two',
                        'display_name' => 'User Two',
                        'email' => 'user2@example.com',
                        'phone' => '0987654321',
                        'company_name' => 'User 2 Corp',
                        'designation' => 'Lead',
                        'profile_photo_url' => 'https://example.com/photo.jpg',
                    ],
                    'total_requirements' => 2,
                    'given_requirements' => 2,
                    'received_requirements' => 0,
                ],
            ]);
    }

    public function test_requirements_summary_with_invalid_user_id(): void
    {
        $user1 = User::create([
            'id' => '11111111-1111-1111-1111-111111111111',
            'display_name' => 'User One',
        ]);

        Sanctum::actingAs($user1);

        // A valid UUID that does not exist in our database
        $response = $this->getJson('/api/v1/requirements/summary/99999999-9999-9999-9999-999999999999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'User not found',
                'data' => null,
            ]);
    }
}
