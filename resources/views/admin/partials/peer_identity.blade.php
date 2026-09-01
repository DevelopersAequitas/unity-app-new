@php
    $user = $user ?? (object) [];
    $userId = $userId ?? $user->id ?? $user->uuid ?? null;

    $name = $user->name
        ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
        ?? '—';
    if (trim($name) === '' || $name === '—') {
        $name = $user->display_name ?? '—';
    }
    $name = trim($name) !== '' ? trim($name) : '—';

    $company = $user->company_name
        ?? $user->company
        ?? $user->business_name
        ?? $user->organization
        ?? '';

    $city = $user->city
        ?? $user->current_city
        ?? $user->location_city
        ?? '';

    $circleLine = $circleName ?? '';

    $getInitials = function($name) {
        $words = explode(' ', trim($name));
        $initials = '';
        foreach ($words as $w) {
            if(!empty($w)) $initials .= strtoupper(substr($w, 0, 1));
        }
        return substr($initials, 0, 2) ?: 'P';
    };

    $getAvatarBg = function($name) {
        $colors = ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6'];
        $hash = crc32($name);
        return $colors[abs($hash) % count($colors)];
    };
@endphp

<div class="peer-badge-wrapper">
    <div class="peer-badge-avatar" style="background-color: {{ $getAvatarBg($name) }}">
        {{ $getInitials($name) }}
    </div>
    <div class="peer-badge-info">
        <div class="peer-badge-name">
            @if(!empty($userId) && auth('admin')->check())
                <a href="#" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $userId }}', event);">
                    {{ $name }}
                </a>
            @else
                {{ $name }}
            @endif
        </div>
        <div class="peer-badge-meta">
            @if($company) <span>{{ $company }}</span> @endif
            @if($city) {!! $company ? ' &bull; ' : '' !!}<span>{{ $city }}</span> @endif
            @if($circleLine) {!! ($company || $city) ? ' &bull; ' : '' !!}<span>{{ $circleLine }}</span> @endif
        </div>
    </div>
</div>

