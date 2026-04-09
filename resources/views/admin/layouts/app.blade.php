<!DOCTYPE html>
<html lang="en">
<head>
    @php
        $adminCssVersion = @filemtime(public_path('css/admin.css')) ?: time();
        $adminSearchableSelectJsVersion = @filemtime(public_path('js/admin-searchable-select.js')) ?: time();
        $adminStickyXScrollJsVersion = @filemtime(public_path('js/admin-sticky-x-scroll.js')) ?: time();
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Panel')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ $adminCssVersion }}">
    @stack('styles')
</head>
<body>
    <div class="admin-shell d-flex">
        @include('admin.partials.sidebar')
        <div class="admin-main flex-grow-1">
            @include('admin.partials.topbar')
            <main class="admin-content container-fluid py-4">
                @yield('content')
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('js/admin-searchable-select.js') }}?v={{ $adminSearchableSelectJsVersion }}"></script>
    <script src="{{ asset('js/admin-sticky-x-scroll.js') }}?v={{ $adminStickyXScrollJsVersion }}"></script>
    @stack('scripts')
</body>
</html>
