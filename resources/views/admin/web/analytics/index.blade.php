@extends('admin.layouts.app')

@section('title', 'Website Analytics - Peers Global Web')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-indigo-subtle text-indigo rounded-pill px-2.5 py-1" style="background: rgba(99, 102, 241, 0.12); color: #6366f1; font-weight: 600; font-size: 0.72rem;">
                    <i class="bi bi-graph-up-arrow me-1"></i> Peers Global Website
                </span>
            </div>
            <h1 class="h4 fw-bold text-dark mb-0 tracking-tight">Website Traffic & Engagement Analytics</h1>
            <p class="text-muted small mb-0 mt-0.5">Real-time visitor conversion funnels and inquiry analytics</p>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="row g-4 mb-4">
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-0 shadow-xs rounded-4 p-4 bg-white">
                <span class="text-uppercase tracking-wider text-muted fw-bold" style="font-size: 0.7rem;">TOTAL VISITORS</span>
                <div class="h2 fw-bold text-dark my-2">{{ $metrics['total_visitors'] }}</div>
                <div class="text-success small fw-semibold">+18.4% from last month</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-0 shadow-xs rounded-4 p-4 bg-white">
                <span class="text-uppercase tracking-wider text-muted fw-bold" style="font-size: 0.7rem;">PROMOTER INQUIRIES</span>
                <div class="h2 fw-bold text-dark my-2">{{ $metrics['unique_promoters'] }}</div>
                <div class="text-primary small fw-semibold">High-intent leaders</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-0 shadow-xs rounded-4 p-4 bg-white">
                <span class="text-uppercase tracking-wider text-muted fw-bold" style="font-size: 0.7rem;">CIRCLE APPLICATIONS</span>
                <div class="h2 fw-bold text-dark my-2">{{ $metrics['circle_applications'] }}</div>
                <div class="text-success small fw-semibold">+12 this week</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-0 shadow-xs rounded-4 p-4 bg-white">
                <span class="text-uppercase tracking-wider text-muted fw-bold" style="font-size: 0.7rem;">BOUNCE RATE</span>
                <div class="h2 fw-bold text-dark my-2">{{ $metrics['bounce_rate'] }}</div>
                <div class="text-muted small">Avg Session: {{ $metrics['avg_session'] }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
