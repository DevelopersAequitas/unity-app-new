<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeaderNotificationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('notifications');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    private function createUser(): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'leader_test@example.com',
            'phone' => '9988776655',
        ]);
    }

    public function test_mark_notifications_as_read_with_uuid(): void
    {
        $user = $this->createUser();
        $notification = Notification::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'title' => 'Test Notification',
            'message' => 'Test message',
            'is_read' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/mark-read', [
                'notification_ids' => [$notification->id],
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Notifications marked as read successfully.',
            ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    }

    public function test_mark_all_notifications_as_read(): void
    {
        $user = $this->createUser();
        $notif1 = Notification::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'title' => 'Notif 1',
            'is_read' => false,
        ]);
        $notif2 = Notification::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'title' => 'Notif 2',
            'is_read' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/mark-read', [
                'notification_ids' => ['all'],
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertTrue((bool) $notif1->fresh()->is_read);
        $this->assertTrue((bool) $notif2->fresh()->is_read);
    }

    public function test_mark_read_handles_non_uuid_strings_gracefully(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/mark-read', [
                'notification_ids' => ['notif_01', 'sample_dummy_id'],
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Notifications marked as read successfully.',
            ]);
    }
}
