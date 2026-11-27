<?php

declare(strict_types=1);

namespace App\Services\Peers;

use App\Models\PeerNote;
use App\Models\SpeedNetworking\SpeedNetworkingMatch;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class PeerNoteService
{
    /**
     * Retrieve the authenticated user's private note for a given peer.
     * Falls back to the user's latest non-empty Speed Collaboration note for that peer.
     *
     * @return array{peer_id: string, note: string, source: ?string, updated_at: ?string}
     */
    public function getNoteForPeer(User $authUser, string $peerId): array
    {
        $authId = (string) $authUser->id;

        if ($authId !== '' && $authId !== $peerId && Schema::hasTable('peer_notes')) {
            $record = PeerNote::query()
                ->where('user_id', $authId)
                ->where('peer_id', $peerId)
                ->first();

            if ($record) {
                return [
                    'peer_id' => $peerId,
                    'note' => (string) ($record->note ?? ''),
                    'source' => (string) ($record->source ?? PeerNote::SOURCE_PROFILE),
                    'updated_at' => $record->updated_at?->toISOString(),
                ];
            }
        }

        if ($authId !== '' && $authId !== $peerId) {
            $speedNote = $this->findLatestSpeedNetworkingNote($authId, $peerId);
            if ($speedNote !== null) {
                return $speedNote;
            }
        }

        return [
            'peer_id' => $peerId,
            'note' => '',
            'source' => null,
            'updated_at' => null,
        ];
    }

    /**
     * Create, update, or clear a private note for a given peer.
     *
     * @return array{peer_id: string, note: string, source: ?string, updated_at: ?string}
     */
    public function saveNoteForPeer(
        User $authUser,
        string $peerId,
        ?string $rawNote,
        string $source = PeerNote::SOURCE_PROFILE
    ): array {
        $authId = (string) $authUser->id;
        $cleanNote = trim((string) ($rawNote ?? ''));

        if (! Schema::hasTable('peer_notes')) {
            return [
                'peer_id' => $peerId,
                'note' => $cleanNote,
                'source' => $cleanNote !== '' ? $source : null,
                'updated_at' => null,
            ];
        }

        if ($cleanNote === '') {
            PeerNote::query()
                ->where('user_id', $authId)
                ->where('peer_id', $peerId)
                ->delete();

            return [
                'peer_id' => $peerId,
                'note' => '',
                'source' => null,
                'updated_at' => null,
            ];
        }

        $record = PeerNote::query()->updateOrCreate(
            [
                'user_id' => $authId,
                'peer_id' => $peerId,
            ],
            [
                'note' => $cleanNote,
                'source' => $source,
            ]
        );

        return [
            'peer_id' => $peerId,
            'note' => (string) ($record->note ?? ''),
            'source' => (string) ($record->source ?? $source),
            'updated_at' => $record->updated_at?->toISOString(),
        ];
    }

    /**
     * Sync a non-empty Speed Collaboration note into peer_notes for the note owner only.
     */
    public function syncFromSpeedNetworking(string $userId, string $peerId, string $notes): void
    {
        $cleanNote = trim($notes);

        if (
            $userId === ''
            || $peerId === ''
            || $userId === $peerId
            || $cleanNote === ''
            || ! Schema::hasTable('peer_notes')
        ) {
            return;
        }

        PeerNote::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'peer_id' => $peerId,
            ],
            [
                'note' => $cleanNote,
                'source' => PeerNote::SOURCE_SPEED_NETWORKING,
            ]
        );
    }

    /**
     * @return array{peer_id: string, note: string, source: string, updated_at: ?string}|null
     */
    protected function findLatestSpeedNetworkingNote(string $authId, string $peerId): ?array
    {
        if (! Schema::hasTable('speed_networking_matches')) {
            return null;
        }

        $matches = SpeedNetworkingMatch::query()
            ->where(function ($q) use ($authId, $peerId): void {
                $q->where(function ($sub) use ($authId, $peerId): void {
                    $sub->where('user_a_id', $authId)
                        ->where('user_b_id', $peerId);
                })->orWhere(function ($sub) use ($authId, $peerId): void {
                    $sub->where('user_b_id', $authId)
                        ->where('user_a_id', $peerId);
                });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        foreach ($matches as $match) {
            // Only read the authenticated user's own per-user column; never leak the partner's note.
            $ownNote = (string) $match->user_a_id === $authId
                ? trim((string) ($match->user_a_notes ?? ''))
                : trim((string) ($match->user_b_notes ?? ''));

            if ($ownNote !== '') {
                return [
                    'peer_id' => $peerId,
                    'note' => $ownNote,
                    'source' => PeerNote::SOURCE_SPEED_NETWORKING,
                    'updated_at' => $match->updated_at?->toISOString(),
                ];
            }
        }

        return null;
    }
}
