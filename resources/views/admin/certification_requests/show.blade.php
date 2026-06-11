@extends('admin.layouts.app')

@section('title', $resource['title'].' Details')

@section('content')
@php
    $statusClasses = [
        'new' => 'warning text-dark',
        'approved' => 'success',
        'rejected' => 'danger',
    ];

    $statusLabels = [
        'new' => 'Pending / New',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    $formatLabel = static fn (string $column) => \Illuminate\Support\Str::title(str_replace('_', ' ', $column));
    $formatValue = static function ($value, string $column): string {
        if ($value === null || $value === '') {
            return '—';
        }

        if (in_array($column, ['created_at', 'updated_at'], true)) {
            return \Illuminate\Support\Carbon::parse($value)->format('d M Y h:i A');
        }

        if ($column === 'percentage') {
            return rtrim(rtrim(number_format((float) $value, 2), '0'), '.').'%';
        }

        return (string) $value;
    };

    $status = (string) $certificationRequest->status;
    $statusClass = $statusClasses[$status] ?? 'secondary';
    $statusLabel = $statusLabels[$status] ?? ucfirst($status ?: 'Unknown');
@endphp

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
        <div>
            <h1 class="h4 mb-1">{{ $resource['title'] }} Details</h1>
            <p class="text-muted mb-0">{{ $certificationRequest->full_name }} · {{ $certificationRequest->business_name }}</p>
        </div>
        <a href="{{ route($resource['index_route']) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="small text-muted">Current Status</div>
                <span class="badge bg-{{ $statusClass }}">{{ $statusLabel }}</span>
            </div>
            @if($status === 'new')
                <div class="btn-group">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveCertificationRequest">Approve</button>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectCertificationRequest">Reject</button>
                </div>
            @endif
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                @foreach($resource['detail_columns'] as $column)
                    @php $value = data_get($certificationRequest, $column); @endphp
                    <div class="col-md-6">
                        <div class="small text-muted mb-1">{{ $formatLabel($column) }}</div>
                        @if($column === 'status')
                            <span class="badge bg-{{ $statusClass }}">{{ $statusLabel }}</span>
                        @elseif($column === 'notes' || in_array($column, $certificationRequest::QUIZ_FIELDS, true))
                            <div class="border rounded p-2 bg-light" style="white-space: pre-wrap;">{{ $formatValue($value, $column) }}</div>
                        @else
                            <div>{{ $formatValue($value, $column) }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@if($status === 'new')
    <div class="modal fade" id="approveCertificationRequest" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route($resource['approve_route'], $certificationRequest->id) }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Approve {{ $resource['singular_title'] }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><p class="mb-0">Are you sure you want to approve this certification request?</p></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Approve</button></div>
        </form></div>
    </div>
    <div class="modal fade" id="rejectCertificationRequest" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route($resource['reject_route'], $certificationRequest->id) }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Reject {{ $resource['singular_title'] }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><p class="mb-0">Are you sure you want to reject this certification request?</p></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Reject</button></div>
        </form></div>
    </div>
@endif
@endsection
