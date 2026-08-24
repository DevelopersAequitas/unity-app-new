<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PeerRecommendation extends Model
{
    protected $table = 'peer_recommendations';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'peer_name',
        'peer_mobile',
        'peer_email',
        'peer_city',
        'peer_business',
        'peer_industry',
        'why_valuable',
        'category',
        'category_id',
        'circle_id',
        'circle_name',
        'how_well_known',
        'is_aware',
        'note',
        'coins_awarded',
        'coins_awarded_at',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'is_aware' => 'boolean',
        'coins_awarded' => 'boolean',
        'coins_awarded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
