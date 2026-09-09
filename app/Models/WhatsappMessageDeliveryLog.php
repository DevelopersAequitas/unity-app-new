<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WhatsappMessageDeliveryLog extends Model
{
    protected $table = 'whatsapp_message_delivery_logs';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'notification_id',
        'template_key',
        'template_name',
        'phone',
        'creative_url',
        'provider',
        'provider_message_id',
        'status',
        'error_message',
        'request_payload',
        'response_payload',
        'attempted_at',
        'delivered_at',
        'read_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'attempted_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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
}
