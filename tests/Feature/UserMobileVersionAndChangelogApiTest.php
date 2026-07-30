<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppChangelog;
use App\Models\User;
use App\Models\UserMobileVersion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserMobileVersionAndChangelogApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create users table for testing
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email', 255)->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('password_hash')->nullable();
            $table->string('company_name')->nullable();
            $table->string('membership_status')->nullable();
            $table->integer('coins_balance')->default(0);
            $table->string('public_profile_slug')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Create personal_access_tokens table for Sanctum authentication
        Schema::dropIfExists('personal_access_tokens');
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
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

        // Create user_mobile_versions schema in test DB
        Schema::dropIfExists('user_mobile_versions');
        Schema::create('user_mobile_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('platform');
            $table->string('app_version');
            $table->string('device_model')->nullable();
            $table->string('os_version')->nullable();
            $table->timestamps();
        });

        // Create app_changelogs schema in test DB
        Schema::dropIfExists('app_changelogs');
        Schema::create('app_changelogs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('version');
            $table->string('platform')->default('all');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('features')->default('[]');
            $table->boolean('is_released')->default(true);
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_authenticated_user_can_store_mobile_version(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $payload = [
            'platform' => 'android',
            'app_version' => '1.0.5',
            'device_model' => 'Pixel 8 Pro',
            'os_version' => 'Android 14',
        ];

        $response = $this->postJson('/api/v1/user/mobile-version', $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'User mobile version stored successfully.',
            ]);

        $this->assertDatabaseHas('user_mobile_versions', [
            'user_id' => $user->id,
            'platform' => 'android',
            'app_version' => '1.0.5',
            'device_model' => 'Pixel 8 Pro',
            'os_version' => 'Android 14',
        ]);
    }

    public function test_storing_mobile_version_validates_input(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        // Invalid platform (should be android or ios)
        $payload = [
            'platform' => 'windows',
            'app_version' => '1.0.5',
        ];

        $response = $this->postJson('/api/v1/user/mobile-version', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['platform']);
    }

    public function test_unauthenticated_user_cannot_store_mobile_version(): void
    {
        $payload = [
            'platform' => 'android',
            'app_version' => '1.0.5',
        ];

        $response = $this->postJson('/api/v1/user/mobile-version', $payload);

        $response->assertStatus(401);
    }

    public function test_can_fetch_released_changelogs(): void
    {
        AppChangelog::create([
            'id' => '00000000-0000-0000-0000-000000000001',
            'version' => '1.1.0',
            'platform' => 'android',
            'title' => 'New Android Feature',
            'description' => 'Changelog description',
            'features' => ['Fast loading', 'New design'],
            'is_released' => true,
            'released_at' => now(),
        ]);

        AppChangelog::create([
            'id' => '00000000-0000-0000-0000-000000000002',
            'version' => '1.0.9',
            'platform' => 'ios',
            'title' => 'New iOS Feature',
            'features' => ['Bug fix'],
            'is_released' => true,
            'released_at' => now()->subDay(),
        ]);

        // Fetch without platform filter (returns both)
        $response = $this->getJson('/api/v1/app/changelogs');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'App changelogs fetched successfully.',
            ])
            ->assertJsonCount(2, 'data');

        // Fetch with platform=android
        $responseAndroid = $this->getJson('/api/v1/app/changelogs?platform=android');
        $responseAndroid->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'version' => '1.1.0',
                'platform' => 'android',
            ]);
    }
}
