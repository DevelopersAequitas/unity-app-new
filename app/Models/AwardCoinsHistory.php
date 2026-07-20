<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AwardCoinsHistory extends Model
{
    use HasUuids;

    protected $table = 'awards_coins_history';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'coins_earned',
        'medal_rank',
        'title',
        'meaning',
        'achieved_at',
    ];

    protected $casts = [
        'coins_earned' => 'integer',
        'achieved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
