@extends('admin.layouts.app')

@section('title', 'Peers Store — Wallet Ledger: ' . ($user->name ?? 'Peer'))

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.wallet.index') }}" class="text-decoration-none text-muted">Wallets</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $user->name }}</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-person-badge text-primary"></i> Wallet Ledger: {{ $user->name }}
            </h1>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-warning d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#requestAdjustModal">
                <i class="bi bi-pencil-square"></i> Request Adjustment
            </button>
            <button class="btn {{ ($user->is_store_frozen ?? false) ? 'btn-outline-success' : 'btn-outline-danger' }} d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#freezeModal">
                <i class="bi {{ ($user->is_store_frozen ?? false) ? 'bi-unlock-fill' : 'bi-lock-fill' }}"></i>
                {{ ($user->is_store_frozen ?? false) ? 'Unfreeze Wallet' : 'Freeze Wallet' }}
            </button>
            <a href="{{ route('admin.store.wallet.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Back to Wallets
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- User Summary & Balances --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold fs-4" style="width: 54px; height: 54px;">
                            {{ strtoupper(substr($user->name ?? 'P', 0, 1)) }}
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">{{ $user->name }}</h5>
                            <span class="text-muted small">ID: #{{ $user->id }} &bull; {{ $user->circle->name ?? 'No Circle' }}</span>
                        </div>
                    </div>
                    <div class="border-top pt-2 small text-muted">
                        <div class="d-flex justify-content-between py-1">
                            <span>Phone:</span>
                            <span class="fw-semibold text-dark">{{ $user->phone_number ?? $user->mobile ?: '—' }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span>Email:</span>
                            <span class="fw-semibold text-dark">{{ $user->email ?: '—' }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span>Wallet State:</span>
                            <span>
                                @if($user->is_store_frozen ?? false)
                                    <span class="badge bg-danger"><i class="bi bi-lock-fill"></i> Frozen</span>
                                @else
                                    <span class="badge bg-success"><i class="bi bi-unlock-fill"></i> Active</span>
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="row g-3 h-100">
                <div class="col-sm-4">
                    <div class="card shadow-sm border-0 h-100 bg-primary bg-opacity-10 border-primary border-opacity-25">
                        <div class="card-body d-flex flex-column justify-content-between">
                            <span class="text-muted fw-semibold small text-uppercase">Total Spendable Balance</span>
                            <div class="my-2">
                                <span class="fs-2 fw-bold text-primary">{{ number_format($totalBalance) }}</span>
                                <span class="text-muted small ms-1">Coins</span>
                            </div>
                            <span class="small text-muted">Current usable balance</span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card shadow-sm border-0 h-100 bg-success bg-opacity-10 border-success border-opacity-25">
                        <div class="card-body d-flex flex-column justify-content-between">
                            <span class="text-muted fw-semibold small text-uppercase">Earned Coins</span>
                            <div class="my-2">
                                <span class="fs-2 fw-bold text-success">{{ number_format($earnedBalance) }}</span>
                                <span class="text-muted small ms-1">Coins</span>
                            </div>
                            <span class="small text-muted">Meetings, Referrals & Deals</span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card shadow-sm border-0 h-100 bg-info bg-opacity-10 border-info border-opacity-25">
                        <div class="card-body d-flex flex-column justify-content-between">
                            <span class="text-muted fw-semibold small text-uppercase">Bonus Coins</span>
                            <div class="my-2">
                                <span class="fs-2 fw-bold text-info">{{ number_format($bonusBalance) }}</span>
                                <span class="text-muted small ms-1">Coins</span>
                            </div>
                            <span class="small text-muted">Promotional Grants</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Pending Maker-Checker Adjustments --}}
    @if($pendingAdjustments->isNotEmpty())
        <div class="card shadow-sm border-warning mb-4">
            <div class="card-header bg-warning-subtle text-dark fw-bold d-flex align-items-center gap-2">
                <i class="bi bi-clock-history text-warning"></i> Pending Maker-Checker Adjustment Requests for this Peer
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Request ID</th>
                                <th>Coin Bucket</th>
                                <th>Action</th>
                                <th>Amount</th>
                                <th>Requested By</th>
                                <th>Reason</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingAdjustments as $adj)
                                <tr>
                                    <td class="ps-3 fw-bold">#{{ $adj->id }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ ucfirst($adj->coin_type) }}</span></td>
                                    <td><span class="badge {{ $adj->action === 'credit' ? 'bg-success' : 'bg-danger' }}">{{ ucfirst($adj->action) }}</span></td>
                                    <td class="fw-bold">{{ number_format($adj->amount) }} Coins</td>
                                    <td>{{ $adj->requester->name ?? 'Admin #' . $adj->requested_by }}</td>
                                    <td>{{ $adj->reason }}</td>
                                    <td class="text-muted small">{{ ($adj->requested_at ?? $adj->created_at) ? \Carbon\Carbon::parse($adj->requested_at ?? $adj->created_at)->format('d M Y, h:i A') : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Append-Only Ledger History --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="card-title fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-journal-text text-primary"></i> Append-Only Coin Ledger History
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 15%;">Timestamp</th>
                            <th style="width: 15%;">Type / Action</th>
                            <th style="width: 15%;">Amount</th>
                            <th style="width: 15%;">Balance After</th>
                            <th class="pe-4" style="width: 40%;">Description & Source Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ledgers as $entry)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold text-dark">{{ $entry->created_at ? \Carbon\Carbon::parse($entry->created_at)->format('d M Y') : '—' }}</div>
                                    <small class="text-muted">{{ $entry->created_at ? \Carbon\Carbon::parse($entry->created_at)->format('h:i A') : '' }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        {{ ucfirst(str_replace('_', ' ', $entry->source_type ?? $entry->type ?? 'Transaction')) }}
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $isCredit = ($entry->coins ?? $entry->amount ?? 0) > 0;
                                        $amt = abs($entry->coins ?? $entry->amount ?? 0);
                                    @endphp
                                    <span class="fw-bold fs-6 {{ $isCredit ? 'text-success' : 'text-danger' }}">
                                        {{ $isCredit ? '+' : '-' }}{{ number_format($amt) }} Coins
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark">{{ number_format($entry->closing_balance ?? $entry->balance_after ?? 0) }}</span>
                                </td>
                                <td class="pe-4">
                                    <div class="text-dark small">{{ ($entry->description ?? $entry->remark ?? $entry->reference) ?: 'No note' }}</div>
                                    @if(!empty($entry->source_id))
                                        <small class="text-muted">Source Ref: #{{ $entry->source_id }}</small>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary"></i>
                                    No ledger entries recorded for this user yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($ledgers->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $ledgers->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Freeze Modal --}}
<div class="modal fade" id="freezeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.store.wallet.freeze', $user->id) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi {{ ($user->is_store_frozen ?? false) ? 'bi-unlock text-success' : 'bi-lock text-danger' }}"></i>
                        {{ ($user->is_store_frozen ?? false) ? 'Unfreeze Peer Wallet' : 'Freeze Peer Wallet' }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        {{ ($user->is_store_frozen ?? false) 
                            ? 'Unfreezing will restore full store purchase and redemption privileges for ' . $user->name . '.'
                            : 'Freezing will prevent ' . $user->name . ' from placing store orders or burning coins until unfrozen by an authorized administrator.' }}
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason for Action <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Provide reason for audit log..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn {{ ($user->is_store_frozen ?? false) ? 'btn-success' : 'btn-danger' }}">
                        {{ ($user->is_store_frozen ?? false) ? 'Confirm Unfreeze' : 'Confirm Freeze' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Request Adjustment Modal --}}
<div class="modal fade" id="requestAdjustModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.store.wallet.adjustments.request') }}">
                @csrf
                <input type="hidden" name="user_id" value="{{ $user->id }}">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-shield-lock text-warning"></i> Request Coin Adjustment: {{ $user->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        <i class="bi bi-info-circle-fill me-1"></i>
                        <strong>Maker-Checker Requirement:</strong> Balance updates cannot be executed directly. Another authorized admin must review and approve this request.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Coin Bucket <span class="text-danger">*</span></label>
                        <select name="coin_type" class="form-select" required>
                            <option value="earned">Earned Coins</option>
                            <option value="bonus">Bonus Coins</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Action <span class="text-danger">*</span></label>
                            <select name="action" class="form-select" required>
                                <option value="credit">Credit (+ Coins)</option>
                                <option value="debit">Debit (- Coins)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Amount (Coins) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control" min="1" required placeholder="e.g. 500">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Maker Reason & Justification <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Explain the context and justification..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-send"></i> Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
