@extends('admin.layouts.app')

@section('title', 'Industry Director Dashboard')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <p class="text-muted mb-1">Industry Director Dashboard</p>
        <h4 class="mb-0">{{ $industryName }}</h4>
        <div class="small text-muted mt-1">All widgets are scoped to your assigned industry.</div>
    </div>
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Industry ID: {{ $industryId }}</span>
</div>

@if (! $hasData)
    <div class="alert alert-info">No members or circles were found for this industry yet.</div>
@endif

<div class="row g-3 mb-4">
    @foreach ([
        ['label' => 'Total Industry Members', 'value' => $stats['total_members'], 'icon' => 'bi-people'],
        ['label' => 'Active Members', 'value' => $stats['active_members'], 'icon' => 'bi-person-check'],
        ['label' => 'New Registrations', 'value' => $stats['new_registrations'], 'icon' => 'bi-person-plus'],
        ['label' => 'Total Activities', 'value' => $stats['total_activities'], 'icon' => 'bi-activity'],
        ['label' => 'Total Posts', 'value' => $stats['total_posts'], 'icon' => 'bi-chat-dots'],
        ['label' => 'Pending Requests', 'value' => $stats['pending_requests'], 'icon' => 'bi-hourglass-split'],
        ['label' => 'Total Circles', 'value' => $stats['total_circles'], 'icon' => 'bi-diagram-3'],
        ['label' => 'Total Coins Earned', 'value' => $stats['total_coins_earned'], 'icon' => 'bi-coin'],
        ['label' => 'Life Impact', 'value' => $stats['life_impact'], 'icon' => 'bi-heart-pulse'],
    ] as $card)
        <div class="col-sm-6 col-xl-4">
            <div class="card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between gap-3">
                    <div>
                        <p class="text-muted mb-1">{{ $card['label'] }}</p>
                        <h4 class="mb-0">{{ number_format($card['value']) }}</h4>
                    </div>
                    <i class="bi {{ $card['icon'] }} fs-2 text-primary"></i>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    @foreach ([
        'membership_growth' => 'Membership Growth Trend',
        'activity_trend' => 'Activity Trend',
        'coins_trend' => 'Coins Trend',
        'life_impact_trend' => 'Life Impact Trend',
    ] as $key => $title)
        @php
            $series = $charts[$key] ?? ['labels' => [], 'values' => []];
            $max = max($series['values'] ?: [0]);
        @endphp
        <div class="col-12 col-xl-6">
            <div class="card p-4 h-100">
                <h6 class="mb-3">{{ $title }}</h6>
                @if (array_sum($series['values'] ?? []) === 0)
                    <div class="text-muted small">No data available for this period.</div>
                @else
                    <div class="d-flex align-items-end gap-2" style="min-height: 180px;">
                        @foreach ($series['values'] as $index => $value)
                            @php $height = $max > 0 ? max(8, (int) round(($value / $max) * 150)) : 8; @endphp
                            <div class="flex-fill text-center">
                                <div class="bg-primary rounded-top mx-auto" style="height: {{ $height }}px; width: 70%;"></div>
                                <div class="small fw-semibold mt-2">{{ number_format($value) }}</div>
                                <div class="small text-muted">{{ $series['labels'][$index] ?? '' }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>
@endsection
