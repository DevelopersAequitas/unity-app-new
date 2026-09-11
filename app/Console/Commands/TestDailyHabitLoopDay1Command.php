<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendDailyHabitWhatsappJob;
use App\Models\Notifications\DailyHabitSend;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestDailyHabitLoopDay1Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'habit-loop:test-day1 {user_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Local/Development testing only: Dispatch Day 1 Habit Loop WhatsApp message for a user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->argument('user_id');

        if ($userId) {
            $user = User::find($userId);
            if (! $user) {
                $this->error("User not found with ID: {$userId}");

                return 1;
            }
        } else {
            $user = User::query()
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->whereNotNull('first_name')
                ->where('first_name', '!=', '')
                ->first();

            if (! $user) {
                $this->error('No eligible user found with a valid phone number and first name.');

                return 1;
            }
        }

        // Must have valid phone
        $phone = $user->phone ?? $user->secondary_mobile;
        if (! $phone) {
            $this->error("User {$user->first_name} does not have a phone or secondary mobile number configured.");

            return 1;
        }

        // 2. Load the real day_1_complete_profile record from whatsapp_templates
        $template = WhatsappTemplate::where('template_key', 'day_1_complete_profile')
            ->where('is_active', true)
            ->first();

        if (! $template) {
            $this->error("Active 'day_1_complete_profile' WhatsApp template not found in database.");

            return 1;
        }

        // 3. Create/update only the Daily Habit Loop tracking record needed for this local test.
        // We delete any existing Day 1 send record to keep it clean and prevent duplicate check block.
        DailyHabitSend::where('user_id', $user->id)
            ->where('day_number', 1)
            ->delete();

        $sendRecord = DailyHabitSend::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'journey_started_at' => now(),
            'day_number' => 1,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        $this->info("Resetting/scheduling Day 1 tracking record for User: {$user->first_name}");

        // 4. Dispatch the job synchronously
        try {
            dispatch_sync(new SendDailyHabitWhatsappJob($sendRecord->id));
            $sendRecord->refresh();
            $statusStr = $sendRecord->status;
        } catch (\Exception $e) {
            $statusStr = 'failed: '.$e->getMessage();
        }

        // Mask the secret key
        $secret = (string) $template->webhook_secret;
        $maskedSecret = strlen($secret) > 4
            ? substr($secret, 0, 2).str_repeat('*', strlen($secret) - 4).substr($secret, -2)
            : str_repeat('*', strlen($secret));

        // 5. Print the requested parameters:
        $this->info("User ID: {$user->id}");
        $this->info("User Name: {$user->first_name} ".($user->last_name ?? ''));
        $this->info("User Phone: {$phone}");
        $this->info("Template Key: {$template->template_key}");
        $this->info("Template Name: {$template->template_name}");
        $this->info("Webhook URL: {$template->webhook_url}");
        $this->info("Webhook Secret (Masked): {$maskedSecret}");
        $this->info("Scheduled At: {$sendRecord->scheduled_at->toIso8601String()}");
        $this->info("Job Dispatched Status: {$statusStr}");

        return $statusStr === 'sent' ? 0 : 1;
    }
}
