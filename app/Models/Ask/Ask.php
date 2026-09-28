<?php

declare(strict_types=1);

namespace App\Models\Ask;

use App\Models\Circle;
use App\Models\District;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Ask extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'asks';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_FULFILLED = 'fulfilled';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const VISIBILITY_ALL_PEERS = 'all_peers';

    public const VISIBILITY_DISTRICT = 'district';

    public const VISIBILITY_CIRCLE = 'circle';

    protected $fillable = [
        'id',
        'user_id',
        'flow_id',
        'type_id',
        'title',
        'status',
        'visibility_type',
        'visibility_district_id',
        'visibility_circle_id',
        'publish_to_timeline',
        'published_at',
        'expires_at',
        'closed_at',
        'fulfilled_at',
        'outcome_status',
        'approx_deal_value',
        'outcome_notes',
        'metadata',
    ];

    protected $casts = [
        'publish_to_timeline' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'closed_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function getApproxValueAttribute(): ?string
    {
        return $this->approx_deal_value ?? ($this->metadata['approx_value'] ?? null);
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = self::STATUS_DRAFT;
            }
            if (empty($model->visibility_type)) {
                $model->visibility_type = self::VISIBILITY_ALL_PEERS;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(AskFlow::class, 'flow_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AskType::class, 'type_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'visibility_district_id');
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'visibility_circle_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AskAnswer::class, 'ask_id')->orderBy('sort_order');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(AskMatch::class, 'ask_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AskResponse::class, 'ask_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(AskStatusHistory::class, 'ask_id')->orderByDesc('created_at');
    }

    public function timelineLink(): HasOne
    {
        return $this->hasOne(AskTimelineLink::class, 'ask_id');
    }
}
