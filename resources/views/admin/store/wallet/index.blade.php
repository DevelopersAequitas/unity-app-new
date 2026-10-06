@extends('admin.layouts.app')

@section('title', 'Peers Store — Peer Wallets')

@section('content')
<style>
    .wallet-card-header {
        background: #ffffff;
        border-bottom: 1px solid #f1f5f9;
    }
    .badge-soft-success {
        background-color: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    .badge-soft-danger {
        background-color: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    .badge-soft-info {
        background-color: #f0f9ff;
        color: #0369a1;
        border: 1px solid #bae6fd;
    }
    .badge-soft-secondary {
        background-color: #f8fafc;
        color: #475569;
        border: 1px solid #e2e8f0;
    }
</style>

<div class="container-fluid px-3 py-3">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Peer Wallets</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3">
                    <i class="bi bi-wallet2 fs-4"></i>
                </div>
                <div>
                    <h1 class="h3 mb-0 fw-bold text-dark">Peer Coin Wallets</h1>
                    <p class="text-muted small mb-0">Manage peer coin balances, view append-only ledger transaction history, and submit adjustment requests.</p>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.store.wallet.adjustments') }}" class="btn btn-warning d-flex align-items-center gap-2 rounded-3 px-3 py-2 shadow-sm fw-semibold">
                <i class="bi bi-shield-check"></i> Maker-Checker Queue
            </a>
            <a href="{{ route('admin.store.wallet.economy') }}" class="btn btn-outline-primary d-flex align-items-center gap-2 rounded-3 px-3 py-2 fw-semibold">
                <i class="bi bi-graph-up"></i> Economy Overview
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm rounded-3" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm rounded-3" role="alert">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Filter Card --}}
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body p-3 p-md-4">
            <form method="GET" action="{{ route('admin.store.wallet.index') }}" class="row g-3 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by Peer Name, City, Company, Email, Mobile..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="is_frozen" class="form-select">
                        <option value="">All Wallet States (Active & Frozen)</option>
                        <option value="0" {{ $freezeFilter === '0' ? 'selected' : '' }}>Active (Unfrozen) Wallets</option>
                        <option value="1" {{ $freezeFilter === '1' ? 'selected' : '' }}>Frozen Wallets Only</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1 rounded-3 fw-semibold"><i class="bi bi-filter me-1"></i> Filter</button>
                    <a href="{{ route('admin.store.wallet.index') }}" class="btn btn-light rounded-3 border" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Wallets Table --}}
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4 py-3" style="width: 22%;">Peer / Member</th>
                            <th class="py-3" style="width: 18%;">Contact Details</th>
                            <th class="py-3" style="width: 14%;">City &amp; Location</th>
                            <th class="py-3" style="width: 18%;">Category &amp; Industry</th>
                            <th class="py-3" style="width: 12%;">Coin Balance</th>
                            <th class="py-3" style="width: 8%;">Status</th>
                            <th class="pe-4 text-end py-3" style="width: 8%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            @php
                                $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                                $peerName = $fullName ?: ($user->display_name ?: ($user->name ?: 'Peer Member'));
                                
                                // City
                                $cityName = $user->cityRelation?->name ?? $user->city ?? $user->business_city ?? null;
                                $stateName = $user->state ?: $user->business_state;

                                // Category List
                                $categoryList = [];
                                if (!empty($user->mainBusinessCategory?->name)) {
                                    $categoryList[] = trim((string) $user->mainBusinessCategory->name);
                                }
                                if (!empty($user->businessCategory?->name) && !in_array(trim((string) $user->businessCategory->name), $categoryList, true)) {
                                    $categoryList[] = trim((string) $user->businessCategory->name);
                                }
                                if (!empty($user->level4Category?->name) && !in_array(trim((string) $user->level4Category->name), $categoryList, true)) {
                                    $categoryList[] = trim((string) $user->level4Category->name);
                                }
                                if (isset($user->circleMembers)) {
                                    foreach ($user->circleMembers as $cm) {
                                        $catName = $cm->level1Category?->name ?? $cm->level4Category?->name ?? $cm->level3Category?->name ?? $cm->level2Category?->name;
                                        if (!empty($catName) && !in_array(trim((string)$catName), $categoryList, true)) {
                                            $categoryList[] = trim((string)$catName);
                                        }
                                    }
                                }
                                if (empty($categoryList) && !empty($user->business_sub_category)) {
                                    $categoryList[] = trim((string) $user->business_sub_category);
                                }

                                // Industry List
                                $industryList = [];
                                if (!empty($user->industry_tags)) {
                                    $tags = is_array($user->industry_tags) ? $user->industry_tags : explode(',', (string) $user->industry_tags);
                                    foreach ($tags as $tag) {
                                        $tagTrim = trim((string) $tag);
                                        if ($tagTrim !== '' && !in_array($tagTrim, $industryList, true)) {
                                            $industryList[] = $tagTrim;
                                        }
                                    }
                                }
                                if (!empty($user->industries_of_interest)) {
                                    $tags = is_array($user->industries_of_interest) ? $user->industries_of_interest : explode(',', (string) $user->industries_of_interest);
                                    foreach ($tags as $tag) {
                                        $tagTrim = trim((string) $tag);
                                        if ($tagTrim !== '' && !in_array($tagTrim, $industryList, true)) {
                                            $industryList[] = $tagTrim;
                                        }
                                    }
                                }
                                if (empty($industryList) && !empty($user->business_type)) {
                                    $industryList[] = trim((string) $user->business_type);
                                }
                                if (empty($industryList) && !empty($user->company_type)) {
                                    $industryList[] = trim((string) $user->company_type);
                                }
                                
                                $phoneNum = $user->phone_number ?? $user->phone ?? $user->mobile ?? null;
                                $emailAddr = $user->email ?? null;

                                $isFrozen = ($user->wallet_state === 'FROZEN') || ($user->is_store_frozen ?? false);
                            @endphp
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold shadow-xs flex-shrink-0" style="width: 44px; height: 44px; font-size: 1.1rem;">
                                            {{ strtoupper(substr($peerName, 0, 1)) }}
                                        </div>
                                        <div>
                                            <a href="{{ route('admin.store.wallet.show', $user->id) }}" class="fw-bold text-decoration-none text-dark d-block">
                                                {{ $peerName }}
                                            </a>
                                            @if($user->company_name)
                                                <div class="text-muted extra-small mt-0.5">
                                                    <i class="bi bi-building me-1 text-secondary"></i>{{ $user->company_name }}
                                                    @if($user->designation) <span class="text-muted">&bull; {{ $user->designation }}</span> @endif
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex flex-column gap-1">
                                        @if($phoneNum)
                                            <div class="d-flex align-items-center gap-1.5 text-dark small">
                                                <i class="bi bi-telephone text-primary me-1 flex-shrink-0"></i>
                                                <span>{{ $phoneNum }}</span>
                                            </div>
                                        @endif
                                        @if($emailAddr)
                                            <div class="d-flex align-items-center gap-1.5 text-muted small">
                                                <i class="bi bi-envelope text-secondary me-1 flex-shrink-0"></i>
                                                <span class="text-break">{{ $emailAddr }}</span>
                                            </div>
                                        @endif
                                        @if(!$phoneNum && !$emailAddr)
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3">
                                    @if($cityName)
                                        <div class="d-flex align-items-center gap-1 text-dark fw-semibold small">
                                            <i class="bi bi-geo-alt-fill text-danger me-1 flex-shrink-0"></i>{{ $cityName }}
                                        </div>
                                        @if($stateName)
                                            <div class="text-muted extra-small ms-3 ps-1">{{ $stateName }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <div class="d-flex flex-column gap-1">
                                        @if(count($categoryList) > 0)
                                            @foreach(array_slice($categoryList, 0, 2) as $cat)
                                                <div>
                                                    <span class="badge badge-soft-info rounded-pill px-2.5 py-1 small text-truncate" style="max-width: 200px;" title="{{ $cat }}">
                                                        <i class="bi bi-tag-fill me-1"></i>{{ $cat }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        @endif
                                        @if(count($industryList) > 0)
                                            @foreach(array_slice($industryList, 0, 2) as $ind)
                                                <div>
                                                    <span class="badge badge-soft-secondary rounded-pill px-2.5 py-1 extra-small text-truncate" style="max-width: 200px;" title="{{ $ind }}">
                                                        <i class="bi bi-briefcase me-1"></i>{{ $ind }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        @endif
                                        @if(count($categoryList) === 0 && count($industryList) === 0)
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                        <span class="fs-6 fw-bold text-primary">{{ number_format($user->coins_balance ?? 0) }}</span>
                                        <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-2 py-0.5 rounded-pill extra-small">
                                            <i class="bi bi-coin text-warning me-0.5"></i>Coins
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3">
                                    @if($isFrozen)
                                        <span class="badge badge-soft-danger px-2.5 py-1 rounded-pill small fw-semibold">
                                            <i class="bi bi-lock-fill me-1"></i> Frozen
                                        </span>
                                    @else
                                        <span class="badge badge-soft-success px-2.5 py-1 rounded-pill small fw-semibold">
                                            <i class="bi bi-unlock-fill me-1"></i> Active
                                        </span>
                                    @endif
                                </td>
                                <td class="pe-4 text-end py-3">
                                    <div class="d-flex justify-content-end gap-1.5">
                                        <a href="{{ route('admin.store.wallet.show', $user->id) }}" class="btn btn-sm btn-outline-primary rounded-3 px-2 py-1" title="View Wallet Ledger">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <button class="btn btn-sm btn-outline-secondary rounded-3 px-2 py-1" data-bs-toggle="modal" data-bs-target="#quickAdjustModal{{ $user->id }}" title="Request Adjustment">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            {{-- Quick Adjust Request Modal --}}
                            <div class="modal fade" id="quickAdjustModal{{ $user->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content rounded-4 border-0 shadow">
                                        <form method="POST" action="{{ route('admin.store.wallet.adjustments.request') }}">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $user->id }}">
                                            <div class="modal-header border-bottom py-3">
                                                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                                                    <i class="bi bi-shield-lock text-warning"></i> Adjust Coins: {{ $peerName }}
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="alert alert-info small rounded-3 mb-3">
                                                    <i class="bi bi-info-circle-fill me-1"></i>
                                                    <strong>Maker-Checker Enforced:</strong> This adjustment will enter the approval queue. Another authorized admin must verify and approve it before coins are credited/debited.
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold small">Coin Bucket <span class="text-danger">*</span></label>
                                                    <select name="coin_type" class="form-select rounded-3" required>
                                                        <option value="earned">Earned Coins (Spendable on Products)</option>
                                                        <option value="bonus">Bonus Coins (Promotional campaign)</option>
                                                    </select>
                                                </div>
                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <label class="form-label fw-semibold small">Adjustment Action <span class="text-danger">*</span></label>
                                                        <select name="action" class="form-select rounded-3" required>
                                                            <option value="credit">Credit (+ Coins)</option>
                                                            <option value="debit">Debit (- Coins)</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label fw-semibold small">Amount (Coins) <span class="text-danger">*</span></label>
                                                        <input type="number" name="amount" class="form-control rounded-3" min="1" required placeholder="e.g. 500">
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold small">Maker Reason & Justification <span class="text-danger">*</span></label>
                                                    <textarea name="reason" class="form-control rounded-3" rows="3" placeholder="Provide detailed justification for the checker admin..." required></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light border-top py-3">
                                                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-warning rounded-3 fw-semibold"><i class="bi bi-send me-1"></i> Submit to Checker Queue</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-wallet2 fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                    No peer wallets found matching your filter criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($users->hasPages())
            <div class="card-footer bg-white py-3 border-top">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
