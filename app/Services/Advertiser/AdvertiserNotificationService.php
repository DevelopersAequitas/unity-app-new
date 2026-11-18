<?php

declare(strict_types=1);

namespace App\Services\Advertiser;

use App\Mail\AdvertiserNotificationMail;
use App\Models\AdvertiserApplication;
use App\Models\AdvertiserSubscription;
use App\Models\BrandPartner;
use App\Models\BrandPartnerInquiry;
use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AdvertiserNotificationService
{
    public function __construct(
        protected WhatsAppMessageService $whatsAppMessageService
    ) {}

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
     * Send instant callback request notification email to the Brand Partner's contact_email
     * with Reply-To set to the requesting member's email.
     */
    public function sendCallbackRequestNotification(
        BrandPartner $partner,
        BrandPartnerInquiry $inquiry,
        string $recipientEmail
    ): bool {
        if (empty($recipientEmail) || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $brandName = trim((string) ($partner->name ?: 'Brand Partner'));
        $subjectLine = "📞 New Callback Request: {$inquiry->name} on Peers Global Unity";
        $heading = 'New Callback Request from a Peers Global Member';
        $mainMessage = "A member on the Peers Global Unity app has requested a callback.\n\n"
            ."Requirement / Message:\n\"{$inquiry->message}\"\n\n"
            ."You can reply directly to this email or call the member at {$inquiry->phone}.";

        $details = [
            'Member Name' => $inquiry->name,
            'Phone' => $inquiry->phone,
            'Email' => $inquiry->email,
            'Brand Partner' => $brandName,
            'Requested At' => ($inquiry->created_at ?? now())->toDayDateTimeString(),
        ];

        try {
            Mail::to($recipientEmail)->send(
                new AdvertiserNotificationMail(
                    subjectLine: $subjectLine,
                    recipientName: "{$brandName} Team",
                    heading: $heading,
                    mainMessage: $mainMessage,
                    actionUrl: 'mailto:'.$inquiry->email,
                    actionText: 'Reply to '.$inquiry->name,
                    details: $details,
                    replyToEmail: $inquiry->email,
                    replyToName: $inquiry->name
                )
            );

            return true;
        } catch (Throwable $e) {
            Log::error('[AdvertiserNotification] Failed sending callback request email: '.$e->getMessage(), [
                'brand_partner_id' => $partner->id,
                'inquiry_id' => $inquiry->id,
                'recipient_email' => $recipientEmail,
            ]);

            return false;
        }
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
        string $recipientEmail,
        ?string $recipientName = null
    ): bool {
        if (empty($recipientEmail) || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $brandName = trim((string) ($partner->name ?: 'Brand Partner'));
        $targetName = $recipientName ?: "{$brandName} Team";
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

        try {
            Mail::to($recipientEmail)->send(
                new AdvertiserNotificationMail(
                    subjectLine: $subjectLine,
                    recipientName: $targetName,
                    heading: $heading,
                    mainMessage: $mainMessage,
                    details: $details
                )
            );

            return true;
        } catch (Throwable $e) {
            Log::error('[AdvertiserNotification] Failed sending weekly brand partner report email: '.$e->getMessage(), [
                'brand_partner_id' => $partner->id,
                'recipient_email' => $recipientEmail,
            ]);

            return false;
        }
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
