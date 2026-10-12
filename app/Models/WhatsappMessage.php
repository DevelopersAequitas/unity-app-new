<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMessage extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'whatsapp_messages';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'recipient_phone',
        'whatsapp_template_id',
        'meta_template_id',
        'template_name',
        'template_language',
        'message_type',
        'status',
        'meta_message_id',
        'error_code',
        'error_message',
        'request_payload',
        'response_payload',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
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
        return in_array(strtoupper((string) $this->status), ['SENT', 'DELIVERED', 'READ'], true);
    }

    public function isDelivered(): bool
    {
        return in_array(strtoupper((string) $this->status), ['DELIVERED', 'READ'], true);
    }

    public function isRead(): bool
    {
        return strtoupper((string) $this->status) === 'READ';
    }

    public function isFailed(): bool
    {
        return strtoupper((string) $this->status) === 'FAILED';
    }
}
