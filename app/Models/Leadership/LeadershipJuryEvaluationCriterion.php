<?php

declare(strict_types=1);

namespace App\Models\Leadership;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LeadershipJuryEvaluationCriterion extends Model
{
    use HasFactory;

    protected $table = 'leadership_jury_evaluation_criteria';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'campaign_id',
        'criterion_key',
        'name',
        'description',
        'max_score',
        'weight',
        'sort_order',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'max_score' => 'float',
        'weight' => 'float',
        'sort_order' => 'integer',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
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

    public function scores(): HasMany
    {
        return $this->hasMany(LeadershipJuryScore::class, 'criterion_id');
    }
}
