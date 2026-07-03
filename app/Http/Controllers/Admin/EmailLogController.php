<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailLogController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'template_key' => ['nullable', 'string', 'max:255'],
            'source_module' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:all,sent,failed,pending,queued'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50,100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $templateKey = trim((string) ($validated['template_key'] ?? ''));
        $sourceModule = trim((string) ($validated['source_module'] ?? ''));
        $status = (string) ($validated['status'] ?? 'all');
        $dateFrom = (string) ($validated['date_from'] ?? '');
        $dateTo = (string) ($validated['date_to'] ?? '');
        $perPage = (int) ($validated['per_page'] ?? 20);

        $likeOp = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $emailLogs = EmailLog::query()
            ->when($search !== '', function ($builder) use ($search, $likeOp) {
                $likeQuery = '%' . $search . '%';

                $builder->where(function ($inner) use ($likeQuery, $likeOp) {
                    $inner->where('to_email', $likeOp, $likeQuery)
                        ->orWhere('to_name', $likeOp, $likeQuery)
                        ->orWhere('subject', $likeOp, $likeQuery)
                        ->orWhere('template_key', $likeOp, $likeQuery)
                        ->orWhere('source_module', $likeOp, $likeQuery);
                });
            })
            ->when($templateKey !== '', fn ($builder) => $builder->where('template_key', $templateKey))
            ->when($sourceModule !== '', fn ($builder) => $builder->where('source_module', $sourceModule))
            ->when($status !== '' && $status !== 'all', fn ($builder) => $builder->where('status', $status))
            ->when($dateFrom !== '', fn ($builder) => $builder->whereDate('sent_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($builder) => $builder->whereDate('sent_at', '<=', $dateTo))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->appends($request->query());

        $sourceModules = EmailLog::query()
            ->whereNotNull('source_module')
            ->where('source_module', '!=', '')
            ->distinct()
            ->orderBy('source_module')
            ->pluck('source_module');

        $templateKeys = EmailLog::query()
            ->whereNotNull('template_key')
            ->where('template_key', '!=', '')
            ->distinct()
            ->orderBy('template_key')
            ->pluck('template_key');

        return view('admin.email_logs.index', [
            'emailLogs' => $emailLogs,
            'templateKeys' => $templateKeys,
            'sourceModules' => $sourceModules,
            'filters' => [
                'search' => $search,
                'template_key' => $templateKey,
                'source_module' => $sourceModule,
                'status' => in_array($status, ['all', 'sent', 'failed', 'pending', 'queued'], true) ? $status : 'all',
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(EmailLog $emailLog): View
    {
        return view('admin.email_logs.show', [
            'emailLog' => $emailLog,
        ]);
    }
}
