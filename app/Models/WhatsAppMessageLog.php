<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessageLog extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_READ = 'read';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_DRY_RUN = 'dry_run';

    protected $table = 'whatsapp_message_logs';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'whatsapp_template_id',
        'template_key',
        'template_name',
        'event_key',
        'user_id',
        'recipient_phone',
        'requested_recipient_phone',
        'actual_recipient_phone',
        'payload',
        'template_variables',
        'request_payload',
        'provider_response',
        'provider_message_id',
        'status',
        'error_code',
        'error_message',
        'source',
        'environment',
        'is_test',
        'idempotency_key',
        'sent_at',
        'failed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'template_variables' => 'array',
            'request_payload' => 'array',
            'provider_response' => 'array',
            'is_test' => 'boolean',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplate::class, 'whatsapp_template_id', 'id');
    }

    public function isSent(): bool
    {
        return strtolower((string) $this->status) === self::STATUS_SENT;
    }

    public function isFailed(): bool
    {
        return strtolower((string) $this->status) === self::STATUS_FAILED;
    }

    public function isDryRun(): bool
    {
        return strtolower((string) $this->status) === self::STATUS_DRY_RUN;
    }

    public function isSkipped(): bool
    {
        return strtolower((string) $this->status) === self::STATUS_SKIPPED;
    }
}
