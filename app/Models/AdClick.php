<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdClick extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'ad_clicks';

    protected $keyType = 'string';

    public $incrementing = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'user_id',
        'ad_id',
        'click_type',
        'ip',
        'device',
        'created_at',
        'session_id',
        'ip_address',
    ];

    protected $casts = [
        'created_at' => 'datetime',
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
