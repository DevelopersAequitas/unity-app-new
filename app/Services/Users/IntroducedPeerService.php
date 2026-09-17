<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\User;
use App\Services\Creative\IntroductionCreativeService;
use App\Services\MilestoneBadgeService;
use App\Services\Notifications\MilestoneCatalystWhatsappService;
use App\Services\Notifications\MilestoneConnectorWhatsappService;
use App\Services\Notifications\MilestoneWhatsappNotificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    public function __construct(
        UserMilestoneSyncService $milestoneSyncService,
        PeerIntroductionService $peerIntroductionService,
        MilestoneConnectorWhatsappService $connectorWhatsappService,
        IntroductionCreativeService $introductionCreativeService,
        MilestoneCatalystWhatsappService $catalystWhatsappService,
        MilestoneWhatsappNotificationService $milestoneWhatsappService
    ) {
        $this->milestoneSyncService = $milestoneSyncService;
        $this->peerIntroductionService = $peerIntroductionService;
        $this->connectorWhatsappService = $connectorWhatsappService;
        $this->introductionCreativeService = $introductionCreativeService;
        $this->catalystWhatsappService = $catalystWhatsappService;
        $this->milestoneWhatsappService = $milestoneWhatsappService;
    }

    /**
     * Get the list of peers introduced by the given user.
     *
     * @return Collection<int, User>
     */
    public function getIntroducedPeers(User $user): Collection
    {
        return User::query()
            ->where('introduced_by', $user->id)
            ->with(['city', 'profilePhotoFile', 'coverPhotoFile', 'introducedBy'])
            ->get();
    }

    /**
     * Get paginated peers introduced by the given user, sorted by introduced_count DESC.
     */
    public function getIntroducedPeersWithCount(User $user, int $perPage = 20, int $page = 1)
    {
        return User::query()
            ->where('introduced_by', $user->id)
            ->whereNull('deleted_at')
            ->where(function ($statusQuery) {
                $statusQuery->whereNull('status')->orWhere('status', 'active');
            })
            ->withCount(['introducedPeers as introduced_count' => function ($q) {
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
                $lockedPeer->save();
                $isNewIntroduction = true;
            }

            // Always recalculate members_introduced_count for the introducing user from actual DB count
            $count = User::where('introduced_by', $user->id)->count();

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
