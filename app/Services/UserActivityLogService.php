<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserActivityLog;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserActivityLogService
{
    /**
     * Record a new user activity log.
     *
     * @param  array<string, mixed>  $data
     */
    public function log(array $data, ?User $user = null, ?Request $request = null): ?UserActivityLog
    {
        if (! Schema::hasTable('user_activity_logs')) {
            Log::warning('[UserActivityLogService] Table user_activity_logs does not exist yet. Please run the SQL migration.', $data);

            return null;
        }

        $userId = $user?->id ?? $data['user_id'] ?? null;
        $ip = $data['ip_address'] ?? ($request?->ip() ?? null);
        $userAgent = $data['user_agent'] ?? ($request?->userAgent() ?? null);

        $properties = $data['properties'] ?? $data['metadata'] ?? [];
        if (! is_array($properties)) {
            $properties = (array) $properties;
        }

        $deviceInfo = $data['device_info'] ?? null;
        if (is_string($deviceInfo)) {
            $decoded = json_decode($deviceInfo, true);
            if (is_array($decoded)) {
                $deviceInfo = $decoded;
            }
        }

        return UserActivityLog::create([
            'user_id' => $userId ? (string) $userId : null,
            'activity_type' => (string) ($data['activity_type'] ?? 'general'),
            'action' => isset($data['action']) ? (string) $data['action'] : null,
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'module' => isset($data['module']) ? (string) $data['module'] : null,
            'entity_type' => isset($data['entity_type']) ? (string) $data['entity_type'] : null,
            'entity_id' => isset($data['entity_id']) ? (string) $data['entity_id'] : null,
            'status' => (string) ($data['status'] ?? 'success'),
            'ip_address' => $ip ? (string) $ip : null,
            'user_agent' => $userAgent ? (string) $userAgent : null,
            'device_info' => is_array($deviceInfo) ? $deviceInfo : (empty($deviceInfo) ? null : ['raw' => (string) $deviceInfo]),
            'platform' => isset($data['platform']) ? (string) $data['platform'] : null,
            'app_version' => isset($data['app_version']) ? (string) $data['app_version'] : null,
            'properties' => $properties,
        ]);
    }

    /**
     * Retrieve paginated activity logs for a user.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getLogsForUser(string $userId, int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        if (! Schema::hasTable('user_activity_logs')) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        $query = UserActivityLog::query()
            ->where('user_id', $userId)
            ->when(! empty($filters['activity_type']), fn ($q) => $q->where('activity_type', $filters['activity_type']))
            ->when(! empty($filters['module']), fn ($q) => $q->where('module', $filters['module']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->latest('created_at');

        return $query->paginate($perPage);
    }

    /**
     * Retrieve paginated activity logs for admin overview.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAdminLogs(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        if (! Schema::hasTable('user_activity_logs')) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        $activityType = trim((string) ($filters['activity_type'] ?? 'all'));
        $module = trim((string) ($filters['module'] ?? 'all'));
        $status = trim((string) ($filters['status'] ?? 'all'));
        $platform = trim((string) ($filters['platform'] ?? 'all'));
        $dateFrom = ! empty($filters['date_from']) ? (string) $filters['date_from'] : null;
        $dateTo = ! empty($filters['date_to']) ? (string) $filters['date_to'] : null;

        $query = UserActivityLog::query()
            ->with(['user:id,first_name,last_name,display_name,email,phone,company_name,profile_photo_url'])
            ->search($search)
            ->activityType($activityType)
            ->module($module)
            ->status($status)
            ->platform($platform)
            ->dateRange($dateFrom, $dateTo)
            ->latest('created_at');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Compute overview statistics for the admin dashboard.
     *
     * @return array<string, int>
     */
    public function getAdminStats(): array
    {
        if (! Schema::hasTable('user_activity_logs')) {
            return [
                'total_logs' => 0,
                'today_logs' => 0,
                'failed_logs' => 0,
                'mobile_logs' => 0,
            ];
        }

        $today = Carbon::today()->startOfDay();

        return [
            'total_logs' => (int) UserActivityLog::query()->count(),
            'today_logs' => (int) UserActivityLog::query()->where('created_at', '>=', $today)->count(),
            'failed_logs' => (int) UserActivityLog::query()->whereIn('status', ['failed', 'blocked', 'warning'])->count(),
            'mobile_logs' => (int) UserActivityLog::query()->whereIn('platform', ['android', 'ios', 'flutter'])->count(),
        ];
    }

    /**
     * Retrieve unique filter options from logged records.
     *
     * @return array<string, array<int, string>>
     */
    public function getFilterOptions(): array
    {
        if (! Schema::hasTable('user_activity_logs')) {
            return [
                'activity_types' => ['login', 'logout', 'failed_login', 'screen_view', 'otp_verify', 'security_alert', 'password_change'],
                'modules' => ['auth', 'security', 'profile', 'speed_collaboration', 'store', 'circles', 'settings'],
                'platforms' => ['android', 'ios', 'web', 'windows', 'macos'],
                'statuses' => ['success', 'failed', 'warning', 'blocked'],
            ];
        }

        $activityTypes = UserActivityLog::query()->distinct()->whereNotNull('activity_type')->pluck('activity_type')->filter()->values()->all();
        $modules = UserActivityLog::query()->distinct()->whereNotNull('module')->pluck('module')->filter()->values()->all();
        $platforms = UserActivityLog::query()->distinct()->whereNotNull('platform')->pluck('platform')->filter()->values()->all();

        return [
            'activity_types' => ! empty($activityTypes) ? $activityTypes : ['login', 'logout', 'failed_login', 'screen_view', 'otp_verify', 'security_alert', 'password_change'],
            'modules' => ! empty($modules) ? $modules : ['auth', 'security', 'profile', 'speed_collaboration', 'store', 'circles', 'settings'],
            'platforms' => ! empty($platforms) ? $platforms : ['android', 'ios', 'web', 'windows', 'macos'],
            'statuses' => ['success', 'failed', 'warning', 'blocked'],
        ];
    }

    /**
     * Export matching activity logs as CSV stream.
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportCsv(array $filters = []): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="user_activity_logs_'.date('Y-m-d_His').'.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($filters): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Log ID',
                'Timestamp (UTC)',
                'User ID',
                'User Name',
                'User Email',
                'Activity Type',
                'Action',
                'Module',
                'Status',
                'IP Address',
                'Platform',
                'App Version',
                'Description',
                'Device Info (JSON)',
                'Properties (JSON)',
            ]);

            if (Schema::hasTable('user_activity_logs')) {
                $search = trim((string) ($filters['search'] ?? ''));
                $activityType = trim((string) ($filters['activity_type'] ?? 'all'));
                $module = trim((string) ($filters['module'] ?? 'all'));
                $status = trim((string) ($filters['status'] ?? 'all'));
                $platform = trim((string) ($filters['platform'] ?? 'all'));
                $dateFrom = ! empty($filters['date_from']) ? (string) $filters['date_from'] : null;
                $dateTo = ! empty($filters['date_to']) ? (string) $filters['date_to'] : null;

                $query = UserActivityLog::query()
                    ->with(['user'])
                    ->search($search)
                    ->activityType($activityType)
                    ->module($module)
                    ->status($status)
                    ->platform($platform)
                    ->dateRange($dateFrom, $dateTo)
                    ->latest('created_at');

                $query->chunk(500, function ($logs) use ($handle): void {
                    foreach ($logs as $log) {
                        $userName = $log->user ? ($log->user->display_name ?: $log->user->first_name.' '.$log->user->last_name) : 'Guest / System';
                        fputcsv($handle, [
                            $log->id,
                            $log->created_at?->toIso8601String(),
                            $log->user_id ?? '',
                            $userName,
                            $log->user?->email ?? '',
                            $log->activity_type,
                            $log->action ?? '',
                            $log->module ?? '',
                            $log->status,
                            $log->ip_address ?? '',
                            $log->platform ?? '',
                            $log->app_version ?? '',
                            $log->description ?? '',
                            is_array($log->device_info) ? json_encode($log->device_info, JSON_UNESCAPED_SLASHES) : ($log->device_info ?? ''),
                            is_array($log->properties) ? json_encode($log->properties, JSON_UNESCAPED_SLASHES) : ($log->properties ?? ''),
                        ]);
                    }
                });
            }

            fclose($handle);
        }, 200, $headers);
    }
}
