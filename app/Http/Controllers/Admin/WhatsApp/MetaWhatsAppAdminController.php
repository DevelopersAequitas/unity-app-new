<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\WhatsApp;

use App\Exceptions\MetaWhatsAppException;
use App\Http\Controllers\Api\WhatsApp\MetaWhatsAppWebhookController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WhatsApp\CreateMetaWhatsAppTemplateRequest;
use App\Http\Requests\Admin\WhatsApp\SendWhatsAppTestMessageRequest;
use App\Models\WhatsappMessage;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsappTemplate;
use App\Models\WhatsappWebhookEvent;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class MetaWhatsAppAdminController extends Controller
{
    public function __construct(
        private readonly MetaWhatsAppService $whatsAppService
    ) {}

    /**
     * Display WhatsApp Templates list.
     */
    public function templates(Request $request): View|JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $category = trim((string) $request->query('category', ''));
        $language = trim((string) $request->query('language', ''));

        $query = WhatsappTemplate::query()
            ->where(function ($q) {
                $q->whereNotNull('meta_template_id')
                    ->orWhereNotNull('components');
            });

        if ($search !== '') {
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $like) {
                $q->where('name', $like, "%{$search}%")
                    ->orWhere('meta_template_id', $like, "%{$search}%");
            });
        }

        if ($status !== '') {
            $query->where('status', strtoupper($status));
        }

        if ($category !== '') {
            $query->where('category', strtoupper($category));
        }

        if ($language !== '') {
            $query->where('language', $language);
        }

        $templates = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(20)->withQueryString();

        $stats = [
            'total' => WhatsappTemplate::whereNotNull('meta_template_id')->count(),
            'approved' => WhatsappTemplate::whereIn('status', ['APPROVED', 'ACTIVE'])->count(),
            'in_review' => WhatsappTemplate::whereIn('status', ['IN_REVIEW', 'PENDING'])->count(),
            'rejected' => WhatsappTemplate::where('status', 'REJECTED')->count(),
            'paused' => WhatsappTemplate::whereIn('status', ['PAUSED', 'DISABLED'])->count(),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'stats' => $stats,
                'templates' => $templates,
            ]);
        }

        return view('admin.whatsapp.templates.index', [
            'templates' => $templates,
            'stats' => $stats,
            'search' => $search,
            'status' => $status,
            'category' => $category,
            'language' => $language,
        ]);
    }

    /**
     * Show single template details.
     */
    public function showTemplate(string $id): JsonResponse|View
    {
        $template = WhatsappTemplate::where('id', $id)
            ->orWhere('meta_template_id', $id)
            ->firstOrFail();

        // Auto-check live status with Meta if template is pending or in review
        if (in_array(strtoupper((string) $template->status), ['IN_REVIEW', 'PENDING'], true) && ! empty($template->meta_template_id)) {
            try {
                $metaData = $this->whatsAppService->getTemplate((string) $template->meta_template_id);
                if (! empty($metaData['status'])) {
                    $template->update([
                        'status' => strtoupper((string) $metaData['status']),
                        'category' => strtoupper((string) ($metaData['category'] ?? $template->category)),
                        'components' => (array) ($metaData['components'] ?? $template->components),
                        'meta_response' => $metaData,
                        'last_synced_at' => now(),
                    ]);
                    $template->refresh();
                }
            } catch (Throwable $e) {
                Log::debug('[MetaWhatsAppAdminController] Template auto-check failed: '.$e->getMessage());
            }
        }

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'template' => $template,
            ]);
        }

        return view('admin.whatsapp.templates.show', [
            'template' => $template,
        ]);
    }

    /**
     * Synchronize templates directly from Meta Graph API.
     */
    public function syncTemplates(Request $request): JsonResponse|RedirectResponse
    {
        try {
            $result = $this->whatsAppService->syncTemplates();

            $message = "Successfully synchronized {$result['total_synced']} templates from Meta ({$result['created']} created, {$result['updated']} updated).";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => $result,
                ]);
            }

            return redirect()->route('admin.whatsapp.templates')->with('success', $message);
        } catch (MetaWhatsAppException $e) {
            $errorMsg = 'Meta sync failed: '.$e->getUserFriendlyMessage();

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $errorMsg], $e->getHttpStatus());
            }

            return redirect()->back()->with('error', $errorMsg);
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'Synchronization error: '.$e->getMessage());
        }
    }

    /**
     * Show Template Creation view.
     */
    public function createTemplateView(): View
    {
        return view('admin.whatsapp.templates.create');
    }

    /**
     * Submit a new template to Meta.
     */
    public function storeTemplate(CreateMetaWhatsAppTemplateRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $components = [];

        // Header Component
        $headerType = strtoupper((string) ($validated['header_type'] ?? 'NONE'));
        if ($headerType !== 'NONE') {
            $header = ['type' => 'HEADER', 'format' => $headerType];
            if ($headerType === 'TEXT' && ! empty($validated['header_text'])) {
                $header['text'] = $validated['header_text'];
            } elseif (in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
                $file = null;
                if ($headerType === 'IMAGE' && $request->hasFile('header_image_file')) {
                    $file = $request->file('header_image_file');
                } elseif ($headerType === 'VIDEO' && $request->hasFile('header_video_file')) {
                    $file = $request->file('header_video_file');
                } elseif ($headerType === 'DOCUMENT' && $request->hasFile('header_document_file')) {
                    $file = $request->file('header_document_file');
                }

                $handle = ! empty($validated['header_handle']) ? (string) $validated['header_handle'] : null;

                if ($file instanceof UploadedFile) {
                    $handle = $this->whatsAppService->uploadTemplateMediaSample($file);
                }

                if ($handle) {
                    $header['example'] = [
                        'header_handle' => [$handle],
                    ];
                }
            }
            $components[] = $header;
        }

        // Body Component (Required)
        $body = [
            'type' => 'BODY',
            'text' => $validated['body_text'],
        ];

        // Process positional variables: {{1}}, {{2}}, etc.
        preg_match_all('/\{\{(\d+)\}\}/', $validated['body_text'], $matches);
        if (! empty($matches[1])) {
            $exampleValues = [];
            $paramExamples = (array) ($validated['example_body_params'] ?? []);
            foreach ($matches[1] as $idx => $varNum) {
                $exampleValues[] = $paramExamples[$varNum] ?? $paramExamples[$idx] ?? "Example_{$varNum}";
            }
            $body['example'] = [
                'body_text' => [$exampleValues],
            ];
        }

        $components[] = $body;

        // Footer Component (Optional)
        if (! empty($validated['footer_text'])) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $validated['footer_text'],
            ];
        }

        // Buttons Component (Optional)
        if (! empty($validated['buttons']) && is_array($validated['buttons'])) {
            $buttons = [];
            foreach ($validated['buttons'] as $btn) {
                $type = strtoupper((string) $btn['type']);
                $btnItem = [
                    'type' => $type,
                    'text' => $btn['text'],
                ];
                if ($type === 'URL') {
                    $btnItem['url'] = $btn['url'];
                } elseif ($type === 'PHONE_NUMBER') {
                    $btnItem['phone_number'] = $btn['phone_number'];
                }
                $buttons[] = $btnItem;
            }

            if (! empty($buttons)) {
                $components[] = [
                    'type' => 'BUTTONS',
                    'buttons' => $buttons,
                ];
            }
        }

        $payload = [
            'name' => $validated['name'],
            'language' => $validated['language'],
            'category' => $validated['category'],
            'components' => $components,
        ];

        try {
            $result = $this->whatsAppService->createTemplate($payload);

            $msg = "WhatsApp template '{$validated['name']}' submitted to Meta. Initial status: IN_REVIEW.";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'data' => $result,
                ], 201);
            }

            return redirect()->route('admin.whatsapp.templates')->with('success', $msg);
        } catch (MetaWhatsAppException $e) {
            $errorMsg = 'Meta rejected template: '.$e->getUserFriendlyMessage();

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $errorMsg], $e->getHttpStatus());
            }

            return redirect()->back()->withInput()->with('error', $errorMsg);
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            return redirect()->back()->withInput()->with('error', 'Error: '.$e->getMessage());
        }
    }

    /**
     * Delete a template from Meta and locally.
     */
    public function deleteTemplate(string $id): JsonResponse|RedirectResponse
    {
        $template = WhatsappTemplate::findOrFail($id);

        try {
            $this->whatsAppService->deleteTemplate($template->name);

            $message = "Template '{$template->name}' deleted from Meta.";

            if (request()->wantsJson()) {
                return response()->json(['success' => true, 'message' => $message]);
            }

            return redirect()->route('admin.whatsapp.templates')->with('success', $message);
        } catch (MetaWhatsAppException $e) {
            $msg = 'Failed to delete template: '.$e->getUserFriendlyMessage();

            if (request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], $e->getHttpStatus());
            }

            return redirect()->back()->with('error', $msg);
        }
    }

    /**
     * Show Test Message screen.
     */
    public function testMessageView(): View
    {
        $templates = WhatsappTemplate::whereIn('status', ['APPROVED', 'ACTIVE'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $allowedRecipients = (array) Config::get('whatsapp.testing.allowed_recipients', ['+916353025164', '+917041151714']);
        $allowedRecipient = $allowedRecipients[0] ?? (string) Config::get('whatsapp.testing.recipient', '+916353025164');
        $senderNumber = (string) Config::get('whatsapp.sender.display_phone', '+919664620704');

        return view('admin.whatsapp.test_message', [
            'templates' => $templates,
            'allowedRecipient' => $allowedRecipient,
            'allowedRecipients' => $allowedRecipients,
            'senderNumber' => $senderNumber,
        ]);
    }

    /**
     * Send Test Message synchronously.
     */
    public function sendTestMessage(SendWhatsAppTestMessageRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $recipient = (string) $validated['recipient'];
        $templateName = (string) $validated['template_name'];
        $language = (string) ($validated['language'] ?? 'en_US');
        $variables = (array) ($validated['variables'] ?? []);

        // Find template definition to inspect media header requirements
        $template = WhatsappTemplate::query()
            ->where('name', $templateName)
            ->where('language', $language)
            ->first();

        $components = [];

        // Build Header Component if template has media/text header
        $headerDef = null;
        if ($template && is_array($template->components)) {
            foreach ($template->components as $c) {
                if (isset($c['type']) && strtoupper((string) $c['type']) === 'HEADER') {
                    $headerDef = $c;
                    break;
                }
            }
        }

        if ($headerDef && isset($headerDef['format'])) {
            $format = strtoupper((string) $headerDef['format']);

            if ($format === 'IMAGE') {
                $imageObject = null;
                if ($request->hasFile('header_image_file')) {
                    $mediaId = $this->whatsAppService->uploadMedia($request->file('header_image_file'));
                    $imageObject = ['id' => $mediaId];
                } elseif (! empty($validated['header_image_url'])) {
                    $imageObject = ['link' => (string) $validated['header_image_url']];
                } elseif (! empty($headerDef['example']['header_handle'][0])) {
                    $sampleHandle = (string) $headerDef['example']['header_handle'][0];
                    if (str_starts_with($sampleHandle, 'http://') || str_starts_with($sampleHandle, 'https://')) {
                        $imageObject = ['link' => $sampleHandle];
                    }
                }

                if ($imageObject) {
                    $components[] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'image',
                                'image' => $imageObject,
                            ],
                        ],
                    ];
                }
            } elseif ($format === 'VIDEO') {
                $videoObject = null;
                if ($request->hasFile('header_video_file')) {
                    $mediaId = $this->whatsAppService->uploadMedia($request->file('header_video_file'));
                    $videoObject = ['id' => $mediaId];
                } elseif (! empty($validated['header_video_url'])) {
                    $videoObject = ['link' => (string) $validated['header_video_url']];
                } elseif (! empty($headerDef['example']['header_handle'][0])) {
                    $sampleHandle = (string) $headerDef['example']['header_handle'][0];
                    if (str_starts_with($sampleHandle, 'http://') || str_starts_with($sampleHandle, 'https://')) {
                        $videoObject = ['link' => $sampleHandle];
                    }
                }

                if ($videoObject) {
                    $components[] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'video',
                                'video' => $videoObject,
                            ],
                        ],
                    ];
                }
            } elseif ($format === 'DOCUMENT') {
                $docObject = null;
                if ($request->hasFile('header_document_file')) {
                    $mediaId = $this->whatsAppService->uploadMedia($request->file('header_document_file'));
                    $docObject = ['id' => $mediaId];
                } elseif (! empty($validated['header_document_url'])) {
                    $docObject = ['link' => (string) $validated['header_document_url']];
                } elseif (! empty($headerDef['example']['header_handle'][0])) {
                    $sampleHandle = (string) $headerDef['example']['header_handle'][0];
                    if (str_starts_with($sampleHandle, 'http://') || str_starts_with($sampleHandle, 'https://')) {
                        $docObject = ['link' => $sampleHandle];
                    }
                }

                if ($docObject) {
                    $components[] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'document',
                                'document' => $docObject,
                            ],
                        ],
                    ];
                }
            }
        }

        // Build Meta components if variables provided
        if (! empty($variables)) {
            $bodyParameters = [];
            foreach ($variables as $val) {
                $bodyParameters[] = [
                    'type' => 'text',
                    'text' => (string) $val,
                ];
            }
            $components[] = [
                'type' => 'body',
                'parameters' => $bodyParameters,
            ];
        }

        try {
            $result = $this->whatsAppService->sendTemplateMessage(
                $recipient,
                $templateName,
                $language,
                $components,
                null
            );

            $msg = "WhatsApp test message sent successfully! Meta Message ID: {$result['meta_message_id']}. Waiting for delivery webhook.";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'data' => $result,
                ]);
            }

            return redirect()->route('admin.whatsapp.messages')->with('success', $msg);
        } catch (MetaWhatsAppException $e) {
            $errMsg = 'Message send failed: '.$e->getUserFriendlyMessage();

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $errMsg], $e->getHttpStatus());
            }

            return redirect()->back()->withInput()->with('error', $errMsg);
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            return redirect()->back()->withInput()->with('error', 'Error sending message: '.$e->getMessage());
        }
    }

    /**
     * Display WhatsApp Message Logs.
     */
    public function messages(Request $request): View|JsonResponse
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $templateKey = $request->query('template_key') ?: $request->query('template');
        $eventKey = $request->query('event_key') ?: $request->query('event');
        $phone = $request->query('phone');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $isTest = $request->query('is_test');
        $userId = $request->query('user_id');

        $query = WhatsAppMessageLog::with(['user', 'template']);

        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        if ($search) {
            $query->where(function ($q) use ($search, $like) {
                $q->where('recipient_phone', $like, "%{$search}%")
                    ->orWhere('requested_recipient_phone', $like, "%{$search}%")
                    ->orWhere('actual_recipient_phone', $like, "%{$search}%")
                    ->orWhere('provider_message_id', $like, "%{$search}%")
                    ->orWhere('template_name', $like, "%{$search}%")
                    ->orWhere('template_key', $like, "%{$search}%")
                    ->orWhere('event_key', $like, "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', strtolower((string) $status));
        }

        if ($templateKey) {
            $query->where(function ($q) use ($templateKey, $like) {
                $q->where('template_key', $like, "%{$templateKey}%")
                    ->orWhere('template_name', $like, "%{$templateKey}%");
            });
        }

        if ($eventKey) {
            $query->where('event_key', $like, "%{$eventKey}%");
        }

        if ($phone) {
            $query->where(function ($q) use ($phone, $like) {
                $q->where('recipient_phone', $like, "%{$phone}%")
                    ->orWhere('requested_recipient_phone', $like, "%{$phone}%")
                    ->orWhere('actual_recipient_phone', $like, "%{$phone}%");
            });
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($isTest !== null && $isTest !== '') {
            $query->where('is_test', filter_var($isTest, FILTER_VALIDATE_BOOLEAN));
        }

        if ($userId) {
            $query->where('user_id', (string) $userId);
        }

        $messages = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->withQueryString();

        $stats = [
            'total' => WhatsAppMessageLog::count(),
            'sent' => WhatsAppMessageLog::whereIn('status', ['sent', 'delivered', 'read'])->count(),
            'delivered' => WhatsAppMessageLog::whereIn('status', ['delivered', 'read'])->count(),
            'read' => WhatsAppMessageLog::where('status', 'read')->count(),
            'failed' => WhatsAppMessageLog::where('status', 'failed')->count(),
            'dry_run' => WhatsAppMessageLog::where('status', 'dry_run')->count(),
            'test' => WhatsAppMessageLog::where('is_test', true)->count(),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'stats' => $stats,
                'messages' => $messages,
            ]);
        }

        return view('admin.whatsapp.messages.index', [
            'messages' => $messages,
            'stats' => $stats,
            'search' => $search,
            'status' => $status,
            'template' => $templateKey,
            'templateKey' => $templateKey,
            'eventKey' => $eventKey,
            'phone' => $phone,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'isTest' => $isTest,
        ]);
    }

    /**
     * Show single message log details.
     */
    public function showMessage(string $id): JsonResponse|View
    {
        $message = WhatsAppMessageLog::with(['user', 'template'])->find($id);

        if (! $message) {
            $oldMsg = WhatsappMessage::with(['user', 'template'])->find($id);
            if ($oldMsg) {
                $message = WhatsAppMessageLog::with(['user', 'template'])->where('provider_message_id', $oldMsg->meta_message_id)->first();
            }
        }

        if (! $message) {
            abort(404, 'WhatsApp message log not found.');
        }

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return view('admin.whatsapp.messages.show', [
            'message' => $message,
        ]);
    }

    /**
     * Mark all delivered test messages as read (manual bulk action for local verification).
     */
    public function markAllDeliveredAsRead(): RedirectResponse
    {
        WhatsAppMessageLog::query()
            ->where('status', WhatsAppMessageLog::STATUS_DELIVERED)
            ->update([
                'status' => WhatsAppMessageLog::STATUS_READ,
            ]);

        WhatsappMessage::query()
            ->where('status', 'DELIVERED')
            ->update([
                'status' => 'READ',
                'read_at' => now(),
            ]);

        return redirect()->back()->with('success', 'All delivered messages marked as READ successfully.');
    }

    /**
     * Simulate incoming status webhook for localhost testing (Delivered / Read).
     */
    public function simulateStatus(Request $request, string $id, MetaWhatsAppWebhookController $webhookController): RedirectResponse|JsonResponse
    {
        $message = WhatsAppMessageLog::find($id);
        $wamid = null;
        $recipientPhone = null;

        if ($message) {
            $wamid = $message->provider_message_id;
            $recipientPhone = $message->actual_recipient_phone ?: $message->recipient_phone;
        } else {
            $oldMsg = WhatsappMessage::findOrFail($id);
            $wamid = $oldMsg->meta_message_id;
            $recipientPhone = $oldMsg->recipient_phone;
        }

        $status = strtolower((string) $request->input('status', 'delivered'));

        if (! in_array($status, ['delivered', 'read', 'failed'], true)) {
            $status = 'delivered';
        }

        $statusItem = [
            'id' => $wamid,
            'status' => $status,
            'timestamp' => now()->timestamp,
            'recipient_id' => $recipientPhone,
        ];

        if ($status === 'failed') {
            $statusItem['errors'] = [
                [
                    'code' => 131026,
                    'title' => 'Message undeliverable',
                    'message' => 'Message undeliverable to recipient phone number on WhatsApp network.',
                ],
            ];
        }

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => Config::get('whatsapp.meta.business_account_id', '1475250733815899'),
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => Config::get('whatsapp.sender.display_phone', '+919664620704'),
                                    'phone_number_id' => Config::get('whatsapp.meta.phone_number_id', '1148211571702139'),
                                ],
                                'statuses' => [
                                    $statusItem,
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];

        $webhookRequest = Request::create(
            '/api/whatsapp/webhook',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode($payload)
        );

        $webhookController->handle($webhookRequest);

        $msg = 'Simulated Meta Webhook for status: '.strtoupper($status).'. Message status updated successfully!';

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Display Webhook Logs.
     */
    public function webhookLogs(Request $request): View|JsonResponse
    {
        $events = WhatsappWebhookEvent::orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'events' => $events,
            ]);
        }

        return view('admin.whatsapp.webhook_logs', [
            'events' => $events,
        ]);
    }

    /**
     * Display WhatsApp Settings and Connection Status.
     */
    public function settings(): View
    {
        $config = [
            'waba_id' => Config::get('whatsapp.meta.business_account_id', '1475250733815899'),
            'phone_number_id' => Config::get('whatsapp.meta.phone_number_id', '1148211571702139'),
            'business_id' => Config::get('whatsapp.meta.business_id', '5418283234857525'),
            'app_id' => Config::get('whatsapp.meta.app_id', '757348670325961'),
            'api_version' => Config::get('whatsapp.meta.api_version', 'v22.0'),
            'display_phone' => Config::get('whatsapp.sender.display_phone', '+919664620704'),
            'verified_name' => Config::get('whatsapp.sender.verified_name', 'Peers Global Business Media Pvt Ltd'),
            'token_configured' => ! empty(Config::get('whatsapp.meta.access_token')),
            'webhook_verify_token_configured' => ! empty(Config::get('whatsapp.meta.webhook_verify_token')),
            'webhook_url' => url('/api/whatsapp/webhook'),
            'test_recipient' => Config::get('whatsapp.testing.recipient', '+916353025164'),
        ];

        return view('admin.whatsapp.settings', [
            'config' => $config,
        ]);
    }

    /**
     * Run live Meta Connection Test.
     */
    public function testConnection(): JsonResponse
    {
        $result = $this->whatsAppService->validateConfiguration();

        return response()->json([
            'success' => $result['connected'],
            'data' => $result,
        ]);
    }
}
