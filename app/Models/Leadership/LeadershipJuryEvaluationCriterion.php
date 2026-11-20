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

    protected $table = 'web_leadership_jury_evaluation_criteria';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'campaign_id',
        'criterion_key',
        'title',
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

    protected $appends = [
        'category',
        'weightage',
    ];

    public function getCategoryAttribute(): ?string
    {
        return $this->attributes['criterion_key'] ?? 'General';
    }

    public function getWeightageAttribute(): float
    {
        return (float) ($this->attributes['weight'] ?? 20.0);
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });

        static::saving(function (self $model): void {
            if (empty($model->title)) {
                $model->title = $model->name ?? 'Leadership Criterion';
            }
            if (empty($model->name) && ! empty($model->title)) {
                $model->name = $model->title;
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
