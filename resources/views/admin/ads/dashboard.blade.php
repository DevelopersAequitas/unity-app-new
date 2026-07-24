@extends('admin.layouts.app')

@section('title', 'Ads Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Ads Dashboard</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.ads.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-megaphone me-1"></i>All Ads</a>
        <a href="{{ route('admin.ads.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Ad</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if($stats['unique_views'] == 0)
    <div class="alert alert-info border-0 shadow-sm d-flex align-items-center gap-3 mb-4 bg-info-subtle text-info-emphasis rounded-3 p-3">
        <i class="bi bi-info-circle-fill fs-4"></i>
        <div>
            <div class="fw-bold">Analytics collection in progress</div>
            <span class="small opacity-75">Ad views, clicks, unique tracking metrics and CTR will populate automatically as peers interact with advertisements.</span>
        </div>
    </div>
@endif

<!-- Quick Actions -->
<div class="card border-0 shadow-sm mb-4 bg-white rounded-3">
    <div class="card-body py-3 d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <h6 class="fw-bold mb-0 text-secondary"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Quick Actions</h6>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.ads.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Ad</a>
            <a href="{{ route('admin.ads.index') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-list-task me-1"></i>All Ads</a>
            <a href="{{ route('admin.ads.analytics') }}" class="btn btn-sm btn-outline-info"><i class="bi bi-graph-up me-1"></i>Analytics Report</a>
        </div>
    </div>
</div>

<!-- Section 1: Primary KPI Cards -->
<div class="row g-3 mb-4">
    <!-- Total Ads -->
    <div class="col-lg col-md-4 col-sm-6 col-12">
        <div class="card border-0 shadow-sm p-3 h-100 bg-white rounded-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold" style="font-size: 11px; letter-spacing: 0.5px;">Total Ads</span>
                    <h3 class="fw-bold mb-0 mt-2 text-dark fs-2">{{ number_format($stats['total_ads']) }}</h3>
                </div>
                <div class="bg-primary-subtle text-primary rounded-circle p-2.5 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                    <i class="bi bi-megaphone-fill fs-5"></i>
                </div>
            </div>
        </div>
    </div>
    <!-- Active Ads -->
    <div class="col-lg col-md-4 col-sm-6 col-12">
        <div class="card border-0 shadow-sm p-3 h-100 bg-white rounded-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold" style="font-size: 11px; letter-spacing: 0.5px;">Active Ads</span>
                    <h3 class="fw-bold mb-0 mt-2 text-success fs-2">{{ number_format($stats['active_ads']) }}</h3>
                </div>
                <div class="bg-success-subtle text-success rounded-circle p-2.5 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                    <i class="bi bi-check-circle-fill fs-5"></i>
                </div>
            </div>
        </div>
    </div>
    <!-- Total Views -->
    <div class="col-lg col-md-4 col-sm-6 col-12">
        <div class="card border-0 shadow-sm p-3 h-100 bg-white rounded-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold" style="font-size: 11px; letter-spacing: 0.5px;">Total Views</span>
                    <h3 class="fw-bold mb-0 mt-2 text-info fs-2">{{ number_format($stats['total_views']) }}</h3>
                    @if($stats['unique_views'] > 0)
                        <span class="text-muted small d-block mt-1" style="font-size: 11px;">Unique: {{ number_format($stats['unique_views']) }}</span>
                    @endif
                </div>
                <div class="bg-info-subtle text-info rounded-circle p-2.5 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                    <i class="bi bi-eye-fill fs-5"></i>
                </div>
            </div>
        </div>
    </div>
    <!-- Total Clicks -->
    <div class="col-lg col-md-4 col-sm-6 col-12">
        <div class="card border-0 shadow-sm p-3 h-100 bg-white rounded-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold" style="font-size: 11px; letter-spacing: 0.5px;">Total Clicks</span>
                    <h3 class="fw-bold mb-0 mt-2 text-primary fs-2">{{ number_format($stats['total_clicks']) }}</h3>
                    @if($stats['unique_views'] > 0)
                        <span class="text-muted small d-block mt-1" style="font-size: 11px;">Unique: {{ number_format($stats['unique_clicks']) }}</span>
                    @endif
                </div>
                <div class="bg-primary-subtle text-primary rounded-circle p-2.5 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                    <i class="bi bi-cursor-fill fs-5"></i>
                </div>
            </div>
        </div>
    </div>
    <!-- Unique CTR -->
    <div class="col-lg col-md-4 col-sm-6 col-12">
        <div class="card border-0 shadow-sm p-3 h-100 bg-white rounded-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold" style="font-size: 11px; letter-spacing: 0.5px;">Unique CTR</span>
                    <h3 class="fw-bold mb-0 mt-2 text-warning fs-2">{{ $stats['ctr'] }}%</h3>
                </div>
                <div class="bg-warning-subtle text-warning rounded-circle p-2.5 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                    <i class="bi bi-percent fs-5"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Section 2: Secondary Statistics -->
<div class="row row-cols-2 row-cols-sm-3 row-cols-md-5 g-3 mb-4">
    <!-- Unique Views -->
    <div class="col">
        <div class="card border-0 shadow-sm p-3 bg-white text-center rounded-3">
            <span class="text-muted small text-uppercase fw-semibold" style="font-size: 10px; letter-spacing: 0.5px;">Unique Views</span>
            <div class="fw-bold text-dark fs-4 mt-2">{{ number_format($stats['unique_views']) }}</div>
        </div>
    </div>
    <!-- Unique Clicks -->
    <div class="col">
        <div class="card border-0 shadow-sm p-3 bg-white text-center rounded-3">
            <span class="text-muted small text-uppercase fw-semibold" style="font-size: 10px; letter-spacing: 0.5px;">Unique Clicks</span>
            <div class="fw-bold text-dark fs-4 mt-2">{{ number_format($stats['unique_clicks']) }}</div>
        </div>
    </div>
    <!-- Scheduled Ads -->
    <div class="col">
        <div class="card border-0 shadow-sm p-3 bg-white text-center rounded-3">
            <span class="text-muted small text-uppercase fw-semibold" style="font-size: 10px; letter-spacing: 0.5px;">Scheduled</span>
            <div class="fw-bold text-info fs-4 mt-2">{{ number_format($stats['scheduled_ads']) }}</div>
        </div>
    </div>
    <!-- Expired Ads -->
    <div class="col">
        <div class="card border-0 shadow-sm p-3 bg-white text-center rounded-3">
            <span class="text-muted small text-uppercase fw-semibold" style="font-size: 10px; letter-spacing: 0.5px;">Expired</span>
            <div class="fw-bold text-danger fs-4 mt-2">{{ number_format($stats['expired_ads']) }}</div>
        </div>
    </div>
    <!-- Inactive Ads -->
    <div class="col">
        <div class="card border-0 shadow-sm p-3 bg-white text-center rounded-3">
            <span class="text-muted small text-uppercase fw-semibold" style="font-size: 10px; letter-spacing: 0.5px;">Inactive</span>
            <div class="fw-bold text-secondary fs-4 mt-2">{{ number_format($stats['inactive_ads']) }}</div>
        </div>
    </div>
</div>

<!-- Section 3: Performance Highlights -->
<div class="row g-3 mb-4">
    <!-- Top Performing Ad -->
    <div class="col-md-6 col-12">
        <div class="card border-0 shadow-sm p-3 h-100 bg-white d-flex flex-row align-items-center justify-content-between rounded-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success-subtle p-3 text-success">
                    <i class="bi bi-graph-up-arrow fs-2"></i>
                </div>
                <div>
                    <span class="text-muted small text-uppercase fw-semibold">Top Performing Ad</span>
                    <h5 class="fw-bold mb-0 text-dark mt-1">
                        {{ $stats['top_performing_ad']?->title ?? 'None Yet' }}
                    </h5>
                    <span class="text-muted small">Highest engagement with {{ number_format($stats['top_performing_clicks']) }} clicks.</span>
                </div>
            </div>
            @if($stats['top_performing_ad'])
                <a href="{{ route('admin.ads.show', $stats['top_performing_ad']) }}" class="btn btn-sm btn-outline-secondary">Details</a>
            @endif
        </div>
    </div>
    <!-- Most Viewed Ad -->
    <div class="col-md-6 col-12">
        <div class="card border-0 shadow-sm p-3 h-100 bg-white d-flex flex-row align-items-center justify-content-between rounded-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-info-subtle p-3 text-info">
                    <i class="bi bi-eye-fill fs-2"></i>
                </div>
                <div>
                    <span class="text-muted small text-uppercase fw-semibold">Most Viewed Ad</span>
                    <h5 class="fw-bold mb-0 text-dark mt-1">
                        {{ $stats['most_viewed_ad']?->title ?? 'None Yet' }}
                    </h5>
                    <span class="text-muted small">Highest visibility with {{ number_format($stats['most_viewed_count']) }} views.</span>
                </div>
            </div>
            @if($stats['most_viewed_ad'])
                <a href="{{ route('admin.ads.show', $stats['most_viewed_ad']) }}" class="btn btn-sm btn-outline-secondary">Details</a>
            @endif
        </div>
    </div>
</div>

<!-- Section 4: Analytics Charts / Insights -->
<div class="row g-3">
    <!-- Traffic Chart (Line) -->
    <div class="col-md-8 col-12">
        <div class="card border-0 shadow-sm p-4 bg-white rounded-3">
            <h5 class="fw-bold mb-3 text-secondary">Daily Traffic (Last 30 Days)</h5>
            <div style="height: 300px; position: relative;">
                <canvas id="trafficChartCanvas"></canvas>
            </div>
        </div>
    </div>
    @if(!empty($charts['has_placement']))
        <!-- Placements Breakdown (Doughnut) -->
        <div class="col-md-4 col-12">
            <div class="card border-0 shadow-sm p-4 bg-white h-100 rounded-3">
                <h5 class="fw-bold mb-3 text-secondary">Placement Performance</h5>
                <div style="height: 250px; position: relative;" class="d-flex align-items-center justify-content-center">
                    <canvas id="placementsChartCanvas"></canvas>
                </div>
            </div>
        </div>
    @else
        <!-- Top Ads by Engagement Table -->
        <div class="col-md-4 col-12">
            <div class="card border-0 shadow-sm p-3 bg-white h-100 rounded-3">
                <h5 class="fw-bold mb-3 text-secondary">Top Ads by Engagement</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Ad</th>
                                <th class="text-center">Views</th>
                                <th class="text-center">Clicks</th>
                                <th class="text-end">CTR</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($charts['top_ads_by_engagement'] as $ad)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.ads.show', $ad['id']) }}" class="text-decoration-none fw-semibold text-dark">
                                            {{ Str::limit($ad['title'], 18) }}
                                        </a>
                                    </td>
                                    <td class="text-center text-info fw-bold">{{ number_format($ad['views']) }}</td>
                                    <td class="text-center text-primary fw-bold">{{ number_format($ad['clicks']) }}</td>
                                    <td class="text-end text-warning fw-bold">{{ $ad['ctr'] }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">No engagement data yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- ChartJS Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const chartsData = @json($charts);

        // 1. Traffic Chart (Line)
        const trafficDates = chartsData.traffic_chart.map(item => item.date);
        const trafficViews = chartsData.traffic_chart.map(item => item.views);
        const trafficClicks = chartsData.traffic_chart.map(item => item.clicks);

        new Chart(document.getElementById('trafficChartCanvas'), {
            type: 'line',
            data: {
                labels: trafficDates,
                datasets: [
                    {
                        label: 'Views',
                        data: trafficViews,
                        borderColor: '#0dcaf0',
                        backgroundColor: '#0dcaf010',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Clicks',
                        data: trafficClicks,
                        borderColor: '#0d6efd',
                        backgroundColor: '#0d6efd10',
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true }
                }
            }
        });

        // 2. Placements Chart (Doughnut - if enabled)
        if (chartsData.has_placement && document.getElementById('placementsChartCanvas')) {
            const placeLabels = chartsData.placements.map(item => item.name);
            const placeCounts = chartsData.placements.map(item => item.count);

            new Chart(document.getElementById('placementsChartCanvas'), {
                type: 'doughnut',
                data: {
                    labels: placeLabels.length ? placeLabels : ['No Data'],
                    datasets: [{
                        data: placeCounts.length ? placeCounts : [1],
                        backgroundColor: ['#4A90E2', '#F5A623', '#E28499', '#7ED321', '#BD10E0', '#CCCCCC']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right' }
                    }
                }
            });
        }
    });
</script>
@endsection
