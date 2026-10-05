<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\User;
use App\Services\Coins\CoinsService;
use App\Services\Creative\IntroductionCreativeService;
use App\Services\MilestoneBadgeService;
use App\Services\Notifications\MilestoneCatalystWhatsappService;
use App\Services\Notifications\MilestoneConnectorWhatsappService;
use App\Services\Notifications\MilestoneWhatsappNotificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

class IntroducedPeerService
{
    protected UserMilestoneSyncService $milestoneSyncService;

    protected PeerIntroductionService $peerIntroductionService;

    protected MilestoneConnectorWhatsappService $connectorWhatsappService;

    protected IntroductionCreativeService $introductionCreativeService;

    protected MilestoneCatalystWhatsappService $catalystWhatsappService;

    protected MilestoneWhatsappNotificationService $milestoneWhatsappService;

    protected CoinsService $coinsService;

    public function __construct(
        UserMilestoneSyncService $milestoneSyncService,
        PeerIntroductionService $peerIntroductionService,
        MilestoneConnectorWhatsappService $connectorWhatsappService,
        IntroductionCreativeService $introductionCreativeService,
        MilestoneCatalystWhatsappService $catalystWhatsappService,
        MilestoneWhatsappNotificationService $milestoneWhatsappService,
        ?CoinsService $coinsService = null
    ) {
        $this->milestoneSyncService = $milestoneSyncService;
        $this->peerIntroductionService = $peerIntroductionService;
        $this->connectorWhatsappService = $connectorWhatsappService;
        $this->introductionCreativeService = $introductionCreativeService;
        $this->catalystWhatsappService = $catalystWhatsappService;
        $this->milestoneWhatsappService = $milestoneWhatsappService;
        $this->coinsService = $coinsService ?? app(CoinsService::class);
    }

    /**
     * Get the list of peers introduced or referred by the given user.
     *
     * @return Collection<int, User>
     */
    public function getIntroducedPeers(User $user): Collection
    {
        return User::query()
            ->where(function ($query) use ($user): void {
                $query->where('introduced_by', $user->id);

                if (Schema::hasColumn('users', 'referred_by_user_id')) {
                    $query->orWhere('referred_by_user_id', $user->id);
                }

                if (Schema::hasTable('referraldata')) {
                    $query->orWhereIn('id', function ($sub) use ($user): void {
                        $sub->select('referred_user_id')
                            ->from('referraldata')
                            ->where('referrer_user_id', $user->id)
                            ->whereNotNull('referred_user_id');
                    });
                }

                if (Schema::hasTable('peer_recommendations')) {
                    $query->orWhereIn('phone', function ($sub) use ($user): void {
                        $sub->select('peer_mobile')
                            ->from('peer_recommendations')
                            ->where('user_id', $user->id)
                            ->whereNotNull('peer_mobile');
                    });
                }
            })
            ->where('id', '!=', $user->id)
            ->whereNull('deleted_at')
            ->where(function ($statusQuery): void {
                $statusQuery->whereNull('status')->orWhere('status', 'active');
            })
            ->where('status', '!=', 'inactive')
            ->with(['city', 'profilePhotoFile', 'coverPhotoFile', 'introducedBy', 'level4Category:id,name', 'businessCategory:id,name'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get paginated peers introduced or referred by the given user, sorted by introduced_count DESC.
     */
    public function getIntroducedPeersWithCount(User $user, int $perPage = 20, int $page = 1)
    {
        return User::query()
            ->where(function ($query) use ($user): void {
                $query->where('introduced_by', $user->id);

                if (Schema::hasColumn('users', 'referred_by_user_id')) {
                    $query->orWhere('referred_by_user_id', $user->id);
                }

                if (Schema::hasTable('referraldata')) {
                    $query->orWhereIn('id', function ($sub) use ($user): void {
                        $sub->select('referred_user_id')
                            ->from('referraldata')
                            ->where('referrer_user_id', $user->id)
                            ->whereNotNull('referred_user_id');
                    });
                }

                if (Schema::hasTable('peer_recommendations')) {
                    $query->orWhereIn('phone', function ($sub) use ($user): void {
                        $sub->select('peer_mobile')
                            ->from('peer_recommendations')
                            ->where('user_id', $user->id)
                            ->whereNotNull('peer_mobile');
                    });
                }
            })
            ->where('id', '!=', $user->id)
            ->whereNull('deleted_at')
            ->where(function ($statusQuery): void {
                $statusQuery->whereNull('status')->orWhere('status', 'active');
            })
            ->where('status', '!=', 'inactive')
            ->withCount(['introducedPeers as introduced_count' => function ($q): void {
                $q->whereNull('deleted_at');
            }])
            ->with([
                'city:id,name,country,country_code',
                'profilePhotoFile',
                'coverPhotoFile',
                'introducedBy',
                'level4Category:id,name',
                'businessCategory:id,name',
            ])
            ->orderByDesc('introduced_count')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Introduce a peer.
     *
     * @param  User  $user  The authenticated user who is introducing.
     * @param  string  $peerId  The ID of the peer being introduced.
     * @param  string|null  $introductionRequestId  Optional introduction request ID.
     * @return User The introduced user.
     *
     * @throws InvalidArgumentException
     */
    public function introducePeer(User $user, string $peerId, ?string $introductionRequestId = null): User
    {
        if ($user->id === $peerId) {
            throw new InvalidArgumentException('You cannot introduce yourself.');
        }

        $count = 0;
        $isNewIntroduction = false;

        DB::transaction(function () use ($user, $peerId, &$introducedUser, &$count, &$isNewIntroduction): void {
            /** @var User $lockedPeer */
            $lockedPeer = User::where('id', $peerId)->lockForUpdate()->firstOrFail();
            $introducedUser = $lockedPeer;

            if ($lockedPeer->introduced_by !== null && $lockedPeer->introduced_by !== $user->id) {
                throw new InvalidArgumentException('This peer has already been introduced by another member.');
            }

            if ($lockedPeer->introduced_by === null) {
                $lockedPeer->introduced_by = $user->id;
                if (Schema::hasColumn('users', 'referred_by_user_id') && blank($lockedPeer->referred_by_user_id)) {
                    $lockedPeer->referred_by_user_id = $user->id;
                }
                $lockedPeer->save();
                $isNewIntroduction = true;
            }

            // Always recalculate members_introduced_count for the introducing user from actual DB count
            $count = User::query()
                ->where(function ($q) use ($user): void {
                    $q->where('introduced_by', $user->id);
                    if (Schema::hasColumn('users', 'referred_by_user_id')) {
                        $q->orWhere('referred_by_user_id', $user->id);
                    }
                    if (Schema::hasTable('referraldata')) {
                        $q->orWhereIn('id', function ($sub) use ($user): void {
                            $sub->select('referred_user_id')
                                ->from('referraldata')
                                ->where('referrer_user_id', $user->id)
                                ->whereNotNull('referred_user_id');
                        });
                    }
                })
                ->where('id', '!=', $user->id)
                ->whereNull('deleted_at')
                ->count();

            // Always update introducer's count and persist
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();
            $lockedUser->members_introduced_count = $count;
            $lockedUser->saveQuietly();

            $user->members_introduced_count = $count;

            // Sync user milestones
            $this->milestoneSyncService->sync($lockedUser);
        });

        // Trigger introduction creative rendering, timeline post and notifications if newly introduced
        if ($isNewIntroduction) {
            // Reward 1000 coins to the introducing member
            $rewardCoins = (int) (config('coins.activity_rewards.recommend_peer') ?? config('coins.recommend_peer') ?? 1000);
            if ($rewardCoins > 0) {
                try {
                    $this->coinsService->reward(
                        $user,
                        $rewardCoins,
                        'referral_signup:'.$peerId,
                        [
                            'source' => 'introduce_peer',
                            'peer_id' => $peerId,
                            'coins' => $rewardCoins,
                        ],
                        $user->id
                    );
                    $user->refresh();
                } catch (Throwable $coinEx) {
                    Log::error('[IntroducedPeerService] Failed awarding coins for peer introduction: '.$coinEx->getMessage(), [
                        'user_id' => $user->id,
                        'peer_id' => $peerId,
                    ]);
                }
            }

            $this->peerIntroductionService->handlePeerIntroduction($user, $introducedUser);

            // Generate and store milestone creative ONLY if count matches a configured milestone required_count
            $creative = null;
            if ($this->introductionCreativeService->isConfiguredMilestone($count)) {
                try {
                    $creative = $this->introductionCreativeService->handleIntroductionCreative(
                        $user,
                        $introducedUser,
                        $count,
                        $introductionRequestId
                    );
                } catch (Throwable $creativeEx) {
                    Log::error('[IntroducedPeerService] Failed storing introduction creative: '.$creativeEx->getMessage(), [
                        'user_id' => $user->id,
                        'introduced_id' => $introducedUser->id,
                        'exception' => $creativeEx,
                    ]);
                }
            }

            // Explicitly calculate and award milestone badges in user_milestone_badges
            app(MilestoneBadgeService::class)->calculateForUser($user);

            // Trigger milestone WhatsApp notification workflow for exact milestone counts
            try {
                $this->milestoneWhatsappService->handleMilestoneNotification(
                    $user,
                    $count,
                    $creative?->image_url
                );
            } catch (Throwable $milestoneEx) {
                Log::error('[IntroducedPeerService] Failed triggering milestone WhatsApp notification: '.$milestoneEx->getMessage(), [
                    'user_id' => $user->id,
                    'introduced_id' => $introducedUser->id,
                    'count' => $count,
                    'exception' => $milestoneEx,
                ]);
            }
        }

        return $introducedUser;
    }
}
