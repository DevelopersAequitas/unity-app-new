<?php

declare(strict_types=1);

namespace App\Models\Leadership;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LeadershipJuryScore extends Model
{
    use HasFactory;

    protected $table = 'leadership_jury_scores';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'assignment_id',
        'criterion_id',
        'score',
        'remarks',
        'scored_at',
    ];

    protected $casts = [
        'score' => 'float',
        'scored_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(LeadershipJuryAssignment::class, 'assignment_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(LeadershipJuryEvaluationCriterion::class, 'criterion_id');
    }
}
