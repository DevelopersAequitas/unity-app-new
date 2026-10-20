<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Mail\SupportTicketSubmittedMail;
use App\Models\FileModel;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\EmailLogs\EmailLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PublicSupportService
{
    public function __construct(
        protected readonly EmailLogService $emailLogService
    ) {}

    /**
     * Create a new support ticket submitted from the public support portal.
     *
     * @param  array{contact_name: string, email: string, phone?: ?string, category?: ?string, priority?: ?string, subject: string, description: string}  $data
     */
    public function createTicket(array $data, ?UploadedFile $media = null, ?User $user = null): SupportTicket
    {
        $subject = trim($data['subject']);
        if (! empty($data['category']) && ! str_starts_with($subject, '[')) {
            $subject = '['.trim((string) $data['category']).'] '.$subject;
        }

        $description = trim($data['description']);
        if (! empty($data['phone'])) {
            $description .= "\n\nContact Phone: ".trim((string) $data['phone']);
        }
        if (! empty($data['category'])) {
            $description .= "\nCategory: ".trim((string) $data['category']);
        }

        $mediaPayload = $media ? $this->uploadMedia($media, $user) : null;

        $ticket = new SupportTicket;
        $ticket->user_id = $user?->id;
        $ticket->contact_name = trim($data['contact_name']);
        $ticket->email = strtolower(trim($data['email']));
        $ticket->subject = $subject;
        $ticket->description = $description;
        $ticket->status = 'open';
        $ticket->priority = ! empty($data['priority']) ? (string) $data['priority'] : 'normal';
        $ticket->ticket_number = $this->generateTicketNumber();

        if ($mediaPayload) {
            $ticket->media_file_id = $mediaPayload['file_id'];
            $ticket->media_type = $mediaPayload['type'];
            $ticket->media_url = $mediaPayload['url'];
        }

        $ticket->save();

        $this->sendConfirmationEmail($ticket);

        return $ticket;
    }

    /**
     * Check the status of an existing ticket by ticket number/ID and email.
     */
    public function findTicketForStatus(string $ticketNumber, string $email): ?SupportTicket
    {
        $cleanNumber = trim($ticketNumber);
        $cleanEmail = strtolower(trim($email));
        $isUuid = \Illuminate\Support\Str::isUuid($cleanNumber);

        return SupportTicket::query()
            ->where(function ($query) use ($cleanNumber, $isUuid): void {
                $query->where('ticket_number', $cleanNumber);
                if ($isUuid) {
                    $query->orWhere('id', $cleanNumber);
                }
            })
            ->whereRaw('LOWER(email) = ?', [$cleanEmail])
            ->first();
    }

    /**
     * Upload an attachment for the support ticket.
     *
     * @return array{file_id: string, type: string, url: string}
     */
    protected function uploadMedia(UploadedFile $media, ?User $user): array
    {
        $disk = (string) config('filesystems.default', 'public');
        $path = $media->store('uploads/'.now()->format('Y/m/d'), $disk);

        $file = FileModel::create([
            'uploader_user_id' => $user?->id,
            's3_key' => $path,
            'mime_type' => $media->getClientMimeType(),
            'size_bytes' => $media->getSize(),
        ]);

        $mime = (string) $media->getClientMimeType();
        $mediaType = str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'video/') ? 'video' : 'document');
        $baseUrl = rtrim((string) config('app.url'), '/');

        return [
            'file_id' => (string) $file->id,
            'type' => $mediaType,
            'url' => $baseUrl.'/api/v1/files/'.$file->id,
        ];
    }

    /**
     * Generate an incremental ticket number with date prefix.
     */
    protected function generateTicketNumber(): string
    {
        $datePart = now()->format('Ymd');
        $prefix = 'SUP-'.$datePart.'-';

        $latestTicket = SupportTicket::query()
            ->where('ticket_number', 'like', $prefix.'%')
            ->orderByDesc('ticket_number')
            ->first();

        $nextNumber = 1;
        if ($latestTicket && is_string($latestTicket->ticket_number)) {
            $parts = explode('-', $latestTicket->ticket_number);
            $lastPart = end($parts);
            if (is_numeric($lastPart)) {
                $nextNumber = ((int) $lastPart) + 1;
            }
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Send confirmation email to the user who opened the ticket.
     */
    protected function sendConfirmationEmail(SupportTicket $ticket): void
    {
        if (empty($ticket->email)) {
            return;
        }

        $mail = new SupportTicketSubmittedMail($ticket);

        try {
            Mail::to($ticket->email)->send($mail);
            $this->emailLogService->logMailableSent($mail, [
                'to_email' => $ticket->email,
                'to_name' => $ticket->contact_name,
                'template_key' => 'support_ticket_submitted',
                'source_module' => 'support',
                'related_type' => 'support_ticket',
                'related_id' => $ticket->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send public support ticket confirmation email.', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);

            try {
                $this->emailLogService->logMailableFailed($mail, [
                    'to_email' => $ticket->email,
                    'to_name' => $ticket->contact_name,
                    'template_key' => 'support_ticket_submitted',
                    'source_module' => 'support',
                    'related_type' => 'support_ticket',
                    'related_id' => $ticket->id,
                ], $e);
            } catch (\Throwable) {
                // Ignore secondary logging failure to maintain resilience
            }
        }
    }
}
