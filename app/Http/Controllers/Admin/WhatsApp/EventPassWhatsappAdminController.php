<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\WhatsappMessage;
use App\Services\Events\EventRegistrationQrService;
use App\Services\Notifications\EventRegistrationWhatsappService;
use App\Support\AdminCircleScope;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EventPassWhatsappAdminController extends Controller
{
    /**
     * Display listing of Event WhatsApp QR Passes and delivery metrics.
     */
    public function index(Request $request): View
    {
        $filters = $request->only([
            'search',
            'status',
            'event_id',
            'user_type',
            'from_date',
            'to_date',
            'quick',
            'date_preset',
        ]);

        $activePreset = $filters['date_preset'] ?? $filters['quick'] ?? '';
        if ($activePreset === 'all_time' || $activePreset === 'all' || $activePreset === 'any') {
            $activePreset = '';
        }
        $filters['date_preset'] = $activePreset;

        $presets = [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'this_week' => 'This Week',
            'last_week' => 'Last Week',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'last_7_days' => 'Last 7 Days',
            'last_30_days' => 'Last 30 Days',
            'last_90_days' => 'Last 90 Days',
        ];

        $admin = Auth::guard('admin')->user();

        // Base query with circle access scoping
        $baseQuery = EventRegistration::query()
            ->where('status', '!=', 'cancelled')
            ->whereHas('event', function ($eq) use ($admin) {
                AdminCircleScope::applyToEventsQuery($eq, $admin);
            });

        // Compute summary statistics across the base dataset or filtered by date
        $statsQuery = clone $baseQuery;
        if (! empty($filters['event_id'])) {
            $statsQuery->where('event_id', $filters['event_id']);
        }
        $this->applyDateFilter($statsQuery, $filters);

        $totalPasses = (clone $statsQuery)->count();
        $totalDelivered = (clone $statsQuery)->whereIn('whatsapp_status', ['delivered', 'read'])->count();
        $totalRead = (clone $statsQuery)->where('whatsapp_status', 'read')->count();
        $totalSent = (clone $statsQuery)->where('whatsapp_status', 'sent')->count();
        $totalFailed = (clone $statsQuery)->where('whatsapp_status', 'failed')->count();
        $totalPending = (clone $statsQuery)->where(function ($q) {
            $q->whereNull('whatsapp_status')
                ->orWhereIn('whatsapp_status', ['', 'pending']);
        })->count();

        $attempted = $totalDelivered + $totalSent + $totalFailed;
        $deliveryRate = $attempted > 0 ? round(($totalDelivered / $attempted) * 100, 1) : 0.0;

        // Breakdown by top events
        $eventBreakdown = Event::query()
            ->whereHas('registrations', function ($rq) {
                $rq->where('status', '!=', 'cancelled');
            })
            ->withCount([
                'registrations as total_registrations' => fn ($q) => $q->where('status', '!=', 'cancelled'),
                'registrations as delivered_count' => fn ($q) => $q->whereIn('whatsapp_status', ['delivered', 'read'])->where('status', '!=', 'cancelled'),
                'registrations as failed_count' => fn ($q) => $q->where('whatsapp_status', 'failed')->where('status', '!=', 'cancelled'),
            ])
            ->orderByDesc('total_registrations')
            ->limit(8)
            ->get();

        // Main listing query
        $query = clone $baseQuery;
        $query->with(['event.circle', 'occurrence', 'user']);
        $this->applyFilters($query, $filters);

        $registrations = $query->latest('created_at')
            ->paginate((int) $request->input('per_page', 20))
            ->withQueryString();

        // Events list for filter
        $eventsQuery = Event::query()->orderBy('title');
        AdminCircleScope::applyToEventsQuery($eventsQuery, $admin);
        $events = $eventsQuery->get(['id', 'title', 'start_at']);

        return view('admin.whatsapp.event_passes.index', [
            'registrations' => $registrations,
            'filters' => $filters,
            'presets' => $presets,
            'activePreset' => $activePreset,
            'activeStatus' => $filters['status'] ?? '',
            'events' => $events,
            'eventBreakdown' => $eventBreakdown,
            'stats' => [
                'total_passes' => $totalPasses,
                'total_delivered' => $totalDelivered,
                'total_read' => $totalRead,
                'total_sent' => $totalSent,
                'total_failed' => $totalFailed,
                'total_pending' => $totalPending,
                'delivery_rate' => $deliveryRate,
            ],
        ]);
    }

    /**
     * Resend WhatsApp QR Pass to a specific attendee.
     */
    public function resend(string $id, EventRegistrationWhatsappService $whatsappService): JsonResponse|RedirectResponse
    {
        $registration = EventRegistration::with(['event', 'user', 'occurrence'])->find($id);
        if (! $registration) {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event registration record not found.',
                ], 404);
            }

            return back()->with('error', 'Registration record not found.');
        }

        if ($registration->isSpeedNetworking()) {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Speed Networking events are virtual sessions and do not use in-person QR passes.',
                ], 422);
            }

            return back()->with('error', 'Speed Networking events are virtual sessions and do not use in-person QR passes.');
        }

        // Ensure QR code is generated (force = true for admin resend)
        app(EventRegistrationQrService::class)->ensureQrGenerated($registration, force: true);

        $phone = $registration->user?->phone ?: $registration->visitor_phone;
        $name = $registration->user?->display_name ?: ($registration->user ? trim(($registration->user->first_name ?? '').' '.($registration->user->last_name ?? '')) : ($registration->visitor_name ?: 'Participant'));

        $sent = $whatsappService->sendNotification($registration, force: true);
        $registration->refresh();

        if ($sent) {
            $msg = "WhatsApp QR pass resent successfully to {$name} ({$phone})!";
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'registration' => [
                        'id' => $registration->id,
                        'whatsapp_status' => $registration->whatsapp_status,
                        'whatsapp_sent_at' => $registration->whatsapp_sent_at?->format('d M Y, h:i A') ?: now()->format('d M Y, h:i A'),
                        'whatsapp_provider_id' => $registration->whatsapp_provider_id,
                    ],
                ]);
            }

            return back()->with('success', $msg);
        }

        $errMsg = 'Failed to send WhatsApp message. Please check recipient phone number and WhatsApp template status.';
        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $errMsg,
                'status' => $registration->whatsapp_status,
            ], 500);
        }

        return back()->with('error', $errMsg);
    }

    /**
     * Bulk resend WhatsApp QR passes for multiple registrations.
     */
    public function bulkResend(Request $request, EventRegistrationWhatsappService $whatsappService): JsonResponse
    {
        $ids = (array) $request->input('ids', []);
        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'No registrations selected for resend.',
            ], 422);
        }

        $registrations = EventRegistration::with(['event', 'user', 'occurrence'])
            ->whereIn('id', $ids)
            ->get();

        $successCount = 0;
        $failedCount = 0;

        foreach ($registrations as $reg) {
            try {
                app(EventRegistrationQrService::class)->ensureQrGenerated($reg, force: true);
                if ($whatsappService->sendNotification($reg, force: true)) {
                    $successCount++;
                } else {
                    $failedCount++;
                }
            } catch (Throwable) {
                $failedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Bulk dispatch complete: {$successCount} sent successfully, {$failedCount} failed.",
            'success_count' => $successCount,
            'failed_count' => $failedCount,
        ]);
    }

    /**
     * Show detailed pass and WhatsApp delivery trail for a registration.
     */
    public function show(string $id): View|JsonResponse
    {
        $registration = EventRegistration::with(['event.circle', 'occurrence', 'user'])->findOrFail($id);

        $qrService = app(EventRegistrationQrService::class);
        $qrService->ensureQrGenerated($registration);

        $qrUrl = $qrService->qrCodeUrl($registration);
        $inlineSvg = $registration->qr_code_svg;

        // Delivery logs safely queried without non-existent notifiable columns
        $matchIds = array_values(array_filter([
            (string) $registration->id,
            $registration->whatsapp_provider_id,
        ]));

        $deliveryLogs = NotificationDeliveryLog::query()
            ->where(function ($q) use ($matchIds, $registration) {
                $q->whereIn('provider_message_id', $matchIds);
                if (\Illuminate\Support\Facades\Schema::hasColumn('notification_delivery_logs', 'request_payload')) {
                    $q->orWhere('request_payload->registration_id', (string) $registration->id);
                }
            })
            ->latest()
            ->get();

        // Meta WhatsApp Message record if available
        $metaMessage = $registration->whatsapp_provider_id
            ? WhatsappMessage::where('meta_message_id', $registration->whatsapp_provider_id)->first()
            : null;

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'registration' => $registration,
                'qr_url' => $qrUrl,
                'delivery_logs' => $deliveryLogs,
                'meta_message' => $metaMessage,
            ]);
        }

        return view('admin.whatsapp.event_passes.show', [
            'registration' => $registration,
            'qrUrl' => $qrUrl,
            'inlineSvg' => $inlineSvg,
            'deliveryLogs' => $deliveryLogs,
            'metaMessage' => $metaMessage,
        ]);
    }

    /**
     * Export filtered event passes to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->only([
            'search',
            'status',
            'event_id',
            'user_type',
            'from_date',
            'to_date',
            'date_preset',
        ]);

        $admin = Auth::guard('admin')->user();
        $query = EventRegistration::query()
            ->where('status', '!=', 'cancelled')
            ->whereHas('event', function ($eq) use ($admin) {
                AdminCircleScope::applyToEventsQuery($eq, $admin);
            })
            ->with(['event.circle', 'occurrence', 'user']);

        $this->applyFilters($query, $filters);

        $filename = 'event-whatsapp-passes-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            // Add UTF-8 BOM
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Registration ID',
                'Participant Name',
                'Type',
                'Phone',
                'Email',
                'Event Title',
                'Event Date',
                'Circle',
                'WhatsApp Status',
                'Sent At',
                'Meta Message ID (wamid)',
                'Check-in Status',
                'Registered At',
            ]);

            $query->chunk(200, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    $isMember = ! empty($row->user_id);
                    $name = $isMember
                        ? ($row->user?->display_name ?: trim(($row->user?->first_name ?? '').' '.($row->user?->last_name ?? '')))
                        : ($row->visitor_name ?: 'Visitor');
                    $phone = $isMember ? ($row->user?->phone ?: '-') : ($row->visitor_phone ?: '-');
                    $email = $isMember ? ($row->user?->email ?: '-') : ($row->visitor_email ?: '-');

                    fputcsv($handle, [
                        $row->id,
                        $name,
                        $isMember ? 'Member' : 'Visitor',
                        $phone,
                        $email,
                        $row->event?->title ?? '-',
                        optional($row->occurrence?->start_at ?: $row->event?->start_at)->format('Y-m-d H:i') ?? '-',
                        $row->event?->circle?->name ?? '-',
                        strtoupper($row->whatsapp_status ?: 'PENDING'),
                        $row->whatsapp_sent_at?->format('Y-m-d H:i:s') ?? '-',
                        $row->whatsapp_provider_id ?? '-',
                        $row->checkin_status ?? 'not_checked_in',
                        $row->created_at?->format('Y-m-d H:i:s') ?? '-',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Apply filter criteria to query.
     */
    private function applyFilters($query, array $filters): void
    {
        // 1. Search
        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $wildcard = "%{$term}%";

            $query->where(function ($q) use ($like, $wildcard, $term) {
                $q->where('visitor_name', $like, $wildcard)
                    ->orWhere('visitor_phone', $like, $wildcard)
                    ->orWhere('visitor_email', $like, $wildcard)
                    ->orWhere('id', 'like', "%{$term}%")
                    ->orWhereHas('user', function ($uq) use ($like, $wildcard) {
                        $uq->where('display_name', $like, $wildcard)
                            ->orWhere('first_name', $like, $wildcard)
                            ->orWhere('last_name', $like, $wildcard)
                            ->orWhere('phone', $like, $wildcard)
                            ->orWhere('email', $like, $wildcard);
                    })
                    ->orWhereHas('event', function ($eq) use ($like, $wildcard) {
                        $eq->where('title', $like, $wildcard);
                    });
            });
        }

        // 2. WhatsApp Status
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $status = strtolower($filters['status']);
            if ($status === 'delivered') {
                $query->whereIn('whatsapp_status', ['delivered', 'read']);
            } elseif ($status === 'read') {
                $query->where('whatsapp_status', 'read');
            } elseif ($status === 'sent') {
                $query->where('whatsapp_status', 'sent');
            } elseif ($status === 'failed') {
                $query->where('whatsapp_status', 'failed');
            } elseif ($status === 'pending') {
                $query->where(function ($q) {
                    $q->whereNull('whatsapp_status')
                        ->orWhereIn('whatsapp_status', ['', 'pending']);
                });
            } else {
                $query->where('whatsapp_status', $status);
            }
        }

        // 3. Specific Event
        if (! empty($filters['event_id'])) {
            $query->where('event_id', $filters['event_id']);
        }

        // 4. Member / Visitor Type
        if (! empty($filters['user_type']) && $filters['user_type'] !== 'all') {
            if ($filters['user_type'] === 'member') {
                $query->whereNotNull('user_id');
            } elseif ($filters['user_type'] === 'visitor') {
                $query->whereNull('user_id');
            }
        }

        // 5. Date Filters
        $this->applyDateFilter($query, $filters);
    }

    /**
     * Apply date range or preset filters.
     */
    private function applyDateFilter($query, array $filters): void
    {
        $preset = $filters['date_preset'] ?? $filters['quick'] ?? '';

        if (! empty($preset)) {
            $now = Carbon::now();
            match ($preset) {
                'today' => $query->whereDate('created_at', $now->toDateString()),
                'yesterday' => $query->whereDate('created_at', $now->copy()->subDay()->toDateString()),
                'this_week' => $query->whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]),
                'last_week' => $query->whereBetween('created_at', [
                    $now->copy()->subWeek()->startOfWeek(),
                    $now->copy()->subWeek()->endOfWeek(),
                ]),
                'this_month' => $query->whereBetween('created_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]),
                'last_month' => $query->whereBetween('created_at', [
                    $now->copy()->subMonth()->startOfMonth(),
                    $now->copy()->subMonth()->endOfMonth(),
                ]),
                'last_7_days' => $query->where('created_at', '>=', $now->copy()->subDays(7)->startOfDay()),
                'last_30_days' => $query->where('created_at', '>=', $now->copy()->subDays(30)->startOfDay()),
                'last_90_days' => $query->where('created_at', '>=', $now->copy()->subDays(90)->startOfDay()),
                default => null,
            };

            return;
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }
        if (! empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }
    }
}
