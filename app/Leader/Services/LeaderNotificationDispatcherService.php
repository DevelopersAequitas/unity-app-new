<?php

declare(strict_types=1);

namespace App\Leader\Services;

use App\Jobs\SendFcmNotificationJob;
use App\Models\Circle;
use App\Models\Notification;
use App\Models\Notifications\AppNotification;
use App\Models\User;
use App\Services\Notifications\FcmService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeaderNotificationDispatcherService
{
    public function __construct(
        private readonly ?LeaderNotificationPolicyService $policyService = null,
    ) {}

    private function policy(): LeaderNotificationPolicyService
    {
        return $this->policyService ?? app(LeaderNotificationPolicyService::class);
    }

    /**
     * Dispatch an event-driven notification to a specific user with explicit leadership audience,
     * sha256 idempotency, financial confidentiality, quiet hours, and after-commit FCM delivery.
     */
    public function dispatchToUser(
        string|int $userId,
        string $type,
        string $title,
        string $message,
        string $screen = '/dashboard',
        array $data = [],
        string $category = 'system',
        string $priority = 'medium'
    ): ?AppNotification {
        $userIdStr = (string) $userId;
        if ($userIdStr === '') {
            return null;
        }

        try {
            $policy = $this->policy();
            $user = Schema::hasTable('users') ? User::query()->find($userIdStr) : null;

            // Enforce financial confidentiality boundary
            if ($user && $policy->isFinancialSensitive($type, $category, $data) && ! $policy->canUserReceiveFinancialNotification($user)) {
                return null;
            }

            $isBroadcast = $type === 'admin_broadcast' || ! empty($data['sent_by_admin']);
            $deliveryTier = (string) ($data['delivery_tier'] ?? ($policy->isCriticalNotification($type, $priority) ? 'critical' : 'important'));
            $requiresAction = (bool) ($data['requires_action'] ?? false);

            $entityId = (string) ($data['entity_id'] ?? $data['reference_id'] ?? $title);
            $window = (string) ($data['idempotency_window'] ?? ($isBroadcast ? Str::random(10) : date('Y-m-d')));
            $dedupeKey = (string) ($data['idempotency_key'] ?? hash('sha256', $userIdStr.':'.$type.':'.$entityId.':'.$window));

            $rawRef = $data['reference_id'] ?? ($data['entity_id'] ?? null);
            $safeUuidRef = ($rawRef !== null && Str::isUuid((string) $rawRef)) ? (string) $rawRef : null;
            $notificationId = (string) Str::uuid();
            $entityType = (string) ($data['entity_type'] ?? ($data['reference_type'] ?? ''));
            $entityId = (string) ($data['entity_id'] ?? ($data['reference_id'] ?? ''));

            $enrichedData = array_merge($data, [
                'schema_version' => '2.0',
                'notification_id' => $notificationId,
                'notification_type' => $type,
                'audience' => 'leadership',
                'app' => 'leader',
                'app_name' => 'leader_app',
                'type' => $type,
                'event_key' => (string) ($data['event_key'] ?? $type),
                'category' => $category,
                'screen' => $screen,
                'destination' => (string) ($data['destination'] ?? $screen),
                'destination_tab' => $data['destination_tab'] ?? null,
                'destination_filter' => $data['destination_filter'] ?? null,
                'entity_type' => $entityType !== '' ? $entityType : null,
                'entity_id' => $entityId !== '' ? $entityId : null,
                'target_record_id' => $data['target_record_id'] ?? ($entityId !== '' ? $entityId : null),
                'target_list_id' => $data['target_list_id'] ?? ($data['circle_id'] ?? null),
                'target_status' => $data['target_status'] ?? ($requiresAction ? 'pending' : null),
                'action_identifier' => $data['action_identifier'] ?? ($requiresAction ? 'review' : null),
                'priority' => $priority,
                'delivery_tier' => $deliveryTier,
                'requires_action' => $requiresAction,
                'action_required' => $requiresAction,
                'title' => $title,
                'body' => $message,
            ]);

            // 1. Persist AppNotification entry (idempotent)
            $appNotification = null;
            if (Schema::hasTable('app_notifications')) {
                if (! $isBroadcast) {
                    $existing = AppNotification::query()
                        ->where('user_id', $userIdStr)
                        ->where('dedupe_key', $dedupeKey)
                        ->first();

                    if ($existing) {
                        return $existing;
                    }
                }

                $appNotification = AppNotification::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $userIdStr,
                    'type' => $type,
                    'category' => $category,
                    'title' => $title,
                    'body' => $message,
                    'message' => $message,
                    'channel' => 'app',
                    'priority' => $priority,
                    'reference_type' => $data['reference_type'] ?? ($data['entity_type'] ?? null),
                    'reference_id' => $safeUuidRef,
                    'screen' => $screen,
                    'data' => $enrichedData,
                    'dedupe_key' => $dedupeKey,
                    'status' => 'delivered',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 2. Also record in notifications table for universal inbox compatibility
            if (Schema::hasTable('notifications')) {
                try {
                    Notification::create([
                        'id' => (string) Str::uuid(),
                        'user_id' => $userIdStr,
                        'type' => 'activity_update',
                        'title' => $title,
                        'message' => $message,
                        'payload' => array_merge($enrichedData, [
                            'notification_type' => $type,
                        ]),
                        'data' => $enrichedData,
                        'source_type' => 'leader_event',
                        'source_id' => $safeUuidRef,
                        'source_event' => $type,
                        'is_read' => false,
                        'created_at' => now(),
                    ]);
                } catch (\Throwable $ne) {
                    Log::warning('Failed to insert into notifications table: '.$ne->getMessage());
                }
            }

            // 3. Respect quiet hours & lock-screen privacy for FCM push
            $skipPushForQuietHours = $policy->isWithinQuietHours($userIdStr, $type, $priority, $deliveryTier);
            if ($user && ! $skipPushForQuietHours) {
                $isSensitive = $policy->isFinancialSensitive($type, $category, $data) || $category === 'security';
                $pushTitle = $isSensitive ? 'Peers Global Leadership Alert' : $title;
                $pushBody = $isSensitive ? 'You have a confidential leadership update. Open the app to review.' : $message;

                $fcmData = array_merge($enrichedData, [
                    'title' => $pushTitle,
                    'body' => $pushBody,
                    'notification_id' => $appNotification ? (string) $appNotification->id : null,
                ]);

                $sendFcmCallback = static function () use ($user, $userIdStr, $pushTitle, $pushBody, $fcmData, $appNotification): void {
                    try {
                        if (class_exists(FcmService::class)) {
                            $result = app(FcmService::class)->sendToUser($user, $pushTitle, $pushBody, $fcmData, $appNotification);
                            if ($appNotification) {
                                $sent = (bool) ($result['success'] ?? false);
                                $appNotification->update([
                                    'sent_at' => $sent ? now() : $appNotification->sent_at,
                                    'failed_at' => $sent ? null : now(),
                                    'failure_reason' => $sent ? null : (string) ($result['error'] ?? 'FCM delivery failed'),
                                ]);
                            }
                        } elseif (class_exists(SendFcmNotificationJob::class)) {
                            SendFcmNotificationJob::dispatchSync($userIdStr, $pushTitle, $pushBody, $fcmData);
                            if ($appNotification) {
                                $appNotification->update(['sent_at' => now()]);
                            }
                        }
                    } catch (\Throwable $fe) {
                        Log::warning('FCM job dispatch failed: '.$fe->getMessage());
                        if ($appNotification) {
                            $appNotification->update([
                                'failed_at' => now(),
                                'failure_reason' => Str::limit($fe->getMessage(), 250),
                            ]);
                        }
                    }
                };

                if (DB::transactionLevel() > 0) {
                    DB::afterCommit($sendFcmCallback);
                } else {
                    $sendFcmCallback();
                }
            }

            return $appNotification;
        } catch (\Throwable $e) {
            Log::warning('Failed to dispatch leader notification: '.$e->getMessage(), [
                'user_id' => $userIdStr,
                'type' => $type,
            ]);

            return null;
        }
    }

    /**
     * Centralized policy-driven event dispatcher for any catalogued Leadership App event.
     *
     * @param  array<string, mixed>  $context
     * @return array<int, string> Notified user IDs
     */
    public function dispatchEvent(string $eventKey, string $title, string $message, array $context = []): array
    {
        $policy = $this->policy();
        if (empty($context['is_test']) && ! $policy->isFlowEnabled($eventKey)) {
            return [];
        }

        $catalogue = $policy->getEventCatalogue();
        $spec = $catalogue[$eventKey] ?? null;

        if (! $spec) {
            return [];
        }

        $circleId = (string) ($context['circle_id'] ?? '');
        $districtId = (string) ($context['district_id'] ?? '');
        $industryId = (string) ($context['industry_id'] ?? '');
        $countryId = (string) ($context['country_id'] ?? '');
        $countryName = (string) ($context['country_name'] ?? '');
        $actorId = (string) ($context['actor_id'] ?? '');

        if ($circleId !== '' && ($districtId === '' || $countryName === '') && Schema::hasTable('circles')) {
            $circle = Circle::query()->find($circleId);
            if ($circle) {
                $districtId = $districtId !== '' ? $districtId : (string) ($circle->district_id ?? '');
                $countryName = $countryName !== '' ? $countryName : (string) ($circle->country ?? '');
            }
        }

        $targetRoles = array_values(array_unique(array_merge(
            $spec['primary_roles'] ?? [],
            $spec['backup_roles'] ?? [],
            $spec['info_roles'] ?? []
        )));

        $recipientIds = [];

        if (in_array('superAdmin', $targetRoles, true)) {
            $recipientIds = array_merge($recipientIds, $policy->resolveSuperAdminUserIds());
        }

        if (! empty($spec['global_founder']) || in_array('globalFounder', $targetRoles, true)) {
            $recipientIds = array_merge($recipientIds, $policy->resolveGlobalFounderUserIds());
        }

        if (in_array('countryDirector', $targetRoles, true)) {
            $recipientIds = array_merge($recipientIds, $policy->resolveCountryDirectorUserIds($countryId, $countryName));
        }

        if (in_array('districtExecDirector', $targetRoles, true) && $districtId !== '') {
            $recipientIds = array_merge($recipientIds, $policy->resolveDistrictDirectorUserIds($districtId));
        }

        if (in_array('industryDirector', $targetRoles, true) && $industryId !== '') {
            $recipientIds = array_merge($recipientIds, $policy->resolveIndustryDirectorUserIds($industryId));
        }

        $circleRoles = array_values(array_intersect($targetRoles, [
            'circleFounder',
            'circleDirector',
            'circleChair',
            'chairBusinessGrowth',
            'chairMembership',
            'chairEventsPrograms',
            'viceChair',
            'secretary',
            'committeeLeader',
        ]));

        if (! empty($circleRoles) && $circleId !== '') {
            $recipientIds = array_merge($recipientIds, $policy->resolveCircleLeaderUserIds($circleId, $circleRoles));
        }

        if (! empty($context['explicit_recipient_ids']) && is_array($context['explicit_recipient_ids'])) {
            $recipientIds = array_merge($recipientIds, array_map('strval', $context['explicit_recipient_ids']));
        }

        $recipientIds = array_values(array_unique(array_filter($recipientIds, static fn (string $id): bool => $id !== '' && $id !== $actorId)));

        $payloadData = array_merge($context, [
            'event_key' => $eventKey,
            'catalogue_id' => $spec['id'],
            'delivery_tier' => $spec['delivery_tier'],
            'requires_action' => $spec['requires_action'],
            'financial_sensitive' => $spec['financial_sensitive'],
            'entity_type' => $context['entity_type'] ?? $spec['entity_type'],
            'entity_id' => $context['entity_id'] ?? ($context['reference_id'] ?? null),
            'org_scope' => array_filter([
                'circle_id' => $circleId ?: null,
                'district_id' => $districtId ?: null,
                'industry_id' => $industryId ?: null,
                'country_id' => $countryId ?: null,
                'country' => $countryName ?: null,
            ]),
        ]);

        $notified = [];
        foreach ($recipientIds as $uid) {
            $created = $this->dispatchToUser(
                $uid,
                $eventKey,
                $title,
                $message,
                (string) ($context['screen'] ?? $spec['screen']),
                $payloadData,
                (string) $spec['category'],
                (string) $spec['priority']
            );
            if ($created !== null) {
                $notified[] = $uid;
            }
        }

        return $notified;
    }

    /**
     * Notify Super Admins ONLY (strictly separated from Country Directors and Global Founders).
     */
    public function notifySuperAdmins(
        string $type,
        string $title,
        string $message,
        string $screen = '/dashboard',
        array $data = [],
        string $category = 'system'
    ): void {
        foreach ($this->policy()->resolveSuperAdminUserIds() as $adminId) {
            $this->dispatchToUser($adminId, $type, $title, $message, $screen, $data, $category, 'high');
        }
    }

    /**
     * Notify Global Founders of strategic governance events.
     */
    public function notifyGlobalFounders(
        string $type,
        string $title,
        string $message,
        string $screen = '/dashboard',
        array $data = [],
        string $category = 'governance'
    ): void {
        foreach ($this->policy()->resolveGlobalFounderUserIds() as $founderId) {
            $this->dispatchToUser($founderId, $type, $title, $message, $screen, $data, $category, 'high');
        }
    }

    /**
     * Notify Country Directors strictly within their assigned country scope.
     */
    public function notifyCountryDirectors(
        ?string $countryId,
        ?string $countryName,
        string $type,
        string $title,
        string $message,
        string $screen = '/dashboard',
        array $data = [],
        string $category = 'governance'
    ): void {
        foreach ($this->policy()->resolveCountryDirectorUserIds($countryId, $countryName) as $cdId) {
            $this->dispatchToUser($cdId, $type, $title, $message, $screen, $data, $category, 'high');
        }
    }

    /**
     * Notify Circle Leaders (scoped strictly to the given circle and allowed roles).
     *
     * @param  array<int, string>  $allowedRoles
     */
    public function notifyCircleLeaders(
        string $circleId,
        string $type,
        string $title,
        string $message,
        string $screen = '/circle-details',
        array $data = [],
        string $category = 'governance',
        array $allowedRoles = ['circleDirector', 'circleFounder']
    ): void {
        if (! Schema::hasTable('circles')) {
            return;
        }

        $circle = Circle::query()->find($circleId);
        if (! $circle) {
            return;
        }

        $leaderIds = $this->policy()->resolveCircleLeaderUserIds($circleId, $allowedRoles);

        foreach ($leaderIds as $leaderId) {
            $this->dispatchToUser(
                $leaderId,
                $type,
                $title,
                $message,
                $screen,
                array_merge($data, [
                    'circle_id' => $circleId,
                    'circle_name' => $circle->name ?? 'Circle',
                    'entity_type' => $data['entity_type'] ?? 'circle',
                    'entity_id' => $data['entity_id'] ?? $circleId,
                ]),
                $category,
                'medium'
            );
        }
    }

    /**
     * Notify the District Executive Director (DED) governing a district.
     */
    public function notifyDistrictDirector(
        string $districtId,
        string $type,
        string $title,
        string $message,
        string $screen = '/circle-details',
        array $data = [],
        string $category = 'governance'
    ): void {
        $dedIds = $this->policy()->resolveDistrictDirectorUserIds($districtId);

        foreach ($dedIds as $dedUserId) {
            $this->dispatchToUser(
                $dedUserId,
                $type,
                $title,
                $message,
                $screen,
                array_merge($data, ['district_id' => $districtId]),
                $category,
                'medium'
            );
        }
    }

    /**
     * Notify Industry Directors strictly via authoritative industry_director_assignments.
     */
    public function notifyIndustryDirector(
        string $industryId,
        string $type,
        string $title,
        string $message,
        string $screen = '/leader-activities',
        array $data = [],
        string $category = 'industry'
    ): void {
        $idUsers = $this->policy()->resolveIndustryDirectorUserIds($industryId);

        foreach ($idUsers as $uid) {
            $this->dispatchToUser(
                $uid,
                $type,
                $title,
                $message,
                $screen,
                array_merge($data, ['industry_id' => $industryId]),
                $category,
                'medium'
            );
        }
    }

    /**
     * Hierarchical Bottom-to-Top Activity Escalation:
     * Circle Leaders -> District Exec Director -> (optionally) Super Admin.
     */
    public function bubbleUpCircleEvent(
        string $circleId,
        string $type,
        string $title,
        string $message,
        string $screen = '/circle-details',
        array $data = [],
        string $category = 'governance',
        bool $notifySuperAdmin = false
    ): void {
        $this->notifyCircleLeaders($circleId, $type, $title, $message, $screen, $data, $category);

        if (Schema::hasTable('circles')) {
            $circle = Circle::query()->find($circleId);
            if ($circle && ! empty($circle->district_id)) {
                $districtId = (string) $circle->district_id;
                $this->notifyDistrictDirector(
                    $districtId,
                    $type,
                    $title,
                    $message,
                    $screen,
                    array_merge($data, ['circle_id' => $circleId, 'district_id' => $districtId]),
                    $category
                );
            }
        }

        if ($notifySuperAdmin) {
            $this->notifySuperAdmins(
                $type,
                $title,
                $message,
                $screen,
                array_merge($data, ['circle_id' => $circleId]),
                $category
            );
        }
    }
}
