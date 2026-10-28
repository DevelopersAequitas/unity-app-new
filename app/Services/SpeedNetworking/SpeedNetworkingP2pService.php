<?php

declare(strict_types=1);

namespace App\Services\SpeedNetworking;

use App\Models\FileModel;
use App\Models\P2pMeeting;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\SpeedNetworking\SpeedNetworkingMatch;
use App\Models\User;
use App\Services\Coins\CoinsService;
use App\Services\Creative\SpeedNetworkingP2pCreativeGenerator;
use App\Services\LifeImpact\LifeImpactService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SpeedNetworkingP2pService
{
    protected SpeedNetworkingP2pCreativeGenerator $creativeGenerator;

    protected CoinsService $coinsService;

    protected LifeImpactService $lifeImpactService;

    public function __construct(
        ?SpeedNetworkingP2pCreativeGenerator $creativeGenerator = null,
        ?CoinsService $coinsService = null,
        ?LifeImpactService $lifeImpactService = null
    ) {
        $this->creativeGenerator = $creativeGenerator ?? app(SpeedNetworkingP2pCreativeGenerator::class);
        $this->coinsService = $coinsService ?? app(CoinsService::class);
        $this->lifeImpactService = $lifeImpactService ?? app(LifeImpactService::class);
    }

    /**
     * Convert a completed Speed Networking match between two peers into a P2P meeting,
     * generate its creative poster, reward coins, and increase life impact for both peers.
     * Note: Timeline post is deliberately NOT created (so it only displays in P2P history).
     */
    public function completeMatchAsP2pMeeting(
        SpeedNetworkingMatch $match,
        SpeedNetworkingEvent $event
    ): ?P2pMeeting {
        if (! Schema::hasTable('p2p_meetings')) {
            return null;
        }

        if ($match->isBreakRound() || empty($match->user_a_id) || empty($match->user_b_id)) {
            return null;
        }

        $userAId = (string) $match->user_a_id;
        $userBId = (string) $match->user_b_id;

        if ($userAId === $userBId) {
            return null;
        }

        // 1. Idempotency Check: check if P2pMeeting was already recorded for this match
        $existing = P2pMeeting::query()
            ->where(function ($q) use ($userAId, $userBId): void {
                $q->where(function ($sub) use ($userAId, $userBId): void {
                    $sub->where('initiator_user_id', $userAId)
                        ->where('peer_user_id', $userBId);
                })->orWhere(function ($sub) use ($userAId, $userBId): void {
                    $sub->where('initiator_user_id', $userBId)
                        ->where('peer_user_id', $userAId);
                });
            })
            ->where(function ($q) use ($match): void {
                $q->where('remarks', 'like', "%[sn_match:{$match->id}]%")
                    ->orWhere('remarks', 'like', "%[match:{$match->id}]%");
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        /** @var User|null $userA */
        $userA = User::find($userAId);
        /** @var User|null $userB */
        $userB = User::find($userBId);

        if (! $userA || ! $userB) {
            return null;
        }

        // 2. Generate composite creative featuring both peers
        $fileModel = null;
        try {
            $fileModel = $this->creativeGenerator->generate($userA, $userB, $event, $match);
        } catch (Throwable $creativeEx) {
            Log::error('Could not generate speed networking creative, proceeding with meeting creation: '.$creativeEx->getMessage(), [
                'match_id' => $match->id,
                'event_id' => $event->id,
            ]);
        }

        // 3. Format Media Array for P2pMeeting
        $media = [];
        if ($fileModel instanceof FileModel) {
            $media[] = [
                'file_id' => (string) $fileModel->id,
                'media_type' => 'image',
                'url' => url('/api/v1/files/'.$fileModel->id),
            ];
        }

        $meetingDate = $match->started_at
            ? $match->started_at->toDateString()
            : ($event->start_date ? Carbon::parse($event->start_date)->toDateString() : now()->toDateString());

        $meetingPlace = 'Speed Networking - '.trim($event->title ?: 'Virtual Networking');
        $remarks = "Speed Networking Round {$match->round_number} - {$event->title} [sn_match:{$match->id}]";

        // 4. Create P2pMeeting (Without Timeline Feed Post)
        return DB::transaction(function () use ($userA, $userB, $meetingDate, $meetingPlace, $remarks, $media, $match, $event): P2pMeeting {
            // Concurrency lock check
            $lockedExisting = P2pMeeting::query()
                ->where(function ($q) use ($userA, $userB): void {
                    $q->where(function ($sub) use ($userA, $userB): void {
                        $sub->where('initiator_user_id', $userA->id)
                            ->where('peer_user_id', $userB->id);
                    })->orWhere(function ($sub) use ($userA, $userB): void {
                        $sub->where('initiator_user_id', $userB->id)
                            ->where('peer_user_id', $userA->id);
                    });
                })
                ->where(function ($q) use ($match): void {
                    $q->where('remarks', 'like', "%[sn_match:{$match->id}]%")
                        ->orWhere('remarks', 'like', "%[match:{$match->id}]%");
                })
                ->lockForUpdate()
                ->first();

            if ($lockedExisting) {
                return $lockedExisting;
            }

            $meeting = P2pMeeting::create([
                'initiator_user_id' => (string) $userA->id,
                'peer_user_id' => (string) $userB->id,
                'meeting_date' => $meetingDate,
                'meeting_place' => $meetingPlace,
                'remarks' => $remarks,
                'media' => $media,
                'is_deleted' => false,
            ]);

            // 5. Reward Coins for P2P Meeting to BOTH users
            try {
                $this->coinsService->rewardForActivity(
                    $userA,
                    'p2p_meeting',
                    null,
                    'Speed Networking P2P Meeting',
                    (string) $userA->id,
                    'p2p_meeting',
                    (string) $meeting->id
                );

                $this->coinsService->rewardForActivity(
                    $userB,
                    'p2p_meeting',
                    null,
                    'Speed Networking P2P Meeting',
                    (string) $userB->id,
                    'p2p_meeting',
                    (string) $meeting->id
                );
            } catch (Throwable $coinEx) {
                Log::warning('Could not award coins for speed networking P2P meeting: '.$coinEx->getMessage());
            }

            // 6. Increase Life Impact for BOTH users
            try {
                $impactPoints = (int) config('impact.activity_rewards.p2p_meeting', 5);
                if ($impactPoints <= 0) {
                    $impactPoints = 5;
                }

                $userAName = $userA->display_name ?: $userA->name ?: 'Peer';
                $userBName = $userB->display_name ?: $userB->name ?: 'Peer';

                $this->lifeImpactService->addLifeImpact(
                    (string) $userA->id,
                    (string) $userB->id,
                    'p2p_meeting',
                    (string) $meeting->id,
                    $impactPoints,
                    'Speed Networking 1-on-1 Meeting',
                    "Completed a Speed Networking 1-on-1 session with {$userBName}",
                    [
                        'meeting_date' => $meeting->meeting_date,
                        'meeting_place' => $meeting->meeting_place,
                        'remarks' => $meeting->remarks,
                        'peer_user_id' => (string) $userB->id,
                        'speed_networking_event_id' => (string) $event->id,
                        'match_id' => (string) $match->id,
                        'round_number' => $match->round_number,
                    ]
                );

                $this->lifeImpactService->addLifeImpact(
                    (string) $userB->id,
                    (string) $userA->id,
                    'p2p_meeting',
                    (string) $meeting->id,
                    $impactPoints,
                    'Speed Networking 1-on-1 Meeting',
                    "Completed a Speed Networking 1-on-1 session with {$userAName}",
                    [
                        'meeting_date' => $meeting->meeting_date,
                        'meeting_place' => $meeting->meeting_place,
                        'remarks' => $meeting->remarks,
                        'peer_user_id' => (string) $userA->id,
                        'speed_networking_event_id' => (string) $event->id,
                        'match_id' => (string) $match->id,
                        'round_number' => $match->round_number,
                    ]
                );
            } catch (Throwable $impactEx) {
                Log::warning('Could not add life impact for speed networking P2P meeting: '.$impactEx->getMessage());
            }

            // CRITICAL: Timeline post is NOT created here, ensuring it only appears in P2P history!
            Log::info('[SpeedNetworkingP2pService] Successfully recorded P2P meeting from Speed Networking match', [
                'meeting_id' => $meeting->id,
                'match_id' => $match->id,
                'user_a_id' => $userA->id,
                'user_b_id' => $userB->id,
            ]);

            return $meeting;
        });
    }

    /**
     * Process all completed matches for a given event and convert to P2P meetings.
     * Optionally filtered for a specific user.
     *
     * @return Collection<int, P2pMeeting>
     */
    public function completeAllMatchesForEvent(
        SpeedNetworkingEvent $event,
        ?string $userId = null
    ): Collection {
        if (! Schema::hasTable('p2p_meetings') || ! Schema::hasTable('speed_networking_matches')) {
            return collect();
        }

        $query = SpeedNetworkingMatch::query()
            ->where('event_id', $event->id)
            ->whereNotNull('user_b_id');

        if ($userId !== null && $userId !== '') {
            $query->where(function ($q) use ($userId): void {
                $q->where('user_a_id', $userId)
                    ->orWhere('user_b_id', $userId);
            });
        }

        $matches = $query->get();
        $p2pMeetings = collect();

        foreach ($matches as $match) {
            try {
                $meeting = $this->completeMatchAsP2pMeeting($match, $event);
                if ($meeting) {
                    $p2pMeetings->push($meeting);
                }
            } catch (Throwable $e) {
                Log::error('Failed to convert match to P2P meeting', [
                    'match_id' => $match->id,
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $p2pMeetings;
    }

    /**
     * Find creative image URL for a given match if already generated.
     */
    public function getCreativeUrlForMatch(SpeedNetworkingMatch $match): ?string
    {
        if (! Schema::hasTable('p2p_meetings')) {
            return null;
        }

        $meeting = P2pMeeting::query()
            ->where(function ($q) use ($match): void {
                $q->where('remarks', 'like', "%[sn_match:{$match->id}]%")
                    ->orWhere('remarks', 'like', "%[match:{$match->id}]%");
            })
            ->first();

        if (! $meeting || empty($meeting->media)) {
            return null;
        }

        $media = is_array($meeting->media) ? $meeting->media : json_decode((string) $meeting->media, true);
        if (empty($media)) {
            return null;
        }

        foreach ($media as $item) {
            if (! empty($item['url'])) {
                return (string) $item['url'];
            }
            if (! empty($item['file_id'])) {
                return url('/api/v1/files/'.$item['file_id']);
            }
        }

        return null;
    }
}
