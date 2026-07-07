@extends('admin.layouts.app')

@section('title', 'Add Coins')

@push('styles')
<style>
    .form-section-title {
        font-family: 'Outfit', sans-serif;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border-light);
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold"><i class="bi bi-coin text-primary me-2"></i>Add Coins</h4>
        <p class="text-muted small mb-0">Create a manual coins adjustment for a platform member</p>
    </div>
    <a href="{{ route('admin.coins.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Coins
    </a>
</div>

<div class="card-activities-wrapper mb-4">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.coins.store') }}">
            @csrf

            <h5 class="form-section-title"><i class="bi bi-person-fill text-primary me-2"></i>Member & Adjustment Details</h5>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">User / Member *</label>
                    <select name="user_id" class="form-select js-user-select @error('user_id') is-invalid @enderror" required>
                        <option value="">Select a member</option>
                        @foreach ($users as $user)
                            <option
                                value="{{ $user->id }}"
                                @selected(old('user_id') === $user->id)
                            >{{ \App\Support\UserOptionLabel::make($user) }}</option>
                        @endforeach
                    </select>
                    @error('user_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Activity (optional)</label>
                    <select name="activity" class="form-select js-no-searchable-select @error('activity') is-invalid @enderror">
                        <option value="">None / Manual Adjustment</option>
                        @foreach ($activityTypes as $type)
                            <option value="{{ $type }}" @selected(old('activity') === $type)>{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $type)) }}</option>
                        @endforeach
                    </select>
                    @error('activity')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <div class="form-text">If selected, the ledger reference will start with "Activity: &lt;type&gt;".</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Coins Amount *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-coin text-warning"></i></span>
                        <input
                            type="number"
                            min="1"
                            name="amount"
                            value="{{ old('amount') }}"
                            class="form-control @error('amount') is-invalid @enderror"
                            required
                            placeholder="e.g. 100"
                        >
                    </div>
                    @error('amount')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Remarks (optional)</label>
                    <input
                        type="text"
                        name="remarks"
                        value="{{ old('remarks') }}"
                        class="form-control @error('remarks') is-invalid @enderror"
                        maxlength="255"
                        placeholder="e.g. Testimonial bonus coins"
                    >
                    @error('remarks')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-3 border-top justify-content-end">
                <a href="{{ route('admin.coins.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-success px-4">
                    <i class="bi bi-check-circle me-1"></i>Add Coins
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.$ && $.fn.select2) {
            $('.js-user-select').select2({
                placeholder: 'Select a member',
                allowClear: true,
                width: '100%'
            });
        }
    });
</script>
@endpush
