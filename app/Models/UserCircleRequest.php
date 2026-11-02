<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class UserCircleRequest extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'user_circle_requests';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUS_OPTIONS = [
        self::STATUS_PENDING,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'user_id',
        'circle_name',
        'category_id',
        'description',
        'city',
        'country',
        'meeting_mode',
        'meeting_frequency',
        'status',
        'admin_notes',
        'minimum_peers_required',
        'reviewed_by',
        'reviewed_at',
        'created_circle_id',
        'circle_created_at',
        'metadata',
    ];

    protected $casts = [
        'category_id'            => 'integer',
        'reviewed_at'            => 'datetime',
        'circle_created_at'      => 'datetime',
        'minimum_peers_required' => 'integer',
        'metadata'               => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function createdCircle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'created_circle_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CircleCategory::class, 'category_id');
    }

    public function nominees(): HasMany
    {
        return $this->hasMany(UserCircleRequestNominee::class, 'user_circle_request_id');
    }

    public function recommendedPeers(): HasMany
    {
        return $this->hasMany(UserCircleRequestNominee::class, 'user_circle_request_id');
    }

    public function getNomineesCountAttribute(): int
    {
        return $this->nominees()->count();
    }

    public function getRecommendedPeersCountAttribute(): int
    {
        return $this->nominees()->count();
    }

    public function hasMinimumNominees(): bool
    {
        return $this->nominees()->count() >= $this->minimum_peers_required;
    }

    public function hasMinimumRecommendedPeers(): bool
    {
        return $this->hasMinimumNominees();
    }
}
