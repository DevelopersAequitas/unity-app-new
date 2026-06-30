<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\AdminUser;
use App\Models\AdminCampaign;
use App\Models\CampaignSchedule;
use App\Models\CampaignDelivery;
use App\Models\CampaignLog;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserPushToken;
use App\Models\Role;
use App\Jobs\ProcessCampaignDeliveryJob;
use App\Services\Firebase\FcmService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;

class CampaignNotificationDeliveryTest extends TestCase
{
    use DatabaseTransactions;

    protected AdminUser $admin;
    protected User $userWithToken;
    protected User $userWithoutToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchemas();

        // Setup Admin User and authenticate
        $this->admin = AdminUser::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Administrator',
            'email' => 'admin@example.com',
        ]);

        $roleKeys = ['global_admin', 'industry_director', 'ded', 'circle_leader', 'chair', 'vice_chair', 'secretary', 'member'];
        $globalAdminRoleId = null;
        foreach ($roleKeys as $k) {
            $role = new Role();
            $role->id = (string) Str::uuid();
            $role->name = ucfirst(str_replace('_', ' ', $k));
            $role->key = $k;
            $role->save();
            if ($k === 'global_admin') {
                $globalAdminRoleId = $role->id;
            }
        }

        $this->admin->roles()->attach($globalAdminRoleId);

        // Auto-generate UUID for Notification model in tests
        \App\Models\Notification::creating(function ($notification) {
            if (empty($notification->id)) {
                $notification->id = (string) \Illuminate\Support\Str::uuid();
            }
        });

        // Setup members
        $this->userWithToken = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'display_name' => 'John Doe',
            'email' => 'john@example.com',
            'membership_status' => 'active',
        ]);

        UserPushToken::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->userWithToken->id,
            'token' => 'fcm-valid-token-123',
            'platform' => 'android',
        ]);

        $this->userWithoutToken = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'display_name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'membership_status' => 'active',
        ]);
    }

    private function createTestSchemas(): void
    {
        Schema::dropIfExists('circle_members');
        Schema::dropIfExists('user_push_tokens');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('campaign_logs');
        Schema::dropIfExists('campaign_deliveries');
        Schema::dropIfExists('campaign_schedules');
        Schema::dropIfExists('admin_campaigns');
        Schema::dropIfExists('admin_user_roles');
        Schema::dropIfExists('admin_users');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('campaign_email_templates');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('user_login_histories');
        Schema::dropIfExists('circle_subscriptions');

        Schema::create('circle_subscriptions', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('circle_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('circle_members', function ($table) {

            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('circle_id');
            $table->string('status')->default('approved');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamp('paid_starts_at')->nullable();
            $table->timestamp('paid_ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });


        Schema::create('users', function ($table) {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('city')->nullable();
            $table->string('city_of_residence')->nullable();
            $table->string('membership_status')->nullable();
            $table->string('password_hash')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->bigInteger('coins_balance')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_login_histories', function ($table) {
            $table->id();
            $table->uuid('user_id');
            $table->timestamp('logged_in_at')->nullable();
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });


        Schema::create('user_push_tokens', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->text('token');
            $table->string('platform')->nullable();
            $table->string('device_id')->nullable();
            $table->string('app_version')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('otp_codes', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('email');
            $table->string('purpose');
            $table->string('code');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function ($table) {
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


        Schema::create('admin_users', function ($table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('roles', function ($table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('key')->unique();
            $table->timestamps();
        });

        Schema::create('admin_user_roles', function ($table) {
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->primary(['user_id', 'role_id']);
        });

        Schema::create('admin_campaigns', function ($table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('campaign_type');
            $table->string('subject')->nullable();
            $table->text('email_body')->nullable();
            $table->string('notification_title')->nullable();
            $table->text('notification_message')->nullable();
            $table->string('audience_type');
            $table->json('filters')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->integer('total_email_sent')->default(0);
            $table->integer('total_notification_sent')->default(0);
            $table->integer('total_failed')->default(0);
            $table->string('status')->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('pamphlet_id')->nullable();
            $table->json('pamphlet_snapshot')->nullable();
            $table->uuid('email_template_id')->nullable();
            $table->json('email_template_snapshot')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('campaign_schedules', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('campaign_id');
            $table->string('schedule_type');
            $table->date('start_date');
            $table->string('end_type')->default('never');
            $table->date('end_date')->nullable();
            $table->string('send_time');
            $table->string('timezone');
            $table->string('recurrence_type')->nullable();
            $table->integer('frequency_interval')->nullable();
            $table->string('weekdays')->nullable();
            $table->string('monthly_basis')->nullable();
            $table->integer('monthly_day_of_month')->nullable();
            $table->string('monthly_position')->nullable();
            $table->string('monthly_day_of_week')->nullable();
            $table->integer('yearly_month')->nullable();
            $table->integer('yearly_day')->nullable();
            $table->string('custom_unit')->nullable();
            $table->integer('cycle_send_days')->nullable();
            $table->integer('cycle_pause_days')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_deliveries', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('campaign_id');
            $table->uuid('schedule_id')->nullable();
            $table->string('status')->default('scheduled');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->integer('total_email_sent')->default(0);
            $table->integer('total_notification_sent')->default(0);
            $table->integer('total_failed')->default(0);
            $table->text('error_message')->nullable();
            $table->string('batch_id')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_logs', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('delivery_id');
            $table->uuid('user_id');
            $table->string('email')->nullable();
            $table->string('email_status')->default('queued');
            $table->string('notification_status')->default('queued');
            $table->boolean('email_sent')->default(false);
            $table->boolean('notification_sent')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type');
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->string('source_type')->nullable();
            $table->uuid('source_id')->nullable();
            $table->string('source_event')->nullable();
            $table->timestamps();
        });

        Schema::create('email_logs', function ($table) {
            $table->uuid('id')->primary();
            $table->string('to_email');
            $table->string('template_key');
            $table->json('payload')->nullable();
            $table->string('status');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_audit_logs', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('admin_user_id')->nullable();
            $table->string('action');
            $table->string('target_table');
            $table->uuid('target_id');
            $table->json('details')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('campaign_email_templates', function ($table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->default('basic');
            $table->text('preview_image_url')->nullable();
            $table->text('html_structure')->nullable();
            $table->text('css_styles')->nullable();
            $table->string('template_type')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('job_batches', function ($table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->text('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });
    }

    /**
     * Test Case 1: Email Only Campaign
     */
    public function test_campaign_email_only(): void
    {
        Mail::fake();
        $this->actingAs($this->admin, 'admin');

        $campaign = AdminCampaign::create([
            'id' => (string) Str::uuid(),
            'title' => 'Email Only Test',
            'campaign_type' => 'email_only',
            'subject' => 'Test Email Subject',
            'email_body' => 'Test Email Body',
            'audience_type' => 'all_members',
            'filters' => [],
            'status' => 'draft',
        ]);

        $delivery = CampaignDelivery::create([
            'id' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'status' => 'scheduled',
            'scheduled_at' => now(),
        ]);

        $job = new \App\Jobs\ProcessCampaignDeliveryJob($delivery->id);
        $job->handle(app(\App\Services\AdminCampaigns\CampaignRecipientResolverService::class));

        $this->assertDatabaseHas('campaign_deliveries', [
            'id' => $delivery->id,
            'total_recipients' => 2,
        ]);

        Mail::assertSent(\App\Mail\AdminCampaignMailable::class, 2);
    }

    /**
     * Test Case 2: Notification Only - Success (User has Token)
     */
    public function test_campaign_notification_only_success(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Mock FcmService
        $fcmMock = $this->mock(FcmService::class);
        $fcmMock->shouldReceive('sendToDevice')
            ->once()
            ->andReturn([
                'success' => true,
                'firebase_response' => ['name' => 'mock-message-id'],
                'error' => null
            ]);

        // Create campaign targetted only to userWithToken (use filter)
        $campaign = AdminCampaign::create([
            'id' => (string) Str::uuid(),
            'title' => 'Notification Only Success',
            'campaign_type' => 'notification_only',
            'notification_title' => 'Push Title',
            'notification_message' => 'Push Msg',
            'audience_type' => 'specific_members',
            'filters' => ['user_ids' => [$this->userWithToken->id]],
            'status' => 'draft',
        ]);

        $delivery = CampaignDelivery::create([
            'id' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'status' => 'scheduled',
            'scheduled_at' => now(),
        ]);

        $job = new \App\Jobs\ProcessCampaignDeliveryJob($delivery->id);
        $job->handle(app(\App\Services\AdminCampaigns\CampaignRecipientResolverService::class));

        // Wait for batch jobs (since we are synchronously calling SendCampaignRecipientJob inside process, or let's execute the job directly)
        $log = CampaignLog::where('delivery_id', $delivery->id)->first();
        $this->assertNotNull($log);

        $this->assertDatabaseHas('campaign_logs', [
            'id' => $log->id,
            'notification_status' => 'sent',
            'notification_sent' => true,
            'error_message' => null,
        ]);
    }

    /**
     * Test Case 3: Email + Notification - Success
     */
    public function test_campaign_email_and_notification_success(): void
    {
        Mail::fake();
        $this->actingAs($this->admin, 'admin');

        $fcmMock = $this->mock(FcmService::class);
        $fcmMock->shouldReceive('sendToDevice')
            ->once()
            ->andReturn([
                'success' => true,
                'firebase_response' => ['name' => 'mock-message-id-2'],
                'error' => null
            ]);

        $campaign = AdminCampaign::create([
            'id' => (string) Str::uuid(),
            'title' => 'Email + Notification Success',
            'campaign_type' => 'email_and_notification',
            'subject' => 'Subject',
            'email_body' => 'Body',
            'notification_title' => 'Push Title',
            'notification_message' => 'Push Msg',
            'audience_type' => 'specific_members',
            'filters' => ['member_ids' => [$this->userWithToken->id]],
            'status' => 'draft',
        ]);

        $delivery = CampaignDelivery::create([
            'id' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'status' => 'scheduled',
            'scheduled_at' => now(),
        ]);

        $log = CampaignLog::create([
            'id' => (string) Str::uuid(),
            'delivery_id' => $delivery->id,
            'user_id' => $this->userWithToken->id,
            'email' => $this->userWithToken->email,
            'email_status' => 'queued',
            'notification_status' => 'queued',
        ]);

        $recipientJob = new \App\Jobs\SendCampaignRecipientJob($delivery->id, $log->id, $this->userWithToken->id);
        $recipientJob->handle(app(\App\Services\EmailLogs\EmailLogService::class), $fcmMock);

        Mail::assertSent(\App\Mail\AdminCampaignMailable::class, 1);
        $this->assertDatabaseHas('campaign_logs', [
            'id' => $log->id,
            'email_status' => 'sent',
            'email_sent' => true,
            'notification_status' => 'sent',
            'notification_sent' => true,
            'error_message' => null,
        ]);
    }

    /**
     * Test Case 4: Notification - Provider Error (Invalid Device Token)
     */
    public function test_campaign_notification_invalid_device_token(): void
    {
        $this->actingAs($this->admin, 'admin');

        $fcmMock = $this->mock(FcmService::class);
        $fcmMock->shouldReceive('sendToDevice')
            ->once()
            ->andReturn([
                'success' => false,
                'firebase_response' => ['error' => 'InvalidRegistration'],
                'error' => 'Invalid or unregistered Firebase device token.'
            ]);

        $campaign = AdminCampaign::create([
            'id' => (string) Str::uuid(),
            'title' => 'Invalid Token Test',
            'campaign_type' => 'notification_only',
            'notification_title' => 'Push Title',
            'notification_message' => 'Push Msg',
            'audience_type' => 'specific_members',
            'filters' => ['member_ids' => [$this->userWithToken->id]],
            'status' => 'draft',
        ]);

        $delivery = CampaignDelivery::create([
            'id' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'status' => 'scheduled',
            'scheduled_at' => now(),
        ]);

        $log = CampaignLog::create([
            'id' => (string) Str::uuid(),
            'delivery_id' => $delivery->id,
            'user_id' => $this->userWithToken->id,
            'email' => $this->userWithToken->email,
            'email_status' => 'skipped',
            'notification_status' => 'queued',
        ]);

        $recipientJob = new \App\Jobs\SendCampaignRecipientJob($delivery->id, $log->id, $this->userWithToken->id);
        $recipientJob->handle(app(\App\Services\EmailLogs\EmailLogService::class), $fcmMock);

        $this->assertDatabaseHas('campaign_logs', [
            'id' => $log->id,
            'notification_status' => 'failed',
            'notification_sent' => false,
            'error_message' => 'Push Error: Invalid or unregistered Firebase device token.',
        ]);
    }

    /**
     * Test Case 5: Notification - Missing Device Token
     */
    public function test_campaign_notification_missing_device_token(): void
    {
        $this->actingAs($this->admin, 'admin');

        $fcmMock = $this->mock(FcmService::class);
        $fcmMock->shouldNotReceive('sendToDevice');

        $campaign = AdminCampaign::create([
            'id' => (string) Str::uuid(),
            'title' => 'Missing Token Test',
            'campaign_type' => 'notification_only',
            'notification_title' => 'Push Title',
            'notification_message' => 'Push Msg',
            'audience_type' => 'specific_members',
            'filters' => ['member_ids' => [$this->userWithoutToken->id]],
            'status' => 'draft',
        ]);

        $delivery = CampaignDelivery::create([
            'id' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'status' => 'scheduled',
            'scheduled_at' => now(),
        ]);

        $log = CampaignLog::create([
            'id' => (string) Str::uuid(),
            'delivery_id' => $delivery->id,
            'user_id' => $this->userWithoutToken->id,
            'email' => $this->userWithoutToken->email,
            'email_status' => 'skipped',
            'notification_status' => 'queued',
        ]);

        $recipientJob = new \App\Jobs\SendCampaignRecipientJob($delivery->id, $log->id, $this->userWithoutToken->id);
        $recipientJob->handle(app(\App\Services\EmailLogs\EmailLogService::class), $fcmMock);

        $this->assertDatabaseHas('campaign_logs', [
            'id' => $log->id,
            'notification_status' => 'failed',
            'notification_sent' => false,
            'error_message' => 'Push Error: No device token found',
        ]);
    }

    /**
     * Test Case 6: Login automatically registers push token
     */
    public function test_login_registers_push_token_automatically(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Login',
            'display_name' => 'John Login',
            'email' => 'john-login@example.com',
            'password_hash' => \Illuminate\Support\Facades\Hash::make('password123'),
            'membership_status' => 'active',
            'status' => 'active',
        ]);

        $payload = [
            'email' => 'john-login@example.com',
            'password' => 'password123',
            'token' => 'fcm-login-token-999',
            'platform' => 'Android',
            'device_id' => 'device-login-123',
            'app_version' => '1.0.0',
        ];

        $response = $this->postJson('/api/v1/auth/login', $payload);
        $response->assertStatus(200);

        $this->assertDatabaseHas('user_push_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-login-token-999',
            'platform' => 'android',
            'device_id' => 'device-login-123',
            'app_version' => '1.0.0',
        ]);
    }

    /**
     * Test Case 7: Verify OTP automatically registers push token
     */
    public function test_verify_otp_registers_push_token_automatically(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Jane',
            'last_name' => 'Otp',
            'display_name' => 'Jane Otp',
            'email' => 'jane-otp@example.com',
            'membership_status' => 'active',
            'status' => 'active',
        ]);

        \App\Models\OtpCode::create([
            'user_id'    => $user->id,
            'email'      => $user->email,
            'purpose'    => 'login_otp',
            'code'       => \Illuminate\Support\Facades\Hash::make('1234'),
            'expires_at' => now()->addMinutes(5),
            'used_at'    => null,
        ]);

        $payload = [
            'email' => 'jane-otp@example.com',
            'otp' => '1234',
            'fcm_token' => 'fcm-otp-token-888',
            'platform' => 'iOS',
            'device_id' => 'device-otp-456',
            'app_version' => '1.2.0',
        ];

        $response = $this->postJson('/api/v1/auth/verify-otp', $payload);
        $response->assertStatus(200);

        $this->assertDatabaseHas('user_push_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-otp-token-888',
            'platform' => 'ios',
            'device_id' => 'device-otp-456',
            'app_version' => '1.2.0',
        ]);
    }

    /**
     * Test Case 8: Token refresh updates existing record for device
     */
    public function test_token_refresh_updates_existing_record_for_device(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Refresh',
            'last_name' => 'User',
            'email' => 'refresh@example.com',
        ]);

        // 1. First registration
        UserPushToken::registerTokenForUser($user, [
            'token' => 'fcm-token-v1',
            'platform' => 'android',
            'device_id' => 'device-ref-777',
            'app_version' => '1.0.0',
        ]);

        // 2. Token refresh (same device_id, different token)
        UserPushToken::registerTokenForUser($user, [
            'token' => 'fcm-token-v2',
            'platform' => 'android',
            'device_id' => 'device-ref-777',
            'app_version' => '1.1.0',
        ]);

        // Assert only one token exists for this device
        $this->assertEquals(1, UserPushToken::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('user_push_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-token-v2',
            'platform' => 'android',
            'device_id' => 'device-ref-777',
            'app_version' => '1.1.0',
        ]);
    }

    /**
     * Test Case 9: Multiple devices per user are supported
     */
    public function test_multiple_devices_per_user_supported(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Multi',
            'last_name' => 'Device',
            'email' => 'multi@example.com',
        ]);

        // Register device 1
        UserPushToken::registerTokenForUser($user, [
            'token' => 'fcm-device-1',
            'platform' => 'android',
            'device_id' => 'dev-1',
        ]);

        // Register device 2
        UserPushToken::registerTokenForUser($user, [
            'token' => 'fcm-device-2',
            'platform' => 'ios',
            'device_id' => 'dev-2',
        ]);

        // Assert two records exist
        $this->assertEquals(2, UserPushToken::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('user_push_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-device-1',
            'device_id' => 'dev-1',
        ]);
        $this->assertDatabaseHas('user_push_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-device-2',
            'device_id' => 'dev-2',
        ]);
    }

    /**
     * Test Case 10: Logout deregisters matching token only
     */
    public function test_logout_deregisters_matching_token(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Logout',
            'last_name' => 'User',
            'email' => 'logout@example.com',
            'membership_status' => 'active',
            'status' => 'active',
        ]);

        // Register device 1
        UserPushToken::registerTokenForUser($user, [
            'token' => 'fcm-logout-1',
            'platform' => 'android',
            'device_id' => 'dev-logout-1',
        ]);

        // Register device 2
        UserPushToken::registerTokenForUser($user, [
            'token' => 'fcm-logout-2',
            'platform' => 'ios',
            'device_id' => 'dev-logout-2',
        ]);

        $this->actingAs($user, 'sanctum');

        // Logout requesting deletion of device 1 token only
        $response = $this->postJson('/api/v1/auth/logout', [
            'device_id' => 'dev-logout-1',
        ]);
        $response->assertStatus(200);

        // Assert dev-logout-1 is deleted, but dev-logout-2 remains
        $this->assertEquals(1, UserPushToken::where('user_id', $user->id)->count());
        $this->assertDatabaseMissing('user_push_tokens', [
            'token' => 'fcm-logout-1',
        ]);
        $this->assertDatabaseHas('user_push_tokens', [
            'token' => 'fcm-logout-2',
        ]);
    }
}

