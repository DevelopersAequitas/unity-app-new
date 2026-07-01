<?php

namespace Tests\Feature;

use App\Models\AdminCampaign;
use App\Models\AdminUser;
use App\Models\CampaignDelivery;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCampaignE2ETest extends TestCase
{
    use DatabaseTransactions;

    protected AdminUser $admin;

    protected User $user1;

    protected User $user2;

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
            $role = new Role;
            $role->id = (string) Str::uuid();
            $role->name = ucfirst(str_replace('_', ' ', $k));
            $role->key = $k;
            $role->save();
            if ($k === 'global_admin') {
                $globalAdminRoleId = $role->id;
            }
        }

        $this->admin->roles()->attach($globalAdminRoleId);

        // Auto-generate UUID for Notification model in tests since SQLite does not support gen_random_uuid()
        \App\Models\Notification::creating(function ($notification) {
            if (empty($notification->id)) {
                $notification->id = (string) \Illuminate\Support\Str::uuid();
            }
        });

        // Setup target users/members for test campaigns
        $this->user1 = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'display_name' => 'John Doe',
            'email' => 'john@example.com',
            'membership_status' => 'active',
        ]);

        $this->user2 = User::create([
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
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_push_tokens', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->text('token');
            $table->string('platform')->nullable();
            $table->string('device_id')->nullable();
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

        Schema::create('circle_members', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('circle_id');
            $table->string('status')->default('approved');
            $table->timestamps();
            $table->softDeletes();
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

    public function test_e2e_send_immediately_email_and_notification(): void
    {
        Mail::fake();
        $this->actingAs($this->admin, 'admin');
        $this->withoutExceptionHandling();

        // Register push tokens for user1 and user2
        \App\Models\UserPushToken::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user1->id,
            'token' => 'fcm-token-user1',
            'platform' => 'android',
        ]);
        \App\Models\UserPushToken::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user2->id,
            'token' => 'fcm-token-user2',
            'platform' => 'ios',
        ]);

        // Mock FcmService
        $fcmMock = $this->mock(\App\Services\Firebase\FcmService::class);
        $fcmMock->shouldReceive('sendToDevice')
            ->twice()
            ->andReturn([
                'success' => true,
                'firebase_response' => ['name' => 'mock-message-id'],
                'error' => null,
            ]);

        // Submit form data for sending immediately
        $response = $this->post(route('admin.campaigns.store'), [
            'title' => 'Immediate Campaign E2E',
            'campaign_type' => 'email_and_notification',
            'subject' => 'Immediate Subject',
            'email_body' => 'Immediate Body',
            'notification_title' => 'Immediate Push Title',
            'notification_message' => 'Immediate Push Msg',
            'audience_type' => 'all_members',
            'filters' => [],
            'action' => 'send', // Click Send Campaign
            'schedule' => [
                'schedule_type' => 'immediately',
                // recurrence settings that should be ignored:
                'monthly_basis' => 'date',
                'monthly_day_of_month' => '',
            ],
        ]);

        // Assert validation passes and redirects to show page
        $campaign = AdminCampaign::where('title', 'Immediate Campaign E2E')->first();
        $this->assertNotNull($campaign);
        $response->assertRedirect(route('admin.campaigns.show', $campaign));

        $campaign->refresh();
        $this->assertEquals('sent', $campaign->status);
        $this->assertEquals('immediately', $campaign->schedule->schedule_type);

        $delivery = CampaignDelivery::where('campaign_id', $campaign->id)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('sent', $delivery->status);

        // Check report view / page populated
        $this->assertEquals(2, $campaign->total_recipients);
        $this->assertEquals(2, $campaign->total_email_sent);
        $this->assertEquals(2, $campaign->total_notification_sent);

        // Check recipient logs are stored
        $this->assertDatabaseHas('campaign_logs', [
            'delivery_id' => $delivery->id,
            'user_id' => $this->user1->id,
            'email_status' => 'sent',
            'notification_status' => 'sent',
            'email_sent' => true,
            'notification_sent' => true,
        ]);
        $this->assertDatabaseHas('campaign_logs', [
            'delivery_id' => $delivery->id,
            'user_id' => $this->user2->id,
            'email_status' => 'sent',
            'notification_status' => 'sent',
            'email_sent' => true,
            'notification_sent' => true,
        ]);
    }

    /**
     * E2E Flow 2: Schedule Once
     */
    public function test_e2e_schedule_once(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->post(route('admin.campaigns.store'), [
            'title' => 'Once Campaign E2E',
            'campaign_type' => 'email_only',
            'subject' => 'Once Subject',
            'email_body' => 'Once Body',
            'audience_type' => 'all_members',
            'filters' => [],
            'action' => 'send',
            'schedule' => [
                'schedule_type' => 'once',
                'start_date' => '2026-06-20',
                'send_time' => '10:00:00',
                'timezone' => 'UTC',
            ],
        ]);

        $campaign = AdminCampaign::where('title', 'Once Campaign E2E')->first();
        $this->assertNotNull($campaign);
        $response->assertRedirect(route('admin.campaigns.show', $campaign));

        $this->assertEquals('scheduled', $campaign->status);
        $this->assertEquals('once', $campaign->schedule->schedule_type);
        $this->assertEquals('2026-06-20 10:00:00', $campaign->schedule->next_run_at->format('Y-m-d H:i:s'));
    }

    /**
     * E2E Flow 3: Daily Recurrence
     */
    public function test_e2e_daily_recurrence(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->post(route('admin.campaigns.store'), [
            'title' => 'Daily Campaign E2E',
            'campaign_type' => 'email_only',
            'subject' => 'Daily Subject',
            'email_body' => 'Daily Body',
            'audience_type' => 'all_members',
            'filters' => [],
            'action' => 'send',
            'schedule' => [
                'schedule_type' => 'recurring',
                'start_date' => '2026-06-15',
                'send_time' => '09:00:00',
                'timezone' => 'UTC',
                'end_type' => 'never',
                'recurrence_type' => 'daily',
                'frequency_interval' => 1,
            ],
        ]);

        $campaign = AdminCampaign::where('title', 'Daily Campaign E2E')->first();
        $this->assertNotNull($campaign);
        $response->assertRedirect(route('admin.campaigns.show', $campaign));

        $this->assertEquals('scheduled', $campaign->status);
        $this->assertEquals('recurring', $campaign->schedule->schedule_type);
        $this->assertEquals('daily', $campaign->schedule->recurrence_type);
        $this->assertNotNull($campaign->schedule->next_run_at);
    }

    /**
     * E2E Flow 4: Weekly Recurrence
     */
    public function test_e2e_weekly_recurrence(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->post(route('admin.campaigns.store'), [
            'title' => 'Weekly Campaign E2E',
            'campaign_type' => 'email_only',
            'subject' => 'Weekly Subject',
            'email_body' => 'Weekly Body',
            'audience_type' => 'all_members',
            'filters' => [],
            'action' => 'send',
            'schedule' => [
                'schedule_type' => 'recurring',
                'start_date' => '2026-06-15',
                'send_time' => '09:00:00',
                'timezone' => 'UTC',
                'end_type' => 'never',
                'recurrence_type' => 'weekly',
                'frequency_interval' => 1,
                'weekdays' => ['Monday', 'Friday'],
            ],
        ]);

        $campaign = AdminCampaign::where('title', 'Weekly Campaign E2E')->first();
        $this->assertNotNull($campaign);
        $response->assertRedirect(route('admin.campaigns.show', $campaign));

        $this->assertEquals('scheduled', $campaign->status);
        $this->assertEquals('recurring', $campaign->schedule->schedule_type);
        $this->assertEquals('weekly', $campaign->schedule->recurrence_type);
        $this->assertNotNull($campaign->schedule->next_run_at);
    }

    /**
     * E2E Flow 5: Monthly Recurrence
     */
    public function test_e2e_monthly_recurrence(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->post(route('admin.campaigns.store'), [
            'title' => 'Monthly Campaign E2E',
            'campaign_type' => 'email_only',
            'subject' => 'Monthly Subject',
            'email_body' => 'Monthly Body',
            'audience_type' => 'all_members',
            'filters' => [],
            'action' => 'send',
            'schedule' => [
                'schedule_type' => 'recurring',
                'start_date' => '2026-06-15',
                'send_time' => '09:00:00',
                'timezone' => 'UTC',
                'end_type' => 'never',
                'recurrence_type' => 'monthly',
                'frequency_interval' => 1,
                'monthly_basis' => 'date',
                'monthly_day_of_month' => 15,
            ],
        ]);

        $campaign = AdminCampaign::where('title', 'Monthly Campaign E2E')->first();
        $this->assertNotNull($campaign);
        $response->assertRedirect(route('admin.campaigns.show', $campaign));

        $this->assertEquals('scheduled', $campaign->status);
        $this->assertEquals('recurring', $campaign->schedule->schedule_type);
        $this->assertEquals('monthly', $campaign->schedule->recurrence_type);
        $this->assertNotNull($campaign->schedule->next_run_at);
    }

    /**
     * E2E Flow 5b: Yearly Recurrence
     */
    public function test_e2e_yearly_recurrence(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->post(route('admin.campaigns.store'), [
            'title' => 'Yearly Campaign E2E',
            'campaign_type' => 'email_only',
            'subject' => 'Yearly Subject',
            'email_body' => 'Yearly Body',
            'audience_type' => 'all_members',
            'filters' => [],
            'action' => 'send',
            'schedule' => [
                'schedule_type' => 'recurring',
                'start_date' => '2026-06-15',
                'send_time' => '09:00:00',
                'timezone' => 'UTC',
                'end_type' => 'never',
                'recurrence_type' => 'yearly',
                'frequency_interval' => 1,
                'yearly_month' => 6,
                'yearly_day' => 15,
            ],
        ]);

        $campaign = AdminCampaign::where('title', 'Yearly Campaign E2E')->first();
        $this->assertNotNull($campaign);
        $response->assertRedirect(route('admin.campaigns.show', $campaign));

        $this->assertEquals('scheduled', $campaign->status);
        $this->assertEquals('recurring', $campaign->schedule->schedule_type);
        $this->assertEquals('yearly', $campaign->schedule->recurrence_type);
        $this->assertNotNull($campaign->schedule->next_run_at);
    }

    /**
     * E2E Flow 6: Specific Members
     */
    public function test_e2e_specific_members(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->post(route('admin.campaigns.store'), [
            'title' => 'Specific Members Campaign E2E',
            'campaign_type' => 'email_only',
            'subject' => 'Specific Subject',
            'email_body' => 'Specific Body',
            'audience_type' => 'specific_members',
            'filters' => [
                'user_ids' => [$this->user1->id],
            ],
            'action' => 'draft',
            'schedule' => [
                'schedule_type' => 'immediately',
            ],
        ]);

        $campaign = AdminCampaign::where('title', 'Specific Members Campaign E2E')->first();
        $this->assertNotNull($campaign);
        $response->assertRedirect(route('admin.campaigns.show', $campaign));

        $viewResponse = $this->get(route('admin.campaigns.show', $campaign));
        $viewResponse->assertOk();

        $viewResponse->assertSee($this->user1->adminDisplayName());
        $viewResponse->assertSee($this->user1->email);
        $viewResponse->assertSee($this->user1->id);
        $viewResponse->assertSee('John Doe');
    }
}
