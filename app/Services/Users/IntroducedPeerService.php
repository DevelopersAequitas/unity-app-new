<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\User;
use App\Services\Creative\IntroductionCreativeService;
use App\Services\Notifications\MilestoneConnectorWhatsappService;
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

    public function __construct(
        UserMilestoneSyncService $milestoneSyncService,
        PeerIntroductionService $peerIntroductionService,
        MilestoneConnectorWhatsappService $connectorWhatsappService,
        IntroductionCreativeService $introductionCreativeService
    ) {
        $this->milestoneSyncService = $milestoneSyncService;
        $this->peerIntroductionService = $peerIntroductionService;
        $this->connectorWhatsappService = $connectorWhatsappService;
        $this->introductionCreativeService = $introductionCreativeService;
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
            $lockedUser->save();

            $user->members_introduced_count = $count;

            // Sync user milestones
            $this->milestoneSyncService->sync($lockedUser);
        });

        // Trigger introduction creative rendering, timeline post and notifications if newly introduced
        if ($isNewIntroduction) {
            $this->peerIntroductionService->handlePeerIntroduction($user, $introducedUser);

            // Generate and store milestone creative if count matches a configured milestone required_count
            $creative = null;
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

            // Safely trigger milestone_connector WhatsApp notification for first introduction ONLY
            if ($count === 1) {
                try {
                    $this->connectorWhatsappService->handleFirstIntroduction($user, $creative?->image_url);
                } catch (Throwable $whatsappEx) {
                    Log::error('[IntroducedPeerService] Failed triggering milestone connector WhatsApp: '.$whatsappEx->getMessage(), [
                        'user_id' => $user->id,
                        'exception' => $whatsappEx,
                    ]);
                }
            }
        }

        return $introducedUser;
    }
}
