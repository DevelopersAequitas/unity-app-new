<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WhatsAppBulkCampaign;
use App\Models\WhatsAppBulkCampaignImportError;
use App\Models\WhatsAppBulkCampaignRecipient;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\MetaWhatsAppService;
use App\Services\WhatsApp\WhatsAppBulkCampaignService;
use App\Services\WhatsApp\WhatsAppCampaignCsvImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class WhatsAppBulkCampaignAdminController extends Controller
{
    public function __construct(
        protected WhatsAppBulkCampaignService $campaignService,
        protected WhatsAppCampaignCsvImportService $csvService,
        protected MetaWhatsAppService $metaWhatsAppService
    ) {}

    /**
     * Display a listing of WhatsApp Bulk Campaigns with search, filters, and metrics.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));
        $templateId = trim((string) $request->input('template_id', ''));
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));

        $campaigns = null;
        $stats = [
            'total' => 0,
            'pending_or_scheduled' => 0,
            'completed' => 0,
            'total_sent' => 0,
            'total_delivered' => 0,
            'total_read' => 0,
            'total_failed' => 0,
        ];
        $dbError = null;

        try {
            $query = WhatsAppBulkCampaign::with(['template', 'creator'])
                ->orderBy('created_at', 'desc');

            if ($search !== '') {
                $query->where('name', 'ilike', "%{$search}%");
            }

            if ($status !== '' && $status !== 'all') {
                $query->where('status', $status);
            }

            if ($templateId !== '') {
                $query->where('whatsapp_template_id', $templateId);
            }

            if ($dateFrom !== '') {
                $query->whereDate('created_at', '>=', Carbon::parse($dateFrom));
            }

            if ($dateTo !== '') {
                $query->whereDate('created_at', '<=', Carbon::parse($dateTo));
            }

            $campaigns = $query->paginate(15)->withQueryString();

            // Aggregate metrics
            $agg = DB::table('whatsapp_bulk_campaigns')
                ->selectRaw("
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE status IN ('scheduled', 'queued', 'processing')) as pending_or_scheduled,
                    COUNT(*) FILTER (WHERE status IN ('completed', 'completed_with_errors')) as completed,
                    COALESCE(SUM(sent_count), 0) as total_sent,
                    COALESCE(SUM(delivered_count), 0) as total_delivered,
                    COALESCE(SUM(read_count), 0) as total_read,
                    COALESCE(SUM(failed_count), 0) as total_failed
                ")->first();

            if ($agg) {
                $stats = [
                    'total' => (int) $agg->total,
                    'pending_or_scheduled' => (int) $agg->pending_or_scheduled,
                    'completed' => (int) $agg->completed,
                    'total_sent' => (int) $agg->total_sent,
                    'total_delivered' => (int) $agg->total_delivered,
                    'total_read' => (int) $agg->total_read,
                    'total_failed' => (int) $agg->total_failed,
                ];
            }
        } catch (Throwable $e) {
            $dbError = "Bulk campaigns table not yet initialized. Please run docs/sql/whatsapp_bulk_campaigns.sql manually in your PostgreSQL database. (Details: {$e->getMessage()})";
            Log::warning('[WhatsAppBulkCampaignAdminController] Database error: '.$e->getMessage());
        }

        // Available templates for filtering
        $templates = [];
        try {
            $templates = WhatsappTemplate::orderBy('name')->get(['id', 'name', 'status']);
        } catch (Throwable) {
            // Silently fallback if templates table has issues
        }

        return view('admin.whatsapp.campaigns.index', [
            'campaigns' => $campaigns,
            'stats' => $stats,
            'templates' => $templates,
            'search' => $search,
            'status' => $status,
            'templateId' => $templateId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'dbError' => $dbError,
        ]);
    }

    /**
     * Show the 4-step wizard to create a new WhatsApp Bulk Campaign.
     */
    public function create(): View
    {
        $approvedTemplates = [];
        $dbError = null;

        try {
            $rawTemplates = WhatsappTemplate::whereIn('status', ['APPROVED', 'ACTIVE'])
                ->orderBy('name')
                ->get();

            foreach ($rawTemplates as $tmpl) {
                $approvedTemplates[] = $this->campaignService->analyzeTemplateComponents($tmpl);
            }
        } catch (Throwable $e) {
            $dbError = "Unable to load WhatsApp templates: {$e->getMessage()}";
        }

        return view('admin.whatsapp.campaigns.create', [
            'templates' => $approvedTemplates,
            'dbError' => $dbError,
        ]);
    }

    /**
     * AJAX endpoint: Upload and validate CSV file.
     */
    public function uploadCsv(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
            'header_mapping' => 'nullable|array',
        ]);

        try {
            /** @var UploadedFile $file */
            $file = $request->file('file');
            $headerMapping = (array) $request->input('header_mapping', []);

            $result = $this->csvService->parseAndValidate($file, $headerMapping);

            return response()->json([
                'success' => true,
                'data' => $result,
            ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    /**
     * Download the standard sample CSV file.
     */
    public function downloadSampleCsv(): Response
    {
        $content = $this->csvService->generateSampleCsv();

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="peers_whatsapp_campaign_sample.csv"',
        ]);
    }

    /**
     * Download import error report for a staged import.
     */
    public function downloadErrors(string $importId): Response
    {
        $importData = $this->csvService->getImportData($importId);

        if (! $importData || empty($importData['errors'])) {
            return response('No errors found for this import.', 404);
        }

        $csv = $this->csvService->generateErrorReportCsv($importData['errors']);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"import_errors_{$importId}.csv\"",
        ]);
    }

    /**
     * AJAX endpoint: Resolve and preview a personalized sample message.
     */
    public function previewMessage(Request $request): JsonResponse
    {
        $request->validate([
            'whatsapp_template_id' => 'required|uuid',
            'variable_mapping' => 'nullable|array',
            'media_configuration' => 'nullable|array',
            'sample_recipient' => 'nullable|array',
        ]);

        try {
            $template = WhatsappTemplate::findOrFail($request->input('whatsapp_template_id'));
            $templateAnalysis = $this->campaignService->analyzeTemplateComponents($template);

            $sampleRecipient = (array) $request->input('sample_recipient', [
                'name' => 'Amit Patel',
                'phone' => '919876543210',
                'custom_data' => [
                    'company' => 'Peers Global Unity',
                    'city' => 'Ahmedabad',
                    'email' => 'amit@example.com',
                ],
            ]);

            $resolved = $this->campaignService->resolveRecipientPayload(
                $sampleRecipient,
                (array) $request->input('variable_mapping', []),
                (array) $request->input('media_configuration', []),
                $templateAnalysis
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'template' => $templateAnalysis,
                    'resolved' => $resolved,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Create and dispatch or schedule the campaign.
     */
    public function store(Request $request): RedirectResponse
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'whatsapp_template_id' => 'required|uuid|exists:whatsapp_templates,id',
            'import_id' => 'required|string',
            'variable_mapping' => 'nullable|array',
            'schedule_type' => 'required|in:immediate,scheduled',
            'scheduled_at' => 'nullable|required_if:schedule_type,scheduled|date',
            'media_file' => 'nullable|file|max:32768',
            'media_url' => 'nullable|url|max:1000',
        ], [
            'import_id.required' => 'Please upload a CSV file with recipient phone numbers in Step 2.',
            'whatsapp_template_id.required' => 'Please select an approved WhatsApp template in Step 1.',
            'name.required' => 'Please provide a campaign name in Step 1.',
            'media_file.max' => 'The uploaded media file must not exceed 32MB.',
        ]);

        $mediaConfig = [
            'source' => $request->input('media_source', 'file'),
            'column' => $request->input('media_column'),
        ];
        if (! empty($validated['media_url'])) {
            $mediaConfig['media_url'] = $validated['media_url'];
        }

        // If media file was uploaded, upload it to Meta Cloud API to obtain a Meta Media ID
        if ($request->hasFile('media_file')) {
            try {
                $uploadedMedia = $request->file('media_file');
                $mediaId = $this->metaWhatsAppService->uploadMedia($uploadedMedia);
                $mediaConfig['media_id'] = $mediaId;
                $mediaConfig['original_filename'] = $uploadedMedia->getClientOriginalName();
                $mediaConfig['mime_type'] = $uploadedMedia->getMimeType();
            } catch (Throwable $e) {
                return back()
                    ->withInput()
                    ->with('error', 'Failed to upload header media to WhatsApp Cloud API: '.$e->getMessage());
            }
        }

        try {
            $campaign = $this->campaignService->createCampaign([
                'name' => $validated['name'],
                'whatsapp_template_id' => $validated['whatsapp_template_id'],
                'import_id' => $validated['import_id'],
                'variable_mapping' => $validated['variable_mapping'] ?? [],
                'media_configuration' => $mediaConfig,
                'schedule_type' => $validated['schedule_type'],
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'creator_id' => Auth::id() ?: null,
            ]);

            $msg = ($campaign->schedule_type === 'scheduled')
                ? "Campaign '{$campaign->name}' has been scheduled for {$campaign->scheduled_at->format('d M Y, h:i A')}."
                : "Campaign '{$campaign->name}' created and queued for immediate delivery to {$campaign->valid_count} recipients.";

            return redirect()
                ->route('admin.whatsapp.campaigns.show', $campaign->id)
                ->with('success', $msg);
        } catch (Throwable $e) {
            Log::error('[WhatsAppBulkCampaignAdminController] Failed to create campaign: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create campaign: '.$e->getMessage());
        }
    }

    /**
     * Show campaign details, recipient delivery states, and audit logs.
     */
    public function show(string $id, Request $request): View
    {
        $campaign = WhatsAppBulkCampaign::with(['template', 'creator'])->findOrFail($id);

        $recipientStatus = trim((string) $request->input('recipient_status', ''));
        $search = trim((string) $request->input('search', ''));

        $recipientsQuery = WhatsAppBulkCampaignRecipient::where('campaign_id', $campaign->id);

        if ($recipientStatus !== '' && $recipientStatus !== 'all') {
            $recipientsQuery->where('status', $recipientStatus);
        }

        if ($search !== '') {
            $recipientsQuery->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        $recipients = $recipientsQuery->orderBy('csv_row_number', 'asc')->paginate(25)->withQueryString();
        $importErrors = WhatsAppBulkCampaignImportError::where('campaign_id', $campaign->id)->limit(50)->get();
        $logs = $campaign->logs()->limit(30)->get();

        return view('admin.whatsapp.campaigns.show', [
            'campaign' => $campaign,
            'recipients' => $recipients,
            'importErrors' => $importErrors,
            'logs' => $logs,
            'recipientStatus' => $recipientStatus,
            'search' => $search,
        ]);
    }

    /**
     * Cancel an active or scheduled campaign.
     */
    public function cancel(string $id): RedirectResponse
    {
        $campaign = WhatsAppBulkCampaign::findOrFail($id);

        try {
            $user = Auth::user();
            $this->campaignService->cancelCampaign($campaign, $user instanceof User ? $user : null);

            return back()->with('success', "Campaign '{$campaign->name}' has been cancelled.");
        } catch (Throwable $e) {
            return back()->with('error', 'Cannot cancel campaign: '.$e->getMessage());
        }
    }

    /**
     * Retry eligible failed recipients for this campaign.
     */
    public function retry(string $id): RedirectResponse
    {
        $campaign = WhatsAppBulkCampaign::findOrFail($id);

        try {
            $retriedCount = $this->campaignService->retryFailedRecipients($campaign);

            return back()->with('success', "Queued {$retriedCount} failed recipient(s) for retry.");
        } catch (Throwable $e) {
            return back()->with('error', 'Failed to trigger retry: '.$e->getMessage());
        }
    }

    /**
     * Export campaign recipient results to CSV.
     */
    public function export(string $id, Request $request): StreamedResponse
    {
        $campaign = WhatsAppBulkCampaign::findOrFail($id);
        $status = trim((string) $request->input('status', ''));

        $filename = 'campaign_'.Str::slug($campaign->name).'_recipients.csv';

        return response()->streamDownload(function () use ($campaign, $status) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Row', 'Recipient Name', 'Phone', 'Status', 'WhatsApp Message ID', 'Sent At', 'Delivered At', 'Read At', 'Error Details']);

            $query = WhatsAppBulkCampaignRecipient::where('campaign_id', $campaign->id);
            if ($status !== '' && $status !== 'all') {
                $query->where('status', $status);
            }

            $query->orderBy('csv_row_number', 'asc')->chunk(500, function ($recipients) use ($handle) {
                foreach ($recipients as $rec) {
                    fputcsv($handle, [
                        $rec->csv_row_number,
                        $rec->name,
                        $rec->phone,
                        strtoupper($rec->status),
                        $rec->whatsapp_message_id ?? '',
                        $rec->sent_at?->format('Y-m-d H:i:s') ?? '',
                        $rec->delivered_at?->format('Y-m-d H:i:s') ?? '',
                        $rec->read_at?->format('Y-m-d H:i:s') ?? '',
                        $rec->error_message ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
