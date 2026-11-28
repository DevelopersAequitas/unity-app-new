<?php

declare(strict_types=1);

namespace App\Services\SpeedNetworking;

use App\Models\CoinsLedger;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\LifeImpactHistory;
use App\Models\P2pMeeting;
use App\Models\SpeedNetworking\SpeedNetworkingBlockedPeer;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\SpeedNetworking\SpeedNetworkingEventSkip;
use App\Models\SpeedNetworking\SpeedNetworkingMatch;
use App\Models\SpeedNetworking\SpeedNetworkingParticipant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpeedCollaborationAnalyticsService
{
    private const WAITING_STATUSES = [
        SpeedNetworkingParticipant::STATUS_SEARCHING,
        SpeedNetworkingParticipant::STATUS_WAITING,
        SpeedNetworkingParticipant::STATUS_NEXT_PEER_REQUIRED,
        SpeedNetworkingParticipant::STATUS_IDLE,
        SpeedNetworkingParticipant::STATUS_LOBBY,
    ];

    /**
     * Resolve either a SpeedNetworkingEvent or a fallback base Event.
     *
     * @return array{type: 'sn'|'base', sn_event: SpeedNetworkingEvent|null, base_event: Event|null, id: string}
     */
    public function resolveEvent(string $id): array
    {
        $snEvent = Schema::hasTable('speed_networking_events')
            ? SpeedNetworkingEvent::with('circle')->find($id)
            : null;
        $baseEvent = Schema::hasTable('events')
            ? Event::with('circle')->find($id)
            : null;

        if (! $snEvent && ! $baseEvent) {
            abort(404, 'Speed Collaboration event not found.');
        }

        if ($snEvent && ! $baseEvent && Schema::hasTable('events')) {
            $baseEvent = Event::with('circle')->where('title', $snEvent->title)->first();
        }

        return [
            'type' => $snEvent ? 'sn' : 'base',
            'sn_event' => $snEvent,
            'base_event' => $baseEvent,
            'id' => (string) ($snEvent?->id ?? $baseEvent?->id),
        ];
    }

    /**
     * Feature One: List all Speed Collaboration events with batch-aggregated analytics.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getEventsAnalytics(array $filters = []): array
    {
        $allEvents = $this->collectAllSpeedNetworkingEvents();

        if (! empty($filters['search'])) {
            $needle = mb_strtolower(trim((string) $filters['search']));
            $allEvents = $allEvents->filter(function (array $ev) use ($needle): bool {
                return str_contains(mb_strtolower($ev['title']), $needle)
                    || str_contains(mb_strtolower((string) ($ev['description'] ?? '')), $needle)
                    || str_contains(mb_strtolower((string) ($ev['circle_name'] ?? '')), $needle);
            })->values();
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $statusFilter = mb_strtolower((string) $filters['status']);
            $allEvents = $allEvents->filter(function (array $ev) use ($statusFilter): bool {
                if ($statusFilter === 'live') {
                    return in_array($ev['status_info']['display_status'], ['live', 'paused'], true);
                }

                return $ev['status_info']['display_status'] === $statusFilter
                    || mb_strtolower($ev['raw_status']) === $statusFilter;
            })->values();
        }

        if (! empty($filters['date_from'])) {
            $from = Carbon::parse((string) $filters['date_from'])->startOfDay();
            $allEvents = $allEvents->filter(function (array $ev) use ($from): bool {
                $dt = $ev['sort_timestamp'];

                return $dt !== null && $dt->greaterThanOrEqualTo($from);
            })->values();
        }

        if (! empty($filters['date_to'])) {
            $to = Carbon::parse((string) $filters['date_to'])->endOfDay();
            $allEvents = $allEvents->filter(function (array $ev) use ($to): bool {
                $dt = $ev['sort_timestamp'];

                return $dt !== null && $dt->lessThanOrEqualTo($to);
            })->values();
        }

        $eventIds = $allEvents->pluck('id')->all();
        $batchStats = $this->computeBatchEventStats($eventIds);

        $enriched = $allEvents->map(function (array $ev) use ($batchStats): array {
            $stats = $batchStats[$ev['id']] ?? $this->emptyEventListStats();

            return [
                'id' => $ev['id'],
                'title' => $ev['title'],
                'description' => $ev['description'],
                'banner_url' => $ev['banner_url'],
                'circle_id' => $ev['circle_id'],
                'circle_name' => $ev['circle_name'],
                'event_type' => $ev['event_type'],
                'status' => $ev['raw_status'],
                'display_status' => $ev['status_info']['display_status'],
                'status_label' => $ev['status_info']['label'],
                'scheduled_start_time' => $ev['scheduled_start_time'],
                'scheduled_end_time' => $ev['scheduled_end_time'],
                'actual_start_time' => $ev['actual_start_time'],
                'actual_end_time' => $ev['actual_end_time'],
                'round_duration_seconds' => $ev['round_duration_seconds'],
                'break_duration_seconds' => $ev['break_duration_seconds'],
                'preview_duration_seconds' => $ev['preview_duration_seconds'],
                'max_skip_limit' => $ev['max_skip_limit'],
                'total_rounds' => $ev['total_rounds'],
                'current_round' => $ev['current_round'],
                'ticket_price' => $ev['ticket_price'],
                'is_paid' => $ev['is_paid'],
                'registered_count' => $stats['registered_count'],
                'attended_count' => $stats['attended_count'],
                'canceled_count' => $stats['canceled_count'],
                'attendance_percentage' => $stats['attendance_percentage'],
                'connections_made' => $stats['connections_made'],
                'completed_connections' => $stats['completed_connections'],
                'qualifying_p2p_connections' => $stats['qualifying_p2p_connections'],
                'unique_peers_connected' => $stats['unique_peers_connected'],
                'unique_pairs_count' => $stats['unique_pairs_count'],
                'total_coins_earned' => $stats['total_coins_earned'],
                'total_life_impact_generated' => $stats['total_life_impact_generated'],
                'total_skips_count' => $stats['total_skips_count'],
                'total_participants_count' => $stats['attended_count'],
                'active_tables_count' => $stats['connections_made'],
                'avg_connections_per_attendee' => $stats['attended_count'] > 0
                    ? round($stats['connections_made'] / $stats['attended_count'], 2)
                    : 0.0,
                'participation_rate_percent' => $stats['attended_count'] > 0
                    ? round(($stats['unique_peers_connected'] / $stats['attended_count']) * 100, 1)
                    : 0.0,
            ];
        })->values();

        $summary = [
            'total_events' => $enriched->count(),
            'live_events' => $enriched->whereIn('display_status', ['live', 'paused'])->count(),
            'upcoming_events' => $enriched->where('display_status', 'upcoming')->count(),
            'completed_events' => $enriched->where('display_status', 'completed')->count(),
            'canceled_events' => $enriched->where('display_status', 'canceled')->count(),
            'total_registered_peers' => (int) $enriched->sum('registered_count'),
            'total_attended_peers' => (int) $enriched->sum('attended_count'),
            'total_connections_made' => (int) $enriched->sum('connections_made'),
            'total_coins_generated' => (int) $enriched->sum('total_coins_earned'),
            'total_life_impact_generated' => (int) $enriched->sum('total_life_impact_generated'),
        ];

        return [
            'summary' => $summary,
            'events' => $enriched->all(),
        ];
    }

    /**
     * Feature Eleven: Multi-event comparison metrics.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getEventsComparison(array $filters = []): array
    {
        $analytics = $this->getEventsAnalytics($filters);
        $events = collect($analytics['events']);

        $requestedIds = $filters['event_ids'] ?? [];
        if (is_array($requestedIds) && $requestedIds !== []) {
            $idSet = array_flip(array_map('strval', $requestedIds));
            $events = $events->filter(static fn (array $ev): bool => isset($idSet[$ev['id']]))->values();
        } else {
            $events = $events->take(10)->values();
        }

        return [
            'events' => $events->map(static function (array $ev): array {
                return [
                    'id' => $ev['id'],
                    'title' => $ev['title'],
                    'display_status' => $ev['display_status'],
                    'status_label' => $ev['status_label'],
                    'scheduled_start_time' => $ev['scheduled_start_time'],
                    'registered_count' => $ev['registered_count'],
                    'attended_count' => $ev['attended_count'],
                    'attendance_percentage' => $ev['attendance_percentage'],
                    'connections_made' => $ev['connections_made'],
                    'unique_peers_connected' => $ev['unique_peers_connected'],
                    'avg_connections_per_attendee' => $ev['avg_connections_per_attendee'],
                    'participation_rate_percent' => $ev['participation_rate_percent'],
                    'total_coins_earned' => $ev['total_coins_earned'],
                    'total_life_impact_generated' => $ev['total_life_impact_generated'],
                ];
            })->all(),
        ];
    }

    /**
     * Feature Two: Event Overview Dashboard payload.
     *
     * @return array<string, mixed>
     */
    public function getEventOverview(string $eventId): array
    {
        $dataset = $this->buildEventDataset($eventId);
        $connSummary = $dataset['connections_summary'];
        $attendanceSummary = $dataset['attendance_summary'];

        $recentRegistrations = collect($dataset['roster'])
            ->filter(static fn (array $row): bool => $row['is_registered'] && ! $row['is_canceled'])
            ->sortByDesc(static fn (array $row): string => (string) ($row['registered_at'] ?? $row['joined_at'] ?? ''))
            ->take(10)
            ->map(static fn (array $row): array => [
                'user_id' => $row['user_id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'company' => $row['company'],
                'avatar_url' => $row['avatar_url'],
                'registered_at' => $row['registered_at'] ?? $row['joined_at'],
                'attended' => $row['attended'],
                'connections_made' => $row['connections_made'],
                'coins_earned' => $row['coins_earned'],
                'life_impact_earned' => $row['life_impact_earned'],
            ])
            ->values()
            ->all();

        $topActivePeers = collect($dataset['roster'])
            ->filter(static fn (array $row): bool => $row['connections_made'] > 0)
            ->sortByDesc('connections_made')
            ->take(5)
            ->map(static fn (array $row): array => [
                'user_id' => $row['user_id'],
                'name' => $row['name'],
                'company' => $row['company'],
                'avatar_url' => $row['avatar_url'],
                'connections_made' => $row['connections_made'],
                'unique_peers_met' => $row['unique_peers_met'],
                'coins_earned' => $row['coins_earned'],
                'life_impact_earned' => $row['life_impact_earned'],
                'total_duration_seconds' => $row['total_duration_seconds'],
            ])
            ->values()
            ->all();

        return [
            'event' => $dataset['event'],
            'kpis' => [
                'registered_peers' => $attendanceSummary['registered_count'],
                'attended_peers' => $attendanceSummary['attended_count'],
                'not_attended_peers' => $attendanceSummary['not_attended_count'],
                'canceled_registrations' => $attendanceSummary['canceled_count'],
                'attendance_percentage' => $attendanceSummary['attendance_percentage'],
                'total_connections_made' => $connSummary['valid_connections_count'],
                'total_connection_sessions' => $connSummary['total_match_records'],
                'completed_meetings' => $connSummary['completed_connections_count'],
                'qualifying_p2p_connections' => $connSummary['qualifying_p2p_connections_count'],
                'active_connections_count' => $connSummary['active_in_call_count'] + $connSummary['active_preview_count'],
                'unique_pairs_connected' => $connSummary['unique_pairs_count'],
                'unique_peers_connected' => $connSummary['unique_peers_connected_count'],
                'average_connections_per_attendee' => $connSummary['avg_connections_per_attendee'],
                'average_connection_duration_seconds' => $connSummary['avg_duration_seconds'],
                'total_coins_earned' => $dataset['rewards']['coins_total'],
                'total_life_impact_generated' => $dataset['rewards']['life_impact_total'],
                'peers_rewarded_coins' => $dataset['rewards']['peers_rewarded_coins_count'],
                'peers_generated_impact' => $dataset['rewards']['peers_generated_impact_count'],
                'total_skips_count' => $dataset['skips_total_count'],
                'total_blocks_count' => $dataset['blocks_total_count'],
            ],
            'attendance_breakdown' => $attendanceSummary,
            'recent_registrations' => $recentRegistrations,
            'top_active_peers' => $topActivePeers,
            'data_notes' => $dataset['data_notes'],
        ];
    }

    /**
     * Feature Three: Live Event Monitor Snapshot & Chronological Activity Feed.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getLiveMonitorSnapshot(string $eventId, array $filters = []): array
    {
        $dataset = $this->buildEventDataset($eventId);
        $now = Carbon::now();
        $event = $dataset['event'];
        $snEvent = $dataset['sn_event'];

        $remainingSeconds = 0;
        if ($snEvent && $snEvent->status === SpeedNetworkingEvent::STATUS_IN_PROGRESS) {
            $roundDur = max(1, (int) $snEvent->round_duration_seconds);
            $activeEndsAt = SpeedNetworkingMatch::where('event_id', $snEvent->id)
                ->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)
                ->whereNotNull('call_expires_at')
                ->max('call_expires_at');

            if ($activeEndsAt) {
                $remainingSeconds = max(0, (int) $now->diffInSeconds(Carbon::parse($activeEndsAt), false));
            } elseif ($snEvent->actual_start_time) {
                $elapsed = max(0, (int) $snEvent->actual_start_time->diffInSeconds($now, false));
                $remainingSeconds = max(0, $roundDur - ($elapsed % $roundDur));
            } else {
                $remainingSeconds = $roundDur;
            }
        } elseif ($snEvent) {
            $remainingSeconds = (int) $snEvent->round_duration_seconds;
        }

        $elapsedSeconds = 0;
        $elapsedReference = $snEvent?->actual_start_time ?? ($event['scheduled_start_time'] ? Carbon::parse($event['scheduled_start_time']) : null);
        if ($elapsedReference && in_array($event['display_status'], ['live', 'paused'], true)) {
            $elapsedSeconds = max(0, (int) $elapsedReference->diffInSeconds($now, false));
        } elseif ($snEvent?->actual_start_time && $snEvent?->actual_end_time) {
            $elapsedSeconds = max(0, (int) $snEvent->actual_start_time->diffInSeconds($snEvent->actual_end_time, false));
        }

        // Active connections (both in_call and preview)
        $activeConnections = collect($dataset['matches_enriched'])
            ->filter(static fn (array $m): bool => ! $m['is_break_round'] && $m['status'] === SpeedNetworkingMatch::STATUS_ACTIVE)
            ->sortBy('table_number')
            ->map(static function (array $m) use ($event): array {
                return [
                    'match_id' => $m['match_id'],
                    'event_name' => $event['title'],
                    'round_number' => $m['round_number'],
                    'table_number' => $m['table_number'],
                    'channel_id' => $m['channel_id'],
                    'match_status' => $m['match_status'],
                    'human_status' => $m['match_status'] === SpeedNetworkingMatch::MATCH_STATUS_IN_CALL
                        ? 'Connected (In Call)'
                        : 'Connecting (15s Preview)',
                    'peer_a' => $m['user_a'],
                    'peer_b' => $m['user_b'],
                    'user_a_decision' => $m['user_a_decision'],
                    'user_b_decision' => $m['user_b_decision'],
                    'started_at' => $m['started_at'],
                    'duration_seconds' => $m['duration_seconds'],
                    'remaining_seconds' => $m['remaining_seconds'],
                ];
            })
            ->values()
            ->all();

        // Waiting participants
        $waitingParticipants = collect($dataset['roster'])
            ->filter(static fn (array $row): bool => $row['is_waiting_live'])
            ->sortByDesc('current_waiting_seconds')
            ->map(static fn (array $row): array => [
                'user_id' => $row['user_id'],
                'name' => $row['name'],
                'company' => $row['company'],
                'email' => $row['email'],
                'avatar_url' => $row['avatar_url'],
                'waiting_since' => $row['waiting_since'],
                'waiting_duration_seconds' => $row['current_waiting_seconds'],
                'current_round' => $event['current_round'],
                'completed_connections' => $row['completed_connections'],
                'raw_status' => $row['raw_live_status'],
                'human_status' => $row['human_live_status'],
                'is_online' => $row['is_online'],
            ])
            ->values()
            ->all();

        $activeCutoff = $now->copy()->subSeconds(60);
        $activeNowCount = collect($dataset['roster'])
            ->filter(static function (array $row) use ($activeCutoff): bool {
                if (! empty($row['last_heartbeat_at']) && Carbon::parse($row['last_heartbeat_at'])->greaterThanOrEqualTo($activeCutoff)) {
                    return true;
                }

                return in_array($row['raw_live_status'], ['in_call', 'preview', 'accepted'], true);
            })
            ->count();

        $connectedPeersCount = collect($activeConnections)
            ->where('match_status', SpeedNetworkingMatch::MATCH_STATUS_IN_CALL)
            ->count() * 2;
        $previewPeersCount = collect($activeConnections)
            ->where('match_status', SpeedNetworkingMatch::MATCH_STATUS_PREVIEW)
            ->count() * 2;

        $category = mb_strtolower((string) ($filters['category'] ?? 'all'));
        $activityFeed = $this->buildActivityFeed($dataset);
        if ($category !== '' && $category !== 'all') {
            $activityFeed = array_values(array_filter(
                $activityFeed,
                static fn (array $item): bool => $item['category'] === $category
            ));
        }

        return [
            'event' => $event,
            'server_time' => $now->copy()->utc()->toISOString(),
            'elapsed_seconds' => $elapsedSeconds,
            'remaining_seconds' => $remainingSeconds,
            'kpis' => [
                'active_attendees_count' => $activeNowCount,
                'connected_peers_count' => $connectedPeersCount,
                'in_preview_peers_count' => $previewPeersCount,
                'waiting_peers_count' => count($waitingParticipants),
                'active_connection_sessions' => count($activeConnections),
                'completed_connections_count' => $dataset['connections_summary']['completed_connections_count'],
                'qualifying_p2p_connections_count' => $dataset['connections_summary']['qualifying_p2p_connections_count'],
                'skips_count' => $dataset['skips_total_count'],
                'skipped_pairs_count' => count($dataset['skips']),
                'blocks_count' => $dataset['blocks_total_count'],
                'coins_generated_so_far' => $dataset['rewards']['coins_total'],
                'life_impact_generated_so_far' => $dataset['rewards']['life_impact_total'],
            ],
            'active_connections' => $activeConnections,
            'waiting_participants' => $waitingParticipants,
            'skips' => $dataset['skips'],
            'blocks' => $dataset['blocks'],
            'activity_feed' => array_slice($activityFeed, 0, 150),
            'data_notes' => $dataset['data_notes'],
        ];
    }

    /**
     * Feature Four: Event Attendee Management & Search/Filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getEventAttendees(string $eventId, array $filters = []): array
    {
        $dataset = $this->buildEventDataset($eventId);
        $roster = collect($dataset['roster']);

        $filterCounts = [
            'all' => $roster->count(),
            'registered' => $roster->where('is_registered', true)->where('is_canceled', false)->count(),
            'attended' => $roster->where('attended', true)->count(),
            'not_attended' => $roster->where('is_registered', true)->where('is_canceled', false)->where('attended', false)->count(),
            'canceled' => $roster->where('is_canceled', true)->count(),
            'connected' => $roster->where('is_currently_connected', true)->count(),
            'waiting' => $roster->where('is_waiting_live', true)->count(),
            'skipped' => $roster->filter(static fn (array $r): bool => $r['skips_given'] > 0 || $r['skips_received'] > 0)->count(),
            'completed' => $roster->filter(static fn (array $r): bool => $r['completed_connections'] > 0)->count(),
        ];

        $filtered = $roster;

        if (! empty($filters['search'])) {
            $q = mb_strtolower(trim((string) $filters['search']));
            $filtered = $filtered->filter(static function (array $row) use ($q): bool {
                return str_contains(mb_strtolower($row['name']), $q)
                    || str_contains(mb_strtolower($row['email']), $q)
                    || str_contains(mb_strtolower($row['company']), $q)
                    || str_contains(mb_strtolower($row['designation']), $q);
            });
        }

        $statusFilter = mb_strtolower((string) ($filters['status'] ?? $filters['live_status'] ?? 'all'));
        if ($statusFilter !== '' && $statusFilter !== 'all') {
            $filtered = match ($statusFilter) {
                'registered' => $filtered->where('is_registered', true)->where('is_canceled', false),
                'attended' => $filtered->where('attended', true),
                'not_attended' => $filtered->where('is_registered', true)->where('is_canceled', false)->where('attended', false),
                'canceled', 'cancelled' => $filtered->where('is_canceled', true),
                'connected', 'in_call' => $filtered->where('is_currently_connected', true),
                'waiting' => $filtered->where('is_waiting_live', true),
                'skipped' => $filtered->filter(static fn (array $r): bool => $r['skips_given'] > 0 || $r['skips_received'] > 0),
                'completed' => $filtered->filter(static fn (array $r): bool => $r['completed_connections'] > 0),
                default => $filtered->filter(static fn (array $r): bool => mb_strtolower($r['raw_live_status']) === $statusFilter),
            };
        }

        $sortBy = (string) ($filters['sort_by'] ?? 'connections_made');
        $sortDir = mb_strtolower((string) ($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSortFields = [
            'name',
            'company',
            'connections_made',
            'unique_peers_met',
            'coins_earned',
            'life_impact_earned',
            'skips_given',
            'total_duration_seconds',
            'attended',
        ];
        if (! in_array($sortBy, $allowedSortFields, true)) {
            $sortBy = 'connections_made';
        }

        $sorted = $sortDir === 'asc'
            ? $filtered->sortBy($sortBy)->values()
            : $filtered->sortByDesc($sortBy)->values();

        $perPage = max(1, min(200, (int) ($filters['per_page'] ?? 50)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $total = $sorted->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $items = $sorted->forPage($page, $perPage)->values()->all();

        return [
            'event' => $dataset['event'],
            'filter_counts' => $filterCounts,
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * Feature Eight: Individual Peer Event Analytics.
     *
     * @return array<string, mixed>
     */
    public function getPeerEventAnalytics(string $eventId, string $userId): array
    {
        $dataset = $this->buildEventDataset($eventId);
        $peerRow = collect($dataset['roster'])->firstWhere('user_id', $userId);

        if (! $peerRow) {
            $user = User::find($userId);
            if (! $user) {
                abort(404, 'Peer not found for this event.');
            }
            abort(404, 'This peer has no registration or participation record in this event.');
        }

        $peerConnections = collect($dataset['matches_enriched'])
            ->filter(static function (array $m) use ($userId): bool {
                return ($m['user_a_id'] === $userId || $m['user_b_id'] === $userId) && ! $m['is_break_round'];
            })
            ->sortBy([['round_number', 'asc'], ['started_at', 'asc']])
            ->map(function (array $m) use ($userId, $dataset): array {
                $isA = $m['user_a_id'] === $userId;
                $partner = $isA ? $m['user_b'] : $m['user_a'];
                $partnerId = (string) ($partner['id'] ?? ($isA ? $m['user_b_id'] : $m['user_a_id']));

                $coinsForPeerInMatch = (int) collect($dataset['rewards']['coin_transactions'])
                    ->where('match_id', $m['match_id'])
                    ->where('user_id', $userId)
                    ->sum('amount');

                $impactForPeerInMatch = (int) collect($dataset['rewards']['impact_transactions'])
                    ->where('match_id', $m['match_id'])
                    ->where('user_id', $userId)
                    ->sum('impact_value');

                return [
                    'match_id' => $m['match_id'],
                    'round_number' => $m['round_number'],
                    'table_number' => $m['table_number'],
                    'partner' => $partner,
                    'partner_id' => $partnerId,
                    'partner_name' => $partner['name'] ?? 'Peer',
                    'partner_company' => $partner['company'] ?? '',
                    'started_at' => $m['started_at'],
                    'ended_at' => $m['ended_at'],
                    'duration_seconds' => $m['duration_seconds'],
                    'status' => $m['match_status'],
                    'human_status' => $m['human_status'],
                    'qualifying_p2p' => $m['qualifying_p2p'],
                    'coins_earned' => $coinsForPeerInMatch,
                    'life_impact_earned' => $impactForPeerInMatch,
                    'p2p_meeting_id' => $m['p2p_meeting_id'],
                    'notes' => $m['notes'],
                ];
            })
            ->values()
            ->all();

        $skipsActivity = collect($dataset['skips'])
            ->filter(static fn (array $s): bool => $s['user']['id'] === $userId || $s['skipped_peer']['id'] === $userId)
            ->values()
            ->all();

        $peerTimeline = array_values(array_filter(
            $this->buildActivityFeed($dataset),
            static fn (array $item): bool => in_array($userId, $item['user_ids'], true)
        ));

        $avgMinutes = round($peerRow['avg_duration_seconds'] / 60, 1);
        $highlights = [
            $peerRow['attended'] ? 'Attended event' : 'Did not attend event',
            sprintf('%d connection%s completed', $peerRow['completed_connections'], $peerRow['completed_connections'] === 1 ? '' : 's'),
            sprintf('%d unique peer%s met', $peerRow['unique_peers_met'], $peerRow['unique_peers_met'] === 1 ? '' : 's'),
            sprintf('%s coins earned in this event', number_format($peerRow['coins_earned'])),
            sprintf('%d Life Impact generated in this event', $peerRow['life_impact_earned']),
            sprintf('%d skip%s recorded', $peerRow['skips_given'], $peerRow['skips_given'] === 1 ? '' : 's'),
            sprintf('Average connection duration: %.1f min', $avgMinutes),
        ];

        return [
            'event' => $dataset['event'],
            'peer' => $peerRow,
            'highlights' => $highlights,
            'connections' => $peerConnections,
            'skips_activity' => $skipsActivity,
            'coin_transactions' => collect($dataset['rewards']['coin_transactions'])->where('user_id', $userId)->values()->all(),
            'impact_transactions' => collect($dataset['rewards']['impact_transactions'])->where('user_id', $userId)->values()->all(),
            'timeline' => $peerTimeline,
        ];
    }

    /**
     * Feature Five: Who Connected With Whom? + Live Meets Matrix + Unique Peer Analysis.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getEventConnections(string $eventId, array $filters = []): array
    {
        $dataset = $this->buildEventDataset($eventId);
        $matches = collect($dataset['matches_enriched']);

        // Meetaway-style Live Meets Matrix: each participating peer on left, ordered round meets on right
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $liveMeetsMatrix = collect($dataset['roster'])
            ->filter(static fn (array $row): bool => $row['attended'] || $row['connections_made'] > 0)
            ->map(function (array $row) use ($matches): array {
                $userId = $row['user_id'];
                $peerMatches = $matches->filter(static fn (array $m): bool => $m['user_a_id'] === $userId || $m['user_b_id'] === $userId)
                    ->sortBy([['round_number', 'asc'], ['started_at', 'asc']])
                    ->map(static function (array $m) use ($userId): array {
                        if ($m['is_break_round']) {
                            return [
                                'match_id' => $m['match_id'],
                                'round_number' => $m['round_number'],
                                'is_break' => true,
                                'partner_id' => null,
                                'partner_name' => 'Break',
                                'partner_company' => '',
                                'status' => $m['match_status'],
                                'human_status' => 'Break',
                                'duration_seconds' => $m['duration_seconds'],
                            ];
                        }
                        $isA = $m['user_a_id'] === $userId;
                        $partner = $isA ? $m['user_b'] : $m['user_a'];

                        return [
                            'match_id' => $m['match_id'],
                            'round_number' => $m['round_number'],
                            'is_break' => false,
                            'partner_id' => $partner['id'] ?? null,
                            'partner_name' => $partner['name'] ?? 'Peer',
                            'partner_company' => $partner['company'] ?? '',
                            'status' => $m['match_status'],
                            'human_status' => $m['human_status'],
                            'duration_seconds' => $m['duration_seconds'],
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'user_id' => $row['user_id'],
                    'name' => $row['name'],
                    'company' => $row['company'],
                    'designation' => $row['designation'],
                    'avatar_url' => $row['avatar_url'],
                    'connections_count' => $row['connections_made'],
                    'unique_peers_count' => $row['unique_peers_met'],
                    'meets' => $peerMatches,
                ];
            })
            ->filter(static function (array $entry) use ($search): bool {
                if ($search === '') {
                    return true;
                }
                if (str_contains(mb_strtolower($entry['name']), $search) || str_contains(mb_strtolower($entry['company']), $search)) {
                    return true;
                }
                foreach ($entry['meets'] as $meet) {
                    if (str_contains(mb_strtolower((string) $meet['partner_name']), $search)) {
                        return true;
                    }
                }

                return false;
            })
            ->filter(static fn (array $entry): bool => count($entry['meets']) > 0)
            ->sortBy('name')
            ->values()
            ->all();

        // Detailed connections table (excluding solo break rows from Who Met Whom table)
        $filteredConnections = $matches->where('is_break_round', false);

        if ($search !== '') {
            $filteredConnections = $filteredConnections->filter(static function (array $m) use ($search): bool {
                return str_contains(mb_strtolower((string) ($m['user_a']['name'] ?? '')), $search)
                    || str_contains(mb_strtolower((string) ($m['user_a']['company'] ?? '')), $search)
                    || str_contains(mb_strtolower((string) ($m['user_b']['name'] ?? '')), $search)
                    || str_contains(mb_strtolower((string) ($m['user_b']['company'] ?? '')), $search)
                    || str_contains(mb_strtolower((string) $m['human_status']), $search);
            });
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $st = mb_strtolower((string) $filters['status']);
            $filteredConnections = $filteredConnections->filter(static function (array $m) use ($st): bool {
                if ($st === 'valid') {
                    return $m['is_valid_connection'];
                }

                return mb_strtolower($m['match_status']) === $st || mb_strtolower($m['status']) === $st;
            });
        }

        if (! empty($filters['round_number'])) {
            $round = (int) $filters['round_number'];
            $filteredConnections = $filteredConnections->where('round_number', $round);
        }

        if (! empty($filters['peer_id'])) {
            $pid = (string) $filters['peer_id'];
            $filteredConnections = $filteredConnections->filter(
                static fn (array $m): bool => $m['user_a_id'] === $pid || $m['user_b_id'] === $pid
            );
        }

        if (! empty($filters['qualifying_only'])) {
            $filteredConnections = $filteredConnections->where('qualifying_p2p', true);
        }

        $sortedConnections = $filteredConnections
            ->sortByDesc(static fn (array $m): string => sprintf('%04d-%s', $m['round_number'], (string) ($m['started_at'] ?? '')))
            ->values();

        $perPage = max(1, min(200, (int) ($filters['per_page'] ?? 100)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $total = $sortedConnections->count();
        $lastPage = max(1, (int) ceil($total / $perPage));

        $mostActivePeers = collect($dataset['roster'])
            ->where('connections_made', '>', 0)
            ->sortByDesc('connections_made')
            ->take(5)
            ->map(static fn (array $r): array => [
                'user_id' => $r['user_id'],
                'name' => $r['name'],
                'company' => $r['company'],
                'connections_made' => $r['connections_made'],
                'unique_peers_met' => $r['unique_peers_met'],
            ])
            ->values()
            ->all();

        $zeroConnectionAttendees = collect($dataset['roster'])
            ->where('attended', true)
            ->where('connections_made', 0)
            ->map(static fn (array $r): array => [
                'user_id' => $r['user_id'],
                'name' => $r['name'],
                'company' => $r['company'],
                'email' => $r['email'],
                'skips_given' => $r['skips_given'],
            ])
            ->values()
            ->all();

        $repeatedPairs = collect($dataset['unique_pair_sessions'])
            ->filter(static fn (array $p): bool => $p['session_count'] > 1)
            ->sortByDesc('session_count')
            ->values()
            ->all();

        return [
            'event' => $dataset['event'],
            'unique_analysis' => array_merge($dataset['connections_summary'], [
                'most_active_peers' => $mostActivePeers,
                'zero_connection_attendees' => $zeroConnectionAttendees,
                'zero_connection_attendees_count' => count($zeroConnectionAttendees),
                'repeated_pairs' => $repeatedPairs,
                'repeated_pairs_count' => count($repeatedPairs),
            ]),
            'live_meets_matrix' => $liveMeetsMatrix,
            'connections' => $sortedConnections->forPage($page, $perPage)->values()->all(),
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * Features Six & Seven: Event-Wise Coin Analytics & Life Impact Analytics.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getEventRewardsAnalytics(string $eventId, array $filters = []): array
    {
        $dataset = $this->buildEventDataset($eventId);
        $attendedCount = max(0, $dataset['attendance_summary']['attended_count']);
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $peerIdFilter = ! empty($filters['peer_id']) ? (string) $filters['peer_id'] : null;

        $rosterCollection = collect($dataset['roster']);

        // --- Coins Analytics ---
        $coinEarners = $rosterCollection->where('coins_earned', '>', 0)->sortByDesc('coins_earned')->values();
        $coinTotal = $dataset['rewards']['coins_total'];
        $highestCoinEarner = $coinEarners->first();
        $lowestCoinEarner = $coinEarners->last();

        $coinPerPeer = $rosterCollection
            ->filter(static function (array $r) use ($search, $peerIdFilter): bool {
                if ($peerIdFilter !== null && $r['user_id'] !== $peerIdFilter) {
                    return false;
                }
                if ($search !== '') {
                    return str_contains(mb_strtolower($r['name']), $search)
                        || str_contains(mb_strtolower($r['company']), $search)
                        || str_contains(mb_strtolower($r['email']), $search);
                }

                return $r['attended'] || $r['coins_earned'] > 0;
            })
            ->sortByDesc('coins_earned')
            ->map(static fn (array $r): array => [
                'user_id' => $r['user_id'],
                'name' => $r['name'],
                'company' => $r['company'],
                'email' => $r['email'],
                'avatar_url' => $r['avatar_url'],
                'connections_made' => $r['connections_made'],
                'qualifying_p2p_count' => $r['qualifying_p2p_count'],
                'coins_earned_during_event' => $r['coins_earned'],
                'transactions_count' => $r['coin_transactions_count'],
                'current_balance_informational' => $r['current_coins_balance'],
            ])
            ->values()
            ->all();

        $coinTransactions = collect($dataset['rewards']['coin_transactions'])
            ->filter(static function (array $tx) use ($search, $peerIdFilter): bool {
                if ($peerIdFilter !== null && $tx['user_id'] !== $peerIdFilter) {
                    return false;
                }
                if ($search !== '') {
                    return str_contains(mb_strtolower($tx['peer_name']), $search)
                        || str_contains(mb_strtolower((string) ($tx['partner_name'] ?? '')), $search)
                        || str_contains(mb_strtolower((string) ($tx['reference'] ?? '')), $search)
                        || str_contains(mb_strtolower((string) ($tx['remark'] ?? '')), $search);
                }

                return true;
            })
            ->values()
            ->all();

        // --- Life Impact Analytics ---
        $impactGenerators = $rosterCollection->where('life_impact_earned', '>', 0)->sortByDesc('life_impact_earned')->values();
        $impactTotal = $dataset['rewards']['life_impact_total'];
        $highestImpactPeer = $impactGenerators->first();

        $impactPerPeer = $rosterCollection
            ->filter(static function (array $r) use ($search, $peerIdFilter): bool {
                if ($peerIdFilter !== null && $r['user_id'] !== $peerIdFilter) {
                    return false;
                }
                if ($search !== '') {
                    return str_contains(mb_strtolower($r['name']), $search)
                        || str_contains(mb_strtolower($r['company']), $search)
                        || str_contains(mb_strtolower($r['email']), $search);
                }

                return $r['attended'] || $r['life_impact_earned'] > 0;
            })
            ->sortByDesc('life_impact_earned')
            ->map(static fn (array $r): array => [
                'user_id' => $r['user_id'],
                'name' => $r['name'],
                'company' => $r['company'],
                'email' => $r['email'],
                'avatar_url' => $r['avatar_url'],
                'connections_made' => $r['connections_made'],
                'life_impact_earned_during_event' => $r['life_impact_earned'],
                'transactions_count' => $r['life_impact_transactions_count'],
                'current_total_impact_informational' => $r['current_life_impact_total'],
            ])
            ->values()
            ->all();

        $impactTransactions = collect($dataset['rewards']['impact_transactions'])
            ->filter(static function (array $tx) use ($search, $peerIdFilter): bool {
                if ($peerIdFilter !== null && $tx['user_id'] !== $peerIdFilter) {
                    return false;
                }
                if ($search !== '') {
                    return str_contains(mb_strtolower($tx['peer_name']), $search)
                        || str_contains(mb_strtolower((string) ($tx['partner_name'] ?? '')), $search)
                        || str_contains(mb_strtolower((string) ($tx['title'] ?? '')), $search)
                        || str_contains(mb_strtolower((string) ($tx['description'] ?? '')), $search);
                }

                return true;
            })
            ->values()
            ->all();

        return [
            'event' => $dataset['event'],
            'coins' => [
                'summary' => [
                    'total_coins_generated' => $coinTotal,
                    'total_coins_received' => $coinTotal,
                    'peers_rewarded_count' => $coinEarners->count(),
                    'avg_coins_per_attendee' => $attendedCount > 0 ? round($coinTotal / $attendedCount, 1) : 0.0,
                    'highest_earner' => $highestCoinEarner ? [
                        'user_id' => $highestCoinEarner['user_id'],
                        'name' => $highestCoinEarner['name'],
                        'amount' => $highestCoinEarner['coins_earned'],
                    ] : null,
                    'lowest_earner' => $lowestCoinEarner ? [
                        'user_id' => $lowestCoinEarner['user_id'],
                        'name' => $lowestCoinEarner['name'],
                        'amount' => $lowestCoinEarner['coins_earned'],
                    ] : null,
                    'total_transactions' => count($dataset['rewards']['coin_transactions']),
                ],
                'per_peer' => $coinPerPeer,
                'transactions' => $coinTransactions,
            ],
            'life_impact' => [
                'summary' => [
                    'total_impact_generated' => $impactTotal,
                    'peers_generating_impact_count' => $impactGenerators->count(),
                    'avg_impact_per_attendee' => $attendedCount > 0 ? round($impactTotal / $attendedCount, 2) : 0.0,
                    'highest_generator' => $highestImpactPeer ? [
                        'user_id' => $highestImpactPeer['user_id'],
                        'name' => $highestImpactPeer['name'],
                        'amount' => $highestImpactPeer['life_impact_earned'],
                    ] : null,
                    'total_impact_activities' => count($dataset['rewards']['impact_transactions']),
                ],
                'per_peer' => $impactPerPeer,
                'transactions' => $impactTransactions,
            ],
            'data_notes' => $dataset['data_notes'],
        ];
    }

    /**
     * Feature Nine: Event Data & Participation Reports.
     *
     * @return array<string, mixed>
     */
    public function getEventReports(string $eventId): array
    {
        $dataset = $this->buildEventDataset($eventId);
        $roster = collect($dataset['roster']);
        $attendedRoster = $roster->where('attended', true)->values();
        $avgConnections = (float) ($dataset['connections_summary']['avg_connections_per_attendee'] ?? 0.0);
        $highActivityThreshold = max(3, (int) ceil($avgConnections > 0 ? $avgConnections * 1.5 : 3));

        $zeroConnectionAttendees = $attendedRoster->where('connections_made', 0)->values()->all();
        $singleConnectionAttendees = $attendedRoster->where('connections_made', 1)->values()->all();
        $highlyActivePeers = $attendedRoster
            ->filter(static fn (array $r): bool => $r['connections_made'] >= $highActivityThreshold)
            ->sortByDesc('connections_made')
            ->values()
            ->all();
        $frequentSkippers = $roster
            ->filter(static fn (array $r): bool => $r['skips_given'] >= 2)
            ->sortByDesc('skips_given')
            ->values()
            ->all();
        $highWaitingPeers = $roster
            ->filter(static fn (array $r): bool => $r['estimated_waiting_seconds'] >= 120 || $r['is_waiting_live'])
            ->sortByDesc('estimated_waiting_seconds')
            ->values()
            ->all();
        $repeatedPairs = collect($dataset['unique_pair_sessions'])
            ->filter(static fn (array $p): bool => $p['session_count'] > 1)
            ->sortByDesc('session_count')
            ->values()
            ->all();

        $mostConnectedPeers = $attendedRoster->sortByDesc('connections_made')->take(10)->values()->all();
        $leastConnectedPeers = $attendedRoster->sortBy('connections_made')->take(10)->values()->all();

        return [
            'event' => $dataset['event'],
            'live_stats' => [
                'introductions_made' => $dataset['connections_summary']['valid_connections_count'],
                'completed_connections' => $dataset['connections_summary']['completed_connections_count'],
                'attended_count' => $dataset['attendance_summary']['attended_count'],
                'registered_count' => $dataset['attendance_summary']['registered_count'],
                'attendance_percentage' => $dataset['attendance_summary']['attendance_percentage'],
                'unique_peers_connected' => $dataset['connections_summary']['unique_peers_connected_count'],
                'total_coins_generated' => $dataset['rewards']['coins_total'],
                'total_life_impact_generated' => $dataset['rewards']['life_impact_total'],
            ],
            'participation_definitions' => [
                'highly_active_threshold' => "Peers with at least {$highActivityThreshold} connections made",
                'frequent_skipper_threshold' => 'Peers who skipped 2 or more times in this event',
                'waiting_time_note' => 'Waiting durations reflect live waiting time since last heartbeat/round and estimated gaps between consecutive sessions.',
            ],
            'participation' => [
                'zero_connection_attendees' => $zeroConnectionAttendees,
                'single_connection_attendees' => $singleConnectionAttendees,
                'highly_active_peers' => $highlyActivePeers,
                'repeated_connection_pairs' => $repeatedPairs,
                'frequent_skippers' => $frequentSkippers,
                'high_waiting_peers' => $highWaitingPeers,
                'most_connected_peers' => $mostConnectedPeers,
                'least_connected_peers' => $leastConnectedPeers,
            ],
            'available_reports' => [
                [
                    'key' => 'summary',
                    'title' => 'Event Summary Report',
                    'description' => 'Overview KPIs, attendance rates, connection volume, coins & life impact totals.',
                ],
                [
                    'key' => 'attendees',
                    'title' => 'Attendee Report',
                    'description' => 'Full attendee roster with registration, attendance, connections, coins & life impact.',
                ],
                [
                    'key' => 'connections',
                    'title' => 'Who Met Whom Report',
                    'description' => 'Complete list of peer-to-peer pairings, round numbers, durations & Meetaway matrix.',
                ],
                [
                    'key' => 'coins',
                    'title' => 'Event Coin Report',
                    'description' => 'Event-attributed coin rewards per peer and full transaction ledger.',
                ],
                [
                    'key' => 'life_impact',
                    'title' => 'Life Impact Report',
                    'description' => 'Event-attributed life impact scores per peer and activity ledger.',
                ],
                [
                    'key' => 'peer_performance',
                    'title' => 'Peer Performance Report',
                    'description' => 'Connections, unique peers met, skips, talk durations, coins & impact per peer.',
                ],
                [
                    'key' => 'live_activity',
                    'title' => 'Live Event Activity Report',
                    'description' => 'Chronological activity log, waiting pool snapshot & skip moderation history.',
                ],
                [
                    'key' => 'participation',
                    'title' => 'Participation Insights Report',
                    'description' => 'Zero-connection, single-connection, highly active peers, repeat pairs & frequent skippers.',
                ],
            ],
        ];
    }

    /**
     * Build the full canonical analytics dataset for a single event (used by reports, live monitor, and exports).
     *
     * @return array<string, mixed>
     */
    public function buildEventDataset(string $eventId): array
    {
        $resolved = $this->resolveEvent($eventId);
        $snEvent = $resolved['sn_event'];
        $baseEvent = $resolved['base_event'];
        $canonicalId = $resolved['id'];

        $rawStatus = (string) ($snEvent?->status ?? $baseEvent?->status ?? 'scheduled');
        $statusInfo = $this->normalizeEventStatus($rawStatus);

        $matches = Schema::hasTable('speed_networking_matches')
            ? SpeedNetworkingMatch::where('event_id', $canonicalId)->get()
            : collect();

        $participants = Schema::hasTable('speed_networking_participants')
            ? SpeedNetworkingParticipant::where('event_id', $canonicalId)->get()
            : collect();

        $registrations = Schema::hasTable('event_registrations')
            ? EventRegistration::query()
                ->where('event_id', $canonicalId)
                ->whereNull('deleted_at')
                ->get()
            : collect();

        $skipRows = Schema::hasTable('speed_networking_event_skips')
            ? SpeedNetworkingEventSkip::where('event_id', $canonicalId)->orderByDesc('updated_at')->get()
            : collect();

        $blockRows = Schema::hasTable('speed_networking_blocked_peers')
            ? SpeedNetworkingBlockedPeer::where('event_id', $canonicalId)->orderByDesc('created_at')->get()
            : collect();

        $rewards = $this->resolveEventRewardData($canonicalId, $matches);

        // Collect all user IDs so we load users once (zero N+1)
        $allUserIds = collect()
            ->merge($participants->pluck('user_id'))
            ->merge($registrations->pluck('user_id'))
            ->merge($matches->pluck('user_a_id'))
            ->merge($matches->pluck('user_b_id'))
            ->merge($skipRows->pluck('user_id'))
            ->merge($skipRows->pluck('skipped_peer_id'))
            ->merge($blockRows->pluck('blocker_id'))
            ->merge($blockRows->pluck('blocked_id'))
            ->merge(collect($rewards['coin_transactions'])->pluck('user_id'))
            ->merge(collect($rewards['impact_transactions'])->pluck('user_id'))
            ->filter()
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        /** @var Collection<string, User> $usersMap */
        $usersMap = $allUserIds !== []
            ? User::whereIn('id', $allUserIds)->get()->keyBy(static fn (User $u): string => (string) $u->id)
            : collect();

        $formatUser = function (?string $uid, ?EventRegistration $reg = null) use ($usersMap): array {
            $uidStr = (string) ($uid ?? '');
            $u = $uidStr !== '' ? $usersMap->get($uidStr) : null;

            if (! $u) {
                $fallbackName = $reg?->visitor_name ?: 'Peer Member';

                return [
                    'id' => $uidStr,
                    'name' => $fallbackName,
                    'email' => (string) ($reg?->visitor_email ?? ''),
                    'company' => (string) ($reg?->visitor_company ?? ''),
                    'designation' => (string) ($reg?->visitor_designation ?? ''),
                    'city' => (string) ($reg?->visitor_city ?? ''),
                    'avatar_url' => null,
                    'coins_balance' => 0,
                    'life_impacted_count' => 0,
                ];
            }

            $name = $u->display_name
                ?: trim(($u->first_name ?? '').' '.($u->last_name ?? ''))
                ?: ($u->name ?: 'Peer Member');

            return [
                'id' => (string) $u->id,
                'name' => $name,
                'email' => (string) ($u->email ?? ''),
                'company' => (string) ($u->company_name ?? ''),
                'designation' => (string) ($u->designation ?? ''),
                'city' => (string) ($u->city ?? $u->business_city ?? ''),
                'avatar_url' => $u->profile_photo_url ?? null,
                'coins_balance' => (int) ($u->coins_balance ?? 0),
                'life_impacted_count' => (int) ($u->life_impacted_count ?? 0),
            ];
        };

        // Enrich coin and impact transactions with real peer & partner names
        $matchById = $matches->keyBy(static fn (SpeedNetworkingMatch $m): string => (string) $m->id);
        $enrichPartnerFromMatch = function (?string $matchId, string $recipientId) use ($matchById, $formatUser): array {
            if ($matchId === null || ! $matchById->has($matchId)) {
                return ['partner_user_id' => null, 'partner_name' => null, 'round_number' => null];
            }
            /** @var SpeedNetworkingMatch $m */
            $m = $matchById->get($matchId);
            $partnerId = (string) ($m->user_a_id) === $recipientId ? (string) ($m->user_b_id ?? '') : (string) $m->user_a_id;
            $partner = $partnerId !== '' ? $formatUser($partnerId) : null;

            return [
                'partner_user_id' => $partnerId !== '' ? $partnerId : null,
                'partner_name' => $partner['name'] ?? null,
                'round_number' => (int) $m->round_number,
            ];
        };

        $rewards['coin_transactions'] = array_map(
            static function (array $tx) use ($formatUser, $enrichPartnerFromMatch): array {
                $peer = $formatUser($tx['user_id']);
                $pInfo = $enrichPartnerFromMatch($tx['match_id'], $tx['user_id']);

                return array_merge($tx, [
                    'peer_name' => $peer['name'],
                    'peer_company' => $peer['company'],
                    'peer_avatar_url' => $peer['avatar_url'],
                    'partner_user_id' => $pInfo['partner_user_id'],
                    'partner_name' => $pInfo['partner_name'],
                    'round_number' => $pInfo['round_number'],
                ]);
            },
            $rewards['coin_transactions']
        );

        $rewards['impact_transactions'] = array_map(
            static function (array $tx) use ($formatUser, $enrichPartnerFromMatch): array {
                $peer = $formatUser($tx['user_id']);
                $pInfo = $enrichPartnerFromMatch($tx['match_id'], $tx['user_id']);

                return array_merge($tx, [
                    'peer_name' => $peer['name'],
                    'peer_company' => $peer['company'],
                    'peer_avatar_url' => $peer['avatar_url'],
                    'partner_user_id' => $tx['triggered_by_user_id'] ?? $pInfo['partner_user_id'],
                    'partner_name' => $tx['triggered_by_user_id']
                        ? $formatUser($tx['triggered_by_user_id'])['name']
                        : $pInfo['partner_name'],
                    'round_number' => $pInfo['round_number'],
                ]);
            },
            $rewards['impact_transactions']
        );

        // Compute match & connection enrichments
        $now = Carbon::now();
        $roundDuration = max(1, (int) ($snEvent?->round_duration_seconds ?? ($baseEvent?->metadata['round_duration_seconds'] ?? 300)));
        $matchesEnriched = [];
        $validPairSessions = [];
        $uniquePairsSet = [];
        $uniquePeersConnectedSet = [];
        $validConnectionsCount = 0;
        $completedConnectionsCount = 0;
        $qualifyingP2pCount = 0;
        $activeInCallCount = 0;
        $activePreviewCount = 0;
        $totalValidDurationSeconds = 0;

        foreach ($matches as $m) {
            $matchId = (string) $m->id;
            $isBreak = $m->isBreakRound();
            $matchStatus = (string) ($m->match_status ?: $m->status);
            $callStart = $m->call_started_at ?? $m->started_at;
            $durationSeconds = 0;

            if ($m->duration_seconds !== null && (int) $m->duration_seconds > 0) {
                $durationSeconds = (int) $m->duration_seconds;
            } elseif ($callStart && $m->ended_at) {
                $durationSeconds = max(0, (int) $callStart->diffInSeconds($m->ended_at, false));
            } elseif ($callStart && $m->status === SpeedNetworkingMatch::STATUS_ACTIVE && $matchStatus === SpeedNetworkingMatch::MATCH_STATUS_IN_CALL) {
                $durationSeconds = max(0, (int) $callStart->diffInSeconds($now, false));
            }

            $remainingSec = 0;
            if ($m->status === SpeedNetworkingMatch::STATUS_ACTIVE) {
                if ($matchStatus === SpeedNetworkingMatch::MATCH_STATUS_IN_CALL && $m->call_expires_at) {
                    $remainingSec = max(0, (int) $now->diffInSeconds($m->call_expires_at, false));
                } elseif ($matchStatus === SpeedNetworkingMatch::MATCH_STATUS_PREVIEW && $m->preview_expires_at) {
                    $remainingSec = max(0, (int) $now->diffInSeconds($m->preview_expires_at, false));
                }
            }

            $isValidConnection = ! $isBreak
                && in_array($matchStatus, [SpeedNetworkingMatch::MATCH_STATUS_COMPLETED, SpeedNetworkingMatch::MATCH_STATUS_IN_CALL], true)
                && $m->status !== SpeedNetworkingMatch::STATUS_SKIPPED;

            $isCompleted = ! $isBreak && $matchStatus === SpeedNetworkingMatch::MATCH_STATUS_COMPLETED;
            $qualifiesP2p = $isValidConnection && $durationSeconds >= SpeedNetworkingMatch::MIN_QUALIFYING_DURATION_SECONDS;

            if ($isValidConnection) {
                $validConnectionsCount++;
                $totalValidDurationSeconds += $durationSeconds;
                $uniquePeersConnectedSet[(string) $m->user_a_id] = true;
                $uniquePeersConnectedSet[(string) $m->user_b_id] = true;

                $pairUsers = [(string) $m->user_a_id, (string) $m->user_b_id];
                sort($pairUsers);
                $pairKey = $pairUsers[0].':'.$pairUsers[1];
                $uniquePairsSet[$pairKey] = true;

                if (! isset($validPairSessions[$pairKey])) {
                    $pA = $formatUser($pairUsers[0]);
                    $pB = $formatUser($pairUsers[1]);
                    $validPairSessions[$pairKey] = [
                        'pair_key' => $pairKey,
                        'peer_a' => ['id' => $pA['id'], 'name' => $pA['name'], 'company' => $pA['company']],
                        'peer_b' => ['id' => $pB['id'], 'name' => $pB['name'], 'company' => $pB['company']],
                        'session_count' => 0,
                        'rounds' => [],
                    ];
                }
                $validPairSessions[$pairKey]['session_count']++;
                $validPairSessions[$pairKey]['rounds'][] = (int) $m->round_number;
            }

            if ($isCompleted) {
                $completedConnectionsCount++;
            }
            if ($qualifiesP2p) {
                $qualifyingP2pCount++;
            }
            if ($m->status === SpeedNetworkingMatch::STATUS_ACTIVE && $matchStatus === SpeedNetworkingMatch::MATCH_STATUS_IN_CALL) {
                $activeInCallCount++;
            }
            if ($m->status === SpeedNetworkingMatch::STATUS_ACTIVE && $matchStatus === SpeedNetworkingMatch::MATCH_STATUS_PREVIEW) {
                $activePreviewCount++;
            }

            $coinTxsForMatch = array_values(array_filter(
                $rewards['coin_transactions'],
                static fn (array $tx): bool => $tx['match_id'] === $matchId
            ));
            $impactTxsForMatch = array_values(array_filter(
                $rewards['impact_transactions'],
                static fn (array $tx): bool => $tx['match_id'] === $matchId
            ));

            $p2pMeetingId = $rewards['match_to_p2p'][$matchId] ?? null;

            $matchesEnriched[] = [
                'id' => $matchId,
                'match_id' => $matchId,
                'round_number' => (int) $m->round_number,
                'table_number' => (int) $m->table_number,
                'channel_id' => (string) ($m->channel_id ?? ''),
                'user_a_id' => (string) $m->user_a_id,
                'user_b_id' => $m->user_b_id ? (string) $m->user_b_id : null,
                'user_a' => $formatUser((string) $m->user_a_id),
                'user_b' => $m->user_b_id ? $formatUser((string) $m->user_b_id) : null,
                'status' => (string) $m->status,
                'match_status' => $matchStatus,
                'human_status' => $this->humanizeMatchStatus($matchStatus, $isBreak),
                'user_a_decision' => (string) ($m->user_a_decision ?: SpeedNetworkingMatch::DECISION_PENDING),
                'user_b_decision' => (string) ($m->user_b_decision ?: SpeedNetworkingMatch::DECISION_PENDING),
                'is_break_round' => $isBreak,
                'is_valid_connection' => $isValidConnection,
                'qualifying_p2p' => $qualifiesP2p,
                'started_at' => $callStart?->copy()->utc()->toISOString(),
                'preview_started_at' => $m->started_at?->copy()->utc()->toISOString(),
                'ended_at' => $m->ended_at?->copy()->utc()->toISOString(),
                'duration_seconds' => $durationSeconds,
                'remaining_seconds' => $remainingSec,
                'coins_earned_total' => (int) array_sum(array_column($coinTxsForMatch, 'amount')),
                'life_impact_total' => (int) array_sum(array_column($impactTxsForMatch, 'impact_value')),
                'coin_transactions' => $coinTxsForMatch,
                'impact_transactions' => $impactTxsForMatch,
                'p2p_meeting_id' => $p2pMeetingId,
                'notes' => $m->getNotesForUser(null),
            ];
        }

        // Index skips & blocks per user
        $maxSkipLimit = $snEvent ? $snEvent->getEffectiveMaxSkipLimit() : (int) ($baseEvent?->metadata['max_skip_limit'] ?? 2);
        $skipsList = $skipRows->map(static function (SpeedNetworkingEventSkip $row) use ($formatUser, $maxSkipLimit): array {
            $count = max(1, (int) ($row->skip_count ?? 1));

            return [
                'id' => (string) $row->id,
                'user' => $formatUser((string) $row->user_id),
                'skipped_peer' => $formatUser((string) $row->skipped_peer_id),
                'skip_count' => $count,
                'max_skip_limit' => $maxSkipLimit,
                'limit_reached' => $maxSkipLimit <= 1 || $count >= $maxSkipLimit,
                'updated_at' => $row->updated_at?->copy()->utc()->toISOString(),
                'created_at' => $row->created_at?->copy()->utc()->toISOString(),
            ];
        })->values()->all();

        $blocksList = $blockRows->map(static function (SpeedNetworkingBlockedPeer $row) use ($formatUser): array {
            return [
                'id' => (string) $row->id,
                'blocker' => $formatUser((string) $row->blocker_id),
                'blocked_peer' => $formatUser((string) $row->blocked_id),
                'reason' => (string) ($row->reason ?? 'Blocked during Speed Collaboration'),
                'created_at' => $row->created_at?->copy()->utc()->toISOString(),
            ];
        })->values()->all();

        // Build per-user lookup maps
        $regsByUser = [];
        foreach ($registrations as $reg) {
            $uid = (string) ($reg->user_id ?? '');
            if ($uid === '') {
                continue;
            }
            $regsByUser[$uid] = $reg;
        }
        $partsByUser = $participants->keyBy(static fn (SpeedNetworkingParticipant $p): string => (string) $p->user_id);

        $rosterUserIds = collect(array_keys($regsByUser))
            ->merge($participants->pluck('user_id')->map(static fn ($id): string => (string) $id))
            ->merge(collect($matches)->flatMap(static fn (SpeedNetworkingMatch $m): array => array_filter([
                (string) $m->user_a_id,
                $m->user_b_id ? (string) $m->user_b_id : null,
            ])))
            ->unique()
            ->values();

        $activeCutoff = $now->copy()->subSeconds(60);
        $roster = [];
        $registeredActiveCount = 0;
        $attendedCount = 0;
        $notAttendedCount = 0;
        $canceledCount = 0;
        $joinedWithoutRegCount = 0;

        foreach ($rosterUserIds as $uid) {
            /** @var EventRegistration|null $reg */
            $reg = $regsByUser[$uid] ?? null;
            /** @var SpeedNetworkingParticipant|null $part */
            $part = $partsByUser->get($uid);
            $userInfo = $formatUser($uid, $reg);

            $isCanceled = $reg !== null && in_array(mb_strtolower((string) $reg->status), ['cancelled', 'canceled'], true);
            $isRegistered = $reg !== null && ! $isCanceled;

            $userMatches = array_values(array_filter(
                $matchesEnriched,
                static fn (array $m): bool => ! $m['is_break_round'] && ($m['user_a_id'] === $uid || $m['user_b_id'] === $uid)
            ));

            $hasParticipantAttendance = $part !== null && (
                $part->joined_at !== null
                || $part->rounds_completed > 0
                || $part->status !== SpeedNetworkingParticipant::STATUS_REGISTERED
            );
            $hasCheckinAttendance = $reg !== null && in_array(mb_strtolower((string) $reg->checkin_status), ['checked_in', 'attended'], true);
            $attended = ! $isCanceled && ($hasParticipantAttendance || count($userMatches) > 0 || $hasCheckinAttendance);

            // If no event_registrations exist for this event, participants count as registered too
            $effectiveRegistered = $isRegistered || ($registrations->isEmpty() && $attended);
            if ($isCanceled) {
                $canceledCount++;
            } elseif ($effectiveRegistered) {
                $registeredActiveCount++;
                if (! $isRegistered && $attended) {
                    $joinedWithoutRegCount++;
                }
            }

            if ($attended) {
                $attendedCount++;
            } elseif ($effectiveRegistered) {
                $notAttendedCount++;
            }

            $validUserMatches = array_values(array_filter($userMatches, static fn (array $m): bool => $m['is_valid_connection']));
            $completedUserMatches = array_values(array_filter($validUserMatches, static fn (array $m): bool => $m['match_status'] === SpeedNetworkingMatch::MATCH_STATUS_COMPLETED));
            $qualifyingUserMatches = array_values(array_filter($validUserMatches, static fn (array $m): bool => $m['qualifying_p2p']));

            $uniquePartnerSet = [];
            $userTotalDuration = 0;
            foreach ($validUserMatches as $um) {
                $partnerId = $um['user_a_id'] === $uid ? $um['user_b_id'] : $um['user_a_id'];
                if ($partnerId !== null) {
                    $uniquePartnerSet[$partnerId] = true;
                }
                $userTotalDuration += $um['duration_seconds'];
            }

            $userConnCount = count($validUserMatches);
            $userUniqueCount = count($uniquePartnerSet);
            $repeatedCount = max(0, $userConnCount - $userUniqueCount);

            $userCoinTxs = array_values(array_filter($rewards['coin_transactions'], static fn (array $tx): bool => $tx['user_id'] === $uid));
            $userImpactTxs = array_values(array_filter($rewards['impact_transactions'], static fn (array $tx): bool => $tx['user_id'] === $uid));
            $userCoins = (int) array_sum(array_column($userCoinTxs, 'amount'));
            $userImpact = (int) array_sum(array_column($userImpactTxs, 'impact_value'));

            $skipsGiven = (int) $skipRows->where('user_id', $uid)->sum(static fn ($r): int => max(1, (int) ($r->skip_count ?? 1)));
            $skipsReceived = (int) $skipRows->where('skipped_peer_id', $uid)->sum(static fn ($r): int => max(1, (int) ($r->skip_count ?? 1)));
            $blocksGiven = $blockRows->where('blocker_id', $uid)->count();
            $blocksReceived = $blockRows->where('blocked_id', $uid)->count();

            $rawLiveStatus = (string) ($part?->status ?? ($isCanceled ? 'canceled' : ($attended ? 'completed' : 'registered')));
            $isOnline = $part !== null
                && $part->status !== SpeedNetworkingParticipant::STATUS_LEFT
                && (
                    ($part->last_heartbeat_at && $part->last_heartbeat_at->greaterThanOrEqualTo($activeCutoff))
                    || in_array($part->status, [SpeedNetworkingParticipant::STATUS_IN_CALL, SpeedNetworkingParticipant::STATUS_PREVIEW, SpeedNetworkingParticipant::STATUS_ACCEPTED], true)
                );

            $isWaitingLive = $part !== null
                && in_array($statusInfo['display_status'], ['live', 'paused'], true)
                && in_array($part->status, self::WAITING_STATUSES, true)
                && $part->status !== SpeedNetworkingParticipant::STATUS_LEFT;

            $waitingSince = $isWaitingLive
                ? ($part->updated_at ?? $part->last_heartbeat_at ?? $part->joined_at)
                : null;
            $currentWaitingSeconds = $waitingSince ? max(0, (int) $waitingSince->diffInSeconds($now, false)) : 0;
            $estimatedWaitingSeconds = $this->estimatePeerWaitingSeconds($userMatches, $currentWaitingSeconds);

            $roster[] = [
                'user_id' => $uid,
                'name' => $userInfo['name'],
                'email' => $userInfo['email'],
                'company' => $userInfo['company'],
                'designation' => $userInfo['designation'],
                'city' => $userInfo['city'],
                'avatar_url' => $userInfo['avatar_url'],
                'is_registered' => $effectiveRegistered,
                'is_canceled' => $isCanceled,
                'attended' => $attended,
                'attendance_status' => $isCanceled ? 'Canceled' : ($attended ? 'Attended' : 'Not Attended'),
                'registered_at' => $reg?->registered_at?->copy()->utc()->toISOString() ?? $reg?->created_at?->copy()->utc()->toISOString(),
                'joined_at' => $part?->joined_at?->copy()->utc()->toISOString(),
                'last_heartbeat_at' => $part?->last_heartbeat_at?->copy()->utc()->toISOString(),
                'is_online' => $isOnline,
                'is_currently_connected' => in_array($rawLiveStatus, [SpeedNetworkingParticipant::STATUS_IN_CALL, SpeedNetworkingParticipant::STATUS_PREVIEW, SpeedNetworkingParticipant::STATUS_ACCEPTED], true),
                'is_waiting_live' => $isWaitingLive,
                'waiting_since' => $waitingSince?->copy()->utc()->toISOString(),
                'current_waiting_seconds' => $currentWaitingSeconds,
                'estimated_waiting_seconds' => $estimatedWaitingSeconds,
                'raw_live_status' => $rawLiveStatus,
                'human_live_status' => $this->humanizeParticipantStatus($rawLiveStatus, $statusInfo['display_status'], $attended, $isCanceled),
                'connections_made' => $userConnCount,
                'completed_connections' => count($completedUserMatches),
                'qualifying_p2p_count' => count($qualifyingUserMatches),
                'unique_peers_met' => $userUniqueCount,
                'repeated_connections' => $repeatedCount,
                'total_duration_seconds' => $userTotalDuration,
                'avg_duration_seconds' => $userConnCount > 0 ? (int) round($userTotalDuration / $userConnCount) : 0,
                'skips_given' => $skipsGiven,
                'skips_received' => $skipsReceived,
                'blocks_given' => $blocksGiven,
                'blocks_received' => $blocksReceived,
                'coins_earned' => $userCoins,
                'coin_transactions_count' => count($userCoinTxs),
                'current_coins_balance' => $userInfo['coins_balance'],
                'life_impact_earned' => $userImpact,
                'life_impact_transactions_count' => count($userImpactTxs),
                'current_life_impact_total' => $userInfo['life_impacted_count'],
            ];
        }

        // If registrations exist AND some attended users joined without registration, reflect distinct registered vs attended accurately
        $attendancePct = $registeredActiveCount > 0 ? round(($attendedCount / $registeredActiveCount) * 100, 1) : 0.0;
        $durSec = $snEvent
            ? max(60, (int) ($snEvent->duration_seconds ?: $snEvent->calculateDurationSeconds()))
            : max(60, (int) ($baseEvent?->metadata['duration_seconds'] ?? 1800));
        $schedStart = $snEvent?->scheduled_start_time ?? $baseEvent?->start_at;
        $schedEnd = $snEvent?->scheduled_end_time ?? $baseEvent?->end_at ?? ($schedStart?->copy()->addSeconds($durSec));

        $eventPayload = [
            'id' => $canonicalId,
            'title' => (string) ($snEvent?->title ?? $baseEvent?->title ?? 'Speed Collaboration'),
            'description' => $snEvent?->description ?? $baseEvent?->description,
            'banner_url' => $snEvent?->banner_url ?? $baseEvent?->banner_url,
            'status' => $rawStatus,
            'display_status' => $statusInfo['display_status'],
            'status_label' => $statusInfo['label'],
            'circle_name' => $snEvent?->circle?->name ?? $baseEvent?->circle?->name,
            'scheduled_start_time' => $schedStart?->copy()->utc()->toISOString(),
            'scheduled_end_time' => $schedEnd?->copy()->utc()->toISOString(),
            'actual_start_time' => $snEvent?->actual_start_time?->copy()->utc()->toISOString(),
            'actual_end_time' => $snEvent?->actual_end_time?->copy()->utc()->toISOString(),
            'duration_seconds' => $durSec,
            'duration_minutes' => (int) ceil($durSec / 60),
            'round_duration_seconds' => $roundDuration,
            'break_duration_seconds' => (int) ($snEvent?->break_duration_seconds ?? 20),
            'preview_duration_seconds' => (int) ($snEvent?->preview_duration_seconds ?? 15),
            'max_skip_limit' => $maxSkipLimit,
            'total_rounds' => (int) ($snEvent?->total_rounds ?? 5),
            'current_round' => (int) ($snEvent?->current_round ?? 0),
            'ticket_price' => (float) ($snEvent?->ticket_price ?? $baseEvent?->ticket_price ?? 0),
            'is_paid' => (bool) ($snEvent?->is_paid ?? $baseEvent?->is_paid ?? false),
            'currency' => (string) ($snEvent?->currency ?? 'INR'),
        ];

        $skipsTotalCount = (int) $skipRows->sum(static fn ($r): int => max(1, (int) ($r->skip_count ?? 1)));

        return [
            'event' => $eventPayload,
            'sn_event' => $snEvent,
            'attendance_summary' => [
                'registered_count' => $registeredActiveCount,
                'attended_count' => $attendedCount,
                'not_attended_count' => $notAttendedCount,
                'canceled_count' => $canceledCount,
                'joined_without_registration_count' => $joinedWithoutRegCount,
                'attendance_percentage' => $attendancePct,
            ],
            'connections_summary' => [
                'total_match_records' => count($matchesEnriched),
                'valid_connections_count' => $validConnectionsCount,
                'completed_connections_count' => $completedConnectionsCount,
                'qualifying_p2p_connections_count' => $qualifyingP2pCount,
                'active_in_call_count' => $activeInCallCount,
                'active_preview_count' => $activePreviewCount,
                'unique_pairs_count' => count($uniquePairsSet),
                'unique_peers_connected_count' => count($uniquePeersConnectedSet),
                'avg_connections_per_attendee' => $attendedCount > 0 ? round($validConnectionsCount / $attendedCount, 2) : 0.0,
                'avg_duration_seconds' => $validConnectionsCount > 0 ? (int) round($totalValidDurationSeconds / $validConnectionsCount) : 0,
            ],
            'unique_pair_sessions' => array_values($validPairSessions),
            'roster' => $roster,
            'matches_enriched' => $matchesEnriched,
            'skips' => $skipsList,
            'skips_total_count' => $skipsTotalCount,
            'blocks' => $blocksList,
            'blocks_total_count' => count($blocksList),
            'rewards' => $rewards,
            'data_notes' => [
                'Coins Earned During Event sums only verified coins_ledger credit rows linked to this event’s Speed Collaboration P2P meetings (or direct event reference), never user wallet balances.',
                'Life Impact Generated sums only verified life_impact_histories rows linked to this event (by P2P meeting ID or event/match metadata), never lifetime user totals.',
                'Waiting duration uses live waiting participant state timestamps during live sessions and inter-session gaps as an estimate when historical queue-entry logs are absent.',
            ],
        ];
    }

    /**
     * Resolve event-attributed Coin & Life Impact ledger records for a given event's matches.
     *
     * @param  Collection<int, SpeedNetworkingMatch>  $matches
     * @return array<string, mixed>
     */
    protected function resolveEventRewardData(string $eventId, Collection $matches): array
    {
        $matchIds = $matches->pluck('id')->map(static fn ($id): string => (string) $id)->all();
        $matchIdSet = array_flip($matchIds);
        $p2pToMatch = [];
        $matchToP2p = [];

        if ($matchIds !== [] && Schema::hasTable('p2p_meetings')) {
            $p2pQuery = P2pMeeting::query()
                ->where('is_deleted', false)
                ->where(function ($q): void {
                    $q->where('remarks', 'like', '%[sn_match:%')
                        ->orWhere('remarks', 'like', '%[match:%');
                });

            foreach ($p2pQuery->get(['id', 'remarks', 'initiator_user_id', 'peer_user_id']) as $meeting) {
                if (preg_match('/\[(?:sn_match|match):([0-9a-fA-F-]{36})\]/', (string) $meeting->remarks, $m)) {
                    $resolvedMatchId = strtolower($m[1]);
                    foreach ($matchIdSet as $candidateId => $_) {
                        if (strtolower($candidateId) === $resolvedMatchId) {
                            $midStr = (string) $meeting->id;
                            $p2pToMatch[$midStr] = $candidateId;
                            $matchToP2p[$candidateId] = $midStr;
                            break;
                        }
                    }
                }
            }
        }

        $p2pIds = array_keys($p2pToMatch);
        $coinTransactions = [];
        $seenCoinTx = [];

        if (Schema::hasTable('coins_ledger')) {
            $coinQuery = CoinsLedger::query()->where('amount', '>', 0);
            $coinQuery->where(function ($q) use ($p2pIds, $eventId): void {
                $q->where('source_id', $eventId)->orWhere('reference', $eventId);
                if ($p2pIds !== []) {
                    $q->orWhere(function ($sub) use ($p2pIds): void {
                        $sub->where('source_type', 'p2p_meeting')
                            ->whereIn('source_id', $p2pIds);
                    });
                }
            });

            foreach ($coinQuery->orderByDesc('created_at')->get() as $ledger) {
                $txId = (string) $ledger->transaction_id;
                if (isset($seenCoinTx[$txId])) {
                    continue;
                }
                $seenCoinTx[$txId] = true;

                $sourceId = (string) ($ledger->source_id ?? '');
                $resolvedMatchId = $p2pToMatch[$sourceId] ?? null;

                $coinTransactions[] = [
                    'transaction_id' => $txId,
                    'user_id' => (string) $ledger->user_id,
                    'amount' => (int) $ledger->amount,
                    'balance_after' => (int) ($ledger->balance_after ?? 0),
                    'source_type' => (string) ($ledger->source_type ?? 'p2p_meeting'),
                    'source_id' => $sourceId,
                    'match_id' => $resolvedMatchId,
                    'reference' => (string) ($ledger->reference ?? ''),
                    'remark' => (string) ($ledger->remark ?? ''),
                    'created_at' => $ledger->created_at?->copy()->utc()->toISOString(),
                    'status' => 'Credited',
                ];
            }
        }

        $impactTransactions = [];
        $seenImpactTx = [];

        if (Schema::hasTable('life_impact_histories')) {
            $impactQuery = LifeImpactHistory::query();

            if (Schema::hasColumn('life_impact_histories', 'counted_in_total')) {
                $impactQuery->where(function ($q): void {
                    $q->where('counted_in_total', true)->orWhereNull('counted_in_total');
                });
            }
            if (Schema::hasColumn('life_impact_histories', 'status')) {
                $impactQuery->where(function ($q): void {
                    $q->where('status', 'approved')->orWhereNull('status');
                });
            }

            $isPgsql = DB::connection()->getDriverName() === 'pgsql';
            $impactQuery->where(function ($q) use ($p2pIds, $eventId, $isPgsql): void {
                if ($isPgsql) {
                    $q->whereRaw('CAST(meta AS TEXT) LIKE ?', ['%'.$eventId.'%']);
                } else {
                    $q->where('meta', 'like', '%'.$eventId.'%');
                }
                if ($p2pIds !== []) {
                    $q->orWhere(function ($sub) use ($p2pIds): void {
                        $sub->where('activity_type', 'p2p_meeting')
                            ->whereIn('activity_id', $p2pIds);
                    });
                }
            });

            foreach ($impactQuery->orderByDesc('created_at')->get() as $hist) {
                $hid = (string) $hist->id;
                if (isset($seenImpactTx[$hid])) {
                    continue;
                }

                $meta = is_array($hist->meta) ? $hist->meta : (json_decode((string) $hist->meta, true) ?: []);
                $activityId = (string) ($hist->activity_id ?? '');
                $metaEventId = (string) ($meta['speed_networking_event_id'] ?? '');
                $metaMatchId = (string) ($meta['match_id'] ?? '');

                $belongs = ($hist->activity_type === 'p2p_meeting' && isset($p2pToMatch[$activityId]))
                    || $metaEventId === $eventId
                    || ($metaMatchId !== '' && isset($matchIdSet[$metaMatchId]));

                if (! $belongs) {
                    continue;
                }

                $seenImpactTx[$hid] = true;
                $resolvedMatchId = $p2pToMatch[$activityId] ?? ($metaMatchId !== '' ? $metaMatchId : null);
                $impactVal = max(0, (int) ($hist->impact_value ?: $hist->life_impacted ?: 0));
                if ($impactVal === 0) {
                    $impactVal = $hist->resolveImpactValue();
                }

                $impactTransactions[] = [
                    'transaction_id' => $hid,
                    'user_id' => (string) $hist->user_id,
                    'triggered_by_user_id' => $hist->triggered_by_user_id ? (string) $hist->triggered_by_user_id : null,
                    'impact_value' => $impactVal,
                    'impact_after' => (int) ($hist->impact_after ?? 0),
                    'activity_type' => (string) ($hist->activity_type ?? 'p2p_meeting'),
                    'activity_id' => $activityId,
                    'match_id' => $resolvedMatchId,
                    'title' => (string) ($hist->title ?? 'Speed Collaboration 1-on-1 Meeting'),
                    'description' => (string) ($hist->description ?? $hist->remarks ?? ''),
                    'created_at' => $hist->created_at?->copy()->utc()->toISOString(),
                    'status' => (string) ($hist->status ?? 'approved'),
                ];
            }
        }

        $coinsTotal = (int) array_sum(array_column($coinTransactions, 'amount'));
        $impactTotal = (int) array_sum(array_column($impactTransactions, 'impact_value'));
        $coinPeers = count(array_unique(array_column($coinTransactions, 'user_id')));
        $impactPeers = count(array_unique(array_column($impactTransactions, 'user_id')));

        return [
            'p2p_to_match' => $p2pToMatch,
            'match_to_p2p' => $matchToP2p,
            'coin_transactions' => $coinTransactions,
            'impact_transactions' => $impactTransactions,
            'coins_total' => $coinsTotal,
            'life_impact_total' => $impactTotal,
            'peers_rewarded_coins_count' => $coinPeers,
            'peers_generated_impact_count' => $impactPeers,
        ];
    }

    /**
     * Collect and merge speed networking events from both speed_networking_events and base events.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function collectAllSpeedNetworkingEvents(): Collection
    {
        $snEvents = Schema::hasTable('speed_networking_events')
            ? SpeedNetworkingEvent::with('circle')->get()
            : collect();
        $baseEvents = Schema::hasTable('events')
            ? Event::with('circle')
                ->where(function ($q): void {
                    $q->where('event_type', 'speed_networking');
                    if (DB::connection()->getDriverName() === 'pgsql') {
                        $q->orWhereRaw("CAST(metadata AS TEXT) LIKE '%speed_networking%'");
                    } else {
                        $q->orWhere('metadata', 'like', '%speed_networking%');
                    }
                    $q->orWhere('title', 'like', '%Speed Collaboration%')
                        ->orWhere('title', 'like', '%Speed Networking%');
                })
                ->get()
            : collect();

        $baseById = $baseEvents->keyBy(static fn (Event $e): string => (string) $e->id);
        $items = collect();
        $seenIds = [];
        $seenTitles = [];

        foreach ($snEvents as $sn) {
            $id = (string) $sn->id;
            $base = $baseById->get($id) ?? $baseEvents->first(
                static fn (Event $be): bool => mb_strtolower(trim($be->title)) === mb_strtolower(trim($sn->title))
            );
            $rawStatus = (string) $sn->status;
            $schedStart = $sn->scheduled_start_time ?? $base?->start_at;
            $durSec = max(60, (int) ($sn->duration_seconds ?: $sn->calculateDurationSeconds()));
            $schedEnd = $sn->scheduled_end_time ?? $base?->end_at ?? $schedStart?->copy()->addSeconds($durSec);

            $seenIds[$id] = true;
            $seenTitles[mb_strtolower(trim($sn->title))] = true;
            if ($base) {
                $seenIds[(string) $base->id] = true;
            }

            $items->push([
                'id' => $id,
                'title' => (string) $sn->title,
                'description' => $sn->description ?? $base?->description,
                'banner_url' => $sn->banner_url ?? $base?->banner_url,
                'circle_id' => $sn->circle_id ? (string) $sn->circle_id : ($base?->circle_id ? (string) $base->circle_id : null),
                'circle_name' => $sn->circle?->name ?? $base?->circle?->name,
                'event_type' => 'speed_networking',
                'raw_status' => $rawStatus,
                'status_info' => $this->normalizeEventStatus($rawStatus),
                'scheduled_start_time' => $schedStart?->copy()->utc()->toISOString(),
                'scheduled_end_time' => $schedEnd?->copy()->utc()->toISOString(),
                'actual_start_time' => $sn->actual_start_time?->copy()->utc()->toISOString(),
                'actual_end_time' => $sn->actual_end_time?->copy()->utc()->toISOString(),
                'round_duration_seconds' => (int) $sn->round_duration_seconds,
                'break_duration_seconds' => (int) $sn->break_duration_seconds,
                'preview_duration_seconds' => (int) ($sn->preview_duration_seconds ?? 15),
                'max_skip_limit' => $sn->getEffectiveMaxSkipLimit(),
                'total_rounds' => (int) $sn->total_rounds,
                'current_round' => (int) $sn->current_round,
                'ticket_price' => (float) ($sn->ticket_price ?? $base?->ticket_price ?? 0),
                'is_paid' => (bool) ($sn->is_paid ?? $base?->is_paid ?? false),
                'sort_timestamp' => $schedStart ?? $sn->created_at,
            ]);
        }

        foreach ($baseEvents as $be) {
            $id = (string) $be->id;
            $normTitle = mb_strtolower(trim($be->title));
            if (isset($seenIds[$id]) || isset($seenTitles[$normTitle])) {
                continue;
            }
            $seenIds[$id] = true;
            $seenTitles[$normTitle] = true;
            $meta = is_array($be->metadata) ? $be->metadata : [];
            $rawStatus = (string) ($be->status ?: 'scheduled');
            $schedStart = $be->start_at;
            $schedEnd = $be->end_at ?? $schedStart?->copy()->addMinutes(30);

            $items->push([
                'id' => $id,
                'title' => (string) $be->title,
                'description' => $be->description,
                'banner_url' => $be->banner_url,
                'circle_id' => $be->circle_id ? (string) $be->circle_id : null,
                'circle_name' => $be->circle?->name,
                'event_type' => (string) ($be->event_type ?: 'speed_networking'),
                'raw_status' => $rawStatus,
                'status_info' => $this->normalizeEventStatus($rawStatus),
                'scheduled_start_time' => $schedStart?->copy()->utc()->toISOString(),
                'scheduled_end_time' => $schedEnd?->copy()->utc()->toISOString(),
                'actual_start_time' => null,
                'actual_end_time' => null,
                'round_duration_seconds' => (int) ($meta['round_duration_seconds'] ?? 300),
                'break_duration_seconds' => (int) ($meta['break_duration_seconds'] ?? 20),
                'preview_duration_seconds' => (int) ($meta['preview_duration_seconds'] ?? 15),
                'max_skip_limit' => (int) ($meta['max_skip_limit'] ?? 2),
                'total_rounds' => (int) ($meta['total_rounds'] ?? 5),
                'current_round' => 0,
                'ticket_price' => (float) ($be->ticket_price ?? 0),
                'is_paid' => (bool) ($be->is_paid ?? false),
                'sort_timestamp' => $schedStart ?? $be->created_at,
            ]);
        }

        return $items->sortByDesc(static fn (array $e): int => $e['sort_timestamp']?->timestamp ?? 0)->values();
    }

    /**
     * Batch-compute event list analytics across multiple events without N+1 queries.
     *
     * @param  array<int, string>  $eventIds
     * @return array<string, array<string, int|float>>
     */
    protected function computeBatchEventStats(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $stats = [];
        foreach ($eventIds as $id) {
            $stats[$id] = $this->emptyEventListStats();
        }

        $regRows = Schema::hasTable('event_registrations')
            ? EventRegistration::whereIn('event_id', $eventIds)
                ->whereNull('deleted_at')
                ->get(['event_id', 'user_id', 'status', 'checkin_status'])
            : collect();

        $partRows = Schema::hasTable('speed_networking_participants')
            ? SpeedNetworkingParticipant::whereIn('event_id', $eventIds)
                ->get(['event_id', 'user_id', 'status', 'joined_at', 'rounds_completed'])
            : collect();

        $matchRows = Schema::hasTable('speed_networking_matches')
            ? SpeedNetworkingMatch::whereIn('event_id', $eventIds)
                ->get(['id', 'event_id', 'user_a_id', 'user_b_id', 'status', 'match_status', 'duration_seconds', 'call_started_at', 'started_at', 'ended_at'])
            : collect();

        $skipRows = Schema::hasTable('speed_networking_event_skips')
            ? SpeedNetworkingEventSkip::whereIn('event_id', $eventIds)
                ->get(['event_id', 'skip_count'])
            : collect();

        $regsByEvent = $regRows->groupBy('event_id');
        $partsByEvent = $partRows->groupBy('event_id');
        $matchesByEvent = $matchRows->groupBy('event_id');
        $skipsByEvent = $skipRows->groupBy('event_id');

        // Map all match IDs to event IDs so we batch-fetch P2P meetings & ledger entries once
        $matchToEvent = [];
        foreach ($matchRows as $mr) {
            $matchToEvent[(string) $mr->id] = (string) $mr->event_id;
        }

        $p2pToEvent = [];
        if ($matchToEvent !== [] && Schema::hasTable('p2p_meetings')) {
            $p2ps = P2pMeeting::where('is_deleted', false)
                ->where(function ($q): void {
                    $q->where('remarks', 'like', '%[sn_match:%')
                        ->orWhere('remarks', 'like', '%[match:%');
                })
                ->get(['id', 'remarks']);

            foreach ($p2ps as $p2p) {
                if (preg_match('/\[(?:sn_match|match):([0-9a-fA-F-]{36})\]/', (string) $p2p->remarks, $m)) {
                    $lowerMatchId = strtolower($m[1]);
                    foreach ($matchToEvent as $mId => $eId) {
                        if (strtolower($mId) === $lowerMatchId) {
                            $p2pToEvent[(string) $p2p->id] = $eId;
                            break;
                        }
                    }
                }
            }
        }

        $coinsByEvent = [];
        if (Schema::hasTable('coins_ledger')) {
            $p2pIds = array_keys($p2pToEvent);
            $ledgerQuery = CoinsLedger::query()->where('amount', '>', 0)->where(function ($q) use ($p2pIds, $eventIds): void {
                $q->whereIn('source_id', $eventIds)->orWhereIn('reference', $eventIds);
                if ($p2pIds !== []) {
                    $q->orWhere(function ($sub) use ($p2pIds): void {
                        $sub->where('source_type', 'p2p_meeting')->whereIn('source_id', $p2pIds);
                    });
                }
            });
            $seenTx = [];
            foreach ($ledgerQuery->get(['transaction_id', 'amount', 'source_type', 'source_id', 'reference']) as $tx) {
                $txId = (string) $tx->transaction_id;
                if (isset($seenTx[$txId])) {
                    continue;
                }
                $seenTx[$txId] = true;
                $sid = (string) ($tx->source_id ?? '');
                $ref = (string) ($tx->reference ?? '');
                $resolvedEventId = $p2pToEvent[$sid]
                    ?? (isset($stats[$sid]) ? $sid : (isset($stats[$ref]) ? $ref : null));
                if ($resolvedEventId !== null) {
                    $coinsByEvent[$resolvedEventId] = ($coinsByEvent[$resolvedEventId] ?? 0) + (int) $tx->amount;
                }
            }
        }

        $impactByEvent = [];
        if (Schema::hasTable('life_impact_histories')) {
            $p2pIds = array_keys($p2pToEvent);
            $ihQuery = LifeImpactHistory::query();
            if (Schema::hasColumn('life_impact_histories', 'counted_in_total')) {
                $ihQuery->where(function ($q): void {
                    $q->where('counted_in_total', true)->orWhereNull('counted_in_total');
                });
            }
            if (Schema::hasColumn('life_impact_histories', 'status')) {
                $ihQuery->where(function ($q): void {
                    $q->where('status', 'approved')->orWhereNull('status');
                });
            }
            $isPgsql = DB::connection()->getDriverName() === 'pgsql';
            $ihQuery->where(function ($q) use ($p2pIds, $isPgsql): void {
                if ($isPgsql) {
                    $q->whereRaw("CAST(meta AS TEXT) LIKE '%speed_networking_event_id%'");
                } else {
                    $q->where('meta', 'like', '%speed_networking_event_id%');
                }
                if ($p2pIds !== []) {
                    $q->orWhere(function ($sub) use ($p2pIds): void {
                        $sub->where('activity_type', 'p2p_meeting')->whereIn('activity_id', $p2pIds);
                    });
                }
            });

            $seenImpact = [];
            foreach ($ihQuery->get() as $ih) {
                $hid = (string) $ih->id;
                if (isset($seenImpact[$hid])) {
                    continue;
                }
                $meta = is_array($ih->meta) ? $ih->meta : (json_decode((string) $ih->meta, true) ?: []);
                $aid = (string) ($ih->activity_id ?? '');
                $metaEid = (string) ($meta['speed_networking_event_id'] ?? '');
                $metaMid = (string) ($meta['match_id'] ?? '');
                $resolvedEventId = $p2pToEvent[$aid]
                    ?? ($metaEid !== '' && isset($stats[$metaEid]) ? $metaEid : ($matchToEvent[$metaMid] ?? null));
                if ($resolvedEventId !== null) {
                    $seenImpact[$hid] = true;
                    $val = max(0, (int) ($ih->impact_value ?: $ih->life_impacted ?: 0));
                    if ($val === 0) {
                        $val = $ih->resolveImpactValue();
                    }
                    $impactByEvent[$resolvedEventId] = ($impactByEvent[$resolvedEventId] ?? 0) + $val;
                }
            }
        }

        $now = Carbon::now();
        foreach ($eventIds as $eid) {
            $eRegs = $regsByEvent->get($eid, collect());
            $eParts = $partsByEvent->get($eid, collect());
            $eMatches = $matchesByEvent->get($eid, collect());
            $eSkips = $skipsByEvent->get($eid, collect());

            $activeRegUsers = [];
            $canceledUsers = [];
            $attendedUsers = [];

            foreach ($eRegs as $r) {
                $uid = (string) ($r->user_id ?? '');
                if ($uid === '') {
                    continue;
                }
                if (in_array(mb_strtolower((string) $r->status), ['cancelled', 'canceled'], true)) {
                    $canceledUsers[$uid] = true;
                } else {
                    $activeRegUsers[$uid] = true;
                    if (in_array(mb_strtolower((string) $r->checkin_status), ['checked_in', 'attended'], true)) {
                        $attendedUsers[$uid] = true;
                    }
                }
            }

            foreach ($eParts as $p) {
                $uid = (string) $p->user_id;
                if (isset($canceledUsers[$uid])) {
                    continue;
                }
                if ($p->joined_at !== null || $p->rounds_completed > 0 || $p->status !== SpeedNetworkingParticipant::STATUS_REGISTERED) {
                    $attendedUsers[$uid] = true;
                    if ($eRegs->isEmpty()) {
                        $activeRegUsers[$uid] = true;
                    }
                }
            }

            $connMade = 0;
            $completed = 0;
            $qualifying = 0;
            $uniquePairs = [];
            $uniquePeers = [];

            foreach ($eMatches as $m) {
                if ($m->isBreakRound()) {
                    continue;
                }
                $attendedUsers[(string) $m->user_a_id] = true;
                $attendedUsers[(string) $m->user_b_id] = true;
                if ($eRegs->isEmpty()) {
                    $activeRegUsers[(string) $m->user_a_id] = true;
                    $activeRegUsers[(string) $m->user_b_id] = true;
                }

                $ms = (string) ($m->match_status ?: $m->status);
                $isValid = in_array($ms, [SpeedNetworkingMatch::MATCH_STATUS_COMPLETED, SpeedNetworkingMatch::MATCH_STATUS_IN_CALL], true)
                    && $m->status !== SpeedNetworkingMatch::STATUS_SKIPPED;
                if (! $isValid) {
                    continue;
                }

                $connMade++;
                if ($ms === SpeedNetworkingMatch::MATCH_STATUS_COMPLETED) {
                    $completed++;
                }
                $dur = (int) ($m->duration_seconds ?? 0);
                if ($dur === 0 && ($m->call_started_at || $m->started_at)) {
                    $start = $m->call_started_at ?? $m->started_at;
                    $end = $m->ended_at ?? $now;
                    $dur = max(0, (int) $start->diffInSeconds($end, false));
                }
                if ($dur >= SpeedNetworkingMatch::MIN_QUALIFYING_DURATION_SECONDS) {
                    $qualifying++;
                }

                $pair = [(string) $m->user_a_id, (string) $m->user_b_id];
                sort($pair);
                $uniquePairs[$pair[0].':'.$pair[1]] = true;
                $uniquePeers[$pair[0]] = true;
                $uniquePeers[$pair[1]] = true;
            }

            // Include attended users who joined directly if registrations exist
            $registeredCount = count(array_unique(array_merge(
                array_keys($activeRegUsers),
                $eRegs->isEmpty() ? array_keys($attendedUsers) : []
            )));
            $attendedCount = count($attendedUsers);
            $attendancePct = $registeredCount > 0 ? round(($attendedCount / $registeredCount) * 100, 1) : 0.0;

            $stats[$eid] = [
                'registered_count' => $registeredCount,
                'attended_count' => $attendedCount,
                'canceled_count' => count($canceledUsers),
                'attendance_percentage' => $attendancePct,
                'connections_made' => $connMade,
                'completed_connections' => $completed,
                'qualifying_p2p_connections' => $qualifying,
                'unique_peers_connected' => count($uniquePeers),
                'unique_pairs_count' => count($uniquePairs),
                'total_coins_earned' => $coinsByEvent[$eid] ?? 0,
                'total_life_impact_generated' => $impactByEvent[$eid] ?? 0,
                'total_skips_count' => (int) $eSkips->sum(static fn ($r): int => max(1, (int) ($r->skip_count ?? 1))),
            ];
        }

        return $stats;
    }

    /**
     * Build chronological event activity feed from persisted timestamps across matches, joins, skips, blocks & rewards.
     *
     * @param  array<string, mixed>  $dataset
     * @return array<int, array<string, mixed>>
     */
    protected function buildActivityFeed(array $dataset): array
    {
        $feed = [];

        foreach ($dataset['roster'] as $row) {
            if (! empty($row['joined_at'])) {
                $feed[] = [
                    'id' => 'join-'.$row['user_id'],
                    'category' => 'waiting',
                    'type' => 'peer_joined',
                    'timestamp' => $row['joined_at'],
                    'message' => "{$row['name']} joined the Speed Collaboration session.",
                    'user_ids' => [$row['user_id']],
                    'round_number' => null,
                ];
            }
            if ($row['is_waiting_live'] && ! empty($row['waiting_since'])) {
                $feed[] = [
                    'id' => 'wait-'.$row['user_id'],
                    'category' => 'waiting',
                    'type' => 'peer_waiting',
                    'timestamp' => $row['waiting_since'],
                    'message' => "{$row['name']} is waiting for the next connection.",
                    'user_ids' => [$row['user_id']],
                    'round_number' => $dataset['event']['current_round'],
                ];
            }
        }

        foreach ($dataset['matches_enriched'] as $m) {
            if ($m['is_break_round']) {
                continue;
            }
            $nameA = $m['user_a']['name'] ?? 'Peer A';
            $nameB = $m['user_b']['name'] ?? 'Peer B';
            $uIds = array_values(array_filter([$m['user_a_id'], $m['user_b_id']]));

            $startTs = $m['started_at'] ?? $m['preview_started_at'];
            if ($startTs) {
                $feed[] = [
                    'id' => 'conn-start-'.$m['match_id'],
                    'category' => 'connection',
                    'type' => $m['match_status'] === SpeedNetworkingMatch::MATCH_STATUS_PREVIEW ? 'preview_started' : 'connection_started',
                    'timestamp' => $startTs,
                    'message' => "{$nameA} connected with {$nameB} (Round {$m['round_number']}).",
                    'user_ids' => $uIds,
                    'round_number' => $m['round_number'],
                ];
            }

            if ($m['match_status'] === SpeedNetworkingMatch::MATCH_STATUS_COMPLETED && ! empty($m['ended_at'])) {
                $feed[] = [
                    'id' => 'conn-end-'.$m['match_id'],
                    'category' => 'completed',
                    'type' => 'connection_completed',
                    'timestamp' => $m['ended_at'],
                    'message' => "{$nameA} & {$nameB} completed their Round {$m['round_number']} connection.",
                    'user_ids' => $uIds,
                    'round_number' => $m['round_number'],
                ];
            }
        }

        foreach ($dataset['skips'] as $s) {
            $ts = $s['updated_at'] ?? $s['created_at'];
            if ($ts) {
                $feed[] = [
                    'id' => 'skip-'.$s['id'],
                    'category' => 'skip',
                    'type' => 'peer_skipped',
                    'timestamp' => $ts,
                    'message' => "{$s['user']['name']} skipped {$s['skipped_peer']['name']} (Count: {$s['skip_count']}).",
                    'user_ids' => [$s['user']['id'], $s['skipped_peer']['id']],
                    'round_number' => null,
                ];
            }
        }

        foreach ($dataset['blocks'] as $b) {
            if (! empty($b['created_at'])) {
                $feed[] = [
                    'id' => 'block-'.$b['id'],
                    'category' => 'skip',
                    'type' => 'peer_blocked',
                    'timestamp' => $b['created_at'],
                    'message' => "{$b['blocker']['name']} blocked {$b['blocked_peer']['name']}.",
                    'user_ids' => [$b['blocker']['id'], $b['blocked_peer']['id']],
                    'round_number' => null,
                ];
            }
        }

        foreach ($dataset['rewards']['coin_transactions'] as $tx) {
            if (! empty($tx['created_at'])) {
                $amt = number_format($tx['amount']);
                $feed[] = [
                    'id' => 'coin-'.$tx['transaction_id'],
                    'category' => 'reward',
                    'type' => 'coins_credited',
                    'timestamp' => $tx['created_at'],
                    'message' => "{$tx['peer_name']} received {$amt} coins.",
                    'user_ids' => array_values(array_filter([$tx['user_id'], $tx['partner_user_id']])),
                    'round_number' => $tx['round_number'],
                ];
            }
        }

        foreach ($dataset['rewards']['impact_transactions'] as $tx) {
            if (! empty($tx['created_at'])) {
                $feed[] = [
                    'id' => 'impact-'.$tx['transaction_id'],
                    'category' => 'reward',
                    'type' => 'life_impact_recorded',
                    'timestamp' => $tx['created_at'],
                    'message' => "{$tx['peer_name']}'s Life Impact increased by {$tx['impact_value']}.",
                    'user_ids' => array_values(array_filter([$tx['user_id'], $tx['partner_user_id']])),
                    'round_number' => $tx['round_number'],
                ];
            }
        }

        usort($feed, static fn (array $a, array $b): int => strcmp((string) $b['timestamp'], (string) $a['timestamp']));

        return $feed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $userMatches
     */
    protected function estimatePeerWaitingSeconds(array $userMatches, int $currentWaitingSeconds): int
    {
        $gapSeconds = 0;
        if (count($userMatches) > 1) {
            $sorted = $userMatches;
            usort($sorted, static fn (array $a, array $b): int => strcmp((string) ($a['started_at'] ?? ''), (string) ($b['started_at'] ?? '')));
            for ($i = 1, $len = count($sorted); $i < $len; $i++) {
                $prevEnd = $sorted[$i - 1]['ended_at'] ?? null;
                $currStart = $sorted[$i]['preview_started_at'] ?? $sorted[$i]['started_at'] ?? null;
                if ($prevEnd && $currStart) {
                    $diff = (int) Carbon::parse($prevEnd)->diffInSeconds(Carbon::parse($currStart), false);
                    if ($diff > 0) {
                        $gapSeconds += $diff;
                    }
                }
            }
        }

        return $gapSeconds + $currentWaitingSeconds;
    }

    /**
     * @return array{raw_status: string, display_status: string, label: string}
     */
    protected function normalizeEventStatus(?string $status): array
    {
        $raw = mb_strtolower(trim((string) $status));

        return match ($raw) {
            'in_progress', 'live', 'lobby_open' => [
                'raw_status' => $raw,
                'display_status' => 'live',
                'label' => 'Live',
            ],
            'paused' => [
                'raw_status' => $raw,
                'display_status' => 'paused',
                'label' => 'Paused',
            ],
            'completed', 'ended' => [
                'raw_status' => $raw,
                'display_status' => 'completed',
                'label' => 'Completed',
            ],
            'cancelled', 'canceled' => [
                'raw_status' => $raw,
                'display_status' => 'canceled',
                'label' => 'Canceled',
            ],
            default => [
                'raw_status' => $raw !== '' ? $raw : 'scheduled',
                'display_status' => 'upcoming',
                'label' => 'Upcoming',
            ],
        };
    }

    protected function humanizeMatchStatus(string $matchStatus, bool $isBreak): string
    {
        if ($isBreak) {
            return 'Break Round';
        }

        return match ($matchStatus) {
            SpeedNetworkingMatch::MATCH_STATUS_PREVIEW => 'Preview Handshake',
            SpeedNetworkingMatch::MATCH_STATUS_IN_CALL => 'Connected (In Call)',
            SpeedNetworkingMatch::MATCH_STATUS_COMPLETED => 'Connection Completed',
            SpeedNetworkingMatch::MATCH_STATUS_SKIPPED => 'Skipped',
            SpeedNetworkingMatch::MATCH_STATUS_BLOCKED => 'Blocked',
            SpeedNetworkingMatch::MATCH_STATUS_EXPIRED => 'Preview Expired',
            default => ucfirst(str_replace('_', ' ', $matchStatus)),
        };
    }

    protected function humanizeParticipantStatus(string $rawStatus, string $eventDisplayStatus, bool $attended, bool $isCanceled): string
    {
        if ($isCanceled) {
            return 'Canceled';
        }

        if ($eventDisplayStatus === 'completed' && $attended && ! in_array($rawStatus, ['in_call', 'preview'], true)) {
            return 'Session Completed';
        }

        return match ($rawStatus) {
            SpeedNetworkingParticipant::STATUS_IN_CALL => 'Connected (In Call)',
            SpeedNetworkingParticipant::STATUS_PREVIEW, SpeedNetworkingParticipant::STATUS_ACCEPTED => 'Starting Connection',
            SpeedNetworkingParticipant::STATUS_WAITING, SpeedNetworkingParticipant::STATUS_SEARCHING, SpeedNetworkingParticipant::STATUS_NEXT_PEER_REQUIRED, SpeedNetworkingParticipant::STATUS_IDLE => 'Waiting for Next Connection',
            SpeedNetworkingParticipant::STATUS_LOBBY => 'In Event Lobby',
            SpeedNetworkingParticipant::STATUS_BREAK => 'On Break',
            SpeedNetworkingParticipant::STATUS_LEFT => 'Disconnected',
            SpeedNetworkingParticipant::STATUS_REGISTERED => 'Not Started',
            default => ucfirst(str_replace('_', ' ', $rawStatus)),
        };
    }

    /**
     * @return array<string, int|float>
     */
    protected function emptyEventListStats(): array
    {
        return [
            'registered_count' => 0,
            'attended_count' => 0,
            'canceled_count' => 0,
            'attendance_percentage' => 0.0,
            'connections_made' => 0,
            'completed_connections' => 0,
            'qualifying_p2p_connections' => 0,
            'unique_peers_connected' => 0,
            'unique_pairs_count' => 0,
            'total_coins_earned' => 0,
            'total_life_impact_generated' => 0,
            'total_skips_count' => 0,
        ];
    }
}
