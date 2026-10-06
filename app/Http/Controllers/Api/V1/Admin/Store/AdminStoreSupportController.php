<?php

namespace App\Http\Controllers\Api\V1\Admin\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Store\StoreSupportTicket;
use App\Models\Store\StoreSupportTicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreSupportController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = StoreSupportTicket::with(['user', 'order', 'assignee']);
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = (int) $request->input('per_page', 20);
        $tickets = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->success($tickets, 'Admin support tickets retrieved');
    }

    public function show(string $id): JsonResponse
    {
        $ticket = StoreSupportTicket::with(['user', 'order', 'assignee', 'messages.sender'])->findOrFail($id);

        return $this->success($ticket, 'Ticket details retrieved');
    }

    public function assign(Request $request, string $id): JsonResponse
    {
        $request->validate(['assignee_id' => 'required|uuid|exists:users,id']);
        $ticket = StoreSupportTicket::findOrFail($id);
        $ticket->update(['assigned_to' => $request->input('assignee_id'), 'status' => 'IN_PROGRESS']);

        return $this->success($ticket, 'Ticket assigned successfully');
    }

    public function reply(Request $request, string $id): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:2000']);
        $ticket = StoreSupportTicket::findOrFail($id);
        $admin = $request->user();

        $msg = StoreSupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_id' => $admin ? $admin->id : null,
            'sender_type' => 'ADMIN',
            'body' => $request->input('message'),
            'created_at' => now(),
        ]);

        $ticket->update(['status' => 'WAITING_USER']);

        return $this->success($msg, 'Reply posted successfully', 201);
    }

    public function resolve(Request $request, string $id): JsonResponse
    {
        $ticket = StoreSupportTicket::findOrFail($id);
        $ticket->update([
            'status' => 'RESOLVED',
            'resolved_at' => now(),
            'resolution_note' => $request->input('note', 'Resolved by admin'),
        ]);

        return $this->success($ticket, 'Ticket marked as resolved');
    }
}
