<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendSupportTicketEmailRequest;
use App\Mail\SupportTicketResolvedMail;
use App\Mail\SupportTicketResponseMail;
use App\Models\SupportTicket;
use App\Services\EmailLogs\EmailLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function __construct(
        private readonly EmailLogService $emailLogService
    ) {}

    /**
     * Display a listing of the support tickets.
     */
    public function index(Request $request): View
    {
        $query = SupportTicket::query()->with('user');

        // Filter by Status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by Priority
        if ($request->filled('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        // Search term
        if ($request->filled('search')) {
            $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->search).'%';
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'ilike', $search)
                    ->orWhere('contact_name', 'ilike', $search)
                    ->orWhere('email', 'ilike', $search)
                    ->orWhere('subject', 'ilike', $search);
            });
        }

        $tickets = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('admin.support_tickets.index', compact('tickets'));
    }

    /**
     * Display the specified support ticket detail.
     */
    public function show(string $id): View
    {
        $ticket = SupportTicket::query()->with('user')->findOrFail($id);

        return view('admin.support_tickets.show', compact('ticket'));
    }

    /**
     * Update the specified support ticket (status, priority, admin note).
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:open,in_progress,resolved,closed'],
            'priority' => ['required', 'string', 'in:low,normal,high,urgent'],
            'admin_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $ticket = SupportTicket::query()->findOrFail($id);
        $previousStatus = $ticket->status;

        $ticket->fill($validated);

        $shouldSendResolvedEmail = $previousStatus !== 'resolved' && $validated['status'] === 'resolved';

        if ($validated['status'] === 'resolved' && $previousStatus !== 'resolved') {
            $ticket->resolved_at = now();
        } elseif ($validated['status'] !== 'resolved') {
            $ticket->resolved_at = null;
        }

        $ticket->save();

        if ($shouldSendResolvedEmail) {
            $this->sendResolvedNotification($ticket);
        }

        return redirect()->route('admin.support-tickets.show', $id)
            ->with('success', 'Support ticket updated successfully.');
    }

    /**
     * Send an email response directly to the customer.
     */
    public function sendEmail(SendSupportTicketEmailRequest $request, string $id): RedirectResponse
    {
        $ticket = SupportTicket::query()->findOrFail($id);

        $subject = (string) $request->input('subject');
        $message = (string) $request->input('message');
        $status = $request->input('status');

        $mailable = new SupportTicketResponseMail($ticket, $subject, $message);

        try {
            Mail::to($ticket->email)->send($mailable);

            $this->emailLogService->logMailableSent($mailable, [
                'to_email' => $ticket->email,
                'to_name' => $ticket->contact_name,
                'template_key' => 'support_ticket_response',
                'source_module' => 'support',
                'related_type' => 'support_ticket',
                'related_id' => $ticket->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send support ticket direct email response.', [
                'ticket_id' => $ticket->id,
                'message' => $e->getMessage(),
            ]);

            $this->emailLogService->logMailableFailed($mailable, [
                'to_email' => $ticket->email,
                'to_name' => $ticket->contact_name,
                'template_key' => 'support_ticket_response',
                'source_module' => 'support',
                'related_type' => 'support_ticket',
                'related_id' => $ticket->id,
            ], $e);

            return redirect()->route('admin.support-tickets.show', $id)
                ->with('error', 'Failed to send email response: '.$e->getMessage());
        }

        // Record response note entry in admin_note log
        $timestamp = now()->format('Y-m-d H:i');
        $noteEntry = "[Email Sent - {$timestamp}]\nSubject: {$subject}\n\n{$message}";
        $ticket->admin_note = $ticket->admin_note ? $ticket->admin_note."\n\n".$noteEntry : $noteEntry;

        // Optionally update ticket status if specified
        if (! empty($status) && in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true)) {
            $previousStatus = $ticket->status;
            $ticket->status = $status;
            if ($status === 'resolved' && $previousStatus !== 'resolved') {
                $ticket->resolved_at = now();
            } elseif ($status !== 'resolved') {
                $ticket->resolved_at = null;
            }
        }

        $ticket->save();

        return redirect()->route('admin.support-tickets.show', $id)
            ->with('success', 'Email response sent successfully to '.$ticket->email.'.');
    }

    /**
     * Helper to send resolution notification email.
     */
    private function sendResolvedNotification(SupportTicket $ticket): void
    {
        $mail = new SupportTicketResolvedMail($ticket);

        try {
            Mail::to($ticket->email)->send($mail);
            $this->emailLogService->logMailableSent($mail, [
                'to_email' => $ticket->email,
                'to_name' => $ticket->contact_name,
                'template_key' => 'support_ticket_resolved',
                'source_module' => 'support',
                'related_type' => 'support_ticket',
                'related_id' => $ticket->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send support ticket resolved email.', [
                'ticket_id' => $ticket->id,
                'message' => $e->getMessage(),
            ]);

            $this->emailLogService->logMailableFailed($mail, [
                'to_email' => $ticket->email,
                'to_name' => $ticket->contact_name,
                'template_key' => 'support_ticket_resolved',
                'source_module' => 'support',
                'related_type' => 'support_ticket',
                'related_id' => $ticket->id,
            ], $e);
        }
    }
}
