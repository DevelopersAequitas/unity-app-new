<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\MemberAwardSend;
use App\Models\User;
use App\Services\WhatsApp\AwardRecognitionWhatsappService;
use App\Services\WhatsApp\MetaDeliveryStatusSyncService;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class AwardRecognitionAdminController extends Controller
{
    public function __construct(
        protected AwardRecognitionWhatsappService $awardService,
        protected MetaWhatsAppService $metaService,
        protected MetaDeliveryStatusSyncService $syncService
    ) {}

    /**
     * Display listing of Award Recognitions WhatsApp:
     * - Peers overview (default, like 30-Day Journey with timeline links)
     * - Deliveries stream log
     */
    public function index(Request $request): View|JsonResponse
    {
        $tab = $request->query('tab', 'peers');
        $search = trim((string) $request->query('search', ''));
        $track = strtoupper(trim((string) $request->query('track', '')));
        $status = strtolower(trim((string) $request->query('status', '')));
        $peerFilter = trim((string) $request->query('peer_filter', 'all'));

        $stats = [
            'total_peers' => User::count(),
            'total_awards' => MemberAwardSend::count(),
            'total_growth' => MemberAwardSend::where('award_track', 'GROWTH')->count(),
            'total_impact' => MemberAwardSend::where('award_track', 'LIFE_IMPACT')->count(),
            'total_sent' => MemberAwardSend::whereIn('status', ['sent', 'delivered', 'read'])->count(),
            'total_delivered' => MemberAwardSend::whereIn('status', ['delivered', 'read'])->count(),
            'total_read' => MemberAwardSend::where('status', 'read')->count(),
            'total_failed' => MemberAwardSend::where('status', 'failed')->count(),
        ];

        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        // 1. Query for Peers list (Members and their Award status)
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
                'memberAwardSends as total_awards_count',
                'memberAwardSends as total_growth_count' => function ($q) {
                    $q->where('award_track', 'GROWTH');
                },
                'memberAwardSends as total_impact_count' => function ($q) {
                    $q->where('award_track', 'LIFE_IMPACT');
                },
                'memberAwardSends as total_delivered_count' => function ($q) {
                    $q->whereIn('status', ['delivered', 'read']);
                },
                'memberAwardSends as total_failed_count' => function ($q) {
                    $q->where('status', 'failed');
                },
            ]);

        if ($search !== '') {
            $userQuery->where(function ($q) use ($search, $like) {
                $q->where('first_name', $like, "%{$search}%")
                    ->orWhere('last_name', $like, "%{$search}%")
                    ->orWhere('display_name', $like, "%{$search}%")
                    ->orWhere('email', $like, "%{$search}%")
                    ->orWhere('phone', $like, "%{$search}%");
            });
        }

        if ($peerFilter === 'has_awards') {
            $userQuery->whereHas('memberAwardSends');
        } elseif ($peerFilter === 'no_awards') {
            $userQuery->whereDoesntHave('memberAwardSends');
        } elseif ($peerFilter === 'track1') {
            $userQuery->whereHas('memberAwardSends', fn($q) => $q->where('award_track', 'GROWTH'));
        } elseif ($peerFilter === 'track2') {
            $userQuery->whereHas('memberAwardSends', fn($q) => $q->where('award_track', 'LIFE_IMPACT'));
        }

        $members = $userQuery->orderByDesc('created_at')->paginate(15, ['*'], 'members_page')->withQueryString();

        // 2. Query for Deliveries Log
        $deliveriesQuery = MemberAwardSend::query()->with('user');

        if ($search !== '') {
            $deliveriesQuery->where(function ($q) use ($search, $like) {
                $q->where('phone_number', $like, "%{$search}%")
                    ->orWhere('award_title', $like, "%{$search}%")
                    ->orWhere('template_name', $like, "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search, $like) {
                        $uq->where('first_name', $like, "%{$search}%")
                            ->orWhere('last_name', $like, "%{$search}%")
                            ->orWhere('display_name', $like, "%{$search}%")
                            ->orWhere('email', $like, "%{$search}%")
                            ->orWhere('phone', $like, "%{$search}%");
                    });
            });
        }

        if ($track !== '' && in_array($track, ['GROWTH', 'LIFE_IMPACT'], true)) {
            $deliveriesQuery->where('award_track', $track);
        }

        if ($status !== '') {
            if ($status === 'sent_group') {
                $deliveriesQuery->whereIn('status', ['sent', 'delivered', 'read']);
            } else {
                $deliveriesQuery->where('status', $status);
            }
        }

        $sends = $deliveriesQuery->orderByDesc('created_at')->paginate(20, ['*'], 'sends_page')->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'stats' => $stats,
                'members' => $members,
                'sends' => $sends,
            ]);
        }

        $growthAwards = AwardRecognitionWhatsappService::GROWTH_TRACK;
        $impactAwards = AwardRecognitionWhatsappService::IMPACT_TRACK;

        return view('admin.whatsapp.awards.index', compact(
            'stats',
            'members',
            'sends',
            'tab',
            'search',
            'track',
            'status',
            'peerFilter',
            'growthAwards',
            'impactAwards'
        ));
    }

    /**
     * Show detailed Award Recognition Timeline for an individual peer.
     * Matches the 30-Day Journey timeline UX with 3 sections:
     * 1) All Awards (24)
     * 2) Member Introduction (12)
     * 3) Impact (12)
     */
    public function userTimeline(string $userId): View|JsonResponse
    {
        $user = User::findOrFail($userId);

        // Auto-sync delivery statuses from meta message logs
        try {
            $this->syncService->syncAwardDeliveries($userId);
        } catch (Throwable) {}

        // Fetch all existing sends for this user
        $sends = MemberAwardSend::where('user_id', $userId)->get()->keyBy(function ($item) {
            return "{$item->award_track}_{$item->milestone_count}";
        });

        // 12 Growth Track Milestones (Member Introduction)
        $growthTrack = AwardRecognitionWhatsappService::GROWTH_TRACK;
        // 12 Impact Track Milestones (Life Impact)
        $impactTrack = AwardRecognitionWhatsappService::IMPACT_TRACK;

        $milestones = [];

        // Build Track 1 items
        foreach ($growthTrack as $count => $meta) {
            $key = "GROWTH_{$count}";
            $existing = $sends->get($key);

            $milestones[] = [
                'track' => 'GROWTH',
                'track_label' => 'Member Introduction',
                'track_badge' => 'Track 1: Growth',
                'milestone_count' => $count,
                'milestone_label' => "{$count} Introduced",
                'title' => $meta['title'],
                'template_name' => $meta['template'],
                'language' => $meta['language'],
                'send' => $existing,
                'status' => $existing ? $existing->status : 'not_sent',
                'sent_at' => $existing?->sent_at,
                'delivered_at' => $existing?->delivered_at,
                'read_at' => $existing?->read_at,
                'provider_message_id' => $existing?->provider_message_id,
                'creative_image_url' => $existing?->creative_image_url,
                'error_message' => $existing?->error_message,
            ];
        }

        // Build Track 2 items
        foreach ($impactTrack as $count => $meta) {
            $key = "LIFE_IMPACT_{$count}";
            $existing = $sends->get($key);

            $milestones[] = [
                'track' => 'LIFE_IMPACT',
                'track_label' => 'Life Impact',
                'track_badge' => 'Track 2: Impact',
                'milestone_count' => $count,
                'milestone_label' => number_format($count) . " Impacts",
                'title' => $meta['title'],
                'template_name' => $meta['template'],
                'language' => $meta['language'],
                'send' => $existing,
                'status' => $existing ? $existing->status : 'not_sent',
                'sent_at' => $existing?->sent_at,
                'delivered_at' => $existing?->delivered_at,
                'read_at' => $existing?->read_at,
                'provider_message_id' => $existing?->provider_message_id,
                'creative_image_url' => $existing?->creative_image_url,
                'error_message' => $existing?->error_message,
            ];
        }

        $sentCount = $sends->whereIn('status', ['sent', 'delivered', 'read'])->count();
        $deliveredCount = $sends->whereIn('status', ['delivered', 'read'])->count();
        $failedCount = $sends->where('status', 'failed')->count();

        $rawPhone = trim((string) ($user->phone ?? $user->secondary_mobile ?? ''));
        $userPhone = '';
        if ($rawPhone !== '') {
            $digits = preg_replace('/\D+/', '', $rawPhone) ?? '';
            if (strlen($digits) === 10) {
                $userPhone = '+91' . $digits;
            } elseif (str_starts_with($rawPhone, '+')) {
                $userPhone = '+' . $digits;
            } elseif (strlen($digits) > 10) {
                $userPhone = '+' . $digits;
            } else {
                $userPhone = $rawPhone;
            }
        }

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'user' => $user,
                'user_phone' => $userPhone,
                'milestones' => $milestones,
                'stats' => [
                    'total_available' => 24,
                    'total_sent' => $sentCount,
                    'total_delivered' => $deliveredCount,
                    'total_failed' => $failedCount,
                ],
            ]);
        }

        return view('admin.whatsapp.awards.timeline', compact(
            'user',
            'userPhone',
            'milestones',
            'sentCount',
            'deliveredCount',
            'failedCount'
        ));
    }

    /**
     * Send or test-send a specific milestone award for a user.
     */
    public function sendUserMilestone(Request $request, string $userId): RedirectResponse|JsonResponse
    {
        $user = User::findOrFail($userId);

        $validated = $request->validate([
            'track' => ['required', 'string', 'in:GROWTH,LIFE_IMPACT'],
            'milestone_count' => ['required', 'integer'],
            'override_phone' => ['nullable', 'string'],
        ]);

        $track = $validated['track'];
        $count = (int) $validated['milestone_count'];
        $phoneOverride = $request->filled('override_phone') ? trim((string) $request->input('override_phone')) : null;

        try {
            if ($track === 'GROWTH') {
                $awardInfo = AwardRecognitionWhatsappService::GROWTH_TRACK[$count] ?? null;
                if (! $awardInfo) {
                    throw new \InvalidArgumentException("Invalid Growth milestone: {$count}");
                }
                if ($phoneOverride) {
                    $send = $this->awardService->sendAward(
                        $user,
                        'GROWTH',
                        $awardInfo['title'],
                        $count,
                        $awardInfo['template'],
                        $awardInfo['language'],
                        null,
                        $phoneOverride
                    );
                } else {
                    $send = $this->awardService->handleGrowthAward($user, $count);
                }
            } else {
                $awardInfo = AwardRecognitionWhatsappService::IMPACT_TRACK[$count] ?? null;
                if (! $awardInfo) {
                    throw new \InvalidArgumentException("Invalid Life Impact milestone: {$count}");
                }
                if ($phoneOverride) {
                    $send = $this->awardService->sendAward(
                        $user,
                        'LIFE_IMPACT',
                        $awardInfo['title'],
                        $count,
                        $awardInfo['template'],
                        $awardInfo['language'],
                        null,
                        $phoneOverride
                    );
                } else {
                    $send = $this->awardService->handleImpactAward($user, $count);
                }
            }

            $message = "Award WhatsApp '{$awardInfo['title']}' dispatched successfully to " . ($phoneOverride ?: ($user->phone ?? 'recipient')) . "!";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'send' => $send,
                ]);
            }

            return back()->with('success', $message);
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to dispatch award: ' . $e->getMessage(),
                ], 400);
            }

            return back()->with('error', 'Failed to dispatch award: ' . $e->getMessage());
        }
    }

    /**
     * Sync delivery statuses from Meta WhatsApp message stream.
     */
    public function syncStatuses(Request $request): RedirectResponse|JsonResponse
    {
        $userId = $request->input('user_id') ? trim((string) $request->input('user_id')) : null;
        $updated = $this->syncService->syncAwardDeliveries($userId);

        $message = "Synced WhatsApp delivery statuses. {$updated} award message(s) updated.";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'updated_count' => $updated,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Show award send details modal/view.
     */
    public function show(string $id): JsonResponse|View
    {
        $send = MemberAwardSend::with('user')->findOrFail($id);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $send,
            ]);
        }

        return view('admin.whatsapp.awards.show', compact('send'));
    }

    /**
     * Resend an award WhatsApp template.
     */
    public function resend(string $id): RedirectResponse|JsonResponse
    {
        $send = MemberAwardSend::findOrFail($id);
        $user = $send->user ?: User::find($send->user_id);

        if (! $user) {
            return back()->with('error', 'User record not found for this send.');
        }

        try {
            $newSend = $this->awardService->sendAward(
                $user,
                $send->award_track,
                $send->award_title,
                $send->milestone_count,
                $send->template_name,
                $send->language,
                $send->creative_image_url,
                $send->phone_number
            );

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Award template '{$send->template_name}' resent successfully!",
                    'data' => $newSend,
                ]);
            }

            return back()->with('success', "Award template '{$send->template_name}' resent successfully!");
        } catch (Throwable $e) {
            return back()->with('error', 'Failed to resend: ' . $e->getMessage());
        }
    }

    /**
     * Direct test-send an award template.
     */
    public function sendTest(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:10', 'max:20'],
            'award_type' => ['required', 'string', 'in:growth,impact'],
            'award_key' => ['required', 'integer'],
            'image_url' => ['nullable', 'url'],
            'member_name' => ['nullable', 'string', 'max:100'],
        ]);

        $phone = MetaWhatsAppService::normalizePhone($validated['phone']);
        $memberName = trim($validated['member_name'] ?? 'Test Member');
        if ($memberName === '') {
            $memberName = 'Test Member';
        }

        $track = $validated['award_type'] === 'growth' ? 'GROWTH' : 'LIFE_IMPACT';
        $awardKey = (int) $validated['award_key'];

        $awardInfo = $track === 'GROWTH'
            ? (AwardRecognitionWhatsappService::GROWTH_TRACK[$awardKey] ?? null)
            : (AwardRecognitionWhatsappService::IMPACT_TRACK[$awardKey] ?? null);

        if (! $awardInfo) {
            return back()->with('error', 'Invalid award selected.');
        }

        $dummyUser = User::where('phone', 'like', '%' . substr($phone, -10))->first() ?: new User([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'first_name' => $memberName,
            'last_name' => '',
            'display_name' => $memberName,
            'phone' => $phone,
        ]);

        try {
            $sendRecord = $this->awardService->sendAward(
                $dummyUser,
                $track,
                $awardInfo['title'],
                $awardKey,
                $awardInfo['template'],
                $awardInfo['language'],
                $validated['image_url'] ?? null,
                $phone
            );

            $msg = "Award WhatsApp template '{$awardInfo['template']}' dispatched to {$phone} successfully!";
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'data' => $sendRecord,
                ]);
            }

            return back()->with('success', $msg);
        } catch (Throwable $e) {
            return back()->with('error', 'Test send failed: ' . $e->getMessage());
        }
    }
}
