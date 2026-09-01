@extends('admin.layouts.app')

@section('title', 'Ad Details - ' . $ad->title)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">{{ $ad->title }}</h1>
        <span class="text-muted small">Ad ID: {{ $ad->id }}</span>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.ads.dashboard') }}" class="btn btn-sm btn-outline-info"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        <a href="{{ route('admin.ads.edit', $ad) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit Ad</a>
        <a href="{{ route('admin.ads.index') }}" class="btn btn-sm btn-outline-secondary">All Ads</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Total Views Card -->
    <div class="col-md-3 col-sm-6 col-12">
        <div class="card border-0 shadow-sm p-3 bg-white rounded-3 text-center">
            <span class="text-muted small text-uppercase fw-semibold">Total Views</span>
            <h3 class="fw-bold text-info mb-0 mt-2">{{ number_format($analytics['views']) }}</h3>
            <span class="text-muted small">Unique: {{ number_format($analytics['unique_views']) }}</span>
        </div>
    </div>
    <!-- Total Clicks Card -->
    <div class="col-md-3 col-sm-6 col-12">
        <div class="card border-0 shadow-sm p-3 bg-white rounded-3 text-center">
            <span class="text-muted small text-uppercase fw-semibold">Total Clicks</span>
            <h3 class="fw-bold text-primary mb-0 mt-2">{{ number_format($analytics['clicks']) }}</h3>
            <span class="text-muted small">Unique: {{ number_format($analytics['unique_clicks']) }}</span>
        </div>
    </div>
    <!-- Unique CTR Card -->
    <div class="col-md-3 col-sm-6 col-12">
        <div class="card border-0 shadow-sm p-3 bg-white rounded-3 text-center">
            <span class="text-muted small text-uppercase fw-semibold">Unique CTR</span>
            <h3 class="fw-bold text-warning mb-0 mt-2">{{ $analytics['ctr'] }}%</h3>
            <span class="text-muted small">Click-Through Rate</span>
        </div>
    </div>
    <!-- Status Card -->
    <div class="col-md-3 col-sm-6 col-12">
        <div class="card border-0 shadow-sm p-3 bg-white rounded-3 text-center">
            <span class="text-muted small text-uppercase fw-semibold">Status</span>
            <h3 class="fw-bold mb-0 mt-2 fs-4">
                @if(!$ad->is_active)
                    <span class="badge bg-secondary">Inactive</span>
                @elseif($ad->ends_at && $ad->ends_at->isPast())
                    <span class="badge bg-danger">Expired</span>
                @elseif($ad->starts_at && $ad->starts_at->isFuture())
                    <span class="badge bg-info">Scheduled</span>
                @else
                    <span class="badge bg-success">Active</span>
                @endif
            </h3>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm bg-white rounded-3">
    <div class="card-header bg-white border-bottom py-3">
        <h5 class="fw-bold mb-0 text-secondary">Ad Information</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4 text-center">
                @if($ad->image_url)
                    <img src="{{ $ad->image_url }}" alt="Ad Image" class="img-fluid rounded border shadow-sm style-max-height-250">
                @else
                    <div class="bg-light text-muted p-5 rounded">No Image Uploaded</div>
                @endif
            </div>
            <div class="col-md-8">
                <table class="table table-sm table-borderless">
                    <tr>
                        <th style="width: 160px;">Title:</th>
                        <td>{{ $ad->title }}</td>
                    </tr>
                    <tr>
                        <th>Subtitle:</th>
                        <td>{{ $ad->subtitle ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Placement:</th>
                        <td><span class="badge bg-light text-dark border">{{ ucfirst($ad->placement ?? 'unassigned') }}</span></td>
                    </tr>
                    <tr>
                        <th>Redirect URL:</th>
                        <td>
                            @if($ad->redirect_url)
                                <a href="{{ $ad->redirect_url }}" target="_blank" rel="noopener">{{ $ad->redirect_url }}</a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Button Text:</th>
                        <td>{{ $ad->button_text ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Campaign Dates:</th>
                        <td>
                            {{ $ad->starts_at ? $ad->starts_at->format('Y-m-d H:i') : 'Unlimited' }}
                            to
                            {{ $ad->ends_at ? $ad->ends_at->format('Y-m-d H:i') : 'Unlimited' }}
                        </td>
                    </tr>
                    <tr>
                        <th>Description:</th>
                        <td>{{ $ad->description ?? '—' }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
