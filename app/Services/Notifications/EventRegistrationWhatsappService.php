<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Exceptions\MetaWhatsAppException;
use App\Models\EventRegistration;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Services\Events\EventRegistrationQrService;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class EventRegistrationWhatsappService
{
    /**
     * Candidate Meta template names in priority order.
     */
    public const CANDIDATE_TEMPLATES = [
        'event_registration_qr_pass',
        'event_registration',
        'peers_event_pass',
    ];

    public function __construct(
        private readonly MetaWhatsAppService $metaWhatsAppService,
        private readonly WhatsappNotificationService $legacyNotificationService
    ) {}

    /**
     * Resolve the active approved template for event registration QR pass.
     */
    public function resolveTemplate(): ?WhatsappTemplate
    {
        // 1. Direct match on candidates with APPROVED or ACTIVE status
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

        // 3. Fallback: Search any template matching event_registration or event_pass
        return WhatsappTemplate::query()
            ->where(function ($q): void {
                $q->where('name', 'ILIKE', '%event_registration%')
                    ->orWhere('name', 'ILIKE', '%event%pass%')
                    ->orWhere('template_key', 'ILIKE', '%event_registration%')
                    ->orWhere('template_key', 'ILIKE', '%event%pass%');
            })
            ->whereIn('status', ['APPROVED', 'ACTIVE'])
            ->first();
    }

    /**
     * Active dispatch tracker to prevent recursive re-entrancy.
     *
     * @var array<string, bool>
     */
    private static array $sendingRegistrations = [];

    /**
     * Trigger WhatsApp notification for an event registration safely via Meta Cloud API.
     */
    public function sendNotification(EventRegistration $registration, bool $force = false): bool
    {
        $regId = (string) $registration->id;
        if (isset(self::$sendingRegistrations[$regId])) {
            return false;
        }

        self::$sendingRegistrations[$regId] = true;
        $phone = '';

        try {
            $registration = $registration->fresh(['user', 'event.circle', 'occurrence']) ?? $registration;

            $status = strtolower((string) ($registration->status ?? ''));
            $paymentStatus = strtolower((string) ($registration->payment_status ?? ''));
            $paymentRequired = (bool) ($registration->payment_required ?? false);

            if ($status !== 'registered') {
                Log::info('[EventRegistrationWhatsappService] Skipped: Status is not registered.', [
                    'registration_id' => (string) $registration->id,
                    'status' => $status,
                ]);

                return false;
            }

            // Strictly require payment to be completed if payment was required
            if ($paymentRequired && ! in_array($paymentStatus, ['paid', 'success', 'completed'], true)) {
                Log::info('[EventRegistrationWhatsappService] Skipped: Payment required but not completed yet.', [
                    'registration_id' => (string) $registration->id,
                    'payment_status' => $paymentStatus,
                    'payment_required' => true,
                ]);

                return false;
            }

            if (! $force && $this->isAlreadySent($registration)) {
                Log::info('[EventRegistrationWhatsappService] Skipped: Already sent for this registration.', [
                    'registration_id' => (string) $registration->id,
                ]);

                return false;
            }

            $phoneData = $this->resolvePhone($registration);
            $phone = $phoneData['phone'];
            $rawPhone = $phoneData['raw_phone'];
            $phoneSource = $phoneData['source'];

            if ($phone === '') {
                Log::warning('[EventRegistrationWhatsappService] Skipped: Missing or invalid phone number.', [
                    'registration_id' => (string) $registration->id,
                    'user_id' => (string) ($registration->user_id ?? ''),
                    'attendee_type' => $registration->user_id ? 'member' : 'visitor',
                    'raw_phone' => $rawPhone,
                    'source' => $phoneSource,
                ]);

                $this->updateRegistrationWhatsappStatus($registration, 'failed');
                $this->logDelivery($registration, '', 'skipped', 'Missing or invalid phone number');

                return false;
            }

            // Ensure QR Code is generated and available
            $qrService = app(EventRegistrationQrService::class);
            $registration = $qrService->ensureQrGenerated($registration);
            $qrCodeUrl = (string) ($qrService->qrCodeUrl($registration) ?: $registration->qr_code_url ?: '');

            $userName = null;
            if ($registration->user) {
                $dispName = trim((string) ($registration->user->display_name ?? ''));
                $firstLast = trim(trim((string) ($registration->user->first_name ?? '')).' '.trim((string) ($registration->user->last_name ?? '')));
                $rawName = trim((string) ($registration->user->name ?? ''));
                $adminName = method_exists($registration->user, 'adminDisplayName')
                    ? trim((string) $registration->user->adminDisplayName())
                    : '';

                $userName = $dispName !== ''
                    ? $dispName
                    : ($firstLast !== ''
                        ? $firstLast
                        : ($rawName !== ''
                            ? $rawName
                            : ($adminName !== '—' && $adminName !== '' ? $adminName : null)));
            }

            $recipientName = trim((string) ($registration->visitor_name ?: $userName ?: 'Valued Guest'));
            if ($recipientName === '' || $recipientName === '—') {
                $recipientName = 'Valued Guest';
            }

            $event = $registration->event;
            $occurrence = $registration->occurrence;

            $startAt = $occurrence?->start_at ?? $event?->start_at;
            $endAt = $occurrence?->end_at ?? $event?->end_at;

            $eventName = trim((string) ($event?->title ?? 'Peers Global Event'));
            $registrationId = (string) $registration->id;
            $eventDate = $startAt ? $startAt->format('d M Y') : 'TBA';
            $eventTime = $startAt ? ($startAt->format('h:i A').($endAt ? ' - '.$endAt->format('h:i A') : '')) : 'TBA';
            $venue = trim((string) ($event?->location_text ?: $event?->location ?: $event?->venue ?: 'As communicated by organizer'));

            $eventId = (string) ($registration->event_id ?? $event?->id ?? '');
            $entryPassUrl = $qrCodeUrl ?: ('https://peersunity.com/share?type=event_qr&id='.$eventId);

            // Resolve approved Meta WhatsApp template
            $template = $this->resolveTemplate();

            if (! $template) {
                $errorMsg = 'No approved WhatsApp template found for event registration QR pass (searched: ' . implode(', ', self::CANDIDATE_TEMPLATES) . ').';
                Log::error('[EventRegistrationWhatsappService] ' . $errorMsg);

                $this->updateRegistrationWhatsappStatus($registration, 'failed');
                $this->logDelivery($registration, $phone, 'failed', $errorMsg);

                return false;
            }

            // Resolve image header url for Meta (handling localhost/dev gracefully)
            $headerImageUrl = $this->resolveHeaderImageUrl($template, $qrCodeUrl);

            // Build dynamic components according to template
            $components = $this->buildComponents(
                template: $template,
                headerImageUrl: $headerImageUrl,
                eventName: $eventName,
                registrationId: $registrationId,
                recipientName: $recipientName,
                eventDate: $eventDate,
                eventTime: $eventTime,
                venue: $venue,
                entryPassUrl: $entryPassUrl
            );

            $payload = [
                'event_name' => $eventName,
                'registration_id' => $registrationId,
                'participant_name' => $recipientName,
                'name' => $recipientName,
                'event_date' => $eventDate,
                'event_time' => $eventTime,
                'venue' => $venue,
                'entry_pass_url' => $entryPassUrl,
                'qr_code_url' => $qrCodeUrl,
                'header_image_url' => $headerImageUrl,
                'phone' => $phone,
                'normalized_phone' => $phone,
                'raw_phone' => $rawPhone,
                'phone_source' => $phoneSource,
                'template_name' => $template->name,
            ];

            Log::info('[EventRegistrationWhatsappService] Dispatching Meta template event QR pass.', [
                'registration_id' => (string) $registration->id,
                'template_name' => $template->name,
                'recipient' => $phone,
                'attendee_name' => $recipientName,
                'components' => $components,
            ]);

            $templateLang = $template->language ?: 'en_IN';
            $metaResult = $this->metaWhatsAppService->sendTemplateMessage(
                recipientPhone: $phone,
                templateName: $template->name,
                languageCode: $templateLang,
                components: $components,
                userId: $registration->user_id
            );

            $providerMessageId = $metaResult['meta_message_id'] ?? null;

            if (! empty($metaResult['success']) || ! empty($providerMessageId)) {
                $now = now();
                $this->updateRegistrationWhatsappStatus(
                    $registration,
                    'sent',
                    $now->toDateTimeString(),
                    $providerMessageId,
                    $metaResult
                );

                $this->logDelivery($registration, $phone, 'sent', null, $payload, $providerMessageId);

                Log::info('[EventRegistrationWhatsappService] Event QR pass delivered successfully via Meta WhatsApp.', [
                    'registration_id' => (string) $registration->id,
                    'phone' => $phone,
                    'provider_message_id' => $providerMessageId,
                ]);

                return true;
            }

            $errorMsg = 'Meta WhatsApp dispatch returned unsuccessful response.';
            $this->updateRegistrationWhatsappStatus($registration, 'failed', null, null, $metaResult);
            $this->logDelivery($registration, $phone, 'failed', $errorMsg, $payload);

            return false;
        } catch (MetaWhatsAppException $e) {
            Log::error('[EventRegistrationWhatsappService] Meta WhatsApp API error: ' . $e->getMessage(), [
                'registration_id' => (string) ($registration->id ?? ''),
                'phone' => $phone,
                'error_code' => $e->getMetaErrorCode(),
                'error_subcode' => $e->getMetaErrorSubcode(),
            ]);

            $this->updateRegistrationWhatsappStatus($registration, 'failed', null, null, [
                'error' => $e->getMessage(),
                'error_code' => $e->getMetaErrorCode(),
                'error_subcode' => $e->getMetaErrorSubcode(),
            ]);

            $this->logDelivery($registration, $phone, 'failed', $e->getMessage());

            return false;
        } catch (Throwable $e) {
            Log::error('[EventRegistrationWhatsappService] Exception sending event QR WhatsApp: ' . $e->getMessage(), [
                'registration_id' => (string) ($registration->id ?? ''),
                'phone' => $phone,
            ]);

            $this->updateRegistrationWhatsappStatus($registration, 'failed', null, null, [
                'error' => $e->getMessage(),
            ]);

            $this->logDelivery($registration, $phone, 'failed', $e->getMessage());

            return false;
        } finally {
            unset(self::$sendingRegistrations[$regId]);
        }
    }

    /**
     * Build Meta template components matching template specification.
     *
     * @return list<array<string, mixed>>
     */
    protected function buildComponents(
        WhatsappTemplate $template,
        string $headerImageUrl,
        string $eventName,
        string $registrationId,
        string $recipientName,
        string $eventDate,
        string $eventTime,
        string $venue,
        string $entryPassUrl
    ): array {
        $components = [];

        // 1. HEADER (Image component with QR Code)
        if (! empty($headerImageUrl)) {
            $components[] = [
                'type' => 'header',
                'parameters' => [
                    [
                        'type' => 'image',
                        'image' => [
                            'link' => $headerImageUrl,
                        ],
                    ],
                ],
            ];
        }

        // 2. BODY parameters
        if ($template->name === 'peers_event_pass') {
            // peers_event_pass has 5 positional parameters: {{1}}=Name, {{2}}=Event, {{3}}=Date, {{4}}=Time, {{5}}=Venue
            $components[] = [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $recipientName],
                    ['type' => 'text', 'text' => $eventName],
                    ['type' => 'text', 'text' => $eventDate],
                    ['type' => 'text', 'text' => $eventTime],
                    ['type' => 'text', 'text' => $venue],
                ],
            ];
        } else {
            // event_registration_qr_pass has 7 positional parameters:
            // {{1}} = Event Name
            // {{2}} = Registration ID
            // {{3}} = Attendee Name
            // {{4}} = Date
            // {{5}} = Time
            // {{6}} = Venue
            // {{7}} = Entry pass link
            $components[] = [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $eventName],
                    ['type' => 'text', 'text' => $registrationId],
                    ['type' => 'text', 'text' => $recipientName],
                    ['type' => 'text', 'text' => $eventDate],
                    ['type' => 'text', 'text' => $eventTime],
                    ['type' => 'text', 'text' => $venue],
                    ['type' => 'text', 'text' => $entryPassUrl],
                ],
            ];
        }

        return $components;
    }

    /**
     * Resolve public HTTPS image URL for Meta WhatsApp header.
     * In local development, safely falls back to template example header handle so Meta API does not reject localhost.
     */
    protected function resolveHeaderImageUrl(WhatsappTemplate $template, string $qrCodeUrl): string
    {
        $isLocal = empty($qrCodeUrl)
            || str_contains($qrCodeUrl, 'localhost')
            || str_contains($qrCodeUrl, '127.0.0.1');

        if ($isLocal) {
            // Extract approved example header handle from template definition if present
            $exampleHandle = null;
            if (! empty($template->components) && is_array($template->components)) {
                foreach ($template->components as $comp) {
                    if (strtoupper((string) ($comp['type'] ?? '')) === 'HEADER') {
                        $exampleHandle = $comp['example']['header_handle'][0] ?? null;
                        if (! empty($exampleHandle)) {
                            break;
                        }
                    }
                }
            }

            if (! empty($exampleHandle)) {
                return (string) $exampleHandle;
            }

            if (! empty($qrCodeUrl)) {
                return str_replace(
                    ['http://localhost:8000', 'http://127.0.0.1:8000', 'http://localhost', 'http://127.0.0.1'],
                    'https://dev.peersunity.com',
                    $qrCodeUrl
                );
            }

            return 'https://dev.peersunity.com/assets/images/default-event-qr.png';
        }

        return $qrCodeUrl;
    }

    /**
     * Resolve phone number following priority rules and apply normalization:
     * Priority 1: visitor_phone if available and non-empty.
     * Priority 2: user->phone if user_id exists.
     */
    public function resolvePhone(EventRegistration $registration): array
    {
        $rawVisitorPhone = trim((string) ($registration->visitor_phone ?? ''));
        if ($rawVisitorPhone !== '') {
            $normalized = static::normalizePhone($rawVisitorPhone);

            return [
                'raw_phone' => $rawVisitorPhone,
                'phone' => $normalized,
                'source' => 'visitor_phone',
            ];
        }

        if (! empty($registration->user_id)) {
            $user = $registration->user ?? User::query()->find($registration->user_id);
            $rawUserPhone = trim((string) ($user?->phone ?? ''));
            if ($rawUserPhone !== '') {
                $normalized = static::normalizePhone($rawUserPhone);

                return [
                    'raw_phone' => $rawUserPhone,
                    'phone' => $normalized,
                    'source' => 'user_phone',
                ];
            }

            $rawSecondary = trim((string) ($user?->secondary_mobile ?? ''));
            if ($rawSecondary !== '') {
                $normalized = static::normalizePhone($rawSecondary);

                return [
                    'raw_phone' => $rawSecondary,
                    'phone' => $normalized,
                    'source' => 'user_secondary_mobile',
                ];
            }
        }

        return [
            'raw_phone' => '',
            'phone' => '',
            'source' => 'none',
        ];
    }

    /**
     * Normalize phone number to standard international format (e.g. 919904978744).
     */
    public static function normalizePhone(?string $phone): string
    {
        return MetaWhatsAppService::normalizePhone($phone);
    }

    /**
     * Check if WhatsApp message was already sent for this registration.
     */
    public function isAlreadySent(EventRegistration $registration): bool
    {
        if (($registration->whatsapp_status ?? null) === 'sent') {
            return true;
        }

        if (Schema::hasTable('whatsapp_messages')) {
            $phoneData = $this->resolvePhone($registration);
            $phone = $phoneData['phone'];

            if (! empty($phone)) {
                $hasSentMeta = WhatsappMessage::query()
                    ->where('recipient_phone', $phone)
                    ->whereIn('template_name', self::CANDIDATE_TEMPLATES)
                    ->whereIn('status', ['SENT', 'DELIVERED', 'READ'])
                    ->where(function ($q) use ($registration): void {
                        $q->where('request_payload', 'like', '%' . $registration->id . '%');
                    })
                    ->exists();

                if ($hasSentMeta) {
                    return true;
                }
            }
        }

        if (! Schema::hasTable('notification_delivery_logs')) {
            return false;
        }

        try {
            return NotificationDeliveryLog::query()
                ->where('channel', 'whatsapp')
                ->whereIn('provider', self::CANDIDATE_TEMPLATES)
                ->where('status', 'sent')
                ->where(function ($query) use ($registration): void {
                    $query->where('request_payload->registration_id', (string) $registration->id)
                        ->orWhere('provider_message_id', (string) $registration->id);
                })
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Update whatsapp_status and associated columns on event_registrations table.
     */
    private function updateRegistrationWhatsappStatus(
        EventRegistration $registration,
        string $status,
        ?string $sentAt = null,
        ?string $providerMessageId = null,
        ?array $responsePayload = null
    ): void {
        try {
            $updates = [];
            if (Schema::hasColumn('event_registrations', 'whatsapp_status')) {
                $updates['whatsapp_status'] = $status;
            }
            if ($sentAt !== null && Schema::hasColumn('event_registrations', 'whatsapp_sent_at')) {
                $updates['whatsapp_sent_at'] = $sentAt;
            }
            if ($providerMessageId !== null && Schema::hasColumn('event_registrations', 'whatsapp_provider_id')) {
                $updates['whatsapp_provider_id'] = $providerMessageId;
            }
            if ($responsePayload !== null && Schema::hasColumn('event_registrations', 'whatsapp_response_payload')) {
                $updates['whatsapp_response_payload'] = $responsePayload;
            }

            if (! empty($updates)) {
                $registration->forceFill($updates)->save();
            }
        } catch (Throwable $e) {
            Log::error('[EventRegistrationWhatsappService] Failed to update event_registration whatsapp status.', [
                'registration_id' => (string) $registration->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log delivery attempt in notification_delivery_logs table.
     */
    private function logDelivery(
        EventRegistration $registration,
        string $phone,
        string $status,
        ?string $errorMessage = null,
        array $payload = [],
        ?string $providerMessageId = null
    ): void {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return;
        }

        try {
            NotificationDeliveryLog::query()->create([
                'user_id' => (string) ($registration->user_id ?: null),
                'channel' => 'whatsapp',
                'provider' => 'event_registration_qr_pass',
                'provider_message_id' => $providerMessageId ?: (string) $registration->id,
                'status' => $status,
                'request_payload' => array_merge([
                    'registration_id' => (string) $registration->id,
                    'template_key' => 'event_registration_qr_pass',
                    'phone' => $phone,
                ], $payload),
                'error_message' => $errorMessage,
                'attempted_at' => now(),
                'delivered_at' => $status === 'sent' ? now() : null,
            ]);
        } catch (Throwable $e) {
            Log::error('[EventRegistrationWhatsappService] Failed to log in NotificationDeliveryLog.', [
                'registration_id' => (string) $registration->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
