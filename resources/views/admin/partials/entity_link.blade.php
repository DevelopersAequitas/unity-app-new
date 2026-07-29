@php
    $label = $label ?? $name ?? null;
    $route = $route ?? null;
    $params = $params ?? $param ?? $id ?? null;
    $url = $url ?? null;
    $can = $can ?? true;
    $fallback = $fallback ?? '—';
    $class = $class ?? 'text-indigo-600 hover:text-indigo-800 hover:underline font-medium text-decoration-none';
    $target = $target ?? null;
    $stopPropagation = $stopPropagation ?? false;

    $hasLink = false;
    $href = '#';

    if ($can && filled($label)) {
        if ($url) {
            $hasLink = true;
            $href = $url;
        } elseif ($route && \Illuminate\Support\Facades\Route::has($route) && filled($params)) {
            try {
                $href = route($route, $params);
                $hasLink = true;
            } catch (\Throwable $e) {
                $hasLink = false;
            }
        }
    }
@endphp

@if($hasLink)
    <a href="{{ $href }}" class="{{ $class }}" @if($target) target="{{ $target }}" @endif @if($stopPropagation) onclick="event.stopPropagation();" @endif>{{ $label }}</a>
@else
    <span>{{ filled($label) ? $label : $fallback }}</span>
@endif
