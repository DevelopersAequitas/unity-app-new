<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\Events\EventOccurrenceGeneratorService;
use Illuminate\Console\Command;

class SyncEventOccurrencesCommand extends Command
{
    protected $signature = 'events:sync-occurrences
                            {--event= : Specific Event ID to synchronize occurrences for}';

    protected $description = 'Synchronizes and regenerates event occurrences matching the latest event schedule and start date';

    public function handle(EventOccurrenceGeneratorService $occurrenceGenerator): int
    {
        $eventId = $this->option('event');

        $query = Event::query();
        if ($eventId) {
            $query->where('id', (string) $eventId);
        }

        $events = $query->get();
        if ($events->isEmpty()) {
            $this->info('No events found to synchronize.');

            return Command::SUCCESS;
        }

        $this->info("Found {$events->count()} event(s). Synchronizing occurrences...");

        foreach ($events as $event) {
            $this->line("Synchronizing event [{$event->id}] \"{$event->title}\" (start: {$event->start_at}, recurrence: {$event->recurrence_type})...");
            $occurrenceGenerator->regenerateFuture($event);
            $this->info("  Done. Upcoming occurrences regenerated for [{$event->id}].");
        }

        $this->info('Event occurrences synchronization complete.');

        return Command::SUCCESS;
    }
}
