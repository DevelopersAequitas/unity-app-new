<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppBulkCampaignRecipient extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'whatsapp_bulk_campaign_recipients';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'campaign_id',
        'name',
        'phone',
        'original_phone',
        'csv_row_number',
        'custom_data',
        'resolved_parameters',
        'status',
        'whatsapp_message_id',
        'attempt_count',
        'last_attempt_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'error_code',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'custom_data' => 'array',
            'resolved_parameters' => 'array',
            'attempt_count' => 'integer',
            'csv_row_number' => 'integer',
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppBulkCampaign::class, 'campaign_id');
    }

    public function isDelivered(): bool
    {
        return in_array($this->status, ['delivered', 'read'], true);
    }

    public function isRead(): bool
    {
        return $this->status === 'read';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function canBeRetried(): bool
    {
        return $this->status === 'failed' && empty($this->whatsapp_message_id);
    }
}
