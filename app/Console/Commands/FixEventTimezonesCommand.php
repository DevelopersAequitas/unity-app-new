<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\Events\EventOccurrenceGeneratorService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FixEventTimezonesCommand extends Command
{
    protected $signature = 'events:fix-utc-times
                            {--from-tz=Asia/Kolkata : Timezone that the current database values represent}
                            {--event= : Specific Event ID to convert}
                            {--dry-run : Only show changes without saving}';

    protected $description = 'Converts event timestamps that were stored as local time into proper UTC';

    public function handle(EventOccurrenceGeneratorService $occurrenceGenerator): int
    {
        $fromTz = (string) $this->option('from-tz');
        $eventId = $this->option('event');
        $dryRun = (bool) $this->option('dry-run');

        $query = Event::query();
        if ($eventId) {
            $query->where('id', $eventId);
        }

        $events = $query->get();
        if ($events->isEmpty()) {
            $this->info('No events found to convert.');

            return Command::SUCCESS;
        }

        $this->info("Found {$events->count()} event(s). Converting from {$fromTz} to UTC...");

        foreach ($events as $event) {
            $metadata = is_string($event->metadata) ? json_decode($event->metadata, true) : (array) ($event->metadata ?? []);

            // If metadata already has a timezone flag and was previously converted, skip unless forced
            if (! empty($metadata['timezone_converted']) && ! $eventId) {
                $this->line("Skipping [{$event->id}] {$event->title} (already marked as converted)");

                continue;
            }

            $currentStart = Carbon::parse($event->start_at)->format('Y-m-d H:i:s');
            $currentEnd = $event->end_at ? Carbon::parse($event->end_at)->format('Y-m-d H:i:s') : null;

            // Treat current string as local time in $fromTz, and convert to UTC
            $utcStart = Carbon::parse($currentStart, $fromTz)->utc();
            $utcEnd = $currentEnd ? Carbon::parse($currentEnd, $fromTz)->utc() : null;

            $this->line("Event [{$event->id}] \"{$event->title}\":");
            $this->line("  Start: {$currentStart} ({$fromTz}) -> {$utcStart->toDateTimeString()} (UTC)");
            if ($currentEnd) {
                $this->line("  End:   {$currentEnd} ({$fromTz}) -> {$utcEnd->toDateTimeString()} (UTC)");
            }

            if (! $dryRun) {
                $metadata['timezone'] = $fromTz;
                $metadata['timezone_converted'] = true;
                $metadata['original_local_start'] = $currentStart;

                $event->start_at = $utcStart;
                $event->end_at = $utcEnd;
                $event->metadata = $metadata;
                $event->save();

                $occurrenceGenerator->generate($event);
                $this->info('  Saved and occurrences regenerated.');
            }
        }

        $this->info($dryRun ? 'Dry run complete. No changes made.' : 'All event timezones updated to UTC successfully.');

        return Command::SUCCESS;
    }
}
