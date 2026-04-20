<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Models\CoinClaimRequest;
use App\Models\Event;
use App\Models\Impact;
use App\Models\Industry;
use App\Models\LeaderInterestSubmission;
use App\Models\Payment;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminExecutionController extends Controller
{
    public function leadership(Request $request)
    {
        $applications = LeaderInterestSubmission::query()->latest('created_at')->paginate(20, ['*'], 'applications_page');

        $assignments = DB::table('circle_members as cm')
            ->join('users', 'users.id', '=', 'cm.user_id')
            ->join('circles', 'circles.id', '=', 'cm.circle_id')
            ->whereIn(DB::raw('cm.role::text'), ['founder', 'director', 'chair', 'vice_chair', 'secretary', 'committee_leader'])
            ->selectRaw('cm.id, cm.user_id, cm.circle_id, cm.role::text as role, cm.status, users.display_name, circles.name as circle_name, cm.created_at')
            ->orderByDesc('cm.created_at')
            ->paginate(20, ['*'], 'assignments_page');

        $performance = DB::table('circle_members as cm')
            ->leftJoin('impacts', 'impacts.user_id', '=', 'cm.user_id')
            ->join('users', 'users.id', '=', 'cm.user_id')
            ->whereIn(DB::raw('cm.role::text'), ['founder', 'director', 'chair', 'vice_chair', 'secretary', 'committee_leader'])
            ->selectRaw('cm.user_id, users.display_name, cm.role::text as role, COUNT(impacts.id) as impact_score')
            ->groupBy('cm.user_id', 'users.display_name', DB::raw('cm.role::text'))
            ->orderByDesc('impact_score')
            ->limit(20)
            ->get();

        return view('admin/execution/leadership', compact('applications', 'assignments', 'performance'));
    }

    public function industries()
    {
        $industries = Industry::query()->withCount('circles')->paginate(20);

        return view('admin/execution/industries', compact('industries'));
    }

    public function events()
    {
        $events = Event::query()->latest('start_at')->paginate(20);
        $eventRows = $events->getCollection()->map(function ($event) {
            $revenue = (float) DB::table('event_sponsors')->where('event_id', $event->id)->sum('amount');
            $expense = (float) DB::table('event_expenses')->where('event_id', $event->id)->sum('amount');

            return [
                'event' => $event,
                'revenue' => $revenue,
                'expense' => $expense,
                'net' => $revenue - $expense,
            ];
        });

        return view('admin/execution/events', compact('events', 'eventRows'));
    }

    public function finance(Request $request)
    {
        $amountColumn = $this->resolvePaymentAmountColumn();
        $categoryColumn = $this->resolvePaymentCategoryColumn();
        $successStatuses = $this->resolvePaidStatuses();

        $payments = Payment::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($categoryColumn && $request->filled('source'), fn ($q) => $q->where($categoryColumn, $request->string('source')))
            ->latest('created_at')
            ->paginate(20);

        $payments->setCollection($payments->getCollection()->map(function (Payment $payment) use ($amountColumn, $categoryColumn) {
            $payment->display_amount = (float) ($payment->{$amountColumn} ?? 0);
            $payment->display_source = $categoryColumn ? (string) ($payment->{$categoryColumn} ?? '-') : null;

            return $payment;
        }));

        $baseRevenueQuery = Payment::query()->whereIn('status', $successStatuses);

        $summary = [
            'membership' => $categoryColumn ? (float) (clone $baseRevenueQuery)->where($categoryColumn, 'membership')->sum($amountColumn) : null,
            'circle_fee' => $categoryColumn ? (float) (clone $baseRevenueQuery)->where($categoryColumn, 'circle_fee')->sum($amountColumn) : null,
            'event' => $categoryColumn ? (float) (clone $baseRevenueQuery)->where($categoryColumn, 'event')->sum($amountColumn) : null,
            'sponsor' => $categoryColumn ? (float) (clone $baseRevenueQuery)->where($categoryColumn, 'sponsor')->sum($amountColumn) : null,
            'total' => (float) (clone $baseRevenueQuery)->sum($amountColumn),
            'supports_source_split' => (bool) $categoryColumn,
            'source_column' => $categoryColumn,
            'amount_column' => $amountColumn,
        ];

        $subscriptions = User::query()->whereNotNull('zoho_subscription_id')->select('id', 'display_name', 'zoho_subscription_id', 'zoho_plan_code', 'membership_status')->limit(50)->get();

        return view('admin/execution/finance', compact('payments', 'summary', 'subscriptions'));
    }

    public function communications()
    {
        $templates = DB::table('communication_templates')->latest('updated_at')->paginate(20, ['*'], 'templates_page');
        $broadcasts = DB::table('broadcast_messages')->latest('created_at')->paginate(20, ['*'], 'broadcast_page');
        $emailLogs = DB::table('email_logs')->latest('created_at')->limit(25)->get();

        return view('admin/execution/communications', compact('templates', 'broadcasts', 'emailLogs'));
    }

    public function sendBroadcast(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'channel' => ['nullable', 'string', 'max:50'],
        ]);

        DB::table('broadcast_messages')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'title' => $validated['title'],
            'message' => $validated['message'],
            'channel' => $validated['channel'] ?? 'in_app',
            'created_by' => auth('admin')->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.execution.communications')->with('status', 'Broadcast message queued successfully.');
    }

    public function meetings(Request $request)
    {
        $meetings = DB::table('circle_meetings as cm')
            ->leftJoin('circles', 'circles.id', '=', 'cm.circle_id')
            ->select('cm.*', 'circles.name as circle_name')
            ->orderByDesc('cm.meeting_date')
            ->paginate(20);

        $warnings = DB::table('absence_warnings')->latest('created_at')->paginate(20, ['*'], 'warnings_page');

        return view('admin/execution/meetings', compact('meetings', 'warnings'));
    }

    public function reports()
    {
        $amountColumn = $this->resolvePaymentAmountColumn();
        $successStatuses = $this->resolvePaidStatuses();

        $reportCards = [
            'users' => User::query()->count(),
            'circles' => Circle::query()->count(),
            'industries' => Industry::query()->count(),
            'pending_join_requests' => CircleJoinRequest::query()->whereIn('status', ['pending_cd_approval', 'pending_id_approval', 'pending_circle_fee'])->count(),
            'pending_impacts' => Impact::query()->where('status', 'pending')->count(),
            'pending_coin_claims' => CoinClaimRequest::query()->where('status', 'pending')->count(),
            'post_reports' => PostReport::query()->count(),
            'posts' => Post::query()->count(),
            'revenue' => (float) Payment::query()->whereIn('status', $successStatuses)->sum($amountColumn),
            'events' => Event::query()->count(),
        ];

        $latestPayments = Payment::query()->latest('created_at')->limit(15)->get();
        $categoryColumn = $this->resolvePaymentCategoryColumn();
        $latestPayments->transform(function (Payment $payment) use ($amountColumn, $categoryColumn) {
            $payment->display_amount = (float) ($payment->{$amountColumn} ?? 0);
            $payment->display_source = $categoryColumn ? (string) ($payment->{$categoryColumn} ?? '-') : null;

            return $payment;
        });

        return view('admin/execution/reports', compact('reportCards', 'latestPayments'));
    }

    private function resolvePaymentAmountColumn(): string
    {
        foreach (['total_amount', 'amount', 'base_amount'] as $column) {
            if (Schema::hasColumn('payments', $column)) {
                return $column;
            }
        }

        return 'total_amount';
    }

    private function resolvePaymentCategoryColumn(): ?string
    {
        foreach (['source', 'type', 'payment_type', 'category', 'transaction_type', 'purpose'] as $column) {
            if (Schema::hasColumn('payments', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function resolvePaidStatuses(): array
    {
        $statuses = [];
        $distinct = DB::table('payments')->select('status')->whereNotNull('status')->distinct()->pluck('status')->map(fn ($s) => strtolower((string) $s))->all();

        foreach (['success', 'paid', 'completed'] as $candidate) {
            if (in_array($candidate, $distinct, true)) {
                $statuses[] = $candidate;
            }
        }

        return $statuses !== [] ? $statuses : ['success'];
    }
}
