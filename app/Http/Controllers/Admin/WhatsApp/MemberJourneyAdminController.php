<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WhatsApp\SendJourneyDayRequest;
use App\Models\MemberJourneySend;
use App\Models\User;
use App\Services\WhatsApp\MemberJourney30DayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MemberJourneyAdminController extends Controller
{
    public function __construct(
        protected MemberJourney30DayService $journeyService
    ) {}

    /**
     * Display members and their 30-Day Onboarding WhatsApp Journey progress.
     */
    public function index(Request $request): View|JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $filterStatus = trim((string) $request->query('status', ''));

        // High-level journey metrics across all members
        $stats = [
            'total_peers' => User::count(),
            'total_in_journey' => MemberJourneySend::distinct('user_id')->count('user_id'),
            'total_sent' => MemberJourneySend::where('status', 'sent')->count(),
            'total_skipped' => MemberJourneySend::where('status', 'skipped')->count(),
            'total_due_today' => MemberJourneySend::where('status', 'scheduled')
                ->where('scheduled_at', '<=', now())
                ->count(),
            'total_failed' => MemberJourneySend::where('status', 'failed')->count(),
        ];

        // Member query with aggregated journey counts
        $userQuery = User::query()
            ->select([
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.display_name',
                'users.email',
                'users.phone',
                'users.created_at',
            ])
            ->withCount([
                'memberJourneySends as total_days_scheduled' => function ($q) {
                    $q->where('status', 'scheduled');
                },
                'memberJourneySends as total_sent' => function ($q) {
                    $q->where('status', 'sent');
                },
                'memberJourneySends as total_skipped' => function ($q) {
                    $q->where('status', 'skipped');
                },
                'memberJourneySends as total_failed' => function ($q) {
                    $q->where('status', 'failed');
                },
                'memberJourneySends as total_days_count',
            ]);

        if ($search !== '') {
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $userQuery->where(function ($q) use ($search, $like) {
                $q->where('first_name', $like, "%{$search}%")
                    ->orWhere('last_name', $like, "%{$search}%")
                    ->orWhere('display_name', $like, "%{$search}%")
                    ->orWhere('phone', $like, "%{$search}%")
                    ->orWhere('email', $like, "%{$search}%");
            });
        }

        if ($filterStatus === 'in_journey') {
            $userQuery->whereHas('memberJourneySends');
        } elseif ($filterStatus === 'not_started') {
            $userQuery->whereDoesntHave('memberJourneySends');
        } elseif ($filterStatus === 'in_progress') {
            $userQuery->whereHas('memberJourneySends', function ($q) {
                $q->where('status', 'scheduled');
            });
        } elseif ($filterStatus === 'completed') {
            $userQuery->whereHas('memberJourneySends')
                ->whereDoesntHave('memberJourneySends', function ($q) {
                    $q->where('status', 'scheduled');
                });
        } elseif ($filterStatus === 'failed') {
            $userQuery->whereHas('memberJourneySends', function ($q) {
                $q->where('status', 'failed');
            });
        }

        $members = $userQuery->orderByDesc('created_at')->paginate(15)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'stats' => $stats,
                'members' => $members,
            ]);
        }

        return view('admin.whatsapp.journey.index', [
            'stats' => $stats,
            'members' => $members,
            'search' => $search,
            'status' => $filterStatus,
        ]);
    }

    /**
     * Show detailed 30-day timeline and actions for an individual member.
     */
    public function show(string $userId): View|JsonResponse
    {
        $user = User::findOrFail($userId);

        // Auto-sync delivery statuses from meta message logs
        try {
            app(\App\Services\WhatsApp\MetaDeliveryStatusSyncService::class)->syncJourneyDeliveries($userId);
        } catch (\Throwable) {}

        $sends = $this->journeyService->getMemberJourney($userId);

        // Precompute readiness & profile state
        $profileReadiness = [
            'has_bio' => ! empty($user->short_bio) || ! empty($user->long_bio_html),
            'has_designation' => ! empty($user->designation),
            'has_company' => ! empty($user->company_name),
            'has_business_info' => ! empty($user->business_type) || ! empty($user->industry_tags) || ! empty($user->experience_summary),
            'has_photo' => ! empty($user->profile_photo_url) || ! empty($user->profile_photo_file_id),
        ];

        $templateMeta = MemberJourney30DayService::JOURNEY_TEMPLATES;

        // Group sends by day number for fast lookup
        $sendsByDay = $sends->keyBy('day_number');

        $rawPhone = trim((string) ($user->phone ?? $user->secondary_mobile ?? ''));
        $userPhone = '';
        if ($rawPhone !== '') {
            $digits = preg_replace('/\D+/', '', $rawPhone) ?? '';
            if (strlen($digits) === 10) {
                $userPhone = '+91'.$digits;
            } elseif (str_starts_with($rawPhone, '+')) {
                $userPhone = '+'.$digits;
            } elseif (strlen($digits) > 10) {
                $userPhone = '+'.$digits;
            } else {
                $userPhone = $rawPhone;
            }
        }

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'user' => $user,
                'user_phone' => $userPhone,
                'profile_readiness' => $profileReadiness,
                'sends' => $sends,
            ]);
        }

        return view('admin.whatsapp.journey.show', [
            'user' => $user,
            'userPhone' => $userPhone,
            'sends' => $sends,
            'sendsByDay' => $sendsByDay,
            'profileReadiness' => $profileReadiness,
            'templateMeta' => $templateMeta,
        ]);
    }

    /**
     * Initialize or re-populate the 30-Day journey schedule for a member.
     */
    public function initialize(string $userId): RedirectResponse|JsonResponse
    {
        $user = User::findOrFail($userId);
        $createdCount = $this->journeyService->initializeJourney($user);

        $displayName = $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        $message = "Initialized 30-Day Journey for {$displayName}. {$createdCount} new day(s) scheduled.";

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'created_count' => $createdCount,
            ]);
        }

        return redirect()->route('admin.whatsapp.journey.show', $user->id)
            ->with('success', $message);
    }

    /**
     * Bulk initialize 30-Day journey schedule for all uninitialized peers.
     */
    public function initializeAll(Request $request): RedirectResponse|JsonResponse
    {
        $result = $this->journeyService->initializeAllUsers(500);

        $message = "Bulk initialized 30-Day Journey for {$result['users_initialized']} members ({$result['total_days_created']} total days scheduled).";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'result' => $result,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Trigger manual or test delivery of a specific journey day.
     */
    public function sendDay(SendJourneyDayRequest $request, string $sendId): RedirectResponse|JsonResponse
    {
        $send = MemberJourneySend::with('user')->findOrFail($sendId);

        $force = (bool) $request->boolean('force');
        $overridePhone = $request->input('override_phone') ? trim((string) $request->input('override_phone')) : null;

        $result = $this->journeyService->sendDay($send, $force, $overridePhone);

        if ($request->wantsJson()) {
            return response()->json($result, $result['success'] ? 200 : 400);
        }

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    /**
     * Trigger batch processing of all due sends right now.
     */
    public function processDue(Request $request): RedirectResponse|JsonResponse
    {
        $stats = $this->journeyService->processDueSends();

        $message = "Processed due sends: {$stats['sent']} sent, {$stats['skipped']} skipped, {$stats['failed']} failed (out of {$stats['total_due']} due).";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'stats' => $stats,
                'message' => $message,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Sync delivery statuses from Meta WhatsApp message stream.
     */
    public function syncStatuses(Request $request): RedirectResponse|JsonResponse
    {
        $updated = app(\App\Services\WhatsApp\MetaDeliveryStatusSyncService::class)->syncJourneyDeliveries();

        $message = "Synced WhatsApp delivery statuses. {$updated} journey message(s) updated.";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'updated_count' => $updated,
                'message' => $message,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }
}
