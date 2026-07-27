<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\HasApiTokens;

class ScanAppUser extends Authenticatable
{
    use HasApiTokens;
    use HasUuids;
    use Notifiable;

    protected $table = 'scan_app_users';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'username',
        'password_hash',
        'plain_password',
        'hotel_name',
        'event_id',
        'event_ids',
        'event_name',
        'is_active',
        'created_by_admin_id',
        'last_login_at',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'event_ids' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function getAssignedEventIdsAttribute(): array
    {
        $ids = [];
        if ($this->event_id) {
            $ids[] = (string) $this->event_id;
        }
        if (is_array($this->event_ids)) {
            foreach ($this->event_ids as $id) {
                if (! empty($id)) {
                    $ids[] = (string) $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public function assignedEvents()
    {
        $ids = $this->assigned_event_ids;
        if (empty($ids)) {
            return collect();
        }

        return Event::query()->whereIn('id', $ids)->orderByDesc('start_at')->get();
    }

    public function canScanEvent(string $eventId): bool
    {
        $ids = $this->assigned_event_ids;
        if (empty($ids)) {
            return true;
        }

        return in_array((string) $eventId, $ids, true);
    }

    public function peerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'username', 'email');
    }

    public function checkPassword(string $password): bool
    {
        try {
            return Hash::check($password, (string) $this->password_hash);
        } catch (\Throwable $exception) {
            Log::warning("ScanAppUser password check exception for username {$this->username}: ".$exception->getMessage());

            return false;
        }
    }
}
