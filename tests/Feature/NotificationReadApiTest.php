<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationReadApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('notifications');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type');
            $table->json('payload');
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('read_at')->nullable();
        });
    }

    public function test_mark_read_marks_only_the_authenticated_users_notification(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();
        $notification = $this->createNotification($user);
        $otherNotification = $this->createNotification($otherUser);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Notification marked as read')
            ->assertJsonPath('data.is_read', true);

        $this->assertTrue($notification->fresh()->is_read);
        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertFalse($otherNotification->fresh()->is_read);
    }

    public function test_mark_all_read_marks_all_unread_notifications_for_authenticated_user(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();
        $firstUnread = $this->createNotification($user);
        $secondUnread = $this->createNotification($user);
        $alreadyRead = $this->createNotification($user, true);
        $otherNotification = $this->createNotification($otherUser);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/notifications/read-all');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'All notifications marked as read')
            ->assertJsonPath('data.updated_count', 2);

        $this->assertTrue($firstUnread->fresh()->is_read);
        $this->assertNotNull($firstUnread->fresh()->read_at);
        $this->assertTrue($secondUnread->fresh()->is_read);
        $this->assertNotNull($secondUnread->fresh()->read_at);
        $this->assertTrue($alreadyRead->fresh()->is_read);
        $this->assertFalse($otherNotification->fresh()->is_read);
    }

    private function createUser(): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'email' => Str::uuid().'@example.com',
        ]);
    }

    private function createNotification(User $user, bool $isRead = false): Notification
    {
        return Notification::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'type' => 'system',
            'payload' => ['message' => 'Test notification'],
            'is_read' => $isRead,
            'created_at' => now(),
            'read_at' => $isRead ? now()->subMinute() : null,
        ]);
    }
}
