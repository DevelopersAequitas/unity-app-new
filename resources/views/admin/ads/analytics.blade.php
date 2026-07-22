@extends('admin.layouts.app')

@section('title', 'Ads Analytics & Reports')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Ads Analytics &amp; Performance Reports</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.ads.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-megaphone me-1"></i>All Ads</a>
        <a href="{{ route('admin.ads.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Ad</a>
    </div>
</div>

<div class="row g-3">
    <!-- Click Through Rate widget -->
    <div class="col-md-6 col-12">
        <div class="card border-0 shadow-sm p-4 bg-white text-center h-100">
            <h6 class="text-uppercase text-secondary fw-semibold mb-2">Overall Click-Through Rate (CTR)</h6>
            <div class="display-3 fw-bold text-primary my-3">{{ $stats['ctr'] }}%</div>
            <p class="text-muted small mb-0">Percentage of unique ad impressions that resulted in unique clicks.</p>
        </div>
    </div>
    <!-- Total Engagement widget -->
    <div class="col-md-6 col-12">
        <div class="card border-0 shadow-sm p-4 bg-white text-center h-100">
            <h6 class="text-uppercase text-secondary fw-semibold mb-2">Total Interactions</h6>
            <div class="display-3 fw-bold text-success my-3">{{ number_format($stats['total_views'] + $stats['total_clicks']) }}</div>
            <p class="text-muted small mb-0">Combined total of views ({{ number_format($stats['total_views']) }}) and clicks ({{ number_format($stats['total_clicks']) }}).</p>
        </div>
    </div>

    <!-- Placement Distribution OR Top Ads Engagement -->
    <div class="col-md-6 col-12 mt-3">
        <div class="card border-0 shadow-sm p-4 bg-white h-100">
            @if(!empty($charts['has_placement']))
                <h5 class="fw-bold mb-3 text-secondary">Placements Distribution</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Placement</th>
                                <th class="text-center">Ads Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($charts['placements'] as $place)
                                <tr>
                                    <td>
                                        <span class="fw-bold text-dark">{{ $place['name'] }}</span>
                                    </td>
                                    <td class="text-center fw-semibold text-primary">{{ number_format($place['count']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <h5 class="fw-bold mb-3 text-secondary">Top Ads Engagement</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
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
                                            {{ Str::limit($ad['title'], 20) }}
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
            @endif
        </div>
    </div>

    <!-- Last 6 Months Metrics -->
    <div class="col-md-6 col-12 mt-3">
        <div class="card border-0 shadow-sm p-4 bg-white h-100">
            <h5 class="fw-bold mb-3 text-secondary">Historical Overview</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Month</th>
                            <th class="text-center">Views</th>
                            <th class="text-center">Clicks</th>
                            <th class="text-center">CTR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($charts['monthly_performance'] as $month)
                            @php
                                $mCtr = $month['views'] > 0 ? round(($month['clicks'] / $month['views']) * 100, 2) : 0;
                            @endphp
                            <tr>
                                <td class="fw-semibold text-dark">{{ $month['month'] }}</td>
                                <td class="text-center text-info">{{ number_format($month['views']) }}</td>
                                <td class="text-center text-primary">{{ number_format($month['clicks']) }}</td>
                                <td class="text-center fw-bold text-warning">{{ $mCtr }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
