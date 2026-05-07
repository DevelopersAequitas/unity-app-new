@extends('admin.layouts.app')

@section('title', 'Life Impact History')

@section('content')
    @php
        $memberName = $member->display_name ?? trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''));
        $heading = $memberName ? $memberName . ' Life Impact History' : 'Life Impact History';
        $resetUrl = $activeCategory
            ? route('admin.life-impact.history.category', [$member, $activeCategory])
            : route('admin.life-impact.history', $member);
        $formatDate = static function ($value): string {
            if (! $value) {
                return '—';
            }

            try {
                return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i');
            } catch (\Throwable $throwable) {
                return (string) $value;
            }
        };
        $clean = static function ($value): string {
            $value = trim((string) ($value ?? ''));
            return $value !== '' ? $value : '—';
        };
        $categoryLabel = static function ($item) use ($categories, $clean): string {
            $actionKey = trim((string) ($item->action_key ?? ''));
            $impactCategory = trim((string) ($item->impact_category ?? ''));
            $activityType = trim((string) ($item->activity_type ?? ''));
            $actionLabel = trim((string) ($item->action_label ?? ''));

            if ($actionKey === 'admin_adjustment' || $impactCategory === 'admin_adjustment' || $activityType === 'admin_adjustment') {
                return 'Admin Adjustment';
            }

            foreach ($categories as $category) {
                $aliases = $category['aliases'] ?? [];
                foreach ([$actionKey, $impactCategory, $activityType] as $token) {
                    $normalized = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($token)), '_');
                    if ($normalized !== '' && in_array($normalized, $aliases, true)) {
                        return $category['label'];
                    }
                }
            }

            return $actionLabel !== '' ? $actionLabel : $clean($impactCategory ?: $activityType ?: $actionKey);
        };
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0">{{ $heading }}</h5>
            <small class="text-muted">{{ $member->adminDisplayInlineLabel() }}</small>
            @if ($activeCategoryLabel)
                <span class="badge bg-light text-dark border ms-2">Category: {{ $activeCategoryLabel }}</span>
            @else
                <span class="badge bg-light text-dark border ms-2">All Life Impact History</span>
            @endif
        </div>
        <a href="{{ route('admin.life-impact.index') }}" class="btn btn-outline-secondary">Back to Life Impact</a>
    </div>

    <div class="card shadow-sm">
        <div class="border-bottom p-3">
            <form method="GET" class="d-flex flex-wrap gap-2 align-items-end" id="lifeImpactHistoryFilterForm">
                <div>
                    <label class="form-label small text-muted mb-1">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div>
                    <label class="form-label small text-muted mb-1">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div style="min-width: 260px;">
                    <label class="form-label small text-muted mb-1">Search</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Title / description / remarks">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="{{ $resetUrl }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Impact Value</th>
                        <th>Total After</th>
                        <th>Category / Action</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Remarks</th>
                    </tr>
                    <tr>
                        <th>
                            <input
                                type="date"
                                name="date"
                                form="lifeImpactHistoryFilterForm"
                                value="{{ $filters['date'] ?? '' }}"
                                class="form-control form-control-sm"
                                placeholder="Date"
                            >
                        </th>
                        <th><input type="text" class="form-control form-control-sm" placeholder="—" disabled></th>
                        <th><input type="text" class="form-control form-control-sm" placeholder="—" disabled></th>
                        <th><input type="text" class="form-control form-control-sm" placeholder="—" disabled></th>
                        <th><input type="text" class="form-control form-control-sm" placeholder="—" disabled></th>
                        <th><input type="text" class="form-control form-control-sm" placeholder="—" disabled></th>
                        <th><input type="text" class="form-control form-control-sm" placeholder="—" disabled></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $isAdminAdjustment = ($item->action_key ?? null) === 'admin_adjustment'
                                || ($item->impact_category ?? null) === 'admin_adjustment'
                                || ($item->activity_type ?? null) === 'admin_adjustment';
                        @endphp
                        <tr>
                            <td>{{ $formatDate($item->created_at ?? null) }}</td>
                            <td>{{ number_format((int) ($item->impact_value ?? 0)) }}</td>
                            <td>{{ isset($item->life_impacted) ? number_format((int) $item->life_impacted) : '—' }}</td>
                            <td>
                                <span class="badge {{ $isAdminAdjustment ? 'bg-warning-subtle text-warning border border-warning-subtle' : 'bg-primary-subtle text-primary' }}">
                                    {{ $categoryLabel($item) }}
                                </span>
                                @if (! empty($item->action_key) && $item->action_key !== 'admin_adjustment')
                                    <div class="text-muted small">{{ $item->action_key }}</div>
                                @endif
                            </td>
                            <td class="text-wrap" style="max-width: 260px; white-space: normal;">{{ $clean($item->title ?? '') }}</td>
                            <td class="text-wrap" style="max-width: 320px; white-space: normal;">{{ $clean($item->description ?? '') }}</td>
                            <td class="text-wrap" style="max-width: 280px; white-space: normal;">{{ $clean($item->remarks ?? '') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No life impact history entries found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $items->links() }}
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('lifeImpactHistoryFilterForm');

            if (!form) {
                return;
            }

            const inputs = form.querySelectorAll('input, select');

            inputs.forEach(function (input) {
                input.addEventListener('keypress', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        form.submit();
                    }
                });
            });
        });
    </script>
@endpush
