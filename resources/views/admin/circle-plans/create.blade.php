@extends('admin.layouts.app')

@section('title', 'Create Circle Plan')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0">Create Circle Plan</h4>
            <p class="text-muted small mb-0">Define pricing and duration for a new Circle membership plan</p>
        </div>
        <a href="{{ route('admin.circle-plans.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
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
            <form method="POST" action="{{ route('admin.circle-plans.store') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="e.g. Premium Circle Peer" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Slug</label>
                        <input type="text" class="form-control font-monospace" value="Auto-generated (circle_*)" disabled readonly>
                        <small class="text-muted">Slug will be automatically prefixed with <code>circle_</code></small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Price (Base)</label>
                        <input type="number" step="0.01" min="0" name="price" id="planPriceInput" class="form-control" value="{{ old('price', 0) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">GST %</label>
                        <input type="number" step="0.01" min="0" name="gst_percent" id="planGstInput" class="form-control" value="{{ old('gst_percent', 18) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Total Amount (Preview)</label>
                        @php
                            $price = (float) old('price', 0);
                            $gstPercent = (float) old('gst_percent', 18);
                            $gstAmount = round($price * ($gstPercent / 100), 2);
                            $totalAmount = round($price + $gstAmount, 2);
                        @endphp
                        <input type="text" id="planTotalPreview" class="form-control" value="₹{{ number_format($totalAmount, 2) }}" disabled readonly>
                        <small id="planGstBreakdown" class="text-muted d-block mt-1">GST: ₹{{ number_format($gstAmount, 2) }}</small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Duration Days</label>
                        <input type="number" min="0" name="duration_days" class="form-control" value="{{ old('duration_days', 0) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Duration Months</label>
                        <input type="number" min="0" name="duration_months" class="form-control" value="{{ old('duration_months') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Coins</label>
                        <input type="number" min="0" name="coins" class="form-control" value="{{ old('coins', 0) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Sort Order</label>
                        <input type="number" min="0" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Is Free</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_free" id="is_free" value="1" @checked(old('is_free'))>
                            <label class="form-check-label" for="is_free">Yes</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Is Active</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', true))>
                            <label class="form-check-label" for="is_active">Yes</label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.circle-plans.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Circle Plan</button>
                </div>
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
