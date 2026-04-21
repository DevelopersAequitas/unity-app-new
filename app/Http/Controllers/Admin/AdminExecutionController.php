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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminExecutionController extends Controller
{
    public function leadership(Request $request)
    {
        $applications = LeaderInterestSubmission::query()
            ->leftJoin('users as applicant', 'applicant.id', '=', 'leader_interest_submissions.user_id')
            ->select([
                'leader_interest_submissions.*',
                DB::raw("COALESCE(NULLIF(applicant.display_name, ''), NULLIF(TRIM(CONCAT_WS(' ', applicant.first_name, applicant.last_name)), ''), applicant.email, '-') as applicant_name"),
                DB::raw("COALESCE(applicant.email, '-') as applicant_email"),
            ])
            ->latest('leader_interest_submissions.created_at')
            ->paginate(20, ['*'], 'applications_page');

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
            ->where(function ($q): void {
                $q->whereNull('impacts.status')->orWhere('impacts.status', 'approved');
            })
            ->selectRaw("cm.user_id, COALESCE(NULLIF(users.display_name, ''), NULLIF(TRIM(CONCAT_WS(' ', users.first_name, users.last_name)), ''), users.email, '-') as display_name, cm.role::text as role, SUM(COALESCE(impacts.life_impacted, 0)) as impact_score")
            ->groupBy('cm.user_id', 'users.display_name', 'users.first_name', 'users.last_name', 'users.email', DB::raw('cm.role::text'))
            ->orderByDesc('impact_score')
            ->limit(20)
            ->get();

        return view('admin/execution/leadership', compact('applications', 'assignments', 'performance'));
    }

    public function industries()
    {
        $industries = Industry::query()->paginate(20);

        $circles = Circle::query()->select('id', 'industry_tags')->whereNull('deleted_at')->get();
        $industries->setCollection($industries->getCollection()->map(function (Industry $industry) use ($circles) {
            $industry->circles_count = $circles->filter(function (Circle $circle) use ($industry): bool {
                return $this->circleMatchesIndustry($circle->industry_tags, (string) $industry->id, (string) $industry->name);
            })->count();

            return $industry;
        }));

        return view('admin/execution/industries', compact('industries'));
    }

    public function events()
    {
        $sponsorTable = $this->resolveFirstExistingTable(['event_sponsors', 'event_sponsorships', 'sponsorships']);
        $expenseTable = $this->resolveFirstExistingTable(['event_expenses', 'expenses']);
        $events = Event::query()->latest('start_at')->paginate(20);
        $eventRows = $events->getCollection()->map(function ($event) use ($sponsorTable, $expenseTable) {
            $revenue = $this->sumEventAmount($sponsorTable, (string) $event->id);
            $expense = $this->sumEventAmount($expenseTable, (string) $event->id);

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
            ->with(['user:id,first_name,last_name,display_name,email'])
            ->when($request->filled('status'), fn ($q) => $q->whereRaw('LOWER(status) = ?', [strtolower($request->string('status')->toString())]))
            ->when($categoryColumn && $request->filled('source'), fn ($q) => $q->where($categoryColumn, $request->string('source')->toString()))
            ->latest('created_at')
            ->paginate(20);

        $payments->setCollection($payments->getCollection()->map(function (Payment $payment) use ($amountColumn, $categoryColumn) {
            $payment->display_amount = (float) ($payment->{$amountColumn} ?? 0);
            $payment->display_source = $categoryColumn ? (string) ($payment->{$categoryColumn} ?? '-') : $this->derivePaymentCategory($payment);
            $payment->display_user = $payment->user?->display_name ?: trim(($payment->user?->first_name ?? '') . ' ' . ($payment->user?->last_name ?? ''));
            if ($payment->display_user === '') {
                $payment->display_user = $payment->user?->email ?: (string) $payment->user_id;
            }

            return $payment;
        }));

        $baseRevenueQuery = Payment::query()->whereRaw('LOWER(status) IN (' . implode(',', array_fill(0, count($successStatuses), '?')) . ')', $successStatuses);
        $categoryTotals = $this->aggregatePaymentCategories((clone $baseRevenueQuery)->get(), $amountColumn, $categoryColumn);

        $summary = [
            'membership' => $categoryTotals['membership'],
            'circle_fee' => $categoryTotals['circle_fee'],
            'event' => $categoryTotals['event'],
            'sponsor' => $categoryTotals['sponsor'],
            'total' => (float) (clone $baseRevenueQuery)->sum($amountColumn),
            'supports_source_split' => true,
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
        $mailFailureSummary = [
            'failed_emails' => $emailLogs->where('status', 'failed')->count(),
            'failed_jobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->where('payload', 'ILIKE', '%Send%Mail%')->count() : 0,
        ];

        return view('admin/execution/communications', compact('templates', 'broadcasts', 'emailLogs', 'mailFailureSummary'));
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
        $meetingTable = $this->resolveFirstExistingTable(['circle_meetings', 'meetings']);
        $warningTable = $this->resolveFirstExistingTable(['absence_warnings', 'warnings']);

        $meetings = $meetingTable
            ? DB::table($meetingTable . ' as cm')
                ->leftJoin('circles', 'circles.id', '=', 'cm.circle_id')
                ->select('cm.*', 'circles.name as circle_name')
                ->orderByDesc(Schema::hasColumn($meetingTable, 'meeting_date') ? 'cm.meeting_date' : 'cm.created_at')
                ->paginate(20)
            : $this->emptyPaginator();

        $warnings = $warningTable
            ? DB::table($warningTable . ' as aw')
                ->leftJoin('users', 'users.id', '=', 'aw.user_id')
                ->leftJoin('circles', 'circles.id', '=', 'aw.circle_id')
                ->select('aw.*', DB::raw("COALESCE(NULLIF(users.display_name, ''), users.email, aw.user_id::text) as user_name"), 'circles.name as circle_name')
                ->latest('aw.created_at')
                ->paginate(20, ['*'], 'warnings_page')
            : $this->emptyPaginator(20, 'warnings_page');

        return view('admin/execution/meetings', compact('meetings', 'warnings'));
    }

    public function reports()
    {
        $amountColumn = $this->resolvePaymentAmountColumn();
        $successStatuses = $this->resolvePaidStatuses();

        $baseRevenueQuery = Payment::query()->whereRaw('LOWER(status) IN (' . implode(',', array_fill(0, count($successStatuses), '?')) . ')', $successStatuses);
        $reportCards = [
            'users' => User::query()->count(),
            'circles' => Circle::query()->count(),
            'industries' => Industry::query()->count(),
            'pending_join_requests' => CircleJoinRequest::query()->whereIn('status', ['pending_cd_approval', 'pending_id_approval', 'pending_circle_fee'])->count(),
            'pending_impacts' => Impact::query()->where('status', 'pending')->count(),
            'pending_coin_claims' => CoinClaimRequest::query()->where('status', 'pending')->count(),
            'post_reports' => PostReport::query()->count(),
            'posts' => Post::query()->count(),
            'revenue' => (float) (clone $baseRevenueQuery)->sum($amountColumn),
            'events' => Event::query()->count(),
        ];

        $latestPayments = Payment::query()->with(['user:id,first_name,last_name,display_name,email'])->latest('created_at')->limit(15)->get();
        $categoryColumn = $this->resolvePaymentCategoryColumn();
        $latestPayments->transform(function (Payment $payment) use ($amountColumn, $categoryColumn) {
            $payment->display_amount = (float) ($payment->{$amountColumn} ?? 0);
            $payment->display_source = $categoryColumn ? (string) ($payment->{$categoryColumn} ?? '-') : $this->derivePaymentCategory($payment);
            $payment->display_user = $payment->user?->display_name ?: trim(($payment->user?->first_name ?? '') . ' ' . ($payment->user?->last_name ?? ''));
            if ($payment->display_user === '') {
                $payment->display_user = $payment->user?->email ?: (string) $payment->user_id;
            }

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

    private function derivePaymentCategory(Payment $payment): string
    {
        if (! empty($payment->membership_plan_id)) {
            return 'membership';
        }

        return 'uncategorized';
    }

    private function aggregatePaymentCategories(Collection $payments, string $amountColumn, ?string $categoryColumn): array
    {
        $totals = ['membership' => 0.0, 'circle_fee' => 0.0, 'event' => 0.0, 'sponsor' => 0.0];
        foreach ($payments as $payment) {
            $category = $categoryColumn ? (string) ($payment->{$categoryColumn} ?? '') : $this->derivePaymentCategory($payment);
            if (! array_key_exists($category, $totals)) {
                continue;
            }
            $totals[$category] += (float) ($payment->{$amountColumn} ?? 0);
        }

        return $totals;
    }

    private function resolveFirstExistingTable(array $candidates): ?string
    {
        foreach ($candidates as $table) {
            if (Schema::hasTable($table)) {
                return $table;
            }
        }

        return null;
    }

    private function sumEventAmount(?string $table, string $eventId): float
    {
        if (! $table) {
            return $this->sumEventPayments($eventId);
        }

        $query = DB::table($table);
        if (Schema::hasColumn($table, 'event_id')) {
            $query->where('event_id', $eventId);
        } elseif (Schema::hasColumn($table, 'related_id')) {
            $query->where('related_id', $eventId);
        } else {
            return $this->sumEventPayments($eventId);
        }

        foreach (['amount', 'total_amount', 'value', 'sponsor_amount', 'expense_amount'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                $value = (float) $query->sum($column);
                if ($value > 0) {
                    return $value;
                }
            }
        }

        return $this->sumEventPayments($eventId);
    }

    private function emptyPaginator(int $perPage = 20, string $pageName = 'page'): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, 1, ['pageName' => $pageName]);
    }

    private function sumEventPayments(string $eventId): float
    {
        if (! Schema::hasColumn('payments', 'event_id')) {
            return 0.0;
        }

        return (float) Payment::query()
            ->where('event_id', $eventId)
            ->whereRaw('LOWER(status) IN (' . implode(',', array_fill(0, count($this->resolvePaidStatuses()), '?')) . ')', $this->resolvePaidStatuses())
            ->sum($this->resolvePaymentAmountColumn());
    }

    private function circleMatchesIndustry(mixed $industryTags, string $industryId, string $industryName): bool
    {
        $raw = is_array($industryTags) ? $industryTags : (is_string($industryTags) ? json_decode($industryTags, true) : []);
        $raw = is_array($raw) ? $raw : [];
        $values = collect($raw)->flatMap(function ($tag) {
            if (is_array($tag)) {
                return array_filter([
                    (string) ($tag['id'] ?? ''),
                    (string) ($tag['uuid'] ?? ''),
                    (string) ($tag['value'] ?? ''),
                    (string) ($tag['name'] ?? ''),
                    (string) ($tag['label'] ?? ''),
                ]);
            }

            return [(string) $tag];
        })->map(fn ($v) => strtolower(trim((string) $v)))->filter()->unique()->values()->all();

        $id = strtolower(trim($industryId));
        $name = strtolower(trim($industryName));

        return in_array($id, $values, true) || ($name !== '' && in_array($name, $values, true));
    }
}
