<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountDeletionRequest extends Model
{
    use HasUuids;

    protected $table = 'account_deletion_requests';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'email',
        'reason',
        'status',
    ];

    /**
     * Get the user who requested deletion (via user_id FK, includes soft-deleted).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * Resolve the linked user by user_id first, then by email column as fallback.
     * Returns the User model (including soft-deleted) or null if not found.
     * This handles requests where user_id is null but email is stored.
     */
    public function resolveLinkedUser(): ?User
    {
        // Try via user_id FK first
        if ($this->user_id) {
            $u = User::withTrashed()->find($this->user_id);
            if ($u) {
                return $u;
            }
        }

        // Fallback: look up by the stored email column
        if ($this->email) {
            return User::withTrashed()->where('email', $this->email)->first();
        }

        return null;
    }
}
