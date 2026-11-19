<?php

declare(strict_types=1);

namespace App\Services\SpeedNetworking;

use App\Models\Connection;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\SpeedNetworking\SpeedNetworkingBlockedPeer;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\SpeedNetworking\SpeedNetworkingEventSkip;
use App\Models\SpeedNetworking\SpeedNetworkingMatch;
use App\Models\SpeedNetworking\SpeedNetworkingParticipant;
use App\Models\User;
use App\Services\PushNotificationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SpeedNetworkingService
{
    protected static bool $schemaVerified = false;

    protected SpeedNetworkingP2pService $p2pService;

    public function __construct(
        protected AgoraTokenService $agoraTokenService,
        ?SpeedNetworkingP2pService $p2pService = null
    ) {
        $this->p2pService = $p2pService ?? app(SpeedNetworkingP2pService::class);
        $this->ensureSpeedNetworkingSchema();
    }

    /**
     * Ensure required Speed Networking preview, skip, and block tables/columns exist.
     */
    public function ensureSpeedNetworkingSchema(): void
    {
        if (
            self::$schemaVerified
            && Schema::hasTable('speed_networking_blocked_peers')
            && Schema::hasTable('speed_networking_event_skips')
        ) {
            return;
        }

        try {
            if (Schema::hasTable('speed_networking_matches')) {
                Schema::table('speed_networking_matches', function (Blueprint $table): void {
                    if (! Schema::hasColumn('speed_networking_matches', 'match_status')) {
                        $table->string('match_status', 32)->default('preview')->index();
                    }
                    if (! Schema::hasColumn('speed_networking_matches', 'user_a_decision')) {
                        $table->string('user_a_decision', 16)->default('pending');
                    }
                    if (! Schema::hasColumn('speed_networking_matches', 'user_b_decision')) {
                        $table->string('user_b_decision', 16)->default('pending');
                    }
                    if (! Schema::hasColumn('speed_networking_matches', 'preview_duration_seconds')) {
                        $table->integer('preview_duration_seconds')->default(15);
                    }
                    if (! Schema::hasColumn('speed_networking_matches', 'preview_expires_at')) {
                        $table->timestamp('preview_expires_at')->nullable();
                    }
                    if (! Schema::hasColumn('speed_networking_matches', 'call_started_at')) {
                        $table->timestamp('call_started_at')->nullable();
                    }
                    if (! Schema::hasColumn('speed_networking_matches', 'duration_seconds')) {
                        $table->integer('duration_seconds')->nullable();
                    }
                    if (! Schema::hasColumn('speed_networking_matches', 'user_a_notes')) {
                        $table->text('user_a_notes')->nullable();
                    }
                    if (! Schema::hasColumn('speed_networking_matches', 'user_b_notes')) {
                        $table->text('user_b_notes')->nullable();
                    }
                    if (! Schema::hasColumn('speed_networking_matches', 'meeting_notes')) {
                        $table->text('meeting_notes')->nullable();
                    }
                });
            }

            if (! Schema::hasTable('speed_networking_blocked_peers')) {
                Schema::create('speed_networking_blocked_peers', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('blocker_id', 64)->index();
                    $table->string('blocked_id', 64)->index();
                    $table->string('event_id', 64)->nullable()->index();
                    $table->string('reason', 255)->nullable();
                    $table->timestamps();

                    $table->unique(['blocker_id', 'blocked_id'], 'uq_sn_blocker_blocked');
                    $table->index(['blocked_id', 'blocker_id'], 'idx_sn_blocked_blocker');
                });
            }

            if (! Schema::hasTable('speed_networking_event_skips')) {
                Schema::create('speed_networking_event_skips', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('event_id', 64)->index();
                    $table->string('user_id', 64)->index();
                    $table->string('skipped_peer_id', 64)->index();
                    $table->timestamps();

                    $table->unique(['event_id', 'user_id', 'skipped_peer_id'], 'uq_sn_event_skip');
                    $table->index(['event_id', 'skipped_peer_id', 'user_id'], 'idx_sn_event_skip_reverse');
                });
            }

            self::$schemaVerified = true;
        } catch (\Throwable $e) {
            Log::warning('[SpeedNetworkingService] ensureSpeedNetworkingSchema warning: '.$e->getMessage());
        }
    }

    /**
     * Get all peers blocked by a user in Speed Networking.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBlockedPeersForUser(string $userId): array
    {
        $this->ensureSpeedNetworkingSchema();

        $blockedMap = [];

        // 1. From speed_networking_blocked_peers
        if (Schema::hasTable('speed_networking_blocked_peers')) {
            $records = SpeedNetworkingBlockedPeer::with('blockedUser')
                ->where('blocker_id', $userId)
                ->latest()
                ->get();

            foreach ($records as $row) {
                $u = $row->blockedUser;
                $targetId = (string) ($u?->id ?? $row->blocked_id);
                if ($targetId === '') {
                    continue;
                }
                $blockedMap[$targetId] = [
                    'user' => $u,
                    'target_id' => $targetId,
                    'reason' => $row->reason,
                    'blocked_at' => $row->created_at?->toIso8601String(),
                ];
            }
        }

        // 2. Also merge from general peer_blocks table
        if (Schema::hasTable('peer_blocks')) {
            try {
                $generalBlocks = DB::table('peer_blocks')
                    ->where('blocker_user_id', $userId)
                    ->latest()
                    ->get();

                foreach ($generalBlocks as $gb) {
                    $targetId = (string) $gb->blocked_user_id;
                    if ($targetId === '' || isset($blockedMap[$targetId])) {
                        continue;
                    }
                    $blockedMap[$targetId] = [
                        'user' => null,
                        'target_id' => $targetId,
                        'reason' => $gb->reason ?? 'Blocked from Speed Collaboration',
                        'blocked_at' => isset($gb->created_at) ? Carbon::parse($gb->created_at)->toIso8601String() : null,
                    ];
                }
            } catch (\Throwable) {
            }
        }

        // Fetch user records if missing from relations
        $missingUserIds = [];
        foreach ($blockedMap as $tid => $data) {
            if (! $data['user']) {
                $missingUserIds[] = $tid;
            }
        }
        if (! empty($missingUserIds)) {
            $fetchedUsers = User::whereIn('id', $missingUserIds)->get()->keyBy(fn ($u) => (string) $u->id);
            foreach ($missingUserIds as $muId) {
                if (isset($fetchedUsers[$muId])) {
                    $blockedMap[$muId]['user'] = $fetchedUsers[$muId];
                }
            }
        }

        $results = [];
        foreach ($blockedMap as $targetId => $entry) {
            /** @var User|null $u */
            $u = $entry['user'];
            $name = 'Peer';
            if ($u) {
                $name = $u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
                if ($name === '') {
                    $name = (string) ($u->name ?? 'Peer');
                }
            }

            $company = (string) ($u?->company_name ?? $u?->company ?? '');
            $avatarUrl = (string) ($u?->profile_photo_url ?? '');
            $industry = (string) ($u?->business_sub_category ?? $u?->company_type ?? 'Business & Entrepreneurship');

            $results[] = [
                'id' => $targetId,
                'peer_id' => $targetId,
                'blocked_peer_id' => $targetId,
                'name' => $name,
                'display_name' => $name,
                'designation' => (string) ($u?->designation ?? ''),
                'company' => $company,
                'company_name' => $company,
                'avatar_url' => $avatarUrl,
                'profile_photo_url' => $avatarUrl,
                'city' => (string) ($u?->city ?? ''),
                'industry' => $industry,
                'category' => $industry,
                'reason' => $entry['reason'],
                'blocked_at' => $entry['blocked_at'],
            ];
        }

        return $results;
    }

    /**
     * Permanently block a peer from Speed Networking matching across all current and future Speed Networking events.
     */
    public function blockPeerInSpeedNetworking(
        string $blockerId,
        string $blockedId,
        ?string $eventId = null,
        ?string $reason = null
    ): SpeedNetworkingBlockedPeer {
        $this->ensureSpeedNetworkingSchema();

        $record = SpeedNetworkingBlockedPeer::updateOrCreate(
            [
                'blocker_id' => $blockerId,
                'blocked_id' => $blockedId,
            ],
            [
                'event_id' => $eventId ?: null,
                'reason' => $reason ?: 'Blocked from Speed Collaboration',
            ]
        );

        // Also mirror to global peer_blocks table
        if (Schema::hasTable('peer_blocks')) {
            try {
                DB::table('peer_blocks')->updateOrInsert(
                    [
                        'blocker_user_id' => $blockerId,
                        'blocked_user_id' => $blockedId,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'reason' => $reason ?: 'Blocked from Speed Collaboration',
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]
                );
            } catch (\Throwable) {
            }
        }

        // If an active preview or call exists between these two peers in ANY event, cancel it and free both to waiting
        if (Schema::hasTable('speed_networking_matches')) {
            $activeMatches = SpeedNetworkingMatch::query()
                ->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)
                ->where(function ($q) use ($blockerId, $blockedId): void {
                    $q->where(function ($sub) use ($blockerId, $blockedId): void {
                        $sub->where('user_a_id', $blockerId)->where('user_b_id', $blockedId);
                    })->orWhere(function ($sub) use ($blockerId, $blockedId): void {
                        $sub->where('user_a_id', $blockedId)->where('user_b_id', $blockerId);
                    });
                })
                ->get();

            foreach ($activeMatches as $match) {
                $isUserA = ((string) $match->user_a_id === $blockerId);
                $decisionCol = $isUserA ? 'user_a_decision' : 'user_b_decision';
                $match->$decisionCol = SpeedNetworkingMatch::DECISION_BLOCK;
                $match->match_status = SpeedNetworkingMatch::MATCH_STATUS_BLOCKED;
                $match->status = SpeedNetworkingMatch::STATUS_SKIPPED;
                $match->ended_at = Carbon::now();
                $match->save();

                if (Schema::hasTable('speed_networking_participants')) {
                    SpeedNetworkingParticipant::where('event_id', $match->event_id)
                        ->whereIn('user_id', [$blockerId, $blockedId])
                        ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
                        ->update([
                            'status' => SpeedNetworkingParticipant::STATUS_SEARCHING,
                            'last_heartbeat_at' => Carbon::now(),
                        ]);
                }
            }
        }

        return $record;
    }

    /**
     * Unblock a peer from Speed Networking matching.
     */
    public function unblockPeerInSpeedNetworking(string $blockerId, string $blockedId): void
    {
        $this->ensureSpeedNetworkingSchema();

        if (Schema::hasTable('speed_networking_blocked_peers')) {
            SpeedNetworkingBlockedPeer::where('blocker_id', $blockerId)
                ->where('blocked_id', $blockedId)
                ->delete();
        }

        if (Schema::hasTable('peer_blocks')) {
            try {
                DB::table('peer_blocks')
                    ->where('blocker_user_id', $blockerId)
                    ->where('blocked_user_id', $blockedId)
                    ->delete();
            } catch (\Throwable) {
            }
        }
    }

    /**
     * Get all peer IDs bidirectionally blocked with $userId across all events.
     *
     * @return array<int, string>
     */
    public function getBidirectionallyBlockedPeerIds(string $userId): array
    {
        $this->ensureSpeedNetworkingSchema();

        $blockedIds = [];

        // 1. Speed Networking specific blocks (bidirectional)
        if (Schema::hasTable('speed_networking_blocked_peers')) {
            $snBlocked = SpeedNetworkingBlockedPeer::query()
                ->where('blocker_id', $userId)
                ->orWhere('blocked_id', $userId)
                ->get(['blocker_id', 'blocked_id'])
                ->map(fn (SpeedNetworkingBlockedPeer $b): string => (string) ((string) $b->blocker_id === $userId ? $b->blocked_id : $b->blocker_id))
                ->filter()
                ->all();

            $blockedIds = array_merge($blockedIds, $snBlocked);
        }

        // 2. Global peer blocks (bidirectional)
        if (Schema::hasTable('peer_blocks')) {
            try {
                $globalBlocked = DB::table('peer_blocks')
                    ->where('blocker_user_id', $userId)
                    ->orWhere('blocked_user_id', $userId)
                    ->get(['blocker_user_id', 'blocked_user_id'])
                    ->map(fn ($b): string => (string) ((string) $b->blocker_user_id === $userId ? $b->blocked_user_id : $b->blocker_user_id))
                    ->filter()
                    ->all();

                $blockedIds = array_merge($blockedIds, $globalBlocked);
            } catch (\Throwable) {
            }
        }

        return array_values(array_unique(array_filter($blockedIds)));
    }

    /**
     * Get all peer IDs skipped or blocked with $userId in a specific Speed Networking event.
     *
     * @return array<int, string>
     */
    public function getEventSkippedPeerIds(string $eventId, string $userId): array
    {
        $this->ensureSpeedNetworkingSchema();

        $skippedIds = [];

        if (Schema::hasTable('speed_networking_event_skips')) {
            $skippedIds = SpeedNetworkingEventSkip::query()
                ->where('event_id', $eventId)
                ->where(function ($q) use ($userId): void {
                    $q->where('user_id', $userId)
                        ->orWhere('skipped_peer_id', $userId);
                })
                ->get(['user_id', 'skipped_peer_id'])
                ->map(fn (SpeedNetworkingEventSkip $s): string => (string) ((string) $s->user_id === $userId ? $s->skipped_peer_id : $s->user_id))
                ->filter()
                ->all();
        }

        if (Schema::hasTable('speed_networking_matches')) {
            $matchSkippedIds = SpeedNetworkingMatch::query()
                ->where('event_id', $eventId)
                ->whereNotNull('user_b_id')
                ->where(function ($q): void {
                    $q->whereIn('match_status', [
                        SpeedNetworkingMatch::MATCH_STATUS_SKIPPED,
                        SpeedNetworkingMatch::MATCH_STATUS_EXPIRED,
                    ])->orWhere('status', SpeedNetworkingMatch::STATUS_SKIPPED);
                })
                ->where(function ($q) use ($userId): void {
                    $q->where('user_a_id', $userId)
                        ->orWhere('user_b_id', $userId);
                })
                ->get(['user_a_id', 'user_b_id'])
                ->map(fn (SpeedNetworkingMatch $m): string => (string) ((string) $m->user_a_id === $userId ? $m->user_b_id : $m->user_a_id))
                ->filter()
                ->all();

            $skippedIds = array_merge($skippedIds, $matchSkippedIds);
        }

        return array_values(array_unique($skippedIds));
    }

    /**
     * Get the configured Agora App ID.
     */
    public function getAgoraAppId(): ?string
    {
        try {
            return $this->agoraTokenService->getAppId();
        } catch (\Throwable) {
            $appId = config('services.agora.app_id') ?: env('AGORA_APP_ID');

            return ! empty($appId) ? (string) $appId : null;
        }
    }

    /**
     * Get paginated list of Speed Networking events for Admin panel.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listEvents(array $filters = [], int $perPage = 15): array
    {
        try {
            $this->checkAndAutoStartScheduledEvents();
        } catch (\Throwable) {
        }

        $hasSnTable = false;
        try {
            $hasSnTable = Schema::hasTable('speed_networking_events');
        } catch (\Throwable $e) {
        }

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

                $mapped = collect($paginated->items())->map(function (SpeedNetworkingEvent $item): object {
                    $durationSec = max(60, $item->calculateDurationSeconds());
                    $durationMin = (int) ceil($durationSec / 60);
                    $schedStart = $item->scheduled_start_time ?? $item->actual_start_time ?? Carbon::now();
                    $schedEnd = $item->scheduled_end_time ?? $schedStart->copy()->addSeconds($durationSec);

                    return (object) [
                        'id' => (string) $item->id,
                        'title' => (string) $item->title,
                        'description' => $item->description,
                        'banner_url' => $item->banner_url,
                        'status' => (string) $item->status,
                        'round_duration_seconds' => (int) $item->round_duration_seconds,
                        'break_duration_seconds' => (int) $item->break_duration_seconds,
                        'total_rounds' => (int) $item->total_rounds,
                        'current_round' => (int) $item->current_round,
                        'duration_seconds' => $durationSec,
                        'duration_minutes' => $durationMin,
                        'duration' => $durationMin,
                        'scheduled_start_time' => $schedStart->copy()->utc()->toISOString(),
                        'scheduled_end_time' => $schedEnd->copy()->utc()->toISOString(),
                        'end_time' => $schedEnd->copy()->utc()->toISOString(),
                        'actual_start_time' => $item->actual_start_time?->copy()->utc()->toISOString(),
                        'actual_end_time' => $item->actual_end_time?->copy()->utc()->toISOString(),
                        'ticket_price' => (float) ($item->ticket_price ?? 0),
                        'is_paid' => (bool) ($item->is_paid ?? false),
                        'currency' => (string) ($item->currency ?? 'INR'),
                        'circle_id' => $item->circle_id ? (string) $item->circle_id : null,
                        'circle_restricted' => (bool) ($item->circle_restricted ?? false),
                        'participants_count' => (int) ($item->participants_count ?? 0),
                        'active_participants_count' => (int) ($item->active_participants_count ?? $item->participants_count ?? 0),
                        'matches_count' => (int) ($item->matches_count ?? 0),
                        'created_at' => $item->created_at?->toIso8601String(),
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
                $roundDuration = (int) ($meta['round_duration_seconds'] ?? 300);
                $breakDuration = (int) ($meta['break_duration_seconds'] ?? 20);
                $totalRounds = (int) ($meta['total_rounds'] ?? 5);

                $durationSec = null;
                if (! empty($meta['duration_seconds'])) {
                    $durationSec = (int) $meta['duration_seconds'];
                } elseif (! empty($meta['duration_minutes'])) {
                    $durationSec = (int) $meta['duration_minutes'] * 60;
                } elseif (! empty($meta['duration'])) {
                    $durationSec = (int) ($meta['duration'] * 60);
                } else {
                    $durationSec = $totalRounds > 0
                        ? ($totalRounds * $roundDuration) + (max(0, $totalRounds - 1) * $breakDuration)
                        : (5 * $roundDuration) + (4 * $breakDuration);
                }
                $durationMin = (int) ceil($durationSec / 60);

                $startAt = $e->start_at ? Carbon::parse($e->start_at) : null;
                $endAt = ! empty($meta['scheduled_end_time'])
                    ? Carbon::parse($meta['scheduled_end_time'])
                    : (! empty($e->end_at)
                        ? Carbon::parse($e->end_at)
                        : ($startAt ? $startAt->copy()->addSeconds($durationSec) : Carbon::now()->addSeconds($durationSec)));

                $regCount = 0;
                try {
                    $regCount = $e->registrations()->count();
                } catch (\Throwable) {
                }

                $schedStart = $startAt ?? Carbon::now();

                return (object) [
                    'id' => $e->id,
                    'title' => $e->title,
                    'description' => $e->description,
                    'status' => $meta['speed_status'] ?? 'scheduled',
                    'round_duration_seconds' => $roundDuration,
                    'break_duration_seconds' => $breakDuration,
                    'total_rounds' => $totalRounds,
                    'current_round' => (int) ($meta['current_round'] ?? 0),
                    'duration_seconds' => $durationSec,
                    'duration_minutes' => $durationMin,
                    'duration' => $durationMin,
                    'scheduled_start_time' => $schedStart->copy()->utc()->toISOString(),
                    'scheduled_end_time' => $endAt->copy()->utc()->toISOString(),
                    'end_time' => $endAt->copy()->utc()->toISOString(),
                    'actual_start_time' => ! empty($meta['actual_start_time']) ? Carbon::parse($meta['actual_start_time'])->utc()->toISOString() : $startAt?->copy()->utc()->toISOString(),
                    'actual_end_time' => $meta['actual_end_time'] ?? null,
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
        try {
            $this->checkAndAutoStartScheduledEvents();
            $event->refresh();
        } catch (\Throwable) {
        }

        $event->loadMissing('circle');
        $totalParticipants = $event->participants()->count();
        $activeParticipants = $event->participants()
            ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
            ->count();
        $totalMatches = $event->matches()->count();
        $activeMatches = $event->matches()
            ->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)
            ->count();

        $activeTablesCount = $event->matches()
            ->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)
            ->whereNotNull('user_b_id')
            ->count();
        if ($activeTablesCount === 0 && $event->status === SpeedNetworkingEvent::STATUS_IN_PROGRESS) {
            $activeTablesCount = (int) ceil($activeParticipants / 2);
        }

        $byStatus = $event->participants()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        return [
            'event' => $event,
            'stats' => [
                'total_participants' => $totalParticipants,
                'active_participants' => $activeParticipants,
                'total_matches' => $totalMatches,
                'active_matches' => $activeMatches,
                'active_tables_count' => $activeTablesCount,
                'participants_by_status' => [
                    'in_call' => (int) ($byStatus['in_call'] ?? 0),
                    'break' => (int) ($byStatus['break'] ?? 0),
                    'left' => (int) ($byStatus['left'] ?? 0),
                    'lobby' => (int) ($byStatus['lobby'] ?? 0),
                    'registered' => (int) ($byStatus['registered'] ?? 0),
                ],
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

        $ticketPrice = round(max((float) ($data['ticket_price'] ?? ($data['ticketPrice'] ?? 0)), 0), 2);
        $isFree = (bool) ($data['is_free'] ?? ($data['isFree'] ?? false));
        if ($isFree) {
            $ticketPrice = 0.0;
            $isPaid = false;
        } else {
            $isPaid = (bool) ($data['is_paid'] ?? ($data['isPaid'] ?? false)) || $ticketPrice > 0;
        }

        $circleId = ! empty($data['circle_id']) ? (string) $data['circle_id'] : null;
        $circleRestricted = (bool) ($data['circle_restricted'] ?? ! empty($circleId));
        $currency = ! empty($data['currency']) ? (string) $data['currency'] : 'INR';

        $roundDuration = (int) ($data['round_duration_seconds'] ?? config('services.speed_networking.default_round_seconds', 180));
        $breakDuration = (int) ($data['break_duration_seconds'] ?? config('services.speed_networking.default_break_seconds', 20));
        $totalRounds = isset($data['total_rounds']) && $data['total_rounds'] !== '' ? max(0, (int) $data['total_rounds']) : 0;

        $hasExplicitDuration = ! empty($data['duration_seconds']) || ! empty($data['duration_minutes']) || ! empty($data['duration']);

        $durationSeconds = null;
        if (! empty($data['duration_seconds'])) {
            $durationSeconds = (int) $data['duration_seconds'];
        } elseif (! empty($data['duration_minutes'])) {
            $durationSeconds = (int) $data['duration_minutes'] * 60;
        } elseif (! empty($data['duration'])) {
            $durationSeconds = (int) ($data['duration'] * 60);
        } else {
            $durationSeconds = $totalRounds > 0
                ? ($totalRounds * $roundDuration) + (max(0, $totalRounds - 1) * $breakDuration)
                : (5 * $roundDuration) + (4 * $breakDuration);
        }

        $timezone = $data['timezone']
            ?? $data['_browser_timezone']
            ?? request()?->input('timezone')
            ?? request()?->input('_browser_timezone')
            ?? request()?->header('X-Timezone')
            ?? (config('app.timezone') ?: 'Asia/Kolkata');
        if ($timezone === 'UTC' && empty($data['timezone']) && empty($data['_browser_timezone']) && ! request()?->has('timezone') && ! request()?->has('_browser_timezone')) {
            $timezone = 'Asia/Kolkata';
        }

        $scheduledStart = null;
        if (! empty($data['scheduled_start_time'])) {
            $rawStartStr = trim((string) $data['scheduled_start_time']);
            $hasExplicitTz = (bool) preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', $rawStartStr);
            $scheduledStart = ($hasExplicitTz ? Carbon::parse($rawStartStr) : Carbon::parse($rawStartStr, $timezone))->utc();
        }

        if ($hasExplicitDuration && $scheduledStart) {
            $scheduledEnd = $scheduledStart->copy()->addSeconds($durationSeconds);
        } elseif (! empty($data['scheduled_end_time'])) {
            $rawEndStr = trim((string) $data['scheduled_end_time']);
            $hasExplicitEndTz = (bool) preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', $rawEndStr);
            $scheduledEnd = ($hasExplicitEndTz ? Carbon::parse($rawEndStr) : Carbon::parse($rawEndStr, $timezone))->utc();
            if ($scheduledStart && $scheduledEnd->greaterThan($scheduledStart)) {
                $durationSeconds = max(60, (int) abs($scheduledStart->diffInSeconds($scheduledEnd, true)));
            }
        } else {
            $scheduledEnd = $scheduledStart ? $scheduledStart->copy()->addSeconds($durationSeconds) : null;
        }

        $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [];
        $metadata['duration_seconds'] = $durationSeconds;
        $metadata['duration_minutes'] = (int) ceil($durationSeconds / 60);

        $attributes = [
            'title' => (string) $data['title'],
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'banner_url' => isset($data['banner_url']) ? (string) $data['banner_url'] : null,
            'round_duration_seconds' => $roundDuration,
            'break_duration_seconds' => $breakDuration,
            'total_rounds' => $totalRounds,
            'scheduled_start_time' => $scheduledStart,
            'scheduled_end_time' => $scheduledEnd,
            'actual_start_time' => ! empty($data['actual_start_time']) ? Carbon::parse($data['actual_start_time']) : null,
            'actual_end_time' => ! empty($data['actual_end_time']) ? Carbon::parse($data['actual_end_time']) : null,
            'ticket_price' => $ticketPrice,
            'is_paid' => $isPaid,
            'currency' => $currency,
            'circle_id' => $circleId,
            'circle_restricted' => $circleRestricted,
            'metadata' => $metadata,
        ];

        if (! empty($data['status'])) {
            $attributes['status'] = (string) $data['status'];
        }

        if (Schema::hasTable('speed_networking_events') && ! Schema::hasColumn('speed_networking_events', 'metadata')) {
            unset($attributes['metadata']);
        }

        if ($id) {
            $event = SpeedNetworkingEvent::findOrFail($id);
            if ($hasExplicitDuration || empty($attributes['scheduled_end_time'])) {
                $baseStart = $attributes['scheduled_start_time'] ?? $event->scheduled_start_time;
                if ($baseStart) {
                    $attributes['scheduled_end_time'] = Carbon::parse($baseStart)->addSeconds($durationSeconds);
                }
            }
            $event->update($attributes);

            try {
                $endAt = $event->scheduled_end_time
                    ?? ($event->scheduled_start_time ? Carbon::parse($event->scheduled_start_time)->addSeconds($durationSeconds) : Carbon::now()->addSeconds($durationSeconds));

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
                        'circle_id' => $circleId,
                        'start_at' => $event->scheduled_start_time ?? Carbon::now(),
                        'end_at' => $endAt,
                        'metadata' => array_merge(
                            is_array($event->metadata) ? $event->metadata : [],
                            [
                                'round_duration_seconds' => $event->round_duration_seconds,
                                'break_duration_seconds' => $event->break_duration_seconds,
                                'total_rounds' => $event->total_rounds,
                                'current_round' => $event->current_round,
                                'speed_status' => $event->status,
                                'ticket_price' => $ticketPrice,
                                'is_paid' => $isPaid,
                                'currency' => $currency,
                                'circle_id' => $circleId,
                                'circle_restricted' => $circleRestricted,
                                'scheduled_end_time' => $event->scheduled_end_time?->toIso8601String(),
                                'duration_seconds' => $durationSeconds,
                                'duration_minutes' => (int) ceil($durationSeconds / 60),
                            ]
                        ),
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('Could not mirror SpeedNetworkingEvent update to Event', ['error' => $e->getMessage()]);
            }

            return $event;
        }

        $attributes['status'] = ! empty($data['status'])
            ? (string) $data['status']
            : (! empty($attributes['scheduled_start_time'])
                ? SpeedNetworkingEvent::STATUS_SCHEDULED
                : SpeedNetworkingEvent::STATUS_DRAFT);
        $attributes['current_round'] = 0;

        $event = SpeedNetworkingEvent::create($attributes);

        try {
            $endAt = $event->scheduled_end_time
                ?? ($event->scheduled_start_time ? Carbon::parse($event->scheduled_start_time)->addSeconds($durationSeconds) : Carbon::now()->addSeconds($durationSeconds));

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
                    'circle_id' => $circleId,
                    'start_at' => $event->scheduled_start_time ?? Carbon::now(),
                    'end_at' => $endAt,
                    'metadata' => array_merge(
                        is_array($event->metadata) ? $event->metadata : [],
                        [
                            'round_duration_seconds' => $event->round_duration_seconds,
                            'break_duration_seconds' => $event->break_duration_seconds,
                            'total_rounds' => $event->total_rounds,
                            'current_round' => $event->current_round,
                            'speed_status' => $event->status,
                            'ticket_price' => $ticketPrice,
                            'is_paid' => $isPaid,
                            'currency' => $currency,
                            'circle_id' => $circleId,
                            'circle_restricted' => $circleRestricted,
                            'scheduled_end_time' => $event->scheduled_end_time?->toIso8601String(),
                            'duration_seconds' => $durationSeconds,
                            'duration_minutes' => (int) ceil($durationSeconds / 60),
                        ]
                    ),
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
        $started = DB::transaction(function () use ($event): SpeedNetworkingEvent {
            $event->status = SpeedNetworkingEvent::STATUS_IN_PROGRESS;
            $event->actual_start_time = Carbon::now();
            $event->current_round = 1;
            $event->save();

            $this->generateMatchesForRound($event, 1);

            return $event->fresh(['participants', 'matches']);
        });

        try {
            $baseEvent = Event::find($event->id);
            if ($baseEvent) {
                $meta = $baseEvent->metadata ?? [];
                $meta['speed_status'] = 'in_progress';
                $meta['current_round'] = 1;
                $meta['actual_start_time'] = ($event->actual_start_time ?? Carbon::now())->toIso8601String();
                $baseEvent->metadata = $meta;
                $baseEvent->start_at = $event->scheduled_start_time ?? $baseEvent->start_at ?? Carbon::now();
                $baseEvent->save();
            }
        } catch (\Throwable) {
        }

        // Notify all registered participants that the session has gone live
        $this->notifyAttendeesEventStarted($started);

        return $started;
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
            $effectiveRounds = $this->getEffectiveTotalRounds($event);

            if ($nextRound > $effectiveRounds) {
                $event->status = SpeedNetworkingEvent::STATUS_COMPLETED;
                $event->actual_end_time = Carbon::now();
                $event->save();

                try {
                    $baseEvent = Event::find($event->id);
                    if ($baseEvent) {
                        $meta = $baseEvent->metadata ?? [];
                        $meta['speed_status'] = 'completed';
                        $baseEvent->metadata = $meta;
                        $baseEvent->end_at = Carbon::now();
                        $baseEvent->save();
                    }
                } catch (\Throwable) {
                }

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
            $event->actual_end_time = Carbon::now();
            $event->save();

            try {
                $baseEvent = Event::find($event->id);
                if ($baseEvent) {
                    $meta = $baseEvent->metadata ?? [];
                    $meta['speed_status'] = 'completed';
                    $meta['actual_end_time'] = ($event->actual_end_time ?? Carbon::now())->toIso8601String();
                    $baseEvent->metadata = $meta;
                    $baseEvent->end_at = $event->scheduled_end_time ?? $baseEvent->end_at ?? Carbon::now();
                    if (Schema::hasColumn('events', 'status')) {
                        $baseEvent->status = 'completed';
                    }
                    $baseEvent->save();
                }
            } catch (\Throwable) {
            }

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
     * Calculate effective total rounds for an event.
     * Mode 1: If total_rounds is explicitly configured (> 0), return that forced round count.
     * Mode 2: If total_rounds is 0 or null (Auto-Scale), calculate dynamically based on active/joined participants:
     *         For N participants: N even -> N - 1 rounds; N odd -> N rounds (with 1 rotation bye).
     */
    public function getEffectiveTotalRounds(SpeedNetworkingEvent $event): int
    {
        if ($event->total_rounds > 0) {
            return (int) $event->total_rounds;
        }

        $activeCount = SpeedNetworkingParticipant::where('event_id', $event->id)
            ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
            ->count();

        if ($activeCount <= 1) {
            return 1;
        }

        return ($activeCount % 2 === 0) ? ($activeCount - 1) : $activeCount;
    }

    /**
     * Pause an in-progress Speed Networking event.
     */
    public function pauseEvent(SpeedNetworkingEvent $event): SpeedNetworkingEvent
    {
        $event->status = SpeedNetworkingEvent::STATUS_PAUSED;
        $event->save();

        try {
            Event::where('id', $event->id)->update([
                'metadata->speed_status' => SpeedNetworkingEvent::STATUS_PAUSED,
            ]);
        } catch (\Throwable $e) {
        }

        return $event;
    }

    /**
     * Resume a paused Speed Networking event.
     */
    public function resumeEvent(SpeedNetworkingEvent $event): SpeedNetworkingEvent
    {
        $event->status = SpeedNetworkingEvent::STATUS_IN_PROGRESS;
        $event->save();

        try {
            Event::where('id', $event->id)->update([
                'metadata->speed_status' => SpeedNetworkingEvent::STATUS_IN_PROGRESS,
            ]);
        } catch (\Throwable $e) {
        }

        return $event;
    }

    /**
     * Force trigger rematching for active round to pair any waiting participants.
     *
     * @return array<string, mixed>
     */
    public function rematchEvent(SpeedNetworkingEvent $event): array
    {
        $round = max(1, (int) $event->current_round);
        $this->generateMatchesForRound($event, $round);

        return $this->getLiveTables($event);
    }

    /**
     * Get matches / connections for an event for Admin Panel Connects View.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getEventMatches(SpeedNetworkingEvent $event, array $filters = [], int $perPage = 50): array
    {
        $query = SpeedNetworkingMatch::with(['userA', 'userB'])
            ->where('event_id', $event->id)
            ->orderBy('round_number', 'desc')
            ->orderBy('table_number', 'asc');

        if (! empty($filters['round_number'])) {
            $query->where('round_number', (int) $filters['round_number']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.trim((string) $filters['search']).'%';
            $query->where(function ($q) use ($search): void {
                $q->whereHas('userA', function ($uq) use ($search): void {
                    $uq->where('first_name', 'like', $search)
                        ->orWhere('last_name', 'like', $search)
                        ->orWhere('company_name', 'like', $search)
                        ->orWhere('display_name', 'like', $search);
                })->orWhereHas('userB', function ($uq) use ($search): void {
                    $uq->where('first_name', 'like', $search)
                        ->orWhere('last_name', 'like', $search)
                        ->orWhere('company_name', 'like', $search)
                        ->orWhere('display_name', 'like', $search);
                });
            });
        }

        $paginated = $query->paginate($perPage);

        $now = Carbon::now();
        $matches = collect($paginated->items())->map(function (SpeedNetworkingMatch $m) use ($now): array {
            $ua = $m->userA;
            $ub = $m->userB;
            $durationSeconds = 0;
            if ($m->started_at && $m->ended_at) {
                $durationSeconds = (int) $m->ended_at->diffInSeconds($m->started_at);
            } elseif ($m->started_at && $m->status === SpeedNetworkingMatch::STATUS_ACTIVE) {
                $durationSeconds = (int) $now->diffInSeconds($m->started_at);
            }

            return [
                'id' => (string) $m->id,
                'match_id' => (string) $m->id,
                'event_id' => (string) $m->event_id,
                'round_number' => (int) $m->round_number,
                'table_number' => (int) $m->table_number,
                'channel_id' => (string) $m->channel_id,
                'status' => (string) $m->status,
                'is_break_round' => $m->isBreakRound(),
                'started_at' => $m->started_at?->toIso8601String(),
                'ended_at' => $m->ended_at?->toIso8601String(),
                'duration_seconds' => $durationSeconds,
                'user_a' => $ua ? [
                    'id' => (string) $ua->id,
                    'name' => $ua->display_name ?: trim(($ua->first_name ?? '').' '.($ua->last_name ?? '')) ?: $ua->name,
                    'company' => (string) ($ua->company_name ?? 'Peers Member'),
                    'designation' => (string) ($ua->designation ?? ''),
                    'city' => (string) ($ua->city ?? ''),
                    'avatar_url' => $ua->profile_photo_url ?? null,
                    'phone' => (string) ($ua->phone ?? ''),
                    'email' => (string) ($ua->email ?? ''),
                ] : null,
                'user_b' => $ub ? [
                    'id' => (string) $ub->id,
                    'name' => $ub->display_name ?: trim(($ub->first_name ?? '').' '.($ub->last_name ?? '')) ?: $ub->name,
                    'company' => (string) ($ub->company_name ?? 'Peers Member'),
                    'designation' => (string) ($ub->designation ?? ''),
                    'city' => (string) ($ub->city ?? ''),
                    'avatar_url' => $ub->profile_photo_url ?? null,
                    'phone' => (string) ($ub->phone ?? ''),
                    'email' => (string) ($ub->email ?? ''),
                ] : null,
            ];
        });

        // Summary metrics
        $totalMatches = SpeedNetworkingMatch::where('event_id', $event->id)->count();
        $completedMatches = SpeedNetworkingMatch::where('event_id', $event->id)->where('status', SpeedNetworkingMatch::STATUS_COMPLETED)->count();
        $activeMatches = SpeedNetworkingMatch::where('event_id', $event->id)->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)->count();
        $uniquePairs = SpeedNetworkingMatch::where('event_id', $event->id)->whereNotNull('user_b_id')->count();

        return [
            'summary' => [
                'total_matches' => $totalMatches,
                'completed_matches' => $completedMatches,
                'active_matches' => $activeMatches,
                'unique_pairs_count' => $uniquePairs,
                'current_round' => (int) $event->current_round,
                'total_rounds' => (int) $event->total_rounds,
            ],
            'data' => $matches->values(),
            'pagination' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ];
    }

    /**
     * User joins event lobby or active round.
     *
     * @return array<string, mixed>
     */
    public function joinEvent(SpeedNetworkingEvent $event, User $user): array
    {
        $this->ensureSpeedNetworkingSchema();

        return DB::transaction(function () use ($event, $user): array {
            $lockedEvent = SpeedNetworkingEvent::where('id', $event->id)->lockForUpdate()->first() ?? $event;

            // If event has already reached its scheduled end time or completed status, return concluded payload
            if ($lockedEvent->isCompleted()) {
                if ($lockedEvent->status !== SpeedNetworkingEvent::STATUS_COMPLETED) {
                    $lockedEvent->status = SpeedNetworkingEvent::STATUS_COMPLETED;
                    $lockedEvent->actual_end_time ??= Carbon::now();
                    $lockedEvent->save();
                }

                $summary = $this->getSessionSummary($lockedEvent, $user);

                return array_merge([
                    'event_id' => (string) $lockedEvent->id,
                    'title' => $lockedEvent->title,
                    'event_title' => $lockedEvent->title,
                    'round_duration_seconds' => (int) $lockedEvent->round_duration_seconds,
                    'break_duration_seconds' => (int) $lockedEvent->break_duration_seconds,
                    'remaining_seconds' => 0,
                    'preview_duration_seconds' => 15,
                    'current_round' => (int) $lockedEvent->current_round,
                    'total_rounds' => (int) $lockedEvent->total_rounds,
                    'channel_id' => '',
                    'agora_app_id' => $this->getAgoraAppId(),
                    'agora_token' => '',
                    'status' => 'event_ended',
                    'match_status' => 'event_ended',
                    'my_decision' => 'pending',
                    'peer_decision' => 'pending',
                    'both_accepted' => false,
                    'is_break_round' => false,
                    'is_waiting' => false,
                    'has_met_all_peers' => true,
                    'completed' => true,
                    'message' => 'Speed Collaboration has concluded.',
                    'peer' => null,
                ], $summary);
            }

            $existingParticipant = SpeedNetworkingParticipant::where('event_id', $lockedEvent->id)
                ->where('user_id', $user->id)
                ->first();

            $initialStatus = SpeedNetworkingParticipant::STATUS_SEARCHING;
            if ($existingParticipant && in_array($existingParticipant->status, [
                SpeedNetworkingParticipant::STATUS_PREVIEW,
                SpeedNetworkingParticipant::STATUS_ACCEPTED,
                SpeedNetworkingParticipant::STATUS_IN_CALL,
            ], true)) {
                $initialStatus = $existingParticipant->status;
            }

            $participant = SpeedNetworkingParticipant::updateOrCreate(
                [
                    'event_id' => $lockedEvent->id,
                    'user_id' => $user->id,
                ],
                [
                    'status' => $initialStatus,
                    'joined_at' => $existingParticipant?->joined_at ?? Carbon::now(),
                    'last_heartbeat_at' => Carbon::now(),
                ]
            );

            // Automatically activate event ONLY when scheduled_start_time has arrived (or is not set)
            if (in_array($lockedEvent->status, [SpeedNetworkingEvent::STATUS_DRAFT, SpeedNetworkingEvent::STATUS_SCHEDULED, SpeedNetworkingEvent::STATUS_LOBBY_OPEN], true)) {
                if (empty($lockedEvent->scheduled_start_time) || $lockedEvent->scheduled_start_time->lessThanOrEqualTo(Carbon::now())) {
                    $lockedEvent->status = SpeedNetworkingEvent::STATUS_IN_PROGRESS;
                    $lockedEvent->actual_start_time ??= Carbon::now();
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
                    'preview_duration_seconds' => 15,
                    'current_round' => 0,
                    'total_rounds' => (int) $lockedEvent->total_rounds,
                    'channel_id' => '',
                    'agora_app_id' => $this->getAgoraAppId(),
                    'agora_token' => '',
                    'match_status' => 'waiting',
                    'my_decision' => 'pending',
                    'peer_decision' => 'pending',
                    'both_accepted' => false,
                    'is_break_round' => false,
                    'is_waiting' => true,
                    'has_met_all_peers' => false,
                    'completed' => false,
                    'message' => 'Lobby is open. Waiting for the event to begin.',
                    'peer' => null,
                ];
            }

            // In progress: look for user's active 1-on-1 match (preview or in_call)
            $activeMatch = $this->findActiveMatchForUser($lockedEvent->id, (string) $user->id);

            if ($activeMatch) {
                $matchStatus = (string) ($activeMatch->match_status ?: SpeedNetworkingMatch::MATCH_STATUS_PREVIEW);

                if ($matchStatus === SpeedNetworkingMatch::MATCH_STATUS_PREVIEW) {
                    $isPreviewExpired = $activeMatch->preview_expires_at !== null
                        && Carbon::now()->greaterThan($activeMatch->preview_expires_at)
                        && ! ($activeMatch->user_a_decision === SpeedNetworkingMatch::DECISION_ACCEPTED && $activeMatch->user_b_decision === SpeedNetworkingMatch::DECISION_ACCEPTED);

                    if (! $isPreviewExpired) {
                        $isUserA = ((string) $activeMatch->user_a_id === (string) $user->id);
                        $myDec = $isUserA ? $activeMatch->user_a_decision : $activeMatch->user_b_decision;
                        $participant->update([
                            'status' => $myDec === SpeedNetworkingMatch::DECISION_ACCEPTED
                                ? SpeedNetworkingParticipant::STATUS_ACCEPTED
                                : SpeedNetworkingParticipant::STATUS_PREVIEW,
                        ]);

                        return $this->formatMatchResponse($lockedEvent, $activeMatch, (string) $user->id);
                    }

                    $this->expirePreviewMatch($activeMatch);
                    $refreshedMatch = $activeMatch->fresh(['userA', 'userB']) ?? $activeMatch;
                    if ($refreshedMatch->match_status === SpeedNetworkingMatch::MATCH_STATUS_IN_CALL) {
                        return $this->formatMatchResponse($event, $refreshedMatch, (string) $user->id);
                    }
                } else {
                    $callStart = $activeMatch->call_started_at ?? $activeMatch->started_at;
                    $elapsed = $callStart ? Carbon::now()->diffInSeconds($callStart) : 0;
                    if ($elapsed < $lockedEvent->round_duration_seconds) {
                        $participant->update(['status' => SpeedNetworkingParticipant::STATUS_IN_CALL]);

                        return $this->formatMatchResponse($lockedEvent, $activeMatch, (string) $user->id);
                    }

                    $activeMatch->update([
                        'status' => SpeedNetworkingMatch::STATUS_COMPLETED,
                        'match_status' => SpeedNetworkingMatch::MATCH_STATUS_COMPLETED,
                        'ended_at' => Carbon::now(),
                    ]);
                }
            }

            $participant->update(['status' => SpeedNetworkingParticipant::STATUS_SEARCHING]);

            return $this->findOrCreateMatchForUser($lockedEvent, $user);
        });
    }

    /**
     * Handle timed-out preview match.
     * Per Speed Collaboration specification: auto-connect when preview timer expires
     * unless at least one peer explicitly submitted 'skip' or 'block'.
     */
    protected function expirePreviewMatch(SpeedNetworkingMatch $match): void
    {
        $hasExplicitSkipOrBlock = in_array($match->user_a_decision, [SpeedNetworkingMatch::DECISION_SKIP, SpeedNetworkingMatch::DECISION_BLOCK], true)
            || in_array($match->user_b_decision, [SpeedNetworkingMatch::DECISION_SKIP, SpeedNetworkingMatch::DECISION_BLOCK], true)
            || in_array($match->match_status, [SpeedNetworkingMatch::MATCH_STATUS_SKIPPED, SpeedNetworkingMatch::MATCH_STATUS_BLOCKED], true);

        if ($hasExplicitSkipOrBlock) {
            $match->update([
                'status' => SpeedNetworkingMatch::STATUS_SKIPPED,
                'match_status' => SpeedNetworkingMatch::MATCH_STATUS_SKIPPED,
                'ended_at' => Carbon::now(),
            ]);

            if (! empty($match->user_a_id) && ! empty($match->user_b_id) && Schema::hasTable('speed_networking_event_skips')) {
                try {
                    SpeedNetworkingEventSkip::firstOrCreate([
                        'event_id' => (string) $match->event_id,
                        'user_id' => (string) $match->user_a_id,
                        'skipped_peer_id' => (string) $match->user_b_id,
                    ]);
                } catch (\Throwable) {
                }
            }

            if (Schema::hasTable('speed_networking_participants')) {
                SpeedNetworkingParticipant::where('event_id', $match->event_id)
                    ->whereIn('user_id', array_filter([(string) $match->user_a_id, (string) $match->user_b_id]))
                    ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
                    ->update([
                        'status' => SpeedNetworkingParticipant::STATUS_SEARCHING,
                        'last_heartbeat_at' => Carbon::now(),
                    ]);
            }

            return;
        }

        // Neither peer sent skip or block -> Auto-connect into live video call
        $now = Carbon::now();
        $match->update([
            'status' => SpeedNetworkingMatch::STATUS_ACTIVE,
            'match_status' => SpeedNetworkingMatch::MATCH_STATUS_IN_CALL,
            'user_a_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
            'user_b_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
            'call_started_at' => $match->call_started_at ?? $now,
            'started_at' => $match->started_at ?? $now,
        ]);

        if (Schema::hasTable('speed_networking_participants')) {
            SpeedNetworkingParticipant::where('event_id', $match->event_id)
                ->whereIn('user_id', array_filter([(string) $match->user_a_id, (string) $match->user_b_id]))
                ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
                ->update([
                    'status' => SpeedNetworkingParticipant::STATUS_IN_CALL,
                    'last_heartbeat_at' => $now,
                ]);
        }
    }

    /**
     * Check if a participant has met all other currently active attendees in actual video calls in this event.
     */
    public function hasMetAllActivePeers(SpeedNetworkingEvent $event, string $userId): bool
    {
        if (! $event->isCompleted()) {
            return false;
        }

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
            return false;
        }

        $metUserIds = SpeedNetworkingMatch::where('event_id', $event->id)
            ->where(function ($q) use ($userId): void {
                $q->where('user_a_id', $userId)
                    ->orWhere('user_b_id', $userId);
            })
            ->whereNotNull('user_b_id')
            ->whereIn('match_status', [
                SpeedNetworkingMatch::MATCH_STATUS_IN_CALL,
                SpeedNetworkingMatch::MATCH_STATUS_COMPLETED,
            ])
            ->get()
            ->map(fn (SpeedNetworkingMatch $m) => (string) ($m->user_a_id === $userId ? $m->user_b_id : $m->user_a_id))
            ->filter()
            ->unique()
            ->all();

        if (empty($metUserIds)) {
            return false;
        }

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
        $this->ensureSpeedNetworkingSchema();

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
                    'agora_app_id' => $this->getAgoraAppId(),
                    'agora_token' => '',
                    'status' => 'event_ended',
                    'match_status' => 'event_ended',
                    'is_break_round' => false,
                    'is_waiting' => false,
                    'completed' => true,
                    'has_met_all_peers' => true,
                    'message' => 'Speed Collaboration has concluded.',
                    'peer' => null,
                ], $summary);
            }

            $participant = SpeedNetworkingParticipant::where('event_id', $lockedEvent->id)
                ->where('user_id', $user->id)
                ->first();

            $activeMatch = $this->findActiveMatchForUser($lockedEvent->id, (string) $user->id);

            if ($activeMatch) {
                $matchStatus = (string) ($activeMatch->match_status ?: SpeedNetworkingMatch::MATCH_STATUS_PREVIEW);

                if ($matchStatus === SpeedNetworkingMatch::MATCH_STATUS_PREVIEW) {
                    $isPreviewExpired = $activeMatch->preview_expires_at !== null
                        && Carbon::now()->greaterThan($activeMatch->preview_expires_at)
                        && ! ($activeMatch->user_a_decision === SpeedNetworkingMatch::DECISION_ACCEPTED && $activeMatch->user_b_decision === SpeedNetworkingMatch::DECISION_ACCEPTED);

                    if (! $isPreviewExpired) {
                        $isUserA = ((string) $activeMatch->user_a_id === (string) $user->id);
                        $myDec = (string) ($isUserA ? $activeMatch->user_a_decision : $activeMatch->user_b_decision);

                        // If this is a fresh preview match where the user hasn't skipped/blocked, return it!
                        if ($isGetRequest || $myDec === SpeedNetworkingMatch::DECISION_PENDING || $myDec === SpeedNetworkingMatch::DECISION_ACCEPTED) {
                            return $this->formatMatchResponse($lockedEvent, $activeMatch, (string) $user->id);
                        }
                    } else {
                        // Countdown expired: Auto-connect per Speed Collaboration spec
                        $this->expirePreviewMatch($activeMatch);
                        $refreshedMatch = $activeMatch->fresh(['userA', 'userB']) ?? $activeMatch;
                        if ($refreshedMatch->match_status === SpeedNetworkingMatch::MATCH_STATUS_IN_CALL) {
                            return $this->formatMatchResponse($lockedEvent, $refreshedMatch, (string) $user->id);
                        }
                    }
                } else {
                    $callStart = $activeMatch->call_started_at ?? $activeMatch->started_at;
                    $elapsed = $callStart ? Carbon::now()->diffInSeconds($callStart) : 0;
                    $isExpired = $elapsed >= $lockedEvent->round_duration_seconds;

                    // If GET request and match is still within round duration, return active match
                    if ($isGetRequest && ! $isExpired) {
                        if ($participant && $participant->status !== SpeedNetworkingParticipant::STATUS_IN_CALL) {
                            $participant->update(['status' => SpeedNetworkingParticipant::STATUS_IN_CALL]);
                        }

                        return $this->formatMatchResponse($lockedEvent, $activeMatch, (string) $user->id);
                    }

                    // User is completing/leaving the active call
                    $endedAt = $activeMatch->ended_at ?? Carbon::now();
                    $durationSeconds = $activeMatch->duration_seconds !== null && (int) $activeMatch->duration_seconds > 0
                        ? (int) $activeMatch->duration_seconds
                        : ($callStart
                            ? min(
                                max(0, (int) $callStart->diffInSeconds($endedAt, false)),
                                max(1, (int) $lockedEvent->round_duration_seconds)
                            )
                            : 0);

                    $activeMatch->update([
                        'status' => SpeedNetworkingMatch::STATUS_COMPLETED,
                        'match_status' => SpeedNetworkingMatch::MATCH_STATUS_COMPLETED,
                        'ended_at' => $endedAt,
                        'duration_seconds' => $durationSeconds,
                    ]);
                }
            }

            if ($participant) {
                $participant->update(['status' => SpeedNetworkingParticipant::STATUS_SEARCHING]);
            }

            return $this->findOrCreateMatchForUser($lockedEvent, $user);
        });
    }

    /**
     * Handle Match Preview Action: 'connect', 'skip', or 'block'.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handleMatchAction(SpeedNetworkingEvent $event, User $user, array $payload): array
    {
        $this->ensureSpeedNetworkingSchema();

        $userId = (string) $user->id;
        $rawAction = strtolower(trim((string) ($payload['action'] ?? 'connect')));
        $action = in_array($rawAction, ['accept', 'accepted', 'connect'], true) ? 'connect' : $rawAction;
        $peerId = trim((string) ($payload['peer_id'] ?? ''));
        $matchId = trim((string) ($payload['match_id'] ?? ''));
        $channelId = trim((string) ($payload['channel_id'] ?? ''));
        $reason = trim((string) ($payload['reason'] ?? '')) ?: 'Blocked during Speed Networking';

        return DB::transaction(function () use ($event, $userId, $action, $peerId, $matchId, $channelId, $reason): array {
            $matchQuery = SpeedNetworkingMatch::with(['userA', 'userB'])
                ->where('event_id', $event->id)
                ->where(function ($q) use ($userId): void {
                    $q->where('user_a_id', $userId)
                        ->orWhere('user_b_id', $userId);
                });

            if ($matchId !== '') {
                $match = (clone $matchQuery)->where('id', $matchId)->lockForUpdate()->first();
            } elseif ($channelId !== '') {
                $match = (clone $matchQuery)->where('channel_id', $channelId)->lockForUpdate()->first();
            } else {
                $match = null;
            }

            if (! $match) {
                $match = (clone $matchQuery)
                    ->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)
                    ->latest('created_at')
                    ->lockForUpdate()
                    ->first();
            }

            $resolvedPeerId = $peerId;
            if ($resolvedPeerId === '' && $match) {
                $resolvedPeerId = (string) ((string) $match->user_a_id === $userId ? $match->user_b_id : $match->user_a_id);
            }

            if ($action === 'block' && $resolvedPeerId !== '' && $resolvedPeerId !== $userId) {
                SpeedNetworkingBlockedPeer::updateOrCreate(
                    ['blocker_id' => $userId, 'blocked_id' => $resolvedPeerId],
                    ['event_id' => (string) $event->id, 'reason' => $reason]
                );

                if (Schema::hasTable('speed_networking_event_skips')) {
                    SpeedNetworkingEventSkip::firstOrCreate([
                        'event_id' => (string) $event->id,
                        'user_id' => $userId,
                        'skipped_peer_id' => $resolvedPeerId,
                    ]);
                }
            }

            if ($action === 'skip' && $resolvedPeerId !== '' && $resolvedPeerId !== $userId && Schema::hasTable('speed_networking_event_skips')) {
                SpeedNetworkingEventSkip::firstOrCreate([
                    'event_id' => (string) $event->id,
                    'user_id' => $userId,
                    'skipped_peer_id' => $resolvedPeerId,
                ]);
            }

            if (! $match) {
                SpeedNetworkingParticipant::where('event_id', $event->id)
                    ->where('user_id', $userId)
                    ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
                    ->update([
                        'status' => SpeedNetworkingParticipant::STATUS_SEARCHING,
                        'last_heartbeat_at' => Carbon::now(),
                    ]);

                return [
                    'event_id' => (string) $event->id,
                    'match_id' => $matchId !== '' ? $matchId : null,
                    'channel_id' => $channelId,
                    'match_status' => $action === 'block' ? 'blocked' : ($action === 'skip' ? 'skipped' : 'waiting'),
                    'my_decision' => $action === 'connect' ? 'accepted' : $action,
                    'peer_decision' => 'pending',
                    'both_accepted' => false,
                    'is_waiting' => true,
                ];
            }

            $isUserA = ((string) $match->user_a_id === $userId);
            $decisionCol = $isUserA ? 'user_a_decision' : 'user_b_decision';
            $partnerCol = $isUserA ? 'user_b_decision' : 'user_a_decision';
            $partnerId = (string) ($isUserA ? $match->user_b_id : $match->user_a_id);

            if ($action === 'skip' || $action === 'block') {
                $match->$decisionCol = $action;
                $match->match_status = ($action === 'block')
                    ? SpeedNetworkingMatch::MATCH_STATUS_BLOCKED
                    : SpeedNetworkingMatch::MATCH_STATUS_SKIPPED;
                $match->status = SpeedNetworkingMatch::STATUS_SKIPPED;
                $match->ended_at = Carbon::now();
                $match->save();

                if ($action === 'block' && $partnerId !== '') {
                    $this->blockPeerInSpeedNetworking($userId, $partnerId, (string) $event->id, $reason);
                }

                if ($action === 'skip' && $partnerId !== '' && Schema::hasTable('speed_networking_event_skips')) {
                    SpeedNetworkingEventSkip::updateOrCreate(
                        [
                            'event_id' => (string) $event->id,
                            'user_id' => $userId,
                            'skipped_peer_id' => $partnerId,
                        ],
                        [
                            'updated_at' => Carbon::now(),
                        ]
                    );
                }

                // Free BOTH peers back to waiting pool immediately
                SpeedNetworkingParticipant::where('event_id', $event->id)
                    ->whereIn('user_id', array_filter([$userId, $partnerId]))
                    ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
                    ->update([
                        'status' => SpeedNetworkingParticipant::STATUS_SEARCHING,
                        'last_heartbeat_at' => Carbon::now(),
                    ]);

                return [
                    'event_id' => (string) $event->id,
                    'match_id' => (string) $match->id,
                    'channel_id' => (string) ($match->channel_id ?? ''),
                    'match_status' => $match->match_status,
                    'my_decision' => $action,
                    'peer_decision' => (string) ($match->$partnerCol ?: 'pending'),
                    'both_accepted' => false,
                    'is_waiting' => true,
                ];
            }

            if ($action === 'connect') {
                // Check if this match was skipped/expired, but an active replacement match was already created for user
                if (in_array($match->match_status, [SpeedNetworkingMatch::MATCH_STATUS_SKIPPED, SpeedNetworkingMatch::MATCH_STATUS_EXPIRED], true)) {
                    $activeReplacement = (clone $matchQuery)
                        ->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)
                        ->whereIn('match_status', [
                            SpeedNetworkingMatch::MATCH_STATUS_PREVIEW,
                            SpeedNetworkingMatch::MATCH_STATUS_IN_CALL,
                        ])
                        ->latest('created_at')
                        ->lockForUpdate()
                        ->first();

                    if ($activeReplacement) {
                        $match = $activeReplacement;
                        $isUserA = ((string) $match->user_a_id === $userId);
                        $decisionCol = $isUserA ? 'user_a_decision' : 'user_b_decision';
                        $partnerCol = $isUserA ? 'user_b_decision' : 'user_a_decision';
                        $partnerId = (string) ($isUserA ? $match->user_b_id : $match->user_a_id);
                    }
                }

                // If partner already skipped/blocked or match expired, tell user immediately
                if (
                    in_array($match->match_status, [
                        SpeedNetworkingMatch::MATCH_STATUS_SKIPPED,
                        SpeedNetworkingMatch::MATCH_STATUS_BLOCKED,
                        SpeedNetworkingMatch::MATCH_STATUS_EXPIRED,
                    ], true)
                    || $match->status === SpeedNetworkingMatch::STATUS_SKIPPED
                    || in_array($match->$partnerCol, [SpeedNetworkingMatch::DECISION_SKIP, SpeedNetworkingMatch::DECISION_BLOCK], true)
                ) {
                    SpeedNetworkingParticipant::where('event_id', $event->id)
                        ->where('user_id', $userId)
                        ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
                        ->update([
                            'status' => SpeedNetworkingParticipant::STATUS_SEARCHING,
                            'last_heartbeat_at' => Carbon::now(),
                        ]);

                    return [
                        'event_id' => (string) $event->id,
                        'match_id' => (string) $match->id,
                        'channel_id' => (string) ($match->channel_id ?? ''),
                        'match_status' => SpeedNetworkingMatch::MATCH_STATUS_SKIPPED,
                        'my_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
                        'peer_decision' => in_array($match->$partnerCol, ['skip', 'block'], true) ? $match->$partnerCol : 'skip',
                        'both_accepted' => false,
                        'is_waiting' => true,
                    ];
                }

                $match->$decisionCol = SpeedNetworkingMatch::DECISION_ACCEPTED;

                if ($match->$partnerCol === SpeedNetworkingMatch::DECISION_ACCEPTED || $match->match_status === SpeedNetworkingMatch::MATCH_STATUS_IN_CALL) {
                    // BOTH PEERS ACCEPTED -> START LIVE VIDEO CALL
                    $completedCallsCount = SpeedNetworkingMatch::where('event_id', $event->id)
                        ->where('id', '!=', $match->id)
                        ->whereIn('match_status', [
                            SpeedNetworkingMatch::MATCH_STATUS_IN_CALL,
                            SpeedNetworkingMatch::MATCH_STATUS_COMPLETED,
                        ])
                        ->where(function ($q) use ($userId): void {
                            $q->where('user_a_id', $userId)
                                ->orWhere('user_b_id', $userId);
                        })
                        ->count();

                    $roundNumber = max(1, $completedCallsCount + 1);
                    $now = Carbon::now();

                    if ($match->match_status !== SpeedNetworkingMatch::MATCH_STATUS_IN_CALL) {
                        $match->match_status = SpeedNetworkingMatch::MATCH_STATUS_IN_CALL;
                        $match->status = SpeedNetworkingMatch::STATUS_ACTIVE;
                        $match->round_number = $roundNumber;
                        $match->call_started_at = $match->call_started_at ?? $now;
                        $match->started_at = $match->started_at ?? $now;
                        $match->save();

                        if ($roundNumber > $event->current_round) {
                            $event->current_round = $roundNumber;
                            $event->save();
                        }
                    } else {
                        $match->save();
                    }

                    SpeedNetworkingParticipant::where('event_id', $event->id)
                        ->whereIn('user_id', array_filter([$userId, $partnerId]))
                        ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
                        ->update([
                            'status' => SpeedNetworkingParticipant::STATUS_IN_CALL,
                            'last_heartbeat_at' => $now,
                        ]);
                } else {
                    // Extend preview expiry slightly so it doesn't time out while waiting for partner's tap
                    $match->preview_expires_at = Carbon::now()->addSeconds(15);
                    $match->save();

                    SpeedNetworkingParticipant::where('event_id', $event->id)
                        ->where('user_id', $userId)
                        ->update([
                            'status' => SpeedNetworkingParticipant::STATUS_ACCEPTED,
                            'last_heartbeat_at' => Carbon::now(),
                        ]);
                }

                return $this->formatMatchResponse($event, $match->fresh(['userA', 'userB']) ?? $match, $userId);
            }

            return $this->formatMatchResponse($event, $match, $userId);
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

        $activeMatches = SpeedNetworkingMatch::where('event_id', $event->id)
            ->where(function ($q) use ($user): void {
                $q->where('user_a_id', $user->id)
                    ->orWhere('user_b_id', $user->id);
            })
            ->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)
            ->get();

        foreach ($activeMatches as $match) {
            $partnerId = (string) ((string) $match->user_a_id === (string) $user->id ? $match->user_b_id : $match->user_a_id);

            if ($match->match_status === SpeedNetworkingMatch::MATCH_STATUS_IN_CALL) {
                $endedAt = $match->ended_at ?? Carbon::now();
                $callStart = $match->call_started_at ?? $match->started_at;
                $durationSeconds = $match->duration_seconds !== null && (int) $match->duration_seconds > 0
                    ? (int) $match->duration_seconds
                    : ($callStart
                        ? min(
                            max(0, (int) $callStart->diffInSeconds($endedAt, false)),
                            max(1, (int) $event->round_duration_seconds)
                        )
                        : 0);

                $match->update([
                    'status' => SpeedNetworkingMatch::STATUS_COMPLETED,
                    'match_status' => SpeedNetworkingMatch::MATCH_STATUS_COMPLETED,
                    'ended_at' => $endedAt,
                    'duration_seconds' => $durationSeconds,
                ]);
            } else {
                $match->update([
                    'status' => SpeedNetworkingMatch::STATUS_SKIPPED,
                    'match_status' => SpeedNetworkingMatch::MATCH_STATUS_SKIPPED,
                    'ended_at' => Carbon::now(),
                ]);
            }

            if ($partnerId !== '') {
                SpeedNetworkingParticipant::where('event_id', $event->id)
                    ->where('user_id', $partnerId)
                    ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
                    ->update([
                        'status' => SpeedNetworkingParticipant::STATUS_SEARCHING,
                    ]);
            }
        }
    }

    /**
     * Update participant heartbeat and return active attendee count.
     */
    public function heartbeat(SpeedNetworkingEvent $event, User $user, ?string $userStatus = null, int $breakRemainingSeconds = 0): int
    {
        $updateFields = [
            'last_heartbeat_at' => Carbon::now(),
        ];

        if ($userStatus === 'on_break' || $userStatus === 'break') {
            $updateFields['status'] = SpeedNetworkingParticipant::STATUS_BREAK;
            $updateFields['break_remaining_seconds'] = max(0, $breakRemainingSeconds);
            $updateFields['break_started_at'] = Carbon::now();
        } elseif ($userStatus === 'preview') {
            $updateFields['status'] = SpeedNetworkingParticipant::STATUS_PREVIEW;
            $updateFields['break_remaining_seconds'] = 0;
        } elseif ($userStatus === 'accepted') {
            $updateFields['status'] = SpeedNetworkingParticipant::STATUS_ACCEPTED;
            $updateFields['break_remaining_seconds'] = 0;
        } elseif ($userStatus === 'in_call') {
            $updateFields['status'] = SpeedNetworkingParticipant::STATUS_IN_CALL;
            $updateFields['break_remaining_seconds'] = 0;
        } elseif ($userStatus === 'waiting' || $userStatus === 'searching') {
            $hasActiveMatch = $this->findActiveMatchForUser($event->id, (string) $user->id) !== null;
            if (! $hasActiveMatch) {
                $updateFields['status'] = SpeedNetworkingParticipant::STATUS_SEARCHING;
                $updateFields['break_remaining_seconds'] = 0;
            }
        }

        SpeedNetworkingParticipant::updateOrCreate(
            [
                'event_id' => $event->id,
                'user_id' => $user->id,
            ],
            $updateFields
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
     * Find an incoming match request for a participant currently on break.
     *
     * @return array<string, mixed>|null
     */
    public function findIncomingMatchForBreakUser(SpeedNetworkingEvent $event, User $user): ?array
    {
        $userId = (string) $user->id;

        // Check already interacted peer IDs in this event + skipped + blocked
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

        $excludedIds = array_values(array_unique(array_merge(
            $metUserIds,
            [$userId],
            $this->getBidirectionallyBlockedPeerIds($userId),
            $this->getEventSkippedPeerIds((string) $event->id, $userId)
        )));

        // Find an active searching/lobby participant who has NOT met/skipped/blocked this user yet
        $searchingPeer = SpeedNetworkingParticipant::with('user')
            ->where('event_id', $event->id)
            ->whereNotIn('user_id', $excludedIds)
            ->whereIn('status', [
                SpeedNetworkingParticipant::STATUS_LOBBY,
                SpeedNetworkingParticipant::STATUS_SEARCHING,
                SpeedNetworkingParticipant::STATUS_WAITING,
            ])
            ->where('last_heartbeat_at', '>=', Carbon::now()->subSeconds(45))
            ->first();

        if (! $searchingPeer || ! $searchingPeer->user) {
            return null;
        }

        $peerUser = $searchingPeer->user;
        $channelId = 'sn_invite_'.substr(md5($event->id.'_'.$userId.'_'.$peerUser->id), 0, 16);
        $agoraToken = '';
        try {
            $agoraToken = $this->agoraTokenService->generateToken($channelId, $userId);
        } catch (\Throwable) {
            $agoraToken = 'demo_rtc_token_'.md5($channelId.$userId);
        }

        return [
            'channel_id' => $channelId,
            'agora_app_id' => $this->getAgoraAppId(),
            'agora_token' => $agoraToken,
            'peer' => $this->buildPeerCardPayload($peerUser, $userId, $event, 1),
        ];
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

        if (Schema::hasTable('event_registrations')) {
            try {
                $regQuery = EventRegistration::where('event_id', $event->id)->with('user');
                if (($event->ticket_price ?? 0) > 0) {
                    $regQuery->where(function ($q) {
                        $q->where('status', 'registered')->orWhere('payment_status', 'paid');
                    });
                } else {
                    $regQuery->where('status', '!=', 'cancelled');
                }
                $regList = $regQuery->get();
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
            } catch (\Throwable) {
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

        $durSec = max(60, (int) ($event->duration_seconds ?: $event->calculateDurationSeconds()));
        $durMin = (int) ceil($durSec / 60);
        $schedStart = $event->scheduled_start_time ?? $event->actual_start_time ?? Carbon::now();
        $schedEnd = $event->scheduled_end_time ?? $schedStart->copy()->addSeconds($durSec);

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
                'duration_seconds' => $durSec,
                'duration_minutes' => $durMin,
                'duration' => $durMin,
                'scheduled_start_time' => $schedStart->copy()->utc()->toISOString(),
                'scheduled_end_time' => $schedEnd->copy()->utc()->toISOString(),
                'end_time' => $schedEnd->copy()->utc()->toISOString(),
                'actual_start_time' => $event->actual_start_time?->copy()->utc()->toISOString(),
                'actual_end_time' => $event->actual_end_time?->copy()->utc()->toISOString(),
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
     * Generate pairings for a specific round ensuring strict non-repeating matches, block/skip exclusions, and preview handshake.
     */
    public function generateMatchesForRound(SpeedNetworkingEvent $event, int $roundNumber): void
    {
        $this->ensureSpeedNetworkingSchema();

        $cutoff = Carbon::now()->subSeconds(90);

        // Fetch active participants who are not left or on break and have recent activity
        $participants = SpeedNetworkingParticipant::where('event_id', $event->id)
            ->whereNotIn('status', [
                SpeedNetworkingParticipant::STATUS_LEFT,
                SpeedNetworkingParticipant::STATUS_BREAK,
            ])
            ->where(function ($q) use ($cutoff): void {
                $q->where('last_heartbeat_at', '>=', $cutoff)
                    ->orWhere('joined_at', '>=', $cutoff);
            })
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        // Exclude users already in an active 1-on-1 match (preview or in_call)
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

        // Build history of already-met or skipped pairs in this event
        $previousMatches = SpeedNetworkingMatch::where('event_id', $event->id)
            ->whereNotNull('user_b_id')
            ->get();

        $excludedPairs = [];
        foreach ($previousMatches as $pm) {
            $excludedPairs[(string) $pm->user_a_id][(string) $pm->user_b_id] = true;
            $excludedPairs[(string) $pm->user_b_id][(string) $pm->user_a_id] = true;
        }

        if (Schema::hasTable('speed_networking_event_skips')) {
            $eventSkips = SpeedNetworkingEventSkip::where('event_id', $event->id)->get();
            foreach ($eventSkips as $skip) {
                $excludedPairs[(string) $skip->user_id][(string) $skip->skipped_peer_id] = true;
                $excludedPairs[(string) $skip->skipped_peer_id][(string) $skip->user_id] = true;
            }
        }

        if (Schema::hasTable('speed_networking_blocked_peers')) {
            $blocks = SpeedNetworkingBlockedPeer::whereIn('blocker_id', $available)
                ->orWhereIn('blocked_id', $available)
                ->get();
            foreach ($blocks as $blk) {
                $excludedPairs[(string) $blk->blocker_id][(string) $blk->blocked_id] = true;
                $excludedPairs[(string) $blk->blocked_id][(string) $blk->blocker_id] = true;
            }
        }

        shuffle($available);

        $paired = [];
        $maxTable = (int) (SpeedNetworkingMatch::where('event_id', $event->id)->max('table_number') ?? 0);
        $tableIndex = $maxTable + 1;
        $previewSeconds = 15;

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

                if (empty($excludedPairs[$u1][$u2])) {
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
                    'match_status' => SpeedNetworkingMatch::MATCH_STATUS_PREVIEW,
                    'user_a_decision' => SpeedNetworkingMatch::DECISION_PENDING,
                    'user_b_decision' => SpeedNetworkingMatch::DECISION_PENDING,
                    'preview_duration_seconds' => $previewSeconds,
                    'preview_expires_at' => Carbon::now()->addSeconds($previewSeconds + 3),
                    'call_started_at' => null,
                    'started_at' => null,
                ]);

                SpeedNetworkingParticipant::where('event_id', $event->id)
                    ->whereIn('user_id', [$u1, $partner])
                    ->update(['status' => SpeedNetworkingParticipant::STATUS_PREVIEW]);

                $tableIndex++;
            }
        }
    }

    /**
     * Find active 1-on-1 match (preview or in_call) for a user.
     */
    public function findActiveMatchForUser(string $eventId, string $userId): ?SpeedNetworkingMatch
    {
        return SpeedNetworkingMatch::with(['userA', 'userB'])
            ->where('event_id', $eventId)
            ->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)
            ->whereIn('match_status', [
                SpeedNetworkingMatch::MATCH_STATUS_PREVIEW,
                SpeedNetworkingMatch::MATCH_STATUS_IN_CALL,
            ])
            ->whereNotNull('user_b_id')
            ->where(function ($q) use ($userId): void {
                $q->where('user_a_id', $userId)
                    ->orWhere('user_b_id', $userId);
            })
            ->latest('created_at')
            ->first();
    }

    /**
     * Dynamically match user with next available peer in mutual preview mode without repeating pairings,
     * excluding skipped peers in this event and bidirectionally blocked peers across all events.
     *
     * @return array<string, mixed>
     */
    public function findOrCreateMatchForUser(SpeedNetworkingEvent $event, User $user): array
    {
        $this->ensureSpeedNetworkingSchema();
        $userId = (string) $user->id;

        return DB::transaction(function () use ($event, $user, $userId): array {
            SpeedNetworkingParticipant::where('event_id', $event->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            // 1. Re-check if an active match (preview or in_call) was already assigned to this user
            $existingMatch = $this->findActiveMatchForUser($event->id, $userId);
            if ($existingMatch) {
                $matchStatus = (string) ($existingMatch->match_status ?: SpeedNetworkingMatch::MATCH_STATUS_PREVIEW);
                if ($matchStatus === SpeedNetworkingMatch::MATCH_STATUS_PREVIEW) {
                    $isPreviewExpired = $existingMatch->preview_expires_at !== null
                        && Carbon::now()->greaterThan($existingMatch->preview_expires_at)
                        && ! ($existingMatch->user_a_decision === SpeedNetworkingMatch::DECISION_ACCEPTED && $existingMatch->user_b_decision === SpeedNetworkingMatch::DECISION_ACCEPTED);

                    if (! $isPreviewExpired) {
                        $isUserA = ((string) $existingMatch->user_a_id === $userId);
                        $myDec = (string) ($isUserA ? $existingMatch->user_a_decision : $existingMatch->user_b_decision);
                        SpeedNetworkingParticipant::where('event_id', $event->id)
                            ->where('user_id', $userId)
                            ->update([
                                'status' => $myDec === SpeedNetworkingMatch::DECISION_ACCEPTED
                                    ? SpeedNetworkingParticipant::STATUS_ACCEPTED
                                    : SpeedNetworkingParticipant::STATUS_PREVIEW,
                            ]);

                        return $this->formatMatchResponse($event, $existingMatch, $userId);
                    }

                    // Auto-connect on preview expiration
                    $this->expirePreviewMatch($existingMatch);
                    $refreshed = $existingMatch->fresh(['userA', 'userB']) ?? $existingMatch;
                    if ($refreshed->match_status === SpeedNetworkingMatch::MATCH_STATUS_IN_CALL) {
                        return $this->formatMatchResponse($event, $refreshed, $userId);
                    }
                } else {
                    $callStart = $existingMatch->call_started_at ?? $existingMatch->started_at;
                    $elapsed = $callStart ? Carbon::now()->diffInSeconds($callStart) : 0;
                    if ($elapsed < $event->round_duration_seconds) {
                        SpeedNetworkingParticipant::where('event_id', $event->id)
                            ->where('user_id', $userId)
                            ->update(['status' => SpeedNetworkingParticipant::STATUS_IN_CALL]);

                        return $this->formatMatchResponse($event, $existingMatch, $userId);
                    }

                    $endedAt = $existingMatch->ended_at ?? Carbon::now();
                    $durationSeconds = $existingMatch->duration_seconds !== null && (int) $existingMatch->duration_seconds > 0
                        ? (int) $existingMatch->duration_seconds
                        : ($callStart
                            ? min(
                                max(0, (int) $callStart->diffInSeconds($endedAt, false)),
                                max(1, (int) $event->round_duration_seconds)
                            )
                            : 0);

                    $existingMatch->update([
                        'status' => SpeedNetworkingMatch::STATUS_COMPLETED,
                        'match_status' => SpeedNetworkingMatch::MATCH_STATUS_COMPLETED,
                        'ended_at' => $endedAt,
                        'duration_seconds' => $durationSeconds,
                    ]);
                }
            }

            // 2. Count actual completed video call rounds for this user in this event
            $userCompletedCount = SpeedNetworkingMatch::where('event_id', $event->id)
                ->where(function ($q) use ($userId): void {
                    $q->where('user_a_id', $userId)
                        ->orWhere('user_b_id', $userId);
                })
                ->whereNotNull('user_b_id')
                ->whereIn('match_status', [
                    SpeedNetworkingMatch::MATCH_STATUS_IN_CALL,
                    SpeedNetworkingMatch::MATCH_STATUS_COMPLETED,
                ])
                ->count();

            $hasReachedTotalRounds = ($event->total_rounds > 0 && $userCompletedCount >= $event->total_rounds);

            if ($hasReachedTotalRounds && $event->isCompleted()) {
                SpeedNetworkingParticipant::where('event_id', $event->id)
                    ->where('user_id', $userId)
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
                    'preview_duration_seconds' => 15,
                    'channel_id' => '',
                    'agora_app_id' => $this->getAgoraAppId(),
                    'agora_token' => '',
                    'status' => 'event_ended',
                    'match_status' => 'event_ended',
                    'my_decision' => 'pending',
                    'peer_decision' => 'pending',
                    'both_accepted' => false,
                    'is_break_round' => false,
                    'is_waiting' => false,
                    'completed' => true,
                    'has_met_all_peers' => true,
                    'message' => 'Speed Collaboration has concluded.',
                    'peer' => null,
                ], $summary);
            }

            // 3. Clean up any expired preview matches in this event so stalled peers are freed
            $expiredPreviews = SpeedNetworkingMatch::where('event_id', $event->id)
                ->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)
                ->where('match_status', SpeedNetworkingMatch::MATCH_STATUS_PREVIEW)
                ->whereNotNull('preview_expires_at')
                ->where('preview_expires_at', '<', Carbon::now())
                ->get();

            foreach ($expiredPreviews as $expMatch) {
                if (! ($expMatch->user_a_decision === SpeedNetworkingMatch::DECISION_ACCEPTED && $expMatch->user_b_decision === SpeedNetworkingMatch::DECISION_ACCEPTED)) {
                    $this->expirePreviewMatch($expMatch);
                }
            }

            // 4. Build exclusion lists:
            // - Peers already met in actual calls (in_call or completed)
            // - Peers skipped in THIS event
            // - Peers bidirectionally blocked in Speed Networking (ALL events)
            // - Users currently busy in an active preview or call
            $metCallPeerIds = SpeedNetworkingMatch::where('event_id', $event->id)
                ->where(function ($q) use ($userId): void {
                    $q->where('user_a_id', $userId)
                        ->orWhere('user_b_id', $userId);
                })
                ->whereNotNull('user_b_id')
                ->whereIn('match_status', [
                    SpeedNetworkingMatch::MATCH_STATUS_IN_CALL,
                    SpeedNetworkingMatch::MATCH_STATUS_COMPLETED,
                ])
                ->get()
                ->map(fn (SpeedNetworkingMatch $m): string => (string) ((string) $m->user_a_id === $userId ? $m->user_b_id : $m->user_a_id))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $skippedPeerIds = $this->getEventSkippedPeerIds((string) $event->id, $userId);
            $blockedPeerIds = $this->getBidirectionallyBlockedPeerIds($userId);

            $busyUserIds = SpeedNetworkingMatch::where('event_id', $event->id)
                ->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)
                ->whereIn('match_status', [
                    SpeedNetworkingMatch::MATCH_STATUS_PREVIEW,
                    SpeedNetworkingMatch::MATCH_STATUS_IN_CALL,
                ])
                ->whereNotNull('user_b_id')
                ->get()
                ->flatMap(fn (SpeedNetworkingMatch $m): array => [(string) $m->user_a_id, (string) $m->user_b_id])
                ->filter()
                ->unique()
                ->values()
                ->all();

            $excludedUserIds = array_values(array_unique(array_merge(
                [$userId],
                $metCallPeerIds,
                $skippedPeerIds,
                $blockedPeerIds,
                $busyUserIds
            )));

            // 5. Find an available waiting candidate in the event
            $cutoff = Carbon::now()->subSeconds(30);
            $candidate = SpeedNetworkingParticipant::where('event_id', $event->id)
                ->whereNotIn('user_id', $excludedUserIds)
                ->whereIn('status', [
                    SpeedNetworkingParticipant::STATUS_SEARCHING,
                    SpeedNetworkingParticipant::STATUS_WAITING,
                    SpeedNetworkingParticipant::STATUS_LOBBY,
                    SpeedNetworkingParticipant::STATUS_REGISTERED,
                ])
                ->where(function ($q) use ($cutoff): void {
                    $q->where('last_heartbeat_at', '>=', $cutoff)
                        ->orWhere('joined_at', '>=', $cutoff);
                })
                ->orderBy('last_heartbeat_at', 'desc')
                ->lockForUpdate()
                ->first();

            // 5.B If no unskipped candidate is available, cycle previously skipped peers in round-robin / FIFO order
            if (! $candidate && ! empty($skippedPeerIds)) {
                $hardExcludedIds = array_values(array_unique(array_merge(
                    [$userId],
                    $metCallPeerIds,
                    $blockedPeerIds,
                    $busyUserIds
                )));

                $waitingSkippedCandidates = SpeedNetworkingParticipant::where('event_id', $event->id)
                    ->whereNotIn('user_id', $hardExcludedIds)
                    ->whereIn('user_id', $skippedPeerIds)
                    ->whereIn('status', [
                        SpeedNetworkingParticipant::STATUS_SEARCHING,
                        SpeedNetworkingParticipant::STATUS_WAITING,
                        SpeedNetworkingParticipant::STATUS_LOBBY,
                        SpeedNetworkingParticipant::STATUS_REGISTERED,
                    ])
                    ->where(function ($q) use ($cutoff): void {
                        $q->where('last_heartbeat_at', '>=', $cutoff)
                            ->orWhere('joined_at', '>=', $cutoff);
                    })
                    ->get();

                if ($waitingSkippedCandidates->isNotEmpty()) {
                    $candidateUserIds = $waitingSkippedCandidates->pluck('user_id')->map(fn ($id): string => (string) $id)->all();
                    $skipTimes = [];

                    if (Schema::hasTable('speed_networking_event_skips')) {
                        $skipRows = SpeedNetworkingEventSkip::where('event_id', $event->id)
                            ->where(function ($q) use ($userId, $candidateUserIds): void {
                                $q->where('user_id', $userId)->whereIn('skipped_peer_id', $candidateUserIds);
                            })
                            ->orWhere(function ($q) use ($userId, $candidateUserIds): void {
                                $q->whereIn('user_id', $candidateUserIds)->where('skipped_peer_id', $userId);
                            })
                            ->get();

                        foreach ($skipRows as $sr) {
                            $otherId = (string) ($sr->user_id === $userId ? $sr->skipped_peer_id : $sr->user_id);
                            $skipTimes[$otherId] = $sr->updated_at ?: $sr->created_at;
                        }
                    }

                    $skippedMatches = SpeedNetworkingMatch::where('event_id', $event->id)
                        ->where(function ($q) use ($userId): void {
                            $q->where('user_a_id', $userId)->orWhere('user_b_id', $userId);
                        })
                        ->whereIn('match_status', [
                            SpeedNetworkingMatch::MATCH_STATUS_SKIPPED,
                            SpeedNetworkingMatch::MATCH_STATUS_EXPIRED,
                        ])
                        ->get();

                    foreach ($skippedMatches as $sm) {
                        $otherId = (string) ($sm->user_a_id === $userId ? $sm->user_b_id : $sm->user_a_id);
                        $t = $sm->ended_at ?: $sm->updated_at;
                        if (! isset($skipTimes[$otherId]) || ($t && $t->greaterThan($skipTimes[$otherId]))) {
                            $skipTimes[$otherId] = $t;
                        }
                    }

                    // Avoid re-matching a peer skipped less than 3 seconds ago to provide smooth UI transition
                    $cooldownCutoff = Carbon::now()->subSeconds(3);
                    $eligible = $waitingSkippedCandidates->filter(function ($c) use ($skipTimes, $cooldownCutoff): bool {
                        $uid = (string) $c->user_id;
                        $time = $skipTimes[$uid] ?? null;
                        if ($time instanceof Carbon && $time->greaterThan($cooldownCutoff)) {
                            return false;
                        }

                        return true;
                    });

                    // Order by FIFO: oldest skip first
                    $selected = $eligible->sortBy(function ($c) use ($skipTimes): int {
                        $uid = (string) $c->user_id;
                        $time = $skipTimes[$uid] ?? null;

                        return $time instanceof Carbon ? $time->timestamp : 0;
                    })->first();

                    if ($selected) {
                        $candidate = SpeedNetworkingParticipant::where('id', $selected->id)->lockForUpdate()->first();
                    }
                }
            }

            if ($candidate) {
                $partnerId = (string) $candidate->user_id;
                $roundNumber = $userCompletedCount + 1;
                $previewSeconds = 15;

                $maxTable = (int) (SpeedNetworkingMatch::where('event_id', $event->id)->max('table_number') ?? 0);
                $tableNumber = $maxTable + 1;
                $channelId = "sn_{$event->id}_r{$roundNumber}_t{$tableNumber}_".Str::random(6);

                $match = SpeedNetworkingMatch::create([
                    'event_id' => $event->id,
                    'round_number' => $roundNumber,
                    'table_number' => $tableNumber,
                    'channel_id' => $channelId,
                    'user_a_id' => $userId,
                    'user_b_id' => $partnerId,
                    'status' => SpeedNetworkingMatch::STATUS_ACTIVE,
                    'match_status' => SpeedNetworkingMatch::MATCH_STATUS_PREVIEW,
                    'user_a_decision' => SpeedNetworkingMatch::DECISION_PENDING,
                    'user_b_decision' => SpeedNetworkingMatch::DECISION_PENDING,
                    'preview_duration_seconds' => $previewSeconds,
                    'preview_expires_at' => Carbon::now()->addSeconds($previewSeconds + 3),
                    'call_started_at' => null,
                    'started_at' => null,
                ]);

                // Update skip timestamp to move this peer to the back of the queue if skipped again
                if (Schema::hasTable('speed_networking_event_skips')) {
                    SpeedNetworkingEventSkip::where('event_id', $event->id)
                        ->where(function ($q) use ($userId, $partnerId): void {
                            $q->where(function ($sub) use ($userId, $partnerId): void {
                                $sub->where('user_id', $userId)->where('skipped_peer_id', $partnerId);
                            })->orWhere(function ($sub) use ($userId, $partnerId): void {
                                $sub->where('user_id', $partnerId)->where('skipped_peer_id', $userId);
                            });
                        })
                        ->update(['updated_at' => Carbon::now()]);
                }

                // Both Peer A and Peer B enter 'preview' on the SAME match record simultaneously
                SpeedNetworkingParticipant::where('event_id', $event->id)
                    ->whereIn('user_id', [$userId, $partnerId])
                    ->update([
                        'status' => SpeedNetworkingParticipant::STATUS_PREVIEW,
                    ]);

                return $this->formatMatchResponse($event, $match->fresh(['userA', 'userB']) ?? $match, $userId);
            }

            // 6. No candidate is free right now -> keep user in waiting room (NEVER prematurely complete while event is live)
            SpeedNetworkingParticipant::where('event_id', $event->id)
                ->where('user_id', $userId)
                ->where('status', '!=', SpeedNetworkingParticipant::STATUS_LEFT)
                ->update(['status' => SpeedNetworkingParticipant::STATUS_SEARCHING]);

            return [
                'event_id' => (string) $event->id,
                'title' => $event->title,
                'event_title' => $event->title,
                'round_duration_seconds' => (int) $event->round_duration_seconds,
                'break_duration_seconds' => (int) $event->break_duration_seconds,
                'current_round' => $userCompletedCount,
                'total_rounds' => (int) $event->total_rounds,
                'remaining_seconds' => (int) $event->round_duration_seconds,
                'preview_duration_seconds' => 15,
                'channel_id' => '',
                'agora_app_id' => $this->getAgoraAppId(),
                'agora_token' => '',
                'status' => 'waiting',
                'match_status' => 'waiting',
                'my_decision' => 'pending',
                'peer_decision' => 'pending',
                'both_accepted' => false,
                'is_break_round' => false,
                'is_waiting' => true,
                'has_met_all_peers' => false,
                'completed' => false,
                'message' => 'Looking for new peers to join this Speed Collaboration...',
                'peer' => null,
                'interacted_peers' => [],
            ];
        });
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
     * Build the complete PeerCard payload for live call overlay, match preview, and heartbeat responses.
     *
     * @return array<string, mixed>
     */
    public function buildPeerCardPayload(
        User $peer,
        string $currentUserId,
        ?SpeedNetworkingEvent $event = null,
        int $round = 1
    ): array {
        $resolveString = static function (mixed $value): string {
            if (is_null($value)) {
                return '';
            }
            if (is_array($value)) {
                return implode(', ', array_filter(array_map('strval', $value)));
            }

            return trim((string) $value);
        };

        $peerName = $peer->display_name ?: trim(($peer->first_name ?? '').' '.($peer->last_name ?? ''));
        if (empty($peerName)) {
            $peerName = (string) ($peer->getRawOriginal('display_name') ?? $peer->getAttributes()['display_name'] ?? '');
        }
        if (empty($peerName)) {
            $peerName = (string) ($peer->name ?? 'Peer');
        }

        $avatarUrl = (string) ($peer->profile_photo_url ?? '');

        // Connection status check
        $connection = null;
        if (Schema::hasTable('connections')) {
            try {
                $connection = Connection::where(function ($q) use ($currentUserId, $peer): void {
                    $q->where('requester_id', $currentUserId)->where('addressee_id', $peer->id);
                })->orWhere(function ($q) use ($currentUserId, $peer): void {
                    $q->where('requester_id', $peer->id)->where('addressee_id', $currentUserId);
                })->first();
            } catch (\Throwable) {
                $connection = null;
            }
        }

        $connectionStatus = 'none';
        $isConnected = false;
        $isRequested = false;
        $canSendConnectionRequest = true;

        if ($connection) {
            $isConnected = (bool) ($connection->is_approved ?? false);
            if (isset($connection->status) && in_array($connection->status, ['connected', 'pending', 'none'], true)) {
                $connectionStatus = (string) $connection->status;
            } else {
                $connectionStatus = $isConnected ? 'connected' : 'pending';
            }
            $isRequested = ! $isConnected && (string) $connection->requester_id === $currentUserId;
            $canSendConnectionRequest = ! $isConnected && ! $isRequested;
        }

        // Follow status check
        $isFollowing = false;
        if (Schema::hasTable('user_follows')) {
            try {
                $isFollowing = DB::table('user_follows')
                    ->where('follower_id', $currentUserId)
                    ->where('following_id', (string) $peer->id)
                    ->whereIn('status', ['accepted', 'pending'])
                    ->exists();
            } catch (\Throwable) {
                $isFollowing = false;
            }
        }

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

        $category = $resolveString($peer->level4_category ?? $peer->business_category ?? '');
        $industry = $resolveString($peer->business_sub_category);
        if ($industry === '') {
            $industry = $resolveString($peer->company_type);
        }
        if ($industry === '') {
            $industry = $category !== '' ? $category : ($skills[0] ?? 'Business & Entrepreneurship');
        }
        if ($category === '') {
            $category = $skills[0] ?? $industry;
        }

        $company = $resolveString($peer->company_name ?? $peer->company ?? '');
        $isPro = method_exists($peer, 'isPro') ? (bool) $peer->isPro() : (! empty($peer->is_pro));
        $isVerified = (bool) ($peer->is_verified ?? false);

        $peerLifeImpact = $this->resolveUserLifeImpact($peer, $event);

        return [
            'id' => (string) $peer->id,
            'peer_id' => (string) ($peer->peer_id ?? $peer->id),
            'name' => $peerName,
            'display_name' => $peerName,
            'full_name' => $peerName,
            'first_name' => $resolveString($peer->first_name ?? ''),
            'last_name' => $resolveString($peer->last_name ?? ''),
            'designation' => $resolveString($peer->designation ?? ''),
            'company' => $company,
            'company_name' => $company,
            'avatar_url' => $avatarUrl,
            'profile_photo_url' => $avatarUrl,
            'profile_image_url' => $avatarUrl,
            'profile_photo_image' => $avatarUrl,
            'city' => $resolveString($peer->city ?? ''),
            'bio' => $bio,
            'industry' => $industry,
            'category' => $category,
            'business_category' => $category,
            'business_category_name' => $category,
            'level4_category' => $category,
            'skills' => $skills,
            'is_verified' => $isVerified,
            'is_pro' => $isPro,
            'is_online' => (bool) ($peer->is_online ?? true),
            'connection_status' => $connectionStatus,
            'is_connected' => $isConnected,
            'is_requested' => $isRequested,
            'can_send_connection_request' => $canSendConnectionRequest,
            'is_following' => $isFollowing,
            'round' => $round,
            'score' => $peerLifeImpact,
            'peer_score' => $peerLifeImpact,
            'impact_score' => $peerLifeImpact,
            'life_impacted_count' => $peerLifeImpact,
            'life_impact_count' => $peerLifeImpact,
            'life_impact' => $peerLifeImpact,
            'life_impacted' => $peerLifeImpact,
            'lives_impacted' => $peerLifeImpact,
            'lives_impacted_count' => $peerLifeImpact,
            'life_impact_score' => $peerLifeImpact,
            'total_life_impact' => $peerLifeImpact,
            'total_life_impacted' => $peerLifeImpact,
            'mutual_connections_count' => $peerLifeImpact,
        ];
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
            try {
                $token = $this->agoraTokenService->generateToken(
                    $match->channel_id,
                    0,
                    $event->round_duration_seconds + 300
                );
            } catch (\Throwable) {
                $token = 'demo_rtc_token_'.md5($match->channel_id.$currentUserId);
            }
        }

        $peerData = null;
        if ($peer) {
            $peerData = $this->buildPeerCardPayload(
                $peer,
                $currentUserId,
                $event,
                (int) ($match->round_number ?: 1)
            );
        }

        $isUserA = ((string) $match->user_a_id === $currentUserId);
        $myDecision = (string) ($isUserA
            ? ($match->user_a_decision ?: SpeedNetworkingMatch::DECISION_PENDING)
            : ($match->user_b_decision ?: SpeedNetworkingMatch::DECISION_PENDING));
        $peerDecision = (string) ($isUserA
            ? ($match->user_b_decision ?: SpeedNetworkingMatch::DECISION_PENDING)
            : ($match->user_a_decision ?: SpeedNetworkingMatch::DECISION_PENDING));

        $matchStatus = (string) ($match->match_status ?: SpeedNetworkingMatch::MATCH_STATUS_PREVIEW);
        $bothAccepted = ($myDecision === SpeedNetworkingMatch::DECISION_ACCEPTED
            && $peerDecision === SpeedNetworkingMatch::DECISION_ACCEPTED
            && $matchStatus === SpeedNetworkingMatch::MATCH_STATUS_IN_CALL);

        $previewDuration = (int) ($match->preview_duration_seconds ?: 15);
        $remainingSeconds = (int) $event->round_duration_seconds;

        if ($matchStatus === SpeedNetworkingMatch::MATCH_STATUS_IN_CALL) {
            $callStart = $match->call_started_at ?? $match->started_at;
            if ($callStart) {
                $elapsed = Carbon::now()->diffInSeconds($callStart);
                $remainingSeconds = max(0, (int) $event->round_duration_seconds - (int) $elapsed);
            }
        }

        $message = $matchStatus === SpeedNetworkingMatch::MATCH_STATUS_IN_CALL
            ? 'Speed Collaboration active.'
            : ($myDecision === SpeedNetworkingMatch::DECISION_ACCEPTED
                ? 'Waiting for peer to connect...'
                : 'Match found! Review peer profile to connect.');

        return [
            'event_id' => (string) $event->id,
            'title' => $event->title,
            'event_title' => $event->title,
            'match_id' => (string) $match->id,
            'current_round' => (int) ($match->round_number ?: 1),
            'total_rounds' => (int) $event->total_rounds,
            'round_duration_seconds' => (int) $event->round_duration_seconds,
            'break_duration_seconds' => (int) $event->break_duration_seconds,
            'remaining_seconds' => $remainingSeconds,
            'preview_duration_seconds' => $previewDuration,
            'channel_id' => $match->channel_id ?: '',
            'agora_app_id' => $this->getAgoraAppId(),
            'agora_token' => $token ?: '',
            'match_status' => $matchStatus,
            'my_decision' => $myDecision,
            'peer_decision' => $peerDecision,
            'both_accepted' => $bothAccepted,
            'is_break_round' => $isBreak,
            'is_waiting' => false,
            'has_met_all_peers' => $isBreak ? $hasMetAllPeers : false,
            'completed' => false,
            'message' => $message,
            'peer' => $peerData,
            'interacted_peers' => [],
        ];
    }

    /**
     * Get active match payload for user if currently in an active preview or call round.
     * Also detects if a preview match was just skipped/blocked/expired by the partner.
     *
     * @return array<string, mixed>|null
     */
    public function getActiveMatchPayload(SpeedNetworkingEvent $event, User $user, ?string $userStatus = null): ?array
    {
        $userId = (string) $user->id;
        $activeMatch = $this->findActiveMatchForUser($event->id, $userId);

        if ($activeMatch) {
            $matchStatus = (string) ($activeMatch->match_status ?: SpeedNetworkingMatch::MATCH_STATUS_PREVIEW);

            if ($matchStatus === SpeedNetworkingMatch::MATCH_STATUS_PREVIEW) {
                $isPreviewExpired = $activeMatch->preview_expires_at !== null
                    && Carbon::now()->greaterThan($activeMatch->preview_expires_at)
                    && ! ($activeMatch->user_a_decision === SpeedNetworkingMatch::DECISION_ACCEPTED && $activeMatch->user_b_decision === SpeedNetworkingMatch::DECISION_ACCEPTED);

                if ($isPreviewExpired) {
                    $this->expirePreviewMatch($activeMatch);
                    $refreshedMatch = $activeMatch->fresh(['userA', 'userB']) ?? $activeMatch;
                    if ($refreshedMatch->match_status === SpeedNetworkingMatch::MATCH_STATUS_IN_CALL) {
                        return $this->formatMatchResponse($event, $refreshedMatch, (string) $user->id);
                    }
                    $isUserA = ((string) $activeMatch->user_a_id === $userId);

                    return [
                        'event_id' => (string) $event->id,
                        'match_id' => (string) $activeMatch->id,
                        'channel_id' => (string) ($activeMatch->channel_id ?? ''),
                        'match_status' => SpeedNetworkingMatch::MATCH_STATUS_SKIPPED,
                        'my_decision' => (string) ($isUserA ? $activeMatch->user_a_decision : $activeMatch->user_b_decision),
                        'peer_decision' => SpeedNetworkingMatch::DECISION_SKIP,
                        'both_accepted' => false,
                        'is_waiting' => true,
                        'has_met_all_peers' => false,
                        'completed' => false,
                    ];
                }

                return $this->formatMatchResponse($event, $activeMatch, $userId);
            }

            // In call: check round duration against call_started_at
            $callStart = $activeMatch->call_started_at ?? $activeMatch->started_at;
            $elapsed = $callStart ? Carbon::now()->diffInSeconds($callStart) : 0;
            if ($elapsed >= $event->round_duration_seconds) {
                $endedAt = $activeMatch->ended_at ?? Carbon::now();
                $durationSeconds = $activeMatch->duration_seconds !== null && (int) $activeMatch->duration_seconds > 0
                    ? (int) $activeMatch->duration_seconds
                    : min(max(0, (int) $elapsed), max(1, (int) $event->round_duration_seconds));

                $activeMatch->update([
                    'status' => SpeedNetworkingMatch::STATUS_COMPLETED,
                    'match_status' => SpeedNetworkingMatch::MATCH_STATUS_COMPLETED,
                    'ended_at' => $endedAt,
                    'duration_seconds' => $durationSeconds,
                ]);

                SpeedNetworkingParticipant::where('event_id', $event->id)
                    ->where('user_id', $userId)
                    ->where('status', SpeedNetworkingParticipant::STATUS_IN_CALL)
                    ->update(['status' => SpeedNetworkingParticipant::STATUS_SEARCHING]);

                return null;
            }

            return $this->formatMatchResponse($event, $activeMatch, $userId);
        }

        // If user was on preview or accepted screen, check if partner just skipped/blocked/expired
        if (in_array($userStatus, ['preview', 'accepted'], true)) {
            $recentSkipped = SpeedNetworkingMatch::query()
                ->where('event_id', $event->id)
                ->whereIn('match_status', [
                    SpeedNetworkingMatch::MATCH_STATUS_SKIPPED,
                    SpeedNetworkingMatch::MATCH_STATUS_BLOCKED,
                    SpeedNetworkingMatch::MATCH_STATUS_EXPIRED,
                ])
                ->where(function ($q) use ($userId): void {
                    $q->where('user_a_id', $userId)
                        ->orWhere('user_b_id', $userId);
                })
                ->where('updated_at', '>=', Carbon::now()->subSeconds(15))
                ->latest('updated_at')
                ->first();

            if ($recentSkipped) {
                // If a newer active match already exists for this user, do not return stale skipped status
                $newerMatch = SpeedNetworkingMatch::where('event_id', $event->id)
                    ->where(function ($q) use ($userId): void {
                        $q->where('user_a_id', $userId)->orWhere('user_b_id', $userId);
                    })
                    ->where('id', '!=', $recentSkipped->id)
                    ->where('created_at', '>', $recentSkipped->created_at)
                    ->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)
                    ->latest('created_at')
                    ->first();

                if ($newerMatch) {
                    return $this->formatMatchResponse($event, $newerMatch, $userId);
                }

                $isUserA = ((string) $recentSkipped->user_a_id === $userId);
                $myDec = (string) ($isUserA ? $recentSkipped->user_a_decision : $recentSkipped->user_b_decision);

                SpeedNetworkingParticipant::where('event_id', $event->id)
                    ->where('user_id', $userId)
                    ->whereIn('status', [
                        SpeedNetworkingParticipant::STATUS_PREVIEW,
                        SpeedNetworkingParticipant::STATUS_ACCEPTED,
                    ])
                    ->update(['status' => SpeedNetworkingParticipant::STATUS_SEARCHING]);

                return [
                    'event_id' => (string) $event->id,
                    'match_id' => (string) $recentSkipped->id,
                    'channel_id' => (string) ($recentSkipped->channel_id ?? ''),
                    'match_status' => SpeedNetworkingMatch::MATCH_STATUS_SKIPPED,
                    'my_decision' => $myDec,
                    'peer_decision' => SpeedNetworkingMatch::DECISION_SKIP,
                    'both_accepted' => false,
                    'is_waiting' => true,
                    'has_met_all_peers' => false,
                    'completed' => false,
                ];
            }
        }

        return null;
    }

    /**
     * Format a break round response.
     *
     * @return array<string, mixed>
     */
    protected function formatBreakRoundResponse(SpeedNetworkingEvent $event, int $roundNumber, bool $hasMetAllPeers = false): array
    {
        $isEnded = $event->isCompleted();
        $message = $isEnded
            ? 'Speed Collaboration has concluded.'
            : 'Looking for new peers to join this Speed Collaboration...';

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
            'agora_app_id' => $this->getAgoraAppId(),
            'agora_token' => '',
            'status' => $isEnded ? 'event_ended' : 'waiting',
            'match_status' => $isEnded ? 'event_ended' : 'waiting',
            'is_break_round' => false,
            'is_waiting' => ! $isEnded,
            'has_met_all_peers' => $isEnded,
            'completed' => $isEnded,
            'message' => $message,
            'peer' => null,
        ];
    }

    /**
     * Check if user has finished all rounds in this event.
     * Pool stays open until scheduled_end_time so late joiners can be matched.
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

        return false;
    }

    /**
     * Get summary of peers that the user actively interacted with during this session.
     * Strictly counts ONLY matches where match_status IN ('in_call', 'completed') and excludes blocked peers.
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
        $this->ensureSpeedNetworkingSchema();
        $userId = (string) $user->id;
        $blockedPeerIds = $this->getBidirectionallyBlockedPeerIds($userId);

        $matches = SpeedNetworkingMatch::with(['userA', 'userB'])
            ->where('event_id', $event->id)
            ->where(function ($q) use ($userId): void {
                $q->where('user_a_id', $userId)
                    ->orWhere('user_b_id', $userId);
            })
            ->whereNotNull('user_b_id')
            ->whereIn('match_status', [
                SpeedNetworkingMatch::MATCH_STATUS_IN_CALL,
                SpeedNetworkingMatch::MATCH_STATUS_COMPLETED,
            ])
            ->where('status', '!=', SpeedNetworkingMatch::STATUS_SKIPPED)
            ->orderBy('round_number', 'asc')
            ->get();

        $interactedPeers = [];
        $seenPeerIds = [];

        // Auth user bookmarks
        $authUserBookmarks = (array) ($user->bookmarks ?? []);
        $bookmarkIds = array_map('strval', $authUserBookmarks);

        // Pre-query followed peers using correct user_follows columns (follower_id, following_id)
        $followedPeerIds = [];
        if (Schema::hasTable('user_follows')) {
            try {
                $candidatePeerIds = $matches->map(function (SpeedNetworkingMatch $m) use ($userId): ?string {
                    $pid = (string) ($m->user_a_id === $userId ? $m->user_b_id : $m->user_a_id);

                    return $pid !== '' ? $pid : null;
                })->filter()->unique()->values()->all();

                if (! empty($candidatePeerIds)) {
                    $followedPeerIds = DB::table('user_follows')
                        ->where('follower_id', $userId)
                        ->whereIn('following_id', $candidatePeerIds)
                        ->whereIn('status', ['accepted', 'pending'])
                        ->pluck('following_id')
                        ->map(fn ($id): string => (string) $id)
                        ->all();
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        // Pre-query user's approved connection IDs once instead of per match
        $authConnIds = [];
        if (Schema::hasTable('connections')) {
            try {
                $authConnIds = DB::table('connections')
                    ->where(function ($q) use ($userId): void {
                        $q->where('requester_id', $userId)->orWhere('addressee_id', $userId);
                    })
                    ->where('is_approved', true)
                    ->selectRaw('CASE WHEN requester_id = ? THEN addressee_id ELSE requester_id END as conn_id', [$userId])
                    ->pluck('conn_id')
                    ->map(fn ($id): string => (string) $id)
                    ->toArray();
            } catch (\Throwable) {
                // ignore
            }
        }

        foreach ($matches as $match) {
            /** @var User|null $peer */
            $peer = $match->user_a_id === $userId ? $match->userB : $match->userA;
            $peerId = (string) ($match->user_a_id === $userId ? $match->user_b_id : $match->user_a_id);

            if ($peerId !== '' && in_array($peerId, $blockedPeerIds, true)) {
                continue;
            }

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
            $connection = null;
            if (Schema::hasTable('connections')) {
                $connection = Connection::where(function ($q) use ($userId, $peer): void {
                    $q->where('requester_id', $userId)->where('addressee_id', $peer->id);
                })->orWhere(function ($q) use ($userId, $peer): void {
                    $q->where('requester_id', $peer->id)->where('addressee_id', $userId);
                })->first();
            }

            $connectionStatus = 'none';
            $isConnected = false;
            $isRequested = false;
            $canSendConnectionRequest = true;

            if ($connection) {
                $isConnected = (bool) ($connection->is_approved ?? false);
                if (isset($connection->status) && in_array($connection->status, ['connected', 'pending', 'none'], true)) {
                    $connectionStatus = (string) $connection->status;
                } else {
                    $connectionStatus = $isConnected ? 'connected' : 'pending';
                }
                $isRequested = ! $isConnected && (string) $connection->requester_id === $userId;
                $canSendConnectionRequest = ! $isConnected && ! $isRequested;
            }

            $isFollowing = in_array((string) $peer->id, $followedPeerIds, true);
            $isBookmark = in_array((string) $peer->id, $bookmarkIds, true);

            $mutualCount = 0;
            if (! empty($authConnIds) && Schema::hasTable('connections')) {
                try {
                    $peerIdStr = (string) $peer->id;
                    $peerConnIds = DB::table('connections')
                        ->where(function ($q) use ($peerIdStr): void {
                            $q->where('requester_id', $peerIdStr)->orWhere('addressee_id', $peerIdStr);
                        })
                        ->where('is_approved', true)
                        ->selectRaw('CASE WHEN requester_id = ? THEN addressee_id ELSE requester_id END as conn_id', [$peerIdStr])
                        ->pluck('conn_id')
                        ->map(fn ($id): string => (string) $id)
                        ->toArray();

                    $mutualCount = count(array_diff(array_intersect($authConnIds, $peerConnIds), [$userId, $peerIdStr]));
                } catch (\Throwable) {
                    // ignore
                }
            }

            $introducedCount = (int) ($peer->members_introduced_count ?? 0);
            if ($introducedCount <= 0 && Schema::hasTable('users') && Schema::hasColumn('users', 'introduced_by')) {
                try {
                    $introducedCount = (int) DB::table('users')->where('introduced_by', $peer->id)->count();
                } catch (\Throwable) {
                    // ignore
                }
            }

            $connectionCount = method_exists($peer, 'resolveConnectionCount')
                ? $peer->resolveConnectionCount()
                : (int) ($peer->connection_count ?? 0);

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

            // Calculate actual 1-on-1 call duration in seconds (strictly from call_started_at)
            $duration = $match->resolveCallDurationSeconds((int) $event->round_duration_seconds);
            $qualifiesForP2p = $duration >= SpeedNetworkingMatch::MIN_QUALIFYING_DURATION_SECONDS;

            // Only process P2P meeting, creative, Life Impact (+1), and Coins (+3,000) if duration >= 120s
            $p2pMeeting = null;
            $creativeUrl = null;
            if ($qualifiesForP2p) {
                try {
                    $p2pMeeting = $this->p2pService->completeMatchAsP2pMeeting($match, $event);
                    if ($p2pMeeting) {
                        $creativeUrl = $this->p2pService->getCreativeUrlForMatch($match);
                    }
                } catch (\Throwable $p2pEx) {
                    Log::warning('[SpeedNetworkingService] Could not complete match as P2P meeting in summary: '.$p2pEx->getMessage());
                }
            }

            // Refresh peer after completing P2P meeting so life impact & profile details are fresh
            $peer = $peer->fresh() ?? $peer;
            $impactPerMeeting = (int) (config('services.speed_networking.impact_per_round') ?? config('impact.activity_rewards.speed_networking') ?? 1);
            if ($impactPerMeeting <= 0) {
                $impactPerMeeting = 1;
            }

            $peerLifeImpact = $this->resolveUserLifeImpact($peer, $event);

            $isPro = method_exists($peer, 'isPro') ? (bool) $peer->isPro() : (! empty($peer->is_pro));
            $companyName = ! empty($peer->company_name) ? (string) $peer->company_name : (! empty($peer->company) ? (string) $peer->company : null);
            $skillsList = is_array($peer->skills) ? array_values(array_filter(array_map('strval', $peer->skills))) : (is_string($peer->skills) ? (json_decode($peer->skills, true) ?: [trim($peer->skills)]) : []);
            $businessCategory = $peer->level4_category ?? $peer->business_category ?? null;
            $resolvedIndustry = $businessCategory ?: ($peer->business_sub_category ?: ($peer->company_type ?: ($skillsList[0] ?? 'Business & Entrepreneurship')));
            $resolvedCategory = $businessCategory ?: ($skillsList[0] ?? $resolvedIndustry);

            $savedMatchNotes = $match->getNotesForUser($userId);
            $cleanP2pRemarks = trim(preg_replace('/\[sn_[^\]]+\]/', '', (string) ($p2pMeeting?->remarks ?: ($p2pMeeting?->topic ?: ''))) ?? '');
            $resolvedNotes = $savedMatchNotes !== '' ? $savedMatchNotes : $cleanP2pRemarks;

            $interactedPeers[] = [
                'id' => (string) $peer->id,
                'peer_id' => (string) ($peer->peer_id ?? $peer->id),
                'name' => (string) $peerName,
                'display_name' => (string) $peerName,
                'full_name' => (string) $peerName,
                'first_name' => (string) ($peer->first_name ?? ''),
                'last_name' => (string) ($peer->last_name ?? ''),
                'company' => $companyName,
                'company_name' => $companyName,
                'designation' => ! empty($peer->designation) ? (string) $peer->designation : null,
                'business_category' => $resolvedCategory,
                'business_category_name' => $resolvedCategory,
                'level4_category' => $resolvedCategory,
                'industry' => $resolvedIndustry,
                'category' => $resolvedCategory,
                'skills' => $skillsList,
                'avatar_url' => ! empty($peer->profile_photo_url) ? (string) $peer->profile_photo_url : null,
                'profile_photo_url' => ! empty($peer->profile_photo_url) ? (string) $peer->profile_photo_url : null,
                'profile_image_url' => ! empty($peer->profile_photo_url) ? (string) $peer->profile_photo_url : null,
                'profile_photo_image' => ! empty($peer->profile_photo_url) ? (string) $peer->profile_photo_url : null,
                'city' => ! empty($peer->city) ? (string) $peer->city : null,
                'bio' => $bio !== '' ? $bio : null,
                'round' => (int) $match->round_number,
                'interaction_duration_seconds' => $duration,
                'connection_status' => $connectionStatus,
                'is_connected' => $connectionStatus === 'connected',
                'is_requested' => $isRequested,
                'can_send_connection_request' => $canSendConnectionRequest,
                'is_following' => $isFollowing,
                'is_bookmark' => $isBookmark,
                'is_pro' => $isPro,
                'is_verified' => (bool) ($peer->is_verified ?? false),
                'is_online' => (bool) ($peer->is_online ?? false),
                'score' => $peerLifeImpact,
                'peer_score' => $peerLifeImpact,
                'mutual_connections_count' => $peerLifeImpact > 0 ? $peerLifeImpact : $mutualCount,
                'mutual_count' => $peerLifeImpact > 0 ? $peerLifeImpact : $mutualCount,
                'real_mutual_connections_count' => $mutualCount,
                'connection_count' => $connectionCount > 0 ? $connectionCount : $peerLifeImpact,
                'connections_count' => $connectionCount > 0 ? $connectionCount : $peerLifeImpact,
                'introduced_count' => $introducedCount > 0 ? $introducedCount : $peerLifeImpact,
                'members_introduced_count' => $introducedCount > 0 ? $introducedCount : $peerLifeImpact,

                // Comprehensive life impact aliases for mobile client compatibility
                'life_impacted_count' => $peerLifeImpact,
                'life_impact_count' => $peerLifeImpact,
                'life_impact' => $peerLifeImpact,
                'life_impacted' => $peerLifeImpact,
                'lives_impacted' => $peerLifeImpact,
                'lives_impacted_count' => $peerLifeImpact,
                'life_impact_score' => $peerLifeImpact,
                'total_life_impact' => $peerLifeImpact,
                'total_life_impacted' => $peerLifeImpact,
                'impact_score' => $peerLifeImpact,
                'impact_count' => $peerLifeImpact,
                'impacts_count' => $peerLifeImpact,
                'peer_life_impact' => $peerLifeImpact,
                'peer_life_impacted_count' => $peerLifeImpact,

                'p2p_meeting_id' => ($qualifiesForP2p && $p2pMeeting?->id) ? (string) $p2pMeeting->id : null,
                'creative_url' => $qualifiesForP2p ? $creativeUrl : null,
                'p2p_logged' => $qualifiesForP2p && $p2pMeeting !== null,
                'meeting_notes' => $resolvedNotes,
                'notes' => $resolvedNotes,
                'remarks' => $resolvedNotes,
            ];
        }

        $roundsCompleted = (int) $matches->pluck('round_number')->unique()->count();
        $hasMetAllPeers = $this->hasMetAllActivePeers($event, $userId);

        $qualifyingPeersCount = count(array_filter(
            $interactedPeers,
            static fn (array $peerItem): bool => ($peerItem['p2p_logged'] ?? false) === true
                && ((int) ($peerItem['interaction_duration_seconds'] ?? 0)) >= SpeedNetworkingMatch::MIN_QUALIFYING_DURATION_SECONDS
        ));

        $freshUser = $user->fresh() ?? $user;
        $userCoins = (int) ($freshUser->coins_balance ?? 0);
        $impactPerMeeting = (int) (config('services.speed_networking.impact_per_round') ?? config('impact.activity_rewards.speed_networking') ?? 1);
        if ($impactPerMeeting <= 0) {
            $impactPerMeeting = 1;
        }
        $lifeImpactEarned = $qualifyingPeersCount * $impactPerMeeting;
        $coinsEarned = $qualifyingPeersCount * 3000;

        $userLifeImpact = $this->resolveUserLifeImpact($freshUser, $event);
        if ($userLifeImpact < $lifeImpactEarned) {
            $userLifeImpact = max($userLifeImpact, $lifeImpactEarned);
        }

        $message = $hasMetAllPeers
            ? 'You have met all peers in this event.'
            : 'All speed networking rounds have concluded. Great conversations made!';

        return [
            'completed' => true,
            'has_met_all_peers' => $hasMetAllPeers,
            'message' => $message,
            'total_rounds' => (int) $event->total_rounds,
            'rounds_completed' => $roundsCompleted,
            'total_interactions_count' => count($interactedPeers),
            'qualifying_peers_count' => $qualifyingPeersCount,
            'life_impact' => [
                'earned' => $lifeImpactEarned,
                'total' => $userLifeImpact,
                'balance_after' => $userLifeImpact,
                'impact_score' => $userLifeImpact,
                'impact_score_earned' => $lifeImpactEarned,
                'score' => $userLifeImpact,
                'impact' => $lifeImpactEarned,
            ],
            'life_impact_earned' => $lifeImpactEarned,
            'life_impact_score' => $userLifeImpact,
            'total_life_impacted' => $userLifeImpact,
            'life_impacted_count' => $userLifeImpact,
            'life_impact_count' => $userLifeImpact,
            'lives_impacted' => $userLifeImpact,
            'lives_impacted_count' => $userLifeImpact,
            'impact_score' => $userLifeImpact,
            'impact_score_earned' => $lifeImpactEarned,
            'impact_earned' => $lifeImpactEarned,
            'impact_points_earned' => $lifeImpactEarned,
            'earned_impact' => $lifeImpactEarned,
            'impact' => $lifeImpactEarned,
            'score' => $userLifeImpact,
            'coins' => [
                'earned' => $coinsEarned,
                'balance_after' => $userCoins,
            ],
            'coins_earned' => $coinsEarned,
            'peers_met_count' => count($interactedPeers),
            'peers_met' => $interactedPeers,
            'interacted_peers' => $interactedPeers,
        ];
    }

    /**
     * Resolve the comprehensive life impact count for a user across all sources
     * (users table, life_impact_histories, impacts, p2p_meetings, and speed networking matches).
     */
    public function resolveUserLifeImpact(User|string $userOrId, ?SpeedNetworkingEvent $event = null): int
    {
        $userId = $userOrId instanceof User ? (string) $userOrId->id : (string) $userOrId;
        $user = $userOrId instanceof User ? $userOrId : User::find($userId);

        $total = 0;

        // 1. Check user model attribute
        if ($user) {
            $colValue = (int) ($user->life_impacted_count ?? 0);
            if ($colValue > 0) {
                $total = max($total, $colValue);
            }
        }

        // 2. Direct column check in users table
        if ($total <= 0 && Schema::hasTable('users')) {
            $dbCol = (int) (DB::table('users')->where('id', $userId)->value('life_impacted_count') ?? 0);
            if ($dbCol > 0) {
                $total = max($total, $dbCol);
            }
        }

        // 3. Table life_impact_histories
        if (Schema::hasTable('life_impact_histories')) {
            try {
                $histQuery = DB::table('life_impact_histories')->where('user_id', $userId);
                if (Schema::hasColumn('life_impact_histories', 'status')) {
                    $histQuery->where(function ($q): void {
                        $q->whereNull('status')->orWhere('status', 'approved');
                    });
                }
                if (Schema::hasColumn('life_impact_histories', 'counted_in_total')) {
                    $histQuery->where(function ($q): void {
                        $q->whereNull('counted_in_total')->orWhere('counted_in_total', true);
                    });
                }
                $hasVal = Schema::hasColumn('life_impact_histories', 'impact_value');
                $hasLife = Schema::hasColumn('life_impact_histories', 'life_impacted');
                $valExpr = ($hasVal && $hasLife)
                    ? 'COALESCE(NULLIF(impact_value, 0), NULLIF(life_impacted, 0), 0)'
                    : ($hasVal ? 'COALESCE(NULLIF(impact_value, 0), 0)' : ($hasLife ? 'COALESCE(NULLIF(life_impacted, 0), 0)' : '0'));

                $histSum = (int) $histQuery->sum(DB::raw($valExpr));
                if ($histSum > 0) {
                    $total = max($total, $histSum);
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        // 4. Table impacts
        if (Schema::hasTable('impacts')) {
            try {
                $impQuery = DB::table('impacts')->where('user_id', $userId);
                if (Schema::hasColumn('impacts', 'deleted_at')) {
                    $impQuery->whereNull('deleted_at');
                }
                if (Schema::hasColumn('impacts', 'status')) {
                    $impQuery->where(function ($q): void {
                        $q->whereNull('status')->orWhere('status', 'approved');
                    });
                }
                $hasImpactsLife = Schema::hasColumn('impacts', 'life_impacted');
                $hasImpactsValue = Schema::hasColumn('impacts', 'impact_value');
                $impExpr = ($hasImpactsLife && $hasImpactsValue)
                    ? 'COALESCE(NULLIF(life_impacted, 0), NULLIF(impact_value, 0), 1)'
                    : ($hasImpactsLife ? 'COALESCE(NULLIF(life_impacted, 0), 1)' : ($hasImpactsValue ? 'COALESCE(NULLIF(impact_value, 0), 1)' : '1'));

                $impSum = (int) $impQuery->sum(DB::raw($impExpr));
                if ($impSum > 0) {
                    $total = max($total, $impSum);
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        // 5. Table p2p_meetings
        $impactPerMeeting = (int) (config('services.speed_networking.impact_per_round') ?? config('impact.activity_rewards.speed_networking') ?? 1);
        if ($impactPerMeeting <= 0) {
            $impactPerMeeting = 1;
        }

        if (Schema::hasTable('p2p_meetings')) {
            try {
                $p2pMeetingsCount = DB::table('p2p_meetings')
                    ->where(function ($q) use ($userId): void {
                        $q->where('initiator_user_id', $userId)
                            ->orWhere('peer_user_id', $userId);
                    })
                    ->where('is_deleted', false)
                    ->count();

                if ($p2pMeetingsCount > 0) {
                    $total = max($total, $p2pMeetingsCount * $impactPerMeeting);
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        // 6. Current Speed Networking Event matches (only actual in_call / completed rounds lasting >= 120s)
        if ($event && Schema::hasTable('speed_networking_matches')) {
            try {
                $eventMatches = SpeedNetworkingMatch::where('event_id', $event->id)
                    ->where(function ($q) use ($userId): void {
                        $q->where('user_a_id', $userId)
                            ->orWhere('user_b_id', $userId);
                    })
                    ->whereNotNull('user_b_id')
                    ->whereIn('match_status', [
                        SpeedNetworkingMatch::MATCH_STATUS_IN_CALL,
                        SpeedNetworkingMatch::MATCH_STATUS_COMPLETED,
                    ])
                    ->where('status', '!=', SpeedNetworkingMatch::STATUS_SKIPPED)
                    ->get();

                $qualifyingEventMatchesCount = $eventMatches->filter(
                    static fn (SpeedNetworkingMatch $m): bool => $m->qualifiesForP2pRewards((int) $event->round_duration_seconds)
                )->count();

                if ($qualifyingEventMatchesCount > 0) {
                    $total = max($total, $qualifyingEventMatchesCount * $impactPerMeeting);
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        // Ensure user's cached DB column is up-to-date
        if ($total > 0 && Schema::hasTable('users') && Schema::hasColumn('users', 'life_impacted_count')) {
            try {
                DB::table('users')
                    ->where('id', $userId)
                    ->where(function ($q): void {
                        $q->whereNull('life_impacted_count')->orWhere('life_impacted_count', '<', 1);
                    })
                    ->update([
                        'life_impacted_count' => $total,
                        'updated_at' => now(),
                    ]);
            } catch (\Throwable) {
                // ignore
            }
        }

        return $total;
    }

    /**
     * Get user's Speed Networking history with full session summary for each event.
     *
     * @return array{data: array<int, mixed>, pagination: array<string, int>}
     */
    public function getUserHistory(User $user, int $perPage = 15): array
    {
        $userId = (string) $user->id;

        $eventIds = [];
        if (Schema::hasTable('speed_networking_participants')) {
            $eventIds = SpeedNetworkingParticipant::where('user_id', $userId)
                ->pluck('event_id')
                ->toArray();
        }

        if (Schema::hasTable('speed_networking_events')) {
            $query = SpeedNetworkingEvent::query()
                ->where(function ($q) use ($eventIds): void {
                    if (! empty($eventIds)) {
                        $q->whereIn('id', $eventIds);
                    }
                    $q->orWhere('status', SpeedNetworkingEvent::STATUS_COMPLETED)
                        ->orWhereNotNull('actual_end_time');
                })
                ->orderBy('actual_end_time', 'desc')
                ->orderBy('scheduled_start_time', 'desc')
                ->orderBy('created_at', 'desc');

            $paginated = $query->paginate($perPage);

            $mapped = $paginated->getCollection()->map(function (SpeedNetworkingEvent $event) use ($user): array {
                $totalJoined = $event->participants()->count();
                $summary = $this->getSessionSummary($event, $user);
                $peersMetCount = count($summary['interacted_peers'] ?? []);

                $durSec = max(60, (int) ($event->duration_seconds ?: $event->calculateDurationSeconds()));
                $durMin = (int) ceil($durSec / 60);
                $schedStart = $event->scheduled_start_time ?? $event->actual_start_time ?? Carbon::now();
                $schedEnd = $event->scheduled_end_time ?? $schedStart->copy()->addSeconds($durSec);

                return [
                    'id' => (string) $event->id,
                    'event_id' => (string) $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'banner_url' => $event->banner_url,
                    'status' => $event->status,
                    'duration_seconds' => $durSec,
                    'duration_minutes' => $durMin,
                    'duration' => $durMin,
                    'scheduled_start_time' => $schedStart->copy()->utc()->toISOString(),
                    'scheduled_end_time' => $schedEnd->copy()->utc()->toISOString(),
                    'end_time' => $schedEnd->copy()->utc()->toISOString(),
                    'actual_start_time' => $event->actual_start_time?->copy()->utc()->toISOString(),
                    'actual_end_time' => $event->actual_end_time?->copy()->utc()->toISOString(),
                    'round_duration_seconds' => (int) $event->round_duration_seconds,
                    'break_duration_seconds' => (int) $event->break_duration_seconds,
                    'total_rounds' => (int) $event->total_rounds,
                    'current_round' => (int) $event->current_round,
                    'total_joined' => $totalJoined,
                    'participants_count' => $totalJoined,
                    'rounds_completed' => (int) ($summary['rounds_completed'] ?? $peersMetCount),
                    'peers_met_count' => $peersMetCount,
                    'qualifying_peers_count' => (int) ($summary['qualifying_peers_count'] ?? 0),
                    'life_impact_earned' => (int) ($summary['life_impact_earned'] ?? 0),
                    'coins_earned' => (int) ($summary['coins_earned'] ?? 0),
                    'peers_met' => $summary['interacted_peers'] ?? [],
                    'ticket_price' => (float) $event->ticket_price,
                    'is_paid' => (bool) $event->is_paid,
                    'currency' => (string) $event->currency,
                    'summary' => $summary,
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
        }

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

    /**
     * Get completed Speed Networking history for Admin panel.
     *
     * @param  array<string, mixed>  $filters
     * @return array{data: array<int, mixed>, pagination: array<string, int>}
     */
    public function getAdminHistory(array $filters = [], int $perPage = 15): array
    {
        if (! Schema::hasTable('speed_networking_events')) {
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

        $query = SpeedNetworkingEvent::query()
            ->where(function ($q): void {
                $q->where('status', SpeedNetworkingEvent::STATUS_COMPLETED)
                    ->orWhereNotNull('actual_end_time');
            })
            ->orderBy('actual_end_time', 'desc')
            ->orderBy('scheduled_start_time', 'desc')
            ->orderBy('created_at', 'desc');

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $paginated = $query->paginate($perPage);

        $mapped = $paginated->getCollection()->map(function (SpeedNetworkingEvent $event): array {
            $totalJoined = $event->participants()->count();
            $totalMatches = $event->matches()->count();
            $completedMatches = $event->matches()->where('status', SpeedNetworkingMatch::STATUS_COMPLETED)->count();

            $durSec = max(60, (int) ($event->duration_seconds ?: $event->calculateDurationSeconds()));
            $durMin = (int) ceil($durSec / 60);
            $schedStart = $event->scheduled_start_time ?? $event->actual_start_time ?? Carbon::now();
            $schedEnd = $event->scheduled_end_time ?? $schedStart->copy()->addSeconds($durSec);

            return [
                'id' => (string) $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'banner_url' => $event->banner_url,
                'status' => $event->status,
                'duration_seconds' => $durSec,
                'duration_minutes' => $durMin,
                'duration' => $durMin,
                'scheduled_start_time' => $schedStart->copy()->utc()->toISOString(),
                'scheduled_end_time' => $schedEnd->copy()->utc()->toISOString(),
                'end_time' => $schedEnd->copy()->utc()->toISOString(),
                'actual_start_time' => $event->actual_start_time?->copy()->utc()->toISOString(),
                'actual_end_time' => $event->actual_end_time?->copy()->utc()->toISOString(),
                'round_duration_seconds' => (int) $event->round_duration_seconds,
                'break_duration_seconds' => (int) $event->break_duration_seconds,
                'total_rounds' => (int) $event->total_rounds,
                'rounds_conducted' => (int) $event->current_round,
                'total_joined' => $totalJoined,
                'participants_count' => $totalJoined,
                'total_matches' => $totalMatches,
                'completed_matches' => $completedMatches,
                'ticket_price' => (float) $event->ticket_price,
                'is_paid' => (bool) $event->is_paid,
                'currency' => (string) $event->currency,
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
    }

    /**
     * Get complete administrative summary of an event.
     *
     * @return array<string, mixed>
     */
    public function getAdminEventSummary(SpeedNetworkingEvent $event): array
    {
        $totalJoined = $event->participants()->count();
        $totalMatches = $event->matches()->count();
        $completedMatches = $event->matches()->where('status', SpeedNetworkingMatch::STATUS_COMPLETED)->count();
        $avgDuration = (int) ($event->matches()->where('status', SpeedNetworkingMatch::STATUS_COMPLETED)->avg('duration_seconds') ?? 0);

        $durSec = max(60, (int) ($event->duration_seconds ?: $event->calculateDurationSeconds()));
        $durMin = (int) ceil($durSec / 60);
        $schedStart = $event->scheduled_start_time ?? $event->actual_start_time ?? Carbon::now();
        $schedEnd = $event->scheduled_end_time ?? $schedStart->copy()->addSeconds($durSec);

        return [
            'event' => [
                'id' => (string) $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'banner_url' => $event->banner_url,
                'status' => $event->status,
                'duration_seconds' => $durSec,
                'duration_minutes' => $durMin,
                'duration' => $durMin,
                'scheduled_start_time' => $schedStart->copy()->utc()->toISOString(),
                'scheduled_end_time' => $schedEnd->copy()->utc()->toISOString(),
                'end_time' => $schedEnd->copy()->utc()->toISOString(),
                'actual_start_time' => $event->actual_start_time?->copy()->utc()->toISOString(),
                'actual_end_time' => $event->actual_end_time?->copy()->utc()->toISOString(),
                'round_duration_seconds' => (int) $event->round_duration_seconds,
                'break_duration_seconds' => (int) $event->break_duration_seconds,
                'total_rounds' => (int) $event->total_rounds,
                'current_round' => (int) $event->current_round,
            ],
            'statistics' => [
                'total_joined' => $totalJoined,
                'participants_count' => $totalJoined,
                'total_matches' => $totalMatches,
                'completed_matches' => $completedMatches,
                'average_talk_time_seconds' => $avgDuration,
            ],
        ];
    }

    /**
     * Send push/in-app notification to all registered attendees when the session goes live.
     */
    public function notifyAttendeesEventStarted(SpeedNetworkingEvent $event): void
    {
        try {
            $userIds = [];
            if (Schema::hasTable('speed_networking_participants')) {
                $userIds = SpeedNetworkingParticipant::where('event_id', $event->id)
                    ->pluck('user_id')
                    ->toArray();
            }

            try {
                if (Schema::hasTable('event_registrations')) {
                    $regUserQuery = EventRegistration::where('event_id', $event->id)
                        ->where('status', '!=', 'cancelled')
                        ->whereNull('deleted_at');
                    if (($event->ticket_price ?? 0) > 0) {
                        $regUserQuery->where(function ($q) {
                            $q->where('status', 'registered')->orWhere('payment_status', 'paid');
                        });
                    }
                    $regUserIds = $regUserQuery->pluck('user_id')->toArray();
                    $userIds = array_unique(array_merge($userIds, $regUserIds));
                }
            } catch (\Throwable) {
            }

            if (empty($userIds)) {
                return;
            }

            $users = User::whereIn('id', $userIds)->get();
            $title = '⚡ Speed Networking is Live!';
            $body = "{$event->title} has started! Join now to meet your first peer 1-on-1.";

            $payload = [
                'type' => 'speed_networking_live',
                'notification_type' => 'speed_networking_live',
                'event_id' => (string) $event->id,
                'action' => 'open_speed_networking',
                'screen' => 'speed_networking_lobby',
            ];

            $pushService = null;
            if (class_exists(PushNotificationService::class)) {
                try {
                    $pushService = app(PushNotificationService::class);
                } catch (\Throwable) {
                }
            }

            foreach ($users as $user) {
                if ($pushService) {
                    try {
                        $pushService->storeAndSend($user, $title, $body, $payload, $payload);
                    } catch (\Throwable $pe) {
                        Log::warning('[SpeedNetworkingService] Push failed for user '.$user->id.': '.$pe->getMessage());
                    }
                }
            }

            Log::info('[SpeedNetworkingService] Sent live notification to '.count($users).' attendees for event '.$event->id);
        } catch (\Throwable $e) {
            Log::error('[SpeedNetworkingService] notifyAttendeesEventStarted error: '.$e->getMessage());
        }
    }

    /**
     * Check for scheduled Speed Networking events whose start time has arrived,
     * auto-start them, generate initial pairings, and notify all registered attendees.
     *
     * @return array<int, SpeedNetworkingEvent>
     */
    public function checkAndAutoStartScheduledEvents(): array
    {
        if (! Schema::hasTable('speed_networking_events')) {
            return [];
        }

        $now = Carbon::now();

        // Sync scheduled_start_time and scheduled_end_time from base events table if out of sync
        $openCandidates = SpeedNetworkingEvent::query()
            ->whereNotIn('status', [
                SpeedNetworkingEvent::STATUS_COMPLETED,
                'ended',
                'cancelled',
                'canceled',
            ])
            ->whereNull('actual_end_time')
            ->get();

        if (Schema::hasTable('events') && $openCandidates->isNotEmpty()) {
            foreach ($openCandidates as $candidate) {
                try {
                    $baseEvent = Event::find($candidate->id)
                        ?: Event::where('title', $candidate->title)->latest()->first();
                    if ($baseEvent && $baseEvent->start_at && $baseEvent->end_at) {
                        $baseStart = Carbon::parse($baseEvent->start_at);
                        $baseEnd = Carbon::parse($baseEvent->end_at);
                        if ($baseEnd->greaterThan($baseStart)) {
                            $baseDurSec = max(60, (int) abs($baseStart->diffInSeconds($baseEnd, true)));
                            $needsSync = ! $candidate->scheduled_start_time
                                || ! $candidate->scheduled_end_time
                                || $candidate->scheduled_start_time->ne($baseStart)
                                || $candidate->scheduled_end_time->ne($baseEnd);

                            if ($needsSync) {
                                $meta = is_array($candidate->metadata) ? $candidate->metadata : [];
                                $meta['duration_seconds'] = $baseDurSec;
                                $meta['duration_minutes'] = (int) ceil($baseDurSec / 60);
                                $candidate->scheduled_start_time = $baseStart;
                                $candidate->scheduled_end_time = $baseEnd;
                                if (Schema::hasColumn('speed_networking_events', 'metadata')) {
                                    $candidate->metadata = $meta;
                                }
                                $candidate->save();
                            }
                        }
                    }
                } catch (\Throwable) {
                }
            }
        }

        // Auto-end any non-completed Speed Networking events whose scheduled_end_time has been reached

        foreach ($openCandidates as $candidate) {
            $schedEnd = $candidate->scheduled_end_time;
            if ($schedEnd && $now->greaterThanOrEqualTo($schedEnd)) {
                try {
                    $this->endEvent($candidate);
                    Log::info('[SpeedNetworkingService] Auto-ended expired event '.$candidate->id.' at scheduled_end_time '.$schedEnd);
                } catch (\Throwable $th) {
                    Log::error('[SpeedNetworkingService] Failed to auto-end event '.$candidate->id.': '.$th->getMessage());
                }
            }
        }

        $eventsToStart = SpeedNetworkingEvent::query()
            ->whereIn('status', [
                SpeedNetworkingEvent::STATUS_SCHEDULED,
                SpeedNetworkingEvent::STATUS_LOBBY_OPEN,
                SpeedNetworkingEvent::STATUS_DRAFT,
            ])
            ->whereNotNull('scheduled_start_time')
            ->where('scheduled_start_time', '<=', $now)
            ->whereNull('actual_end_time')
            ->get();

        $started = [];
        foreach ($eventsToStart as $event) {
            $schedEnd = $event->scheduled_end_time;
            if ($schedEnd && $now->greaterThanOrEqualTo($schedEnd)) {
                continue;
            }
            try {
                $startedEvent = $this->startEvent($event);
                $started[] = $startedEvent;
                Log::info('[SpeedNetworkingService] Auto-started event '.$event->id.' at scheduled time '.$event->scheduled_start_time);
            } catch (\Throwable $th) {
                Log::error('[SpeedNetworkingService] Failed to auto-start event '.$event->id.': '.$th->getMessage());
            }
        }

        return $started;
    }
}
