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
        'main_business_category_id',
        'main_business_category',
        'business_subcategory_id',
        'business_subcategory',
        'circle_id',
        'circle_name',
        'how_well_known',
        'is_aware',
        'note',
        'status',
        'coins_awarded',
        'coins_awarded_at',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'main_business_category_id' => 'integer',
        'is_aware' => 'boolean',
        'coins_awarded' => 'boolean',
        'coins_awarded_at' => 'datetime',
    ];

    public function getPeerCityCountryAttribute(): ?string
    {
        return $this->peer_city;
    }

    public function getSubmittedAtAttribute(): ?string
    {
        return $this->created_at?->toISOString() ?? ($this->created_at ? (string) $this->created_at : null);
    }

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
