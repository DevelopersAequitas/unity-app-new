<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppBulkCampaignImportError extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'whatsapp_bulk_campaign_import_errors';

    public $timestamps = false;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'campaign_id',
        'import_reference',
        'row_number',
        'raw_data',
        'error_codes',
        'error_message',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'error_codes' => 'array',
            'row_number' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppBulkCampaign::class, 'campaign_id');
    }
}
