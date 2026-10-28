<?php

declare(strict_types=1);

namespace App\Services\SpeedNetworking;

use App\Models\Connection;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\SpeedNetworking\SpeedNetworkingMatch;
use App\Models\SpeedNetworking\SpeedNetworkingParticipant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SpeedNetworkingService
{
    protected SpeedNetworkingP2pService $p2pService;

    public function __construct(
        protected AgoraTokenService $agoraTokenService,
        ?SpeedNetworkingP2pService $p2pService = null
    ) {
        $this->p2pService = $p2pService ?? app(SpeedNetworkingP2pService::class);
    }

    /**
     * Get paginated list of Speed Networking events for Admin panel.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listEvents(array $filters = [], int $perPage = 15): array
    {
        $hasSnTable = false;
        try {
            $hasSnTable = \Illuminate\Support\Facades\Schema::hasTable('speed_networking_events');
        } catch (\Throwable $e) {}

        if ($hasSnTable) {
            try {
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
            } catch (\Throwable $e) {
                Log::warning('SpeedNetworkingService::listEvents sn table query failed', ['error' => $e->getMessage()]);
            }
        }

        // Fallback to base events table
        try {
            $query = Event::query()
                ->where(function ($q): void {
                    $q->where('event_type', 'speed_networking')
                        ->orWhere('title', 'like', '%Speed%')
                        ->orWhere('title', 'like', '%speed%');
                })
                ->latest('created_at');

            if (! empty($filters['search'])) {
                $search = '%'.trim((string) $filters['search']).'%';
                $query->where('title', 'like', $search);
            }

            $paginated = $query->paginate($perPage);

            $mapped = collect($paginated->items())->map(function (Event $e) {
                $meta = (array) ($e->metadata ?? []);
                $regCount = 0;
                try {
                    $regCount = $e->registrations()->count();
                } catch (\Throwable) {}

                return (object) [
                    'id' => $e->id,
                    'title' => $e->title,
                    'description' => $e->description,
                    'status' => $meta['speed_status'] ?? 'scheduled',
                    'round_duration_seconds' => (int) ($meta['round_duration_seconds'] ?? 300),
                    'break_duration_seconds' => (int) ($meta['break_duration_seconds'] ?? 20),
                    'total_rounds' => (int) ($meta['total_rounds'] ?? 5),
                    'current_round' => (int) ($meta['current_round'] ?? 0),
                    'scheduled_start_time' => $e->start_at,
                    'ticket_price' => (float) ($e->ticket_price ?? ($meta['ticket_price'] ?? 0)),
                    'is_paid' => (bool) ($e->is_paid ?? ($meta['is_paid'] ?? false)),
                    'active_participants_count' => $regCount,
                    'participants_count' => $regCount,
                    'matches_count' => (int) ceil($regCount / 2),
                ];
            });

            return [
                'data' => $mapped->all(),
                'pagination' => [
                    'total' => $paginated->total(),
                    'per_page' => $paginated->perPage(),
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('SpeedNetworkingService::listEvents fallback failed', ['error' => $e->getMessage()]);

            return [
                'data' => [],
                'pagination' => [
                    'total' => 0,
                    'per_page' => $perPage,
                    'current_page' => 1,
                    'last_page' => 1,
                ],
            ];
        }
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
        $id = $event->id;
        $event->delete();
        try {
            Event::where('id', $id)->delete();
        } catch (\Throwable $e) {
            // Ignore
        }
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

        $existingUserIds = $items->pluck('user_id')->all();
        $registrations = EventRegistration::with('user')
            ->where('event_id', $event->id)
            ->whereNotIn('user_id', $existingUserIds)
            ->get();

        foreach ($registrations as $reg) {
            $u = $reg->user;
            if ($u) {
                $name = $u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: $u->name ?: 'Participant';
                $items->push([
                    'participant_id' => (string) $reg->id,
                    'user_id' => (string) $reg->user_id,
                    'name' => $name,
                    'company' => (string) ($u->company_name ?? 'Peers Member'),
                    'designation' => (string) ($u->designation ?? ''),
                    'city' => (string) ($u->city ?? 'Peers Global'),
                    'avatar_url' => $u->profile_photo_url ?? '',
                    'status' => 'in_call',
                    'is_online' => true,
                    'joined_at' => $reg->created_at?->toIso8601String(),
                    'last_heartbeat_at' => Carbon::now()->toIso8601String(),
                ]);
            }
        }

        return [
            'data' => $items->values(),
            'pagination' => [
                'total' => $items->count(),
                'per_page' => max($perPage, $items->count()),
                'current_page' => 1,
                'last_page' => 1,
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
            'total_rounds' => (int) ($data['total_rounds'] ?? 0),
            'scheduled_start_time' => ! empty($data['scheduled_start_time']) ? Carbon::parse($data['scheduled_start_time']) : null,
        ];

        $ticketPrice = round(max((float) ($data['ticket_price'] ?? ($data['ticketPrice'] ?? 0)), 0), 2);
        $isPaid = (bool) ($data['is_paid'] ?? ($data['isPaid'] ?? false)) || $ticketPrice > 0;

        if ($id) {
            $event = SpeedNetworkingEvent::findOrFail($id);
            $event->update($attributes);

            try {
                Event::updateOrCreate(
                    ['id' => $event->id],
                    [
                        'title' => $event->title,
                        'description' => $event->description,
                        'banner_url' => $event->banner_url,
                        'event_type' => 'speed_networking',
                        'mode' => 'online',
                        'is_virtual' => true,
                        'ticket_price' => $ticketPrice,
                        'is_paid' => $isPaid,
                        'start_at' => $event->scheduled_start_time ?? Carbon::now(),
                        'end_at' => $event->scheduled_start_time ? Carbon::parse($event->scheduled_start_time)->addHours(2) : Carbon::now()->addHours(2),
                        'metadata' => [
                            'round_duration_seconds' => $event->round_duration_seconds,
                            'break_duration_seconds' => $event->break_duration_seconds,
                            'total_rounds' => $event->total_rounds,
                            'current_round' => $event->current_round,
                            'speed_status' => $event->status,
                            'ticket_price' => $ticketPrice,
                            'is_paid' => $isPaid,
                        ],
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('Could not mirror SpeedNetworkingEvent update to Event', ['error' => $e->getMessage()]);
            }

            return $event;
        }

        $attributes['status'] = ! empty($attributes['scheduled_start_time'])
            ? SpeedNetworkingEvent::STATUS_SCHEDULED
            : SpeedNetworkingEvent::STATUS_DRAFT;
        $attributes['current_round'] = 0;

        $event = SpeedNetworkingEvent::create($attributes);

        try {
            Event::updateOrCreate(
                ['id' => $event->id],
                [
                    'title' => $event->title,
                    'description' => $event->description,
                    'banner_url' => $event->banner_url,
                    'event_type' => 'speed_networking',
                    'mode' => 'online',
                    'is_virtual' => true,
                    'ticket_price' => $ticketPrice,
                    'is_paid' => $isPaid,
                    'start_at' => $event->scheduled_start_time ?? Carbon::now(),
                    'end_at' => $event->scheduled_start_time ? Carbon::parse($event->scheduled_start_time)->addHours(2) : Carbon::now()->addHours(2),
                    'metadata' => [
                        'round_duration_seconds' => $event->round_duration_seconds,
                        'break_duration_seconds' => $event->break_duration_seconds,
                        'total_rounds' => $event->total_rounds,
                        'current_round' => $event->current_round,
                        'speed_status' => $event->status,
                        'ticket_price' => $ticketPrice,
                        'is_paid' => $isPaid,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Could not mirror SpeedNetworkingEvent create to Event', ['error' => $e->getMessage()]);
        }

        return $event;
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

            if ($event->total_rounds > 0 && $nextRound > $event->total_rounds) {
                $event->status = SpeedNetworkingEvent::STATUS_COMPLETED;
                $event->save();

                try {
                    $this->p2pService->completeAllMatchesForEvent($event);
                } catch (\Throwable $p2pEx) {
                    Log::warning('[SpeedNetworkingService] Could not process P2P meetings on round completion: '.$p2pEx->getMessage());
                }

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

            try {
                $this->p2pService->completeAllMatchesForEvent($event);
            } catch (\Throwable $p2pEx) {
                Log::warning('[SpeedNetworkingService] Could not process P2P meetings on endEvent: '.$p2pEx->getMessage());
            }

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
            $lockedEvent = SpeedNetworkingEvent::where('id', $event->id)->lockForUpdate()->first() ?? $event;

            $participant = SpeedNetworkingParticipant::updateOrCreate(
                [
                    'event_id' => $lockedEvent->id,
                    'user_id' => $user->id,
                ],
                [
                    'status' => SpeedNetworkingParticipant::STATUS_LOBBY,
                    'joined_at' => Carbon::now(),
                    'last_heartbeat_at' => Carbon::now(),
                ]
            );

            // Automatically activate event if scheduled time has arrived, not set, or 2+ participants are present
            $activeCount = SpeedNetworkingParticipant::where('event_id', $lockedEvent->id)
                ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
                ->count();

            if (in_array($lockedEvent->status, [SpeedNetworkingEvent::STATUS_DRAFT, SpeedNetworkingEvent::STATUS_SCHEDULED, SpeedNetworkingEvent::STATUS_LOBBY_OPEN], true)) {
                if (empty($lockedEvent->scheduled_start_time) || $lockedEvent->scheduled_start_time->isPast() || $activeCount >= 2) {
                    $lockedEvent->status = SpeedNetworkingEvent::STATUS_IN_PROGRESS;
                    $lockedEvent->actual_start_time = Carbon::now();
                    if ($lockedEvent->current_round < 1) {
                        $lockedEvent->current_round = 1;
                    }
                    $lockedEvent->save();
                } else {
                    $lockedEvent->status = SpeedNetworkingEvent::STATUS_LOBBY_OPEN;
                    $lockedEvent->save();
                }
            }

            // If event is not in progress or no rounds yet, return lobby payload
            if ($lockedEvent->status !== SpeedNetworkingEvent::STATUS_IN_PROGRESS || $lockedEvent->current_round < 1) {
                return [
                    'event_id' => (string) $lockedEvent->id,
                    'title' => $lockedEvent->title,
                    'event_title' => $lockedEvent->title,
                    'round_duration_seconds' => (int) $lockedEvent->round_duration_seconds,
                    'break_duration_seconds' => (int) $lockedEvent->break_duration_seconds,
                    'remaining_seconds' => (int) $lockedEvent->round_duration_seconds,
                    'current_round' => 0,
                    'total_rounds' => (int) $lockedEvent->total_rounds,
                    'channel_id' => '',
                    'agora_token' => '',
                    'is_break_round' => false,
                    'is_waiting' => true,
                    'has_met_all_peers' => false,
                    'completed' => false,
                    'message' => 'Lobby is open. Waiting for the event to begin.',
                    'peer' => null,
                ];
            }

            // In progress: look for user's active 1-on-1 match
            $activeMatch = $this->findActiveMatchForUser($lockedEvent->id, (string) $user->id);

            if ($activeMatch) {
                $elapsed = $activeMatch->started_at ? Carbon::now()->diffInSeconds($activeMatch->started_at) : 0;
                if ($elapsed < $lockedEvent->round_duration_seconds) {
                    $participant->update(['status' => SpeedNetworkingParticipant::STATUS_IN_CALL]);

                    return $this->formatMatchResponse($lockedEvent, $activeMatch, (string) $user->id);
                }

                $activeMatch->update([
                    'status' => SpeedNetworkingMatch::STATUS_COMPLETED,
                    'ended_at' => Carbon::now(),
                ]);
            }

            return $this->findOrCreateMatchForUser($lockedEvent, $user);
        });
    }

    /**
     * Check if a participant has met all other currently active attendees in this event.
     */
    public function hasMetAllActivePeers(SpeedNetworkingEvent $event, string $userId): bool
    {
        $cutoff = Carbon::now()->subSeconds(90);

        $otherActiveUserIds = SpeedNetworkingParticipant::where('event_id', $event->id)
            ->where('user_id', '!=', $userId)
            ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
            ->where(function ($q) use ($cutoff): void {
                $q->where('last_heartbeat_at', '>=', $cutoff)
                    ->orWhere('joined_at', '>=', $cutoff);
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
            ->map(fn (SpeedNetworkingMatch $m) => (string) ($m->user_a_id === $userId ? $m->user_b_id : $m->user_a_id))
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
    public function getNextMatch(SpeedNetworkingEvent $event, User $user, bool $isGetRequest = false): array
    {
        return DB::transaction(function () use ($event, $user, $isGetRequest): array {
            $lockedEvent = SpeedNetworkingEvent::where('id', $event->id)->lockForUpdate()->first() ?? $event;
            $this->heartbeat($lockedEvent, $user);

            if ($lockedEvent->isCompleted()) {
                $summary = $this->getSessionSummary($lockedEvent, $user);

                return array_merge([
                    'event_id' => (string) $lockedEvent->id,
                    'title' => $lockedEvent->title,
                    'event_title' => $lockedEvent->title,
                    'round_duration_seconds' => (int) $lockedEvent->round_duration_seconds,
                    'break_duration_seconds' => (int) $lockedEvent->break_duration_seconds,
                    'current_round' => (int) $lockedEvent->current_round,
                    'total_rounds' => (int) $lockedEvent->total_rounds,
                    'remaining_seconds' => 0,
                    'channel_id' => '',
                    'agora_token' => '',
                    'is_break_round' => true,
                    'peer' => null,
                ], $summary);
            }

            $participant = SpeedNetworkingParticipant::where('event_id', $lockedEvent->id)
                ->where('user_id', $user->id)
                ->first();

            $activeMatch = $this->findActiveMatchForUser($lockedEvent->id, (string) $user->id);

            if ($activeMatch) {
                $elapsed = $activeMatch->started_at ? Carbon::now()->diffInSeconds($activeMatch->started_at) : 0;
                $isExpired = $elapsed >= $lockedEvent->round_duration_seconds;

                // If GET request and match is still within round duration, return active match
                if ($isGetRequest && ! $isExpired) {
                    if ($participant && $participant->status !== SpeedNetworkingParticipant::STATUS_IN_CALL) {
                        $participant->update(['status' => SpeedNetworkingParticipant::STATUS_IN_CALL]);
                    }

                    return $this->formatMatchResponse($lockedEvent, $activeMatch, (string) $user->id);
                }

                // If participant was in break/waiting status when assigned this match,
                // this request is them receiving the match:
                if (! $isExpired && $participant && $participant->status === SpeedNetworkingParticipant::STATUS_BREAK) {
                    $participant->update(['status' => SpeedNetworkingParticipant::STATUS_IN_CALL]);

                    return $this->formatMatchResponse($lockedEvent, $activeMatch, (string) $user->id);
                }

                // User is completing/leaving the active call
                $activeMatch->update([
                    'status' => SpeedNetworkingMatch::STATUS_COMPLETED,
                    'ended_at' => Carbon::now(),
                ]);

                if ($participant) {
                    $participant->update(['status' => SpeedNetworkingParticipant::STATUS_BREAK]);
                }
            }

            return $this->findOrCreateMatchForUser($lockedEvent, $user);
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
        $participantsMap = [];
        $snList = SpeedNetworkingParticipant::with('user')
            ->where('event_id', $event->id)
            ->get();

        foreach ($snList as $p) {
            $u = $p->user;
            if ($u) {
                $uid = (string) $u->id;
                $name = $u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
                if (empty($name)) {
                    $name = $u->name ?? 'Participant';
                }
                $participantsMap[$uid] = [
                    'id' => $uid,
                    'name' => $name,
                    'company' => (string) ($u->company_name ?? 'Peers Member'),
                    'industry' => (string) ($u->business_sub_category ?? $u->industry ?? 'Business'),
                    'city' => (string) ($u->city ?? 'Peers Global'),
                    'avatar_url' => $u->profile_photo_url ?? null,
                    'status' => $p->status ?? 'in_call',
                ];
            }
        }

        $regList = EventRegistration::where('event_id', $event->id)->with('user')->get();
        foreach ($regList as $reg) {
            $u = $reg->user;
            if ($u && ! isset($participantsMap[(string) $u->id])) {
                $uid = (string) $u->id;
                $name = $u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
                if (empty($name)) {
                    $name = $u->name ?? 'Participant';
                }
                $participantsMap[$uid] = [
                    'id' => $uid,
                    'name' => $name,
                    'company' => (string) ($u->company_name ?? 'Peers Member'),
                    'industry' => (string) ($u->business_sub_category ?? $u->industry ?? 'Business'),
                    'city' => (string) ($u->city ?? 'Peers Global'),
                    'avatar_url' => $u->profile_photo_url ?? null,
                    'status' => 'in_call',
                ];
            }
        }

        $participants = array_values($participantsMap);
        $roundDuration = (int) ($event->round_duration_seconds ?: 300);
        $remainingSeconds = $roundDuration;
        if ($event->status === SpeedNetworkingEvent::STATUS_IN_PROGRESS && ! empty($event->actual_start_time)) {
            $started = Carbon::parse($event->actual_start_time);
            $elapsed = Carbon::now()->diffInSeconds($started);
            $remainingSeconds = max(0, $roundDuration - $elapsed);
        }

        $activeMatches = SpeedNetworkingMatch::with(['userA', 'userB'])
            ->where('event_id', $event->id)
            ->where('round_number', $event->current_round ?: 1)
            ->orderBy('table_number')
            ->get();

        $tableMatches = [];
        if ($activeMatches->isNotEmpty()) {
            foreach ($activeMatches as $m) {
                $ua = $m->userA;
                $ub = $m->userB;
                $tableMatches[] = [
                    'match_id' => (string) $m->id,
                    'table_number' => (int) $m->table_number,
                    'channel_id' => $m->channel_id ?: ('sn_room_table_'.$m->table_number),
                    'status' => $m->status,
                    'is_break_round' => $m->isBreakRound(),
                    'user_a' => $ua ? [
                        'id' => (string) $ua->id,
                        'name' => $ua->display_name ?: trim(($ua->first_name ?? '').' '.($ua->last_name ?? '')) ?: $ua->name,
                        'company' => (string) ($ua->company_name ?? 'Peers Member'),
                        'industry' => (string) ($ua->business_sub_category ?? $ua->industry ?? 'Business'),
                        'avatar_url' => $ua->profile_photo_url ?? null,
                    ] : null,
                    'user_b' => $ub ? [
                        'id' => (string) $ub->id,
                        'name' => $ub->display_name ?: trim(($ub->first_name ?? '').' '.($ub->last_name ?? '')) ?: $ub->name,
                        'company' => (string) ($ub->company_name ?? 'Peers Member'),
                        'industry' => (string) ($ub->business_sub_category ?? $ub->industry ?? 'Business'),
                        'avatar_url' => $ub->profile_photo_url ?? null,
                    ] : null,
                    'started_at' => $m->started_at?->toIso8601String() ?? Carbon::now()->toIso8601String(),
                ];
            }
        } elseif (count($participants) >= 2) {
            $tableCount = (int) ceil(count($participants) / 2);
            for ($i = 0; $i < $tableCount; $i++) {
                $tableMatches[] = [
                    'match_id' => 'tbl_match_'.($i + 1),
                    'table_number' => $i + 1,
                    'channel_id' => 'sn_room_table_'.($i + 1),
                    'status' => $event->status === SpeedNetworkingEvent::STATUS_IN_PROGRESS ? 'active' : 'waiting',
                    'is_break_round' => false,
                    'user_a' => $participants[$i * 2] ?? null,
                    'user_b' => $participants[$i * 2 + 1] ?? null,
                    'started_at' => Carbon::now()->toIso8601String(),
                ];
            }
        } elseif (count($participants) === 1) {
            $tableMatches[] = [
                'match_id' => 'tbl_match_1',
                'table_number' => 1,
                'channel_id' => 'sn_room_table_1',
                'status' => 'waiting',
                'is_break_round' => false,
                'user_a' => $participants[0],
                'user_b' => null,
                'started_at' => Carbon::now()->toIso8601String(),
            ];
        }

        return [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'status' => $event->status,
                'round_duration_seconds' => $roundDuration,
                'break_duration_seconds' => (int) $event->break_duration_seconds,
                'total_rounds' => (int) $event->total_rounds,
                'current_round' => (int) $event->current_round,
                'scheduled_start_time' => $event->scheduled_start_time?->toIso8601String(),
                'actual_start_time' => $event->actual_start_time?->toIso8601String(),
                'total_participants_count' => count($participants),
                'active_tables_count' => count($tableMatches),
            ],
            'active_participants_count' => count($participants),
            'total_participants_count' => count($participants),
            'remaining_seconds' => $remainingSeconds,
            'is_paused' => false,
            'tables' => $tableMatches,
        ];
    }

    /**
     * Generate pairings for a specific round ensuring strict non-repeating matches and no overlapping active calls.
     */
    public function generateMatchesForRound(SpeedNetworkingEvent $event, int $roundNumber): void
    {
        $cutoff = Carbon::now()->subSeconds(90);

        // Fetch active participants who are not left and have recent activity
        $participants = SpeedNetworkingParticipant::where('event_id', $event->id)
            ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
            ->where(function ($q) use ($cutoff): void {
                $q->where('last_heartbeat_at', '>=', $cutoff)
                    ->orWhere('joined_at', '>=', $cutoff);
            })
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        // Exclude users already in an active 1-on-1 match
        $busyUserIds = SpeedNetworkingMatch::where('event_id', $event->id)
            ->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)
            ->whereNotNull('user_b_id')
            ->get()
            ->flatMap(fn (SpeedNetworkingMatch $m) => [(string) $m->user_a_id, (string) $m->user_b_id])
            ->filter()
            ->unique()
            ->values()
            ->all();

        $available = array_values(array_diff($participants, $busyUserIds));
        $count = count($available);
        if ($count < 2) {
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

        shuffle($available);

        $paired = [];
        $maxTable = (int) (SpeedNetworkingMatch::where('event_id', $event->id)->max('table_number') ?? 0);
        $tableIndex = $maxTable + 1;

        for ($i = 0; $i < $count; $i++) {
            $u1 = $available[$i];
            if (isset($paired[$u1])) {
                continue;
            }

            $partner = null;
            for ($j = $i + 1; $j < $count; $j++) {
                $u2 = $available[$j];
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

                $channelId = "sn_{$event->id}_r{$roundNumber}_t{$tableIndex}_".Str::random(6);

                SpeedNetworkingMatch::create([
                    'event_id' => $event->id,
                    'round_number' => $roundNumber,
                    'table_number' => $tableIndex,
                    'channel_id' => $channelId,
                    'user_a_id' => $u1,
                    'user_b_id' => $partner,
                    'status' => SpeedNetworkingMatch::STATUS_ACTIVE,
                    'started_at' => Carbon::now(),
                ]);

                SpeedNetworkingParticipant::where('event_id', $event->id)
                    ->whereIn('user_id', [$u1, $partner])
                    ->update(['status' => SpeedNetworkingParticipant::STATUS_IN_CALL]);

                $tableIndex++;
            }
        }
    }

    /**
     * Find active 1-on-1 match for a user.
     */
    public function findActiveMatchForUser(string $eventId, string $userId): ?SpeedNetworkingMatch
    {
        return SpeedNetworkingMatch::with(['userA', 'userB'])
            ->where('event_id', $eventId)
            ->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)
            ->whereNotNull('user_b_id')
            ->where(function ($q) use ($userId): void {
                $q->where('user_a_id', $userId)
                    ->orWhere('user_b_id', $userId);
            })
            ->first();
    }

    /**
     * Dynamically match user with next available peer without repeating pairings.
     *
     * @return array<string, mixed>
     */
    public function findOrCreateMatchForUser(SpeedNetworkingEvent $event, User $user): array
    {
        // Check if an active match was already assigned to this user
        $existingMatch = $this->findActiveMatchForUser($event->id, (string) $user->id);
        if ($existingMatch) {
            SpeedNetworkingParticipant::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->update(['status' => SpeedNetworkingParticipant::STATUS_IN_CALL]);

            return $this->formatMatchResponse($event, $existingMatch, (string) $user->id);
        }

        // 1. Completed matches count for this user in this event
        $userCompletedCount = SpeedNetworkingMatch::where('event_id', $event->id)
            ->where(function ($q) use ($user): void {
                $q->where('user_a_id', $user->id)
                    ->orWhere('user_b_id', $user->id);
            })
            ->whereNotNull('user_b_id')
            ->where('status', SpeedNetworkingMatch::STATUS_COMPLETED)
            ->count();

        // 2. Fetch all other active participants in this event
        $cutoff = Carbon::now()->subSeconds(90);
        $otherParticipantIds = SpeedNetworkingParticipant::where('event_id', $event->id)
            ->where('user_id', '!=', $user->id)
            ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
            ->where(function ($q) use ($cutoff): void {
                $q->where('last_heartbeat_at', '>=', $cutoff)
                    ->orWhere('joined_at', '>=', $cutoff);
            })
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        // 3. Fetch all peers user has already met in this event (active, completed, or skipped)
        $metPeerIds = SpeedNetworkingMatch::where('event_id', $event->id)
            ->where(function ($q) use ($user): void {
                $q->where('user_a_id', $user->id)
                    ->orWhere('user_b_id', $user->id);
            })
            ->whereNotNull('user_b_id')
            ->get()
            ->map(fn (SpeedNetworkingMatch $m) => (string) ($m->user_a_id === (string) $user->id ? $m->user_b_id : $m->user_a_id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $unmetPeerIds = array_values(array_diff($otherParticipantIds, $metPeerIds));

        $hasReachedTotalRounds = ($event->total_rounds > 0 && $userCompletedCount >= $event->total_rounds);
        $hasMetAllPeers = ! empty($otherParticipantIds) && empty($unmetPeerIds);

        // If user reached round limit OR has met all active peers:
        if ($hasReachedTotalRounds || $hasMetAllPeers) {
            SpeedNetworkingParticipant::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->update(['status' => SpeedNetworkingParticipant::STATUS_BREAK]);

            $summary = $this->getSessionSummary($event, $user);

            return array_merge([
                'event_id' => (string) $event->id,
                'title' => $event->title,
                'event_title' => $event->title,
                'round_duration_seconds' => (int) $event->round_duration_seconds,
                'break_duration_seconds' => (int) $event->break_duration_seconds,
                'current_round' => $userCompletedCount,
                'total_rounds' => (int) $event->total_rounds,
                'remaining_seconds' => 0,
                'channel_id' => '',
                'agora_token' => '',
                'is_break_round' => true,
                'is_waiting' => false,
                'peer' => null,
            ], $summary);
        }

        // If no other participants have joined yet, user is waiting for peers to join
        if (empty($otherParticipantIds)) {
            SpeedNetworkingParticipant::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->update(['status' => SpeedNetworkingParticipant::STATUS_LOBBY]);

            return [
                'event_id' => (string) $event->id,
                'title' => $event->title,
                'event_title' => $event->title,
                'round_duration_seconds' => (int) $event->round_duration_seconds,
                'break_duration_seconds' => (int) $event->break_duration_seconds,
                'current_round' => $userCompletedCount,
                'total_rounds' => (int) $event->total_rounds,
                'remaining_seconds' => 0,
                'channel_id' => '',
                'agora_token' => '',
                'is_break_round' => true,
                'is_waiting' => true,
                'has_met_all_peers' => false,
                'completed' => false,
                'message' => 'Waiting for peers to join the event...',
                'peer' => null,
            ];
        }

        // 4. Identify busy users currently in an active 1-on-1 call
        $busyUserIds = SpeedNetworkingMatch::where('event_id', $event->id)
            ->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)
            ->whereNotNull('user_b_id')
            ->get()
            ->flatMap(fn (SpeedNetworkingMatch $m) => [(string) $m->user_a_id, (string) $m->user_b_id])
            ->filter()
            ->unique()
            ->values()
            ->all();

        $busyParticipantIds = SpeedNetworkingParticipant::where('event_id', $event->id)
            ->where('status', SpeedNetworkingParticipant::STATUS_IN_CALL)
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $allBusyIds = array_unique(array_merge($busyUserIds, $busyParticipantIds));

        // Available candidates are unmet peers who are NOT busy
        $availableCandidates = array_values(array_diff($unmetPeerIds, $allBusyIds));

        if (! empty($availableCandidates)) {
            $partnerId = $availableCandidates[0];
            $roundNumber = $userCompletedCount + 1;

            $maxTable = (int) (SpeedNetworkingMatch::where('event_id', $event->id)->max('table_number') ?? 0);
            $tableNumber = $maxTable + 1;
            $channelId = "sn_{$event->id}_r{$roundNumber}_t{$tableNumber}_".Str::random(6);

            $match = SpeedNetworkingMatch::create([
                'event_id' => $event->id,
                'round_number' => $roundNumber,
                'table_number' => $tableNumber,
                'channel_id' => $channelId,
                'user_a_id' => (string) $user->id,
                'user_b_id' => (string) $partnerId,
                'status' => SpeedNetworkingMatch::STATUS_ACTIVE,
                'started_at' => Carbon::now(),
            ]);

            SpeedNetworkingParticipant::where('event_id', $event->id)
                ->whereIn('user_id', [$user->id, $partnerId])
                ->update(['status' => SpeedNetworkingParticipant::STATUS_IN_CALL]);

            if ($roundNumber > $event->current_round) {
                $event->current_round = $roundNumber;
                $event->save();
            }

            return $this->formatMatchResponse($event, $match, (string) $user->id);
        }

        // 5. Unmet peers exist, but all are currently in other calls -> wait
        SpeedNetworkingParticipant::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->update(['status' => SpeedNetworkingParticipant::STATUS_BREAK]);

        return [
            'event_id' => (string) $event->id,
            'title' => $event->title,
            'event_title' => $event->title,
            'round_duration_seconds' => (int) $event->round_duration_seconds,
            'break_duration_seconds' => (int) $event->break_duration_seconds,
            'current_round' => $userCompletedCount,
            'total_rounds' => (int) $event->total_rounds,
            'remaining_seconds' => 0,
            'channel_id' => '',
            'agora_token' => '',
            'is_break_round' => true,
            'is_waiting' => true,
            'has_met_all_peers' => false,
            'completed' => false,
            'message' => 'Waiting for the next peer to become available...',
            'peer' => null,
        ];
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
            'is_waiting' => false,
            'has_met_all_peers' => $isBreak ? $hasMetAllPeers : false,
            'completed' => false,
            'peer' => $peerData,
        ];
    }

    /**
     * Get active match payload for user if currently in an active round.
     *
     * @return array<string, mixed>|null
     */
    public function getActiveMatchPayload(SpeedNetworkingEvent $event, User $user): ?array
    {
        $activeMatch = $this->findActiveMatchForUser($event->id, (string) $user->id);
        if (! $activeMatch) {
            return null;
        }

        $elapsed = $activeMatch->started_at ? Carbon::now()->diffInSeconds($activeMatch->started_at) : 0;
        if ($elapsed >= $event->round_duration_seconds) {
            return null;
        }

        return $this->formatMatchResponse($event, $activeMatch, (string) $user->id);
    }

    /**
     * Format a break round response.
     *
     * @return array<string, mixed>
     */
    protected function formatBreakRoundResponse(SpeedNetworkingEvent $event, int $roundNumber, bool $hasMetAllPeers = false): array
    {
        $message = $hasMetAllPeers
            ? 'You have met all peers in this event.'
            : 'Waiting for the next peer to become available...';

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
            'is_waiting' => ! $hasMetAllPeers,
            'has_met_all_peers' => $hasMetAllPeers,
            'completed' => $hasMetAllPeers,
            'message' => $message,
            'peer' => null,
        ];
    }

    /**
     * Check if user has finished all rounds or met all peers in this event.
     */
    public function isUserCompleted(SpeedNetworkingEvent $event, User $user): bool
    {
        if ($event->isCompleted()) {
            return true;
        }

        $participant = SpeedNetworkingParticipant::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if ($participant && $participant->status === SpeedNetworkingParticipant::STATUS_LEFT) {
            return true;
        }

        $userCompletedCount = SpeedNetworkingMatch::where('event_id', $event->id)
            ->where(function ($q) use ($user): void {
                $q->where('user_a_id', $user->id)
                    ->orWhere('user_b_id', $user->id);
            })
            ->whereNotNull('user_b_id')
            ->where('status', SpeedNetworkingMatch::STATUS_COMPLETED)
            ->count();

        if ($event->total_rounds > 0 && $userCompletedCount >= $event->total_rounds) {
            return true;
        }

        $cutoff = Carbon::now()->subSeconds(90);
        $otherParticipantIds = SpeedNetworkingParticipant::where('event_id', $event->id)
            ->where('user_id', '!=', $user->id)
            ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
            ->where(function ($q) use ($cutoff): void {
                $q->where('last_heartbeat_at', '>=', $cutoff)
                    ->orWhere('joined_at', '>=', $cutoff);
            })
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        $metPeerIds = SpeedNetworkingMatch::where('event_id', $event->id)
            ->where(function ($q) use ($user): void {
                $q->where('user_a_id', $user->id)
                    ->orWhere('user_b_id', $user->id);
            })
            ->whereNotNull('user_b_id')
            ->get()
            ->map(fn (SpeedNetworkingMatch $m) => (string) ($m->user_a_id === (string) $user->id ? $m->user_b_id : $m->user_a_id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $unmetPeerIds = array_values(array_diff($otherParticipantIds, $metPeerIds));

        if (! empty($otherParticipantIds) && empty($unmetPeerIds) && $userCompletedCount >= 1) {
            return true;
        }

        return false;
    }

    /**
     * Get summary of peers that the user actively interacted with during this session.
     *
     * @return array{
     *     completed: bool,
     *     has_met_all_peers: bool,
     *     message: string,
     *     total_rounds: int,
     *     rounds_completed: int,
     *     total_interactions_count: int,
     *     interacted_peers: array<int, array<string, mixed>>
     * }
     */
    public function getSessionSummary(SpeedNetworkingEvent $event, User $user): array
    {
        $userId = (string) $user->id;

        $matches = SpeedNetworkingMatch::with(['userA', 'userB'])
            ->where('event_id', $event->id)
            ->where(function ($q) use ($userId): void {
                $q->where('user_a_id', $userId)
                    ->orWhere('user_b_id', $userId);
            })
            ->whereNotNull('user_b_id')
            ->orderBy('round_number', 'asc')
            ->get();

        $interactedPeers = [];
        $seenPeerIds = [];

        foreach ($matches as $match) {
            /** @var User|null $peer */
            $peer = $match->user_a_id === $userId ? $match->userB : $match->userA;
            $peerId = (string) ($match->user_a_id === $userId ? $match->user_b_id : $match->user_a_id);

            if (! $peer && $peerId !== '') {
                $peer = User::find($peerId);
            }

            if (! $peer || isset($seenPeerIds[$peer->id])) {
                continue;
            }

            $seenPeerIds[$peer->id] = true;

            $peerName = $peer->display_name;
            if (empty($peerName)) {
                $peerName = trim(($peer->first_name ?? '').' '.($peer->last_name ?? ''));
            }
            if (empty($peerName)) {
                $peerName = (string) ($peer->getRawOriginal('display_name') ?? $peer->getAttributes()['display_name'] ?? '');
            }
            if (empty($peerName)) {
                $peerName = (string) ($peer->name ?? '');
            }
            if (empty($peerName)) {
                $peerName = 'Peer';
            }

            // Connection status check
            $connection = Connection::where(function ($q) use ($userId, $peer): void {
                $q->where('requester_id', $userId)->where('addressee_id', $peer->id);
            })->orWhere(function ($q) use ($userId, $peer): void {
                $q->where('requester_id', $peer->id)->where('addressee_id', $userId);
            })->first();

            $connectionStatus = 'none';
            if ($connection) {
                if (isset($connection->status) && in_array($connection->status, ['connected', 'pending', 'none'], true)) {
                    $connectionStatus = (string) $connection->status;
                } else {
                    $connectionStatus = ! empty($connection->is_approved) ? 'connected' : 'pending';
                }
            }

            $bio = (string) ($peer->short_bio ?? '');
            if ($bio === '') {
                $bio = (string) ($peer->experience_summary ?? '');
            }
            if ($bio === '') {
                $bio = (string) ($peer->superpower ?? '');
            }
            if ($bio === '') {
                $bio = (string) ($peer->i_can_help_with ?? '');
            }

            // Calculate duration in seconds
            $duration = 0;
            if ($match->started_at && $match->ended_at) {
                $duration = max(0, (int) $match->ended_at->diffInSeconds($match->started_at));
            } elseif ($match->started_at) {
                $duration = max(0, (int) Carbon::now()->diffInSeconds($match->started_at));
            }

            if ($duration === 0 && (int) $event->round_duration_seconds > 0) {
                $duration = (int) $event->round_duration_seconds;
            }

            // Automatically process P2P meeting and creative for this match
            $p2pMeeting = null;
            $creativeUrl = null;
            try {
                $p2pMeeting = $this->p2pService->completeMatchAsP2pMeeting($match, $event);
                if ($p2pMeeting) {
                    $creativeUrl = $this->p2pService->getCreativeUrlForMatch($match);
                }
            } catch (\Throwable $p2pEx) {
                Log::warning('[SpeedNetworkingService] Could not complete match as P2P meeting in summary: '.$p2pEx->getMessage());
            }

            // Refresh peer after completing P2P meeting so life impact & profile details are fresh
            $peer = $peer->fresh() ?? $peer;
            $peerLifeImpact = (int) ($peer->life_impacted_count ?? 0);
            if ($peerLifeImpact === 0 && Schema::hasTable('life_impact_histories')) {
                try {
                    $historyTotal = (int) DB::table('life_impact_histories')
                        ->where('user_id', $peer->id)
                        ->sum(DB::raw('COALESCE(impact_value, life_impacted, 0)'));
                    if ($historyTotal > 0) {
                        $peerLifeImpact = $historyTotal;
                    }
                } catch (\Throwable $th) {
                    // ignore
                }
            }

            $isPro = method_exists($peer, 'isPro') ? (bool) $peer->isPro() : (! empty($peer->is_pro));
            $companyName = ! empty($peer->company_name) ? (string) $peer->company_name : (! empty($peer->company) ? (string) $peer->company : null);
            $businessCategory = $peer->level4_category ?? $peer->business_category ?? null;

            $interactedPeers[] = [
                'id' => (string) $peer->id,
                'peer_id' => (string) ($peer->peer_id ?? $peer->id),
                'name' => (string) $peerName,
                'display_name' => (string) $peerName,
                'first_name' => (string) ($peer->first_name ?? ''),
                'last_name' => (string) ($peer->last_name ?? ''),
                'company' => $companyName,
                'company_name' => $companyName,
                'designation' => ! empty($peer->designation) ? (string) $peer->designation : null,
                'business_category' => $businessCategory,
                'business_category_name' => $businessCategory,
                'level4_category' => $businessCategory,
                'avatar_url' => ! empty($peer->profile_photo_url) ? (string) $peer->profile_photo_url : null,
                'profile_photo_url' => ! empty($peer->profile_photo_url) ? (string) $peer->profile_photo_url : null,
                'profile_image_url' => ! empty($peer->profile_photo_url) ? (string) $peer->profile_photo_url : null,
                'city' => ! empty($peer->city) ? (string) $peer->city : null,
                'bio' => $bio !== '' ? $bio : null,
                'round' => (int) $match->round_number,
                'interaction_duration_seconds' => $duration,
                'connection_status' => $connectionStatus,
                'is_connected' => $connectionStatus === 'connected',
                'is_pro' => $isPro,
                'is_verified' => (bool) ($peer->is_verified ?? false),
                'life_impacted_count' => $peerLifeImpact,
                'life_impact_score' => $peerLifeImpact,
                'total_life_impacted' => $peerLifeImpact,
                'impact_score' => $peerLifeImpact,
                'life_impact' => $peerLifeImpact,
                'p2p_meeting_id' => $p2pMeeting?->id ? (string) $p2pMeeting->id : null,
                'creative_url' => $creativeUrl,
                'p2p_logged' => $p2pMeeting !== null,
            ];
        }

        $roundsCompleted = (int) $matches->pluck('round_number')->unique()->count();
        $hasMetAllPeers = $this->hasMetAllActivePeers($event, $userId);

        $freshUser = $user->fresh() ?? $user;
        $userLifeImpact = (int) ($freshUser->life_impacted_count ?? 0);
        if ($userLifeImpact === 0 && Schema::hasTable('life_impact_histories')) {
            try {
                $historyTotal = (int) DB::table('life_impact_histories')
                    ->where('user_id', $freshUser->id)
                    ->sum(DB::raw('COALESCE(impact_value, life_impacted, 0)'));
                if ($historyTotal > 0) {
                    $userLifeImpact = $historyTotal;
                }
            } catch (\Throwable $th) {
                // ignore
            }
        }

        $userCoins = (int) ($freshUser->coins_balance ?? 0);
        $impactPerMeeting = (int) config('impact.activity_rewards.p2p_meeting', 5);
        if ($impactPerMeeting <= 0) {
            $impactPerMeeting = 5;
        }
        $lifeImpactEarned = count($interactedPeers) * $impactPerMeeting;
        $coinsEarned = count($interactedPeers) * 10;

        return [
            'completed' => true,
            'has_met_all_peers' => $hasMetAllPeers,
            'message' => 'All speed networking rounds have concluded. Great conversations made!',
            'total_rounds' => (int) $event->total_rounds,
            'rounds_completed' => $roundsCompleted,
            'total_interactions_count' => count($interactedPeers),
            'life_impact' => [
                'earned' => $lifeImpactEarned,
                'total' => $userLifeImpact,
                'balance_after' => $userLifeImpact,
            ],
            'life_impact_earned' => $lifeImpactEarned,
            'life_impact_score' => $userLifeImpact,
            'total_life_impacted' => $userLifeImpact,
            'life_impacted_count' => $userLifeImpact,
            'coins' => [
                'earned' => $coinsEarned,
                'balance_after' => $userCoins,
            ],
            'coins_earned' => $coinsEarned,
            'interacted_peers' => $interactedPeers,
        ];
    }
}
