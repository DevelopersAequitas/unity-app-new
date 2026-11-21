<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZohoInvoiceDocument extends Model
{
    use HasUuids;

    protected $table = 'zoho_invoice_documents';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'payment_type',
        'reference_id',
        'zoho_customer_id',
        'zoho_invoice_id',
        'zoho_invoice_number',
        'zoho_invoice_url',
        'gst_number',
        'gst_treatment',
        'amount',
        'currency',
        'pdf_disk',
        'pdf_path',
        'pdf_url',
        'pdf_size_bytes',
        'pdf_sha256',
        'whatsapp_status',
        'whatsapp_media_id',
        'whatsapp_message_id',
        'whatsapp_sent_at',
        'whatsapp_error',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'pdf_size_bytes' => 'integer',
        'whatsapp_sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
