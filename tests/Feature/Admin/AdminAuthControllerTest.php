<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Role;
use App\Models\AdminLoginOtp;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Exception;

class AdminAuthControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestSchemas();
    }

    private function createTestSchemas(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('admin_users');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('admin_user_roles');
        Schema::dropIfExists('admin_login_otps');
        Schema::dropIfExists('email_logs');

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('admin_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('key')->unique();
            $table->timestamps();
        });

        Schema::create('admin_user_roles', function (Blueprint $table) {
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->primary(['user_id', 'role_id']);
        });

        Schema::create('admin_login_otps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email');
            $table->string('otp_hash');
            $table->timestamp('expires_at');
            $table->timestamp('last_sent_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('to_email');
            $table->string('to_name')->nullable();
            $table->string('template_key');
            $table->string('subject')->nullable();
            $table->string('source_module')->nullable();
            $table->string('related_type')->nullable();
            $table->string('related_id')->nullable();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->string('source_event')->nullable();
            $table->string('status');
            $table->text('body_html')->nullable();
            $table->text('body_text')->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->string('triggered_by')->nullable();
            $table->uuid('triggered_user_id')->nullable();
            $table->string('mail_provider')->nullable();
            $table->string('queue_id')->nullable();
            $table->string('message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_send_otp_success(): void
    {
        Mail::fake();

        $admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $response = $this->post(route('admin.login.send-otp'), [
            'email' => 'admin@example.com',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('otp_sent', true);
        $response->assertSessionHas('status', 'OTP sent');

        $this->assertDatabaseHas('admin_login_otps', [
            'email' => 'admin@example.com',
        ]);
        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'admin@example.com',
            'status' => 'sent',
        ]);
    }

    public function test_send_otp_mail_failure_handled_gracefully(): void
    {
        Mail::shouldReceive('raw')
            ->once()
            ->andThrow(new Exception('Recipient address rejected: User unknown in virtual alias table'));

        $admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $response = $this->post(route('admin.login.send-otp'), [
            'email' => 'admin@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['email' => 'Failed to send OTP: Recipient address rejected: User unknown in virtual alias table']);

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'admin@example.com',
            'status' => 'failed',
            'error_message' => 'Recipient address rejected: User unknown in virtual alias table',
        ]);
    }
}
