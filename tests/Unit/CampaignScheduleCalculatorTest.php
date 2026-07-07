<?php

namespace Tests\Unit;

use App\Models\CampaignSchedule;
use App\Services\AdminCampaigns\CampaignScheduleCalculator;
use Carbon\Carbon;
use Tests\TestCase;

class CampaignScheduleCalculatorTest extends TestCase
{
    protected CampaignScheduleCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new CampaignScheduleCalculator;
    }

    public function test_calculate_schedule_once()
    {
        $schedule = new CampaignSchedule([
            'schedule_type' => 'once',
            'start_date' => '2026-06-15',
            'send_time' => '09:00:00',
            'timezone' => 'UTC',
        ]);

        $nextRun = $this->calculator->calculateNextRunAt($schedule, Carbon::parse('2026-06-12 12:00:00', 'UTC'));

        $this->assertNotNull($nextRun);
        $this->assertEquals('2026-06-15 09:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_schedule_once_in_past()
    {
        $schedule = new CampaignSchedule([
            'schedule_type' => 'once',
            'start_date' => '2026-06-10',
            'send_time' => '09:00:00',
            'timezone' => 'UTC',
        ]);

        $nextRun = $this->calculator->calculateNextRunAt($schedule, Carbon::parse('2026-06-12 12:00:00', 'UTC'));

        $this->assertNotNull($nextRun);
        // Since it has never run, it executes today (June 12) instead of skipping to tomorrow
        $this->assertEquals('2026-06-12 09:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_recurrence_daily_every_day()
    {
        $schedule = new CampaignSchedule([
            'schedule_type' => 'recurring',
            'recurrence_type' => 'daily',
            'frequency_interval' => 1,
            'start_date' => '2026-06-15',
            'send_time' => '09:00:00',
            'timezone' => 'UTC',
            'last_run_at' => '2026-06-15 09:00:00',
        ]);

        $nextRun = $this->calculator->calculateNextRunAt($schedule, Carbon::parse('2026-06-15 10:00:00', 'UTC'));

        $this->assertNotNull($nextRun);
        // Has run, so it advances to tomorrow
        $this->assertEquals('2026-06-16 09:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_recurrence_daily_every_3_days()
    {
        $schedule = new CampaignSchedule([
            'schedule_type' => 'recurring',
            'recurrence_type' => 'daily',
            'frequency_interval' => 3,
            'start_date' => '2026-06-15',
            'send_time' => '09:00:00',
            'timezone' => 'UTC',
            'last_run_at' => '2026-06-15 09:00:00',
        ]);

        $nextRun = $this->calculator->calculateNextRunAt($schedule, Carbon::parse('2026-06-15 10:00:00', 'UTC'));

        $this->assertNotNull($nextRun);
        $this->assertEquals('2026-06-18 09:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_recurrence_weekly()
    {
        $schedule = new CampaignSchedule([
            'schedule_type' => 'recurring',
            'recurrence_type' => 'weekly',
            'frequency_interval' => 2, // Every 2 weeks
            'weekdays' => 'Monday,Friday',
            'start_date' => '2026-06-15', // This is a Monday
            'send_time' => '09:00:00',
            'timezone' => 'UTC',
            'last_run_at' => '2026-06-15 09:00:00',
        ]);

        $nextRun = $this->calculator->calculateNextRunAt($schedule, Carbon::parse('2026-06-15 10:00:00', 'UTC'));

        $this->assertNotNull($nextRun);
        $this->assertEquals('2026-06-19 09:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_recurrence_weekly_next_cycle()
    {
        $schedule = new CampaignSchedule([
            'schedule_type' => 'recurring',
            'recurrence_type' => 'weekly',
            'frequency_interval' => 2, // Every 2 weeks
            'weekdays' => 'Monday,Friday',
            'start_date' => '2026-06-15', // Monday
            'send_time' => '09:00:00',
            'timezone' => 'UTC',
            'last_run_at' => '2026-06-19 09:00:00',
        ]);

        $nextRun = $this->calculator->calculateNextRunAt($schedule, Carbon::parse('2026-06-19 10:00:00', 'UTC'));

        $this->assertNotNull($nextRun);
        $this->assertEquals('2026-06-29 09:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_monthly_by_date()
    {
        $schedule = new CampaignSchedule([
            'schedule_type' => 'recurring',
            'recurrence_type' => 'monthly',
            'frequency_interval' => 1,
            'monthly_basis' => 'date',
            'monthly_day_of_month' => 15,
            'start_date' => '2026-06-01',
            'send_time' => '09:00:00',
            'timezone' => 'UTC',
        ]);

        // Evaluated before 15th
        $nextRun = $this->calculator->calculateNextRunAt($schedule, Carbon::parse('2026-06-12 12:00:00', 'UTC'));
        $this->assertNotNull($nextRun);
        $this->assertEquals('2026-06-15 09:00:00', $nextRun->format('Y-m-d H:i:s'));

        // Evaluated on 15th after send time, with last_run_at set to test advancement
        $schedule->last_run_at = '2026-06-15 09:00:00';
        $nextRun2 = $this->calculator->calculateNextRunAt($schedule, Carbon::parse('2026-06-15 10:00:00', 'UTC'));
        $this->assertNotNull($nextRun2);
        $this->assertEquals('2026-07-15 09:00:00', $nextRun2->format('Y-m-d H:i:s'));
    }

    public function test_calculate_monthly_by_position()
    {
        $schedule = new CampaignSchedule([
            'schedule_type' => 'recurring',
            'recurrence_type' => 'monthly',
            'frequency_interval' => 1,
            'monthly_basis' => 'position',
            'monthly_position' => 'last',
            'monthly_day_of_week' => 'Friday',
            'start_date' => '2026-06-01',
            'send_time' => '09:00:00',
            'timezone' => 'UTC',
        ]);

        // June 2026 last Friday is June 26
        $nextRun = $this->calculator->calculateNextRunAt($schedule, Carbon::parse('2026-06-12 12:00:00', 'UTC'));
        $this->assertNotNull($nextRun);
        $this->assertEquals('2026-06-26 09:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_cycle_on_off()
    {
        $schedule = new CampaignSchedule([
            'schedule_type' => 'recurring',
            'recurrence_type' => 'cycle',
            'cycle_send_days' => 2,
            'cycle_pause_days' => 2,
            'start_date' => '2026-06-15', // Monday
            'send_time' => '09:00:00',
            'timezone' => 'UTC',
            'last_run_at' => '2026-06-16 09:00:00',
        ]);

        $nextRun = $this->calculator->calculateNextRunAt($schedule, Carbon::parse('2026-06-16 10:00:00', 'UTC'));
        $this->assertNotNull($nextRun);
        $this->assertEquals('2026-06-19 09:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_timezone_conversion()
    {
        $schedule = new CampaignSchedule([
            'schedule_type' => 'once',
            'start_date' => '2026-06-15',
            'send_time' => '09:00:00',
            'timezone' => 'Asia/Kolkata', // GMT+05:30
        ]);

        $nextRun = $this->calculator->calculateNextRunAt($schedule, Carbon::parse('2026-06-12 12:00:00', 'UTC'));

        $this->assertNotNull($nextRun);
        $this->assertEquals('2026-06-15 03:30:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_respects_end_date_limit()
    {
        $schedule = new CampaignSchedule([
            'schedule_type' => 'recurring',
            'recurrence_type' => 'daily',
            'frequency_interval' => 1,
            'start_date' => '2026-06-15',
            'send_time' => '09:00:00',
            'timezone' => 'UTC',
            'end_type' => 'date',
            'end_date' => '2026-06-16',
            'last_run_at' => '2026-06-16 09:00:00',
        ]);

        $nextRun = $this->calculator->calculateNextRunAt($schedule, Carbon::parse('2026-06-16 10:00:00', 'UTC'));
        $this->assertNull($nextRun);
    }

    public function test_calculate_send_immediately()
    {
        $schedule = new CampaignSchedule([
            'schedule_type' => 'immediately',
            'timezone' => 'UTC',
        ]);

        $evalTime = Carbon::parse('2026-06-12 12:00:00', 'UTC');
        $nextRun = $this->calculator->calculateNextRunAt($schedule, $evalTime);

        $this->assertNotNull($nextRun);
        $this->assertEquals($evalTime->format('Y-m-d H:i:s'), $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_schedule_once_with_two_part_send_time()
    {
        $schedule = new CampaignSchedule([
            'schedule_type' => 'once',
            'start_date' => '2026-06-15',
            'send_time' => '09:30',
            'timezone' => 'UTC',
        ]);

        $nextRun = $this->calculator->calculateNextRunAt($schedule, Carbon::parse('2026-06-12 12:00:00', 'UTC'));

        $this->assertNotNull($nextRun);
        $this->assertEquals('2026-06-15 09:30:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_recurrence_with_invalid_timezone()
    {
        $schedule = new CampaignSchedule([
            'schedule_type' => 'once',
            'start_date' => '2026-06-15',
            'send_time' => '09:00:00',
            'timezone' => 'Invalid/Timezone_Name',
        ]);

        $nextRun = $this->calculator->calculateNextRunAt($schedule, Carbon::parse('2026-06-12 12:00:00', 'UTC'));

        $this->assertNotNull($nextRun);
        $this->assertEquals('2026-06-15 09:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_recurrence_with_zero_frequency_interval()
    {
        $schedule = new CampaignSchedule([
            'schedule_type' => 'recurring',
            'recurrence_type' => 'daily',
            'frequency_interval' => 0,
            'start_date' => '2026-06-15',
            'send_time' => '09:00:00',
            'timezone' => 'UTC',
            'last_run_at' => '2026-06-15 09:00:00',
        ]);

        $nextRun = $this->calculator->calculateNextRunAt($schedule, Carbon::parse('2026-06-15 10:00:00', 'UTC'));

        $this->assertNotNull($nextRun);
        $this->assertEquals('2026-06-16 09:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_recurrence_cycle_with_zero_cycle_length()
    {
        $schedule = new CampaignSchedule([
            'schedule_type' => 'recurring',
            'recurrence_type' => 'cycle',
            'cycle_send_days' => 0,
            'cycle_pause_days' => -2,
            'start_date' => '2026-06-15',
            'send_time' => '09:00:00',
            'timezone' => 'UTC',
            'last_run_at' => '2026-06-15 09:00:00',
        ]);

        $nextRun = $this->calculator->calculateNextRunAt($schedule, Carbon::parse('2026-06-15 10:00:00', 'UTC'));

        $this->assertNotNull($nextRun);
        $this->assertEquals('2026-06-16 09:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_first_run_execution_no_skip()
    {
        $schedule = new CampaignSchedule([
            'schedule_type' => 'recurring',
            'recurrence_type' => 'daily',
            'frequency_interval' => 1,
            'start_date' => '2026-06-12',
            'send_time' => '17:20:00',
            'timezone' => 'Asia/Kolkata',
        ]);

        $nextRun = $this->calculator->calculateNextRunAt($schedule, Carbon::parse('2026-06-12 11:55:00', 'UTC'));
        $this->assertNotNull($nextRun);
        $this->assertEquals('2026-06-12 11:50:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_schedule_once_already_run_returns_null()
    {
        $schedule = new CampaignSchedule([
            'schedule_type' => 'once',
            'start_date' => '2026-06-15',
            'send_time' => '09:00:00',
            'timezone' => 'UTC',
            'last_run_at' => '2026-06-15 09:00:00',
        ]);

        $nextRun = $this->calculator->calculateNextRunAt($schedule, Carbon::parse('2026-06-15 10:00:00', 'UTC'));
        $this->assertNull($nextRun);
    }
}
