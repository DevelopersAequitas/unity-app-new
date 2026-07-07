@extends('admin.layouts.app')

@section('title', 'Find & Build Collaborations')

@section('content')
    @php
        use App\Support\CollaborationFormatter;
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
    @endphp

    <!-- Header Component -->
    @include('admin.activities.partials.header', ['title' => 'Find & Build Collaborations'])

    <!-- Metrics Cards -->
    <div class="activities-stats-grid">
        <div class="activity-metric-card">
            <div class="metric-icon bg-primary-subtle text-primary">
                <i class="bi bi-link-45deg"></i>
            </div>
            <div class="metric-val">{{ number_format($total) }}</div>
            <div class="metric-label">Total Collaboration Posts</div>
        </div>

        <div class="activity-metric-card">
            <div class="metric-icon bg-success-subtle text-success">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div class="metric-val">
                {{ number_format($posts->filter(fn($p) => strtolower((string)$p->status) === 'active')->count()) }}
            </div>
            <div class="metric-label">Active Posts (Page)</div>
        </div>
    </div>

    <!-- Filters Section -->
    <form id="collaborationsFiltersForm" method="GET" action="{{ route('admin.collaborations.index') }}">
        @include('admin.components.activity-filter-bar-v2', [
            'actionUrl' => route('admin.collaborations.index'),
            'resetUrl' => route('admin.collaborations.index'),
            'filters' => $filters,
            'circles' => $circles ?? collect(),
            'showExport' => true,
            'exportUrl' => route('admin.collaborations.export', request()->query()),
            'renderFormTag' => false,
            'formId' => 'collaborationsFiltersForm',
        ])

        <!-- Table Card -->
        <div class="card-activities-wrapper">
            <div class="d-flex flex-wrap justify-content-between align-items-center p-3 gap-2 border-bottom bg-light">
                <div class="d-flex align-items-center gap-2">
                    <label for="perPage" class="form-label mb-0 small text-muted">Rows per page:</label>
                    <select id="perPage" name="per_page" class="form-select form-select-sm" style="width: 90px;">
                        @foreach ([10, 20, 50, 100] as $size)
                            <option value="{{ $size }}" @selected($rowsPerPage === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="small text-muted">
                    @if($total > 0)
                        Records {{ $from }} to {{ $to }} of {{ $total }}
                    @else
                        No records found
                    @endif
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-premium align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Peer Details</th>
                            <th>Type</th>
                            <th>Title</th>
                            <th>Scope</th>
                            <th>Mode</th>
                            <th>Stage</th>
                            <th>Yrs Active</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        <tr class="bg-light filter-row">
                            <th><input type="text" name="peer_name" value="{{ $filters['peer_name'] ?? '' }}" placeholder="Peer Name" class="form-control form-control-sm"></th>
                            <th><input type="text" name="collaboration_type" value="{{ $filters['collaboration_type'] ?? '' }}" placeholder="Type" class="form-control form-control-sm"></th>
                            <th><input type="text" name="title" value="{{ $filters['title'] ?? '' }}" placeholder="Title" class="form-control form-control-sm"></th>
                            <th><input type="text" name="scope" value="{{ $filters['scope'] ?? '' }}" placeholder="Scope" class="form-control form-control-sm"></th>
                            <th><input type="text" name="preferred_mode" value="{{ $filters['preferred_mode'] ?? '' }}" placeholder="Mode" class="form-control form-control-sm"></th>
                            <th><input type="text" name="business_stage" value="{{ $filters['business_stage'] ?? '' }}" placeholder="Stage" class="form-control form-control-sm"></th>
                            <th><input type="text" name="year_in_operation" value="{{ $filters['year_in_operation'] ?? '' }}" placeholder="Years" class="form-control form-control-sm"></th>
                            <th>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">Any</option>
                                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                                </select>
                            </th>
                            <th>
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm px-3">Apply</button>
                                    <a href="{{ route('admin.collaborations.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($posts as $post)
                            @php
                                $peerName = $post->peer_name
                                    ?? $post->person_name
                                    ?? $post->name
                                    ?? '—';

                                $company = ($post->peer_company ?? null)
                                    ?? $post->company
                                    ?? $post->company_name
                                    ?? $post->business_name
                                    ?? '—';

                                $city = ($post->peer_city ?? null)
                                    ?? $post->city
                                    ?? $post->user_city
                                    ?? '—';

                                $typeName = $post->collaborationType?->name ?? CollaborationFormatter::humanize($post->collaboration_type);
                                $title = $post->title ?? $post->collaboration_title ?? $post->subject ?? '—';
                                $scope = CollaborationFormatter::humanize($post->scope ?? $post->collaboration_scope ?? $post->scope_text);
                                $preferredMode = CollaborationFormatter::humanize($post->preferred_mode ?? $post->preferred_model ?? $post->meeting_mode ?? $post->mode);
                                $businessStage = CollaborationFormatter::humanize($post->business_stage ?? $post->stage ?? $post->business_stage_text);
                                $yearInOperation = CollaborationFormatter::humanize($post->year_in_operation ?? $post->years_in_operation ?? $post->operating_years ?? $post->years);
                                $status = $post->status ?? '—';
                            @endphp
                            <tr>
                                <td>
                                    <div class="peer-badge-wrapper">
                                        <div class="peer-badge-avatar" style="background-color: {{ $getAvatarBg($peerName) }}">
                                            {{ $getInitials($peerName) }}
                                        </div>
                                        <div class="peer-badge-info">
                                            <div class="peer-badge-name">{{ $peerName }}</div>
                                            <div class="peer-badge-meta">
                                                @if($company) <span>{{ $company }}</span> @endif
                                                @if($city) &bull; <span>{{ $city }}</span> @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">{{ $typeName }}</span></td>
                                <td><div class="fw-semibold text-dark small" style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $title }}</div></td>
                                <td><span class="small">{{ $scope }}</span></td>
                                <td><span class="small">{{ $preferredMode }}</span></td>
                                <td><span class="small">{{ $businessStage }}</span></td>
                                <td><span class="small">{{ $yearInOperation }}</span></td>
                                <td>
                                    <span class="badge {{ strtolower((string) $status) === 'active' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }} px-2 py-1">
                                        {{ CollaborationFormatter::humanize((string) $status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-xs btn-outline-primary" style="font-size: 0.72rem; padding: 2px 8px;" href="{{ route('admin.collaborations.show', ['id' => $post->id] + request()->query()) }}">Details</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No collaboration posts found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2">
        <div>
            {{ $posts->appends(request()->query())->links() }}
        </div>
        <div class="small text-muted">
            @if($total > 0)
                Showing {{ $from }}-{{ $to }} of {{ $total }} records
            @else
                No records
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const perPage = document.getElementById('perPage');

        if (perPage) {
            perPage.addEventListener('change', () => {
                const params = new URLSearchParams(window.location.search);
                params.set('per_page', perPage.value);
                params.delete('page');
                window.location = `${window.location.pathname}?${params.toString()}`;
            });
        }
    });
</script>
@endpush
