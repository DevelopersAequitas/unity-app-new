<?php

namespace App\Services\Circulars;

use App\Jobs\SendPushNotificationJob;
use App\Leader\Services\LeaderNotificationDispatcherService;
use App\Leader\Services\LeaderNotificationPolicyService;
use App\Models\Circular;
use App\Models\Notification;
use App\Models\Notifications\AppNotification;
use App\Models\User;
use App\Shared\Services\UserRoleResolverService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CircularNotificationService
{
    public function send(Circular $circular): void
    {
        if (! $circular->send_push_notification) {
            return;
        }

        if ($circular->notification_sent_at) {
            return;
        }

        $targetRoles = is_array($circular->target_roles) ? $circular->target_roles : [];
        $audienceType = strtolower(trim((string) ($circular->audience_type ?? '')));
        $leadershipUserIds = $this->resolveScopedLeadershipRecipients($circular, $targetRoles, $audienceType);

        if (! empty($leadershipUserIds)) {
            $dispatcher = app(LeaderNotificationDispatcherService::class);
            foreach ($leadershipUserIds as $leaderId) {
                $dispatcher->dispatchToUser(
                    $leaderId,
                    'official_circle_circular',
                    (string) $circular->title,
                    (string) ($circular->summary ?? 'New official circular published'),
                    '/circulars',
                    [
                        'event_key' => 'official_circle_circular',
                        'catalogue_id' => 'L-09',
                        'entity_type' => 'circular',
                        'entity_id' => (string) $circular->id,
                        'reference_type' => Circular::class,
                        'reference_id' => (string) $circular->id,
                        'circular_id' => (string) $circular->id,
                        'circle_id' => $circular->circle_id ? (string) $circular->circle_id : null,
                        'priority' => (string) ($circular->priority ?? 'medium'),
                        'delivery_tier' => ($circular->priority ?? '') === 'urgent' ? 'critical' : 'informational',
                        'idempotency_window' => (string) $circular->id,
                    ],
                    'governance',
                    (string) ($circular->priority ?? 'medium')
                );
            }
        }

        $leaderLookup = array_flip($leadershipUserIds);

        $users = User::query()
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->get(['id']);

        foreach ($users as $user) {
            if (isset($leaderLookup[(string) $user->id])) {
                continue;
            }
            try {
                $notification = Notification::create([
                    'user_id' => $user->id,
                    'type' => 'activity_update',
                    'payload' => [
                        'audience' => 'member',
                        'notification_type' => 'circular',
                        'title' => $circular->title,
                        'body' => $circular->summary ?? 'New circular available',
                        'circular_id' => (string) $circular->id,
                    ],
                    'is_read' => false,
                    'created_at' => now(),
                    'read_at' => null,
                ]);

                try {
                    AppNotification::create([
                        'user_id' => $user->id,
                        'type' => 'circular',
                        'category' => 'circular',
                        'title' => (string) $circular->title,
                        'body' => (string) ($circular->summary ?? 'New circular available'),
                        'message' => (string) ($circular->summary ?? 'New circular available'),
                        'channel' => 'push',
                        'priority' => 'medium',
                        'reference_type' => Circular::class,
                        'reference_id' => (string) $circular->id,
                        'screen' => 'circulars',
                        'data' => [
                            'audience' => 'member',
                            'notification_id' => (string) $notification->id,
                            'circular_id' => (string) $circular->id,
                            'type' => 'circular',
                        ],
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Failed to create AppNotification in CircularNotificationService', [
                        'error' => $e->getMessage(),
                    ]);
                }

                SendPushNotificationJob::dispatch(
                    $user,
                    (string) $circular->title,
                    (string) ($circular->summary ?? 'New circular available'),
                    [
                        'type' => 'circular',
                        'notification_id' => (string) $notification->id,
                        'circular_id' => (string) $circular->id,
                    ]
                );
            } catch (\Throwable $exception) {
                Log::warning('Failed sending circular notification', [
                    'circular_id' => (string) $circular->id,
                    'user_id' => (string) $user->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        DB::table('circulars')
            ->where('id', $circular->id)
            ->update(['notification_sent_at' => DB::raw('NOW()')]);
    }

    /**
     * Resolve scoped Leadership App recipients for a circular without broadcasting to unrelated leaders.
     *
     * @param  array<int, string>  $targetRoles
     * @return array<int, string>
     */
    private function resolveScopedLeadershipRecipients(Circular $circular, array $targetRoles, string $audienceType): array
    {
        $roleResolver = app(UserRoleResolverService::class);
        $policy = app(LeaderNotificationPolicyService::class);
        $ids = [];

        $normalizedTargets = array_values(array_unique(array_filter(array_map(
            static fn ($r): string => $roleResolver->normalizeRoleKey((string) $r),
            $targetRoles
        ))));

        $circleId = (string) ($circular->circle_id ?? '');

        // 1. Explicit target_roles on circular
        if (! empty($normalizedTargets)) {
            if ($circleId !== '') {
                $ids = array_merge($ids, $policy->resolveCircleLeaderUserIds($circleId, $normalizedTargets));
            }
            if (in_array('superAdmin', $normalizedTargets, true)) {
                $ids = array_merge($ids, $policy->resolveSuperAdminUserIds());
            }
            if (in_array('globalFounder', $normalizedTargets, true)) {
                $ids = array_merge($ids, $policy->resolveGlobalFounderUserIds());
            }
            if ($circleId === '') {
                User::query()->whereNull('deleted_at')->where('status', 'active')->orderBy('id')->chunkById(500, function ($users) use ($roleResolver, $normalizedTargets, &$ids): void {
                    foreach ($users as $u) {
                        $resolved = $roleResolver->resolveUserRole($u);
                        if (in_array((string) ($resolved['role'] ?? ''), $normalizedTargets, true)) {
                            $ids[] = (string) $u->id;
                        }
                    }
                });
            }

            return array_values(array_unique(array_filter($ids)));
        }

        // 2. Circle-scoped circular (no target_roles): notify only leaders of that circle
        if ($circleId !== '') {
            return $policy->resolveCircleLeaderUserIds($circleId, [
                'circleDirector', 'circleFounder', 'circleChair',
                'chairBusinessGrowth', 'chairMembership', 'chairEventsPrograms',
                'viceChair', 'secretary', 'committeeLeader',
            ]);
        }

        // 3. Explicit leadership-wide audience
        if (in_array($audienceType, ['all_leaders', 'leadership'], true)) {
            User::query()->whereNull('deleted_at')->where('status', 'active')->orderBy('id')->chunkById(500, function ($users) use ($roleResolver, &$ids): void {
                foreach ($users as $u) {
                    if ($roleResolver->isLeader($u)) {
                        $ids[] = (string) $u->id;
                    }
                }
            });

            return array_values(array_unique(array_filter($ids)));
        }

        // Default: Super Admin & Global Founder governance awareness only for non-targeted circulars
        return array_values(array_unique(array_merge(
            $policy->resolveSuperAdminUserIds(),
            $policy->resolveGlobalFounderUserIds()
        )));
    }
}
