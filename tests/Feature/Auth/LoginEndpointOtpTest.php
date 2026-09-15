<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoginEndpointOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('password_hash')->nullable();
            $table->string('password')->nullable();
            $table->string('status')->default('active');
            $table->string('membership_status')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('otp_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('email');
            $table->string('purpose');
            $table->string('code');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id')->nullable();
            $table->uuid('user_id');
            $table->string('status')->default('approved');
            $table->string('role')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamp('paid_starts_at')->nullable();
            $table->timestamp('paid_ends_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('user_login_histories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('user_id');
            $table->timestamp('logged_in_at')->nullable();
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('email_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('to_email');
            $table->string('to_name')->nullable();
            $table->string('template_key')->nullable();
            $table->string('source_module')->nullable();
            $table->string('related_type')->nullable();
            $table->string('related_id')->nullable();
            $table->text('payload')->nullable();
            $table->string('status')->default('sent');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_login_with_email_only_sends_otp(): void
    {
        Mail::fake();

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'email' => 'harshchauhan9724@gmail.com',
            'first_name' => 'Harsh',
            'last_name' => 'Chauhan',
            'display_name' => 'Harsh Chauhan',
            'password_hash' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'harshchauhan9724@gmail.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('otp_codes', [
            'user_id' => $user->id,
            'email' => 'harshchauhan9724@gmail.com',
            'purpose' => 'login_otp',
        ]);
    }

    public function test_login_with_email_and_otp_authenticates_user(): void
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'email' => 'harshchauhan9724@gmail.com',
            'first_name' => 'Harsh',
            'last_name' => 'Chauhan',
            'display_name' => 'Harsh Chauhan',
            'password_hash' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        OtpCode::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'purpose' => 'login_otp',
            'code' => Hash::make('1234'),
            'expires_at' => now()->addMinutes(5),
            'used_at' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'harshchauhan9724@gmail.com',
            'otp' => '1234',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user',
                ],
            ]);
    }

    public function test_login_with_email_and_password_authenticates_user(): void
    {
        User::query()->create([
            'id' => (string) Str::uuid(),
            'email' => 'harshchauhan9724@gmail.com',
            'first_name' => 'Harsh',
            'last_name' => 'Chauhan',
            'display_name' => 'Harsh Chauhan',
            'password_hash' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'harshchauhan9724@gmail.com',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user',
                ],
            ]);
    }
}
