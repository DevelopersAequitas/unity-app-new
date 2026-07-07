@extends('admin.layouts.app')

@section('title', 'Connections')

@section('content')
    <style>
        .peer-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
    @php
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
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h4 mb-0">Connections</h1>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-light text-dark border">Total Connections: {{ number_format($total) }}</span>
        </div>
    </div>

    <form id="connectionsFiltersForm" method="GET" action="{{ route('admin.activities.connections.index') }}">
    @include('admin.components.activity-filter-bar-v2', [
        'actionUrl' => route('admin.activities.connections.index'),
        'resetUrl' => route('admin.activities.connections.index'),
        'filters' => $filters,
        'circles' => $circles ?? collect(),
        'showExport' => true,
        'exportUrl' => route('admin.activities.connections.export', request()->except(['content'])),
        'renderFormTag' => false,
        'formId' => 'connectionsFiltersForm',
    ])

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white">
            <strong>Top 5 Connected Peers</strong>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Rank</th>
                        <th>Peer Name</th>
                        <th>Total Connections Initiated</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($topMembers as $index => $member)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                @include('admin.components.peer-card', [
                                    'name' => $member->peer_name ?? $displayName($member->display_name ?? null, $member->first_name ?? null, $member->last_name ?? null),
                                    'company' => $member->peer_company ?? '',
                                    'city' => $member->peer_city ?? '',
                                    'maxWidth' => 260,
                                ])
                            </td>
                            <td>{{ $member->total_count ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted">No data available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>From (Requester)</th>
                        <th>To (Addressee)</th>
                        <th>Status</th>
                        <th>Requested At</th>
                        <th>Approved At</th>
                    </tr>
                    <tr>
                        <th>
                            <input type="text" name="from_peer" value="{{ $tableFilters['from_peer'] ?? '' }}" class="form-control form-control-sm" placeholder="From">
                        </th>
                        <th>
                            <input type="text" name="to_peer" value="{{ $tableFilters['to_peer'] ?? '' }}" class="form-control form-control-sm" placeholder="To">
                        </th>
                        <th>
                            <select name="status" class="form-select form-select-sm">
                                <option value="" @selected(($tableFilters['status'] ?? '') === '')>Any</option>
                                <option value="approved" @selected(($tableFilters['status'] ?? '') === 'approved')>Approved</option>
                                <option value="pending" @selected(($tableFilters['status'] ?? '') === 'pending')>Pending</option>
                            </select>
                        </th>
                        <th></th>
                        <th class="text-end">
                            <div class="d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                                <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.activities.connections.index') }}">Reset</a>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $actorName = $displayName($item->actor_display_name ?? null, $item->actor_first_name ?? null, $item->actor_last_name ?? null);
                            $peerName = $displayName($item->peer_display_name ?? null, $item->peer_first_name ?? null, $item->peer_last_name ?? null);
                        @endphp
                        <tr>
                            <td>
                                @include('admin.components.peer-card', [
                                    'name' => $item->from_user_name ?? $actorName,
                                    'company' => $item->from_company ?? '',
                                    'city' => $item->from_city ?? '',
                                ])
                            </td>
                            <td>
                                @include('admin.components.peer-card', [
                                    'name' => $item->to_user_name ?? $peerName,
                                    'company' => $item->to_company ?? '',
                                    'city' => $item->to_city ?? '',
                                ])
                            </td>
                            <td>
                                @if ($item->is_approved)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Approved</span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Pending</span>
                                @endif
                            </td>
                            <td>{{ $formatDateTime($item->created_at ?? null) }}</td>
                            <td>{{ $formatDateTime($item->approved_at ?? null) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No connections found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    </form>

    <div class="mt-3">
        {{ $items->links() }}
    </div>

@endsection
