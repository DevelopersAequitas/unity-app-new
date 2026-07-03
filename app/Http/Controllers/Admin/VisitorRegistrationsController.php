<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\User;
use App\Models\VisitorRegistration;
use App\Services\Coins\CoinsService;
use App\Support\AdminCircleScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitorRegistrationsController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));
        $circleId = (string) $request->query('circle_id', 'all');

        $peerQ = trim((string) $request->query('peer_q', ''));
        $peerPhone = trim((string) $request->query('peer_phone', ''));
        $eventType = trim((string) $request->query('event_type', ''));
        $eventName = trim((string) $request->query('event_name', ''));
        $eventDate = trim((string) $request->query('event_date', ''));
        $visitorName = trim((string) $request->query('visitor_name', ''));
        $visitorMobile = trim((string) $request->query('visitor_mobile', ''));
        $visitorCity = trim((string) $request->query('visitor_city', ''));
        $visitorBusiness = trim((string) $request->query('visitor_business', ''));

        $hasUsersName = Schema::hasColumn('users', 'name');
        $hasUsersCompany = Schema::hasColumn('users', 'company');
        $hasUsersBusinessName = Schema::hasColumn('users', 'business_name');

        $query = VisitorRegistration::query()
            ->with([
                'user',
                'user.circleMembers' => function ($circleMembersQuery) {
                    $circleMembersQuery
                        ->where('status', 'approved')
                        ->whereNull('deleted_at')
                        ->orderByDesc('joined_at')
                        ->with(['circle:id,name']);
                },
            ]);

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($circleId !== '' && $circleId !== 'all') {
            $query->whereHas('user.circleMembers', function ($circleMembersQuery) use ($circleId) {
                $circleMembersQuery
                    ->where('circle_id', $circleId)
                    ->where('status', 'approved')
                    ->whereNull('deleted_at');
            });
        }

        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like, $hasUsersName, $hasUsersCompany, $hasUsersBusinessName) {
                $q->where('visitor_full_name', 'ILIKE', $like)
                    ->orWhere('visitor_mobile', 'ILIKE', $like)
                    ->orWhere('visitor_city', 'ILIKE', $like)
                    ->orWhere('visitor_business', 'ILIKE', $like)
                    ->orWhere('event_type', 'ILIKE', $like)
                    ->orWhere('event_name', 'ILIKE', $like)
                    ->orWhereHas('user', function ($userQuery) use ($like, $hasUsersName, $hasUsersCompany, $hasUsersBusinessName) {
                        $userQuery->where(function ($uq) use ($like, $hasUsersName, $hasUsersCompany, $hasUsersBusinessName) {
                            $uq->where('display_name', 'ILIKE', $like)
                                ->orWhere('first_name', 'ILIKE', $like)
                                ->orWhere('last_name', 'ILIKE', $like)
                                ->orWhere('phone', 'ILIKE', $like)
                                ->orWhere('email', 'ILIKE', $like)
                                ->orWhere('company_name', 'ILIKE', $like)
                                ->orWhere('city', 'ILIKE', $like);

                            if ($hasUsersName) {
                                $uq->orWhere('name', 'ILIKE', $like);
                            }

                            if ($hasUsersCompany) {
                                $uq->orWhere('company', 'ILIKE', $like);
                            }

                            if ($hasUsersBusinessName) {
                                $uq->orWhere('business_name', 'ILIKE', $like);
                            }
                        })->orWhereHas('circleMembers.circle', function ($circleQuery) use ($like) {
                            $circleQuery->where('name', 'ILIKE', $like);
                        });
                    });
            });
        }

        if ($peerQ !== '') {
            $like = "%{$peerQ}%";
            $query->whereHas('user', function ($userQuery) use ($like, $hasUsersName, $hasUsersCompany, $hasUsersBusinessName) {
                $userQuery->where(function ($uq) use ($like, $hasUsersName, $hasUsersCompany, $hasUsersBusinessName) {
                    $uq->where('display_name', 'ILIKE', $like)
                        ->orWhere('first_name', 'ILIKE', $like)
                        ->orWhere('last_name', 'ILIKE', $like)
                        ->orWhere('email', 'ILIKE', $like)
                        ->orWhere('company_name', 'ILIKE', $like)
                        ->orWhere('city', 'ILIKE', $like);

                    if ($hasUsersName) {
                        $uq->orWhere('name', 'ILIKE', $like);
                    }

                    if ($hasUsersCompany) {
                        $uq->orWhere('company', 'ILIKE', $like);
                    }

                    if ($hasUsersBusinessName) {
                        $uq->orWhere('business_name', 'ILIKE', $like);
                    }
                });
            });
        }

        if ($peerPhone !== '') {
            $query->whereHas('user', fn ($userQuery) => $userQuery->where('phone', 'ILIKE', "%{$peerPhone}%"));
        }

        if ($eventType !== '') {
            $query->where('event_type', 'ILIKE', "%{$eventType}%");
        }

        if ($eventName !== '') {
            $query->where('event_name', 'ILIKE', "%{$eventName}%");
        }

        if ($eventDate !== '') {
            $query->whereDate('event_date', $eventDate);
        }

        if ($visitorName !== '') {
            $query->where('visitor_full_name', 'ILIKE', "%{$visitorName}%");
        }

        if ($visitorMobile !== '') {
            $query->where('visitor_mobile', 'ILIKE', "%{$visitorMobile}%");
        }

        if ($visitorCity !== '') {
            $query->where('visitor_city', 'ILIKE', "%{$visitorCity}%");
        }

        if ($visitorBusiness !== '') {
            $query->where('visitor_business', 'ILIKE', "%{$visitorBusiness}%");
        }

        AdminCircleScope::applyToActivityQuery($query, Auth::guard('admin')->user(), 'visitor_registrations.user_id', null);

        $registrations = $query
            ->orderByDesc('created_at')
            ->paginate(25)
            ->appends($request->query());

        $circles = Circle::query()->orderBy('name')->get(['id', 'name']);

        $usersQuery = User::query()
            ->whereNull('deleted_at')
            ->orderBy('first_name')
            ->orderBy('last_name');
        AdminCircleScope::applyToUsersQuery($usersQuery, Auth::guard('admin')->user());
        $users = $usersQuery->get(['id', 'first_name', 'last_name', 'display_name', 'company_name', 'phone']);

        return view('admin.visitor_registrations.index', [
            'registrations' => $registrations,
            'circles' => $circles,
            'users' => $users,
            'filters' => [
                'status' => in_array($status, ['all', 'pending', 'approved', 'rejected'], true) ? $status : 'all',
                'search' => $search,
                'circle_id' => $circleId,
                'peer_q' => $peerQ,
                'peer_phone' => $peerPhone,
                'event_type' => $eventType,
                'event_name' => $eventName,
                'event_date' => $eventDate,
                'visitor_name' => $visitorName,
                'visitor_mobile' => $visitorMobile,
                'visitor_city' => $visitorCity,
                'visitor_business' => $visitorBusiness,
            ],
            'statuses' => ['pending', 'approved', 'rejected'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'visitor_full_name' => ['required', 'string', 'max:255'],
            'visitor_mobile' => ['required', 'string', 'max:50'],
            'visitor_email' => ['required', 'email', 'max:255'],
            'event_type' => ['nullable', 'string', 'max:255'],
            'event_name' => ['nullable', 'string', 'max:255'],
            'event_date' => ['nullable', 'date'],
            'visitor_city' => ['nullable', 'string', 'max:255'],
            'visitor_business' => ['nullable', 'string', 'max:255'],
            'visitor_designation' => ['nullable', 'string', 'max:255'],
            'visitor_business_brief' => ['nullable', 'string'],
        ]);

        $admin = Auth::guard('admin')->user();
        if (! AdminCircleScope::userInScope($admin, $validated['user_id'])) {
            abort(403);
        }

        $data = array_merge([
            'event_type' => 'Physical',
            'event_name' => 'General Meeting',
            'event_date' => now()->format('Y-m-d'),
        ], array_filter($validated, fn ($val) => $val !== null && $val !== ''));

        VisitorRegistration::create(array_merge($data, [
            'status' => 'pending',
            'coins_awarded' => false,
        ]));

        return redirect()
            ->route('admin.visitor-registrations.index')
            ->with('success', 'Visitor registration added successfully.');
    }

    public function export(Request $request): StreamedResponse
    {
        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));
        $circleId = (string) $request->query('circle_id', 'all');

        $peerQ = trim((string) $request->query('peer_q', ''));
        $peerPhone = trim((string) $request->query('peer_phone', ''));
        $eventType = trim((string) $request->query('event_type', ''));
        $eventName = trim((string) $request->query('event_name', ''));
        $eventDate = trim((string) $request->query('event_date', ''));
        $visitorName = trim((string) $request->query('visitor_name', ''));
        $visitorMobile = trim((string) $request->query('visitor_mobile', ''));
        $visitorCity = trim((string) $request->query('visitor_city', ''));
        $visitorBusiness = trim((string) $request->query('visitor_business', ''));

        $query = VisitorRegistration::query()
            ->with([
                'user',
                'user.circleMembers' => function ($circleMembersQuery) {
                    $circleMembersQuery
                        ->where('status', 'approved')
                        ->whereNull('deleted_at')
                        ->orderByDesc('joined_at')
                        ->with(['circle:id,name']);
                },
            ]);

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($circleId !== '' && $circleId !== 'all') {
            $query->whereHas('user.circleMembers', function ($circleMembersQuery) use ($circleId) {
                $circleMembersQuery
                    ->where('circle_id', $circleId)
                    ->where('status', 'approved')
                    ->whereNull('deleted_at');
            });
        }

        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('visitor_full_name', 'ILIKE', $like)
                    ->orWhere('visitor_mobile', 'ILIKE', $like)
                    ->orWhere('visitor_city', 'ILIKE', $like)
                    ->orWhere('visitor_business', 'ILIKE', $like)
                    ->orWhere('event_type', 'ILIKE', $like)
                    ->orWhere('event_name', 'ILIKE', $like);
            });
        }

        if ($peerQ !== '') {
            $like = "%{$peerQ}%";
            $query->whereHas('user', function ($uq) use ($like) {
                $uq->where('display_name', 'ILIKE', $like)
                    ->orWhere('first_name', 'ILIKE', $like)
                    ->orWhere('last_name', 'ILIKE', $like)
                    ->orWhere('company_name', 'ILIKE', $like);
            });
        }

        if ($peerPhone !== '') {
            $query->whereHas('user', fn ($uq) => $uq->where('phone', 'ILIKE', "%{$peerPhone}%"));
        }

        if ($eventType !== '') {
            $query->where('event_type', 'ILIKE', "%{$eventType}%");
        }

        if ($eventName !== '') {
            $query->where('event_name', 'ILIKE', "%{$eventName}%");
        }

        if ($eventDate !== '') {
            $query->whereDate('event_date', $eventDate);
        }

        if ($visitorName !== '') {
            $query->where('visitor_full_name', 'ILIKE', "%{$visitorName}%");
        }

        if ($visitorMobile !== '') {
            $query->where('visitor_mobile', 'ILIKE', "%{$visitorMobile}%");
        }

        if ($visitorCity !== '') {
            $query->where('visitor_city', 'ILIKE', "%{$visitorCity}%");
        }

        if ($visitorBusiness !== '') {
            $query->where('visitor_business', 'ILIKE', "%{$visitorBusiness}%");
        }

        AdminCircleScope::applyToActivityQuery($query, Auth::guard('admin')->user(), 'visitor_registrations.user_id', null);

        $fileName = 'visitors-'.now()->format('Y-m-d-His').'.csv';
        $columns = [
            'Submitted At',
            'Peer Name',
            'Peer Phone',
            'Peer Company',
            'Peer Circle',
            'Event Type',
            'Event Name',
            'Event Date',
            'Visitor Name',
            'Visitor Mobile',
            'Visitor Email',
            'Visitor City',
            'Visitor Business',
            'Status',
        ];

        return response()->streamDownload(function () use ($query, $columns): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, $columns);

            $query->chunk(250, function ($rows) use ($output): void {
                foreach ($rows as $row) {
                    $member = $row->user;
                    $memberName = $member ? trim(($member->first_name ?? '').' '.($member->last_name ?? '')) : '—';
                    $memberCompany = $member ? ($member->company_name ?? $member->company ?? '—') : '—';
                    $memberCircle = $member && $member->circleMembers->first() ? optional($member->circleMembers->first()->circle)->name : '—';

                    fputcsv($output, [
                        $row->created_at ? $row->created_at->format('Y-m-d H:i') : '—',
                        $memberName,
                        $member ? $member->phone : '—',
                        $memberCompany,
                        $memberCircle,
                        ucfirst($row->event_type ?? '—'),
                        $row->event_name ?? '—',
                        $row->event_date ? $row->event_date->format('Y-m-d') : '—',
                        $row->visitor_full_name ?? '—',
                        $row->visitor_mobile ?? '—',
                        $row->visitor_email ?? '—',
                        $row->visitor_city ?? '—',
                        $row->visitor_business ?? '—',
                        ucfirst($row->status ?? '—'),
                    ]);
                }
            });

            fclose($output);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    public function exportSingle(string $id): StreamedResponse
    {
        if (! Str::isUuid($id)) {
            abort(404);
        }

        $admin = Auth::guard('admin')->user();
        $row = VisitorRegistration::query()
            ->with([
                'user',
                'user.circleMembers' => function ($circleMembersQuery) {
                    $circleMembersQuery
                        ->where('status', 'approved')
                        ->whereNull('deleted_at')
                        ->orderByDesc('joined_at')
                        ->with(['circle:id,name']);
                },
            ])
            ->where('id', $id)
            ->firstOrFail();

        if (! AdminCircleScope::userInScope($admin, $row->user_id)) {
            abort(403);
        }

        $fileName = 'visitor-'.strtolower(str_replace(' ', '-', $row->visitor_full_name ?? 'details')).'-'.now()->format('Y-m-d').'.csv';
        $columns = [
            'Submitted At',
            'Peer Name',
            'Peer Phone',
            'Peer Company',
            'Peer Circle',
            'Event Type',
            'Event Name',
            'Event Date',
            'Visitor Name',
            'Visitor Mobile',
            'Visitor Email',
            'Visitor City',
            'Visitor Business',
            'Status',
        ];

        return response()->streamDownload(function () use ($row, $columns): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, $columns);

            $member = $row->user;
            $memberName = $member ? trim(($member->first_name ?? '').' '.($member->last_name ?? '')) : '—';
            $memberCompany = $member ? ($member->company_name ?? $member->company ?? '—') : '—';
            $memberCircle = $member && $member->circleMembers->first() ? optional($member->circleMembers->first()->circle)->name : '—';

            fputcsv($output, [
                $row->created_at ? $row->created_at->format('Y-m-d H:i') : '—',
                $memberName,
                $member ? $member->phone : '—',
                $memberCompany,
                $memberCircle,
                ucfirst($row->event_type ?? '—'),
                $row->event_name ?? '—',
                $row->event_date ? $row->event_date->format('Y-m-d') : '—',
                $row->visitor_full_name ?? '—',
                $row->visitor_mobile ?? '—',
                $row->visitor_email ?? '—',
                $row->visitor_city ?? '—',
                $row->visitor_business ?? '—',
                ucfirst($row->status ?? '—'),
            ]);

            fclose($output);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    public function approve(string $id, CoinsService $coinsService): RedirectResponse
    {
        if (! Str::isUuid($id)) {
            abort(404);
        }

        $admin = Auth::guard('admin')->user();
        $message = DB::transaction(function () use ($id, $admin, $coinsService) {
            $registration = VisitorRegistration::query()
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! AdminCircleScope::userInScope($admin, $registration->user_id)) {
                abort(403);
            }

            if ($registration->status === 'approved' || $registration->coins_awarded) {
                return 'Already approved.';
            }

            $registration->status = 'approved';
            $registration->reviewed_at = now();
            $registration->reviewed_by_admin_user_id = $admin?->id;
            $registration->save();

            $amount = (int) config('coins.register_visitor', 0);
            $ledger = $coinsService->reward($registration->user, $amount, 'Register a Visitor (Approved)');

            if ($ledger) {
                $registration->coins_awarded = true;
                $registration->coins_awarded_at = now();
                $registration->save();
            }

            return 'Visitor registration approved.';
        });

        return redirect()
            ->back()
            ->with('success', $message);
    }

    public function reject(string $id): RedirectResponse
    {
        if (! Str::isUuid($id)) {
            abort(404);
        }

        $admin = Auth::guard('admin')->user();
        $message = DB::transaction(function () use ($id, $admin) {
            $registration = VisitorRegistration::query()
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! AdminCircleScope::userInScope($admin, $registration->user_id)) {
                abort(403);
            }

            if ($registration->status === 'approved') {
                return 'Already approved.';
            }

            $registration->status = 'rejected';
            $registration->reviewed_at = now();
            $registration->reviewed_by_admin_user_id = $admin?->id;
            $registration->save();

            return 'Visitor registration rejected.';
        });

        return redirect()
            ->back()
            ->with('success', $message);
    }

    public function sampleCsv(): StreamedResponse
    {
        $fileName = 'sample-visitor-import.csv';
        $columns = [
            'peer_email',
            'peer_phone',
            'visitor_name',
            'visitor_mobile',
            'visitor_email',
            'event_type',
            'event_name',
            'event_date',
            'visitor_city',
            'visitor_business',
            'visitor_designation',
            'visitor_business_brief',
        ];

        return response()->streamDownload(function () use ($columns): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, $columns);
            fputcsv($output, [
                'peer@example.com',
                '9876543210',
                'Arpan Pandya',
                '+91 96621 72149',
                'arpan@example.com',
                'Physical',
                'Buildcon Circle Meet',
                now()->format('Y-m-d'),
                'Ahmedabad',
                'Interior Designer',
                'Design Consultant',
                'Experienced interior designer specializing in residential projects',
            ]);
            fclose($output);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    public function importStore(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'default_user_id' => ['nullable', 'uuid', 'exists:users,id'],
        ]);

        $defaultUserId = trim((string) $request->input('default_user_id', ''));
        $admin = Auth::guard('admin')->user();

        if ($defaultUserId !== '' && ! AdminCircleScope::userInScope($admin, $defaultUserId)) {
            return back()->withErrors(['csv_file' => 'Selected default peer is outside your assigned scope.'])->withInput();
        }

        $path = $request->file('csv_file')->getRealPath();
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return back()->withErrors(['csv_file' => 'Unable to read the uploaded CSV file.'])->withInput();
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return back()->withErrors(['csv_file' => 'CSV file is empty.'])->withInput();
        }

        $header = array_map(fn ($column) => Str::of((string) $column)->trim()->lower()->toString(), $header);

        $imported = 0;
        $rowNumber = 1;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }

            $rowData = [];
            foreach ($header as $index => $columnName) {
                $rowData[$columnName] = isset($row[$index]) ? trim((string) $row[$index]) : '';
            }

            $visitorName = $rowData['visitor_name'] ?? ($rowData['visitor_full_name'] ?? ($rowData['name'] ?? ''));
            $visitorMobile = $rowData['visitor_mobile'] ?? ($rowData['mobile'] ?? ($rowData['phone'] ?? ''));
            $visitorEmail = $rowData['visitor_email'] ?? ($rowData['email'] ?? '');

            if ($visitorName === '') {
                $errors[] = "Row {$rowNumber}: Visitor name is required.";

                continue;
            }

            if ($visitorMobile === '') {
                $errors[] = "Row {$rowNumber}: Visitor mobile is required.";

                continue;
            }

            if ($visitorEmail === '' || ! filter_var($visitorEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$rowNumber}: Valid visitor email is required.";

                continue;
            }

            $peerUserId = null;
            $peerEmail = $rowData['peer_email'] ?? '';
            $peerPhone = $rowData['peer_phone'] ?? '';
            $peerId = $rowData['peer_id'] ?? ($rowData['user_id'] ?? '');

            if ($peerId !== '' && Str::isUuid($peerId)) {
                $peerUserId = $peerId;
            } elseif ($peerEmail !== '') {
                $peerUserId = User::query()->where('email', 'ILIKE', $peerEmail)->value('id');
            } elseif ($peerPhone !== '') {
                $peerUserId = User::query()->where('phone', 'ILIKE', "%{$peerPhone}%")->value('id');
            }

            if (! $peerUserId && $defaultUserId !== '') {
                $peerUserId = $defaultUserId;
            }

            if (! $peerUserId) {
                $errors[] = "Row {$rowNumber}: Peer (Inviter) could not be resolved. Please specify peer_email/phone or select a default peer.";

                continue;
            }

            if (! AdminCircleScope::userInScope($admin, (string) $peerUserId)) {
                $errors[] = "Row {$rowNumber}: Resolved peer is outside your scope.";

                continue;
            }

            VisitorRegistration::create([
                'user_id' => $peerUserId,
                'visitor_full_name' => $visitorName,
                'visitor_mobile' => $visitorMobile,
                'visitor_email' => $visitorEmail,
                'event_type' => ! empty($rowData['event_type']) ? $rowData['event_type'] : 'Physical',
                'event_name' => ! empty($rowData['event_name']) ? $rowData['event_name'] : 'General Meeting',
                'event_date' => ! empty($rowData['event_date']) ? $rowData['event_date'] : now()->format('Y-m-d'),
                'visitor_city' => ! empty($rowData['visitor_city']) ? $rowData['visitor_city'] : null,
                'visitor_business' => ! empty($rowData['visitor_business']) ? $rowData['visitor_business'] : null,
                'visitor_designation' => ! empty($rowData['visitor_designation']) ? $rowData['visitor_designation'] : null,
                'visitor_business_brief' => ! empty($rowData['visitor_business_brief']) ? $rowData['visitor_business_brief'] : null,
                'status' => 'pending',
                'coins_awarded' => false,
            ]);

            $imported++;
        }

        fclose($handle);

        if ($imported === 0 && ! empty($errors)) {
            return back()->withErrors(['csv_file' => implode(' | ', array_slice($errors, 0, 5))])->withInput();
        }

        $message = "Successfully imported {$imported} visitor registration(s).";
        if (! empty($errors)) {
            $message .= ' (Some rows had warnings: '.implode(' | ', array_slice($errors, 0, 3)).')';
        }

        return redirect()
            ->route('admin.visitor-registrations.index')
            ->with('success', $message);
    }
}
