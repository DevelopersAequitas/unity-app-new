@extends('admin.layouts.app')

@section('title', '403 — Access Forbidden')

@section('content')
<div class="container-fluid py-5">
    <div class="row justify-content-center text-center">
        <div class="col-md-8 col-lg-6">
            <div class="card border-0 shadow-sm p-4 p-md-5 rounded-4 bg-white">
                <div class="mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-circle p-3 mb-3" style="width: 80px; height: 80px;">
                        <i class="bi bi-shield-lock-fill fs-1"></i>
                    </div>
                    <span class="badge bg-danger-subtle text-danger fw-semibold px-3 py-2 rounded-pill fs-6 d-block mx-auto" style="width: max-content;">HTTP 403 FORBIDDEN</span>
                </div>

                <h2 class="fw-bold text-dark mb-2">Access Restricted</h2>
                <p class="text-muted fs-6 mb-4">
                    You do not have permission or role access to edit or access this page. If you believe this is an error, please contact your System Administrator to adjust your role permissions.
                </p>

                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <button onclick="window.history.back()" class="btn btn-outline-secondary px-4 py-2 rounded-3">
                        <i class="bi bi-arrow-left me-1"></i> Go Back
                    </button>
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-primary px-4 py-2 rounded-3">
                        <i class="bi bi-speedometer2 me-1"></i> Return to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
