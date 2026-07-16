@php
    $instance = env('APP_INSTANCE');
    if (!$instance) {
        $host = request()->getHost();
        if (str_contains($host, 'greenpreneur')) {
            $instance = 'greenpreneur';
        } else {
            $instance = 'peers';
        }
    }
@endphp

@if($instance === 'greenpreneur')
    @include('landing-greenpreneur')
@else
    @include('landing-peers')
@endif