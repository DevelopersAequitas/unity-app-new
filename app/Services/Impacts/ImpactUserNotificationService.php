<?php

declare(strict_types=1);

namespace App\Services\Impacts;

use App\Jobs\SendFcmNotificationJob;
use App\Models\Impact;
use App\Models\Notification;
use App\Models\Notifications\AppNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ImpactUserNotificationService
{
    public function sendSubmitted(Impact $impact): Notification
    {
        $impact->loadMissing(['user', 'impactedPeer']);

        $submitterPayload = [
            'notification_type' => 'impact_submitted',
            'title' => 'Impact Submitted',
            'body' => 'Your Impact has been submitted successfully and is awaiting review.',
            'impact_id' => (string) $impact->id,
            'status' => (string) $impact->status,
            'screen' => '/life-impact',
            'navigation_screen' => '/life-impact',
            'tap_destination' => '/life-impact',
        ];

        $submitterNotification = $this->storeAndDispatchToUser(
            (string) $impact->user_id,
            $impact,
            'impact_submitted',
            $submitterPayload
        );

        if ($impact->impacted_peer_id && (string) $impact->impacted_peer_id !== (string) $impact->user_id) {
            $submitterName = $this->resolveUserName($impact->user);

            $peerPayload = [
                'notification_type' => 'impact_received',
                'title' => 'Impact Received',
                'body' => "{$submitterName} submitted a life impact for you.",
                'impact_id' => (string) $impact->id,
                'from_user_id' => (string) $impact->user_id,
                'status' => (string) $impact->status,
                'screen' => '/life-impact',
                'navigation_screen' => '/life-impact',
                'tap_destination' => '/life-impact',
            ];

            $this->storeAndDispatchToUser(
                (string) $impact->impacted_peer_id,
                $impact,
                'impact_received',
                $peerPayload
            );
        }

        return $submitterNotification;
    }

    public function sendApproved(Impact $impact): Notification
    {
        $impact->loadMissing(['user', 'impactedPeer']);

        $submitterPayload = [
            'notification_type' => 'impact_approved',
            'title' => 'Impact Approved',
            'body' => 'Your Impact has been approved successfully.',
            'impact_id' => (string) $impact->id,
            'status' => (string) $impact->status,
            'life_impacted' => (int) ($impact->life_impacted ?? 1),
            'screen' => '/life-impact',
            'navigation_screen' => '/life-impact',
            'tap_destination' => '/life-impact',
        ];

        $submitterNotification = $this->storeAndDispatchToUser(
            (string) $impact->user_id,
            $impact,
            'impact_approved',
            $submitterPayload
        );

        if ($impact->impacted_peer_id && (string) $impact->impacted_peer_id !== (string) $impact->user_id) {
            $submitterName = $this->resolveUserName($impact->user);

            $peerPayload = [
                'notification_type' => 'impact_approved',
                'title' => 'Impact Approved',
                'body' => "Life impact from {$submitterName} has been approved.",
                'impact_id' => (string) $impact->id,
                'from_user_id' => (string) $impact->user_id,
                'status' => (string) $impact->status,
                'life_impacted' => (int) ($impact->life_impacted ?? 1),
                'screen' => '/life-impact',
                'navigation_screen' => '/life-impact',
                'tap_destination' => '/life-impact',
            ];

            $this->storeAndDispatchToUser(
                (string) $impact->impacted_peer_id,
                $impact,
                'impact_approved',
                $peerPayload
            );
        }

        return $submitterNotification;
    }

    public function sendRejected(Impact $impact, ?string $reviewRemarks = null): Notification
    {
        $impact->loadMissing(['user', 'impactedPeer']);

        $payload = [
            'notification_type' => 'impact_rejected',
            'title' => 'Impact Rejected',
            'body' => 'Your impact was reviewed and rejected.',
            'impact_id' => (string) $impact->id,
            'status' => (string) $impact->status,
            'review_remarks' => $reviewRemarks,
            'screen' => '/life-impact',
            'navigation_screen' => '/life-impact',
            'tap_destination' => '/life-impact',
        ];

        return $this->storeAndDispatchToUser(
            (string) $impact->user_id,
            $impact,
            'impact_rejected',
            $payload
        );
    }

    private function storeAndDispatchToUser(string $userId, Impact $impact, string $type, array $payload): Notification
    {
        Log::info('impact.notification.store', [
            'impact_id' => (string) $impact->id,
            'user_id' => $userId,
            'type' => $type,
        ]);

        $notification = Notification::create([
            'user_id' => $userId,
            'type' => 'activity_update',
            'payload' => $payload,
            'is_read' => false,
            'created_at' => now(),
            'read_at' => null,
        ]);

        try {
            $notificationData = [
                'user_id' => $userId,
                'type' => $type,
                'category' => 'life_impact',
                'title' => (string) ($payload['title'] ?? 'Notification'),
                'body' => (string) ($payload['body'] ?? 'You have a new notification'),
                'message' => (string) ($payload['body'] ?? 'You have a new notification'),
                'channel' => 'push',
                'priority' => 'medium',
                'reference_type' => Impact::class,
                'reference_id' => (string) $impact->id,
                'screen' => '/life-impact',
                'data' => array_merge([
                    'screen' => '/life-impact',
                    'navigation_screen' => '/life-impact',
                    'tap_destination' => '/life-impact',
                ], $payload, [
                    'notification_id' => (string) $notification->id,
                ]),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('app_notifications', 'payload')) {
                $notificationData['payload'] = $notificationData['data'];
            }

            AppNotification::create($notificationData);
        } catch (\Throwable $e) {
            Log::error('Failed to create AppNotification in ImpactUserNotificationService', [
                'error' => $e->getMessage(),
            ]);
        }

        DB::afterCommit(function () use ($userId, $impact, $type, $payload): void {
            SendFcmNotificationJob::dispatch(
                $userId,
                (string) ($payload['title'] ?? 'Notification'),
                (string) ($payload['body'] ?? 'You have a new notification'),
                array_merge([
                    'notification_type' => $type,
                    'impact_id' => (string) $impact->id,
                    'screen' => '/life-impact',
                    'navigation_screen' => '/life-impact',
                ], $payload)
            );
        });

        return $notification;
    }

    private function resolveUserName(?object $user): string
    {
        if (! $user) {
            return 'A peer';
        }

        return trim((string) ($user->display_name ?? ''))
            ?: trim(((string) ($user->first_name ?? '')).' '.((string) ($user->last_name ?? '')))
            ?: 'A peer';
    }
}
