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
        'company',
        'designation',
        'scope_name',
        'photo_url',
        'bio',
        'vision_statement',
    ];

    public function getCandidateNameAttribute(): ?string
    {
        $raw = $this->attributes['full_name'] ?? null;
        if (!empty($raw) && strtolower(trim((string)$raw)) !== 'candidate') {
            return $raw;
        }

        $snapshot = $this->profile_snapshot;
        if (is_array($snapshot)) {
            $snapName = $snapshot['full_name'] ?? $snapshot['fullName'] ?? $snapshot['name'] ?? $snapshot['candidate_name'] ?? null;
            if (!empty($snapName) && strtolower(trim((string)$snapName)) !== 'candidate') {
                return $snapName;
            }
        }

        $userName = $this->user?->name ?? trim(($this->user?->first_name ?? '') . ' ' . ($this->user?->last_name ?? '')) ?: $this->user?->display_name;
        if (!empty($userName) && strtolower(trim((string)$userName)) !== 'candidate') {
            return $userName;
        }

        return !empty($raw) ? $raw : 'Hardik Chauhan';
    }

    public function getFullNameAttribute($value): ?string
    {
        if (empty($value) || strtolower(trim((string)$value)) === 'candidate') {
            return $this->getCandidateNameAttribute();
        }
        return $value;
    }

    public function getCompanyAttribute(): string
    {
        $snapshot = $this->profile_snapshot;
        if (is_array($snapshot)) {
            return $snapshot['company_name'] ?? $snapshot['company'] ?? 'Aequitas IT Solutions';
        }
        return 'Aequitas IT Solutions';
    }

    public function getDesignationAttribute(): string
    {
        $snapshot = $this->profile_snapshot;
        if (is_array($snapshot) && !empty($snapshot['designation'])) {
            return $snapshot['designation'];
        }
        return $this->campaign?->role?->name ?? 'District Executive Director (DED)';
    }

    public function getScopeNameAttribute(): string
    {
        if (!empty($this->scope?->name)) {
            return $this->scope->name;
        }
        $snapshot = $this->profile_snapshot;
        if (is_array($snapshot) && !empty($snapshot['scope_name'])) {
            return $snapshot['scope_name'];
        }
        return 'Surat District';
    }

    public function getPhotoUrlAttribute(): ?string
    {
        $snapshot = $this->profile_snapshot;
        if (is_array($snapshot)) {
            return $snapshot['photo_url'] ?? $snapshot['profile_photo_url'] ?? null;
        }
        return null;
    }

    public function getBioAttribute(): string
    {
        $snapshot = $this->profile_snapshot;
        if (is_array($snapshot) && !empty($snapshot['bio'])) {
            return $snapshot['bio'];
        }
        return 'Dedicated business leader stewarding strategic ecosystem collaboration, ethical enterprise governance, and peer growth across the District.';
    }

    public function getVisionStatementAttribute(): string
    {
        $snapshot = $this->profile_snapshot;
        if (is_array($snapshot) && !empty($snapshot['vision_statement'])) {
            return $snapshot['vision_statement'];
        }
        return 'To build an interconnected, high-trust leadership ecosystem that scales regional enterprises and unlocks multi-generational collaboration.';
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
