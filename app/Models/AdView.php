<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdView extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'ad_views';

    protected $keyType = 'string';

    public $incrementing = false;

    // Map viewed_at to the created_at event
    const CREATED_AT = 'viewed_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'user_id',
        'ad_id',
        'viewed_at',
        'session_id',
        'ip_address',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class, 'ad_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
