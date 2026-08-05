@extends('admin.layouts.app')

@section('title', '404 — Page Not Found')

@section('content')
<div class="container-fluid py-5">
    <div class="row justify-content-center text-center">
        <div class="col-md-8 col-lg-6">
            <div class="card border-0 shadow-sm p-4 p-md-5 rounded-4 bg-white">
                <div class="mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-warning-subtle text-warning rounded-circle p-3 mb-3" style="width: 80px; height: 80px;">
                        <i class="bi bi-file-earmark-x-fill fs-1"></i>
                    </div>
                    <span class="badge bg-warning-subtle text-warning-emphasis fw-semibold px-3 py-2 rounded-pill fs-6 d-block w-auto mx-auto" style="width: max-content;">HTTP 404 NOT FOUND</span>
                </div>

                <h2 class="fw-bold text-dark mb-2">Page Not Found</h2>
                <p class="text-muted fs-6 mb-4">
                    The page or resource you are looking for does not exist or may have been moved.
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
