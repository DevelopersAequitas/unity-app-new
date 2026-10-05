<?php

namespace App\Models\Store;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreSupportTicket extends Model
{
    use HasUuids;

    protected $table = 'support_tickets';

    protected $fillable = [
        'ticket_no',
        'ticket_number',
        'user_id',
        'order_id',
        'subject',
        'body',
        'status',
        'priority',
        'assigned_to',
        'resolved_at',
        'resolution_note',
        'sla_due_at',
        'first_response_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $ticket): void {
            if (empty($ticket->ticket_number) && ! empty($ticket->ticket_no)) {
                $ticket->ticket_number = $ticket->ticket_no;
            }
            if (empty($ticket->ticket_no) && ! empty($ticket->ticket_number)) {
                $ticket->ticket_no = $ticket->ticket_number;
            }
        });
    }

    protected $casts = [
        'resolved_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'first_response_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(StoreSupportTicketMessage::class, 'ticket_id')->orderBy('created_at', 'asc');
    }
}
