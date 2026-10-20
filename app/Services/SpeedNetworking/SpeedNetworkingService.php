<?php

declare(strict_types=1);

namespace App\Services\SpeedNetworking;

use App\Models\Connection;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\SpeedNetworking\SpeedNetworkingMatch;
use App\Models\SpeedNetworking\SpeedNetworkingParticipant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SpeedNetworkingService
{
    public function __construct(
        protected AgoraTokenService $agoraTokenService
    ) {}

    /**
     * Get paginated list of Speed Networking events for Admin panel.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listEvents(array $filters = [], int $perPage = 15): array
    {
        $query = SpeedNetworkingEvent::query()
            ->withCount([
                'participants',
                'participants as active_participants_count' => function ($q): void {
                    $q->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT);
                },
                'matches',
            ])
            ->latest('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.trim((string) $filters['search']).'%';
            $query->where('title', 'like', $search);
        }

        $paginated = $query->paginate($perPage);

        return [
            'data' => $paginated->items(),
            'pagination' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ];
    }

    /**
     * Get detailed event overview with stats for Admin panel.
     *
     * @return array<string, mixed>
     */
    public function getEventDetails(SpeedNetworkingEvent $event): array
    {
        $totalParticipants = $event->participants()->count();
        $activeParticipants = $event->participants()
            ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
            ->count();
        $totalMatches = $event->matches()->count();
        $activeMatches = $event->matches()
            ->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)
            ->count();

        return [
            'event' => $event,
            'stats' => [
                'total_participants' => $totalParticipants,
                'active_participants' => $activeParticipants,
                'total_matches' => $totalMatches,
                'active_matches' => $activeMatches,
            ],
        ];
    }

    /**
     * Delete (soft delete) a Speed Networking event.
     */
    public function deleteEvent(SpeedNetworkingEvent $event): void
    {
        $event->delete();
    }

    /**
     * Get participants in an event for Admin panel.
     *
     * @return array<string, mixed>
     */
    public function getEventParticipants(SpeedNetworkingEvent $event, int $perPage = 25): array
    {
        $paginated = SpeedNetworkingParticipant::with('user')
            ->where('event_id', $event->id)
            ->orderBy('joined_at', 'desc')
            ->paginate($perPage);

        $now = Carbon::now();
        $items = collect($paginated->items())->map(function (SpeedNetworkingParticipant $p) use ($now): array {
            $u = $p->user;
            $name = $u ? ($u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? ''))) : 'Unknown';

            $isOnline = $p->last_heartbeat_at && $p->last_heartbeat_at->diffInSeconds($now) <= 60;

            return [
                'participant_id' => (string) $p->id,
                'user_id' => (string) $p->user_id,
                'name' => $name,
                'company' => (string) ($u?->company_name ?? ''),
                'designation' => (string) ($u?->designation ?? ''),
                'city' => (string) ($u?->city ?? ''),
                'avatar_url' => $u?->profile_photo_url ?? '',
                'status' => $p->status,
                'is_online' => $isOnline,
                'joined_at' => $p->joined_at?->toIso8601String(),
                'last_heartbeat_at' => $p->last_heartbeat_at?->toIso8601String(),
            ];
        });

        return [
            'data' => $items,
            'pagination' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ];
    }

    /**
     * Create or update a Speed Networking event.
     *
     * @param  array<string, mixed>  $data
     */
    public function createOrUpdateEvent(array $data): SpeedNetworkingEvent
    {
        $id = $data['id'] ?? null;

        $attributes = [
            'title' => (string) $data['title'],
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'banner_url' => isset($data['banner_url']) ? (string) $data['banner_url'] : null,
            'round_duration_seconds' => (int) ($data['round_duration_seconds'] ?? config('services.speed_networking.default_round_seconds', 300)),
            'break_duration_seconds' => (int) ($data['break_duration_seconds'] ?? config('services.speed_networking.default_break_seconds', 20)),
            'total_rounds' => (int) ($data['total_rounds'] ?? 5),
            'scheduled_start_time' => ! empty($data['scheduled_start_time']) ? Carbon::parse($data['scheduled_start_time']) : null,
        ];

        if ($id) {
            $event = SpeedNetworkingEvent::findOrFail($id);
            $event->update($attributes);

            return $event;
        }

        $attributes['status'] = ! empty($attributes['scheduled_start_time'])
            ? SpeedNetworkingEvent::STATUS_SCHEDULED
            : SpeedNetworkingEvent::STATUS_DRAFT;
        $attributes['current_round'] = 0;

        return SpeedNetworkingEvent::create($attributes);
    }

    /**
     * Start a Speed Networking event and generate Round 1 pairings.
     */
    public function startEvent(SpeedNetworkingEvent $event): SpeedNetworkingEvent
    {
        return DB::transaction(function () use ($event): SpeedNetworkingEvent {
            $event->status = SpeedNetworkingEvent::STATUS_IN_PROGRESS;
            $event->actual_start_time = Carbon::now();
            $event->current_round = 1;
            $event->save();

            $this->generateMatchesForRound($event, 1);

            return $event->fresh(['participants', 'matches']);
        });
    }

    /**
     * Advance the event to the next round.
     */
    public function nextRound(SpeedNetworkingEvent $event): SpeedNetworkingEvent
    {
        return DB::transaction(function () use ($event): SpeedNetworkingEvent {
            // Mark active matches of the previous round as completed
            SpeedNetworkingMatch::where('event_id', $event->id)
                ->where('round_number', $event->current_round)
                ->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)
                ->update([
                    'status' => SpeedNetworkingMatch::STATUS_COMPLETED,
                    'ended_at' => Carbon::now(),
                ]);

            $nextRound = $event->current_round + 1;

            if ($nextRound > $event->total_rounds) {
                $event->status = SpeedNetworkingEvent::STATUS_COMPLETED;
                $event->save();

                return $event;
            }

            $event->current_round = $nextRound;
            $event->save();

            $this->generateMatchesForRound($event, $nextRound);

            return $event->fresh(['participants', 'matches']);
        });
    }

    /**
     * End a Speed Networking event.
     */
    public function endEvent(SpeedNetworkingEvent $event): SpeedNetworkingEvent
    {
        return DB::transaction(function () use ($event): SpeedNetworkingEvent {
            $event->status = SpeedNetworkingEvent::STATUS_COMPLETED;
            $event->save();

            SpeedNetworkingMatch::where('event_id', $event->id)
                ->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)
                ->update([
                    'status' => SpeedNetworkingMatch::STATUS_COMPLETED,
                    'ended_at' => Carbon::now(),
                ]);

            return $event;
        });
    }

    /**
     * User joins event lobby or active round.
     *
     * @return array<string, mixed>
     */
    public function joinEvent(SpeedNetworkingEvent $event, User $user): array
    {
        return DB::transaction(function () use ($event, $user): array {
            $participant = SpeedNetworkingParticipant::updateOrCreate(
                [
                    'event_id' => $event->id,
                    'user_id' => $user->id,
                ],
                [
                    'status' => $event->status === SpeedNetworkingEvent::STATUS_IN_PROGRESS
                        ? SpeedNetworkingParticipant::STATUS_IN_CALL
                        : SpeedNetworkingParticipant::STATUS_LOBBY,
                    'joined_at' => Carbon::now(),
                    'last_heartbeat_at' => Carbon::now(),
                ]
            );

            // If event is scheduled or draft, open lobby if appropriate
            if (in_array($event->status, [SpeedNetworkingEvent::STATUS_DRAFT, SpeedNetworkingEvent::STATUS_SCHEDULED], true)) {
                $event->status = SpeedNetworkingEvent::STATUS_LOBBY_OPEN;
                $event->save();
            }

            // If event is not in progress or no rounds yet, return lobby payload
            if ($event->status !== SpeedNetworkingEvent::STATUS_IN_PROGRESS || $event->current_round < 1) {
                return [
                    'event_id' => (string) $event->id,
                    'title' => $event->title,
                    'event_title' => $event->title,
                    'round_duration_seconds' => (int) $event->round_duration_seconds,
                    'break_duration_seconds' => (int) $event->break_duration_seconds,
                    'remaining_seconds' => (int) $event->round_duration_seconds,
                    'current_round' => 0,
                    'total_rounds' => (int) $event->total_rounds,
                    'channel_id' => '',
                    'agora_token' => '',
                    'is_break_round' => false,
                    'has_met_all_peers' => false,
                    'peer' => null,
                ];
            }

            // In progress: look for user's match in current round
            $match = $this->findMatchForUser($event->id, $event->current_round, (string) $user->id);

            if (! $match) {
                // If no matches were generated yet for this round, generate them now
                $matchCount = SpeedNetworkingMatch::where('event_id', $event->id)
                    ->where('round_number', $event->current_round)
                    ->count();

                if ($matchCount === 0) {
                    $this->generateMatchesForRound($event, $event->current_round);
                    $match = $this->findMatchForUser($event->id, $event->current_round, (string) $user->id);
                } else {
                    // Pair with any unpaired active participant or create break match
                    $match = $this->pairLateParticipant($event, $user);
                }
            }

            if ($match) {
                return $this->formatMatchResponse($event, $match, (string) $user->id);
            }

            $hasMetAll = $this->hasMetAllActivePeers($event, (string) $user->id);

            return $this->formatBreakRoundResponse($event, $event->current_round, $hasMetAll);
        });
    }

    /**
     * Check if a participant has met all other currently active attendees in this event.
     */
    public function hasMetAllActivePeers(SpeedNetworkingEvent $event, string $userId): bool
    {
        $otherActiveUserIds = SpeedNetworkingParticipant::where('event_id', $event->id)
            ->where('user_id', '!=', $userId)
            ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
            ->where(function ($q): void {
                $q->where('last_heartbeat_at', '>=', Carbon::now()->subSeconds(45))
                    ->orWhere('joined_at', '>=', Carbon::now()->subSeconds(45));
            })
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if (empty($otherActiveUserIds)) {
            return true;
        }

        $metUserIds = SpeedNetworkingMatch::where('event_id', $event->id)
            ->where(function ($q) use ($userId): void {
                $q->where('user_a_id', $userId)
                    ->orWhere('user_b_id', $userId);
            })
            ->whereNotNull('user_b_id')
            ->get()
            ->map(fn (SpeedNetworkingMatch $m) => $m->user_a_id === $userId ? (string) $m->user_b_id : (string) $m->user_a_id)
            ->filter()
            ->unique()
            ->all();

        $unmet = array_diff($otherActiveUserIds, $metUserIds);

        return empty($unmet);
    }

    /**
     * Get user's match for the next round (or current round).
     *
     * @return array<string, mixed>
     */
    public function getNextMatch(SpeedNetworkingEvent $event, User $user): array
    {
        return DB::transaction(function () use ($event, $user): array {
            $this->heartbeat($event, $user);

            // Mark previous active match as completed
            SpeedNetworkingMatch::where('event_id', $event->id)
                ->where(function ($q) use ($user): void {
                    $q->where('user_a_id', $user->id)
                        ->orWhere('user_b_id', $user->id);
                })
                ->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)
                ->update([
                    'status' => SpeedNetworkingMatch::STATUS_COMPLETED,
                    'ended_at' => Carbon::now(),
                ]);

            if ($event->isCompleted()) {
                return [
                    'completed' => true,
                    'message' => 'Speed networking event has concluded.',
                    'has_met_all_peers' => true,
                    'is_break_round' => true,
                    'peer' => null,
                ];
            }

            // Determine target round:
            // Check if user has an existing match for current round
            $currentRound = $event->current_round > 0 ? $event->current_round : 1;
            $match = $this->findMatchForUser($event->id, $currentRound, (string) $user->id);

            // If user has already completed match in current round, advance to next round
            if (! $match || $match->status === SpeedNetworkingMatch::STATUS_COMPLETED) {
                $targetRound = $currentRound + 1;

                if ($targetRound > $event->total_rounds) {
                    return [
                        'completed' => true,
                        'message' => 'Speed networking event has concluded.',
                        'has_met_all_peers' => true,
                        'is_break_round' => true,
                        'peer' => null,
                    ];
                }

                $event->current_round = $targetRound;
                $event->save();

                $match = $this->findMatchForUser($event->id, $targetRound, (string) $user->id);
                if (! $match) {
                    $this->generateMatchesForRound($event, $targetRound);
                    $match = $this->findMatchForUser($event->id, $targetRound, (string) $user->id);
                }
            }

            if (! $match) {
                $hasMetAll = $this->hasMetAllActivePeers($event, (string) $user->id);

                return $this->formatBreakRoundResponse($event, $event->current_round, $hasMetAll);
            }

            return $this->formatMatchResponse($event, $match, (string) $user->id);
        });
    }

    /**
     * Mark participant as left.
     */
    public function leaveEvent(SpeedNetworkingEvent $event, User $user): void
    {
        SpeedNetworkingParticipant::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->update([
                'status' => SpeedNetworkingParticipant::STATUS_LEFT,
                'last_heartbeat_at' => Carbon::now(),
            ]);

        SpeedNetworkingMatch::where('event_id', $event->id)
            ->where(function ($q) use ($user): void {
                $q->where('user_a_id', $user->id)
                    ->orWhere('user_b_id', $user->id);
            })
            ->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)
            ->update([
                'status' => SpeedNetworkingMatch::STATUS_SKIPPED,
                'ended_at' => Carbon::now(),
            ]);
    }

    /**
     * Update participant heartbeat and return active attendee count.
     */
    public function heartbeat(SpeedNetworkingEvent $event, User $user): int
    {
        SpeedNetworkingParticipant::updateOrCreate(
            [
                'event_id' => $event->id,
                'user_id' => $user->id,
            ],
            [
                'last_heartbeat_at' => Carbon::now(),
            ]
        );

        return SpeedNetworkingParticipant::where('event_id', $event->id)
            ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
            ->where(function ($q): void {
                $q->where('last_heartbeat_at', '>=', Carbon::now()->subSeconds(45))
                    ->orWhere('joined_at', '>=', Carbon::now()->subSeconds(45));
            })
            ->count();
    }

    /**
     * Get live tables monitoring data for admin.
     *
     * @return array<string, mixed>
     */
    public function getLiveTables(SpeedNetworkingEvent $event): array
    {
        $currentRound = $event->current_round;

        $matches = SpeedNetworkingMatch::with(['userA', 'userB'])
            ->where('event_id', $event->id)
            ->where('round_number', $currentRound)
            ->orderBy('table_number', 'asc')
            ->get();

        $tables = $matches->map(function (SpeedNetworkingMatch $match): array {
            return [
                'match_id' => (string) $match->id,
                'table_number' => (int) $match->table_number,
                'channel_id' => $match->channel_id,
                'status' => $match->status,
                'is_break_round' => $match->isBreakRound(),
                'user_a' => $match->userA ? [
                    'id' => (string) $match->userA->id,
                    'name' => $match->userA->display_name ?: trim(($match->userA->first_name ?? '').' '.($match->userA->last_name ?? '')),
                    'company' => (string) ($match->userA->company_name ?? ''),
                    'avatar_url' => $match->userA->profile_photo_url ?? '',
                ] : null,
                'user_b' => $match->userB ? [
                    'id' => (string) $match->userB->id,
                    'name' => $match->userB->display_name ?: trim(($match->userB->first_name ?? '').' '.($match->userB->last_name ?? '')),
                    'company' => (string) ($match->userB->company_name ?? ''),
                    'avatar_url' => $match->userB->profile_photo_url ?? '',
                ] : null,
                'started_at' => $match->started_at?->toIso8601String(),
                'ended_at' => $match->ended_at?->toIso8601String(),
            ];
        });

        $totalActive = SpeedNetworkingParticipant::where('event_id', $event->id)
            ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
            ->count();

        return [
            'event_id' => (string) $event->id,
            'event_title' => $event->title,
            'status' => $event->status,
            'current_round' => (int) $event->current_round,
            'total_rounds' => (int) $event->total_rounds,
            'total_active_participants' => $totalActive,
            'tables' => $tables,
        ];
    }

    /**
     * Generate pairings for a specific round ensuring strict non-repeating matches.
     */
    public function generateMatchesForRound(SpeedNetworkingEvent $event, int $roundNumber): void
    {
        // Don't re-generate if matches already exist for this round
        $exists = SpeedNetworkingMatch::where('event_id', $event->id)
            ->where('round_number', $roundNumber)
            ->exists();

        if ($exists) {
            return;
        }

        // Fetch active participants who pinged in the last 45 seconds (or joined recently)
        $participants = SpeedNetworkingParticipant::where('event_id', $event->id)
            ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
            ->where(function ($q): void {
                $q->where('last_heartbeat_at', '>=', Carbon::now()->subSeconds(45))
                    ->orWhere('joined_at', '>=', Carbon::now()->subSeconds(45));
            })
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        $count = count($participants);
        if ($count === 0) {
            return;
        }

        // Build history of already-met pairs in this event
        $previousMatches = SpeedNetworkingMatch::where('event_id', $event->id)
            ->whereNotNull('user_b_id')
            ->get();

        $metPairs = [];
        foreach ($previousMatches as $pm) {
            $metPairs[$pm->user_a_id][$pm->user_b_id] = true;
            $metPairs[$pm->user_b_id][$pm->user_a_id] = true;
        }

        // Randomize order for fair rotation
        shuffle($participants);

        $paired = [];
        $matchesToCreate = [];
        $tableIndex = 1;

        // Pair unmatched participants who have never met in this event
        for ($i = 0; $i < $count; $i++) {
            $u1 = $participants[$i];
            if (isset($paired[$u1])) {
                continue;
            }

            // Find an eligible partner for u1 who has not been met yet
            $partner = null;
            for ($j = $i + 1; $j < $count; $j++) {
                $u2 = $participants[$j];
                if (isset($paired[$u2])) {
                    continue;
                }

                if (empty($metPairs[$u1][$u2])) {
                    $partner = $u2;
                    break;
                }
            }

            if ($partner !== null) {
                $paired[$u1] = true;
                $paired[$partner] = true;

                $matchesToCreate[] = [
                    'event_id' => $event->id,
                    'round_number' => $roundNumber,
                    'table_number' => $tableIndex,
                    'channel_id' => "sn_{$event->id}_r{$roundNumber}_table_{$tableIndex}",
                    'user_a_id' => $u1,
                    'user_b_id' => $partner,
                    'status' => SpeedNetworkingMatch::STATUS_ACTIVE,
                    'started_at' => Carbon::now(),
                ];
                $tableIndex++;
            } else {
                // No unmet partner available -> assign a break round
                $paired[$u1] = true;
                $matchesToCreate[] = [
                    'event_id' => $event->id,
                    'round_number' => $roundNumber,
                    'table_number' => 0,
                    'channel_id' => null,
                    'user_a_id' => $u1,
                    'user_b_id' => null,
                    'status' => SpeedNetworkingMatch::STATUS_ACTIVE,
                    'started_at' => Carbon::now(),
                ];
            }
        }

        foreach ($matchesToCreate as $matchData) {
            SpeedNetworkingMatch::create($matchData);
        }
    }

    /**
     * Find a match for a user in a specific round.
     */
    protected function findMatchForUser(string $eventId, int $roundNumber, string $userId): ?SpeedNetworkingMatch
    {
        return SpeedNetworkingMatch::with(['userA', 'userB'])
            ->where('event_id', $eventId)
            ->where('round_number', $roundNumber)
            ->where(function ($q) use ($userId): void {
                $q->where('user_a_id', $userId)
                    ->orWhere('user_b_id', $userId);
            })
            ->first();
    }

    /**
     * Pair late participant with an unpaired participant or assign a break round.
     */
    protected function pairLateParticipant(SpeedNetworkingEvent $event, User $user): SpeedNetworkingMatch
    {
        // Build list of peers this user has already met in this event
        $metUserIds = SpeedNetworkingMatch::where('event_id', $event->id)
            ->where(function ($q) use ($user): void {
                $q->where('user_a_id', $user->id)
                    ->orWhere('user_b_id', $user->id);
            })
            ->whereNotNull('user_b_id')
            ->get()
            ->map(fn (SpeedNetworkingMatch $m) => $m->user_a_id === (string) $user->id ? (string) $m->user_b_id : (string) $m->user_a_id)
            ->filter()
            ->all();

        // Check if there is an existing break match with an UNMET peer
        $breakMatch = SpeedNetworkingMatch::where('event_id', $event->id)
            ->where('round_number', $event->current_round)
            ->whereNull('user_b_id')
            ->where('user_a_id', '!=', $user->id)
            ->whereNotIn('user_a_id', $metUserIds)
            ->first();

        if ($breakMatch) {
            $tableCount = SpeedNetworkingMatch::where('event_id', $event->id)
                ->where('round_number', $event->current_round)
                ->whereNotNull('channel_id')
                ->count();

            $newTableNumber = $tableCount + 1;
            $channelId = "sn_{$event->id}_r{$event->current_round}_table_{$newTableNumber}";

            $breakMatch->update([
                'user_b_id' => $user->id,
                'table_number' => $newTableNumber,
                'channel_id' => $channelId,
                'started_at' => Carbon::now(),
            ]);

            return $breakMatch->fresh(['userA', 'userB']);
        }

        // Otherwise create a break match for this late user
        return SpeedNetworkingMatch::create([
            'event_id' => $event->id,
            'round_number' => $event->current_round,
            'table_number' => 0,
            'channel_id' => null,
            'user_a_id' => (string) $user->id,
            'user_b_id' => null,
            'status' => SpeedNetworkingMatch::STATUS_ACTIVE,
            'started_at' => Carbon::now(),
        ]);
    }

    /**
     * Format a match into the canonical JSON response.
     *
     * @return array<string, mixed>
     */
    public function formatMatchResponse(SpeedNetworkingEvent $event, SpeedNetworkingMatch $match, string $currentUserId): array
    {
        $isBreak = $match->isBreakRound();
        $peer = $isBreak ? null : $match->getPartner($currentUserId);
        $hasMetAllPeers = $this->hasMetAllActivePeers($event, $currentUserId);

        $token = null;
        if (! $isBreak && $match->channel_id) {
            $token = $this->agoraTokenService->generateToken(
                $match->channel_id,
                0,
                $event->round_duration_seconds + 300
            );
        }

        $peerData = null;
        if ($peer) {
            $peerName = $peer->display_name ?: trim(($peer->first_name ?? '').' '.($peer->last_name ?? ''));
            if (empty($peerName)) {
                $peerName = $peer->name ?? 'Peer';
            }

            $avatarUrl = $peer->profile_photo_url ?? '';

            // Connection status check
            $connection = Connection::where(function ($q) use ($currentUserId, $peer): void {
                $q->where('requester_id', $currentUserId)->where('addressee_id', $peer->id);
            })->orWhere(function ($q) use ($currentUserId, $peer): void {
                $q->where('requester_id', $peer->id)->where('addressee_id', $currentUserId);
            })->first();

            $connectionStatus = 'none';
            if ($connection) {
                $connectionStatus = $connection->is_approved ? 'connected' : 'pending';
            }

            $resolveString = static function (mixed $value): string {
                if (is_null($value)) {
                    return '';
                }
                if (is_array($value)) {
                    return implode(', ', array_filter(array_map('strval', $value)));
                }

                return trim((string) $value);
            };

            $skills = [];
            if (! empty($peer->skills)) {
                if (is_array($peer->skills)) {
                    $skills = array_values(array_filter(array_map(function ($s): string {
                        return is_array($s) ? implode(', ', array_filter(array_map('strval', $s))) : (string) $s;
                    }, $peer->skills)));
                } elseif (is_string($peer->skills)) {
                    $decoded = json_decode($peer->skills, true);
                    if (is_array($decoded)) {
                        $skills = array_values(array_filter(array_map('strval', $decoded)));
                    } else {
                        $skills = [trim($peer->skills)];
                    }
                }
            }

            $bio = $resolveString($peer->short_bio);
            if ($bio === '') {
                $bio = $resolveString($peer->experience_summary);
            }
            if ($bio === '') {
                $bio = $resolveString($peer->superpower);
            }
            if ($bio === '') {
                $bio = $resolveString($peer->i_can_help_with);
            }

            $industry = $resolveString($peer->business_sub_category);
            if ($industry === '') {
                $industry = $resolveString($peer->company_type);
            }
            if ($industry === '') {
                $industry = 'Business & Entrepreneurship';
            }

            $peerData = [
                'id' => (string) $peer->id,
                'name' => $peerName,
                'designation' => $resolveString($peer->designation ?? ''),
                'company' => $resolveString($peer->company_name ?? ''),
                'company_name' => $resolveString($peer->company_name ?? ''),
                'avatar_url' => $avatarUrl,
                'profile_photo_image' => $avatarUrl,
                'city' => $resolveString($peer->city ?? ''),
                'bio' => $bio,
                'industry' => $industry,
                'skills' => $skills,
                'connection_status' => $connectionStatus,
            ];
        }

        $remainingSeconds = (int) $event->round_duration_seconds;
        if ($match->started_at) {
            $elapsed = Carbon::now()->diffInSeconds($match->started_at);
            $remainingSeconds = max(0, (int) $event->round_duration_seconds - (int) $elapsed);
        }

        return [
            'event_id' => (string) $event->id,
            'title' => $event->title,
            'event_title' => $event->title,
            'current_round' => (int) $match->round_number,
            'total_rounds' => (int) $event->total_rounds,
            'round_duration_seconds' => (int) $event->round_duration_seconds,
            'break_duration_seconds' => (int) $event->break_duration_seconds,
            'remaining_seconds' => $remainingSeconds,
            'channel_id' => $match->channel_id ?: '',
            'agora_token' => $token ?: '',
            'is_break_round' => $isBreak,
            'has_met_all_peers' => $isBreak ? $hasMetAllPeers : false,
            'peer' => $peerData,
        ];
    }

    /**
     * Format a break round response.
     *
     * @return array<string, mixed>
     */
    protected function formatBreakRoundResponse(SpeedNetworkingEvent $event, int $roundNumber, bool $hasMetAllPeers = false): array
    {
        $message = $hasMetAllPeers
            ? 'You have met all active attendees in this session. Stand by for new joiners or exit.'
            : 'You are on a break round. You will be matched in the next round.';

        return [
            'event_id' => (string) $event->id,
            'title' => $event->title,
            'event_title' => $event->title,
            'round_duration_seconds' => (int) $event->round_duration_seconds,
            'break_duration_seconds' => (int) $event->break_duration_seconds,
            'current_round' => $roundNumber,
            'total_rounds' => (int) $event->total_rounds,
            'remaining_seconds' => 0,
            'channel_id' => '',
            'agora_token' => '',
            'is_break_round' => true,
            'has_met_all_peers' => $hasMetAllPeers,
            'message' => $message,
            'peer' => null,
        ];
    }
}
