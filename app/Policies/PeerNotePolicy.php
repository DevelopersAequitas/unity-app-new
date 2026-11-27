<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PeerNote;
use App\Models\User;

class PeerNotePolicy
{
    public function view(User $user, PeerNote $peerNote): bool
    {
        return (string) $peerNote->user_id === (string) $user->id;
    }

    public function update(User $user, PeerNote $peerNote): bool
    {
        return (string) $peerNote->user_id === (string) $user->id;
    }

    public function delete(User $user, PeerNote $peerNote): bool
    {
        return (string) $peerNote->user_id === (string) $user->id;
    }
}
