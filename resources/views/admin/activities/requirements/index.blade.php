@extends('admin.layouts.app')

@section('title', 'Requirements')

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

        $decodeFilter = function ($value): array {
            if (is_array($value)) {
                return $value;
            }

            if (is_string($value)) {
                $decoded = json_decode($value, true);
                return is_array($decoded) ? $decoded : [];
            }

            return [];
        };
    @endphp

    <!-- Header Component -->
    @include('admin.activities.partials.header', ['title' => 'Requirements'])

    <!-- Metrics Cards -->
    <div class="activities-stats-grid">
        <div class="activity-metric-card">
            <div class="metric-icon bg-primary-subtle text-primary">
                <i class="bi bi-file-earmark-text-fill"></i>
            </div>
            <div class="metric-val">{{ number_format($total) }}</div>
            <div class="metric-label">Total Requirements</div>
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
            <div class="metric-label">Most Requirements by One Peer</div>
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
    <form id="requirementsFiltersForm" method="GET" action="{{ route('admin.activities.requirements.index') }}">
        @include('admin.components.activity-filter-bar-v2', [
            'actionUrl' => route('admin.activities.requirements.index'),
            'resetUrl' => route('admin.activities.requirements.index'),
            'filters' => $filters,
            'circles' => $circles ?? collect(),
            'showExport' => true,
            'exportUrl' => route('admin.activities.requirements.export', request()->query()),
            'renderFormTag' => false,
            'formId' => 'requirementsFiltersForm',
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
                        <span class="fw-bold text-dark"><i class="bi bi-list-task text-primary me-2"></i>Requirements Log</span>
                        <span class="badge bg-light text-dark border">Page count: {{ number_format(count($items)) }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-premium align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>From</th>
                                    <th>Subject & Description</th>
                                    <th>Region & Category</th>
                                    <th>Status</th>
                                    <th>Media</th>
                                    <th>Created At</th>
                                </tr>
                                <tr class="bg-light filter-row">
                                    <th>
                                        <input type="text" name="from_user" value="{{ $filters['from_user'] ?? '' }}" placeholder="From name" class="form-control form-control-sm" />
                                    </th>
                                    <th>
                                        <input type="text" name="subject" value="{{ $filters['subject'] ?? '' }}" placeholder="Subject" class="form-control form-control-sm" />
                                    </th>
                                    <th>
                                        <input type="text" name="region" value="{{ $filters['region'] ?? '' }}" placeholder="Region" class="form-control form-control-sm mb-1" />
                                        <input type="text" name="category" value="{{ $filters['category'] ?? '' }}" placeholder="Category" class="form-control form-control-sm" />
                                    </th>
                                    <th>
                                        <select name="status" class="form-select form-select-sm js-no-searchable-select">
                                            <option value="">Any</option>
                                            @foreach (($statuses ?? collect()) as $status)
                                                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                                            @endforeach
                                        </select>
                                    </th>
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
                                            <a href="{{ route('admin.activities.requirements.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($items as $requirement)
                                    @php
                                        $actorName = $displayName($requirement->actor_display_name ?? null, $requirement->actor_first_name ?? null, $requirement->actor_last_name ?? null);
                                        $mediaInfo = $mediaSummary($requirement->media ?? null);
                                        $mediaId = $firstMediaId($requirement->media ?? null);
                                        $regionFilter = $decodeFilter($requirement->region_filter ?? null);
                                        $categoryFilter = $decodeFilter($requirement->category_filter ?? null);
                                        $regionLabel = $regionFilter['region_label'] ?? $regionFilter['region_name'] ?? $regionFilter['city_name'] ?? null;
                                        $categoryLabel = $categoryFilter['category'] ?? null;

                                        $fromName = $requirement->from_user_name ?? $actorName;
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
                                                        @if($requirement->from_company) <span>{{ $requirement->from_company }}</span> @endif
                                                        @if($requirement->from_city) &bull; <span>{{ $requirement->from_city }}</span> @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark small">{{ $requirement->subject ?? '—' }}</div>
                                            <div class="text-truncate-multi text-muted small" style="max-width: 250px;" title="{{ $requirement->description }}">
                                                {{ $requirement->description ?? '—' }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small"><span class="text-muted">Region:</span> {{ $regionLabel ?: '—' }}</div>
                                            <div class="small"><span class="text-muted">Category:</span> <span class="badge bg-light text-dark border">{{ $categoryLabel ?: '—' }}</span></div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $requirement->status === 'active' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }} px-2 py-1">
                                                {{ ucfirst($requirement->status ?? '—') }}
                                            </span>
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
                                            <span class="small text-muted">{{ $formatDateTime($requirement->created_at ?? null) }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No requirements found.</td>
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
