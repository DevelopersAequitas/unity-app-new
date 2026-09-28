<?php

declare(strict_types=1);

namespace App\Models\Ask;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class AskResponse extends Model
{
    use HasFactory;

    protected $table = 'ask_responses';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    public const TYPE_CAN_HELP_DIRECTLY = 'can_help_directly';

    public const TYPE_CAN_INTRODUCE_PEER = 'can_introduce_peer';

    public const TYPE_KNOW_SOMEONE = 'know_someone';

    public const TYPE_NOT_RELEVANT = 'not_relevant';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'id',
        'ask_id',
        'responder_user_id',
        'response_type',
        'message',
        'timeline',
        'introduced_user_id',
        'status',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = self::STATUS_PENDING;
            }
            if (empty($model->responded_at)) {
                $model->responded_at = now();
            }
        });
    }

    public function ask(): BelongsTo
    {
        return $this->belongsTo(Ask::class, 'ask_id');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responder_user_id');
    }

    public function introducedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'introduced_user_id');
    }

    public function contact(): HasOne
    {
        return $this->hasOne(AskResponseContact::class, 'response_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(AskResponseStatusHistory::class, 'response_id')->orderByDesc('created_at');
    }
}
