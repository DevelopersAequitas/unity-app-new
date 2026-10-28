@extends('admin.layouts.app')

@section('title', 'App Tutorial Popups')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1 text-dark fw-bold">
            <i class="bi bi-window-stack me-2 text-success"></i>App Tutorial Popups
        </h1>
        <p class="text-muted small mb-0">Manage mobile app walkthrough tour popups and toggle individual tour steps.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <span class="badge {{ $masterEnabled ? 'bg-success' : 'bg-secondary' }} px-3 py-2 fs-7">
            <i class="bi {{ $masterEnabled ? 'bi-check-circle-fill' : 'bi-dash-circle-fill' }} me-1"></i>
            Master: {{ $masterEnabled ? 'Enabled' : 'Disabled' }}
        </span>
        <span class="badge bg-light text-dark border px-3 py-2 fs-7">
            {{ $enabledCount }} / {{ $totalCount }} Steps Active
        </span>
        <a href="{{ url('/app-config') }}" target="_blank" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-box-arrow-up-right me-1"></i>Test Public API
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(isset($errors) && $errors->any())
    <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        @foreach($errors->all() as $error)
            {{ $error }}
        @endforeach
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<form action="{{ route('admin.app-tutorial-popups.update') }}" method="POST">
    @csrf
    @method('PUT')

    <!-- Master Switch Card -->
    <div class="card shadow-sm border-0 mb-4" style="border-radius: 16px;">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 52px; height: 52px; border-radius: 14px; background: {{ $masterEnabled ? '#e8f5e9' : '#f5f5f5' }}; color: {{ $masterEnabled ? '#2e7d32' : '#757575' }}; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        <i class="bi bi-toggles"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1 text-dark">Master Tutorials Walkthrough Toggle</h5>
                        <p class="text-muted small mb-0">
                            When disabled, the entire tutorial walkthrough tour is completely turned off for all users in the mobile app.
                        </p>
                    </div>
                </div>
                <div class="form-check form-switch fs-4 mb-0">
                    <input class="form-check-input" 
                           type="checkbox" 
                           role="switch" 
                           name="master_tutorials_enabled" 
                           id="master_tutorials_enabled" 
                           value="1" 
                           @checked($masterEnabled)>
                    <label class="form-check-label visually-hidden" for="master_tutorials_enabled">Master Toggle</label>
                </div>
            </div>
        </div>
    </div>

    <!-- Individual Tutorial Steps -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0" style="border-radius: 16px;">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">Walkthrough Tour Steps</h5>
                        <small class="text-muted">Any step set to <strong>OFF</strong> will be excluded from the mobile app walkthrough tour.</small>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                        8 Standard Steps
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 70px;">Order</th>
                                <th>Step Details</th>
                                <th>API Key</th>
                                <th class="text-center" style="width: 130px;">Included</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($steps as $step)
                            <tr>
                                <td>
                                    <input type="number" 
                                           name="steps[{{ $step['step_key'] }}][sort_order]" 
                                           class="form-control form-control-sm text-center" 
                                           value="{{ $step['sort_order'] }}" 
                                           min="1" 
                                           max="99" 
                                           style="width: 60px;">
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $step['step_name'] }}</div>
                                    <div class="text-muted small">{{ $step['description'] }}</div>
                                </td>
                                <td>
                                    <code>{{ $step['step_key'] }}</code>
                                </td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-inline-block fs-5 mb-0">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               role="switch" 
                                               name="steps[{{ $step['step_key'] }}][is_enabled]" 
                                               value="1" 
                                               @checked($step['is_enabled'])>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white py-3 text-end">
                    <button type="submit" class="btn btn-success px-4 fw-semibold">
                        <i class="bi bi-save me-1"></i>Save All Changes
                    </button>
                </div>
            </div>
        </div>

        <!-- Sidebar / Right Side: API Contract Preview -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 16px;">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-code-slash text-success me-2"></i>Live API Contract
                    </h6>
                    <small class="text-muted">Served directly at <code>GET /app-config</code></small>
                </div>
                <div class="card-body p-3">
                    <pre class="bg-dark text-success p-3 rounded-3 small mb-0" style="max-height: 380px; overflow-y: auto; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;"><code>{
  "features": {
    "tutorials": {{ $masterEnabled ? 'true' : 'false' }}
  },
  "tutorial_steps": {
@foreach($steps as $step)
    "{{ $step['step_key'] }}": {{ $step['is_enabled'] ? 'true' : 'false' }}{{ $loop->last ? '' : ',' }}
@endforeach
  }
}</code></pre>
                </div>
                <div class="card-footer bg-light p-3 small text-muted">
                    <i class="bi bi-info-circle me-1 text-primary"></i>
                    Mobile Flutter app will skip entire walkthrough if <code>tutorials</code> is false, or skip individual steps if false.
                </div>
            </div>

            <!-- Quick Action Box -->
            <div class="card shadow-sm border-0" style="border-radius: 16px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-success mb-2"><i class="bi bi-lightning-charge me-1"></i>Instant Cache Refresh</h6>
                    <p class="small text-muted mb-3">Saving changes immediately clears cached app-config responses for all platforms and active devices.</p>
                    <button type="submit" class="btn btn-success w-100 fw-semibold">
                        <i class="bi bi-check-lg me-1"></i>Publish Tutorial Toggles
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
