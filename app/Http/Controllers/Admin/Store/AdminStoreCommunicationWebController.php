<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\Store\NotificationLog;
use App\Models\Store\StoreSupportTicket;
use App\Models\Store\StoreSupportTicketMessage;
use App\Services\Store\StoreNotificationService;
use App\Services\Store\StoreSupportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminStoreCommunicationWebController extends Controller
{
    protected StoreSupportService $supportService;

    protected StoreNotificationService $notificationService;

    public function __construct(StoreSupportService $supportService, StoreNotificationService $notificationService)
    {
        $this->supportService = $supportService;
        $this->notificationService = $notificationService;
    }

    // ==========================================
    // NOTIFICATIONS & DELIVERY LOGS
    // ==========================================

    public function notificationLogs(Request $request)
    {
        $query = NotificationLog::with('user')->orderBy('created_at', 'desc');
        $search = $request->input('search', '');
        $channelFilter = $request->input('channel', '');
        $statusFilter = $request->input('status', '');

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'ILIKE', "%{$s}%")
                    ->orWhere('body', 'ILIKE', "%{$s}%");
            });
        }

        $logs = $query->paginate(20);

        return view('admin.store.communication.logs', compact('logs', 'search', 'channelFilter', 'statusFilter'));
    }

    public function resendNotification(Request $request, string $id)
    {
        $log = NotificationLog::findOrFail($id);
        $log->update([
            'status' => 'RETRYING',
            'attempts' => $log->attempts + 1,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Notification queued for resending.');
    }

    // ==========================================
    // SUPPORT HELPDESK & TICKETS
    // ==========================================

    public function supportTickets(Request $request)
    {
        $query = StoreSupportTicket::with(['user', 'order'])->orderBy('created_at', 'desc');
        $tab = $request->input('tab', 'all');
        $search = $request->input('search', '');

        $tabs = [
            'all' => 'All Tickets',
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
        ];

        if ($tab !== 'all') {
            $query->where('status', strtoupper($tab));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('ticket_number', 'ILIKE', "%{$s}%")
                    ->orWhere('subject', 'ILIKE', "%{$s}%");
            });
        }

        $tickets = $query->paginate(20);

        return view('admin.store.support.index', compact('tickets', 'tabs', 'tab', 'search'));
    }

    public function showTicket(string $id)
    {
        $ticket = StoreSupportTicket::with(['user', 'order', 'messages.user', 'messages.sender'])->findOrFail($id);
        $messages = $ticket->messages ?? collect([]);

        $cannedReplies = [
            'Order Dispatch Update' => 'Hello! We are pleased to inform you that your merchandise has been packed and handed over to our logistics partner. You can track live milestones directly on the app.',
            'Return Inspection Confirmation' => 'Dear Peer, your returned item has safely arrived at our Central Hub and passed quality inspection. Your coin refund has been credited directly to your wallet.',
            'Hub Pickup Reminder' => 'Your order is ready for pickup at your selected Unity Central Hub. Please present your 6-digit PIN / QR Code to our hub coordinator between 10 AM to 6 PM.',
            'General Support Query' => 'Thank you for reaching out to Peers Store Support. We are investigating your inquiry and will update you shortly.',
        ];

        return view('admin.store.support.show', compact('ticket', 'messages', 'cannedReplies'));
    }

    public function replyTicket(Request $request, string $id)
    {
        $ticket = StoreSupportTicket::findOrFail($id);
        $admin = Auth::guard('admin')->user();

        $validated = $request->validate([
            'message' => 'required|string|min:2|max:2000',
        ]);

        StoreSupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'ADMIN',
            'sender_id' => $admin->id ?? Auth::id(),
            'body' => $validated['message'],
            'message' => $validated['message'],
        ]);

        if ($ticket->status === 'OPEN') {
            $ticket->update(['status' => 'IN_PROGRESS']);
        }

        return back()->with('success', 'Reply sent to peer.');
    }

    public function resolveTicket(string $id)
    {
        $ticket = StoreSupportTicket::findOrFail($id);
        $ticket->update([
            'status' => 'RESOLVED',
            'resolved_at' => now(),
        ]);

        return back()->with('success', 'Support ticket marked as RESOLVED.');
    }
}
