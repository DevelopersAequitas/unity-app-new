<?php

declare(strict_types=1);

namespace App\Models\Web;

use App\Models\Leadership\LeadershipCampaign;
use App\Models\Leadership\LeadershipNomination;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WebVote extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'web_votes';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    protected $fillable = [
        'vote_reference',
        'campaign_id',
        'nomination_id',
        'candidate_name',
        'candidate_email',
        'voter_name',
        'voter_email',
        'voter_phone',
        'voter_company',
        'voter_designation',
        'voter_city',
        'support_reason',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->vote_reference)) {
                $model->vote_reference = 'PG-VOTE-'.date('Y').'-'.strtoupper(Str::random(6));
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(LeadershipCampaign::class, 'campaign_id');
    }

    public function nomination(): BelongsTo
    {
        return $this->belongsTo(LeadershipNomination::class, 'nomination_id');
    }
}
