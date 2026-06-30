<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\AdminCampaign;
use App\Models\CampaignSchedule;
use App\Models\CampaignDelivery;
use App\Jobs\ProcessCampaignDeliveryJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;

class RunScheduledCampaignsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('campaign_deliveries');
        Schema::dropIfExists('campaign_schedules');
        Schema::dropIfExists('admin_campaigns');

        Schema::create('admin_campaigns', function ($table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('campaign_type');
            $table->string('audience_type');
            $table->json('filters')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('campaign_schedules', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('campaign_id');
            $table->string('schedule_type');
            $table->date('start_date');
            $table->string('send_time');
            $table->string('timezone');
            $table->string('recurrence_type')->nullable();
            $table->integer('frequency_interval')->nullable();
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
            $table->string('triggered_by', 50)->default('scheduler');
            $table->timestamps();
        });

    }

    public function test_run_scheduled_campaigns_artisan_command()
    {
        Queue::fake();

        // 1. Create a Campaign Template
        $campaign = AdminCampaign::create([
            'title' => 'Test Automation Campaign',
            'campaign_type' => 'email_only',
            'audience_type' => 'all_members',
            'filters' => [],
            'status' => 'active', // Active status
        ]);

        // 2. Create a Schedule due now
        $schedule = CampaignSchedule::create([
            'campaign_id' => $campaign->id,
            'schedule_type' => 'recurring',
            'recurrence_type' => 'daily',
            'frequency_interval' => 1,
            'start_date' => now()->subDays(2)->toDateString(),
            'send_time' => '09:00:00',
            'timezone' => 'UTC',
            'next_run_at' => now()->subMinutes(5), // Due 5 mins ago
        ]);

        // 3. Call the Artisan Command
        $this->artisan('campaigns:run')
            ->expectsOutputToContain("Triggering campaign 'Test Automation Campaign'")
            ->assertExitCode(0);

        // 4. Assert a CampaignDelivery run was created in the database
        $this->assertDatabaseHas('campaign_deliveries', [
            'campaign_id' => $campaign->id,
            'schedule_id' => $schedule->id,
            'status' => 'scheduled',
        ]);

        // 5. Assert the next run was updated in the database
        $schedule->refresh();
        $this->assertNotNull($schedule->next_run_at);
        $this->assertTrue($schedule->next_run_at->gt(now())); // Pushed to the future (tomorrow at 09:00)

        // 6. Assert the processing job was dispatched
        $delivery = CampaignDelivery::where('campaign_id', $campaign->id)->first();
        $this->assertNotNull($delivery);
        Queue::assertPushed(ProcessCampaignDeliveryJob::class, function ($job) use ($delivery) {
            // Check that it's processing the correct delivery
            $reflection = new \ReflectionClass($job);
            $property = $reflection->getProperty('deliveryId');
            $property->setAccessible(true);
            return $property->getValue($job) === $delivery->id;
        });
    }

    public function test_ignores_draft_campaigns()
    {
        Queue::fake();

        // 1. Create a Draft Campaign
        $campaign = AdminCampaign::create([
            'title' => 'Draft Campaign',
            'campaign_type' => 'email_only',
            'audience_type' => 'all_members',
            'filters' => [],
            'status' => 'draft', // Draft status
        ]);

        // 2. Create a Schedule due now
        $schedule = CampaignSchedule::create([
            'campaign_id' => $campaign->id,
            'schedule_type' => 'recurring',
            'recurrence_type' => 'daily',
            'frequency_interval' => 1,
            'start_date' => now()->subDays(2)->toDateString(),
            'send_time' => '09:00:00',
            'timezone' => 'UTC',
            'next_run_at' => now()->subMinutes(5),
        ]);

        // 3. Call the Artisan Command
        $this->artisan('campaigns:run')
            ->expectsOutputToContain("No campaign schedules due at this time.")
            ->assertExitCode(0);

        // 4. Assert NO CampaignDelivery run was created
        $this->assertDatabaseMissing('campaign_deliveries', [
            'campaign_id' => $campaign->id,
        ]);

        // 5. Assert job was not dispatched
        Queue::assertNotPushed(ProcessCampaignDeliveryJob::class);
    }
}
