<?php

declare(strict_types=1);

namespace App\Leader\Services;

use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\District;
use App\Models\Notification;
use App\Models\Notifications\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeaderNotificationDispatcherService
{
    /**
     * Dispatch an event-driven notification to a specific user.
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
        if (empty($userIdStr)) {
            return null;
        }

        try {
            $dedupeKey = md5($userIdStr . '_' . $type . '_' . ($data['reference_id'] ?? $title) . '_' . date('Y-m-d'));

            // 1. Create AppNotification entry
            $appNotification = null;
            if (Schema::hasTable('app_notifications')) {
                // Prevent duplicate notifications for same event on same day
                $existing = AppNotification::query()
                    ->where('user_id', $userIdStr)
                    ->where('dedupe_key', $dedupeKey)
                    ->first();

                if ($existing) {
                    return $existing;
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
                    'reference_type' => $data['reference_type'] ?? null,
                    'reference_id' => $data['reference_id'] ?? null,
                    'screen' => $screen,
                    'data' => array_merge($data, [
                        'type' => $type,
                        'category' => $category,
                        'screen' => $screen,
                        'title' => $title,
                        'body' => $message,
                    ]),
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
                        'type' => 'activity_update', // Postgres enum compatible type
                        'title' => $title,
                        'message' => $message,
                        'payload' => array_merge($data, [
                            'notification_type' => $type,
                            'type' => $type,
                            'category' => $category,
                            'screen' => $screen,
                            'title' => $title,
                            'body' => $message,
                        ]),
                        'data' => $data,
                        'source_type' => 'leader_event',
                        'source_id' => $data['reference_id'] ?? null,
                        'source_event' => $type,
                        'is_read' => false,
                        'created_at' => now(),
                    ]);
                } catch (\Throwable $ne) {
                    Log::warning('Failed to insert into notifications table: ' . $ne->getMessage());
                }
            }

            // 3. Dispatch real-time FCM push notification directly to user devices
            try {
                $user = User::query()->find($userIdStr);
                if ($user) {
                    $fcmData = array_merge($data, [
                        'type' => $type,
                        'category' => $category,
                        'screen' => $screen,
                        'title' => $title,
                        'body' => $message,
                        'app' => 'leader',
                        'app_name' => 'leader_app',
                    ]);

                    if (class_exists(\App\Services\Notifications\FcmService::class)) {
                        $fcmService = app(\App\Services\Notifications\FcmService::class);
                        $fcmService->sendToUser($user, $title, $message, $fcmData, $appNotification);
                    } elseif (class_exists(\App\Jobs\SendFcmNotificationJob::class)) {
                        \App\Jobs\SendFcmNotificationJob::dispatchSync($userIdStr, $title, $message, $fcmData);
                    }
                }
            } catch (\Throwable $fe) {
                Log::warning('FCM job dispatch failed: ' . $fe->getMessage());
            }

            return $appNotification;
        } catch (\Throwable $e) {
            Log::warning('Failed to dispatch leader notification: ' . $e->getMessage(), [
                'user_id' => $userIdStr,
                'type' => $type,
            ]);
            return null;
        }
    }

    /**
     * Notify Super Admins and Country Directors of platform-level governance events.
     */
    public function notifySuperAdmins(
        string $type,
        string $title,
        string $message,
        string $screen = '/dashboard',
        array $data = [],
        string $category = 'system'
    ): void {
        $adminIds = [];

        // Check users table with super_admin / country_director roles
        if (Schema::hasTable('users')) {
            $cols = Schema::getColumnListing('users');
            $q = User::query();

            if (in_array('role', $cols, true)) {
                $q->whereIn('role', ['super_admin', 'superadmin', 'country_director', 'cdir']);
            } elseif (in_array('role_id', $cols, true)) {
                $q->whereIn('role_id', ['super_admin', 'superadmin', 'country_director', 'cdir']);
            }

            $users = $q->pluck('id')->all();
            foreach ($users as $uid) {
                $adminIds[] = (string) $uid;
            }
        }

        // Also check admin_users table
        if (Schema::hasTable('admin_users')) {
            $adminUsers = AdminUser::query()->pluck('id')->all();
            foreach ($adminUsers as $aid) {
                $adminIds[] = (string) $aid;
            }
        }

        $adminIds = array_unique(array_filter($adminIds));

        foreach ($adminIds as $adminId) {
            $this->dispatchToUser(
                $adminId,
                $type,
                $title,
                $message,
                $screen,
                $data,
                $category,
                'high'
            );
        }
    }

    /**
     * Notify Circle Director (CD) and Circle Founder (CF) for an assigned circle.
     */
    public function notifyCircleLeaders(
        string $circleId,
        string $type,
        string $title,
        string $message,
        string $screen = '/teams',
        array $data = [],
        string $category = 'circle'
    ): void {
        if (! Schema::hasTable('circles')) {
            return;
        }

        $circle = Circle::query()->find($circleId);
        if (! $circle) {
            return;
        }

        $leaderIds = [];
        $cols = Schema::getColumnListing('circles');

        foreach (['circle_director_user_id', 'director_user_id', 'founder_user_id', 'circle_founder_user_id'] as $col) {
            if (in_array($col, $cols, true) && ! empty($circle->{$col})) {
                $leaderIds[] = (string) $circle->{$col};
            }
        }

        // Also check tenure table for active CD/CF if available
        if (Schema::hasTable('finance_leader_tenures')) {
            $tenureUserIds = \Illuminate\Support\Facades\DB::table('finance_leader_tenures')
                ->where('circle_id', $circleId)
                ->where('status', 'active')
                ->pluck('user_id')
                ->all();

            foreach ($tenureUserIds as $tuid) {
                if (! empty($tuid)) {
                    $leaderIds[] = (string) $tuid;
                }
            }
        }

        $leaderIds = array_unique(array_filter($leaderIds));

        foreach ($leaderIds as $leaderId) {
            $this->dispatchToUser(
                $leaderId,
                $type,
                $title,
                $message,
                $screen,
                array_merge($data, ['circle_id' => $circleId, 'circle_name' => $circle->name ?? 'Circle']),
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
        string $screen = '/teams',
        array $data = [],
        string $category = 'circle'
    ): void {
        if (! Schema::hasTable('districts')) {
            return;
        }

        $district = District::query()->find($districtId);
        if (! $district) {
            return;
        }

        $cols = Schema::getColumnListing('districts');
        $dedUserId = null;

        foreach (['ded_user_id', 'executive_director_user_id', 'director_user_id'] as $col) {
            if (in_array($col, $cols, true) && ! empty($district->{$col})) {
                $dedUserId = (string) $district->{$col};
                break;
            }
        }

        if ($dedUserId) {
            $this->dispatchToUser(
                $dedUserId,
                $type,
                $title,
                $message,
                $screen,
                array_merge($data, ['district_id' => $districtId, 'district_name' => $district->name ?? 'District']),
                $category,
                'medium'
            );
        }
    }

    /**
     * Notify the Industry Director (ID) governing an industry sector.
     */
    public function notifyIndustryDirector(
        string $industryId,
        string $type,
        string $title,
        string $message,
        string $screen = '/peers',
        array $data = [],
        string $category = 'peers'
    ): void {
        // Find Industry Director assigned to this industry
        $idUsers = [];

        if (Schema::hasTable('users')) {
            $q = User::query()->where(function ($sub) use ($industryId): void {
                $cols = Schema::getColumnListing('users');
                if (in_array('role', $cols, true)) {
                    $sub->where('role', 'industry_director');
                }
                if (in_array('industry_id', $cols, true)) {
                    $sub->where('industry_id', $industryId);
                }
            });
            $idUsers = $q->pluck('id')->all();
        }

        foreach ($idUsers as $uid) {
            $this->dispatchToUser(
                (string) $uid,
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
     * When an event happens in a circle (e.g. peer joined, attendance drop, report submitted),
     * notify Circle Leaders -> District Exec Director -> Super Admin.
     */
    public function bubbleUpCircleEvent(
        string $circleId,
        string $type,
        string $title,
        string $message,
        string $screen = '/teams',
        array $data = [],
        string $category = 'circle',
        bool $notifySuperAdmin = false
    ): void {
        // 1. Notify Circle Director & Founder
        $this->notifyCircleLeaders($circleId, $type, $title, $message, $screen, $data, $category);

        // 2. Resolve District and notify DED
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

        // 3. If critical or significant, bubble up to Super Admin (app owner)
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

