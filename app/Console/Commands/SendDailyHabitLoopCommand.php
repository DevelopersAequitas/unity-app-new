<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendDailyHabitWhatsappJob;
use App\Models\Notifications\DailyHabitSend;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDailyHabitLoopCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'habit-loop:send-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send due Phase 2 30-Day Daily Habit Loop WhatsApp messages';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();
        $dueSends = DailyHabitSend::query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', $now)
            ->get();

        $dispatchedCount = 0;
        foreach ($dueSends as $send) {
            SendDailyHabitWhatsappJob::dispatch($send->id);
            $dispatchedCount++;
        }

        $this->info("Dispatched {$dispatchedCount} Daily Habit Loop WhatsApp jobs.");
        Log::info('Daily Habit Loop dispatch command finished.', [
            'dispatched_count' => $dispatchedCount,
        ]);

        return 0;
    }
}
