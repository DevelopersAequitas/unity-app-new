<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserPushToken;
use App\Services\Notifications\FcmService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserFcmTokenSyncTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchemas();

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'testuser@example.com',
            'status' => 'active',
        ]);
    }

    private function createTestSchemas(): void
    {
        Schema::dropIfExists('user_push_tokens');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->nullable();
            $table->string('android_fcm_token')->nullable();
            $table->string('ios_fcm_token')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_push_tokens', function ($table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->text('token');
            $table->string('platform')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('active');
            $table->string('token_status')->default('active');
            $table->string('device_id')->nullable();
            $table->string('app_version')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    public function test_saving_push_token_updates_user_fcm_fields(): void
    {
        // 1. Android token
        $androidToken = UserPushToken::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'token' => 'android-token-123',
            'platform' => 'android',
            'is_active' => true,
        ]);

        $this->user->refresh();
        $this->assertEquals('android-token-123', $this->user->android_fcm_token);
        $this->assertNull($this->user->ios_fcm_token);

        // 2. iOS token
        $iosToken = UserPushToken::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'token' => 'ios-token-456',
            'platform' => 'ios',
            'is_active' => true,
        ]);

        $this->user->refresh();
        $this->assertEquals('android-token-123', $this->user->android_fcm_token);
        $this->assertEquals('ios-token-456', $this->user->ios_fcm_token);
    }

    public function test_deleting_push_token_clears_user_fcm_fields(): void
    {
        $androidToken = UserPushToken::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'token' => 'android-token-123',
            'platform' => 'android',
            'is_active' => true,
        ]);

        $this->user->refresh();
        $this->assertEquals('android-token-123', $this->user->android_fcm_token);

        $androidToken->delete();

        $this->user->refresh();
        $this->assertNull($this->user->android_fcm_token);
    }

    public function test_deactivating_invalid_token_clears_user_fcm_fields(): void
    {
        UserPushToken::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'token' => 'android-token-123',
            'platform' => 'android',
            'is_active' => true,
        ]);

        $this->user->refresh();
        $this->assertEquals('android-token-123', $this->user->android_fcm_token);

        // Deactivate via service
        $fcmService = app(FcmService::class);
        $fcmService->deactivateInvalidToken('android-token-123', 'Testing invalid token');

        $this->user->refresh();
        $this->assertNull($this->user->android_fcm_token);
    }

    public function test_active_tokens_for_user_retrieves_merged_tokens(): void
    {
        // Create only in users table (simulating missing user_push_tokens row)
        $this->user->update([
            'android_fcm_token' => 'virtual-android-token',
            'ios_fcm_token' => 'virtual-ios-token',
        ]);

        $fcmService = app(FcmService::class);
        $tokens = $fcmService->activeTokensForUser($this->user->id);

        $this->assertCount(2, $tokens);
        $this->assertTrue($tokens->contains(fn ($t) => $t->token === 'virtual-android-token' && $t->platform === 'android'));
        $this->assertTrue($tokens->contains(fn ($t) => $t->token === 'virtual-ios-token' && $t->platform === 'ios'));
    }
}
