<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivitiesRequirementNotificationTest extends TestCase
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
        Schema::dropIfExists('posts');
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('coins_ledger');

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type');
            $table->json('payload')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('coins_ledger', function (Blueprint $table): void {
            $table->uuid('transaction_id')->primary();
            $table->uuid('user_id');
            $table->integer('amount');
            $table->integer('balance_after');
            $table->string('reference')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
        });

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
            $table->bigInteger('coins_balance')->default(0);
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

        Schema::create('app_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('campaign_id')->nullable();
            $table->string('type')->nullable();
            $table->string('category')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->text('body')->nullable();
            $table->string('channel')->default('push');
            $table->string('priority')->default('normal');
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('screen')->nullable();
            $table->json('data')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function test_creating_activity_requirement_triggers_notifications_to_other_users(): void
    {
        $creator = User::create([
            'id' => '11111111-1111-1111-1111-111111111111',
            'display_name' => 'Creator User',
            'email' => 'creator@example.com',
        ]);

        $recipient = User::create([
            'id' => '22222222-2222-2222-2222-222222222222',
            'display_name' => 'Recipient User',
            'email' => 'recipient@example.com',
        ]);

        Sanctum::actingAs($creator);

        $payload = [
            'subject' => 'Looking for CRM Software Development',
            'description' => 'We need a CRM system for our 20-member sales team.',
            'region_label' => 'Gujarat Region',
            'city_name' => 'Vadodara',
            'category' => 'IT Services',
            'budget' => 500000,
            'timeline' => '3 months',
            'tags' => ['CRM', 'SaaS'],
            'visibility' => 'public',
        ];

        $response = $this->postJson('/api/v1/activities/requirements', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Requirement created successfully',
            ]);

        $this->assertDatabaseHas('requirements', [
            'user_id' => $creator->id,
            'subject' => 'Looking for CRM Software Development',
        ]);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $recipient->id,
            'category' => 'requirement_created',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $recipient->id,
            'type' => 'activity_update',
        ]);
    }
}
