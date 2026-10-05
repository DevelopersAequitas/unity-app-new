<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Activity;
use App\Models\ActivityAudit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActivityController extends BaseApiController
{
    /**
     * Display a paginated listing of system-wide peer activities with multi-criteria filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Activity::query()
            ->with([
                'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url',
                'relatedUser:id,first_name,last_name,display_name,email,company_name,profile_photo_url',
                'circle:id,name',
                'event:id,title',
                'verifiedByAdmin:id,first_name,last_name,display_name,email',
            ]);

        // Filter by activity type
        if ($type = $request->query('type')) {
            if ($type !== 'all') {
                $query->where('type', $type);
            }
        }

        // Filter by status (pending, approved, rejected)
        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $normalizedStatus = ($status === 'verified') ? 'approved' : $status;
                $query->where('status', $normalizedStatus);
            }
        }

        // Filter by circle
        if ($circleId = $request->query('circle_id')) {
            if ($circleId !== 'all') {
                $query->where('circle_id', $circleId);
            }
        }

        // Filter by user/peer
        if ($userId = $request->query('user_id')) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhere('related_user_id', $userId);
            });
        }

        // Filter by date range
        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        // Debounced search query
        if ($search = $request->query('search')) {
            $search = trim((string) $search);
            $query->where(function ($q) use ($search) {
                $q->where('description', 'ILIKE', "%{$search}%")
                  ->orWhere('admin_notes', 'ILIKE', "%{$search}%")
                  ->orWhere('type', 'ILIKE', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('display_name', 'ILIKE', "%{$search}%")
                         ->orWhere('first_name', 'ILIKE', "%{$search}%")
                         ->orWhere('last_name', 'ILIKE', "%{$search}%")
                         ->orWhere('email', 'ILIKE', "%{$search}%")
                         ->orWhere('company_name', 'ILIKE', "%{$search}%");
                  })
                  ->orWhereHas('relatedUser', function ($ruq) use ($search) {
                      $ruq->where('display_name', 'ILIKE', "%{$search}%")
                          ->orWhere('first_name', 'ILIKE', "%{$search}%")
                          ->orWhere('last_name', 'ILIKE', "%{$search}%")
                          ->orWhere('email', 'ILIKE', "%{$search}%");
                  });
            });
        }

        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->success([
            'items' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Aggregated statistics for peer activities.
     */
    public function stats(Request $request): JsonResponse
    {
        $total = Activity::count();
        $pending = Activity::where('status', 'pending')
            ->orWhere(function ($q) {
                $q->where('requires_verification', true)
                  ->whereNull('verified_at');
            })->count();
        $verified = Activity::whereNotNull('verified_at')
            ->orWhere('status', 'approved')->count();
        $today = Activity::whereDate('created_at', Carbon::today())->count();

        // Breakdown by activity type
        $byType = Activity::select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        return $this->success([
            'total' => $total,
            'pending' => $pending,
            'verified' => $verified,
            'today' => $today,
            'by_type' => $byType,
        ]);
    }

    /**
     * Show single activity details with all related context.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $activity = Activity::with([
            'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,phone,designation',
            'relatedUser:id,first_name,last_name,display_name,email,company_name,profile_photo_url,phone,designation',
            'circle:id,name',
            'event:id,title',
            'verifiedByAdmin:id,first_name,last_name,display_name,email',
            'coinLedger',
        ])->find($id);

        if (! $activity) {
            return $this->error('Activity record not found', 404);
        }

        return $this->success($activity);
    }

    /**
     * Update activity verification status / admin notes.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:verified,approved,rejected,pending'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
            'coins_awarded' => ['nullable', 'integer', 'min:0'],
        ]);

        $activity = Activity::find($id);
        if (! $activity) {
            return $this->error('Activity record not found', 404);
        }

        $admin = $request->user();
        $normalizedStatus = ($validated['status'] === 'verified') ? 'approved' : $validated['status'];

        DB::transaction(function () use ($activity, $validated, $normalizedStatus, $admin) {
            $activity->status = $normalizedStatus;
            if (isset($validated['admin_notes'])) {
                $activity->admin_notes = $validated['admin_notes'];
            }
            if (isset($validated['coins_awarded'])) {
                $activity->coins_awarded = $validated['coins_awarded'];
            }

            if ($normalizedStatus === 'approved') {
                $activity->verified_at = now();
                $activity->verified_by_admin_id = $admin?->id;
            } elseif ($normalizedStatus === 'rejected') {
                $activity->verified_at = now();
                $activity->verified_by_admin_id = $admin?->id;
            }

            $activity->save();

            // Record audit log if model exists
            if (class_exists(ActivityAudit::class)) {
                ActivityAudit::create([
                    'id' => (string) Str::uuid(),
                    'activity_id' => $activity->id,
                    'actor_admin_id' => $admin?->id,
                    'action' => 'status_updated_to_'.$normalizedStatus,
                    'notes' => $validated['admin_notes'] ?? null,
                    'created_at' => now(),
                ]);
            }
        });

        $activity->load(['user', 'relatedUser', 'circle', 'event', 'verifiedByAdmin']);

        return $this->success($activity, 'Activity status updated successfully.');
    }

    /**
     * Get list of standard activity types for UI filtering.
     */
    public function types(): JsonResponse
    {
        $types = [
            ['key' => 'attend_circle_meeting', 'label' => 'Circle Meeting Check-in', 'icon' => 'MapPin', 'color' => '#3B82F6'],
            ['key' => 'publish_story_vj', 'label' => 'Life Impact / Story', 'icon' => 'Target', 'color' => '#10B981'],
            ['key' => 'post_ask', 'label' => 'Asks & Leads', 'icon' => 'MessageSquare', 'color' => '#EC4899'],
            ['key' => 'peer_meeting', 'label' => 'P2P Meeting', 'icon' => 'Users', 'color' => '#6366F1'],
            ['key' => 'close_business_deal', 'label' => 'Business Deal', 'icon' => 'Briefcase', 'color' => '#10B981'],
            ['key' => 'pass_referral', 'label' => 'Peer Referral', 'icon' => 'Share2', 'color' => '#F97316'],
            ['key' => 'testimonial', 'label' => 'Testimonial', 'icon' => 'Award', 'color' => '#06B6D4'],
            ['key' => 'join_circle', 'label' => 'Circle Joined', 'icon' => 'Activity', 'color' => '#8B5CF6'],
            ['key' => 'invite_visitor', 'label' => 'Visitor Invited', 'icon' => 'UserPlus', 'color' => '#EAB308'],
            ['key' => 'renew_membership', 'label' => 'Membership Renewal', 'icon' => 'ShieldCheck', 'color' => '#14B8A6'],
        ];

        return $this->success($types);
    }
}