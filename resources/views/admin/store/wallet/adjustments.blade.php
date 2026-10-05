@extends('admin.layouts.app')

@section('title', 'Peers Store — Maker-Checker Coin Adjustments')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.wallet.index') }}" class="text-decoration-none text-muted">Wallets</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Maker-Checker Queue</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-shield-check text-warning"></i> Maker-Checker Coin Adjustment Queue
            </h1>
        </div>
        <div>
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

    {{-- Maker-Checker Rules Banner --}}
    <div class="alert alert-warning border-warning d-flex align-items-start gap-3 shadow-sm mb-4">
        <i class="bi bi-exclamation-triangle-fill fs-4 text-warning mt-0.5"></i>
        <div>
            <h6 class="fw-bold mb-1 text-dark">Strict Maker-Checker Principle (Segregation of Duties)</h6>
            <p class="mb-0 text-muted small">
                To prevent financial fraud and unauthorized coin minting, <strong>the administrator who created a balance adjustment request cannot approve it</strong>. A distinct checker administrator must review the justification and authorize or reject the request.
            </p>
        </div>
    </div>

    {{-- Pending Approvals Table --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
            <h5 class="card-title fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-hourglass-split text-warning"></i> Pending Verification Queue
                <span class="badge bg-warning text-dark">{{ $pendingAdjustments->count() }} Pending</span>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 10%;">Req ID</th>
                            <th style="width: 20%;">Peer / Member</th>
                            <th style="width: 12%;">Bucket</th>
                            <th style="width: 15%;">Action & Coins</th>
                            <th style="width: 15%;">Requested By (Maker)</th>
                            <th style="width: 15%;">Maker Reason</th>
                            <th class="pe-4 text-end" style="width: 13%;">Checker Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingAdjustments as $adj)
                            @php
                                $isMaker = (auth('admin')->id() === $adj->requested_by);
                            @endphp
                            <tr>
                                <td class="ps-4 fw-bold">#{{ $adj->id }}</td>
                                <td>
                                    <a href="{{ route('admin.store.wallet.show', $adj->user_id) }}" class="fw-bold text-decoration-none text-dark">
                                        {{ $adj->user->name ?? 'Unknown Peer' }}
                                    </a>
                                    <div class="text-muted small">ID: #{{ $adj->user_id }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ ucfirst($adj->coin_type) }} Coins</span>
                                </td>
                                <td>
                                    <span class="badge {{ $adj->action === 'credit' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' }} fw-bold fs-6">
                                        {{ $adj->action === 'credit' ? '+' : '-' }}{{ number_format($adj->amount) }} Coins
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $adj->requester->name ?? 'Admin #' . $adj->requested_by }}</div>
                                    <small class="text-muted">{{ ($adj->requested_at ?? $adj->created_at) ? \Carbon\Carbon::parse($adj->requested_at ?? $adj->created_at)->format('d M Y, h:i A') : '—' }}</small>
                                </td>
                                <td>
                                    <div class="small text-muted" title="{{ $adj->reason }}">{{ Str::limit($adj->reason, 45) }}</div>
                                </td>
                                <td class="pe-4 text-end">
                                    @if($isMaker)
                                        <span class="badge bg-secondary-subtle text-secondary" title="Maker cannot approve own request">
                                            <i class="bi bi-lock-fill"></i> Maker (Locked)
                                        </span>
                                    @else
                                        <div class="d-flex justify-content-end gap-1">
                                            <form method="POST" action="{{ route('admin.store.wallet.adjustments.approve', $adj->id) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success" title="Approve Adjustment" onclick="return confirm('Approve this coin balance adjustment?')">
                                                    <i class="bi bi-check-lg"></i> Approve
                                                </button>
                                            </form>
                                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $adj->id }}" title="Reject">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>

                                        {{-- Reject Modal --}}
                                        <div class="modal fade" id="rejectModal{{ $adj->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content text-start">
                                                    <form method="POST" action="{{ route('admin.store.wallet.adjustments.reject', $adj->id) }}">
                                                        @csrf
                                                        <div class="modal-header">
                                                            <h5 class="modal-title text-danger"><i class="bi bi-x-circle text-danger"></i> Reject Adjustment Request #{{ $adj->id }}</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p class="text-muted small">
                                                                Peer: <strong>{{ $adj->user->name ?? 'User' }}</strong><br>
                                                                Requested Change: <strong>{{ $adj->action === 'credit' ? '+' : '-' }}{{ number_format($adj->amount) }} {{ ucfirst($adj->coin_type) }} Coins</strong>
                                                            </p>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-semibold">Checker Rejection Reason <span class="text-danger">*</span></label>
                                                                <textarea name="rejection_reason" class="form-control" rows="3" placeholder="Explain reason for rejection..." required></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer bg-light">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger"><i class="bi bi-x-lg"></i> Reject Request</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-check2-all text-success fs-1 d-block mb-2"></i>
                                    All clear! No pending coin balance adjustments in the queue.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Historical Adjustments Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="card-title fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-archive text-secondary"></i> Historical Adjustments Audit Log
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 10%;">Req ID</th>
                            <th style="width: 20%;">Peer</th>
                            <th style="width: 15%;">Adjustment</th>
                            <th style="width: 15%;">Maker (Requester)</th>
                            <th style="width: 15%;">Checker (Reviewer)</th>
                            <th style="width: 10%;">Status</th>
                            <th class="pe-4" style="width: 15%;">Processed Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($historicalAdjustments as $hist)
                            <tr>
                                <td class="ps-4 fw-bold">#{{ $hist->id }}</td>
                                <td>{{ $hist->user->name ?? 'Unknown' }}</td>
                                <td>
                                    <span class="fw-bold {{ $hist->action === 'credit' ? 'text-success' : 'text-danger' }}">
                                        {{ $hist->action === 'credit' ? '+' : '-' }}{{ number_format($hist->amount) }} {{ ucfirst($hist->coin_type) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="small">{{ $hist->requester->name ?? 'Admin #' . $hist->requested_by }}</span>
                                </td>
                                <td>
                                    <span class="small">{{ $hist->approver->name ?? 'Admin #' . $hist->approved_by ?: '—' }}</span>
                                </td>
                                <td>
                                    @if($hist->status === 'approved')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Approved</span>
                                    @elseif($hist->status === 'rejected')
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Rejected</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">{{ ucfirst($hist->status) }}</span>
                                    @endif
                                </td>
                                <td class="pe-4 text-muted small">
                                    {{ ($hist->approved_at ?? $hist->rejected_at ?? $hist->updated_at ?? $hist->requested_at) ? \Carbon\Carbon::parse($hist->approved_at ?? $hist->rejected_at ?? $hist->updated_at ?? $hist->requested_at)->format('d M Y, h:i A') : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    No historical adjustment records found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($historicalAdjustments->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $historicalAdjustments->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
