<?php

declare(strict_types=1);

namespace App\Models\Leadership;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LeadershipCampaignScope extends Model
{
    use HasFactory;

    protected $table = 'leadership_campaign_scopes';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'campaign_id',
        'scope_type',
        'scope_name',
        'scope_reference_id',
        'parent_scope_id',
        'settings',
        'status',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(LeadershipCampaign::class, 'campaign_id');
    }

    public function parentScope(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_scope_id');
    }

    public function childScopes(): HasMany
    {
        return $this->hasMany(self::class, 'parent_scope_id');
    }

    public function nominations(): HasMany
    {
        return $this->hasMany(LeadershipNomination::class, 'scope_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(LeadershipVote::class, 'scope_id');
    }
}
