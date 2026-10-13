<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\MemberAwardSend;
use App\Models\User;
use App\Services\WhatsApp\AwardRecognitionWhatsappService;
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
        protected MetaWhatsAppService $metaService
    ) {}

    /**
     * Display listing of Award Recognitions WhatsApp sends.
     */
    public function index(Request $request): View|JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $track = strtoupper(trim((string) $request->query('track', '')));
        $status = strtolower(trim((string) $request->query('status', '')));

        $stats = [
            'total_awards' => MemberAwardSend::count(),
            'total_growth' => MemberAwardSend::where('award_track', 'GROWTH')->count(),
            'total_impact' => MemberAwardSend::where('award_track', 'LIFE_IMPACT')->count(),
            'total_sent' => MemberAwardSend::whereIn('status', ['sent', 'delivered', 'read'])->count(),
            'total_delivered' => MemberAwardSend::whereIn('status', ['delivered', 'read'])->count(),
            'total_read' => MemberAwardSend::where('status', 'read')->count(),
            'total_failed' => MemberAwardSend::where('status', 'failed')->count(),
        ];

        $query = MemberAwardSend::query()->with('user');

        if ($search !== '') {
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $like) {
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
            $query->where('award_track', $track);
        }

        if ($status !== '') {
            if ($status === 'sent_group') {
                $query->whereIn('status', ['sent', 'delivered', 'read']);
            } else {
                $query->where('status', $status);
            }
        }

        $sends = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'stats' => $stats,
                'data' => $sends,
            ]);
        }

        $growthAwards = AwardRecognitionWhatsappService::GROWTH_TRACK;
        $impactAwards = AwardRecognitionWhatsappService::IMPACT_TRACK;

        return view('admin.whatsapp.awards.index', compact(
            'stats',
            'sends',
            'search',
            'track',
            'status',
            'growthAwards',
            'impactAwards'
        ));
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
