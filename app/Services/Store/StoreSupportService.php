<?php

namespace App\Services\Store;

use App\Constants\StoreErrorCodes;
use App\Models\Store\StoreSupportTicket;
use App\Models\Store\StoreSupportTicketMessage;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class StoreSupportService
{
    public function createTicket(User $user, array $data): StoreSupportTicket
    {
        $ticketNo = 'TICK-' . strtoupper(Str::random(8));

        $ticket = StoreSupportTicket::create([
            'ticket_no' => $ticketNo,
            'user_id' => $user->id,
            'order_id' => $data['order_id'] ?? null,
            'subject' => $data['subject'],
            'body' => $data['description'] ?? ($data['body'] ?? ''),
            'status' => 'OPEN',
            'priority' => $data['priority'] ?? 'NORMAL',
            'sla_due_at' => now()->addHours(24),
            'created_at' => now(),
        ]);

        if (! empty($data['description'])) {
            StoreSupportTicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'sender_type' => 'USER',
                'body' => $data['description'],
                'created_at' => now(),
            ]);
        }

        return $ticket->load('messages');
    }

    public function getUserTickets(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return StoreSupportTicket::with(['order', 'messages.sender'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getTicketDetails(User $user, string $id): StoreSupportTicket
    {
        $ticket = StoreSupportTicket::with(['order', 'messages.sender'])
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $ticket) {
            throw new Exception(StoreErrorCodes::VALIDATION_ERROR, 404);
        }

        return $ticket;
    }

    public function addMessage(User $user, string $ticketId, string $message, ?string $attachmentUrl = null): StoreSupportTicketMessage
    {
        $ticket = $this->getTicketDetails($user, $ticketId);

        $msg = StoreSupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_id' => $user->id,
            'sender_type' => 'USER',
            'body' => $message,
            'attachments' => $attachmentUrl ? [$attachmentUrl] : null,
            'created_at' => now(),
        ]);

        $ticket->update(['status' => 'WAITING_FOR_ADMIN']);

        return $msg;
    }

    public function closeTicket(User $user, string $ticketId): StoreSupportTicket
    {
        $ticket = $this->getTicketDetails($user, $ticketId);
        $ticket->update([
            'status' => 'CLOSED',
            'resolved_at' => now(),
            'resolution_note' => 'Closed by user',
        ]);

        return $ticket;
    }
}
