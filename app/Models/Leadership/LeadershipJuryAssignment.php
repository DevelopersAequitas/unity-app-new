<?php

declare(strict_types=1);

namespace App\Models\Leadership;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class LeadershipJuryAssignment extends Model
{
    use HasFactory;

    protected $table = 'web_leadership_jury_assignments';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'campaign_id',
        'nomination_id',
        'juror_user_id',
        'status',
        'invitation_sent_at',
        'due_at',
        'started_at',
        'completed_at',
        'conflict_declared',
        'conflict_details',
        'assignment_remarks',
        'assigned_by',
    ];

    protected $casts = [
        'conflict_declared' => 'boolean',
        'invitation_sent_at' => 'datetime',
        'due_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'candidate_name',
        'jury_name',
        'jury_email',
        'deadline',
        'campaign_id',
        'campaign_name',
        'report_data',
    ];

    public function getCandidateNameAttribute(): ?string
    {
        if ($this->relationLoaded('nomination') && $this->nomination) {
            return $this->nomination->candidate_name ?? $this->nomination->full_name;
        }

        return null;
    }

    public function getJuryNameAttribute(): ?string
    {
        $juror = $this->relationLoaded('juror') ? $this->juror : null;

        return $juror?->display_name ?: trim(($juror?->first_name ?? '').' '.($juror?->last_name ?? '')) ?: $this->juror_user_id;
    }

    public function getJuryEmailAttribute(): ?string
    {
        $juror = $this->relationLoaded('juror') ? $this->juror : null;

        return $juror?->email;
    }

    public function getDeadlineAttribute(): ?string
    {
        return $this->due_at?->toIso8601String();
    }

    public function getCampaignIdAttribute(): ?string
    {
        if ($this->relationLoaded('nomination') && $this->nomination) {
            return $this->nomination->campaign_id;
        }

        return $this->attributes['campaign_id'] ?? null;
    }

    public function getCampaignNameAttribute(): ?string
    {
        if ($this->relationLoaded('nomination') && $this->nomination) {
            return $this->nomination->campaign?->name ?? $this->nomination->campaign_name;
        }

        return null;
    }

    public function getReportDataAttribute(): ?array
    {
        if (! $this->relationLoaded('report')) {
            return null;
        }

        $report = $this->report;
        if (! $report) {
            return null;
        }

        $scoresList = [];
        $scores = $this->relationLoaded('scores') ? $this->scores : collect();
        $totalScoreSum = 0.0;
        $scoredCount = 0;
        foreach ($scores as $sc) {
            $criterion = $sc->relationLoaded('criterion') ? $sc->criterion : null;
            $cat = $criterion?->category ?? $criterion?->criterion_key ?? 'General';
            $critName = $criterion?->name ?? $criterion?->title ?? 'Criterion';
            $isCustom = (bool) ($criterion?->is_custom ?? ($cat === 'Custom Juror Metric' || str_contains(strtolower((string) $cat), 'custom')));
            $scoreVal = (float) $sc->score;
            $totalScoreSum += $scoreVal;
            $scoredCount++;
            $scoresList[] = [
                'criterion_id' => $sc->criterion_id,
                'criterion_name' => $critName,
                'criterion_description' => $criterion?->description ?? null,
                'category' => $cat,
                'is_custom' => $isCustom,
                'max_score' => (float) ($criterion?->max_score ?? 10),
                'weightage' => (float) ($criterion?->weightage ?? 10),
                'score' => $scoreVal,
                'remarks' => $sc->remarks,
            ];
        }

        $overallAverage = $scoredCount > 0 ? round($totalScoreSum / $scoredCount, 1) : null;

        return [
            'overall_score' => $overallAverage,
            'overall_average' => $overallAverage,
            'overall_recommendation' => $report->overall_recommendation,
            'strengths' => $report->strengths,
            'key_strengths' => $report->strengths,
            'concerns' => $report->concerns,
            'key_concerns' => $report->concerns,
            'conflict_of_interest' => (bool) $report->conflict_of_interest,
            'conflict_details' => $report->conflict_details,
            'conflict_disclosure' => $report->conflict_details,
            'final_remarks' => $report->final_remarks,
            'submitted_at' => $report->submitted_at?->toIso8601String(),
            'scores' => $scoresList,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->campaign_id) && ! empty($model->nomination_id)) {
                $model->campaign_id = $model->nomination?->campaign_id
                    ?? \App\Models\Leadership\LeadershipNomination::where('id', $model->nomination_id)->value('campaign_id');
            }
        });
    }

    public function nomination(): BelongsTo
    {
        return $this->belongsTo(LeadershipNomination::class, 'nomination_id');
    }

    public function juror(): BelongsTo
    {
        return $this->belongsTo(User::class, 'juror_user_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(LeadershipJuryScore::class, 'assignment_id');
    }

    public function report(): HasOne
    {
        return $this->hasOne(LeadershipJuryReport::class, 'assignment_id');
    }
}
