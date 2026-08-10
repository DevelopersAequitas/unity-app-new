<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConnectionNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    private function createSchema(): void
    {
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('connections');
        Schema::dropIfExists('peer_blocks');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->nullable();
            $table->string('membership_status')->nullable();
            $table->timestamp('gdpr_deleted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('connections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('requester_id');
            $table->uuid('addressee_id');
            $table->boolean('is_approved')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('approved_at')->nullable();
        });

        Schema::create('peer_blocks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('blocker_user_id');
            $table->uuid('blocked_user_id');
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type')->nullable();
            $table->json('payload')->nullable();
            $table->json('data')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->string('source_event')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('read_at')->nullable();
        });

        Schema::create('app_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('campaign_id')->nullable();
            $table->string('type')->default('general');
            $table->string('category')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('channel')->default('push');
            $table->string('priority')->default('normal');
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('screen')->nullable();
            $table->json('data')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->string('status')->default('sent');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_send_connection_request_creates_app_notification_visible_in_notifications_api(): void
    {
        $sender = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Sender',
            'last_name' => 'Peer',
            'display_name' => 'Sender Peer',
            'status' => 'active',
        ]);

        $recipient = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Recipient',
            'last_name' => 'Peer',
            'display_name' => 'Recipient Peer',
            'status' => 'active',
        ]);

        Sanctum::actingAs($sender);

        $response = $this->postJson("/api/v1/members/{$recipient->id}/connections");
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Connection request sent',
            ]);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $recipient->id,
            'type' => 'connection_request',
            'category' => 'connection_request',
            'title' => 'New Connection Request',
        ]);

        Sanctum::actingAs($recipient);

        $listResponse = $this->getJson('/api/v1/notifications');
        $listResponse->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $items = $listResponse->json('data.items') ?? $listResponse->json('data.notifications') ?? [];
        $connectionNotifs = collect($items)->where('type', 'connection_request');

        $this->assertNotEmpty($connectionNotifs);
        $notif = $connectionNotifs->first();
        $this->assertSame('New Connection Request', $notif['title']);
        $this->assertStringContainsString('sent you a connection request', $notif['body']);
        $this->assertSame('/connection-requests', $notif['screen']);
    }

    public function test_accept_connection_request_creates_app_notification_for_requester(): void
    {
        $requester = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alpha',
            'last_name' => 'User',
            'display_name' => 'Alpha User',
            'status' => 'active',
        ]);

        $acceptor = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Beta',
            'last_name' => 'User',
            'display_name' => 'Beta User',
            'status' => 'active',
        ]);

        $connection = Connection::create([
            'requester_id' => $requester->id,
            'addressee_id' => $acceptor->id,
            'is_approved' => false,
        ]);

        Sanctum::actingAs($acceptor);

        $response = $this->postJson("/api/v1/members/{$requester->id}/connections/accept");
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Connection request accepted',
            ]);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $requester->id,
            'type' => 'connection_accepted',
            'category' => 'connection_accepted',
            'title' => 'Connection Accepted',
        ]);

        Sanctum::actingAs($requester);

        $filterResponse = $this->getJson('/api/v1/notifications?type=connection');
        $filterResponse->assertOk();

        $items = $filterResponse->json('data.items') ?? $filterResponse->json('data.notifications') ?? [];
        $acceptedNotifs = collect($items)->where('type', 'connection_accepted');

        $this->assertNotEmpty($acceptedNotifs);
        $notif = $acceptedNotifs->first();
        $this->assertSame('Connection Accepted', $notif['title']);
        $this->assertStringContainsString('accepted your connection request', $notif['body']);
        $this->assertSame('/my-connections', $notif['screen']);
    }
}
