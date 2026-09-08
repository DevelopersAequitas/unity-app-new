<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\CircleCategory;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeaderAppEndpointsFixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInMemoryDatabase();
    }

    private function setUpInMemoryDatabase(): void
    {
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('leader_role_capabilities');
        Schema::dropIfExists('circle_category_mappings');
        Schema::dropIfExists('circle_categories');
        Schema::dropIfExists('circle_members');
        Schema::dropIfExists('circles');
        Schema::dropIfExists('admin_users');
        Schema::dropIfExists('user_push_tokens');
        Schema::dropIfExists('users');

        Schema::create('circle_category_mappings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('circle_id');
            $table->unsignedBigInteger('category_id');
            $table->timestamps();
        });

        Schema::create('leader_role_capabilities', function (Blueprint $table): void {
            $table->id();
            $table->string('role_key');
            $table->string('capability_key');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email', 255)->unique();
            $table->string('phone', 50)->nullable();
            $table->string('password_hash')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('admin_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('role', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->id();
            $table->uuid('circle_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('role', 50)->nullable();
            $table->string('status', 50)->default('approved');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_push_tokens', function (Blueprint $table): void {
            $table->id();
            $table->uuid('user_id')->nullable();
            $table->string('token')->nullable();
            $table->string('platform', 50)->nullable();
            $table->string('device_id')->nullable();
            $table->string('app_version', 50)->nullable();
            $table->timestamps();
        });

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

        Schema::create('otp_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('code');
            $table->string('purpose', 50);
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('circle_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->integer('level')->default(1);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->uuid('circle_founder_user_id')->nullable();
            $table->string('status', 50)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Test industries endpoint returns 18 circle categories.
     */
    public function test_industries_endpoint_returns_master_18_circle_categories(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Leader',
            'last_name' => 'User',
            'email' => 'leader@example.com',
            'phone' => '+919876543210',
        ]);

        $circle = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'Engineering Circle',
            'slug' => 'engineering-circle',
            'circle_founder_user_id' => $user->id,
            'status' => 'active',
        ]);

        for ($i = 1; $i <= 18; $i++) {
            CircleCategory::create([
                'name' => 'Category '.$i,
                'slug' => 'category-'.$i,
                'level' => 1,
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/industries');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Industries fetched successfully.');

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(18, $data);
        $this->assertEquals('Category 1', $data[0]['name']);
        $this->assertArrayHasKey('circles_count', $data[0]);
        $this->assertArrayHasKey('peers_count', $data[0]);
        $this->assertEquals('Active', $data[0]['status']);
    }

    /**
     * Test verify-otp endpoint response contains access_token and does not contain auth_token or token.
     */
    public function test_verify_otp_returns_access_token_and_omits_auth_token_and_token(): void
    {
        $this->withoutExceptionHandling();

        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Arjun',
            'last_name' => 'Patel',
            'email' => 'arjun.'.Str::random(5).'@peersglobal.in',
            'phone' => '+919876543209',
        ]);

        OtpCode::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'code' => Hash::make('123456'),
            'purpose' => 'login_otp',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'email_or_phone' => $user->email,
            'otp' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Authentication successful')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'access_token',
                    'refresh_token',
                    'token_type',
                    'expires_in',
                    'user',
                ],
            ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('access_token', $data);
        $this->assertArrayNotHasKey('auth_token', $data);
        $this->assertArrayNotHasKey('token', $data);
    }
}
