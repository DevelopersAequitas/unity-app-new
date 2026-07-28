<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class IntroducedPeerService
{
    protected UserMilestoneSyncService $milestoneSyncService;
    protected PeerIntroductionService $peerIntroductionService;

    public function __construct(
        UserMilestoneSyncService $milestoneSyncService,
        PeerIntroductionService $peerIntroductionService
    ) {
        $this->milestoneSyncService = $milestoneSyncService;
        $this->peerIntroductionService = $peerIntroductionService;
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
     * @return User The introduced user.
     *
     * @throws InvalidArgumentException
     */
    public function introducePeer(User $user, string $peerId): User
    {
        if ($user->id === $peerId) {
            throw new InvalidArgumentException('You cannot introduce yourself.');
        }

        $introducedUser = User::findOrFail($peerId);

        if ($introducedUser->introduced_by === $user->id) {
            return $introducedUser;
        }

        if ($introducedUser->introduced_by !== null) {
            throw new InvalidArgumentException('This peer has already been introduced by another member.');
        }

        DB::transaction(function () use ($user, $introducedUser) {
            $introducedUser->introduced_by = $user->id;
            $introducedUser->save();

            // Recalculate members_introduced_count for the introducing user
            $count = User::where('introduced_by', $user->id)->count();
            $user->members_introduced_count = $count;
            $user->save();

            // Sync user milestones
            $this->milestoneSyncService->sync($user);
        });

        // Trigger introduction creative rendering, timeline post and notifications
        $this->peerIntroductionService->handlePeerIntroduction($user, $introducedUser);

        return $introducedUser;
    }
}
