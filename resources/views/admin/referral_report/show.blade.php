@extends('admin.layouts.app')

@section('title', 'Referred Users')

@section('content')
<div class="bg-white border rounded-4 shadow-sm p-3 p-lg-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-bold">Users Referred by {{ $summary?->referrer_name ?: 'Deleted / Unknown User' }}</h1>
            <div class="text-muted small">
                <span>Email: {{ $summary?->referrer_email ?: 'No email' }}</span>
                <span class="mx-1">•</span><span>Phone: {{ $summary?->referrer_phone ?: 'No phone' }}</span>
                @if($summary?->referrer_company)
                    <span class="mx-1">•</span><span>Company: {{ $summary->referrer_company }}</span>
                @endif
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a href="{{ route('admin.referral-report.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <span class="badge bg-light text-dark border p-2">Referral Code: {{ $summary?->referral_codes ?: '—' }}</span>
            <span class="badge bg-light text-dark border p-2">Total Users: {{ number_format((int) ($summary?->total_referred_users ?? 0)) }}</span>
            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle p-2">Coins Granted: {{ number_format((int) ($summary?->total_coins_granted ?? 0)) }}</span>
        </div>
    </div>

    <form id="referralUsersFilters" method="GET" action="{{ route('admin.referral-report.show', $referrerUserId) }}"></form>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="d-flex gap-2 align-items-center">
            <label for="perPage" class="small text-muted mb-0">Show</label>
            <select id="perPage" name="per_page" form="referralUsersFilters" class="form-select form-select-sm" style="width: 90px;">
                @foreach ([10, 20, 50, 100] as $size)
                    <option value="{{ $size }}" @selected(($filters['per_page'] ?? 20) == $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>
        <div class="small text-muted">
            @if($records->total() > 0)
                Records {{ $records->firstItem() }} to {{ $records->lastItem() }} of {{ $records->total() }}
            @else
                No records found
            @endif
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead class="table-light">
                <tr>
                    <th>Referred User</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Company</th>
                    <th>City</th>
                    <th>Referral Code Used</th>
                    <th class="text-center">Coins Granted</th>
                    <th>Reward Status</th>
                    <th>Used At</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark">
                                @if(!empty($record->referred_user_id))
                                    <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $record->referred_user_id }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                        {{ $record->referred_name ?: 'Deleted / Unknown User' }}
                                    </a>
                                @else
                                    {{ $record->referred_name ?: 'Deleted / Unknown User' }}
                                @endif
                            </div>
                        </td>
                        <td class="text-muted small">{{ $record->referred_email ?: '—' }}</td>
                        <td class="text-muted small">{{ $record->referred_phone ?: '—' }}</td>
                        <td>{{ $record->company_name ?: '—' }}</td>
                        <td class="text-muted small">{{ $record->city ?: '—' }}</td>
                        <td><span class="badge bg-light text-dark border">{{ $record->referral_code ?: '—' }}</span></td>
                        <td class="text-center">{{ number_format((int) $record->coins) }}</td>
                        <td>
                            @php
                                $status = strtolower((string) $record->reward_status);
                                $statusClass = match ($status) {
                                    'granted' => 'bg-success-subtle text-success-emphasis border-success-subtle',
                                    'failed' => 'bg-danger-subtle text-danger-emphasis border-danger-subtle',
                                    'pending' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                                    default => 'bg-light text-dark border-light',
                                };
                            @endphp
                            <span class="badge border {{ $statusClass }}">{{ $record->reward_status ?: '—' }}</span>
                        </td>
                        <td>{{ $record->used_at ? \Illuminate\Support\Carbon::parse($record->used_at)->format('d-m-Y h:i A') : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No referred users found for this referrer.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-2">
        {{ $records->links() }}
    </div>
</div>
@endsection
