<?php

namespace App\Jobs;

use App\Events\UserNotificationCreated;
use App\Mail\AdminCampaignMailable;
use App\Models\AdminCampaign;
use App\Models\CampaignDelivery;
use App\Models\CampaignLog;
use App\Models\Notification;
use App\Models\User;
use App\Services\EmailLogs\EmailLogService;
use App\Services\Firebase\FcmService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SendCampaignRecipientJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [10, 30, 60];

    private ?string $notificationType = null;

    public function __construct(
        protected string $deliveryId,
        protected string $logId,
        protected string $userId
    ) {}

    public function handle(EmailLogService $emailLogService, FcmService $fcmService): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $log = CampaignLog::find($this->logId);
        $user = User::find($this->userId);
        $delivery = CampaignDelivery::with('campaign')->find($this->deliveryId);

        if (! $log || ! $user || ! $delivery) {
            return;
        }

        $campaign = $delivery->campaign;

        Log::info('SendCampaignRecipientJob handle started', [
            'delivery_id' => $this->deliveryId,
            'log_id' => $this->logId,
            'user_id' => $this->userId,
            'campaign_id' => $campaign->id,
        ]);

        $emailSent = false;
        $notificationSent = false;
        $emailStatus = $campaign->includesEmail() ? 'pending' : 'skipped';
        $notificationStatus = $campaign->includesNotification() ? 'pending' : 'skipped';
        $errors = [];

        // 1. Send Email if required
        if ($campaign->includesEmail()) {
            $email = trim((string) $user->email);
            if ($email === '') {
                $emailStatus = 'skipped';
            } else {
                try {
                    $mailable = new AdminCampaignMailable($campaign, $user);
                    Mail::to($email)->send($mailable);
                    $emailSent = true;
                    $emailStatus = 'sent';

                    // Log to EmailLogService
                    $emailLogService->logMailableSent($mailable, $this->emailLogData($campaign, $user, $email));
                    Log::info('SendCampaignRecipientJob email sent successfully', [
                        'user_id' => $this->userId,
                        'email' => $email,
                        'campaign_id' => $campaign->id,
                    ]);
                } catch (Throwable $e) {
                    $emailStatus = 'failed';
                    $errors[] = 'Email Error: '.$e->getMessage();
                    Log::error('SendCampaignRecipientJob email failed', [
                        'user_id' => $this->userId,
                        'email' => $email,
                        'campaign_id' => $campaign->id,
                        'error' => $e->getMessage(),
                    ]);

                    try {
                        $emailLogService->logMailableFailed(new AdminCampaignMailable($campaign, $user), $this->emailLogData($campaign, $user, $email), $e);
                    } catch (Throwable $ignore) {
                    }
                }
            }
        }

        // 2. Send Notification if required
        if ($campaign->includesNotification()) {
            try {
                $notification = $this->findExistingCampaignNotification($campaign, $user)
                    ?? Notification::create($this->notificationRow($campaign, $user));

                if ($notification->wasRecentlyCreated) {
                    try {
                        event(new UserNotificationCreated((string) $user->id, [
                            'id' => (string) $notification->id,
                            'title' => $this->notificationTitle($campaign),
                            'body' => $this->notificationMessage($campaign),
                            'type' => 'admin_campaign',
                            'payload' => $notification->payload ?? [],
                            'is_read' => false,
                            'created_at' => $notification->created_at,
                        ]));
                    } catch (Throwable $ignore) {
                    }
                }

                // Call FCM service synchronously
                $tokens = $user->pushTokens()->get();

                if ($tokens->isEmpty()) {
                    $notificationStatus = 'failed';
                    $errors[] = 'Push Error: No device token found';

                    Log::error('Notification delivery failed: No device token found', [
                        'campaign_id' => $campaign->id,
                        'user_id' => $user->id,
                        'device_token' => null,
                        'provider_response' => null,
                        'reason' => 'No device token found',
                    ]);
                } else {
                    $title = $this->notificationTitle($campaign);
                    $message = $this->notificationMessage($campaign);
                    $pamphletImageUrl = $this->pamphletImageUrl($campaign);

                    $pushData = [
                        'type' => 'admin_campaign',
                        'notification_type' => 'admin_campaign',
                        'notification_id' => (string) $notification->id,
                        'campaign_id' => (string) $campaign->id,
                        'campaign_title' => (string) $campaign->title,
                        'pamphlet_id' => $campaign->pamphlet_id ? (string) $campaign->pamphlet_id : null,
                        'pamphlet_image_url' => $pamphletImageUrl,
                    ];

                    $successCount = 0;
                    $tokenErrors = [];

                    foreach ($tokens as $token) {
                        $context = [
                            'user_id' => (string) $user->id,
                            'device_id' => $token->device_id,
                            'platform' => $token->platform,
                            'device_type' => $token->platform,
                            'notification_type' => 'admin_campaign',
                        ];

                        $result = $fcmService->sendToDevice(
                            (string) $token->token,
                            $title,
                            $message,
                            $pushData,
                            null,
                            1,
                            $context,
                            $pamphletImageUrl
                        );

                        if ($result['success'] ?? false) {
                            $successCount++;
                            Log::info('Notification delivery succeeded', [
                                'campaign_id' => $campaign->id,
                                'user_id' => $user->id,
                                'device_token' => $token->token,
                                'provider_response' => $result['firebase_response'] ?? null,
                                'reason' => 'Success',
                            ]);
                        } else {
                            $tokenErrors[] = $result['error'] ?? 'Unknown provider error';
                            Log::error('Notification delivery failed', [
                                'campaign_id' => $campaign->id,
                                'user_id' => $user->id,
                                'device_token' => $token->token,
                                'provider_response' => $result['firebase_response'] ?? null,
                                'reason' => $result['error'] ?? 'Unknown provider error',
                            ]);
                        }
                    }

                    if ($successCount > 0) {
                        $notificationSent = true;
                        $notificationStatus = 'sent';
                    } else {
                        $notificationStatus = 'failed';
                        $errors[] = 'Push Error: '.implode(' | ', $tokenErrors);
                    }
                }
            } catch (Throwable $e) {
                $notificationStatus = 'failed';
                $errors[] = 'Push Error: '.$e->getMessage();
                Log::error('Notification delivery exception', [
                    'campaign_id' => $campaign->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 3. Atomically Update Delivery Stats in Database
        $incData = [];
        if ($emailSent) {
            $incData['total_email_sent'] = DB::raw('total_email_sent + 1');
        }
        if ($notificationSent) {
            $incData['total_notification_sent'] = DB::raw('total_notification_sent + 1');
        }
        if (! empty($errors)) {
            $incData['total_failed'] = DB::raw('total_failed + 1');
        }

        if (! empty($incData)) {
            CampaignDelivery::where('id', $this->deliveryId)->update($incData);
        }

        // 4. Update Recipient Log Record
        $log->update([
            'email_status' => $emailStatus,
            'notification_status' => $notificationStatus,
            'email_sent' => $emailSent,
            'notification_sent' => $notificationSent,
            'error_message' => empty($errors) ? null : Str::limit(implode(' | ', $errors), 2000),
            'sent_at' => now(),
        ]);

        Log::info('SendCampaignRecipientJob handle finished', [
            'delivery_id' => $this->deliveryId,
            'log_id' => $this->logId,
            'user_id' => $this->userId,
            'email_status' => $emailStatus,
            'notification_status' => $notificationStatus,
            'errors' => $errors,
        ]);
    }

    protected function emailLogData(AdminCampaign $campaign, User $user, string $email): array
    {
        return [
            'user_id' => $user->id,
            'to_email' => $email,
            'to_name' => $user->adminDisplayName(),
            'template_key' => 'admin_campaign',
            'subject' => $campaign->subject,
            'source_module' => 'admin_campaigns',
            'related_type' => AdminCampaign::class,
            'related_id' => $campaign->id,
            'source_type' => 'admin_campaign',
            'source_id' => $campaign->id,
            'source_event' => 'campaign_send',
            'payload' => ['campaign_id' => $campaign->id, 'campaign_title' => $campaign->title],
        ];
    }

    protected function resolveNotificationType(): string
    {
        if ($this->notificationType !== null) {
            return $this->notificationType;
        }

        try {
            $allowedTypes = DB::table('pg_enum')
                ->join('pg_type', 'pg_type.oid', '=', 'pg_enum.enumtypid')
                ->where('pg_type.typname', 'notification_type_enum')
                ->pluck('pg_enum.enumlabel')
                ->map(fn ($type) => (string) $type)
                ->all();

            foreach (['general', 'activity_update', 'system'] as $type) {
                if (in_array($type, $allowedTypes, true)) {
                    return $this->notificationType = $type;
                }
            }
        } catch (Throwable) {
        }

        return $this->notificationType = 'system';
    }

    protected function findExistingCampaignNotification(AdminCampaign $campaign, User $user): ?Notification
    {
        $query = Notification::where('user_id', $user->id);

        if (Schema::hasColumn('notifications', 'source_type') && Schema::hasColumn('notifications', 'source_id') && Schema::hasColumn('notifications', 'source_event')) {
            $sourceMatch = (clone $query)
                ->where('source_type', 'admin_campaign')
                ->where('source_id', $campaign->id)
                ->where('source_event', 'campaign_send')
                ->first();

            if ($sourceMatch) {
                return $sourceMatch;
            }
        }

        return $query
            ->where('payload->notification_type', 'admin_campaign')
            ->where('payload->campaign_id', (string) $campaign->id)
            ->first();
    }

    protected function dispatchNotificationDelivery(AdminCampaign $campaign, User $user, Notification $notification): void
    {
        $title = (string) ($campaign->notification_title ?: $campaign->title ?: 'New notification');
        $message = (string) ($campaign->notification_message ?: 'You have a new notification.');
        $payload = $notification->payload ?? [];

        $pamphletImageUrl = null;
        $imageUrl = $campaign->pamphlet_snapshot['image_url'] ?? null;
        if (is_string($imageUrl) && $imageUrl !== '') {
            $pamphletImageUrl = $imageUrl;
        }

        $pushData = [
            'type' => 'admin_campaign',
            'notification_type' => 'admin_campaign',
            'notification_id' => (string) $notification->id,
            'campaign_id' => (string) $campaign->id,
            'campaign_title' => (string) $campaign->title,
            'pamphlet_id' => $campaign->pamphlet_id ? (string) $campaign->pamphlet_id : null,
            'pamphlet_image_url' => $pamphletImageUrl,
        ];

        try {
            event(new UserNotificationCreated((string) $user->id, [
                'id' => (string) $notification->id,
                'title' => $title,
                'body' => $message,
                'type' => 'admin_campaign',
                'payload' => $payload,
                'is_read' => false,
                'created_at' => $notification->created_at,
            ]));

            SendPushNotificationJob::dispatch($user, $title, $message, $pushData);
        } catch (Throwable $exception) {
            Log::error('Admin campaign notification delivery dispatch failed', [
                'campaign_id' => (string) $campaign->id,
                'user_id' => (string) $user->id,
                'notification_id' => (string) $notification->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function notificationRow(AdminCampaign $campaign, User $user): array
    {
        $payload = $this->notificationPayload($campaign, $user);
        $row = [
            'user_id' => $user->id,
            'type' => $this->resolveNotificationType(),
            'payload' => $payload,
            'is_read' => false,
            'created_at' => now(),
            'read_at' => null,
        ];

        foreach ([
            'title' => (string) ($campaign->notification_title ?: $campaign->title ?: 'New notification'),
            'message' => (string) ($campaign->notification_message ?: 'You have a new notification.'),
            'data' => $payload,
            'source_type' => 'admin_campaign',
            'source_id' => (string) $campaign->id,
            'source_event' => 'campaign_send',
        ] as $column => $value) {
            if (Schema::hasColumn('notifications', $column)) {
                $row[$column] = $value;
            }
        }

        return $row;
    }

    protected function notificationPayload(AdminCampaign $campaign, User $user): array
    {
        $pamphletImageUrl = null;
        $imageUrl = $campaign->pamphlet_snapshot['image_url'] ?? null;
        if (is_string($imageUrl) && $imageUrl !== '') {
            $pamphletImageUrl = $imageUrl;
        }

        $campaignData = [
            'notification_type' => 'admin_campaign',
            'campaign_id' => (string) $campaign->id,
            'campaign_title' => (string) $campaign->title,
            'pamphlet_id' => $campaign->pamphlet_id ? (string) $campaign->pamphlet_id : null,
            'pamphlet_image_url' => $pamphletImageUrl,
        ];

        return [
            ...$campaignData,
            'title' => (string) ($campaign->notification_title ?: $campaign->title ?: 'New notification'),
            'body' => (string) ($campaign->notification_message ?: 'You have a new notification.'),
            'to_user_id' => (string) $user->id,
            'data' => $campaignData,
            'notifiable_type' => AdminCampaign::class,
            'notifiable_id' => (string) $campaign->id,
        ];
    }

    protected function notificationTitle(AdminCampaign $campaign): string
    {
        return (string) ($campaign->notification_title ?: $campaign->title ?: 'New notification');
    }

    protected function notificationMessage(AdminCampaign $campaign): string
    {
        return (string) ($campaign->notification_message ?: 'You have a new notification.');
    }

    protected function pamphletImageUrl(AdminCampaign $campaign): ?string
    {
        $imageUrl = $campaign->pamphlet_snapshot['image_url'] ?? null;

        return is_string($imageUrl) && $imageUrl !== '' ? $imageUrl : null;
    }
}
