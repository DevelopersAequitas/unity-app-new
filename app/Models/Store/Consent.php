<?php

namespace App\Models\Store;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consent extends Model
{
    use HasUuids;

    protected $table = 'consents';

    protected $fillable = [
        'user_id',
        'consent_key',
        'version',
        'consented',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'consented' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
