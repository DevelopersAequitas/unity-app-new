@extends('admin.layouts.app')

@section('title', 'Referrals')

@section('content')
    @php
        $getInitials = function($name) {
            $words = explode(' ', trim($name));
            $initials = '';
            foreach ($words as $w) {
                if(!empty($w)) $initials .= strtoupper(substr($w, 0, 1));
            }
            return substr($initials, 0, 2) ?: 'P';
        };
        $getAvatarBg = function($name) {
            $colors = ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6'];
            $hash = crc32($name);
            return $colors[abs($hash) % count($colors)];
        };

        $displayName = function (?string $display, ?string $first, ?string $last): string {
            if ($display) {
                return $display;
            }
            $name = trim(($first ?? '') . ' ' . ($last ?? ''));
            return $name !== '' ? $name : '—';
        };

        $formatDateTime = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i') : '—';
        };

        $formatDate = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d') : '—';
        };
    @endphp

    <!-- Header Component -->
    @include('admin.activities.partials.header', ['title' => 'Referrals'])

    <!-- Metrics Cards -->
    <div class="activities-stats-grid">
        <div class="activity-metric-card">
            <div class="metric-icon bg-primary-subtle text-primary">
                <i class="bi bi-person-plus-fill"></i>
            </div>
            <div class="metric-val">{{ number_format($total) }}</div>
            <div class="metric-label">Total Referrals</div>
        </div>

        <div class="activity-metric-card">
            <div class="metric-icon bg-warning-subtle text-warning-emphasis">
                <i class="bi bi-star-fill"></i>
            </div>
            <div class="metric-val">
                @if(($topMembers ?? collect())->isNotEmpty())
                    {{ $topMembers->first()->total_count ?? 0 }}
                @else
                    0
                @endif
            </div>
            <div class="metric-label">Most Referrals by One Peer</div>
        </div>

        <div class="activity-metric-card">
            <div class="metric-icon bg-danger-subtle text-danger">
                <i class="bi bi-fire"></i>
            </div>
            <div class="metric-val">
                {{ number_format($items->where('hot_value', '>', 3)->count()) }}
            </div>
            <div class="metric-label">Hot Referrals (Page)</div>
        </div>
    </div>

    <!-- Filters Section -->
    <form id="referralsFiltersForm" method="GET" action="{{ route('admin.activities.referrals.index') }}">
        @include('admin.components.activity-filter-bar-v2', [
            'actionUrl' => route('admin.activities.referrals.index'),
            'resetUrl' => route('admin.activities.referrals.index'),
            'filters' => $filters,
            'circles' => $circles ?? collect(),
            'showExport' => true,
            'exportUrl' => route('admin.activities.referrals.export', request()->query()),
            'renderFormTag' => false,
            'formId' => 'referralsFiltersForm',
        ])

        <!-- Top 5 & All logs row -->
        <div class="row g-3 mb-4">
            <!-- Top 5 Peers -->
            <div class="col-12">
                <div class="card-activities-wrapper h-100">
                    <div class="card-header bg-white">
                        <span class="fw-bold text-dark"><i class="bi bi-trophy-fill text-warning me-2"></i>Top 5 Peers</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-premium align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 70px;">Rank</th>
                                    <th>Peer Name</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($topMembers as $index => $member)
                                    <tr>
                                        <td>#{{ $index + 1 }}</td>
                                        <td>
                                            <div class="peer-badge-wrapper">
                                                <div class="peer-badge-avatar" style="background-color: {{ $getAvatarBg($member->peer_name ?? '') }}">
                                                    {{ $getInitials($member->peer_name ?? '') }}
                                                </div>
                                                <div class="peer-badge-info">
                                                    <div class="peer-badge-name">{{ $member->peer_name ?? $displayName($member->display_name ?? null, $member->first_name ?? null, $member->last_name ?? null) }}</div>
                                                    <div class="peer-badge-meta">
                                                        @if($member->peer_company) <span>{{ $member->peer_company }}</span> @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end fw-bold text-primary">{{ $member->total_count ?? 0 }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">No data available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- All Logs -->
            <div class="col-12">
                <div class="card-activities-wrapper">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-dark"><i class="bi bi-person-check text-primary me-2"></i>Referrals Log</span>
                        <span class="badge bg-light text-dark border">Page count: {{ number_format(count($items)) }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-premium align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Referral Details</th>
                                    <th>Contact Info</th>
                                    <th>Hot Value</th>
                                    <th>Remarks</th>
                                    <th>Media</th>
                                    <th>Created At</th>
                                </tr>
                                <tr class="bg-light filter-row">
                                    <th><input type="text" name="from_user" value="{{ $filters['from_user'] ?? '' }}" placeholder="From name" class="form-control form-control-sm"></th>
                                    <th><input type="text" name="to_user" value="{{ $filters['to_user'] ?? '' }}" placeholder="To name" class="form-control form-control-sm"></th>
                                    <th>
                                        <select name="type" class="form-select form-select-sm js-no-searchable-select mb-1">
                                            <option value="">Any Type</option>
                                            @foreach (($types ?? collect()) as $type)
                                                <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type || ($filters['referral_type'] ?? '') === $type)>{{ $type }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="referral_of" value="{{ $filters['referral_of'] ?? '' }}" placeholder="Referral of" class="form-control form-control-sm">
                                    </th>
                                    <th>
                                        <input type="text" name="phone" value="{{ $filters['phone'] ?? '' }}" placeholder="Phone" class="form-control form-control-sm mb-1">
                                        <input type="text" name="email" value="{{ $filters['email'] ?? '' }}" placeholder="Email" class="form-control form-control-sm">
                                    </th>
                                    <th><input type="number" name="hot_value" value="{{ $filters['hot_value'] ?? '' }}" placeholder="Hot" class="form-control form-control-sm"></th>
                                    <th><input type="text" name="remarks" value="{{ $filters['remarks'] ?? '' }}" placeholder="Remarks" class="form-control form-control-sm"></th>
                                    <th>
                                        <select name="has_media" class="form-select form-select-sm js-no-searchable-select">
                                            <option value="">Any</option>
                                            <option value="1" @selected(($filters['has_media'] ?? '') === '1')>Yes</option>
                                            <option value="0" @selected(($filters['has_media'] ?? '') === '0')>No</option>
                                        </select>
                                    </th>
                                    <th class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="submit" class="btn btn-primary btn-sm px-3">Apply</button>
                                            <a href="{{ route('admin.activities.referrals.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($items as $referral)
                                    @php
                                        $actorName = $displayName($referral->actor_display_name ?? null, $referral->actor_first_name ?? null, $referral->actor_last_name ?? null);
                                        $peerName = $displayName($referral->peer_display_name ?? null, $referral->peer_first_name ?? null, $referral->peer_last_name ?? null);

                                        $fromName = $referral->from_user_name ?? $actorName;
                                        $toName = $referral->to_user_name ?? $peerName;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="peer-badge-wrapper">
                                                <div class="peer-badge-avatar" style="background-color: {{ $getAvatarBg($fromName) }}">
                                                    {{ $getInitials($fromName) }}
                                                </div>
                                                <div class="peer-badge-info">
                                                    <div class="peer-badge-name">{{ $fromName }}</div>
                                                    <div class="peer-badge-meta">
                                                        @if($referral->from_company) <span>{{ $referral->from_company }}</span> @endif
                                                        @if($referral->from_city) &bull; <span>{{ $referral->from_city }}</span> @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="peer-badge-wrapper">
                                                <div class="peer-badge-avatar" style="background-color: {{ $getAvatarBg($toName) }}">
                                                    {{ $getInitials($toName) }}
                                                </div>
                                                <div class="peer-badge-info">
                                                    <div class="peer-badge-name">{{ $toName }}</div>
                                                    <div class="peer-badge-meta">
                                                        @if($referral->to_company) <span>{{ $referral->to_company }}</span> @endif
                                                        @if($referral->to_city) &bull; <span>{{ $referral->to_city }}</span> @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark small">{{ $referral->referral_of ?? '—' }}</div>
                                            <span class="badge bg-light text-dark border mt-1">{{ $referral->referral_type ?? '—' }}</span>
                                            @if($referral->referral_date)
                                                <div class="small text-muted mt-1"><i class="bi bi-calendar-check me-1"></i>{{ $formatDate($referral->referral_date) }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="small"><i class="bi bi-telephone me-1 text-muted"></i>{{ $referral->phone ?? '—' }}</div>
                                            <div class="small"><i class="bi bi-envelope me-1 text-muted"></i>{{ $referral->email ?? '—' }}</div>
                                        </td>
                                        <td>
                                            @if($referral->hot_value)
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                                    <i class="bi bi-fire me-1"></i>{{ $referral->hot_value }}
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="text-truncate-multi text-secondary small" style="max-width: 150px;" title="{{ $referral->remarks }}">
                                                {{ $referral->remarks ?? '—' }}
                                            </div>
                                        </td>
                                        <td>
                                            @if ((int) ($referral->has_media ?? 0) === 1)
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                    <i class="bi bi-paperclip me-1"></i>Yes
                                                </span>
                                                @if (!empty($referral->media_reference))
                                                    @php
                                                        $mediaReference = (string) $referral->media_reference;
                                                        $mediaUrl = str_starts_with($mediaReference, 'http://') || str_starts_with($mediaReference, 'https://')
                                                            ? $mediaReference
                                                            : url('/api/v1/files/' . $mediaReference);
                                                    @endphp
                                                    <a href="{{ $mediaUrl }}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-primary ms-1" style="font-size: 0.72rem; padding: 2px 6px;">View</a>
                                                @endif
                                            @else
                                                <span class="text-muted small">No Media</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="small text-muted">{{ $formatDateTime($referral->created_at ?? null) }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">No referrals found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="mt-3">
        {{ $items->links() }}
    </div>
@endsection
