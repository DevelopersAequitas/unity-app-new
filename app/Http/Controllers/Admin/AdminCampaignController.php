<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminCampaign;
use App\Services\AdminCampaigns\CampaignRecipientResolverService;
use App\Services\AdminCampaigns\CampaignSendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class AdminCampaignController extends Controller
{
    private const CAMPAIGN_TYPES = ['email_only', 'notification_only', 'email_and_notification'];
    private const AUDIENCE_TYPES = ['all_members', 'city', 'circle', 'company', 'category', 'membership_status', 'specific_members', 'custom_filter'];

    public function __construct(
        private readonly CampaignRecipientResolverService $recipientResolver,
        private readonly CampaignSendService $sendService,
    ) {
    }

    public function index(Request $request): View
    {
        $campaigns = AdminCampaign::query()->latest('created_at')->paginate(20);

        $stats = [
            'total' => AdminCampaign::query()->count(),
            'draft' => AdminCampaign::query()->where('status', 'draft')->count(),
            'sent' => AdminCampaign::query()->where('status', 'sent')->count(),
            'failed' => AdminCampaign::query()->where('status', 'failed')->count(),
            'emails_sent' => AdminCampaign::query()->sum('total_email_sent'),
            'notifications_sent' => AdminCampaign::query()->sum('total_notification_sent'),
        ];

        return view('admin.campaigns.index', compact('campaigns', 'stats'));
    }

    public function create(): View
    {
        return view('admin.campaigns.form', [
            'campaign' => new AdminCampaign(['campaign_type' => 'email_only', 'audience_type' => 'all_members', 'filters' => []]),
            'filterOptions' => $this->recipientResolver->filterOptions(),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedCampaignData($request);
        $data['status'] = AdminCampaign::STATUS_DRAFT;
        $data['created_by'] = optional($request->user('admin'))->id;
        $data['updated_by'] = optional($request->user('admin'))->id;

        $campaign = AdminCampaign::query()->create($data);

        if ($request->input('action') === 'send') {
            return $this->send($campaign);
        }

        return redirect()->route('admin.campaigns.show', $campaign)->with('success', 'Campaign draft saved.');
    }

    public function show(AdminCampaign $campaign): View
    {
        $campaign->load(['recipients.user']);
        $recipients = $campaign->recipients()->with('user')->latest('created_at')->paginate(50);

        return view('admin.campaigns.show', compact('campaign', 'recipients'));
    }

    public function edit(AdminCampaign $campaign): View|RedirectResponse
    {
        if (! $campaign->isEditable()) {
            return redirect()->route('admin.campaigns.show', $campaign)->with('error', 'Sent campaigns cannot be edited.');
        }

        return view('admin.campaigns.form', [
            'campaign' => $campaign,
            'filterOptions' => $this->recipientResolver->filterOptions(),
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, AdminCampaign $campaign): RedirectResponse
    {
        if (! $campaign->isEditable()) {
            return redirect()->route('admin.campaigns.show', $campaign)->with('error', 'Sent campaigns cannot be edited.');
        }

        $data = $this->validatedCampaignData($request);
        $data['updated_by'] = optional($request->user('admin'))->id;
        $campaign->update($data);

        if ($request->input('action') === 'send') {
            return $this->send($campaign->refresh());
        }

        return redirect()->route('admin.campaigns.show', $campaign)->with('success', 'Campaign draft updated.');
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

        return response()->json(['total' => $total, 'recipients' => $recipients]);
    }

    public function send(AdminCampaign $campaign): RedirectResponse
    {
        try {
            $this->sendService->send($campaign);
            return redirect()->route('admin.campaigns.show', $campaign)->with('success', 'Campaign sent successfully.');
        } catch (RuntimeException $exception) {
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
        ]);

        $data['filters'] = $this->normalizeFilters($request);

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

        return collect($filters)->map(function ($value) {
            if (is_array($value)) {
                return collect($value)->filter(fn ($item) => filled($item))->values()->all();
            }

            return $value;
        })->all();
    }
}
