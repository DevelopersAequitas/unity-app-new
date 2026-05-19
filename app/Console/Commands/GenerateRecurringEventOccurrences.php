<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventOccurrence;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GenerateRecurringEventOccurrences extends Command
{
    protected $signature = 'events:recurring:generate';

    protected $description = 'Generate event occurrences for recurring and non-recurring events.';

    public function handle(): int
    {
        $generated = 0;
        $skipped = 0;

        Event::query()
            ->with('occurrences')
            ->chunkById(100, function ($events) use (&$generated, &$skipped): void {
                foreach ($events as $event) {
                    $starts = $this->buildStarts($event);
                    $durationSeconds = max(0, CarbonImmutable::parse($event->end_at ?? $event->start_at)->diffInSeconds(CarbonImmutable::parse($event->start_at), true));

                    foreach ($starts as $startAt) {
                        $exists = EventOccurrence::query()
                            ->where('event_id', $event->id)
                            ->where('start_at', $startAt)
                            ->exists();

                        if ($exists) {
                            $skipped++;
                            continue;
                        }

                        $payload = [
                            'event_id' => $event->id,
                            'start_at' => $startAt,
                            'end_at' => $durationSeconds > 0 ? $startAt->addSeconds($durationSeconds) : null,
                            'status' => 'scheduled',
                        ];

                        if (Schema::hasColumn('event_occurrences', 'occurrence_date')) {
                            $payload['occurrence_date'] = $startAt->toDateString();
                        }
                        if (Schema::hasColumn('event_occurrences', 'sequence')) {
                            $payload['sequence'] = ((int) EventOccurrence::query()->where('event_id', $event->id)->max('sequence')) + 1;
                        }
                        if (Schema::hasColumn('event_occurrences', 'registration_limit')) {
                            $payload['registration_limit'] = $event->registration_limit;
                        }
                        if (Schema::hasColumn('event_occurrences', 'registered_count')) {
                            $payload['registered_count'] = 0;
                        }
                        if (Schema::hasColumn('event_occurrences', 'checked_in_count')) {
                            $payload['checked_in_count'] = 0;
                        }

                        EventOccurrence::query()->create($payload);
                        $generated++;
                    }
                }
            });

        Log::info('events.recurring.generate', [
            'generated_count' => $generated,
            'skipped_duplicates_count' => $skipped,
        ]);

        $this->info("Generated: {$generated}");
        $this->info("Skipped duplicates: {$skipped}");

        return self::SUCCESS;
    }

    private function buildStarts(Event $event): array
    {
        $start = CarbonImmutable::parse($event->start_at);
        $until = min(
            $event->recurrence_ends_at ? CarbonImmutable::parse($event->recurrence_ends_at) : $start->addMonthsNoOverflow(12),
            $start->addMonthsNoOverflow(12)
        );
        $interval = max(1, (int) ($event->recurrence_interval ?: 1));
        $type = (string) ($event->recurrence_type ?: 'none');

        return match ($type) {
            'daily' => $this->daily($start, $until, $interval),
            'weekly' => $this->weekly($start, $until, $interval, $event->recurrence_day_of_week),
            'monthly' => $this->monthly($start, $until, $interval, $event->recurrence_day_of_month, $event->recurrence_week_of_month, $event->recurrence_day_of_week),
            default => [$start],
        };
    }

    private function daily(CarbonImmutable $start, CarbonImmutable $until, int $interval): array
    {
        $dates = [];
        $cursor = $start;
        while ($cursor->lte($until)) {
            $dates[] = $cursor;
            $cursor = $cursor->addDays($interval);
        }

        return $dates;
    }

    private function weekly(CarbonImmutable $start, CarbonImmutable $until, int $interval, ?int $dayOfWeek): array
    {
        $targetDow = $this->normalizeDayOfWeek($dayOfWeek ?? $start->dayOfWeek);
        $cursor = $start->startOfWeek(CarbonInterface::SUNDAY)->addDays($targetDow)->setTimeFrom($start);
        if ($cursor->lt($start)) {
            $cursor = $cursor->addWeek();
        }

        $dates = [];
        while ($cursor->lte($until)) {
            $dates[] = $cursor;
            $cursor = $cursor->addWeeks($interval);
        }

        return $dates;
    }

    private function monthly(CarbonImmutable $start, CarbonImmutable $until, int $interval, ?int $dayOfMonth, ?int $weekOfMonth, ?int $dayOfWeek): array
    {
        $dates = [];
        $cursor = $start->startOfMonth();
        while ($cursor->lte($until)) {
            $candidate = $this->monthlyCandidate($cursor, $start, $dayOfMonth, $weekOfMonth, $dayOfWeek);
            if ($candidate && $candidate->gte($start) && $candidate->lte($until)) {
                $dates[] = $candidate;
            }
            $cursor = $cursor->addMonthsNoOverflow($interval);
        }

        return $dates;
    }

    private function normalizeDayOfWeek(int $dayOfWeek): int
    {
        return $dayOfWeek === 7 ? 0 : $dayOfWeek;
    }

    private function monthlyCandidate(CarbonImmutable $month, CarbonImmutable $timeSource, ?int $dayOfMonth, ?int $weekOfMonth, ?int $dayOfWeek): ?CarbonImmutable
    {
        if ($weekOfMonth && $dayOfWeek !== null) {
            $targetDayOfWeek = $this->normalizeDayOfWeek($dayOfWeek);
            $candidate = $month->startOfMonth();
            while ($candidate->dayOfWeek !== $targetDayOfWeek) {
                $candidate = $candidate->addDay();
            }
            $candidate = $candidate->addWeeks($weekOfMonth - 1);

            return $candidate->month === $month->month ? $candidate->setTimeFrom($timeSource) : null;
        }

        $day = min($dayOfMonth ?: $timeSource->day, $month->daysInMonth);

        return $month->day($day)->setTimeFrom($timeSource);
    }
}
