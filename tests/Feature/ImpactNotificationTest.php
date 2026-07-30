<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Impact;
use App\Models\ImpactAction;
use App\Models\Notifications\AppNotification;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImpactNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('display_name')->nullable();
                $table->string('email')->unique()->nullable();
                $table->string('phone')->nullable();
                $table->string('company_name')->nullable();
                $table->string('membership_status')->nullable()->default('active');
                $table->string('status')->nullable()->default('active');
                $table->integer('life_impacted_count')->default(0);
                $table->integer('coins_balance')->default(0);
                $table->string('password_hash')->nullable();
                $table->string('public_profile_slug')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_push_tokens')) {
            Schema::create('user_push_tokens', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('token');
                $table->string('platform')->nullable();
                $table->string('device_id')->nullable();
                $table->string('app_version')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('impact_actions')) {
            Schema::create('impact_actions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->integer('impact_score')->default(1);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('impacts')) {
            Schema::create('impacts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('impacted_peer_id');
                $table->date('impact_date')->nullable();
                $table->string('action');
                $table->text('story_to_share')->nullable();
                $table->integer('life_impacted')->default(1);
                $table->text('additional_remarks')->nullable();
                $table->boolean('requires_leadership_approval')->default(true);
                $table->string('status')->default('pending');
                $table->uuid('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->uuid('rejected_by')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('review_remarks')->nullable();
                $table->timestamp('timeline_posted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('type');
                $table->json('payload')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('app_notifications')) {
            Schema::create('app_notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('campaign_id')->nullable();
                $table->string('type');
                $table->string('category')->nullable();
                $table->string('title');
                $table->text('message')->nullable();
                $table->text('body')->nullable();
                $table->string('channel')->default('push');
                $table->string('priority')->default('medium');
                $table->string('reference_type')->nullable();
                $table->string('reference_id')->nullable();
                $table->string('screen')->nullable();
                $table->json('data')->nullable();
                $table->json('payload')->nullable();
                $table->string('dedupe_key')->nullable();
                $table->string('status')->default('pending');
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('clicked_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->timestamps();
            });
        }

        ImpactAction::create([
            'name' => 'Mentorship Session',
            'impact_score' => 1,
            'is_active' => true,
        ]);
    }

    public function test_submitting_impact_creates_notifications_for_both_submitter_and_impacted_peer(): void
    {
        $submitter = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'display_name' => 'John Doe',
        ]);
        $peer = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'display_name' => 'Jane Smith',
        ]);

        $response = $this->actingAs($submitter, 'sanctum')
            ->postJson('/api/v1/life-impact', [
                'impacted_peer_id' => (string) $peer->id,
                'action' => 'Mentorship Session',
                'story_to_share' => 'Helped with coding project',
                'date' => '2026-07-30',
            ]);

        $response->assertStatus(201);

        $impact = Impact::first();
        $this->assertNotNull($impact);

        // Submitter notification check in app_notifications
        $submitterAppNotification = AppNotification::where('user_id', (string) $submitter->id)->first();
        $this->assertNotNull($submitterAppNotification);
        $this->assertEquals('impact_submitted', $submitterAppNotification->type);
        $this->assertEquals('life_impact', $submitterAppNotification->category);
        $this->assertEquals('/life-impact', $submitterAppNotification->screen);

        // Impacted peer notification check in app_notifications
        $peerAppNotification = AppNotification::where('user_id', (string) $peer->id)->first();
        $this->assertNotNull($peerAppNotification);
        $this->assertEquals('impact_received', $peerAppNotification->type);
        $this->assertEquals('life_impact', $peerAppNotification->category);
        $this->assertEquals('/life-impact', $peerAppNotification->screen);
        $this->assertStringContainsString('John Doe', $peerAppNotification->body);

        // Notification List API test for submitter
        $submitterListResponse = $this->actingAs($submitter, 'sanctum')
            ->getJson('/api/v1/notifications');

        $submitterListResponse->assertStatus(200)
            ->assertJsonFragment([
                'type' => 'impact_submitted',
                'screen' => '/life-impact',
                'tap_destination' => '/life-impact',
            ]);

        // Notification List API test for impacted peer
        $peerListResponse = $this->actingAs($peer, 'sanctum')
            ->getJson('/api/v1/notifications');

        $peerListResponse->assertStatus(200)
            ->assertJsonFragment([
                'type' => 'impact_received',
                'screen' => '/life-impact',
                'tap_destination' => '/life-impact',
            ]);
    }
}
