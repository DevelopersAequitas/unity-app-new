<?php

declare(strict_types=1);

namespace App\Models\Leadership;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LeadershipNotificationLog extends Model
{
    use HasFactory;

    protected $table = 'leadership_notification_logs';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'campaign_id',
        'nomination_id',
        'recipient_user_id',
        'recipient',
        'channel',
        'template_key',
        'provider_reference',
        'status',
        'attempt_count',
        'error_message',
        'payload_metadata',
        'sent_at',
        'delivered_at',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'payload_metadata' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

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

    public function nomination(): BelongsTo
    {
        return $this->belongsTo(LeadershipNomination::class, 'nomination_id');
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
