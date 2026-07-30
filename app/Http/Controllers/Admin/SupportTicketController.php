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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function __construct(
        protected EmailLogService $emailLogService
    ) {}

    /**
     * Display a listing of support tickets.
     */
    public function index(Request $request): View
    {
        $query = SupportTicket::query()->with('user');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $tickets = $query->latest()->paginate(15);

        return view('admin.support_tickets.index', compact('tickets'));
    }

    /**
     * Display details of a specific support ticket.
     */
    public function show(string $id): View
    {
        $ticket = SupportTicket::query()->with('user')->findOrFail($id);

        return view('admin.support_tickets.show', compact('ticket'));
    }

    /**
     * Update the support ticket status and/or admin note.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $ticket = SupportTicket::query()->findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:open,in_progress,resolved,closed'],
            'priority' => ['required', 'string', 'in:low,normal,high,urgent'],
            'admin_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $previousStatus = $ticket->status;

        $ticket->status = $validated['status'];
        $ticket->priority = $validated['priority'];
        $ticket->admin_note = $validated['admin_note'];

        if ($validated['status'] === 'resolved' && $previousStatus !== 'resolved') {
            $ticket->resolved_at = now();

            try {
                Mail::to($ticket->email)->send(new SupportTicketResolvedMail($ticket));
            } catch (\Throwable $e) {
                Log::error('Failed to send support ticket resolution email.', [
                    'ticket_id' => $ticket->id,
                    'message' => $e->getMessage(),
                ]);
            }
        } elseif ($validated['status'] !== 'resolved') {
            $ticket->resolved_at = null;
        }

        $ticket->save();

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

        $attachmentsList = [];
        $attachedNames = [];

        if ($request->hasFile('attachments')) {
            /** @var array<UploadedFile> $files */
            $files = (array) $request->file('attachments');
            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    $originalName = $file->getClientOriginalName();
                    $extension = $file->getClientOriginalExtension();
                    $filename = time().'_'.Str::random(8).($extension ? '.'.$extension : '');
                    $storedPath = $file->storeAs('ticket_attachments/'.$ticket->id, $filename, 'public');

                    $fullPath = storage_path('app/public/'.$storedPath);
                    $attachmentsList[] = [
                        'path' => $fullPath,
                        'name' => $originalName,
                        'mime' => $file->getClientMimeType(),
                    ];
                    $attachedNames[] = $originalName;
                }
            }
        }

        $mailable = new SupportTicketResponseMail($ticket, $subject, $message, $attachmentsList);

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
        $attachmentLogStr = ! empty($attachedNames) ? "\nAttachments: ".implode(', ', $attachedNames) : '';
        $noteEntry = "[Email Sent - {$timestamp}]{$attachmentLogStr}\nSubject: {$subject}\n\n{$message}";
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
