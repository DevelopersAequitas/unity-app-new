<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MemberJourneySend;
use App\Services\WhatsApp\MemberJourney30DayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessMemberJourneySendsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:journey:process {--user= : Process due sends for a specific User ID only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process and dispatch scheduled 30-Day WhatsApp Member Onboarding Journey messages';

    /**
     * Execute the console command.
     */
    public function handle(MemberJourney30DayService $journeyService): int
    {
        $specificUserId = $this->option('user');

        if ($specificUserId) {
            $this->info("Processing due journey sends specifically for user: {$specificUserId}...");

            $dueSends = MemberJourneySend::where('user_id', $specificUserId)
                ->where('status', 'scheduled')
                ->where('scheduled_at', '<=', now())
                ->with('user')
                ->orderBy('scheduled_at')
                ->get();

            $total = $dueSends->count();
            $sent = 0;
            $skipped = 0;
            $failed = 0;

            foreach ($dueSends as $send) {
                $result = $journeyService->sendDay($send);
                if ($result['status'] === 'sent') {
                    $sent++;
                    $this->info("  [SENT] Day {$send->day_number}: {$result['message']}");
                } elseif ($result['status'] === 'skipped') {
                    $skipped++;
                    $this->warn("  [SKIPPED] Day {$send->day_number}: {$result['message']}");
                } else {
                    $failed++;
                    $this->error("  [FAILED] Day {$send->day_number}: {$result['message']}");
                }
            }

            $this->info("User dispatch complete: {$sent} sent, {$skipped} skipped, {$failed} failed (Total: {$total}).");

            return 0;
        }

        $this->info('Starting scheduled 30-Day WhatsApp Journey processor...');

        $stats = $journeyService->processDueSends();

        $this->info("Process completed. Total Due: {$stats['total_due']} | Sent: {$stats['sent']} | Skipped: {$stats['skipped']} | Failed: {$stats['failed']}");

        Log::info('[ProcessMemberJourneySendsCommand] Processed due 30-Day WhatsApp journey messages.', $stats);

        return 0;
    }
}
