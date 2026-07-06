@extends('admin.layouts.app')

@section('title', 'Business Deals')

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
    @include('admin.activities.partials.header', ['title' => 'Business Deals'])

    <!-- Metrics Cards -->
    <div class="activities-stats-grid">
        <div class="activity-metric-card">
            <div class="metric-icon bg-primary-subtle text-primary">
                <i class="bi bi-briefcase-fill"></i>
            </div>
            <div class="metric-val">{{ number_format($total) }}</div>
            <div class="metric-label">Total Deals</div>
        </div>

        <div class="activity-metric-card">
            <div class="metric-icon bg-success-subtle text-success">
                <i class="bi bi-currency-rupee"></i>
            </div>
            <div class="metric-val">
                ₹{{ number_format($items->sum('deal_amount'), 2) }}
            </div>
            <div class="metric-label">Total Deal Value (Page)</div>
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
            <div class="metric-label">Most Deals by One Peer</div>
        </div>
    </div>

    <!-- Filters Section -->
    <form id="businessDealsFiltersForm" method="GET" action="{{ route('admin.activities.business-deals.index') }}">
        @include('admin.components.activity-filter-bar-v2', [
            'actionUrl' => route('admin.activities.business-deals.index'),
            'resetUrl' => route('admin.activities.business-deals.index'),
            'filters' => $filters,
            'circles' => $circles ?? collect(),
            'showExport' => true,
            'exportUrl' => route('admin.activities.business-deals.export', request()->query()),
            'renderFormTag' => false,
            'formId' => 'businessDealsFiltersForm',
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
                        <span class="fw-bold text-dark"><i class="bi bi-briefcase text-primary me-2"></i>Business Deals Log</span>
                        <span class="badge bg-light text-dark border">Page count: {{ number_format(count($items)) }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-premium align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Deal Details</th>
                                    <th>Amount</th>
                                    <th>Comment</th>
                                    <th>Media</th>
                                    <th>Created At</th>
                                </tr>
                                <tr class="bg-light filter-row">
                                    <th><input type="text" name="from_user" value="{{ $filters['from_user'] ?? '' }}" placeholder="From name" class="form-control form-control-sm"></th>
                                    <th><input type="text" name="to_user" value="{{ $filters['to_user'] ?? '' }}" placeholder="To name" class="form-control form-control-sm"></th>
                                    <th>
                                        <input type="date" name="deal_date" value="{{ $filters['deal_date'] ?? '' }}" class="form-control form-control-sm mb-1" placeholder="Date">
                                        <input type="text" name="business_type" value="{{ $filters['business_type'] ?? '' }}" placeholder="Business type" class="form-control form-control-sm">
                                    </th>
                                    <th><input type="number" step="0.01" name="deal_amount" value="{{ $filters['deal_amount'] ?? '' }}" placeholder="Amount" class="form-control form-control-sm"></th>
                                    <th><input type="text" name="comment" value="{{ $filters['comment'] ?? '' }}" placeholder="Comment" class="form-control form-control-sm"></th>
                                    <th>
                                        <select name="has_media" class="form-select form-select-sm js-no-searchable-select">
                                            <option value="">Any</option>
                                            <option value="yes" @selected(($filters['has_media'] ?? '') === 'yes')>Yes</option>
                                            <option value="no" @selected(($filters['has_media'] ?? '') === 'no')>No</option>
                                        </select>
                                    </th>
                                    <th class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="submit" class="btn btn-primary btn-sm px-3">Apply</button>
                                            <a href="{{ route('admin.activities.business-deals.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($items as $deal)
                                    @php
                                        $mediaValue = $deal->media_reference ?? null;
                                        $hasMedia = false;

                                        if (is_string($mediaValue)) {
                                            $trim = trim($mediaValue);
                                            $hasMedia = ($trim !== '' && $trim !== 'null' && $trim !== '[]' && $trim !== '{}');
                                        } elseif (is_array($mediaValue)) {
                                            $hasMedia = count($mediaValue) > 0;
                                        } elseif (! is_null($mediaValue)) {
                                            $hasMedia = true;
                                        }

                                        $actorName = $displayName($deal->actor_display_name ?? null, $deal->actor_first_name ?? null, $deal->actor_last_name ?? null);
                                        $peerName = $displayName($deal->peer_display_name ?? null, $deal->peer_first_name ?? null, $deal->peer_last_name ?? null);

                                        $fromName = $deal->from_user_name ?? $actorName;
                                        $toName = $deal->to_user_name ?? $peerName;
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
                                                        @if($deal->from_company) <span>{{ $deal->from_company }}</span> @endif
                                                        @if($deal->from_city) &bull; <span>{{ $deal->from_city }}</span> @endif
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
                                                        @if($deal->to_company) <span>{{ $deal->to_company }}</span> @endif
                                                        @if($deal->to_city) &bull; <span>{{ $deal->to_city }}</span> @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @if($deal->deal_date)
                                                <div class="fw-semibold text-dark small"><i class="bi bi-calendar-check me-1 text-muted"></i>{{ $formatDate($deal->deal_date) }}</div>
                                            @endif
                                            <span class="badge bg-light text-dark border mt-1">{{ $deal->business_type ?? '—' }}</span>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-success">₹{{ number_format($deal->deal_amount ?? 0, 2) }}</span>
                                        </td>
                                        <td>
                                            <div class="text-truncate-multi text-secondary small" style="max-width: 150px;" title="{{ $deal->comment }}">
                                                {{ $deal->comment ?? '—' }}
                                            </div>
                                        </td>
                                        <td>
                                            @if ($hasMedia)
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                    <i class="bi bi-paperclip me-1"></i>Yes
                                                </span>
                                                <button type="button" class="btn btn-xs btn-outline-primary ms-1" style="font-size: 0.72rem; padding: 2px 6px;" data-bs-toggle="modal" data-bs-target="#mediaViewerModal" data-media-source="media-json-{{ $deal->id }}">View</button>
                                                <script type="application/json" id="media-json-{{ $deal->id }}">{{ e(json_encode(is_string($deal->media_reference ?? null) ? json_decode($deal->media_reference ?? '[]', true) : ($deal->media_reference ?? []))) }}</script>
                                            @else
                                                <span class="text-muted small">No Media</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="small text-muted">{{ $formatDateTime($deal->created_at ?? null) }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No business deals found.</td>
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

    <!-- Media Viewer Modal -->
    <div class="modal fade" id="mediaViewerModal" tabindex="-1" aria-labelledby="mediaViewerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="mediaViewerModalLabel"><i class="bi bi-images me-2 text-primary"></i>Media Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body bg-light" data-media-container>
                    <p class="text-muted mb-0">No media available.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('[data-media-source]').forEach((button) => {
            button.addEventListener('click', () => {
                const modal = document.getElementById('mediaViewerModal');
                const container = modal.querySelector('[data-media-container]');
                const sourceId = button.getAttribute('data-media-source');
                const scriptTag = document.getElementById(sourceId);
                let items = [];

                if (scriptTag) {
                    try {
                        items = JSON.parse(scriptTag.textContent || '[]');
                    } catch (error) {
                        items = [];
                    }
                }

                container.innerHTML = '';

                if (!Array.isArray(items) || items.length === 0) {
                    container.innerHTML = '<p class="text-muted mb-0 py-4 text-center">No media available.</p>';
                    return;
                }

                items.forEach((item, index) => {
                    let url = null;

                    if (typeof item === 'string') {
                        url = item;
                    } else if (item && typeof item === 'object') {
                        url = item.url || item.id || null;
                    }

                    if (!url) {
                        return;
                    }

                    if (!url.startsWith('http') && /^[0-9a-fA-F-]{36}$/.test(url)) {
                        url = `/api/v1/files/${url}`;
                    }

                    const wrapper = document.createElement('div');
                    wrapper.classList.add('card', 'p-3', 'mb-3', 'border-0', 'shadow-sm');

                    const link = document.createElement('a');
                    link.href = url;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    link.textContent = `Open File Reference ${index + 1}`;
                    link.classList.add('btn', 'btn-sm', 'btn-outline-primary', 'd-inline-block', 'mb-3', 'align-self-start');

                    wrapper.appendChild(link);

                    if (/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i.test(url)) {
                        const img = document.createElement('img');
                        img.src = url;
                        img.alt = `Media ${index + 1}`;
                        img.classList.add('img-fluid', 'rounded', 'border');
                        img.style.maxHeight = '400px';
                        img.style.objectFit = 'contain';
                        wrapper.appendChild(img);
                    }

                    container.appendChild(wrapper);
                });
            });
        });
    </script>
@endsection
