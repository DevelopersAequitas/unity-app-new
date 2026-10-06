@extends('admin.layouts.app')

@section('title', 'Edit Membership Plan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1 text-dark fw-bold">Edit Membership Plan</h1>
        <p class="text-muted small mb-0">Update pricing and duration details</p>
    </div>
    <a href="{{ route('admin.unity-peers-plans.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
        <i class="bi bi-arrow-left"></i> Back
    </a>
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
                    <div class="col-md-6">
                        <label class="form-label">Slug</label>
                        <input type="text" class="form-control font-monospace" value="{{ $plan->slug }}" disabled readonly>
                        <small class="text-muted">System identifier used for pricing &amp; membership rules.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Price (Base)</label>
                        <input type="number" step="0.01" min="0" name="price" id="planPriceInput" class="form-control" value="{{ old('price', $plan->price) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">GST %</label>
                        <input type="number" step="0.01" min="0" name="gst_percent" id="planGstInput" class="form-control" value="{{ old('gst_percent', $plan->gst_percent) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Total Amount (Preview)</label>
                        @php
                            $price = (float) old('price', $plan->price);
                            $gstPercent = (float) old('gst_percent', $plan->gst_percent);
                            $gstAmount = round($price * ($gstPercent / 100), 2);
                            $totalAmount = round($price + $gstAmount, 2);
                        @endphp
                        <input type="text" id="planTotalPreview" class="form-control" value="₹{{ number_format($totalAmount, 2) }}" disabled readonly>
                        <small id="planGstBreakdown" class="text-muted d-block mt-1">GST: ₹{{ number_format($gstAmount, 2) }}</small>
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

                <div class="mt-4 d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="if(confirm('Are you sure you want to delete this plan ({{ addslashes($plan->name) }})? This action cannot be undone.')) document.getElementById('deletePlanForm').submit();">
                        <i class="bi bi-trash"></i> Delete Plan
                    </button>
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.unity-peers-plans.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </div>
            </form>
            <form id="deletePlanForm" action="{{ route('admin.unity-peers-plans.destroy', $plan) }}" method="POST" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const priceInput = document.getElementById('planPriceInput');
            const gstInput = document.getElementById('planGstInput');
            const totalPreview = document.getElementById('planTotalPreview');
            const gstBreakdown = document.getElementById('planGstBreakdown');

            function updateTotal() {
                const price = parseFloat(priceInput.value) || 0;
                const gst = parseFloat(gstInput.value) || 0;
                const gstAmount = price * (gst / 100);
                const total = price + gstAmount;
                if (totalPreview) {
                    totalPreview.value = '₹' + total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
                if (gstBreakdown) {
                    gstBreakdown.textContent = 'GST: ₹' + gstAmount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
            }

            if (priceInput && gstInput) {
                priceInput.addEventListener('input', updateTotal);
                gstInput.addEventListener('input', updateTotal);
                updateTotal();
            }
        });
    </script>
@endsection
