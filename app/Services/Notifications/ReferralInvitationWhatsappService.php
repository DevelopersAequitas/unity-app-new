<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Exceptions\MetaWhatsAppException;
use App\Models\ContactInvitation;
use App\Models\User;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReferralInvitationWhatsappService
{
    /**
     * Candidate Meta template names in priority order.
     */
    public const CANDIDATE_TEMPLATES = [
        'referr_a_peer_invitation',
        'refer_a_friend',
        'refer_a_friend_invitation',
        'peer_referral_invite',
    ];

    public function __construct(
        private readonly MetaWhatsAppService $metaWhatsAppService,
        private readonly WhatsappNotificationService $legacyNotificationService
    ) {}

    /**
     * Resolve the active approved template for referral invitations.
     */
    public function resolveTemplate(): ?WhatsappTemplate
    {
        // 1. Check known candidate names first
        $template = WhatsappTemplate::query()
            ->whereIn('name', self::CANDIDATE_TEMPLATES)
            ->whereIn('status', ['APPROVED', 'ACTIVE'])
            ->first();

        if ($template) {
            return $template;
        }

        // 2. Check by template_key
        $template = WhatsappTemplate::query()
            ->whereIn('template_key', self::CANDIDATE_TEMPLATES)
            ->where('is_active', true)
            ->first();

        if ($template) {
            return $template;
        }

        // 3. Fallback: Search any template matching referral or invite
        return WhatsappTemplate::query()
            ->where(function ($q) {
                $q->where('name', 'ILIKE', '%refer%')
                    ->orWhere('name', 'ILIKE', '%invite%')
                    ->orWhere('template_key', 'ILIKE', '%refer%')
                    ->orWhere('template_key', 'ILIKE', '%invite%');
            })
            ->whereIn('status', ['APPROVED', 'ACTIVE'])
            ->first();
    }

    /**
     * Dispatch WhatsApp invitation message to a single invited contact via Meta Cloud API.
     */
    public function sendInvitation(ContactInvitation $invitation, User $sender): bool
    {
        $normalizedPhone = null;

        try {
            $senderName = $sender->adminDisplayName();
            $contactName = $invitation->contact_name ?: 'Friend';
            $phone = $invitation->contact_phone;
            $normalizedPhone = MetaWhatsAppService::normalizePhone($phone);

            if (empty($normalizedPhone)) {
                $invitation->update([
                    'status' => 'failed',
                    'whatsapp_status' => 'not_completed',
                    'error_message' => 'Invalid phone number format: ' . $phone,
                ]);

                return false;
            }

            $invitation->update([
                'mobile_normalized' => $normalizedPhone,
            ]);

            // Resolve Referral Link
            $referralLink = null;
            if (! empty($invitation->invitation_message) && preg_match('/https?:\/\/[^\s]+/', $invitation->invitation_message, $urlMatch)) {
                $referralLink = rtrim($urlMatch[0], '.,!?:;');
            }

            if (empty($referralLink)) {
                try {
                    $referralService = app(\App\Services\Referrals\ReferralService::class);
                    $refData = $referralService->getOrCreateUserReferralLink($sender);
                    $referralLink = $refData['referral_link'] ?? null;
                } catch (Throwable $refEx) {
                    Log::warning('[ReferralInvitationWhatsappService] Could not generate dynamic referral link: ' . $refEx->getMessage());
                }
            }

            if (empty($referralLink)) {
                $referralLink = rtrim((string) config('app.url', 'https://peersunity.com'), '/') . '/register';
            }

            $customMessage = $invitation->invitation_message ?: "Hello {$contactName}, {$senderName} has invited you to Peers Global! Join using: {$referralLink}";

            // Resolve approved Meta WhatsApp template
            $template = $this->resolveTemplate();

            if (! $template) {
                $errorMsg = 'No approved WhatsApp template found for referral invitations (searched: ' . implode(', ', self::CANDIDATE_TEMPLATES) . ').';
                Log::error('[ReferralInvitationWhatsappService] ' . $errorMsg);

                $invitation->update([
                    'status' => 'failed',
                    'whatsapp_status' => 'not_completed',
                    'error_message' => $errorMsg,
                ]);

                return false;
            }

            // Build Meta components dynamically based on template definition
            $components = $this->buildComponents($template, $contactName, $senderName, $referralLink);

            Log::info('[ReferralInvitationWhatsappService] Dispatching Meta template invitation.', [
                'template_name' => $template->name,
                'recipient' => $normalizedPhone,
                'sender' => $senderName,
                'components' => $components,
            ]);

            // Dispatch message via MetaWhatsAppService
            $templateLang = $template->language ?: 'en_IN';
            $metaResult = $this->metaWhatsAppService->sendTemplateMessage(
                recipientPhone: $normalizedPhone,
                templateName: $template->name,
                languageCode: $templateLang,
                components: $components,
                userId: $sender->id
            );

            if (! empty($metaResult['success'])) {
                $invitation->update([
                    'status' => 'sent',
                    'whatsapp_status' => 'completed',
                    'whatsapp_sent_at' => now(),
                    'invitation_message' => $customMessage,
                    'whatsapp_provider_id' => $metaResult['meta_message_id'] ?? null,
                    'whatsapp_response_payload' => $metaResult,
                    'error_message' => null,
                ]);

                return true;
            }

            $errorMsg = 'Meta dispatch returned unsuccessful response.';
            $invitation->update([
                'status' => 'failed',
                'whatsapp_status' => 'not_completed',
                'error_message' => $errorMsg,
                'whatsapp_response_payload' => $metaResult,
            ]);

            return false;
        } catch (MetaWhatsAppException $e) {
            Log::error('[ReferralInvitationWhatsappService] Meta WhatsApp error: ' . $e->getMessage(), [
                'invitation_id' => $invitation->id,
                'sender_id' => $sender->id,
                'phone' => $normalizedPhone,
                'error_code' => $e->getErrorCode(),
                'error_subcode' => $e->getErrorSubcode(),
            ]);

            $invitation->update([
                'status' => 'failed',
                'whatsapp_status' => 'not_completed',
                'error_message' => $e->getMessage(),
                'whatsapp_response_payload' => [
                    'error' => $e->getMessage(),
                    'error_code' => $e->getErrorCode(),
                    'error_subcode' => $e->getErrorSubcode(),
                ],
            ]);

            return false;
        } catch (Throwable $e) {
            Log::error('[ReferralInvitationWhatsappService] Exception sending invitation WhatsApp: ' . $e->getMessage(), [
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

    /**
     * Build Meta template components (HEADER, BODY, BUTTONS) matching template schema.
     *
     * @return list<array<string, mixed>>
     */
    protected function buildComponents(
        WhatsappTemplate $template,
        string $contactName,
        string $senderName,
        string $referralLink
    ): array {
        $components = [];
        $rawComponents = $template->components ?? [];

        if (empty($rawComponents) || ! is_array($rawComponents)) {
            // Default 3 body parameters fallback
            return [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $contactName],
                        ['type' => 'text', 'text' => $senderName],
                        ['type' => 'text', 'text' => $referralLink],
                    ],
                ],
            ];
        }

        foreach ($rawComponents as $comp) {
            $type = strtoupper((string) ($comp['type'] ?? ''));

            if ($type === 'HEADER') {
                $format = strtoupper((string) ($comp['format'] ?? 'TEXT'));
                if ($format === 'TEXT') {
                    $headerText = (string) ($comp['text'] ?? '');
                    if (str_contains($headerText, '{{1}}')) {
                        $components[] = [
                            'type' => 'header',
                            'parameters' => [
                                ['type' => 'text', 'text' => $contactName],
                            ],
                        ];
                    }
                } elseif (in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
                    $logoUrl = asset('images/member_introduce_creatives/Connector.png');
                    $components[] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => strtolower($format),
                                strtolower($format) => ['link' => $logoUrl],
                            ],
                        ],
                    ];
                }
            } elseif ($type === 'BODY') {
                $bodyText = (string) ($comp['text'] ?? '');
                preg_match_all('/\{\{(\d+)\}\}/', $bodyText, $matches);
                $varCount = ! empty($matches[1]) ? count(array_unique($matches[1])) : 0;

                $parameters = [];
                // {{1}} -> Contact Name
                if ($varCount >= 1) {
                    $parameters[] = ['type' => 'text', 'text' => $contactName];
                }
                // {{2}} -> Inviter/Sender Name
                if ($varCount >= 2) {
                    $parameters[] = ['type' => 'text', 'text' => $senderName];
                }
                // {{3}} -> Referral / App URL
                if ($varCount >= 3) {
                    $parameters[] = ['type' => 'text', 'text' => $referralLink];
                }
                // Fallback for any extra variables ({{4}}, {{5}}, etc.)
                for ($i = 4; $i <= $varCount; $i++) {
                    $parameters[] = ['type' => 'text', 'text' => 'Peers Unity'];
                }

                if (! empty($parameters)) {
                    $components[] = [
                        'type' => 'body',
                        'parameters' => $parameters,
                    ];
                }
            } elseif ($type === 'BUTTONS') {
                $rawButtons = $comp['buttons'] ?? [];
                foreach ($rawButtons as $bIdx => $btn) {
                    $bType = strtoupper((string) ($btn['type'] ?? ''));
                    $bUrl = (string) ($btn['url'] ?? '');
                    if ($bType === 'URL' && str_contains($bUrl, '{{1}}')) {
                        $components[] = [
                            'type' => 'button',
                            'sub_type' => 'url',
                            'index' => (string) $bIdx,
                            'parameters' => [
                                ['type' => 'text', 'text' => 'ref'],
                            ],
                        ];
                    }
                }
            }
        }

        return $components;
    }
}

