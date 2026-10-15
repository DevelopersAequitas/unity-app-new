<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppBulkCampaign extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'whatsapp_bulk_campaigns';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'whatsapp_template_id',
        'template_name_snapshot',
        'template_language',
        'template_category',
        'template_snapshot',
        'variable_mapping',
        'media_configuration',
        'status',
        'schedule_type',
        'scheduled_at',
        'started_at',
        'completed_at',
        'created_by',
        'total_rows',
        'valid_count',
        'invalid_count',
        'duplicate_count',
        'sent_count',
        'delivered_count',
        'read_count',
        'failed_count',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template_snapshot' => 'array',
            'variable_mapping' => 'array',
            'media_configuration' => 'array',
            'metadata' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_rows' => 'integer',
            'valid_count' => 'integer',
            'invalid_count' => 'integer',
            'duplicate_count' => 'integer',
            'sent_count' => 'integer',
            'delivered_count' => 'integer',
            'read_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplate::class, 'whatsapp_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsAppBulkCampaignRecipient::class, 'campaign_id');
    }

    public function importErrors(): HasMany
    {
        return $this->hasMany(WhatsAppBulkCampaignImportError::class, 'campaign_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WhatsAppBulkCampaignLog::class, 'campaign_id')->orderBy('created_at', 'desc');
    }

    /**
     * Calculate dynamic percentage progress (sent / valid_count).
     */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->valid_count <= 0) {
            return 0;
        }

        $processed = $this->sent_count + $this->failed_count;
        $pct = (int) round(($processed / $this->valid_count) * 100);

        return min(100, max(0, $pct));
    }

    /**
     * Calculate delivery rate (delivered / sent).
     */
    public function getDeliveredRateAttribute(): int
    {
        if ($this->sent_count <= 0) {
            return 0;
        }

        return (int) round(($this->delivered_count / $this->sent_count) * 100);
    }

    /**
     * Calculate read rate (read / sent).
     */
    public function getReadRateAttribute(): int
    {
        if ($this->sent_count <= 0) {
            return 0;
        }

        return (int) round(($this->read_count / $this->sent_count) * 100);
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'completed_with_errors', 'failed', 'cancelled'], true);
    }

    public function canCancel(): bool
    {
        return in_array($this->status, ['draft', 'scheduled', 'queued'], true);
    }

    public function canRetry(): bool
    {
        return $this->isCompleted() && $this->failed_count > 0;
    }
}
