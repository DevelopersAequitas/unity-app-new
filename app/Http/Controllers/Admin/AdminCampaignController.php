<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminCampaign;
use App\Models\CampaignEmailTemplate;
use App\Models\CampaignPamphlet;
use App\Models\CampaignSchedule;
use App\Models\CampaignDelivery;
use App\Models\CampaignLog;
use App\Services\AdminCampaigns\CampaignAudienceImportService;
use App\Services\AdminCampaigns\CampaignEmailTemplateRenderer;
use App\Services\AdminCampaigns\CampaignRecipientResolverService;
use App\Services\AdminCampaigns\CampaignSendService;
use App\Services\AdminCampaigns\CampaignScheduleCalculator;
use App\Jobs\ProcessCampaignDeliveryJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminCampaignController extends Controller
{
    private const CAMPAIGN_TYPES = ['email_only', 'notification_only', 'email_and_notification'];
    private const AUDIENCE_TYPES = ['all_members', 'city', 'circle', 'company', 'category', 'membership_status', 'specific_members', 'custom_filter'];

    public function __construct(
        private readonly CampaignRecipientResolverService $recipientResolver,
        private readonly CampaignSendService $sendService,
        private readonly CampaignAudienceImportService $audienceImportService,
        private readonly CampaignEmailTemplateRenderer $emailTemplateRenderer,
    ) {
    }

    public function index(Request $request): View
    {
        $query = AdminCampaign::query();

        // Apply Search Filter by Title
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', '%' . $search . '%')
                  ->orWhere('title', 'like', '%' . $search . '%');
            });
        }

        // Apply Status Filter
        $status = $request->input('status', 'all');
        if ($status !== 'all' && in_array($status, ['draft', 'scheduled', 'active', 'paused', 'sent', 'failed'], true)) {
            $query->where('status', $status);
        }

        $campaigns = $query->with('schedule')->latest('created_at')->paginate(20)->withQueryString();

        $stats = [
            'total' => AdminCampaign::query()->count(),
            'draft' => AdminCampaign::query()->where('status', 'draft')->count(),
            'sent' => AdminCampaign::query()->where('status', 'sent')->count(),
            'failed' => AdminCampaign::query()->where('status', 'failed')->count(),
            'emails_sent' => AdminCampaign::query()->sum('total_email_sent'),
            'notifications_sent' => AdminCampaign::query()->sum('total_notification_sent'),
        ];

        return view('admin.campaigns.index', compact('campaigns', 'stats', 'status'));
    }


    public function create(): View
    {
        $campaign = new AdminCampaign(['campaign_type' => 'email_only', 'audience_type' => 'all_members', 'filters' => []]);
        $campaign->setRelation('schedule', new CampaignSchedule([
            'schedule_type' => 'immediately',
            'timezone' => 'UTC',
            'start_date' => now()->toDateString(),
            'send_time' => '09:00',
        ]));

        return view('admin.campaigns.form', [
            'campaign' => $campaign,
            'filterOptions' => $this->recipientResolver->filterOptions(),
            'mode' => 'create',
            'emailTemplates' => $this->emailTemplates(),
            'defaultEmailTemplate' => $this->emailTemplateRenderer->defaultTemplate(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Validate schedule first to prevent partial creation bugs
        $scheduleInput = $this->validateScheduleData($request);
        $data = $this->validatedCampaignData($request);

        $action = $request->input('action', 'draft');
        $scheduleType = $request->input('schedule.schedule_type', 'immediately');

        if ($action === 'send') {
            if ($scheduleType === 'immediately') {
                $data['status'] = 'queued';
            } else {
                $data['status'] = 'scheduled';
            }
        } else {
            $data['status'] = AdminCampaign::STATUS_DRAFT;
        }

        $data['created_by'] = optional($request->user('admin'))->id;
        $data['updated_by'] = optional($request->user('admin'))->id;

        $campaign = AdminCampaign::query()->create($data);

        // Save schedule (already validated)
        $this->saveCampaignSchedule($campaign, $scheduleInput, app(CampaignScheduleCalculator::class));

        $this->logCampaignAction($campaign, 'created');

        if ($action === 'send' && $scheduleType === 'immediately') {
            return $this->executeImmediateSend($campaign);
        }

        $msg = ($action === 'send') ? 'Campaign activated and scheduled.' : 'Campaign draft saved.';
        return redirect()->route('admin.campaigns.show', $campaign)->with('success', $msg);
    }

    public function show(AdminCampaign $campaign): View
    {
        \Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->authorize('view', $campaign);

        $campaign->load(['emailTemplate', 'schedule', 'creator', 'deliveries' => function ($query) {
            $query->orderBy('scheduled_at', 'asc');
        }]);

        $deliveryIds = $campaign->deliveries->pluck('id')->all();
        if (!empty($deliveryIds)) {
            $recipients = CampaignLog::with(['user', 'delivery'])
                ->whereIn('delivery_id', $deliveryIds)
                ->latest('created_at')
                ->paginate(50);
        } else {
            // Calculate recipient count on the fly for preview/status page prior to execution
            $campaign->total_recipients = $this->recipientResolver->count(
                $campaign->audience_type,
                $campaign->filters,
                $campaign->includesEmail()
            );

            // Resolve target audience on the fly and map to pending virtual logs
            $query = $this->recipientResolver->query(
                $campaign->audience_type,
                $campaign->filters,
                $campaign->includesEmail()
            );

            $paginator = $query->paginate(50);
            $paginator->getCollection()->transform(function ($user) use ($campaign) {
                $recipient = new \stdClass();
                $recipient->id = $user->id;
                $recipient->user_id = $user->id;
                $recipient->user = $user;
                $recipient->email = $user->email;
                $recipient->email_status = 'pending';
                $recipient->notification_status = 'pending';
                $recipient->error_message = null;
                $recipient->sent_at = null;
                $recipient->scheduled_at = $campaign->schedule?->next_run_at;
                return $recipient;
            });
            $recipients = $paginator;
        }

        $filterSummary = $this->recipientResolver->describeFilters($campaign->filters);

        // Compile Recurring Execution History
        $executionHistory = [];
        $runIndex = 1;

        foreach ($campaign->deliveries as $del) {
            $executionHistory[] = [
                'run_number' => 'Run #' . $runIndex++,
                'scheduled_time' => $campaign->formatTimestamp($del->scheduled_at),
                'actual_time' => $del->started_at ? $campaign->formatTimestamp($del->started_at) : '-',
                'status' => $del->status === 'sent' || $del->status === 'completed' ? 'Success' : \Illuminate\Support\Str::headline($del->status),
                'emails_sent' => $del->total_email_sent,
                'notifications_sent' => $del->total_notification_sent,
                'failed' => $del->total_failed,
                'triggered_by' => $campaign->schedule && $campaign->schedule->schedule_type === 'immediately' ? ($campaign->creator?->name ?? 'Admin') : 'Scheduler',
            ];
        }

        // Calculate upcoming executions if the campaign is recurring/once and not completed/stopped/deleted
        if ($campaign->schedule && $campaign->schedule->schedule_type !== 'immediately' && \in_array($campaign->status, ['active', 'scheduled'])) {
            $calculator = app(CampaignScheduleCalculator::class);
            $nextRun1 = $campaign->schedule->next_run_at;
            if ($nextRun1) {
                $executionHistory[] = [
                    'run_number' => 'Run #' . $runIndex++,
                    'scheduled_time' => $campaign->formatTimestamp($nextRun1),
                    'actual_time' => 'Pending',
                    'status' => 'Pending',
                    'emails_sent' => '-',
                    'notifications_sent' => '-',
                    'failed' => '-',
                    'triggered_by' => 'Scheduler',
                ];

                // Calculate the one after that if it is recurring
                if ($campaign->schedule->schedule_type === 'recurring') {
                    $nextRun2 = $calculator->calculateNextRunAt($campaign->schedule, $nextRun1);
                    if ($nextRun2) {
                        $executionHistory[] = [
                            'run_number' => 'Run #' . $runIndex++,
                            'scheduled_time' => $campaign->formatTimestamp($nextRun2),
                            'actual_time' => 'Pending',
                            'status' => 'Pending',
                            'emails_sent' => '-',
                            'notifications_sent' => '-',
                            'failed' => '-',
                            'triggered_by' => 'Scheduler',
                        ];
                    }
                }
            }
        }

        // Reverse execution history to show latest runs first
        $executionHistory = \array_reverse($executionHistory);

        return view('admin.campaigns.show', compact('campaign', 'recipients', 'filterSummary', 'executionHistory'));
    }

    public function edit(AdminCampaign $campaign): View|RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->authorize('update', $campaign);

        if (! $campaign->isEditable()) {
            return redirect()->route('admin.campaigns.show', $campaign)->with('error', 'Sent campaigns cannot be edited.');
        }

        $campaign->load('schedule');
        if (!$campaign->schedule) {
            $campaign->setRelation('schedule', new CampaignSchedule([
                'schedule_type' => 'immediately',
                'timezone' => 'UTC',
                'start_date' => now()->toDateString(),
                'send_time' => '09:00',
            ]));
        }

        return view('admin.campaigns.form', [
            'campaign' => $campaign,
            'filterOptions' => $this->recipientResolver->filterOptions(),
            'mode' => 'edit',
            'emailTemplates' => $this->emailTemplates(),
            'defaultEmailTemplate' => $this->emailTemplateRenderer->defaultTemplate(),
        ]);
    }

    public function update(Request $request, AdminCampaign $campaign): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::forUser($request->user('admin'))->authorize('update', $campaign);

        if (! $campaign->isEditable()) {
            return redirect()->route('admin.campaigns.show', $campaign)->with('error', 'Sent campaigns cannot be edited.');
        }

        // Validate schedule first to prevent partial update bugs
        $scheduleInput = $this->validateScheduleData($request);
        $data = $this->validatedCampaignData($request);

        $action = $request->input('action', 'draft');
        $scheduleType = $request->input('schedule.schedule_type', 'immediately');

        if ($action === 'send') {
            if ($scheduleType === 'immediately') {
                $data['status'] = 'queued';
            } else {
                // If it was already active, we can keep it active, otherwise scheduled
                $data['status'] = in_array($campaign->status, ['active', 'scheduled']) ? $campaign->status : 'scheduled';
            }
        } else {
            $data['status'] = AdminCampaign::STATUS_DRAFT;
        }

        $data['updated_by'] = optional($request->user('admin'))->id;
        $campaign->update($data);

        // Save schedule (already validated)
        $this->saveCampaignSchedule($campaign, $scheduleInput, app(CampaignScheduleCalculator::class));

        $this->logCampaignAction($campaign, 'edited');

        if ($action === 'send' && $scheduleType === 'immediately') {
            return $this->executeImmediateSend($campaign->refresh());
        }

        $msg = ($action === 'send') ? 'Campaign activated and scheduled.' : 'Campaign draft updated.';
        return redirect()->route('admin.campaigns.show', $campaign)->with('success', $msg);
    }

    public function previewRecipients(Request $request): JsonResponse
    {
        $data = $request->validate([
            'campaign_type' => ['required', Rule::in(self::CAMPAIGN_TYPES)],
            'audience_type' => ['required', Rule::in(self::AUDIENCE_TYPES)],
            'filters' => ['nullable'],
        ]);

        $filters = $this->normalizeFilters($request);
        $requiresEmail = in_array($data['campaign_type'], ['email_only', 'email_and_notification'], true);
        $recipients = $this->recipientResolver->preview($data['audience_type'], $filters, $requiresEmail);
        $total = $this->recipientResolver->count($data['audience_type'], $filters, $requiresEmail);

        return response()->json([
            'total' => $total,
            'recipients' => $recipients,
            'debug' => [
                'selected_business_category_ids' => $this->recipientResolver->businessCategoryIdsFromFilters($filters),
                'matched_users_count' => $total,
            ],
        ]);
    }

    public function send(AdminCampaign $campaign): RedirectResponse
    {
        if (! $campaign->isEditable()) {
            return redirect()->route('admin.campaigns.show', $campaign)->with('error', 'Sent campaigns cannot be edited.');
        }

        $schedule = $campaign->schedule;
        $scheduleType = $schedule ? $schedule->schedule_type : 'immediately';

        if ($scheduleType === 'immediately') {
            $campaign->update(['status' => 'queued']);
            return $this->executeImmediateSend($campaign);
        }

        $campaign->update(['status' => 'active']);
        
        if ($schedule) {
            $nextRun = app(CampaignScheduleCalculator::class)->calculateNextRunAt($schedule, now());
            $schedule->update(['next_run_at' => $nextRun]);
        }

        return redirect()->route('admin.campaigns.show', $campaign)->with('success', 'Campaign schedule activated successfully.');
    }

    protected function validateScheduleData(Request $request): array
    {
        $scheduleType = $request->input('schedule.schedule_type');

        $rules = [
            'schedule.schedule_type' => ['required', 'string', 'in:immediately,once,recurring'],
        ];

        if ($scheduleType === 'once') {
            $rules = array_merge($rules, [
                'schedule.start_date' => ['required', 'date'],
                'schedule.send_time' => ['required', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
                'schedule.timezone' => ['required', 'string'],
            ]);
        } elseif ($scheduleType === 'recurring') {
            $rules = array_merge($rules, [
                'schedule.start_date' => ['required', 'date'],
                'schedule.send_time' => ['required', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
                'schedule.timezone' => ['required', 'string'],
                'schedule.end_type' => ['required', 'string', 'in:never,date'],
                'schedule.end_date' => ['required_if:schedule.end_type,date', 'nullable', 'date'],
                'schedule.recurrence_type' => ['required', 'string', 'in:daily,weekly,monthly,yearly,custom,cycle'],
                'schedule.frequency_interval' => ['nullable', 'integer', 'min:1'],
            ]);

            $recurrenceType = $request->input('schedule.recurrence_type');
            if ($recurrenceType === 'weekly') {
                $rules['schedule.weekdays'] = ['required', 'array'];
                $rules['schedule.weekdays.*'] = ['string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'];
            } elseif ($recurrenceType === 'monthly') {
                $rules['schedule.monthly_basis'] = ['required', 'string', 'in:date,position'];
                
                $monthlyBasis = $request->input('schedule.monthly_basis');
                if ($monthlyBasis === 'date') {
                    $rules['schedule.monthly_day_of_month'] = ['required', 'integer', 'min:1', 'max:31'];
                } elseif ($monthlyBasis === 'position') {
                    $rules['schedule.monthly_position'] = ['required', 'string', 'in:first,second,third,fourth,last'];
                    $rules['schedule.monthly_day_of_week'] = ['required', 'string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'];
                }
            } elseif ($recurrenceType === 'yearly') {
                $rules['schedule.yearly_month'] = ['required', 'integer', 'min:1', 'max:12'];
                $rules['schedule.yearly_day'] = ['required', 'integer', 'min:1', 'max:31'];
            } elseif ($recurrenceType === 'custom') {
                $rules['schedule.custom_unit'] = ['required', 'string', 'in:day,week,month,year'];
            } elseif ($recurrenceType === 'cycle') {
                $rules['schedule.cycle_send_days'] = ['required', 'integer', 'min:1'];
                $rules['schedule.cycle_pause_days'] = ['required', 'integer', 'min:0'];
            }
        }

        return $request->validate($rules);
    }

    protected function saveCampaignSchedule(AdminCampaign $campaign, array $scheduleInput, CampaignScheduleCalculator $calculator): void
    {
        $scheduleData = $scheduleInput['schedule'] ?? [];
        $scheduleType = $scheduleData['schedule_type'] ?? 'immediately';

        $weekdays = isset($scheduleData['weekdays']) ? implode(',', $scheduleData['weekdays']) : null;

        if ($scheduleType === 'immediately') {
            $scheduleData['start_date'] = now()->toDateString();
            $scheduleData['send_time'] = now()->toTimeString();
            $scheduleData['timezone'] = 'UTC';
        }

        $schedule = $campaign->schedule ?: new CampaignSchedule();
        $schedule->fill([
            'campaign_id' => $campaign->id,
            'schedule_type' => $scheduleType,
            'start_date' => $scheduleData['start_date'] ?? now()->toDateString(),
            'end_type' => $scheduleData['end_type'] ?? 'never',
            'end_date' => $scheduleData['end_date'] ?? null,
            'send_time' => $scheduleData['send_time'] ?? now()->toTimeString(),
            'timezone' => $scheduleData['timezone'] ?? 'UTC',
            'recurrence_type' => $scheduleData['recurrence_type'] ?? null,
            'frequency_interval' => isset($scheduleData['frequency_interval']) ? (int)$scheduleData['frequency_interval'] : null,
            'weekdays' => $weekdays,
            'monthly_basis' => $scheduleData['monthly_basis'] ?? null,
            'monthly_day_of_month' => isset($scheduleData['monthly_day_of_month']) ? (int)$scheduleData['monthly_day_of_month'] : null,
            'monthly_position' => $scheduleData['monthly_position'] ?? null,
            'monthly_day_of_week' => $scheduleData['monthly_day_of_week'] ?? null,
            'yearly_month' => isset($scheduleData['yearly_month']) ? (int)$scheduleData['yearly_month'] : null,
            'yearly_day' => isset($scheduleData['yearly_day']) ? (int)$scheduleData['yearly_day'] : null,
            'custom_unit' => $scheduleData['custom_unit'] ?? null,
            'cycle_send_days' => isset($scheduleData['cycle_send_days']) ? (int)$scheduleData['cycle_send_days'] : null,
            'cycle_pause_days' => isset($scheduleData['cycle_pause_days']) ? (int)$scheduleData['cycle_pause_days'] : null,
        ]);

        if (in_array($campaign->status, ['active', 'scheduled', 'sent', 'queued'], true)) {
            if ($scheduleType === 'immediately') {
                $schedule->next_run_at = now();
            } else {
                $schedule->next_run_at = $calculator->calculateNextRunAt($schedule, now());
            }
        } else {
            $schedule->next_run_at = null;
        }

        $campaign->schedule()->save($schedule);
    }

    protected function executeImmediateSend(AdminCampaign $campaign): RedirectResponse
    {
        try {
            $delivery = CampaignDelivery::create([
                'campaign_id' => $campaign->id,
                'schedule_id' => optional($campaign->schedule)->id,
                'status' => 'scheduled',
                'scheduled_at' => now(),
            ]);

            ProcessCampaignDeliveryJob::dispatch($delivery->id);

            return redirect()->route('admin.campaigns.show', $campaign)->with('success', 'Campaign has been queued for background sending.');
        } catch (\Exception $exception) {
            return redirect()->route('admin.campaigns.show', $campaign)->with('error', $exception->getMessage());
        }
    }

    public function filterOptions(): JsonResponse
    {
        return response()->json($this->recipientResolver->filterOptions());
    }

    public function memberSearch(Request $request): JsonResponse
    {
        return response()->json(['items' => $this->recipientResolver->searchMembers((string) $request->query('search', ''))]);
    }

    public function importAudience(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:csv,txt,xlsx,xls'],
            'audience_type' => ['required', Rule::in(self::AUDIENCE_TYPES)],
        ]);

        try {
            $import = $this->audienceImportService->import($data['file'], $data['audience_type']);
        } catch (RuntimeException|\InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $this->importMessage($data['audience_type'], $import['count']),
            'data' => $import,
        ]);
    }

    public function downloadAudienceSample(string $audienceType): Response
    {
        abort_unless(array_key_exists($audienceType, $this->sampleColumns()), 404);

        $columns = $this->sampleColumns()[$audienceType];
        $rows = [
            $columns,
            $this->sampleValues($audienceType, $columns, 0),
            $this->sampleValues($audienceType, $columns, 1),
            $this->sampleValues($audienceType, $columns, 2),
        ];

        $csv = collect($rows)->map(fn (array $row): string => collect($row)->map(fn (string $value): string => '"' . str_replace('"', '""', $value) . '"')->implode(','))->implode("\n");

        return response($csv . "\n", 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="campaign-' . str_replace('_', '-', $audienceType) . '-sample.csv"',
        ]);
    }

    private function emailTemplates(): array
    {
        return CampaignEmailTemplate::query()
            ->where('status', CampaignEmailTemplate::STATUS_ACTIVE)
            ->orderByRaw("CASE slug WHEN 'blank-template' THEN 1 WHEN 'simple-text' THEN 2 WHEN '1-column' THEN 3 WHEN '1-2-1-2-column' THEN 4 WHEN '1-2-column' THEN 5 WHEN '1-2-column-alternate' THEN 6 WHEN '1-3-column' THEN 7 ELSE 99 END")
            ->orderBy('name')
            ->get()
            ->map(fn (CampaignEmailTemplate $template): array => $template->snapshot())
            ->values()
            ->all();
    }

    private function importMessage(string $audienceType, int $count): string
    {
        $label = match ($audienceType) {
            'city' => 'cities',
            'company' => 'companies',
            'membership_status' => 'membership statuses',
            'specific_members' => 'members',
            'category' => 'business categories',
            'circle' => 'circles',
            'custom_filter' => 'audience values',
            default => 'audience values',
        };

        return $count . ' ' . $label . ' imported successfully.';
    }

    private function sampleColumns(): array
    {
        return [
            'city' => ['city'],
            'company' => ['company_name'],
            'membership_status' => ['membership_status'],
            'specific_members' => ['email', 'phone', 'id'],
            'category' => ['business_category'],
            'circle' => ['circle_name'],
            'custom_filter' => ['city', 'company_name', 'membership_status', 'email', 'business_category', 'circle_name'],
        ];
    }

    private function sampleValues(string $audienceType, array $columns, int $index): array
    {
        $samples = [
            'city' => [['Ahmedabad'], ['Pune'], ['Mumbai']],
            'company' => [['Acme Industries'], ['Unity Ventures'], ['Growth Labs']],
            'membership_status' => [['active'], ['trial'], ['expired']],
            'specific_members' => [['member1@example.com', '9876543210', ''], ['member2@example.com', '', ''], ['', '9876500000', '']],
            'category' => [['Manufacturing'], ['Technology'], ['Consulting']],
            'circle' => [['Ahmedabad Circle'], ['Pune Circle'], ['Mumbai Circle']],
            'custom_filter' => [['Ahmedabad', 'Acme Industries', 'active', 'member1@example.com', 'Manufacturing', 'Ahmedabad Circle'], ['Pune', 'Unity Ventures', 'trial', 'member2@example.com', 'Technology', 'Pune Circle'], ['Mumbai', 'Growth Labs', 'expired', '', 'Consulting', 'Mumbai Circle']],
        ];

        return array_pad($samples[$audienceType][$index] ?? [], count($columns), '');
    }

    private function validatedCampaignData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'campaign_type' => ['required', Rule::in(self::CAMPAIGN_TYPES)],
            'subject' => ['nullable', 'string', 'max:255', 'required_if:campaign_type,email_only,email_and_notification'],
            'email_body' => ['nullable', 'string', 'required_if:campaign_type,email_only,email_and_notification'],
            'notification_title' => ['nullable', 'string', 'max:255', 'required_if:campaign_type,notification_only,email_and_notification'],
            'notification_message' => ['nullable', 'string', 'required_if:campaign_type,notification_only,email_and_notification'],
            'audience_type' => ['required', Rule::in(self::AUDIENCE_TYPES)],
            'filters' => ['nullable'],
            'pamphlet_id' => ['nullable', 'uuid', 'exists:campaign_pamphlets,id'],
            'email_template_id' => ['nullable', 'uuid', 'exists:campaign_email_templates,id'],
        ]);

        $data['filters'] = $this->normalizeFilters($request);
        $pamphlet = filled($data['pamphlet_id'] ?? null)
            ? CampaignPamphlet::query()->where('id', $data['pamphlet_id'])->first()
            : null;
        $data['pamphlet_snapshot'] = $pamphlet?->snapshot();
        $emailTemplate = filled($data['email_template_id'] ?? null)
            ? CampaignEmailTemplate::query()->where('id', $data['email_template_id'])->first()
            : $this->emailTemplateRenderer->defaultTemplate();
        $data['email_template_id'] = $emailTemplate?->id;
        $data['email_template_snapshot'] = $emailTemplate?->snapshot();

        if (! in_array($data['campaign_type'], ['email_only', 'email_and_notification'], true)) {
            $data['subject'] = null;
            $data['email_body'] = null;
        }
        if (! in_array($data['campaign_type'], ['notification_only', 'email_and_notification'], true)) {
            $data['notification_title'] = null;
            $data['notification_message'] = null;
        }

        return $data;
    }

    private function normalizeFilters(Request $request): array
    {
        $filters = $request->input('filters', []);
        if (is_string($filters)) {
            $decoded = json_decode($filters, true);
            $filters = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($filters)) {
            $filters = [];
        }

        $normalized = collect($filters)->map(function ($value) {
            if (is_array($value)) {
                return collect($value)->filter(fn ($item) => filled($item))->values()->all();
            }

            return $value;
        })->all();

        if (isset($normalized['category_ids'])) {
            $normalized['business_category_ids'] = collect($normalized['business_category_ids'] ?? [])
                ->merge($normalized['category_ids'])
                ->filter(fn ($item) => filled($item))
                ->unique()
                ->values()
                ->all();
            unset($normalized['category_ids']);
        }

        return $normalized;
    }

    public function destroy(AdminCampaign $campaign): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->authorize('delete', $campaign);

        DB::transaction(function () use ($campaign) {
            $campaign->update(['status' => AdminCampaign::STATUS_DELETED]);
            $campaign->delete();
            $this->logCampaignAction($campaign, 'deleted');
        });

        return redirect()->route('admin.campaigns.index')->with('success', 'Campaign soft-deleted successfully.');
    }

    public function pause(AdminCampaign $campaign): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->authorize('pause', $campaign);

        DB::transaction(function () use ($campaign) {
            $campaign->update(['status' => AdminCampaign::STATUS_PAUSED]);
            if ($campaign->schedule) {
                $campaign->schedule->update(['next_run_at' => null]);
            }
            $this->logCampaignAction($campaign, 'paused');
        });

        return redirect()->route('admin.campaigns.show', $campaign)->with('success', 'Campaign paused successfully.');
    }

    public function resume(AdminCampaign $campaign): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->authorize('resume', $campaign);

        DB::transaction(function () use ($campaign) {
            $campaign->update(['status' => AdminCampaign::STATUS_ACTIVE]);
            if ($campaign->schedule) {
                $nextRun = app(CampaignScheduleCalculator::class)->calculateNextRunAt($campaign->schedule, now());
                $campaign->schedule->update(['next_run_at' => $nextRun]);
            }
            $this->logCampaignAction($campaign, 'resumed');
        });

        return redirect()->route('admin.campaigns.show', $campaign)->with('success', 'Campaign resumed successfully.');
    }

    public function stop(AdminCampaign $campaign): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->authorize('stop', $campaign);

        DB::transaction(function () use ($campaign) {
            $campaign->update(['status' => AdminCampaign::STATUS_STOPPED]);
            if ($campaign->schedule) {
                $campaign->schedule->update(['next_run_at' => null]);
            }
            $this->logCampaignAction($campaign, 'stopped');
        });

        return redirect()->route('admin.campaigns.show', $campaign)->with('success', 'Campaign stopped successfully.');
    }

    public function duplicate(AdminCampaign $campaign): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->authorize('duplicate', $campaign);

        $newCampaign = DB::transaction(function () use ($campaign) {
            $duplicate = $campaign->replicate();
            $duplicate->title = 'Copy of ' . $campaign->title;
            $duplicate->status = AdminCampaign::STATUS_DRAFT;
            $duplicate->total_recipients = 0;
            $duplicate->total_email_sent = 0;
            $duplicate->total_notification_sent = 0;
            $duplicate->total_failed = 0;
            $duplicate->sent_at = null;
            $duplicate->save();

            if ($campaign->schedule) {
                $newSchedule = $campaign->schedule->replicate();
                $newSchedule->campaign_id = $duplicate->id;
                $newSchedule->next_run_at = null;
                $newSchedule->last_run_at = null;
                $newSchedule->save();
            }

            $this->logCampaignAction($duplicate, 'duplicated', ['original_campaign_id' => $campaign->id]);
            return $duplicate;
        });

        return redirect()->route('admin.campaigns.edit', $newCampaign)->with('success', 'Campaign duplicated as draft.');
    }

    public function retry(AdminCampaign $campaign): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->authorize('retry', $campaign);

        $schedule = $campaign->schedule;
        $scheduleType = $schedule ? $schedule->schedule_type : 'immediately';

        if ($scheduleType === 'immediately') {
            DB::transaction(function () use ($campaign) {
                $campaign->update([
                    'status' => 'queued',
                    'total_recipients' => 0,
                    'total_email_sent' => 0,
                    'total_notification_sent' => 0,
                    'total_failed' => 0,
                ]);
                $this->logCampaignAction($campaign, 'retried');
            });
            return $this->executeImmediateSend($campaign);
        }

        DB::transaction(function () use ($campaign, $schedule) {
            $campaign->update([
                'status' => 'active',
                'total_recipients' => 0,
                'total_email_sent' => 0,
                'total_notification_sent' => 0,
                'total_failed' => 0,
            ]);
            if ($schedule) {
                $nextRun = app(CampaignScheduleCalculator::class)->calculateNextRunAt($schedule, now());
                $schedule->update(['next_run_at' => $nextRun]);
            }
            $this->logCampaignAction($campaign, 'retried');
        });

        return redirect()->route('admin.campaigns.show', $campaign)->with('success', 'Campaign retried and activated.');
    }


    private function logCampaignAction(AdminCampaign $campaign, string $action, array $extraDetails = []): void
    {
        try {
            $admin = auth('admin')->user();
            $ipAddress = request()->ip();
            $userAgent = request()->userAgent();

            $log = new \App\Models\AdminAuditLog();
            $log->id = (string) \Illuminate\Support\Str::uuid();
            $log->admin_user_id = $admin?->id;
            $log->action = $action;
            $log->target_table = 'admin_campaigns';
            $log->target_id = $campaign->id;
            $log->details = array_merge([
                'campaign_title' => $campaign->title,
                'status' => $campaign->status,
            ], $extraDetails);
            $log->ip_address = $ipAddress;
            $log->user_agent = $userAgent;
            $log->created_at = now();
            $log->save();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log campaign action: ' . $e->getMessage());
        }
    }
}
