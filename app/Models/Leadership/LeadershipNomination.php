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

class LeadershipNomination extends Model
{
    use HasFactory;

    protected $table = 'leadership_nominations';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'campaign_id',
        'scope_id',
        'user_id',
        'application_number',
        'full_name',
        'email',
        'mobile',
        'profile_snapshot',
        'original_profile',
        'edited_profile',
        'profile_changes',
        'status',
        'review_remarks',
        'rejection_reason',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'shortlisted_at',
    ];

    protected $casts = [
        'profile_snapshot' => 'array',
        'original_profile' => 'array',
        'edited_profile' => 'array',
        'profile_changes' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'shortlisted_at' => 'datetime',
    ];

    
    protected $appends = [
        'candidate_name',
        'campaign_name',
        'applied_role_name',
        'voting_link',
    ];

    public function getCandidateNameAttribute(): ?string
    {
        return $this->full_name
            ?? $this->user?->name
            ?? ($this->profile_snapshot['name'] ?? null)
            ?? ($this->profile_snapshot['full_name'] ?? null)
            ?? ($this->profile_snapshot['candidate_name'] ?? null)
            ?? 'Candidate';
    }

    public function getEmailAttribute(): ?string
    {
        return $this->attributes['email']
            ?? $this->user?->email
            ?? ($this->profile_snapshot['email'] ?? null);
    }

    public function getMobileAttribute(): ?string
    {
        return $this->attributes['mobile']
            ?? $this->user?->phone
            ?? ($this->profile_snapshot['mobile'] ?? null)
            ?? ($this->profile_snapshot['phone'] ?? null);
    }

    public function getCampaignNameAttribute(): ?string
    {
        return $this->campaign?->name;
    }

    public function getAppliedRoleNameAttribute(): ?string
    {
        return $this->campaign?->role?->name;
    }

    public function getVotingLinkAttribute(): string
    {
        return "https://peersglobal.com/leadership/campaigns/{$this->campaign_id}/vote?candidate={$this->id}";
    }

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

    public function scope(): BelongsTo
    {
        return $this->belongsTo(LeadershipCampaignScope::class, 'scope_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(LeadershipNominationAnswer::class, 'nomination_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LeadershipNominationDocument::class, 'nomination_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(LeadershipNominationHistory::class, 'nomination_id')->orderBy('created_at', 'desc');
    }

    public function juryAssignments(): HasMany
    {
        return $this->hasMany(LeadershipJuryAssignment::class, 'nomination_id');
    }

    public function jurySubmissions(): HasMany
    {
        return $this->hasMany(LeadershipJuryFormSubmission::class, 'nomination_id');
    }

    public function finalDecision(): HasOne
    {
        return $this->hasOne(LeadershipFinalDecision::class, 'nomination_id');
    }

    public function resultTokens(): HasMany
    {
        return $this->hasMany(LeadershipCandidateResultToken::class, 'nomination_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(LeadershipVote::class, 'nomination_id');
    }
}
