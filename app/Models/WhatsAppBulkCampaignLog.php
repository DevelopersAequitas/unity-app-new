<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppBulkCampaignLog extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'whatsapp_bulk_campaign_logs';

    public $timestamps = false;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'campaign_id',
        'recipient_id',
        'event_type',
        'message',
        'metadata',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppBulkCampaign::class, 'campaign_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(WhatsAppBulkCampaignRecipient::class, 'recipient_id');
    }
}
