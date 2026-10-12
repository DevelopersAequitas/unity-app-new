<?php

declare(strict_types=1);

namespace App\Models\Leadership;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LeadershipJuryReport extends Model
{
    use HasFactory;

    protected $table = 'leadership_jury_reports';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'assignment_id',
        'overall_recommendation',
        'strengths',
        'concerns',
        'verification_summary',
        'final_remarks',
        'conflict_of_interest',
        'conflict_details',
        'submitted_at',
    ];

    protected $casts = [
        'conflict_of_interest' => 'boolean',
        'submitted_at' => 'datetime',
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
}
