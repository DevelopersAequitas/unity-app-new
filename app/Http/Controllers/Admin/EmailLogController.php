<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class EmailLogController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'template_key' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:all,sent,failed,pending'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50,100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $templateKey = trim((string) ($validated['template_key'] ?? ''));
        $status = (string) ($validated['status'] ?? 'all');
        $dateFrom = (string) ($validated['date_from'] ?? '');
        $dateTo = (string) ($validated['date_to'] ?? '');
        $perPage = (int) ($validated['per_page'] ?? 20);

        $emailLogs = EmailLog::query()
            ->when($search !== '', function ($builder) use ($search) {
                $likeQuery = '%' . $search . '%';

                $builder->where(function ($inner) use ($likeQuery) {
                    $inner->where('to_email', 'ilike', $likeQuery)
                        ->orWhere('subject', 'ilike', $likeQuery);
                });
            })
            ->when($templateKey !== '', fn ($builder) => $builder->where('template_key', $templateKey))
            ->when($status !== '' && $status !== 'all', fn ($builder) => $builder->where('status', $status))
            ->when($dateFrom !== '', fn ($builder) => $builder->whereDate('sent_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($builder) => $builder->whereDate('sent_at', '<=', $dateTo))
            ->orderByDesc('sent_at')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->appends($request->query());

        $templateKeys = EmailLog::query()
            ->whereNotNull('template_key')
            ->where('template_key', '!=', '')
            ->distinct()
            ->orderBy('template_key')
            ->pluck('template_key');

        return view('admin.email_logs.index', [
            'emailLogs' => $emailLogs,
            'templateKeys' => $templateKeys,
            'filters' => [
                'search' => $search,
                'template_key' => $templateKey,
                'status' => in_array($status, ['all', 'sent', 'failed', 'pending'], true) ? $status : 'all',
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(string $id): View
    {
        $emailLog = EmailLog::query()->where('id', $id)->firstOrFail();

        return view('admin.email_logs.show', [
            'emailLog' => $emailLog,
        ]);
    }
}
