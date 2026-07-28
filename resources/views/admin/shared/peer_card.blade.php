@php
    /** @var \App\Models\User|null $user */
    $user = $user ?? null;

    $name = trim((string) ($user?->display_name ?? ''));
    if ($name === '') {
        $name = trim(trim((string) ($user?->first_name ?? '')) . ' ' . trim((string) ($user?->last_name ?? '')));
    }
    if ($name === '') {
        $name = trim((string) ($user?->email ?? ''));
    }
    if ($name === '') {
        $name = '—';
    }

    $company = trim((string) ($user?->company_name ?? $user?->business_name ?? $user?->company ?? ''));
    $city = trim((string) ($user?->city ?? ''));

    $circleNames = collect();
    if ($user?->relationLoaded('circleMembers')) {
        $circleNames = $user->circleMembers->map(fn($cm) => trim((string) optional($cm->circle)->name))->filter()->unique();
    }
    if ($circleNames->isEmpty() && $user?->relationLoaded('circles')) {
        $circleNames = $user->circles->map(fn($c) => trim((string) $c->name))->filter()->unique();
    }
    if ($circleNames->isEmpty()) {
        $circleNames = collect([$user?->adminCircleLabel() ?? '']);
    }
    $circleName = $circleNames->filter()->unique()->implode(', ');
    $initial = strtoupper(substr($name, 0, 1));
    $photoUrl = $user?->profile_photo_url;
@endphp

<div class="flex items-center gap-2.5">
    @if (!empty($photoUrl))
        <img src="{{ $photoUrl }}" alt="{{ $name }}" class="w-8 h-8 rounded-full object-cover border bs shrink-0" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
        <div class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 font-bold items-center justify-center text-xs border bs shrink-0" style="display:none;">
            {{ $initial !== '' ? $initial : 'P' }}
        </div>
    @else
        <div class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 font-bold flex items-center justify-center text-xs border bs shrink-0">
            {{ $initial !== '' ? $initial : 'P' }}
        </div>
    @endif
    <div class="flex flex-col min-w-0 text-left">
        <div class="font-semibold t1 text-[12.5px] truncate" title="{{ $name }}">{{ $name }}</div>
        <div class="text-[11px] t3 flex items-center gap-1.5 flex-wrap">
            @if($company !== '')<span>{{ $company }}</span>@endif
            @if($company !== '' && $city !== '')<span>&bull;</span>@endif
            @if($city !== '')<span>{{ $city }}</span>@endif
        </div>
        @if($circleName !== '')
            <div class="text-[10px] t3 opacity-75 truncate">{{ $circleName }}</div>
        @endif
    </div>
</div>

