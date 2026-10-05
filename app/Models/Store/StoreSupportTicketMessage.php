<?php

namespace App\Models\Store;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreSupportTicketMessage extends Model
{
    use HasUuids;

    protected $table = 'support_ticket_messages';

    const UPDATED_AT = null;

    protected $fillable = [
        'ticket_id',
        'sender_id',
        'sender_type',
        'body',
        'attachments',
        'created_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'created_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(StoreSupportTicket::class, 'ticket_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function getMessageAttribute($value)
    {
        return $value ?? $this->attributes['body'] ?? '';
    }
}
