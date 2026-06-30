<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailLogController extends Controller
{
    private const STATUSES = ['all', 'queued', 'sent', 'failed', 'pending'];
    private const SORTABLE = ['created_at', 'sent_at', 'status', 'subject', 'recipient_email'];

    public function index(Request $request): View
    {
        $data = $this->validatedFilters($request);
        $query = $this->filteredQuery($data)->with(['user:id,display_name,first_name,last_name,email']);

        $sort = $data['sort'];
        $direction = $data['direction'];
        $sortColumn = $sort === 'recipient_email' ? 'to_email' : $sort;

        $emailLogs = $query->orderBy($sortColumn, $direction)
            ->paginate($data['per_page'])
            ->appends($request->query());

        return view('admin.email_logs.index', [
            'emailLogs' => $emailLogs,
            'filters' => $data,
            'templateKeys' => $this->distinctOptions('template_key'),
            'sourceModules' => $this->distinctOptions('source_module'),
            'admins' => AdminUser::query()->orderBy('name')->get(['id', 'name', 'email']),
            'users' => User::query()->orderBy('display_name')->limit(500)->get(['id', 'display_name', 'first_name', 'last_name', 'email']),
        ]);
    }

    public function show(string $id): View
    {
        $emailLog = EmailLog::query()
            ->with(['user:id,display_name,first_name,last_name,email'])
            ->where('id', $id)
            ->firstOrFail();

        return view('admin.email_logs.show', ['emailLog' => $emailLog]);
    }

    public function export(Request $request, string $format): Response|StreamedResponse
    {
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 404);

        $rows = $this->filteredQuery($this->validatedFilters($request))
            ->orderByDesc('created_at')
            ->get();

        $headers = ['ID', 'Recipient Email', 'Recipient Name', 'Subject', 'Template Key', 'Source Module', 'Status', 'Sent At', 'Created At', 'Triggered By', 'Triggered User', 'Mail Provider', 'Error Message'];
        $filename = 'email-logs-' . now()->format('Ymd-His') . '.' . ($format === 'xlsx' ? 'xls' : 'csv');

        if ($format === 'xlsx') {
            $html = view('admin.email_logs.export', compact('rows', 'headers'))->render();
            return response($html, 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $log) {
                fputcsv($out, $this->exportRow($log));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filteredQuery(array $filters): Builder
    {
        return EmailLog::query()
            ->when($filters['search'] !== '', function (Builder $builder) use ($filters) {
                $like = '%' . $filters['search'] . '%';
                $builder->where(fn (Builder $q) => $q->where('subject', 'ilike', $like)
                    ->orWhere('to_email', 'ilike', $like)
                    ->orWhere('to_name', 'ilike', $like)
                    ->orWhere('template_key', 'ilike', $like)
                    ->orWhere('source_module', 'ilike', $like));
            })
            ->when($filters['recipient_email'] !== '', fn (Builder $q) => $q->where('to_email', 'ilike', '%' . $filters['recipient_email'] . '%'))
            ->when($filters['subject'] !== '', fn (Builder $q) => $q->where('subject', 'ilike', '%' . $filters['subject'] . '%'))
            ->when($filters['template_key'] !== '', fn (Builder $q) => $q->where('template_key', $filters['template_key']))
            ->when($filters['source_module'] !== '', fn (Builder $q) => $q->where('source_module', $filters['source_module']))
            ->when($filters['status'] !== 'all', fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['triggered_by'] !== '', fn (Builder $q) => $q->where('triggered_by', $filters['triggered_by']))
            ->when($filters['admin_id'] !== '', fn (Builder $q) => $q->where('admin_id', $filters['admin_id']))
            ->when($filters['user_id'] !== '', fn (Builder $q) => $q->where('user_id', $filters['user_id']))
            ->when($filters['date_from'] !== '', fn (Builder $q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn (Builder $q) => $q->whereDate('created_at', '<=', $filters['date_to']));
    }

    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'], 'recipient_email' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'], 'template_key' => ['nullable', 'string', 'max:255'],
            'source_module' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'in:' . implode(',', self::STATUSES)],
            'triggered_by' => ['nullable', 'string', 'max:100'], 'admin_id' => ['nullable', 'uuid'], 'user_id' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date'], 'per_page' => ['nullable', 'integer', 'in:10,20,50,100'],
            'sort' => ['nullable', 'in:' . implode(',', self::SORTABLE)], 'direction' => ['nullable', 'in:asc,desc'],
        ]);

        return array_merge([
            'search' => '', 'recipient_email' => '', 'subject' => '', 'template_key' => '', 'source_module' => '', 'status' => 'all',
            'triggered_by' => '', 'admin_id' => '', 'user_id' => '', 'date_from' => '', 'date_to' => '', 'per_page' => 20,
            'sort' => 'created_at', 'direction' => 'desc',
        ], array_map(fn ($v) => is_string($v) ? trim($v) : $v, $validated));
    }

    private function distinctOptions(string $column)
    {
        return EmailLog::query()->whereNotNull($column)->where($column, '!=', '')->distinct()->orderBy($column)->pluck($column);
    }

    private function exportRow(EmailLog $log): array
    {
        return [$log->id, $log->to_email, $log->to_name, $log->subject, $log->template_key, $log->source_module, $log->status, optional($log->sent_at)->toDateTimeString(), optional($log->created_at)->toDateTimeString(), $log->triggered_by, $log->trigger_user_name ?: $log->trigger_user_email, $log->mail_provider, $log->error_message];
    }
}
