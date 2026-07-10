@extends('admin.layouts.app')

@section('title', 'Edit Membership Plan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1 text-dark fw-bold">Edit Membership Plan</h1>
        <p class="text-muted small mb-0">Update pricing and duration details</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('admin.unity-peers-plans.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <button type="submit" form="editMembershipPlanForm" class="btn btn-success d-inline-flex align-items-center gap-2">
            <i class="bi bi-check-circle"></i> Save
        </button>
    </div>
</div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <form id="editMembershipPlanForm" method="POST" action="{{ route('admin.unity-peers-plans.update', $plan) }}">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $plan->name) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Price (Base)</label>
                        <input type="number" step="0.01" min="0" name="price" class="form-control" value="{{ old('price', $plan->price) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">GST %</label>
                        <input type="number" step="0.01" min="0" name="gst_percent" class="form-control" value="{{ old('gst_percent', $plan->gst_percent) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Total Amount (Preview)</label>
                        @php
                            $price = (float) old('price', $plan->price);
                            $gstPercent = (float) old('gst_percent', $plan->gst_percent);
                            $gstAmount = round($price * ($gstPercent / 100), 2);
                            $totalAmount = round($price + $gstAmount, 2);
                        @endphp
                        <input type="text" class="form-control" value="₹{{ number_format($totalAmount, 2) }}" disabled>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Duration Days</label>
                        <input type="number" min="0" name="duration_days" class="form-control" value="{{ old('duration_days', $plan->duration_days) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Duration Months</label>
                        <input type="number" min="0" name="duration_months" class="form-control" value="{{ old('duration_months', $plan->duration_months) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Coins</label>
                        <input type="number" min="0" name="coins" class="form-control" value="{{ old('coins', $plan->coins ?? 0) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Sort Order</label>
                        <input type="number" min="0" name="sort_order" class="form-control" value="{{ old('sort_order', $plan->sort_order ?? 0) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Is Free</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_free" id="is_free" value="1" @checked(old('is_free', $plan->is_free))>
                            <label class="form-check-label" for="is_free">Yes</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Is Active</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $plan->is_active))>
                            <label class="form-check-label" for="is_active">Yes</label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.unity-peers-plans.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
@endsection
