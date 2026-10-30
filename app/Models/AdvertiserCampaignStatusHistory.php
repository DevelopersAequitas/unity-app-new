<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvertiserCampaignStatusHistory extends Model
{
    use HasFactory;
    use HasUuids;

    public $timestamps = false;

    protected $table = 'advertiser_campaign_status_history';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'campaign_id',
        'old_status',
        'new_status',
        'changed_by',
        'actor_type',
        'notes',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public static function boot(): void
    {
        parent::boot();

        static::creating(function ($model): void {
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdvertiserCampaign::class, 'campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
