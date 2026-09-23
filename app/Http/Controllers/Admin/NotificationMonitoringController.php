<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notifications\AppNotification;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use App\Services\Notifications\AppNotificationCatalogService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotificationMonitoringController extends Controller
{
    public function __construct(
        private readonly AppNotificationCatalogService $catalogService
    ) {}

    /**
     * Display the Notification Monitoring Dashboard with live stats, advanced multi-filters & paginated logs.
     */
    public function index(Request $request): View
    {
        $filters = $this->buildFilters($request);
        $hasTable = Schema::hasTable('app_notifications');

        if (! $hasTable) {
            return view('admin.notifications.monitoring.index', [
                'items'              => collect(),
                'filters'            => $filters,
                'summary'            => $this->emptySummary(),
                'notificationTypes'  => [],
                'channels'           => $this->availableChannels(),
                'statuses'           => $this->availableStatuses(),
                'total'              => 0,
            ]);
        }

        $baseQuery = $this->buildQuery($filters);

        // Calculate summary metrics for the selected filters (without pagination)
        $summary = $this->calculateSummary($filters);

        // Fetch paginated notification items
        $perPage = max(10, min((int) ($filters['per_page'] ?? 20), 100));
        $items = (clone $baseQuery)
            ->with(['user:id,first_name,last_name,display_name,email,phone,city,membership_status'])
            ->orderBy($this->resolveSortColumn($filters['sort'] ?? 'created_at'), $filters['direction'] ?? 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $notificationTypes = $this->resolveNotificationTypes();

        return view('admin.notifications.monitoring.index', [
            'items'             => $items,
            'filters'           => $filters,
            'summary'           => $summary,
            'notificationTypes' => $notificationTypes,
            'channels'          => $this->availableChannels(),
            'statuses'          => $this->availableStatuses(),
            'total'             => $items->total(),
        ]);
    }

    /**
     * Fetch complete details and delivery attempt logs for a single notification (AJAX Modal).
     */
    public function details(string $id): JsonResponse
    {
        if (! Schema::hasTable('app_notifications')) {
            return response()->json(['success' => false, 'message' => 'Notifications table not found.'], 404);
        }

        $notification = AppNotification::with(['user'])->find($id);

        if (! $notification) {
            return response()->json(['success' => false, 'message' => 'Notification record not found.'], 404);
        }

        $user = $notification->user;
        $userName = $user
            ? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: ($user->display_name ?? $user->email ?? '—')
            : '—';

        $deliveryLogs = Schema::hasTable('notification_delivery_logs')
            ? NotificationDeliveryLog::where('notification_id', $id)->latest()->get()
            : collect();

        // Check if recipient has active push tokens
        $pushTokenCount = 0;
        if ($user && Schema::hasTable('user_push_tokens')) {
            $userColumn = Schema::hasColumn('user_push_tokens', 'usr_id') ? 'usr_id' : 'user_id';
            $tokenQuery = DB::table('user_push_tokens')->where($userColumn, $user->id);
            if (Schema::hasColumn('user_push_tokens', 'deleted_at')) {
                $tokenQuery->whereNull('deleted_at');
            }
            if (Schema::hasColumn('user_push_tokens', 'is_active')) {
                $tokenQuery->where('is_active', true);
            } elseif (Schema::hasColumn('user_push_tokens', 'status')) {
                $tokenQuery->where('status', 'active');
            } elseif (Schema::hasColumn('user_push_tokens', 'token_status')) {
                $tokenQuery->where('token_status', 'active');
            }
            $pushTokenCount = $tokenQuery->count();
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                 => $notification->id,
                'type'               => $notification->type,
                'category'           => $notification->category ?? 'General',
                'channel'            => $notification->channel ?? 'push',
                'priority'           => $notification->priority ?? 'medium',
                'title'              => $notification->title ?? '—',
                'body'               => $notification->body ?? $notification->message ?? '—',
                'screen'             => $notification->screen ?? $notification->navigation_screen ?? '—',
                'status'             => $notification->status ?? 'sent',
                'failure_reason'     => $notification->failure_reason,
                'raw_payload'        => $notification->data ?? [],
                'created_at'         => $notification->created_at ? $notification->created_at->format('M d, Y h:i:s A') : '—',
                'sent_at'            => $notification->sent_at ? $notification->sent_at->format('M d, Y h:i:s A') : null,
                'read_at'            => $notification->read_at ? $notification->read_at->format('M d, Y h:i:s A') : null,
                'clicked_at'         => $notification->clicked_at ? $notification->clicked_at->format('M d, Y h:i:s A') : null,
                'failed_at'          => $notification->failed_at ? $notification->failed_at->format('M d, Y h:i:s A') : null,
                'user'               => [
                    'id'               => $user?->id,
                    'name'             => $userName,
                    'email'            => $user?->email ?? '—',
                    'phone'            => $user?->phone ?? '—',
                    'city'             => $user?->city ?? '—',
                    'membership'       => $user?->membership_status ?? 'Peer',
                    'active_tokens'    => $pushTokenCount,
                ],
                'delivery_logs'      => $deliveryLogs->map(fn ($log) => [
                    'id'                  => $log->id,
                    'channel'             => $log->channel,
                    'provider'            => $log->provider,
                    'provider_message_id' => $log->provider_message_id,
                    'status'              => $log->status,
                    'error_message'       => $log->error_message,
                    'request_payload'     => $log->request_payload,
                    'response_payload'    => $log->response_payload,
                    'attempted_at'        => $log->attempted_at ? $log->attempted_at->format('M d, Y h:i:s A') : null,
                    'delivered_at'        => $log->delivered_at ? $log->delivered_at->format('M d, Y h:i:s A') : null,
                ]),
            ],
        ]);
    }

    /**
     * Stream CSV Export based on active filter criteria.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->buildFilters($request);
        $filename = 'notification_monitoring_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($filters) {
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', '0');
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            $handle = fopen('php://output', 'w');

            try {
                fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM
                fputcsv($handle, [
                    'Notification ID',
                    'Recipient Name',
                    'Recipient Email',
                    'Recipient Phone',
                    'Recipient City',
                    'Notification Type',
                    'Channel',
                    'Title',
                    'Body Message',
                    'Status',
                    'Created At',
                    'Sent At',
                    'Failed At',
                    'Failure Reason',
                ]);

                $query = $this->buildQuery($filters)
                    ->with(['user:id,first_name,last_name,display_name,email,phone,city'])
                    ->orderBy('created_at', 'desc');

                $query->chunk(500, function ($rows) use ($handle) {
                    foreach ($rows as $row) {
                        $user = $row->user;
                        $userName = $user
                            ? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: ($user->display_name ?? $user->email ?? '—')
                            : '—';

                        fputcsv($handle, [
                            $row->id,
                            $userName,
                            $user?->email ?? '',
                            $user?->phone ?? '',
                            $user?->city ?? '',
                            $row->type ?? 'General',
                            $row->channel ?? 'push',
                            $row->title ?? '',
                            $row->body ?? $row->message ?? '',
                            strtoupper((string) ($row->status ?? 'SENT')),
                            $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : '',
                            $row->sent_at ? $row->sent_at->format('Y-m-d H:i:s') : '',
                            $row->failed_at ? $row->failed_at->format('Y-m-d H:i:s') : '',
                            $row->failure_reason ?? '',
                        ]);
                    }
                });
            } finally {
                fclose($handle);
            }
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ──────────────────────────────────────────────
    // Query & Filter Builders
    // ──────────────────────────────────────────────

    private function buildFilters(Request $request): array
    {
        // Default preset to 'today' as required
        $preset = $request->input('date_preset', 'today');
        if (! in_array($preset, ['today', 'yesterday', 'last_7_days', 'last_30_days', 'this_month', 'last_month', 'all', 'custom'], true)) {
            $preset = 'today';
        }

        $fromDate = $request->input('from_date', '');
        $toDate = $request->input('to_date', '');

        if ($preset === 'custom' && empty($fromDate) && empty($toDate)) {
            $preset = 'today';
        }

        return [
            'date_preset'       => $preset,
            'from_date'         => $fromDate,
            'to_date'           => $toDate,
            'status'            => $request->input('status', ''),
            'notification_type' => $request->input('notification_type', ''),
            'channel'           => $request->input('channel', ''),
            'user_query'        => $request->input('user_query', ''),
            'q'                 => $request->input('q', ''),
            'sort'              => $request->input('sort', 'created_at'),
            'direction'         => $request->input('direction', 'desc'),
            'per_page'          => (int) $request->input('per_page', 20),
        ];
    }

    private function buildQuery(array $filters): Builder
    {
        $query = AppNotification::query();

        // 1. Date range filter
        [$start, $end] = $this->resolveDateRange(
            $filters['date_preset'] ?? 'today',
            (string) ($filters['from_date'] ?? ''),
            (string) ($filters['to_date'] ?? '')
        );

        if ($start !== null) {
            $query->where('created_at', '>=', $start);
        }
        if ($end !== null) {
            $query->where('created_at', '<=', $end);
        }

        // 2. Status filter
        if (! empty($filters['status'])) {
            $status = strtolower($filters['status']);
            if ($status === 'sent') {
                $query->where(function ($q) {
                    $q->where('status', 'sent')->orWhereNotNull('sent_at');
                });
            } elseif ($status === 'failed') {
                $query->where(function ($q) {
                    $q->where('status', 'failed')->orWhereNotNull('failed_at');
                });
            } elseif ($status === 'pending') {
                $query->where('status', 'pending');
            } elseif ($status === 'processing') {
                $query->where('status', 'processing');
            } elseif ($status === 'partial') {
                $query->where('status', 'partial');
            } elseif ($status === 'skipped') {
                $query->where('status', 'skipped');
            } else {
                $query->where('status', $status);
            }
        }

        // 3. Notification Type filter
        if (! empty($filters['notification_type'])) {
            $query->where('type', $filters['notification_type']);
        }

        // 4. Channel filter
        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        // 5. User / Recipient filter
        if (! empty($filters['user_query'])) {
            $uq = '%' . strtolower(trim($filters['user_query'])) . '%';
            $query->whereHas('user', function ($uqQuery) use ($uq) {
                $uqQuery->whereRaw('LOWER(display_name) LIKE ?', [$uq])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$uq])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$uq])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$uq])
                    ->orWhere('phone', 'LIKE', $uq);
            });
        }

        // 6. Global search
        if (! empty($filters['q'])) {
            $term = '%' . strtolower(trim($filters['q'])) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(title) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(body) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(type) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(failure_reason) LIKE ?', [$term])
                    ->orWhereHas('user', function ($uqQuery) use ($term) {
                        $uqQuery->whereRaw('LOWER(display_name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                            ->orWhere('phone', 'LIKE', $term);
                    });
            });
        }

        return $query;
    }

    private function calculateSummary(array $filters): array
    {
        // Date range for summary
        [$start, $end] = $this->resolveDateRange(
            $filters['date_preset'] ?? 'today',
            (string) ($filters['from_date'] ?? ''),
            (string) ($filters['to_date'] ?? '')
        );

        // Build base summary query without status filter to show counts for each status
        $summaryQuery = AppNotification::query();
        if ($start !== null) {
            $summaryQuery->where('created_at', '>=', $start);
        }
        if ($end !== null) {
            $summaryQuery->where('created_at', '<=', $end);
        }
        if (! empty($filters['notification_type'])) {
            $summaryQuery->where('type', $filters['notification_type']);
        }
        if (! empty($filters['channel'])) {
            $summaryQuery->where('channel', $filters['channel']);
        }
        if (! empty($filters['user_query'])) {
            $uq = '%' . strtolower(trim($filters['user_query'])) . '%';
            $summaryQuery->whereHas('user', function ($uqQuery) use ($uq) {
                $uqQuery->whereRaw('LOWER(display_name) LIKE ?', [$uq])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$uq])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$uq])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$uq])
                    ->orWhere('phone', 'LIKE', $uq);
            });
        }

        $total = (clone $summaryQuery)->count();
        $sent = (clone $summaryQuery)->where(function ($q) {
            $q->where('status', 'sent')->orWhereNotNull('sent_at');
        })->count();
        $failed = (clone $summaryQuery)->where(function ($q) {
            $q->where('status', 'failed')->orWhereNotNull('failed_at');
        })->count();
        $pending = (clone $summaryQuery)->where('status', 'pending')->count();
        $processing = (clone $summaryQuery)->where('status', 'processing')->count();
        $partial = (clone $summaryQuery)->where('status', 'partial')->count();
        $skipped = (clone $summaryQuery)->where('status', 'skipped')->count();
        $readCount = (clone $summaryQuery)->whereNotNull('read_at')->count();
        $clickedCount = (clone $summaryQuery)->whereNotNull('clicked_at')->count();

        $successRate = $total > 0 ? round(($sent / $total) * 100, 1) : 0;
        $failureRate = $total > 0 ? round(($failed / $total) * 100, 1) : 0;

        return [
            'total'        => $total,
            'sent'         => $sent,
            'failed'       => $failed,
            'pending'      => $pending,
            'processing'   => $processing,
            'partial'      => $partial,
            'skipped'      => $skipped,
            'read_count'   => $readCount,
            'clicked_count'=> $clickedCount,
            'success_rate' => $successRate,
            'failure_rate' => $failureRate,
        ];
    }

    private function resolveDateRange(string $preset, string $from, string $to): array
    {
        $tz  = config('app.timezone', 'UTC');
        $now = Carbon::now($tz);

        return match ($preset) {
            'today'          => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday'      => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'last_7_days'    => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_30_days'   => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'this_month'     => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month'     => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'custom'         => [
                $from !== '' ? Carbon::parse($from, $tz)->startOfDay() : null,
                $to   !== '' ? Carbon::parse($to, $tz)->endOfDay()   : null,
            ],
            'all'            => [null, null],
            default          => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    private function resolveSortColumn(string $sort): string
    {
        return match ($sort) {
            'sent_at'    => 'sent_at',
            'failed_at'  => 'failed_at',
            'status'     => 'status',
            'type'       => 'type',
            'channel'    => 'channel',
            default      => 'created_at',
        };
    }

    private function resolveNotificationTypes(): array
    {
        $catalogTypes = [];
        try {
            $catalogItems = $this->catalogService->getAll();
            foreach ($catalogItems as $item) {
                $k = $item['key'] ?? $item['name'] ?? null;
                if ($k) {
                    $catalogTypes[$k] = $item['name'] ?? ucwords(str_replace('_', ' ', $k));
                }
            }
        } catch (\Throwable) {}

        // Also query distinct types from app_notifications table
        if (Schema::hasTable('app_notifications')) {
            $dbTypes = DB::table('app_notifications')
                ->whereNotNull('type')
                ->where('type', '!=', '')
                ->distinct()
                ->pluck('type');

            foreach ($dbTypes as $t) {
                if (! isset($catalogTypes[$t])) {
                    $catalogTypes[$t] = ucwords(str_replace('_', ' ', (string) $t));
                }
            }
        }

        asort($catalogTypes);

        return $catalogTypes;
    }

    private function availableChannels(): array
    {
        return [
            'push'        => 'Push Notification (Firebase)',
            'email'       => 'Email Notification',
            'push_email'  => 'Push & Email Combined',
            'in_app_only' => 'In-App Only',
            'whatsapp'    => 'WhatsApp Message',
        ];
    }

    private function availableStatuses(): array
    {
        return [
            'sent'       => 'Sent / Delivered',
            'failed'     => 'Failed',
            'pending'    => 'Pending / Queued',
            'processing' => 'Processing',
            'partial'    => 'Partial (Push/Email)',
            'skipped'    => 'Skipped / Suppressed',
        ];
    }

    private function emptySummary(): array
    {
        return [
            'total'        => 0,
            'sent'         => 0,
            'failed'       => 0,
            'pending'      => 0,
            'processing'   => 0,
            'partial'      => 0,
            'skipped'      => 0,
            'read_count'   => 0,
            'clicked_count'=> 0,
            'success_rate' => 0,
            'failure_rate' => 0,
        ];
    }
}
