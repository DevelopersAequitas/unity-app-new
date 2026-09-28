<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactInvitation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactInvitationController extends Controller
{
    /**
     * Display a listing of referral contact invitations.
     */
    public function index(Request $request): View
    {
        $filters = $request->only([
            'search',
            'status',
            'user_id',
            'from_date',
            'to_date',
            'quick',
            'date_preset',
        ]);

        $activePreset = $filters['date_preset'] ?? $filters['quick'] ?? '';
        if ($activePreset === 'all_time' || $activePreset === 'all' || $activePreset === 'any') {
            $activePreset = '';
        }
        $filters['date_preset'] = $activePreset;

        $presets = [
            'today'        => 'Today',
            'yesterday'    => 'Yesterday',
            'this_week'    => 'This Week',
            'last_week'    => 'Last Week',
            'this_month'   => 'This Month',
            'last_month'   => 'Last Month',
            'this_quarter' => 'This Quarter',
            'last_quarter' => 'Last Quarter',
            'this_year'    => 'This Year',
            'last_year'    => 'Last Year',
            'last_7_days'  => 'Last 7 Days',
            'last_30_days' => 'Last 30 Days',
            'last_90_days' => 'Last 90 Days',
        ];

        $query = ContactInvitation::query()
            ->with(['user', 'contactPost']);

        $this->applyFilters($query, $filters);

        $invitations = $query->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        // Summary Counts (calculated across all or current date scope)
        $totalInvitations = ContactInvitation::query()->count();
        $totalCompleted = ContactInvitation::query()->where('whatsapp_status', 'completed')->count();
        $totalNotCompleted = ContactInvitation::query()->where('whatsapp_status', '!=', 'completed')->count();
        $totalUniqueReferrers = ContactInvitation::query()->distinct('user_id')->count('user_id');

        $users = User::query()
            ->whereIn('id', ContactInvitation::query()->select('user_id')->distinct())
            ->orderBy('first_name')
            ->get();

        return view('admin.contact-invitations.index', [
            'invitations' => $invitations,
            'filters' => $filters,
            'presets' => $presets,
            'activePreset' => $activePreset,
            'activeStatus' => $filters['status'] ?? '',
            'users' => $users,
            'stats' => [
                'total_invitations' => $totalInvitations,
                'total_completed' => $totalCompleted,
                'total_not_completed' => $totalNotCompleted,
                'total_referrers' => $totalUniqueReferrers,
            ],
        ]);
    }

    /**
     * Show details of a specific invitation.
     */
    public function show(string $id): View
    {
        $invitation = ContactInvitation::with(['user', 'contactPost'])->findOrFail($id);

        return view('admin.contact-invitations.show', [
            'invitation' => $invitation,
        ]);
    }

    /**
     * Export filtered contact invitations as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->only([
            'search',
            'status',
            'user_id',
            'from_date',
            'to_date',
            'quick',
        ]);

        $fileName = 'invitation-contacts-'.now()->format('Y-m-d-His').'.csv';
        $columns = [
            'ID',
            'Sender Name',
            'Sender Phone',
            'Sender Email',
            'Invited Contact Name',
            'Contact Phone',
            'Contact Email',
            'WhatsApp Status',
            'Sent Date & Time',
            'Invitation Created At',
            'Error / Log Message',
        ];

        return response()->streamDownload(function () use ($filters, $columns): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, $columns);

            $query = ContactInvitation::query()
                ->with(['user']);

            $this->applyFilters($query, $filters);

            $query->latest('created_at')->chunk(500, function ($rows) use ($output): void {
                foreach ($rows as $row) {
                    fputcsv($output, [
                        $row->id,
                        $row->user?->adminDisplayName() ?? '—',
                        $row->user?->phone ?? '—',
                        $row->user?->email ?? '—',
                        $row->contact_name,
                        $row->contact_phone,
                        $row->contact_email ?? '—',
                        $row->whatsapp_status === 'completed' ? 'Completed' : 'Not Completed',
                        $row->whatsapp_sent_at ? $row->whatsapp_sent_at->format('Y-m-d H:i:s') : '—',
                        $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : '—',
                        $row->error_message ?? '—',
                    ]);
                }
            });

            fclose($output);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    /**
     * Apply search, date range, quick filter and status filters.
     */
    private function applyFilters($query, array $filters): void
    {
        // Search Filter
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('contact_name', 'ILIKE', "%{$search}%")
                    ->orWhere('contact_phone', 'ILIKE', "%{$search}%")
                    ->orWhere('mobile_normalized', 'ILIKE', "%{$search}%")
                    ->orWhere('contact_email', 'ILIKE', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('first_name', 'ILIKE', "%{$search}%")
                            ->orWhere('last_name', 'ILIKE', "%{$search}%")
                            ->orWhere('display_name', 'ILIKE', "%{$search}%")
                            ->orWhere('phone', 'ILIKE', "%{$search}%")
                            ->orWhere('email', 'ILIKE', "%{$search}%");
                    });
            });
        }

        // User / Sender Filter
        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Status Filter
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'completed' || $filters['status'] === 'sent') {
                $query->where('whatsapp_status', 'completed');
            } elseif ($filters['status'] === 'not_completed' || $filters['status'] === 'failed') {
                $query->where('whatsapp_status', '!=', 'completed');
            }
        }

        // Date Preset / Quick Filter
        $preset = $filters['date_preset'] ?? $filters['quick'] ?? '';
        if ($preset && ! in_array($preset, ['all_time', 'all', 'any'], true)) {
            [$from, $to] = $this->resolveDateRange($preset);
            if ($from && $to) {
                $query->whereBetween('created_at', [$from, $to]);
            }
        }

        // Custom Date Range
        if (! empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', Carbon::parse($filters['from_date'])->startOfDay());
        }
        if (! empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', Carbon::parse($filters['to_date'])->endOfDay());
        }
    }

    /**
     * Resolve date ranges for presets.
     */
    private function resolveDateRange(string $preset): array
    {
        $now = Carbon::now();

        return match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week' => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'last_quarter' => [$now->copy()->subQuarter()->startOfQuarter(), $now->copy()->subQuarter()->endOfQuarter()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year' => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'last_7_days' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_30_days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'last_90_days' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            default => [null, null],
        };
    }
}
