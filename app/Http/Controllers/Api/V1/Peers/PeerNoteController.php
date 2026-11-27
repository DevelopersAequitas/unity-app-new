<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Peers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Peers\SavePeerNoteRequest;
use App\Models\PeerNote;
use App\Models\User;
use App\Services\Peers\PeerNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class PeerNoteController extends BaseApiController
{
    public function __construct(
        private readonly PeerNoteService $peerNoteService,
    ) {}

    /**
     * Get the authenticated user's private note for a peer.
     */
    public function show(Request $request, string $peerId): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        if (Schema::hasTable('peer_notes')) {
            $existing = PeerNote::query()
                ->where('user_id', (string) $authUser->id)
                ->where('peer_id', $peerId)
                ->first();

            if ($existing) {
                Gate::forUser($authUser)->authorize('view', $existing);
            }
        }

        $payload = $this->peerNoteService->getNoteForPeer($authUser, $peerId);

        return response()->json([
            'success' => true,
            'data' => [
                'peer_id' => $payload['peer_id'],
                'note' => $payload['note'],
                'updated_at' => $payload['updated_at'],
            ],
        ], 200);
    }

    /**
     * Save, update, or clear the authenticated user's private note for a peer.
     */
    public function upsert(SavePeerNoteRequest $request, string $peerId): JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        if ((string) $authUser->id === $peerId) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot save a private note for yourself.',
                'data' => null,
            ], 422);
        }

        if (! User::query()->whereKey($peerId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Peer not found.',
                'data' => null,
            ], 404);
        }

        if (Schema::hasTable('peer_notes')) {
            $existing = PeerNote::query()
                ->where('user_id', (string) $authUser->id)
                ->where('peer_id', $peerId)
                ->first();

            if ($existing) {
                Gate::forUser($authUser)->authorize('update', $existing);
            }
        }

        $validated = $request->validated();
        $noteInput = array_key_exists('note', $validated)
            ? $validated['note']
            : ($validated['notes'] ?? null);

        $payload = $this->peerNoteService->saveNoteForPeer(
            $authUser,
            $peerId,
            is_string($noteInput) ? $noteInput : null,
            (string) ($validated['source'] ?? PeerNote::SOURCE_PROFILE),
        );

        return response()->json([
            'success' => true,
            'message' => 'Note saved successfully',
            'data' => [
                'peer_id' => $payload['peer_id'],
                'note' => $payload['note'],
                'updated_at' => $payload['updated_at'],
            ],
        ], 200);
    }
}
