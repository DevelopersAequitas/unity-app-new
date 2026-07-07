@extends('admin.layouts.app')

@section('title', 'Testimonials')

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

        $mediaSummary = function ($media) use ($validMediaIds): array {
            if (! $media) {
                return ['has' => false, 'count' => 0];
            }

            $decoded = is_string($media) ? json_decode($media, true) : $media;
            $items = is_array($decoded) ? $decoded : [$decoded];

            $validCount = 0;
            foreach ($items as $item) {
                $id = null;
                if (is_array($item)) {
                    $id = $item['id'] ?? $item['file_id'] ?? $item['fileId'] ?? null;
                } elseif (is_string($item) && \Illuminate\Support\Str::isUuid($item)) {
                    $id = $item;
                }

                if ($id && in_array($id, $validMediaIds ?? [], true)) {
                    $validCount++;
                }
            }

            return ['has' => $validCount > 0, 'count' => $validCount];
        };

        $firstMediaId = function ($media) use ($validMediaIds): ?string {
            if (! $media) {
                return null;
            }

            $decoded = is_string($media) ? json_decode($media, true) : $media;
            $items = is_array($decoded) ? array_values($decoded) : [$decoded];

            foreach ($items as $item) {
                $id = null;
                if (is_array($item)) {
                    $id = $item['id'] ?? $item['file_id'] ?? $item['fileId'] ?? null;
                } elseif (is_string($item) && \Illuminate\Support\Str::isUuid($item)) {
                    $id = $item;
                }

                if ($id && in_array($id, $validMediaIds ?? [], true)) {
                    return $id;
                }
            }

            return null;
        };
    @endphp

    <!-- Header Component -->
    @include('admin.activities.partials.header', ['title' => 'Testimonials'])

    <!-- Metrics Cards -->
    <div class="activities-stats-grid">
        <div class="activity-metric-card">
            <div class="metric-icon bg-primary-subtle text-primary">
                <i class="bi bi-chat-quote-fill"></i>
            </div>
            <div class="metric-val">{{ number_format($total) }}</div>
            <div class="metric-label">Total Testimonials</div>
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
            <div class="metric-label">Most Testimonials by One Peer</div>
        </div>

        <div class="activity-metric-card">
            <div class="metric-icon bg-success-subtle text-success">
                <i class="bi bi-images"></i>
            </div>
            <div class="metric-val">
                {{ number_format(count($validMediaIds ?? [])) }}
            </div>
            <div class="metric-label">Verified Attachments</div>
        </div>
    </div>

    <!-- Filters Section -->
    <form id="testimonialsFiltersForm" method="GET" action="{{ route('admin.activities.testimonials.index') }}">
        @include('admin.components.activity-filter-bar-v2', [
            'actionUrl' => route('admin.activities.testimonials.index'),
            'resetUrl' => route('admin.activities.testimonials.index'),
            'filters' => $filters,
            'circles' => $circles ?? collect(),
            'showExport' => true,
            'exportUrl' => route('admin.activities.testimonials.export', request()->except(['content'])),
            'renderFormTag' => false,
            'formId' => 'testimonialsFiltersForm',
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
                        <span class="fw-bold text-dark"><i class="bi bi-list-stars text-primary me-2"></i>Testimonials Log</span>
                        <span class="badge bg-light text-dark border">Page count: {{ number_format(count($items)) }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-premium align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Content</th>
                                    <th>Media</th>
                                    <th>Created At</th>
                                </tr>
                                <tr class="bg-light filter-row">
                                    <th>
                                        <input type="text" name="from_peer" value="{{ $tableFilters['from_peer'] ?? '' }}" class="form-control form-control-sm" placeholder="From name">
                                    </th>
                                    <th>
                                        <input type="text" name="to_peer" value="{{ $tableFilters['to_peer'] ?? '' }}" class="form-control form-control-sm" placeholder="To name">
                                    </th>
                                    <th></th>
                                    <th>
                                        <select name="media" class="form-select form-select-sm">
                                            <option value="" @selected(($tableFilters['media'] ?? '') === '')>Any</option>
                                            <option value="yes" @selected(($tableFilters['media'] ?? '') === 'yes')>Yes</option>
                                            <option value="no" @selected(($tableFilters['media'] ?? '') === 'no')>No</option>
                                        </select>
                                    </th>
                                    <th class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="submit" class="btn btn-primary btn-sm px-3">Apply</button>
                                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.activities.testimonials.index') }}">Reset</a>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($items as $testimonial)
                                    @php
                                        $actorName = $displayName($testimonial->actor_display_name ?? null, $testimonial->actor_first_name ?? null, $testimonial->actor_last_name ?? null);
                                        $peerName = $displayName($testimonial->peer_display_name ?? null, $testimonial->peer_first_name ?? null, $testimonial->peer_last_name ?? null);
                                        $mediaInfo = $mediaSummary($testimonial->media ?? null);
                                        $mediaId = $firstMediaId($testimonial->media ?? null);

                                        $fromName = $testimonial->from_user_name ?? $actorName;
                                        $toName = $testimonial->to_user_name ?? $peerName;
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
                                                        @if($testimonial->from_company) <span>{{ $testimonial->from_company }}</span> @endif
                                                        @if($testimonial->from_city) &bull; <span>{{ $testimonial->from_city }}</span> @endif
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
                                                        @if($testimonial->to_company) <span>{{ $testimonial->to_company }}</span> @endif
                                                        @if($testimonial->to_city) &bull; <span>{{ $testimonial->to_city }}</span> @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-truncate-multi text-secondary small" style="max-width: 250px;" title="{{ $testimonial->content }}">
                                                {{ $testimonial->content ?? '—' }}
                                            </div>
                                        </td>
                                        <td>
                                            @if ($mediaInfo['has'] && $mediaId)
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                    <i class="bi bi-paperclip me-1"></i>Yes ({{ $mediaInfo['count'] }})
                                                </span>
                                                <a href="{{ url('/api/v1/files/' . $mediaId) }}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-primary ms-1" style="font-size: 0.72rem; padding: 2px 6px;">View</a>
                                            @else
                                                <span class="text-muted small">No Media</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="small text-muted">{{ $formatDateTime($testimonial->created_at ?? null) }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">No testimonials found.</td>
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
