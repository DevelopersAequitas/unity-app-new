<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendSupportTicketEmailRequest;
use App\Mail\SupportTicketResolvedMail;
use App\Mail\SupportTicketResponseMail;
use App\Models\SupportTicket;
use App\Services\EmailLogs\EmailLogService;
use App\Services\Notifications\NotificationService;
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
        protected EmailLogService $emailLogService,
        protected NotificationService $notificationService
    ) {}

    /**
     * Display a listing of support tickets.
     */
    public function index(Request $request): View
    {
        $query = SupportTicket::query()->with('user');

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('priority') && $request->input('priority') !== 'all') {
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
            $this->sendResolvedNotification($ticket);
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
        $action = $request->input('action', 'send_email');

        $subject = (string) $request->input('subject');
        $message = (string) $request->input('message');
        $status = $request->input('status');

        $notificationTitle = 'Support Ticket Update';
        $notificationBody = "Your support ticket #{$ticket->ticket_number} request has been accepted by our team. To see more details, please check your email.";

        $emailSent = false;
        $notificationSent = false;

        $attachmentsList = [];
        $attachedNames = [];

        // 1. Send Email if action is send_email or send_both
        if ($action === 'send_email' || $action === 'send_both') {
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
                $emailSent = true;
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
        }

        // 2. Send Push Notification if action is send_notification or send_both
        if ($action === 'send_notification' || $action === 'send_both') {
            if (! $ticket->user) {
                return redirect()->route('admin.support-tickets.show', $id)
                    ->with('error', 'Cannot send push notification: This ticket is not associated with a registered app account (Guest Submission).');
            }

            try {
                $this->notificationService->sendToUser(
                    $ticket->user,
                    'support_ticket_response',
                    $notificationTitle,
                    $notificationBody,
                    [
                        'ticket_id' => $ticket->id,
                        'ticket_number' => $ticket->ticket_number,
                        'screen' => 'support_tickets',
                    ],
                    [
                        'channel' => 'push',
                        'bypass_daily_limit' => true,
                    ]
                );
                $notificationSent = true;
            } catch (\Throwable $e) {
                Log::error('Failed to send support ticket push notification.', [
                    'ticket_id' => $ticket->id,
                    'message' => $e->getMessage(),
                ]);

                if ($emailSent) {
                    return redirect()->route('admin.support-tickets.show', $id)
                        ->with('warning', 'Email response sent successfully, but failed to send push notification: '.$e->getMessage());
                }

                return redirect()->route('admin.support-tickets.show', $id)
                    ->with('error', 'Failed to send push notification: '.$e->getMessage());
            }
        }

        // Record entry in admin_note log
        $timestamp = now()->format('Y-m-d H:i');
        if ($emailSent && $notificationSent) {
            $attachmentLogStr = ! empty($attachedNames) ? "\nAttachments: ".implode(', ', $attachedNames) : '';
            $noteEntry = "[Email & Push Notification Sent - {$timestamp}]{$attachmentLogStr}\nSubject: {$subject}\n\n{$message}\n\nNotification Body: {$notificationBody}";
        } elseif ($emailSent) {
            $attachmentLogStr = ! empty($attachedNames) ? "\nAttachments: ".implode(', ', $attachedNames) : '';
            $noteEntry = "[Email Sent - {$timestamp}]{$attachmentLogStr}\nSubject: {$subject}\n\n{$message}";
        } else {
            $noteEntry = "[Push Notification Sent - {$timestamp}]\nTitle: {$notificationTitle}\nBody: {$notificationBody}";
        }

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

        $successMessage = 'Response sent successfully.';
        if ($emailSent && $notificationSent) {
            $successMessage = 'Email response and push notification sent successfully.';
        } elseif ($emailSent) {
            $successMessage = 'Email response sent successfully to '.$ticket->email.'.';
        } elseif ($notificationSent) {
            $successMessage = 'Push notification sent successfully to customer app.';
        }

        return redirect()->route('admin.support-tickets.show', $id)->with('success', $successMessage);
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
