<?php

declare(strict_types=1);

namespace App\Models\Leadership;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LeadershipCampaign extends Model
{
    use HasFactory;

    protected $table = 'leadership_campaigns';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'role_id',
        'name',
        'slug',
        'description',
        'campaign_year',
        'status',
        'nomination_starts_at',
        'nomination_ends_at',
        'voting_starts_at',
        'voting_ends_at',
        'jury_starts_at',
        'jury_ends_at',
        'results_visibility',
        'allow_multiple_winners',
        'eligibility_rules',
        'settings',
        'created_by',
        'published_at',
    ];

    protected $casts = [
        'campaign_year' => 'integer',
        'allow_multiple_winners' => 'boolean',
        'nomination_starts_at' => 'datetime',
        'nomination_ends_at' => 'datetime',
        'voting_starts_at' => 'datetime',
        'voting_ends_at' => 'datetime',
        'jury_starts_at' => 'datetime',
        'jury_ends_at' => 'datetime',
        'published_at' => 'datetime',
        'eligibility_rules' => 'array',
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

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(LeadershipCampaignScope::class, 'campaign_id');
    }

    public function formTemplates(): HasMany
    {
        return $this->hasMany(LeadershipFormTemplate::class, 'campaign_id');
    }

    public function nominations(): HasMany
    {
        return $this->hasMany(LeadershipNomination::class, 'campaign_id');
    }

    public function evaluationCriteria(): HasMany
    {
        return $this->hasMany(LeadershipJuryEvaluationCriterion::class, 'campaign_id');
    }

    public function finalDecisions(): HasMany
    {
        return $this->hasMany(LeadershipFinalDecision::class, 'campaign_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(LeadershipVote::class, 'campaign_id');
    }
}
