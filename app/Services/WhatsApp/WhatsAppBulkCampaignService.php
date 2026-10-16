<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Jobs\ProcessWhatsAppBulkCampaignJob;
use App\Models\User;
use App\Models\WhatsAppBulkCampaign;
use App\Models\WhatsAppBulkCampaignImportError;
use App\Models\WhatsAppBulkCampaignLog;
use App\Models\WhatsAppBulkCampaignRecipient;
use App\Models\WhatsappTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppBulkCampaignService
{
    public function __construct(
        protected WhatsAppCampaignCsvImportService $csvService,
        protected MetaWhatsAppService $metaWhatsAppService
    ) {}

    /**
     * Inspect a WhatsappTemplate and analyze its required and optional parameters.
     *
     * @return array{
     *     template_id: string,
     *     name: string,
     *     language: string,
     *     category: ?string,
     *     status: string,
     *     header: ?array<string, mixed>,
     *     body_variables: list<array{index: int, token: string, label: string, example: ?string}>,
     *     buttons: list<array{index: int, type: string, text: string, url: ?string, has_variable: bool}>
     * }
     */
    public function analyzeTemplateComponents(WhatsappTemplate $template): array
    {
        $components = is_array($template->components) ? $template->components : [];

        $headerInfo = null;
        $bodyVariables = [];
        $buttons = [];
        $bodyText = '';
        $footerText = null;

        foreach ($components as $component) {
            $type = strtoupper((string) ($component['type'] ?? ''));

            if ($type === 'HEADER') {
                $format = strtoupper((string) ($component['format'] ?? 'TEXT'));
                $text = (string) ($component['text'] ?? '');
                $example = $component['example']['header_text'] ?? ($component['example']['header_handle'] ?? null);

                // Detect header variables like {{1}} in header text
                $headerVars = [];
                if ($format === 'TEXT' && preg_match_all('/\{\{(\d+)\}\}/', $text, $matches)) {
                    foreach ($matches[1] as $vIdx) {
                        $headerVars[] = (int) $vIdx;
                    }
                }

                $headerInfo = [
                    'format' => $format, // TEXT, IMAGE, VIDEO, DOCUMENT
                    'text' => $text,
                    'variables' => $headerVars,
                    'is_media' => in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'], true),
                    'example' => is_array($example) ? ($example[0] ?? null) : $example,
                ];
            } elseif ($type === 'BODY') {
                $text = (string) ($component['text'] ?? '');
                $bodyText = $text;
                $bodyExamples = $component['example']['body_text'][0] ?? [];

                if (preg_match_all('/\{\{(\d+)\}\}/', $text, $matches)) {
                    $uniqueVars = array_unique(array_map('intval', $matches[1]));
                    sort($uniqueVars);

                    foreach ($uniqueVars as $vIdx) {
                        $bodyVariables[] = [
                            'index' => $vIdx,
                            'token' => "{{{$vIdx}}}",
                            'label' => "Variable {$vIdx} ({{{$vIdx}}})",
                            'example' => $bodyExamples[$vIdx - 1] ?? null,
                        ];
                    }
                }
            } elseif ($type === 'FOOTER') {
                $footerText = (string) ($component['text'] ?? '');
            } elseif ($type === 'BUTTONS') {
                $rawButtons = $component['buttons'] ?? [];
                foreach ($rawButtons as $bIdx => $btn) {
                    $bType = strtoupper((string) ($btn['type'] ?? ''));
                    $bText = (string) ($btn['text'] ?? '');
                    $bUrl = (string) ($btn['url'] ?? '');
                    $hasVar = str_contains($bUrl, '{{1}}');

                    $buttons[] = [
                        'index' => $bIdx,
                        'type' => $bType,
                        'text' => $bText,
                        'url' => $bUrl,
                        'has_variable' => $hasVar,
                    ];
                }
            }
        }

        return [
            'template_id' => (string) $template->id,
            'name' => (string) $template->name,
            'language' => (string) $template->language,
            'category' => $template->category,
            'status' => (string) $template->status,
            'header' => $headerInfo,
            'body_text' => $bodyText,
            'body_variables' => $bodyVariables,
            'footer_text' => $footerText,
            'buttons' => $buttons,
        ];
    }

    /**
     * Resolve dynamic parameters for a single recipient using configured mapping.
     *
     * @param  array<string, mixed>  $recipientData
     * @param  array<string, mixed>  $variableMapping
     * @param  array<string, mixed>  $mediaConfig
     * @return array{
     *     components: list<array<string, mixed>>,
     *     resolved_preview: array<string, mixed>,
     *     errors: list<string>
     * }
     */
    public function resolveRecipientPayload(
        array $recipientData,
        array $variableMapping,
        array $mediaConfig = [],
        ?array $templateAnalysis = null
    ): array {
        $components = [];
        $resolvedPreview = [];
        $errors = [];

        // 1. Resolve Header if required
        if (! empty($templateAnalysis['header'])) {
            $header = $templateAnalysis['header'];
            $hFormat = $header['format'] ?? 'TEXT';

            if ($header['is_media']) {
                $mediaType = strtolower($hFormat);
                $mediaSource = $mediaConfig['source'] ?? 'file';
                $mediaId = $mediaConfig['media_id'] ?? null;
                $mediaUrl = $mediaConfig['media_url'] ?? null;
                $mediaColumn = $mediaConfig['column'] ?? null;

                if ($mediaSource === 'csv_column' && ! empty($mediaColumn)) {
                    $colKey = strtolower(trim((string) $mediaColumn));
                    $rowMediaUrl = $recipientData['custom_data'][$colKey] ?? null;
                    if (! empty($rowMediaUrl)) {
                        $components[] = [
                            'type' => 'header',
                            'parameters' => [
                                [
                                    'type' => $mediaType,
                                    $mediaType => ['link' => (string) $rowMediaUrl],
                                ],
                            ],
                        ];
                        $resolvedPreview['header'] = ['type' => $mediaType, 'url' => (string) $rowMediaUrl];
                    } else {
                        $errors[] = "Missing {$hFormat} media URL in CSV column '{$mediaColumn}'.";
                    }
                } elseif (! empty($mediaId)) {
                    $components[] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => $mediaType,
                                $mediaType => ['id' => $mediaId],
                            ],
                        ],
                    ];
                    $resolvedPreview['header'] = ['type' => $mediaType, 'id' => $mediaId];
                } elseif (! empty($mediaUrl)) {
                    $components[] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => $mediaType,
                                $mediaType => ['link' => $mediaUrl],
                            ],
                        ],
                    ];
                    $resolvedPreview['header'] = ['type' => $mediaType, 'url' => $mediaUrl];
                } else {
                    $errors[] = "Template requires a {$hFormat} header media file, URL, or CSV column.";
                }
            } elseif (! empty($header['variables'])) {
                // Header text variables
                $hParams = [];
                foreach ($header['variables'] as $vIdx) {
                    $mapKey = "header_var_{$vIdx}";
                    $val = $this->resolveValueFromMapping($mapKey, $variableMapping, $recipientData);
                    if ($val === null || $val === '') {
                        $errors[] = 'Missing value for Header Variable {{1}}.';
                    } else {
                        $hParams[] = ['type' => 'text', 'text' => (string) $val];
                    }
                }
                if (! empty($hParams)) {
                    $components[] = [
                        'type' => 'header',
                        'parameters' => $hParams,
                    ];
                    $resolvedPreview['header_text_vars'] = array_column($hParams, 'text');
                }
            }
        }

        // 2. Resolve Body Variables
        $resolvedBodyText = (string) ($templateAnalysis['body_text'] ?? '');
        if (! empty($templateAnalysis['body_variables'])) {
            $bodyParams = [];
            foreach ($templateAnalysis['body_variables'] as $bVar) {
                $vIdx = $bVar['index'];
                $mapKey = "body_var_{$vIdx}";
                $val = $this->resolveValueFromMapping($mapKey, $variableMapping, $recipientData);

                if ($val === null || $val === '') {
                    $errors[] = "Missing value for Body Variable {{{$vIdx}}}.";
                } else {
                    $bodyParams[] = ['type' => 'text', 'text' => (string) $val];
                }

                $replacement = ($val !== null && $val !== '') ? (string) $val : "{{{$vIdx}}}";
                $resolvedBodyText = str_replace("{{{$vIdx}}}", $replacement, $resolvedBodyText);
            }

            if (! empty($bodyParams)) {
                $components[] = [
                    'type' => 'body',
                    'parameters' => $bodyParams,
                ];
                $resolvedPreview['body_vars'] = array_column($bodyParams, 'text');
            }
        }
        $resolvedPreview['resolved_body_text'] = $resolvedBodyText;

        // 3. Resolve Button Parameters (e.g. Dynamic URL suffix)
        if (! empty($templateAnalysis['buttons'])) {
            foreach ($templateAnalysis['buttons'] as $btn) {
                if ($btn['has_variable']) {
                    $bIdx = $btn['index'];
                    $mapKey = "button_var_{$bIdx}";
                    $val = $this->resolveValueFromMapping($mapKey, $variableMapping, $recipientData);

                    if ($val === null || $val === '') {
                        $errors[] = "Missing parameter value for button '{$btn['text']}'.";
                    } else {
                        $components[] = [
                            'type' => 'button',
                            'sub_type' => 'url',
                            'index' => (string) $bIdx,
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => (string) $val,
                                ],
                            ],
                        ];
                        $resolvedPreview["button_{$bIdx}"] = (string) $val;
                    }
                }
            }
        }

        return [
            'components' => $components,
            'resolved_preview' => $resolvedPreview,
            'errors' => $errors,
        ];
    }

    /**
     * Resolve a mapped value from either CSV column, fixed value, or recipient field.
     *
     * @param  array<string, mixed>  $variableMapping
     * @param  array<string, mixed>  $recipientData
     */
    private function resolveValueFromMapping(string $mapKey, array $variableMapping, array $recipientData): ?string
    {
        $config = $variableMapping[$mapKey] ?? null;
        if (! is_array($config)) {
            // Check direct string mapping fallback
            if (is_string($config) && isset($recipientData['custom_data'][$config])) {
                return (string) $recipientData['custom_data'][$config];
            }

            return null;
        }

        $source = $config['source'] ?? 'csv_column';

        if ($source === 'fixed') {
            return isset($config['value']) ? trim((string) $config['value']) : null;
        }

        if ($source === 'csv_column') {
            $column = strtolower(trim((string) ($config['column'] ?? '')));
            if ($column === 'name') {
                return (string) ($recipientData['name'] ?? '');
            }
            if ($column === 'phone') {
                return (string) ($recipientData['phone'] ?? '');
            }

            return isset($recipientData['custom_data'][$column]) ? (string) $recipientData['custom_data'][$column] : null;
        }

        if ($source === 'system') {
            $sysField = strtolower(trim((string) ($config['field'] ?? '')));
            if ($sysField === 'name') {
                return (string) ($recipientData['name'] ?? '');
            }
            if ($sysField === 'phone') {
                return (string) ($recipientData['phone'] ?? '');
            }
            if ($sysField === 'date') {
                return now()->format('d M Y');
            }
        }

        return null;
    }

    /**
     * Create campaign and save all recipient records in transactional chunks.
     *
     * @param  array{
     *     name: string,
     *     whatsapp_template_id: string,
     *     import_id: string,
     *     variable_mapping: array<string, mixed>,
     *     media_configuration?: array<string, mixed>,
     *     schedule_type: string,
     *     scheduled_at?: ?string,
     *     creator_id?: ?string
     * }  $payload
     */
    public function createCampaign(array $payload): WhatsAppBulkCampaign
    {
        $template = WhatsappTemplate::findOrFail($payload['whatsapp_template_id']);

        if (! $template->isSendable()) {
            throw new \InvalidArgumentException("WhatsApp template '{$template->name}' is not in APPROVED status. Cannot start campaign.");
        }

        $importData = $this->csvService->getImportData($payload['import_id']);
        if (! $importData || empty($importData['valid_recipients'])) {
            throw new \InvalidArgumentException('No valid recipients found for this campaign import. Please re-upload CSV.');
        }

        $templateAnalysis = $this->analyzeTemplateComponents($template);
        $variableMapping = $payload['variable_mapping'] ?? [];
        $mediaConfig = $payload['media_configuration'] ?? [];

        $scheduleType = $payload['schedule_type'] === 'scheduled' ? 'scheduled' : 'immediate';
        $scheduledAt = null;

        if ($scheduleType === 'scheduled') {
            if (empty($payload['scheduled_at'])) {
                throw new \InvalidArgumentException('Scheduled date and time is required for scheduled campaigns.');
            }
            $scheduledAt = Carbon::parse($payload['scheduled_at']);
            if ($scheduledAt->isPast()) {
                throw new \InvalidArgumentException('Scheduled time must be in the future.');
            }
        }

        $status = ($scheduleType === 'scheduled') ? 'scheduled' : 'queued';

        $campaign = DB::transaction(function () use (
            $payload,
            $template,
            $templateAnalysis,
            $importData,
            $variableMapping,
            $mediaConfig,
            $scheduleType,
            $scheduledAt,
            $status
        ) {
            $campaign = WhatsAppBulkCampaign::create([
                'name' => trim($payload['name']),
                'whatsapp_template_id' => $template->id,
                'template_name_snapshot' => $template->name,
                'template_language' => $template->language,
                'template_category' => $template->category,
                'template_snapshot' => $templateAnalysis,
                'variable_mapping' => $variableMapping,
                'media_configuration' => $mediaConfig,
                'status' => $status,
                'schedule_type' => $scheduleType,
                'scheduled_at' => $scheduledAt,
                'created_by' => $payload['creator_id'] ?? null,
                'total_rows' => (int) $importData['total_rows'],
                'valid_count' => (int) $importData['valid_count'],
                'invalid_count' => (int) $importData['invalid_count'],
                'duplicate_count' => (int) $importData['duplicate_count'],
                'sent_count' => 0,
                'delivered_count' => 0,
                'read_count' => 0,
                'failed_count' => 0,
            ]);

            // Persist CSV Import Errors if any
            if (! empty($importData['errors'])) {
                $errorRows = [];
                foreach ($importData['errors'] as $err) {
                    $errorRows[] = [
                        'id' => (string) Str::uuid(),
                        'campaign_id' => $campaign->id,
                        'import_reference' => $payload['import_id'],
                        'row_number' => (int) ($err['row_number'] ?? 0),
                        'raw_data' => json_encode($err['raw_data'] ?? []),
                        'error_codes' => json_encode(['VALIDATION_ERROR']),
                        'error_message' => (string) ($err['error_message'] ?? 'Validation failure'),
                        'created_at' => now(),
                    ];
                }
                foreach (array_chunk($errorRows, 500) as $errorChunk) {
                    WhatsAppBulkCampaignImportError::insert($errorChunk);
                }
            }

            // Persist valid recipients with pre-resolved template parameters
            $recipientRecords = [];
            foreach ($importData['valid_recipients'] as $rec) {
                $resolved = $this->resolveRecipientPayload($rec, $variableMapping, $mediaConfig, $templateAnalysis);

                $recStatus = empty($resolved['errors']) ? 'pending' : 'failed';
                $recErrorCode = ! empty($resolved['errors']) ? 'PARAMETER_RESOLUTION_ERROR' : null;
                $recErrorMessage = ! empty($resolved['errors']) ? implode(' | ', $resolved['errors']) : null;

                $recipientRecords[] = [
                    'id' => (string) Str::uuid(),
                    'campaign_id' => $campaign->id,
                    'name' => $rec['name'],
                    'phone' => $rec['phone'],
                    'original_phone' => $rec['original_phone'] ?? $rec['phone'],
                    'csv_row_number' => (int) ($rec['csv_row_number'] ?? 0),
                    'custom_data' => json_encode($rec['custom_data'] ?? []),
                    'resolved_parameters' => json_encode($resolved),
                    'status' => $recStatus,
                    'whatsapp_message_id' => null,
                    'attempt_count' => 0,
                    'error_code' => $recErrorCode,
                    'error_message' => $recErrorMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insert in chunks of 500 for optimal memory and Postgres statement limits
            foreach (array_chunk($recipientRecords, 500) as $chunk) {
                WhatsAppBulkCampaignRecipient::insert($chunk);
            }

            // Audit Log
            WhatsAppBulkCampaignLog::create([
                'id' => (string) Str::uuid(),
                'campaign_id' => $campaign->id,
                'event_type' => 'CAMPAIGN_CREATED',
                'message' => "Campaign '{$campaign->name}' created with {$campaign->valid_count} valid recipients. Type: {$scheduleType}.",
                'metadata' => [
                    'schedule_type' => $scheduleType,
                    'scheduled_at' => $scheduledAt?->toIso8601String(),
                    'valid_count' => $campaign->valid_count,
                    'invalid_count' => $campaign->invalid_count,
                ],
                'created_at' => now(),
            ]);

            return $campaign;
        });

        // If immediate, dispatch processing job OUTSIDE the database transaction
        // so the campaign and all its recipients are already committed to PostgreSQL.
        if ($scheduleType === 'immediate') {
            ProcessWhatsAppBulkCampaignJob::dispatch($campaign->id);
        }

        return $campaign;
    }

    /**
     * Cancel an active or scheduled campaign safely.
     */
    public function cancelCampaign(WhatsAppBulkCampaign $campaign, ?User $user = null): bool
    {
        if (! $campaign->canCancel()) {
            throw new \InvalidArgumentException("Campaign cannot be cancelled in its current state ({$campaign->status}).");
        }

        DB::transaction(function () use ($campaign, $user) {
            $campaign->update([
                'status' => 'cancelled',
                'completed_at' => now(),
            ]);

            // Cancel any remaining pending recipients
            WhatsAppBulkCampaignRecipient::where('campaign_id', $campaign->id)
                ->whereIn('status', ['pending', 'queued'])
                ->update(['status' => 'cancelled']);

            WhatsAppBulkCampaignLog::create([
                'id' => (string) Str::uuid(),
                'campaign_id' => $campaign->id,
                'event_type' => 'CAMPAIGN_CANCELLED',
                'message' => 'Campaign cancelled by '.($user ? $user->name : 'System').'.',
                'created_at' => now(),
            ]);
        });

        return true;
    }

    /**
     * Retry eligible failed recipients for a completed campaign without duplicate sends.
     */
    public function retryFailedRecipients(WhatsAppBulkCampaign $campaign): int
    {
        $eligibleCount = WhatsAppBulkCampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'failed')
            ->whereNull('whatsapp_message_id')
            ->count();

        if ($eligibleCount === 0) {
            return 0;
        }

        DB::transaction(function () use ($campaign, $eligibleCount) {
            WhatsAppBulkCampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'failed')
                ->whereNull('whatsapp_message_id')
                ->update([
                    'status' => 'pending',
                    'error_code' => null,
                    'error_message' => null,
                    'updated_at' => now(),
                ]);

            $campaign->update([
                'status' => 'queued',
                'completed_at' => null,
            ]);

            WhatsAppBulkCampaignLog::create([
                'id' => (string) Str::uuid(),
                'campaign_id' => $campaign->id,
                'event_type' => 'CAMPAIGN_RETRIED',
                'message' => "Retrying {$eligibleCount} failed recipients.",
                'created_at' => now(),
            ]);

            ProcessWhatsAppBulkCampaignJob::dispatch($campaign->id);
        });

        return $eligibleCount;
    }

    /**
     * Sync delivery/read/failed webhook event to recipient and campaign counters idempotently.
     */
    public function handleWebhookStatus(string $wamid, string $status, ?string $errorCode = null, ?string $errorMessage = null): void
    {
        $status = strtolower(trim($status));
        $recipient = WhatsAppBulkCampaignRecipient::where('whatsapp_message_id', $wamid)->first();

        if (! $recipient) {
            return;
        }

        $campaign = $recipient->campaign;
        $prevStatus = $recipient->status;

        // Monotonic progression: sent -> delivered -> read (do not downgrade read -> delivered)
        if ($prevStatus === 'read' && in_array($status, ['sent', 'delivered'], true)) {
            return;
        }
        if ($prevStatus === 'delivered' && $status === 'sent') {
            return;
        }

        $now = now();
        $updates = ['status' => $status, 'updated_at' => $now];

        if ($status === 'delivered' && ! $recipient->delivered_at) {
            $updates['delivered_at'] = $now;
        } elseif ($status === 'read') {
            if (! $recipient->delivered_at) {
                $updates['delivered_at'] = $now;
            }
            if (! $recipient->read_at) {
                $updates['read_at'] = $now;
            }
        } elseif ($status === 'failed') {
            $updates['failed_at'] = $now;
            if ($errorCode) {
                $updates['error_code'] = $errorCode;
            }
            if ($errorMessage) {
                $updates['error_message'] = $errorMessage;
            }
        }

        $recipient->update($updates);

        // Recompute aggregate counts for campaign safely
        if ($campaign) {
            $counts = DB::table('whatsapp_bulk_campaign_recipients')
                ->where('campaign_id', $campaign->id)
                ->selectRaw("
                    COUNT(*) FILTER (WHERE status = 'sent') as sent,
                    COUNT(*) FILTER (WHERE status = 'delivered') as delivered,
                    COUNT(*) FILTER (WHERE status = 'read') as read,
                    COUNT(*) FILTER (WHERE status = 'failed') as failed
                ")->first();

            if ($counts) {
                $campaign->update([
                    'sent_count' => (int) $counts->sent + (int) $counts->delivered + (int) $counts->read,
                    'delivered_count' => (int) $counts->delivered + (int) $counts->read,
                    'read_count' => (int) $counts->read,
                    'failed_count' => (int) $counts->failed,
                ]);
            }
        }
    }
}
