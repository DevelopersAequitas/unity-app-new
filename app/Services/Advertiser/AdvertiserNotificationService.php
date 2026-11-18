<?php

declare(strict_types=1);

namespace App\Services\Advertiser;

use App\Events\UserNotificationCreated;
use App\Jobs\SendPushNotificationJob;
use App\Mail\AdvertiserNotificationMail;
use App\Models\AdminUser;
use App\Models\AdvertiserApplication;
use App\Models\AdvertiserSubscription;
use App\Models\BrandPartner;
use App\Models\BrandPartnerInquiry;
use App\Models\Notification;
use App\Models\Notifications\AppNotification;
use App\Models\User;
use App\Services\EmailLogs\EmailLogService;
use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AdvertiserNotificationService
{
    protected EmailLogService $emailLogService;

    public function __construct(
        protected WhatsAppMessageService $whatsAppMessageService,
        ?EmailLogService $emailLogService = null
    ) {
        $this->emailLogService = $emailLogService ?? app(EmailLogService::class);
    }

    /**
     * Send email verification code.
     */
    public function sendEmailVerification(AdvertiserApplication $application, string $plainOtp): void
    {
        try {
            Mail::to($application->applicant_email)->queue(
                new AdvertiserNotificationMail(
                    subjectLine: 'Peers Global Advertiser — Email Verification Code',
                    recipientName: $application->applicant_name,
                    heading: 'Verify Your Email Address',
                    mainMessage: "Your verification code is: {$plainOtp}\n\nThis code will expire in 10 minutes. Please enter this code to complete your advertiser application.",
                    actionUrl: null,
                    actionText: null,
                    details: [
                        'Application Number' => $application->application_number,
                        'Company Name' => $application->company_name,
                    ]
                )
            );
        } catch (Throwable $e) {
            Log::error('[AdvertiserNotification] Failed sending email verification: '.$e->getMessage());
        }
    }

    /**
     * Send application submitted confirmation.
     */
    public function sendApplicationSubmitted(AdvertiserApplication $application): void
    {
        $details = [
            'Application Number' => $application->application_number,
            'Company Name' => $application->company_name,
            'Selected Package' => $application->requested_package_name ?? 'Standard',
            'Submission Date' => now()->toFormattedDateString(),
        ];

        try {
            Mail::to($application->applicant_email)->queue(
                new AdvertiserNotificationMail(
                    subjectLine: 'Peers Global Advertiser Application Received — '.$application->application_number,
                    recipientName: $application->applicant_name,
                    heading: 'Application Received Successfully',
                    mainMessage: 'Thank you for applying to become a Brand Partner on Peers Global Unity. Our review team will review your application and brand assets shortly.',
                    details: $details
                )
            );
        } catch (Throwable $e) {
            Log::error('[AdvertiserNotification] Failed sending application submitted email: '.$e->getMessage());
        }

        $phone = $application->whatsapp_number ?: $application->applicant_phone;
        if (! empty($phone)) {
            $this->sendWhatsAppSafe('advertiser_application_received', $phone, [
                'name' => $application->applicant_name,
                'application_number' => $application->application_number,
                'company_name' => $application->company_name,
            ], $application->user_id, 'advertiser_submitted_'.$application->id);
        }
    }

    /**
     * Send corrections requested notification.
     */
    public function sendCorrectionsRequested(AdvertiserApplication $application, string $feedback): void
    {
        $details = [
            'Application Number' => $application->application_number,
            'Company Name' => $application->company_name,
        ];

        try {
            Mail::to($application->applicant_email)->queue(
                new AdvertiserNotificationMail(
                    subjectLine: 'Action Required: Update Your Brand Partner Application — '.$application->application_number,
                    recipientName: $application->applicant_name,
                    heading: 'Additional Information or Creative Changes Requested',
                    mainMessage: "Our review team has requested updates for your application.\n\nFeedback from Review Team:\n".$feedback."\n\nPlease sign in or visit the application tracking link to update your details or re-upload creatives.",
                    details: $details
                )
            );
        } catch (Throwable $e) {
            Log::error('[AdvertiserNotification] Failed sending corrections requested email: '.$e->getMessage());
        }

        $phone = $application->whatsapp_number ?: $application->applicant_phone;
        if (! empty($phone)) {
            $this->sendWhatsAppSafe('advertiser_corrections_requested', $phone, [
                'name' => $application->applicant_name,
                'application_number' => $application->application_number,
                'feedback' => $feedback,
            ], $application->user_id, 'advertiser_corrections_'.$application->id.'_'.time());
        }
    }

    /**
     * Send application approved notification (next step: payment).
     */
    public function sendApplicationApproved(AdvertiserApplication $application, ?string $customMessage = null): void
    {
        $message = $customMessage ?: 'Your Brand Partner application has been approved! Please sign in to Peers Global Unity to complete the payment and activation process. Once activation is complete, your brand campaign will be published and you can view performance analytics in the app.';

        $details = [
            'Application Number' => $application->application_number,
            'Company Name' => $application->company_name,
            'Approved Package' => $application->requested_package_name ?? 'Advertiser Package',
            'Amount' => ($application->requested_currency ?? 'INR').' '.number_format((float) ($application->requested_package_price ?? 0), 2),
        ];

        try {
            Mail::to($application->applicant_email)->queue(
                new AdvertiserNotificationMail(
                    subjectLine: 'Congratulations! Brand Partner Application Approved — '.$application->application_number,
                    recipientName: $application->applicant_name,
                    heading: 'Your Brand Partner Application is Approved',
                    mainMessage: $message,
                    details: $details
                )
            );
        } catch (Throwable $e) {
            Log::error('[AdvertiserNotification] Failed sending application approved email: '.$e->getMessage());
        }

        $phone = $application->whatsapp_number ?: $application->applicant_phone;
        if (! empty($phone)) {
            $this->sendWhatsAppSafe('advertiser_application_approved', $phone, [
                'name' => $application->applicant_name,
                'application_number' => $application->application_number,
                'company_name' => $application->company_name,
            ], $application->user_id, 'advertiser_approved_'.$application->id);
        }
    }

    /**
     * Send application rejected notification.
     */
    public function sendApplicationRejected(AdvertiserApplication $application, string $reason, ?string $applicantMessage = null): void
    {
        $mainMessage = $applicantMessage ?: "Thank you for your interest in becoming a Brand Partner on Peers Global Unity. After careful review, we regret to inform you that we are unable to approve your application at this time.\n\nReason: ".$reason;

        try {
            Mail::to($application->applicant_email)->queue(
                new AdvertiserNotificationMail(
                    subjectLine: 'Update Regarding Your Brand Partner Application — '.$application->application_number,
                    recipientName: $application->applicant_name,
                    heading: 'Application Status Update',
                    mainMessage: $mainMessage,
                    details: [
                        'Application Number' => $application->application_number,
                        'Company Name' => $application->company_name,
                    ]
                )
            );
        } catch (Throwable $e) {
            Log::error('[AdvertiserNotification] Failed sending application rejected email: '.$e->getMessage());
        }

        $phone = $application->whatsapp_number ?: $application->applicant_phone;
        if (! empty($phone)) {
            $this->sendWhatsAppSafe('advertiser_application_rejected', $phone, [
                'name' => $application->applicant_name,
                'application_number' => $application->application_number,
                'reason' => $reason,
            ], $application->user_id, 'advertiser_rejected_'.$application->id);
        }
    }

    /**
     * Send payment confirmed and campaign activated notification.
     */
    public function sendCampaignActivated(AdvertiserSubscription $subscription): void
    {
        $subscription->loadMissing(['application', 'user']);
        $application = $subscription->application;
        $recipientEmail = $application?->applicant_email ?? $subscription->user?->email;
        $recipientName = $application?->applicant_name ?? $subscription->user?->display_name ?? 'Valued Partner';

        if (empty($recipientEmail)) {
            return;
        }

        if (app()->environment('local', 'testing')) {
            Log::info("[AdvertiserNotification] Campaign activated notification simulated for {$recipientEmail} on subscription {$subscription->id}");

            return;
        }

        $details = [
            'Package' => $subscription->package_name_snapshot,
            'Duration' => $subscription->duration_months.' Months',
            'Start Date' => $subscription->starts_at?->toFormattedDateString() ?? now()->toFormattedDateString(),
            'Expiry Date' => $subscription->expires_at?->toFormattedDateString() ?? 'N/A',
            'Amount Paid' => $subscription->currency.' '.number_format((float) $subscription->amount, 2),
        ];

        try {
            Mail::to($recipientEmail)->queue(
                new AdvertiserNotificationMail(
                    subjectLine: 'Brand Partner Campaign Activated & Published — Peers Global Unity',
                    recipientName: $recipientName,
                    heading: 'Your Brand Partner Campaign is Now Active!',
                    mainMessage: 'Your payment has been verified and your Brand Partner listing is now live across the Peers Global Unity app and platform. You can now view real-time impressions, clicks and engagement metrics in your Advertiser Dashboard.',
                    details: $details
                )
            );
        } catch (Throwable $e) {
            Log::error('[AdvertiserNotification] Failed sending campaign activated email: '.$e->getMessage());
        }

        $phone = $application?->whatsapp_number ?: ($application?->applicant_phone ?: ($subscription->user?->phone ?? ''));
        if (! empty($phone)) {
            $this->sendWhatsAppSafe('advertiser_campaign_published', $phone, [
                'name' => $recipientName,
                'company_name' => $application?->company_name ?? 'Brand Partner',
                'package_name' => $subscription->package_name_snapshot,
            ], $subscription->user_id, 'advertiser_activated_'.$subscription->id);
        }
    }

    /**
     * Send subscription expiry reminder notification.
     */
    public function sendExpiryReminder(AdvertiserSubscription $subscription, int $daysRemaining): void
    {
        $subscription->loadMissing(['application', 'user']);
        $recipientEmail = $subscription->application?->applicant_email ?? $subscription->user?->email;
        $recipientName = $subscription->application?->applicant_name ?? $subscription->user?->display_name ?? 'Valued Partner';

        if (empty($recipientEmail)) {
            return;
        }

        $details = [
            'Package' => $subscription->package_name_snapshot,
            'Expiry Date' => $subscription->expires_at?->toFormattedDateString() ?? 'N/A',
            'Days Remaining' => (string) $daysRemaining,
        ];

        try {
            Mail::to($recipientEmail)->queue(
                new AdvertiserNotificationMail(
                    subjectLine: "Reminder: Your Brand Partner Subscription Expires in {$daysRemaining} Days",
                    recipientName: $recipientName,
                    heading: 'Subscription Expiry Notice',
                    mainMessage: "Your advertising subscription on Peers Global Unity will expire in {$daysRemaining} days. To maintain uninterrupted visibility and featured placement, please renew your subscription.",
                    details: $details
                )
            );
        } catch (Throwable $e) {
            Log::error('[AdvertiserNotification] Failed sending expiry reminder email: '.$e->getMessage());
        }
    }

    /**
     * Resolve the Brand Partner's recipient email, recipient name, and owner User across all linked records.
     *
     * @return array{email: string, name: string, user: User|null}
     */
    public function resolveBrandPartnerRecipient(BrandPartner $partner): array
    {
        $brandName = trim((string) ($partner->name ?: 'Brand Partner'));
        $recipientName = "{$brandName} Team";
        $recipientEmail = trim((string) ($partner->contact_email ?? ''));
        $ownerUser = null;

        $meta = is_array($partner->metadata) ? $partner->metadata : [];

        // 1. Check metadata applicant_user_id
        if (! empty($meta['applicant_user_id']) && Schema::hasTable('users')) {
            $ownerUser = User::find((string) $meta['applicant_user_id']);
        }

        // 2. Check metadata application_id or linked AdvertiserApplication
        $linkedApp = null;
        if (Schema::hasTable('advertiser_applications')) {
            if (! empty($meta['application_id'])) {
                $linkedApp = AdvertiserApplication::find((string) $meta['application_id']);
            }
            if (! $linkedApp) {
                $linkedApp = AdvertiserApplication::where('brand_partner_id', $partner->id)
                    ->orWhereRaw('LOWER(TRIM(company_name)) = ?', [strtolower($brandName)])
                    ->latest()
                    ->first();
            }
        }

        if ($linkedApp) {
            if (! $ownerUser && ! empty($linkedApp->user_id) && Schema::hasTable('users')) {
                $ownerUser = User::find((string) $linkedApp->user_id);
            }
            if ($recipientEmail === '' || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                $recipientEmail = trim((string) ($linkedApp->company_email ?: ($linkedApp->applicant_email ?? '')));
            }
            if (! empty($linkedApp->applicant_name)) {
                $recipientName = (string) $linkedApp->applicant_name;
            }
        }

        // 3. Check metadata subscription_id or linked AdvertiserSubscription
        if (Schema::hasTable('advertiser_subscriptions')) {
            $linkedSub = null;
            if (! empty($meta['subscription_id'])) {
                $linkedSub = AdvertiserSubscription::with(['user', 'application'])->find((string) $meta['subscription_id']);
            }
            if (! $linkedSub) {
                $linkedSub = AdvertiserSubscription::with(['user', 'application'])
                    ->where('brand_partner_id', $partner->id)
                    ->latest()
                    ->first();
            }

            if ($linkedSub) {
                if (! $ownerUser && $linkedSub->user) {
                    $ownerUser = $linkedSub->user;
                }
                if ($recipientEmail === '' || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                    $recipientEmail = trim((string) (
                        $linkedSub->application?->company_email
                        ?: $linkedSub->application?->applicant_email
                        ?: $linkedSub->user?->email
                        ?: ''
                    ));
                }
            }
        }

        // 4. Check metadata email fields
        if ($recipientEmail === '' || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $metaEmail = trim((string) ($meta['contact_email'] ?? $meta['email'] ?? $meta['applicant_email'] ?? ''));
            if (filter_var($metaEmail, FILTER_VALIDATE_EMAIL)) {
                $recipientEmail = $metaEmail;
            }
        }

        // 5. Lookup User by email or company_name
        if (! $ownerUser && Schema::hasTable('users')) {
            if ($recipientEmail !== '' && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                $ownerUser = User::whereRaw('LOWER(TRIM(email)) = ?', [strtolower($recipientEmail)])->first();
            }
            if (! $ownerUser && $brandName !== '') {
                $ownerUser = User::whereRaw('LOWER(TRIM(company_name)) = ?', [strtolower($brandName)])->first();
            }
        }

        if (($recipientEmail === '' || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) && $ownerUser && ! empty($ownerUser->email)) {
            $recipientEmail = trim((string) $ownerUser->email);
        }

        // 6. Fallback to AdminUser creator or system mail.from.address if Brand Partner was created manually without contact_email
        if ($recipientEmail === '' || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            if (! empty($partner->created_by) && Schema::hasTable('admin_users')) {
                try {
                    $adminCreator = AdminUser::find($partner->created_by);
                    if ($adminCreator && ! empty($adminCreator->email) && filter_var($adminCreator->email, FILTER_VALIDATE_EMAIL)) {
                        $recipientEmail = trim((string) $adminCreator->email);
                    }
                } catch (Throwable) {
                }
            }
        }

        if ($recipientEmail === '' || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $fallbackFrom = trim((string) config('mail.from.address', 'pravin@peersunity.com'));
            if (filter_var($fallbackFrom, FILTER_VALIDATE_EMAIL)) {
                $recipientEmail = $fallbackFrom;
            }
        }

        // Backfill contact_email on the BrandPartner row if it was empty
        if (empty($partner->contact_email) && $recipientEmail !== '' && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            try {
                $partner->update(['contact_email' => $recipientEmail]);
            } catch (Throwable) {
            }
        }

        return [
            'email' => $recipientEmail,
            'name' => $recipientName,
            'user' => $ownerUser,
        ];
    }

    /**
     * Send Mailable with automatic fallback mailer and EmailLogService persistence.
     *
     * @param  array<string, mixed>  $logData
     */
    protected function sendMailableWithLog(Mailable $mailable, string $toEmail, array $logData): bool
    {
        if ($toEmail === '' || ! filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            Mail::to($toEmail)->send($mailable);
            $this->emailLogService->logMailableSent($mailable, $logData);

            return true;
        } catch (Throwable $firstException) {
            // Automatic fallback to 'pravin' SMTP mailer if configured and primary mailer failed
            try {
                if (config('mail.mailers.pravin')) {
                    Mail::mailer('pravin')->to($toEmail)->send($mailable);
                    $this->emailLogService->logMailableSent($mailable, $logData);

                    return true;
                }
            } catch (Throwable) {
                // fall through to logFailed below
            }

            $this->emailLogService->logMailableFailed($mailable, $logData, $firstException);
            Log::error('[AdvertiserNotification] Email dispatch failed: '.$firstException->getMessage(), [
                'to_email' => $toEmail,
                'template_key' => $logData['template_key'] ?? null,
            ]);

            return false;
        }
    }

    /**
     * Send instant callback request notification email to the Brand Partner's contact_email
     * with Reply-To set to the requesting member's email, plus a confirmation copy to the requesting member.
     */
    public function sendCallbackRequestNotification(
        BrandPartner $partner,
        BrandPartnerInquiry $inquiry,
        ?string $recipientEmail = null
    ): bool {
        $resolved = $this->resolveBrandPartnerRecipient($partner);
        $targetEmail = (! empty($recipientEmail) && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL))
            ? trim($recipientEmail)
            : $resolved['email'];
        $ownerUser = $resolved['user'];

        $brandName = trim((string) ($partner->name ?: 'Brand Partner'));
        $subjectLine = "📞 New Callback Request: {$inquiry->name} on Peers Global Unity";
        $heading = 'New Callback Request from a Peers Global Member';
        $mainMessage = "Hi {$brandName} Team,\n\n"
            ."A member on the Peers Global Unity app has requested a callback.\n\n"
            ."Member Details:\n"
            ."• Name:  {$inquiry->name}\n"
            ."• Phone: {$inquiry->phone}\n"
            ."• Email: {$inquiry->email}\n\n"
            ."Requirement / Message:\n\"{$inquiry->message}\"\n\n"
            ."You can reply directly to this email or call the member at {$inquiry->phone}.";

        $details = [
            'Member Name' => $inquiry->name,
            'Phone' => $inquiry->phone,
            'Email' => $inquiry->email,
            'Brand Partner' => $brandName,
            'Requested At' => ($inquiry->created_at ?? now())->toDayDateTimeString(),
        ];

        $partnerMailable = new AdvertiserNotificationMail(
            subjectLine: $subjectLine,
            recipientName: "{$brandName} Team",
            heading: $heading,
            mainMessage: $mainMessage,
            actionUrl: 'mailto:'.$inquiry->email,
            actionText: 'Reply to '.$inquiry->name,
            details: $details,
            replyToEmail: $inquiry->email,
            replyToName: $inquiry->name
        );

        $partnerSent = false;
        if ($targetEmail !== '' && filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            $partnerSent = $this->sendMailableWithLog($partnerMailable, $targetEmail, [
                'user_id' => $ownerUser?->id,
                'to_email' => $targetEmail,
                'to_name' => "{$brandName} Team",
                'template_key' => 'brand_partner_callback_request',
                'subject' => $subjectLine,
                'source_module' => 'Brand Partners',
                'related_type' => BrandPartnerInquiry::class,
                'related_id' => (string) $inquiry->id,
                'source_type' => 'brand_partner_callback',
                'source_id' => (string) $partner->id,
                'source_event' => 'callback_requested',
                'triggered_user_id' => $inquiry->user_id,
                'payload' => [
                    'brand_partner_id' => (string) $partner->id,
                    'brand_partner_name' => $brandName,
                    'inquiry_id' => (string) $inquiry->id,
                    'requester_name' => $inquiry->name,
                    'requester_phone' => $inquiry->phone,
                    'requester_email' => $inquiry->email,
                ],
            ]);
        }

        // Also send a confirmation copy to the requesting member if their email is valid and distinct from targetEmail
        $requesterEmail = trim((string) $inquiry->email);
        $requesterSent = false;
        if ($requesterEmail !== '' && filter_var($requesterEmail, FILTER_VALIDATE_EMAIL) && strtolower($requesterEmail) !== strtolower($targetEmail)) {
            $confirmSubject = "✅ Callback Request Sent to {$brandName} — Peers Global Unity";
            $confirmMailable = new AdvertiserNotificationMail(
                subjectLine: $confirmSubject,
                recipientName: $inquiry->name,
                heading: "Your Callback Request Has Been Sent to {$brandName}",
                mainMessage: "Thank you for connecting with {$brandName} on Peers Global Unity. We have shared your contact details and requirement directly with the {$brandName} team so they can reach out to you shortly.\n\n"
                    ."Your Submitted Message:\n\"{$inquiry->message}\"",
                details: [
                    'Brand Partner' => $brandName,
                    'Your Name' => $inquiry->name,
                    'Your Phone' => $inquiry->phone,
                    'Your Email' => $inquiry->email,
                    'Submitted At' => ($inquiry->created_at ?? now())->toDayDateTimeString(),
                ],
                replyToEmail: ($targetEmail !== '' && filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) ? $targetEmail : null,
                replyToName: $brandName
            );

            $requesterSent = $this->sendMailableWithLog($confirmMailable, $requesterEmail, [
                'user_id' => $inquiry->user_id,
                'to_email' => $requesterEmail,
                'to_name' => $inquiry->name,
                'template_key' => 'brand_partner_callback_confirmation',
                'subject' => $confirmSubject,
                'source_module' => 'Brand Partners',
                'related_type' => BrandPartnerInquiry::class,
                'related_id' => (string) $inquiry->id,
                'source_type' => 'brand_partner_callback',
                'source_id' => (string) $partner->id,
                'source_event' => 'callback_confirmation',
                'triggered_user_id' => $inquiry->user_id,
                'payload' => [
                    'brand_partner_id' => (string) $partner->id,
                    'brand_partner_name' => $brandName,
                    'inquiry_id' => (string) $inquiry->id,
                ],
            ]);
        }

        return $partnerSent || $requesterSent;
    }

    /**
     * Send In-App + FCM Push Notification to the Brand Partner owner when a member submits a Callback Request.
     *
     * @return array<string, mixed>
     */
    public function sendCallbackRequestPushAndInAppNotification(
        BrandPartner $partner,
        BrandPartnerInquiry $inquiry,
        int $totalCallbackRequests = 1
    ): array {
        $resolved = $this->resolveBrandPartnerRecipient($partner);
        $ownerUser = $resolved['user'];

        $brandName = trim((string) ($partner->name ?: 'Brand Partner'));
        $shortMessage = Str::limit(trim((string) $inquiry->message), 90);
        $title = "📞 New Callback Request: {$inquiry->name}";
        $body = "{$inquiry->name} ({$inquiry->phone}) requested a callback for {$brandName}: \"{$shortMessage}\"";

        $payload = [
            'type' => 'brand_partner_callback_request',
            'notification_type' => 'brand_partner_callback_request',
            'action' => 'open_brand_partner_callback',
            'screen' => 'advertiser_dashboard',
            'navigation_screen' => '/advertiser/dashboard',
            'route' => '/advertiser/dashboard',
            'title' => $title,
            'body' => $body,
            'message' => $body,
            'brand_partner_id' => (string) $partner->id,
            'brand_partner_name' => $brandName,
            'inquiry_id' => (string) $inquiry->id,
            'from_user_id' => $inquiry->user_id ? (string) $inquiry->user_id : null,
            'to_user_id' => $ownerUser ? (string) $ownerUser->id : null,
            'requester' => [
                'user_id' => $inquiry->user_id ? (string) $inquiry->user_id : null,
                'name' => $inquiry->name,
                'phone' => $inquiry->phone,
                'email' => $inquiry->email,
                'message' => $inquiry->message,
            ],
            'requester_name' => $inquiry->name,
            'requester_phone' => $inquiry->phone,
            'requester_email' => $inquiry->email,
            'requirement_message' => $inquiry->message,
            'callback_requests' => $totalCallbackRequests,
            'callback_requests_count' => $totalCallbackRequests,
            'notifiable_type' => BrandPartnerInquiry::class,
            'notifiable_id' => (string) $inquiry->id,
            'created_at' => ($inquiry->created_at ?? now())->toIso8601String(),
        ];

        if (! $ownerUser) {
            return $payload;
        }

        try {
            if (Schema::hasTable('notifications')) {
                $notificationData = [
                    'user_id' => (string) $ownerUser->id,
                    'type' => 'brand_partner_callback_request',
                    'payload' => $payload,
                    'is_read' => false,
                    'created_at' => now(),
                    'read_at' => null,
                ];
                if (Schema::hasColumn('notifications', 'title')) {
                    $notificationData['title'] = $title;
                }
                if (Schema::hasColumn('notifications', 'message')) {
                    $notificationData['message'] = $body;
                }
                if (Schema::hasColumn('notifications', 'source_type')) {
                    $notificationData['source_type'] = 'brand_partner_callback';
                }
                if (Schema::hasColumn('notifications', 'source_id')) {
                    $notificationData['source_id'] = (string) $inquiry->id;
                }
                if (Schema::hasColumn('notifications', 'source_event')) {
                    $notificationData['source_event'] = 'callback_requested';
                }

                $notification = Notification::create($notificationData);
                $payload['notification_id'] = (string) $notification->id;

                try {
                    event(new UserNotificationCreated((string) $ownerUser->id, [
                        'id' => (string) $notification->id,
                        'type' => 'brand_partner_callback_request',
                        'title' => $title,
                        'message' => $body,
                        'payload' => $payload,
                        'is_read' => false,
                        'created_at' => now()->toIso8601String(),
                    ]));
                } catch (Throwable) {
                }
            }

            if (Schema::hasTable('app_notifications')) {
                AppNotification::create([
                    'user_id' => (string) $ownerUser->id,
                    'type' => 'brand_partner_callback_request',
                    'category' => 'brand_partner_callback_request',
                    'title' => $title,
                    'body' => $body,
                    'message' => $body,
                    'channel' => 'push',
                    'priority' => 'high',
                    'reference_type' => BrandPartnerInquiry::class,
                    'reference_id' => (string) $inquiry->id,
                    'screen' => '/advertiser/dashboard',
                    'data' => $payload,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            SendPushNotificationJob::dispatch($ownerUser, $title, $body, $payload);
        } catch (Throwable $e) {
            Log::warning('[AdvertiserNotification] Failed dispatching callback push/in-app notification: '.$e->getMessage());
        }

        return $payload;
    }

    /**
     * Send weekly Brand Partner performance & callback leads summary email to published Brand Partners.
     *
     * @param  array<string, mixed>  $weeklyStats
     * @param  array<string, mixed>  $lifetimeStats
     */
    public function sendWeeklyBrandPartnerReport(
        BrandPartner $partner,
        array $weeklyStats,
        array $lifetimeStats,
        ?string $recipientEmail = null,
        ?string $recipientName = null
    ): bool {
        $resolved = $this->resolveBrandPartnerRecipient($partner);
        $targetEmail = (! empty($recipientEmail) && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL))
            ? trim($recipientEmail)
            : $resolved['email'];

        if (empty($targetEmail) || ! filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $brandName = trim((string) ($partner->name ?: 'Brand Partner'));
        $targetName = $recipientName ?: ($resolved['name'] ?: "{$brandName} Team");
        $subjectLine = "📊 Weekly Brand Partner Performance Report: {$brandName} — Peers Global Unity";
        $heading = 'Your Weekly Brand Partner Performance Summary';

        $periodFrom = (string) ($weeklyStats['from'] ?? now()->subDays(7)->toFormattedDateString());
        $periodTo = (string) ($weeklyStats['to'] ?? now()->toFormattedDateString());

        $mainMessage = "Here is your weekly performance and engagement summary for {$brandName} on Peers Global Unity ({$periodFrom} – {$periodTo}).\n\n"
            .'Thank you for being a valued Brand Partner in the Peers Global ecosystem!';

        $details = [
            'Brand Partner' => $brandName,
            'Report Period' => "{$periodFrom} – {$periodTo}",
            'Views This Week (Impressions)' => (string) ((int) ($weeklyStats['total_views'] ?? 0)),
            'Unique Peers Reached (This Week)' => (string) ((int) ($weeklyStats['unique_reach'] ?? 0)),
            'Total Clicks (This Week)' => (string) ((int) ($weeklyStats['total_clicks'] ?? 0)),
            'Click-Through Rate (CTR)' => number_format((float) ($weeklyStats['ctr_percent'] ?? 0), 2).'%',
            'New Callback Requests (This Week)' => (string) ((int) ($weeklyStats['callback_requests'] ?? 0)),
            'Lifetime Total Views' => (string) ((int) ($lifetimeStats['total_views'] ?? 0)),
            'Lifetime Total Clicks' => (string) ((int) ($lifetimeStats['total_clicks'] ?? 0)),
            'Lifetime Callback Requests' => (string) ((int) ($lifetimeStats['callback_requests'] ?? 0)),
            'Active Until' => $partner->valid_to ? $partner->valid_to->toFormattedDateString() : 'Active',
        ];

        $mailable = new AdvertiserNotificationMail(
            subjectLine: $subjectLine,
            recipientName: $targetName,
            heading: $heading,
            mainMessage: $mainMessage,
            details: $details
        );

        return $this->sendMailableWithLog($mailable, $targetEmail, [
            'user_id' => $resolved['user']?->id,
            'to_email' => $targetEmail,
            'to_name' => $targetName,
            'template_key' => 'brand_partner_weekly_report',
            'subject' => $subjectLine,
            'source_module' => 'Brand Partners',
            'related_type' => BrandPartner::class,
            'related_id' => (string) $partner->id,
            'source_type' => 'brand_partner_weekly_report',
            'source_id' => (string) $partner->id,
            'source_event' => 'weekly_report',
            'payload' => [
                'brand_partner_id' => (string) $partner->id,
                'brand_partner_name' => $brandName,
                'weekly_stats' => $weeklyStats,
                'lifetime_stats' => $lifetimeStats,
            ],
        ]);
    }

    /**
     * Safe wrapper for sending WhatsApp without breaking request execution.
     */
    private function sendWhatsAppSafe(string $templateKey, string $phone, array $payload, ?string $userId, ?string $eventKey): void
    {
        try {
            $this->whatsAppMessageService->send(
                templateKey: $templateKey,
                phone: $phone,
                payload: $payload,
                userId: $userId,
                eventKey: $eventKey
            );
        } catch (Throwable $e) {
            Log::warning("[AdvertiserNotification] WhatsApp dispatch suppressed: {$templateKey} - ".$e->getMessage());
        }
    }
}
