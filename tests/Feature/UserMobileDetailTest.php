<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserMobileDetail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserMobileDetailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInMemoryDatabase();
    }

    private function setUpInMemoryDatabase(): void
    {
        Schema::dropIfExists('users_mobile_details');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email', 255)->unique();
            $table->string('password_hash');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->uuid('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('users_mobile_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('device_type', 20);
            $table->string('device_name', 255)->nullable();
            $table->string('os_version', 50)->nullable();
            $table->string('device_id', 255);
            $table->string('token_id', 255)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    private function createUser(): User
    {
        return User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'display_name' => 'John Doe',
            'email' => 'john.' . Str::random(5) . '@example.com',
            'password_hash' => bcrypt('password'),
        ]);
    }

    public function test_register_device_successfully(): void
    {
        $user = $this->createUser();
        $tokenObj = $user->createToken('test_token');

        $payload = [
            'device_type' => 'android',
            'device_name' => 'Samsung S22',
            'os_version' => '13.0',
            'device_id' => 'android_device_id_123',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenObj->plainTextToken)
            ->postJson('/api/v1/user/devices/register', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Device registered successfully.')
            ->assertJsonPath('data.device_id', 'android_device_id_123')
            ->assertJsonPath('data.device_type', 'android');

        $this->assertDatabaseHas('users_mobile_details', [
            'user_id' => $user->id,
            'device_id' => 'android_device_id_123',
            'device_type' => 'android',
            'token_id' => (string) $tokenObj->accessToken->id,
        ]);
    }

    public function test_registering_new_device_invalidates_previous_token_on_same_platform(): void
    {
        $user = $this->createUser();

        // 1. Login on first Android device
        $token1 = $user->createToken('auth_token1');
        $tokenId1 = $token1->accessToken->id;

        UserMobileDetail::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'device_type' => 'android',
            'device_name' => 'Samsung S22',
            'os_version' => '13.0',
            'device_id' => 'android_device_1',
            'token_id' => $tokenId1,
            'last_login_at' => now(),
        ]);

        // 2. Login on second Android device (simulate registration)
        $token2 = $user->createToken('auth_token2');
        $tokenId2 = $token2->accessToken->id;

        $payload = [
            'device_type' => 'android',
            'device_name' => 'Google Pixel 7',
            'os_version' => '14.0',
            'device_id' => 'android_device_2',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $token2->plainTextToken)
            ->postJson('/api/v1/user/devices/register', $payload);

        $response->assertStatus(200);

        // Verify the first token is deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId1,
        ]);

        // Verify the old device detail is deleted
        $this->assertDatabaseMissing('users_mobile_details', [
            'device_id' => 'android_device_1',
        ]);

        // Verify the new device detail is stored
        $this->assertDatabaseHas('users_mobile_details', [
            'user_id' => $user->id,
            'device_id' => 'android_device_2',
            'token_id' => (string) $tokenId2,
        ]);
    }

    public function test_registering_device_on_other_platform_keeps_existing_tokens(): void
    {
        $user = $this->createUser();

        // 1. Android token
        $tokenAndroid = $user->createToken('auth_token');
        $tokenAndroidId = $tokenAndroid->accessToken->id;

        UserMobileDetail::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'device_type' => 'android',
            'device_name' => 'Samsung S22',
            'os_version' => '13.0',
            'device_id' => 'android_device_1',
            'token_id' => $tokenAndroidId,
            'last_login_at' => now(),
        ]);

        // 2. Register iOS device
        $tokenIos = $user->createToken('auth_token_ios');
        $tokenIosId = $tokenIos->accessToken->id;

        $payload = [
            'device_type' => 'ios',
            'device_name' => 'iPhone 14',
            'os_version' => '16.0',
            'device_id' => 'ios_device_1',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenIos->plainTextToken)
            ->postJson('/api/v1/user/devices/register', $payload);

        $response->assertStatus(200);

        // Both tokens should exist
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenAndroidId,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenIosId,
        ]);

        // Both device details should exist
        $this->assertDatabaseHas('users_mobile_details', [
            'device_id' => 'android_device_1',
        ]);
        $this->assertDatabaseHas('users_mobile_details', [
            'device_id' => 'ios_device_1',
        ]);
    }

    public function test_logout_device_successfully(): void
    {
        $user = $this->createUser();
        $tokenObj = $user->createToken('test_token');
        $tokenId = $tokenObj->accessToken->id;

        UserMobileDetail::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'device_type' => 'android',
            'device_name' => 'Samsung S22',
            'os_version' => '13.0',
            'device_id' => 'android_device_1',
            'token_id' => $tokenId,
            'last_login_at' => now(),
        ]);

        $payload = [
            'device_id' => 'android_device_1',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenObj->plainTextToken)
            ->postJson('/api/v1/user/devices/logout', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verify token is deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);

        // Verify device detail is deleted
        $this->assertDatabaseMissing('users_mobile_details', [
            'device_id' => 'android_device_1',
        ]);
    }
}
