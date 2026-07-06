<?php

namespace Tests\Feature;

use App\Models\AdminCampaign;
use App\Models\AdminUser;
use App\Models\CampaignSchedule;
use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCampaignLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected AdminUser $admin;

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
    }

    private function createTestSchemas(): void
    {
        Schema::dropIfExists('user_push_tokens');
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('campaign_deliveries');
        Schema::dropIfExists('campaign_schedules');
        Schema::dropIfExists('admin_campaigns');
        Schema::dropIfExists('admin_user_roles');
        Schema::dropIfExists('admin_users');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('circles');
        Schema::dropIfExists('campaign_email_templates');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->uuid('id')->primary();
            $table->string('email')->nullable();
            $table->timestamps();
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

        Schema::create('circles', function ($table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
    }

    public function test_admin_can_edit_editable_campaigns(): void
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin, 'admin');

        $statuses = [AdminCampaign::STATUS_DRAFT, 'scheduled', 'active'];

        foreach ($statuses as $status) {
            $campaign = AdminCampaign::create([
                'title' => 'Editable Campaign '.$status,
                'campaign_type' => 'email_only',
                'audience_type' => 'all_members',
                'filters' => [],
                'status' => $status,
            ]);

            $response = $this->get(route('admin.campaigns.edit', $campaign));
            $response->assertOk();
        }
    }

    public function test_admin_cannot_edit_sent_completed_campaigns(): void
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin, 'admin');

        $statuses = ['sent', 'completed'];

        foreach ($statuses as $status) {
            $campaign = AdminCampaign::create([
                'title' => 'Non-Editable Campaign '.$status,
                'campaign_type' => 'email_only',
                'audience_type' => 'all_members',
                'filters' => [],
                'status' => $status,
            ]);

            $response = $this->get(route('admin.campaigns.edit', $campaign));
            $response->assertRedirect();
            $response->assertSessionHas('error');
        }
    }

    public function test_admin_can_soft_delete_campaign(): void
    {
        $this->actingAs($this->admin, 'admin');

        $campaign = AdminCampaign::create([
            'title' => 'Soft Delete Campaign',
            'campaign_type' => 'email_only',
            'audience_type' => 'all_members',
            'filters' => [],
            'status' => AdminCampaign::STATUS_DRAFT,
        ]);

        $response = $this->delete(route('admin.campaigns.destroy', $campaign));
        $response->assertRedirect(route('admin.campaigns.index'));

        // Assert record is hidden from normal query (soft deleted)
        $this->assertNull(AdminCampaign::find($campaign->id));

        // Assert record exists when querying with trashed
        $trashed = AdminCampaign::withTrashed()->find($campaign->id);
        $this->assertNotNull($trashed);
        $this->assertNotNull($trashed->deleted_at);
        $this->assertEquals(AdminCampaign::STATUS_DELETED, $trashed->status);

        // Verify audit log
        $this->assertDatabaseHas('admin_audit_logs', [
            'target_id' => $campaign->id,
            'action' => 'deleted',
        ]);
    }

    public function test_admin_can_soft_delete_sent_campaign(): void
    {
        $this->actingAs($this->admin, 'admin');

        $campaign = AdminCampaign::create([
            'title' => 'Soft Delete Sent Campaign',
            'campaign_type' => 'email_only',
            'audience_type' => 'all_members',
            'filters' => [],
            'status' => AdminCampaign::STATUS_SENT,
        ]);

        $response = $this->delete(route('admin.campaigns.destroy', $campaign));
        $response->assertRedirect(route('admin.campaigns.index'));

        // Assert record is hidden from normal query (soft deleted)
        $this->assertNull(AdminCampaign::find($campaign->id));

        // Assert record exists when querying with trashed
        $trashed = AdminCampaign::withTrashed()->find($campaign->id);
        $this->assertNotNull($trashed);
        $this->assertNotNull($trashed->deleted_at);
        $this->assertEquals(AdminCampaign::STATUS_DELETED, $trashed->status);

        // Verify audit log
        $this->assertDatabaseHas('admin_audit_logs', [
            'target_id' => $campaign->id,
            'action' => 'deleted',
        ]);
    }

    public function test_admin_can_pause_campaign(): void
    {
        $this->actingAs($this->admin, 'admin');

        $campaign = AdminCampaign::create([
            'title' => 'Pause Campaign',
            'campaign_type' => 'email_only',
            'audience_type' => 'all_members',
            'filters' => [],
            'status' => 'scheduled',
        ]);

        $schedule = CampaignSchedule::create([
            'campaign_id' => $campaign->id,
            'schedule_type' => 'recurring',
            'start_date' => now()->toDateString(),
            'send_time' => '09:00:00',
            'timezone' => 'UTC',
            'next_run_at' => now()->addDay(),
        ]);

        $response = $this->post(route('admin.campaigns.pause', $campaign));
        $response->assertRedirect(route('admin.campaigns.show', $campaign));

        $campaign->refresh();
        $this->assertEquals('paused', $campaign->status);
        $this->assertNull($campaign->schedule->next_run_at);

        // Verify audit log
        $this->assertDatabaseHas('admin_audit_logs', [
            'target_id' => $campaign->id,
            'action' => 'paused',
        ]);
    }

    public function test_admin_can_resume_campaign(): void
    {
        $this->actingAs($this->admin, 'admin');

        $campaign = AdminCampaign::create([
            'title' => 'Resume Campaign',
            'campaign_type' => 'email_only',
            'audience_type' => 'all_members',
            'filters' => [],
            'status' => 'paused',
        ]);

        $schedule = CampaignSchedule::create([
            'campaign_id' => $campaign->id,
            'schedule_type' => 'recurring',
            'start_date' => now()->addDay()->toDateString(),
            'send_time' => '09:00:00',
            'timezone' => 'UTC',
            'next_run_at' => null,
            'recurrence_type' => 'daily',
            'frequency_interval' => 1,
        ]);

        $response = $this->post(route('admin.campaigns.resume', $campaign));
        $response->assertRedirect(route('admin.campaigns.show', $campaign));

        $campaign->refresh();
        $this->assertEquals('active', $campaign->status);
        $this->assertNotNull($campaign->schedule->next_run_at);

        // Verify audit log
        $this->assertDatabaseHas('admin_audit_logs', [
            'target_id' => $campaign->id,
            'action' => 'resumed',
        ]);
    }

    public function test_admin_can_stop_campaign(): void
    {
        $this->actingAs($this->admin, 'admin');

        $campaign = AdminCampaign::create([
            'title' => 'Stop Campaign',
            'campaign_type' => 'email_only',
            'audience_type' => 'all_members',
            'filters' => [],
            'status' => 'active',
        ]);

        $schedule = CampaignSchedule::create([
            'campaign_id' => $campaign->id,
            'schedule_type' => 'recurring',
            'start_date' => now()->toDateString(),
            'send_time' => '09:00:00',
            'timezone' => 'UTC',
            'next_run_at' => now()->addDay(),
        ]);

        $response = $this->post(route('admin.campaigns.stop', $campaign));
        $response->assertRedirect(route('admin.campaigns.show', $campaign));

        $campaign->refresh();
        $this->assertEquals('stopped', $campaign->status);
        $this->assertNull($campaign->schedule->next_run_at);

        // Verify audit log
        $this->assertDatabaseHas('admin_audit_logs', [
            'target_id' => $campaign->id,
            'action' => 'stopped',
        ]);
    }

    public function test_admin_can_duplicate_campaign(): void
    {
        $this->actingAs($this->admin, 'admin');

        $campaign = AdminCampaign::create([
            'title' => 'Original Campaign',
            'campaign_type' => 'email_only',
            'audience_type' => 'all_members',
            'filters' => ['city' => 'New York'],
            'status' => 'sent',
        ]);

        $schedule = CampaignSchedule::create([
            'campaign_id' => $campaign->id,
            'schedule_type' => 'once',
            'start_date' => now()->toDateString(),
            'send_time' => '10:00:00',
            'timezone' => 'EST',
        ]);

        $response = $this->post(route('admin.campaigns.duplicate', $campaign));

        $newCampaign = AdminCampaign::where('title', 'Copy of Original Campaign')->first();
        $this->assertNotNull($newCampaign);
        $response->assertRedirect(route('admin.campaigns.edit', $newCampaign));

        $this->assertEquals('draft', $newCampaign->status);
        $this->assertEquals(['city' => 'New York'], $newCampaign->filters);
        $this->assertEquals('once', $newCampaign->schedule->schedule_type);
        $this->assertEquals('EST', $newCampaign->schedule->timezone);
        $this->assertNull($newCampaign->schedule->next_run_at);

        // Verify audit log
        $this->assertDatabaseHas('admin_audit_logs', [
            'target_id' => $newCampaign->id,
            'action' => 'duplicated',
        ]);
    }

    public function test_admin_can_retry_failed_campaign(): void
    {
        $this->actingAs($this->admin, 'admin');

        $campaign = AdminCampaign::create([
            'title' => 'Failed Campaign',
            'campaign_type' => 'email_only',
            'audience_type' => 'all_members',
            'filters' => [],
            'status' => 'failed',
            'total_recipients' => 50,
            'total_failed' => 50,
        ]);

        $schedule = CampaignSchedule::create([
            'campaign_id' => $campaign->id,
            'schedule_type' => 'recurring',
            'start_date' => now()->toDateString(),
            'send_time' => '12:00:00',
            'timezone' => 'UTC',
            'recurrence_type' => 'daily',
            'frequency_interval' => 1,
            'next_run_at' => null,
        ]);

        $response = $this->post(route('admin.campaigns.retry', $campaign));
        $response->assertRedirect(route('admin.campaigns.show', $campaign));

        $campaign->refresh();
        $this->assertEquals('active', $campaign->status);
        $this->assertEquals(0, $campaign->total_failed);
        $this->assertNotNull($campaign->schedule->next_run_at);

        // Verify audit log
        $this->assertDatabaseHas('admin_audit_logs', [
            'target_id' => $campaign->id,
            'action' => 'retried',
        ]);
    }

    public function test_admin_can_stop_scheduled_campaign(): void
    {
        $this->actingAs($this->admin, 'admin');

        $campaign = AdminCampaign::create([
            'title' => 'Stop Scheduled Campaign',
            'campaign_type' => 'email_only',
            'audience_type' => 'all_members',
            'filters' => [],
            'status' => 'scheduled',
        ]);

        $schedule = CampaignSchedule::create([
            'campaign_id' => $campaign->id,
            'schedule_type' => 'recurring',
            'start_date' => now()->toDateString(),
            'send_time' => '09:00:00',
            'timezone' => 'UTC',
            'next_run_at' => now()->addDay(),
        ]);

        $response = $this->post(route('admin.campaigns.stop', $campaign));
        $response->assertRedirect(route('admin.campaigns.show', $campaign));

        $campaign->refresh();
        $this->assertEquals('stopped', $campaign->status);
        $this->assertNull($campaign->schedule->next_run_at);
    }

    public function test_admin_can_stop_paused_campaign(): void
    {
        $this->actingAs($this->admin, 'admin');

        $campaign = AdminCampaign::create([
            'title' => 'Stop Paused Campaign',
            'campaign_type' => 'email_only',
            'audience_type' => 'all_members',
            'filters' => [],
            'status' => 'paused',
        ]);

        $schedule = CampaignSchedule::create([
            'campaign_id' => $campaign->id,
            'schedule_type' => 'recurring',
            'start_date' => now()->toDateString(),
            'send_time' => '09:00:00',
            'timezone' => 'UTC',
            'next_run_at' => null,
        ]);

        $response = $this->post(route('admin.campaigns.stop', $campaign));
        $response->assertRedirect(route('admin.campaigns.show', $campaign));

        $campaign->refresh();
        $this->assertEquals('stopped', $campaign->status);
    }

    public function test_admin_can_retry_stopped_campaign(): void
    {
        $this->actingAs($this->admin, 'admin');

        $campaign = AdminCampaign::create([
            'title' => 'Stopped Campaign',
            'campaign_type' => 'email_only',
            'audience_type' => 'all_members',
            'filters' => [],
            'status' => 'stopped',
        ]);

        $schedule = CampaignSchedule::create([
            'campaign_id' => $campaign->id,
            'schedule_type' => 'recurring',
            'start_date' => now()->toDateString(),
            'send_time' => '12:00:00',
            'timezone' => 'UTC',
            'recurrence_type' => 'daily',
            'frequency_interval' => 1,
            'next_run_at' => null,
        ]);

        $response = $this->post(route('admin.campaigns.retry', $campaign));
        $response->assertRedirect(route('admin.campaigns.show', $campaign));

        $campaign->refresh();
        $this->assertEquals('active', $campaign->status);
        $this->assertNotNull($campaign->schedule->next_run_at);
    }
}
