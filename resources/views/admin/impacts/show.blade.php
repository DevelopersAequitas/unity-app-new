@extends('admin.layouts.app')

@section('title', 'Impact Detail')

@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0 fw-bold">Impact Detail</h1>
        <a href="{{ route('admin.impacts.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
    <div class="card p-3 mb-3">
        <p><strong>Date:</strong> {{ optional($impact->impact_date)->toDateString() }}</p>
        <p><strong>User:</strong> {{ $impact->user->display_name ?? $impact->user->first_name }}</p>
        <p><strong>Impacted Peer:</strong> {{ $impact->impactedPeer->display_name ?? $impact->impactedPeer->first_name }}</p>
        <p><strong>Action:</strong> {{ $impact->action }}</p>
        <p><strong>Story:</strong> {{ $impact->story_to_share }}</p>
        <p><strong>Additional Remarks:</strong> {{ $impact->additional_remarks ?: '-' }}</p>
        <p><strong>Status:</strong> {{ $impact->status }}</p>
        <p><strong>Total Life Impacted:</strong> {{ (int) ($total_life_impacted ?? 0) }}</p>
    </div>

    @if($impact->status === 'pending')
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('admin.impacts.approve', $impact->id) }}">
            @csrf
            <input type="text" name="review_remarks" class="form-control mb-2" placeholder="Review remarks (optional)">
            <button class="btn btn-success">Approve</button>
        </form>

        <form method="POST" action="{{ route('admin.impacts.reject', $impact->id) }}">
            @csrf
            <input type="text" name="review_remarks" class="form-control mb-2" placeholder="Review remarks (optional)">
            <button class="btn btn-danger">Reject</button>
        </form>
    </div>
    @endif
</div>
@endsection
