<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserPushToken;
use App\Services\Firebase\FcmService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class SendTestNotificationApiTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'display_name' => 'John Doe',
            'email' => 'test-push-user@example.com',
            'status' => 'active',
        ]);
    }

    public function test_send_test_notification_success_with_tokens(): void
    {
        // Register an active token for our user
        UserPushToken::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'token' => 'fcm-test-token-999',
            'platform' => 'android',
            'is_active' => true,
        ]);

        // Mock FcmService
        $fcmMock = $this->mock(FcmService::class);
        $fcmMock->shouldReceive('sendToDevice')
            ->once()
            ->andReturn([
                'success' => true,
                'firebase_response' => ['name' => 'mock-message-id'],
                'error' => null,
            ]);

        $response = $this->postJson('/api/v1/notifications/send-test', [
            'email' => 'test-push-user@example.com',
            'title' => 'API Test Title',
            'body' => 'API Test Body',
            'channel_id' => 'custom_high_importance',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'test-push-user@example.com')
            ->assertJsonPath('data.title', 'API Test Title')
            ->assertJsonPath('data.body', 'API Test Body')
            ->assertJsonPath('data.channel_id', 'custom_high_importance')
            ->assertJsonPath('data.tokens_count', 1)
            ->assertJsonPath('data.success', true);

        // Verify notification entry is created and marked as sent
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->user->id,
            'title' => 'API Test Title',
            'body' => 'API Test Body',
            'status' => 'sent',
        ]);
    }

    public function test_send_test_notification_skipped_when_no_tokens(): void
    {
        // User has no tokens registered
        $response = $this->postJson('/api/v1/notifications/send-test', [
            'email' => 'test-push-user@example.com',
            'title' => 'No Token Title',
            'body' => 'No Token Body',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tokens_count', 0)
            ->assertJsonPath('data.attempted', false)
            ->assertJsonPath('data.success', false);

        // Verify notification entry is marked as skipped
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->user->id,
            'title' => 'No Token Title',
            'body' => 'No Token Body',
            'status' => 'skipped',
        ]);
    }

    public function test_send_test_notification_fails_when_user_not_found(): void
    {
        $response = $this->postJson('/api/v1/notifications/send-test', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['email']);
    }
}
