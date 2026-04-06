<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\BlockPeerRequest;
use App\Http\Resources\BlockedPeerResource;
use App\Models\PeerBlock;
use App\Models\User;
use App\Services\Blocks\PeerBlockService;
use Illuminate\Http\Request;

class PeerBlockController extends BaseApiController
{
    public function __construct(private readonly PeerBlockService $peerBlockService)
    {
    }

    public function index(Request $request)
    {
        $blocks = PeerBlock::query()
            ->where('blocker_user_id', (string) $request->user()->id)
            ->with('blocked:id,display_name,first_name,last_name,company_name,designation,profile_photo_url')
            ->orderByDesc('created_at')
            ->get();

        return $this->success([
            'items' => BlockedPeerResource::collection($blocks),
        ]);
    }

    public function store(BlockPeerRequest $request, User $user)
    {
        $authUser = $request->user();

        if ((string) $authUser->id === (string) $user->id) {
            return $this->error('You cannot block yourself.', 422);
        }

        $alreadyBlocked = $this->peerBlockService->hasBlocked((string) $authUser->id, (string) $user->id);

        $this->peerBlockService->block($authUser, $user, $request->validated('reason'));

        return $this->success(null, $alreadyBlocked ? 'Peer is already blocked.' : 'Peer blocked successfully.');
    }

    public function destroy(Request $request, User $user)
    {
        $authUser = $request->user();

        if ((string) $authUser->id === (string) $user->id) {
            return $this->error('You cannot unblock yourself.', 422);
        }

        $deleted = $this->peerBlockService->unblock($authUser, $user);

        return $this->success(null, $deleted ? 'Peer unblocked successfully.' : 'Peer is already unblocked.');
    }

    public function status(Request $request, User $user)
    {
        $authUser = $request->user();

        if ((string) $authUser->id === (string) $user->id) {
            return $this->success([
                'is_blocked_by_me' => false,
                'has_blocked_me' => false,
                'cannot_interact' => false,
            ]);
        }

        $isBlockedByMe = $this->peerBlockService->hasBlocked((string) $authUser->id, (string) $user->id);
        $hasBlockedMe = $this->peerBlockService->hasBlocked((string) $user->id, (string) $authUser->id);

        return $this->success([
            'is_blocked_by_me' => $isBlockedByMe,
            'has_blocked_me' => $hasBlockedMe,
            'cannot_interact' => $isBlockedByMe || $hasBlockedMe,
        ]);
    }
}
