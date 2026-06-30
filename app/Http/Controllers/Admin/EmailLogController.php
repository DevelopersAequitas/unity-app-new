<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailLogController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    private const SORT_COLUMNS = [
        'created_at' => 'created_at',
        'sent_at' => 'sent_at',
        'status' => 'status',
        'subject' => 'subject',
        'recipient_email' => 'to_email',
    ];

    public function index(Request $request): View
    {
        $filters = $this->cleanFilters($request);

        $emailLogs = $this->filteredQuery($filters)
            ->with('user')
            ->orderBy(self::SORT_COLUMNS[$filters['sort']], $filters['direction'])
            ->paginate($filters['per_page'])
            ->appends($request->query());

        $templateKeys = EmailLog::query()
            ->whereNotNull('template_key')
            ->where('template_key', '!=', '')
            ->distinct()
            ->orderBy('template_key')
            ->pluck('template_key');

        $sourceModules = EmailLog::query()
            ->whereNotNull('source_module')
            ->where('source_module', '!=', '')
            ->distinct()
            ->orderBy('source_module')
            ->pluck('source_module');

        return view('admin.email_logs.index', [
            'emailLogs' => $emailLogs,
            'templateKeys' => $templateKeys,
            'sourceModules' => $sourceModules,
            'filters' => $filters,
            'sortColumns' => array_keys(self::SORT_COLUMNS),
        ]);
    }

    public function show(string $id): View
    {
        $emailLog = EmailLog::query()->with('user')->where('id', $id)->firstOrFail();

        return view('admin.email_logs.show', [
            'emailLog' => $emailLog,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->cleanFilters($request);
        $fileName = 'email-logs-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($filters): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Recipient Email', 'Recipient Name', 'Subject', 'Template Key', 'Source Module', 'Status', 'Sent At', 'Created At', 'Triggered By', 'Triggered User', 'Mail Provider', 'Error Message']);

            $this->filteredQuery($filters)
                ->with('user')
                ->orderBy(self::SORT_COLUMNS[$filters['sort']], $filters['direction'])
                ->chunk(500, function ($logs) use ($handle): void {
                    foreach ($logs as $emailLog) {
                        fputcsv($handle, [
                            $emailLog->id,
                            $emailLog->to_email,
                            $emailLog->to_name,
                            $emailLog->subject,
                            $emailLog->template_key,
                            $emailLog->source_module,
                            $emailLog->status,
                            optional($emailLog->sent_at)->toDateTimeString(),
                            optional($emailLog->created_at)->toDateTimeString(),
                            $emailLog->triggered_by_label,
                            $emailLog->trigger_user_name,
                            $emailLog->mail_provider,
                            $emailLog->error_message,
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    private function filteredQuery(array $filters)
    {
        return EmailLog::query()
            ->when(! empty($filters['search']), function ($builder) use ($filters) {
                $likeQuery = '%' . $filters['search'] . '%';
                $builder->where(function ($inner) use ($likeQuery) {
                    $inner->where('subject', 'ilike', $likeQuery)
                        ->orWhere('to_email', 'ilike', $likeQuery)
                        ->orWhere('to_name', 'ilike', $likeQuery)
                        ->orWhere('template_key', 'ilike', $likeQuery)
                        ->orWhere('source_module', 'ilike', $likeQuery);
                });
            })
            ->when(! empty($filters['recipient_email']), fn ($builder) => $builder->where('to_email', 'ilike', '%' . $filters['recipient_email'] . '%'))
            ->when(! empty($filters['subject']), fn ($builder) => $builder->where('subject', 'ilike', '%' . $filters['subject'] . '%'))
            ->when(! empty($filters['template_key']), fn ($builder) => $builder->where('template_key', $filters['template_key']))
            ->when(! empty($filters['source_module']), fn ($builder) => $builder->where('source_module', $filters['source_module']))
            ->when(! empty($filters['status']) && $filters['status'] !== 'all', fn ($builder) => $builder->where('status', $filters['status']))
            ->when(! empty($filters['triggered_by']) && $filters['triggered_by'] !== 'all', function ($builder) use ($filters) {
                $filters['triggered_by'] === 'system'
                    ? $builder->where(function ($inner) {
                        $inner->whereNull('source_type')->orWhere('source_type', 'system');
                    })
                    : $builder->where('source_type', $filters['triggered_by']);
            })
            ->when(! empty($filters['admin']), fn ($builder) => $builder->where('source_type', 'admin')->where('source_id', $filters['admin']))
            ->when(! empty($filters['user']), fn ($builder) => $builder->where('user_id', $filters['user']))
            ->when(! empty($filters['date_from']), fn ($builder) => $builder->whereDate('created_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($builder) => $builder->whereDate('created_at', '<=', $filters['date_to']));
    }

    private function cleanFilters(Request $request): array
    {
        $filters = collect($request->only([
            'search', 'recipient_email', 'subject', 'template_key', 'source_module', 'status',
            'date_from', 'date_to', 'triggered_by', 'admin', 'user', 'per_page', 'sort', 'direction',
        ]))->map(fn ($value) => is_string($value) && trim($value) === '' ? null : $value)->toArray();

        foreach (['date_from', 'date_to'] as $dateField) {
            $filters[$dateField] = $this->validDate($filters[$dateField] ?? null);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $filters['per_page'] = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 20;
        $filters['status'] = in_array(($filters['status'] ?? 'all'), ['all', 'queued', 'sent', 'failed', 'pending'], true) ? ($filters['status'] ?? 'all') : 'all';
        $filters['triggered_by'] = in_array(($filters['triggered_by'] ?? 'all'), ['all', 'admin', 'user', 'system', 'scheduled_job', 'queue_worker'], true) ? ($filters['triggered_by'] ?? 'all') : 'all';
        $filters['sort'] = array_key_exists(($filters['sort'] ?? 'created_at'), self::SORT_COLUMNS) ? $filters['sort'] : 'created_at';
        $filters['direction'] = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        foreach (['search', 'recipient_email', 'subject', 'template_key', 'source_module', 'admin', 'user'] as $field) {
            $filters[$field] = isset($filters[$field]) ? trim((string) $filters[$field]) : null;
        }

        return $filters;
    }

    private function validDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
