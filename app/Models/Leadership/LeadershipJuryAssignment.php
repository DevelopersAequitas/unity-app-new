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
        return $this->nomination?->candidate_name ?? $this->nomination?->full_name;
    }

    public function getJuryNameAttribute(): ?string
    {
        return $this->juror?->display_name ?: trim(($this->juror?->first_name ?? '').' '.($this->juror?->last_name ?? '')) ?: $this->juror_user_id;
    }

    public function getJuryEmailAttribute(): ?string
    {
        return $this->juror?->email;
    }

    public function getDeadlineAttribute(): ?string
    {
        return $this->due_at?->toIso8601String();
    }

    public function getCampaignIdAttribute(): ?string
    {
        return $this->nomination?->campaign_id;
    }

    public function getCampaignNameAttribute(): ?string
    {
        return $this->nomination?->campaign?->name ?? $this->nomination?->campaign_name;
    }

    public function getReportDataAttribute(): ?array
    {
        if (! $this->relationLoaded('report') || ! $this->report) {
            return null;
        }

        return [
            'overall_recommendation' => $this->report->overall_recommendation,
            'strengths' => $this->report->strengths,
            'concerns' => $this->report->concerns,
            'final_remarks' => $this->report->final_remarks,
            'submitted_at' => $this->report->submitted_at?->toIso8601String(),
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
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
