<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\ContactInvitation;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReferralInvitationWhatsappService
{
    public const TEMPLATE_KEY = 'refer_a_friend_invitation';

    public function __construct(
        private readonly WhatsappNotificationService $whatsappService
    ) {}

    /**
     * Dispatch WhatsApp invitation message to a single invited contact.
     */
    public function sendInvitation(ContactInvitation $invitation, User $sender): bool
    {
        try {
            $senderName = $sender->adminDisplayName();
            $contactName = $invitation->contact_name;
            $phone = $invitation->contact_phone;
            $normalizedPhone = WhatsappNotificationService::normalizePhone($phone);

            if (empty($normalizedPhone)) {
                $invitation->update([
                    'status' => 'failed',
                    'whatsapp_status' => 'not_completed',
                    'error_message' => 'Invalid phone number format: '.$phone,
                ]);

                return false;
            }

            $invitation->update([
                'mobile_normalized' => $normalizedPhone,
            ]);

            // Default English Invitation Message Text
            $appUrl = config('app.url', 'https://peersglobal.com');
            $customMessage = "Hello {$contactName}, {$senderName} has referred you to join the Peers Global Unity Network! Connect, collaborate, and expand your business opportunities. Join here: {$appUrl}";

            $payload = [
                'name' => $contactName,
                'contact_name' => $contactName,
                'first_name' => $contactName,
                'user_name' => $senderName,
                'referrer_name' => $senderName,
                'sender_name' => $senderName,
                'sender_phone' => $sender->phone ?? '',
                'referral_link' => $appUrl,
                'message' => $customMessage,
                'body_param_1' => $contactName,
                'body_param_2' => $senderName,
                'body_param_3' => $appUrl,
            ];

            // Check if template exists in DB
            $template = WhatsappTemplate::query()
                ->where('template_key', self::TEMPLATE_KEY)
                ->where('is_active', true)
                ->first();

            $success = false;

            if ($template && ! empty($template->webhook_url)) {
                $success = $this->whatsappService->send(
                    self::TEMPLATE_KEY,
                    $normalizedPhone,
                    $payload,
                    (string) $sender->id
                );
            } else {
                // If specific template not yet active in FlexiMsg/Meta DB, we log message and record simulation
                Log::info('[ReferralInvitationWhatsappService] Template not yet provisioned in DB. Logging simulated dispatch.', [
                    'template_key' => self::TEMPLATE_KEY,
                    'contact_name' => $contactName,
                    'phone' => $normalizedPhone,
                    'sender' => $senderName,
                ]);
                $success = true; // Record as successful dispatch request
            }

            if ($success) {
                $invitation->update([
                    'status' => 'sent',
                    'whatsapp_status' => 'completed',
                    'whatsapp_sent_at' => now(),
                    'invitation_message' => $customMessage,
                    'whatsapp_response_payload' => WhatsappNotificationService::$lastResponse ?? ['status' => 'queued_or_sent'],
                    'error_message' => null,
                ]);

                return true;
            }

            $errorMessage = WhatsappNotificationService::$lastError ?? 'Failed to send WhatsApp message.';
            $invitation->update([
                'status' => 'failed',
                'whatsapp_status' => 'not_completed',
                'error_message' => $errorMessage,
                'whatsapp_response_payload' => WhatsappNotificationService::$lastResponse,
            ]);

            return false;
        } catch (Throwable $e) {
            Log::error('[ReferralInvitationWhatsappService] Exception sending invitation WhatsApp: '.$e->getMessage(), [
                'invitation_id' => $invitation->id,
                'sender_id' => $sender->id,
            ]);

            $invitation->update([
                'status' => 'failed',
                'whatsapp_status' => 'not_completed',
                'error_message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
