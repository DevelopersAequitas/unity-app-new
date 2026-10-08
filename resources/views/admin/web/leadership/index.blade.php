@extends('admin.layouts.app')

@section('title', 'Leadership Selection Management - Peers Global Unity')

@push('styles')
<style>
    /* Styling adjustments for Leadership Selection React App */
    #leadership-admin-root {
        min-height: calc(100vh - 120px);
    }
    .badge-indigo {
        background-color: rgba(99, 102, 241, 0.12);
        color: #6366f1;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    <div id="leadership-admin-root">
        {{-- Fallback loading indicator while React bundle hydrates --}}
        <div class="d-flex flex-column align-items-center justify-content-center py-5" style="min-height: 400px;">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading Leadership Selection System...</span>
            </div>
            <h5 class="mt-3 text-dark fw-semibold">Loading Leadership Selection Management...</h5>
            <p class="text-muted small">Initializing dashboard, live campaigns, and verification engines</p>
        </div>
    </div>
</div>

<script>
    window.__UNITY_ADMIN_CONTEXT__ = @json($adminContext ?? []);
    window.__UNITY_API_BASE__ = "{{ url('/api/v1/leadership') }}";
</script>

@vite(['resources/js/leadership-admin/main.tsx'])
@endsection
