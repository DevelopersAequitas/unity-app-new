<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Testimonial;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TestimonialApiTest extends TestCase
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
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('peer_blocks');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('coins_ledger');
        Schema::dropIfExists('life_impact_histories');
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('user_push_tokens');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
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

        Schema::create('testimonials', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('from_user_id');
            $table->uuid('to_user_id');
            $table->text('content')->nullable();
            $table->json('media')->nullable();
            $table->integer('rating')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('peer_blocks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('blocker_user_id');
            $table->uuid('blocked_user_id');
            $table->string('reason')->nullable();
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
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('coins_ledger', function (Blueprint $table): void {
            $table->uuid('transaction_id')->primary();
            $table->uuid('user_id');
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->uuid('activity_id')->nullable();
            $table->string('reference')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('life_impact_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('triggered_by_user_id')->nullable();
            $table->string('activity_type');
            $table->uuid('activity_id')->nullable();
            $table->integer('impact_value')->default(0);
            $table->integer('life_impacted')->default(0);
            $table->boolean('counted_in_total')->default(true);
            $table->string('impact_category')->nullable();
            $table->string('action_key')->nullable();
            $table->string('action_label')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type');
            $table->json('payload');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
            $table->timestamp('read_at')->nullable();
        });

        Schema::create('user_push_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('token');
            $table->string('platform');
            $table->string('device_id')->nullable();
            $table->string('app_version')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    private function createUser(string $name, array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'first_name' => strtok($name, ' ') ?: $name,
            'last_name' => trim(strstr($name, ' ') ?: ''),
            'display_name' => $name,
            'email' => Str::slug($name) . '-' . Str::random(6) . '@example.com',
            'status' => 'active',
            'membership_status' => 'premium',
            'coins_balance' => 100,
            'life_impacted_count' => 0,
        ], $attributes));
    }

    public function test_store_testimonial_validation(): void
    {
        $authUser = $this->createUser('Sender User');
        Sanctum::actingAs($authUser);

        // Required field missing
        $this->postJson('/api/v1/testimonials', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['given_to_user_id']);
    }

    public function test_store_testimonial_cannot_give_to_self(): void
    {
        $authUser = $this->createUser('Sender User');
        Sanctum::actingAs($authUser);

        $this->postJson('/api/v1/testimonials', [
            'given_to_user_id' => $authUser->id,
            'message' => 'Great work!',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You cannot give a testimonial to yourself.');
    }

    public function test_store_testimonial_success(): void
    {
        $authUser = $this->createUser('Sender User');
        $receiverUser = $this->createUser('Receiver User');
        Sanctum::actingAs($authUser);

        $response = $this->postJson('/api/v1/testimonials', [
            'given_to_user_id' => $receiverUser->id,
            'message' => 'He is an amazing developer!',
            'rating' => 5,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Testimonial saved successfully')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'given_by_user_id',
                    'given_to_user_id',
                    'message',
                    'rating',
                    'media',
                    'created_at',
                    'updated_at',
                    'given_by' => [
                        'id',
                        'display_name',
                        'company_name',
                        'designation',
                        'profile_photo_url',
                    ],
                    'given_to' => [
                        'id',
                        'display_name',
                        'company_name',
                        'designation',
                        'profile_photo_url',
                    ],
                ]
            ]);

        $this->assertDatabaseHas('testimonials', [
            'from_user_id' => $authUser->id,
            'to_user_id' => $receiverUser->id,
            'content' => 'He is an amazing developer!',
            'rating' => 5,
        ]);
    }

    public function test_store_testimonial_blocked(): void
    {
        $authUser = $this->createUser('Sender User');
        $receiverUser = $this->createUser('Receiver User');
        Sanctum::actingAs($authUser);

        // Block target user
        DB::table('peer_blocks')->insert([
            'blocker_user_id' => $receiverUser->id,
            'blocked_user_id' => $authUser->id,
            'reason' => 'Spam',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/testimonials', [
            'given_to_user_id' => $receiverUser->id,
            'message' => 'Nice peer',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You cannot interact with this peer.');
    }

    public function test_get_given_testimonials(): void
    {
        $authUser = $this->createUser('Sender User');
        $receiverUser = $this->createUser('Receiver User');
        Sanctum::actingAs($authUser);

        Testimonial::forceCreate([
            'from_user_id' => $authUser->id,
            'to_user_id' => $receiverUser->id,
            'content' => 'Awesome person!',
            'rating' => 4,
            'is_deleted' => false,
        ]);

        $this->getJson('/api/v1/testimonials/given')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_testimonials', 1)
            ->assertJsonPath('data.summary.given_by_total', 1)
            ->assertJsonPath('data.summary.given_to_total', 1)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.message', 'Awesome person!')
            ->assertJsonPath('data.items.0.given_to.display_name', 'Receiver User');
    }

    public function test_get_received_testimonials(): void
    {
        $authUser = $this->createUser('Receiver User');
        $senderUser = $this->createUser('Sender User');
        Sanctum::actingAs($authUser);

        Testimonial::forceCreate([
            'from_user_id' => $senderUser->id,
            'to_user_id' => $authUser->id,
            'content' => 'Received feedback!',
            'rating' => 5,
            'is_deleted' => false,
        ]);

        $this->getJson('/api/v1/testimonials/received')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_testimonials', 1)
            ->assertJsonPath('data.summary.given_by_total', 1)
            ->assertJsonPath('data.summary.given_to_total', 1)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.message', 'Received feedback!')
            ->assertJsonPath('data.items.0.given_by.display_name', 'Sender User');
    }

    public function test_get_user_testimonials(): void
    {
        $authUser = $this->createUser('Auth User');
        $profileUser = $this->createUser('Profile User');
        $senderUser = $this->createUser('Sender User');
        Sanctum::actingAs($authUser);

        Testimonial::forceCreate([
            'from_user_id' => $senderUser->id,
            'to_user_id' => $profileUser->id,
            'content' => 'Approved/visible testimonial!',
            'rating' => 5,
            'is_deleted' => false,
        ]);

        // Deleted testimonials should not appear
        Testimonial::forceCreate([
            'from_user_id' => $senderUser->id,
            'to_user_id' => $profileUser->id,
            'content' => 'Deleted testimonial!',
            'rating' => 3,
            'is_deleted' => true,
        ]);

        $this->getJson("/api/v1/users/{$profileUser->id}/testimonials")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_testimonials', 1)
            ->assertJsonPath('data.summary.given_by_total', 1)
            ->assertJsonPath('data.summary.given_to_total', 1)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.message', 'Approved/visible testimonial!');
    }
}
