@extends('admin.layouts.app')

@section('title', ($circle->name ?? 'Circle') . ' Circle')

@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    corePlugins: {
      preflight: false,
    }
  }
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  #grid-root-container {
    --bg:#0A0E17; --surface:#10141F; --surface-2:#141926; --surface-3:#1A2030;
    --border:#232A3B; --border-soft:#1B2130;
    --text-1:#EEF0F5; --text-2:#9096A8; --text-3:#5C6478;
    --accent:#6366F1; --accent-2:#8B5CF6; --accent-soft:#6366F11A;
    --success:#10B981; --success-soft:#10B9811A;
    --warning:#F59E0B; --warning-soft:#F59E0B1A;
    --danger:#F43F5E; --danger-soft:#F43F5E1A;
    --info:#0EA5E9; --info-soft:#0EA5E91A;
    background-color: var(--bg);
    color: var(--text-1);
    font-family: 'Inter', sans-serif;
  }
  #grid-root-container.light {
    --bg:#F8FAFC; --surface:#FFFFFF; --surface-2:#F1F5F9; --surface-3:#E2E8F0;
    --border:#E2E8F0; --border-soft:#F1F5F9;
    --text-1:#0F172A; --text-2:#475569; --text-3:#94A3B8;
  }
  
  #grid-root-container .font-display { font-family: 'Lexend', sans-serif; }
  #grid-root-container .font-mono { font-family: 'JetBrains Mono', monospace; }
  #grid-root-container .t1 { color: var(--text-1); }
  #grid-root-container .t2 { color: var(--text-2); }
  #grid-root-container .t3 { color: var(--text-3); }
  #grid-root-container .bg-accent, .bg-accent { background-color: var(--accent) !important; }
  #grid-root-container .text-accent, .text-accent { color: var(--accent) !important; }
  #grid-root-container .accent, .accent { color: var(--accent) !important; }
  #grid-root-container .surface { background-color: var(--surface) !important; }
  #grid-root-container .surface-2 { background-color: var(--surface-2) !important; }
  #grid-root-container .surface-3 { background-color: var(--surface-3) !important; }
  #grid-root-container .border { border-color: var(--border); }
  #grid-root-container .bs { border-color: var(--border-soft); }
  
  #grid-root-container table { border-color: var(--border-soft) !important; }
  #grid-root-container th { border-color: var(--border-soft) !important; }
  #grid-root-container td { border-color: var(--border-soft) !important; }
  
  #grid-root-container input[type="text"], 
  #grid-root-container input[type="email"], 
  #grid-root-container input[type="date"], 
  #grid-root-container select, 
  #grid-root-container textarea {
    background-color: var(--surface-2) !important;
    border-color: var(--border) !important;
    color: var(--text-1) !important;
  }

  .scrim { backdrop-filter: blur(4px); transition: all 0.3s ease; }
  .drawer { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
  .drawer-hidden { transform: translateX(100%); }
</style>
@endpush

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card">

    <div class="flex flex-wrap justify-between items-center mb-4 gap-3">
        <div>
            <h2 class="font-display font-semibold text-base t1 m-0 flex items-center gap-2">
                <i class="bi bi-people-fill text-indigo-500"></i> {{ $circle->name ?? 'Circle' }}
            </h2>
            <p class="text-xs t3 m-0 mt-0.5">Circle details overview & peers listing</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.circles.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline flex items-center gap-1">
                <i class="bi bi-arrow-left"></i> Back
            </a>

            <a href="{{ route('admin.circles.edit', $circle) }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition no-underline flex items-center gap-1">
                <i class="bi bi-pencil"></i> Edit Circle
            </a>

            <form action="{{ route('admin.circles.destroy', $circle) }}" method="POST" class="inline m-0"
                  onsubmit="return confirm('Delete this circle? This is a soft delete and can be restored by admin.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-50 text-rose-700 border border-rose-200 text-xs font-semibold hover:bg-rose-100 transition cursor-pointer flex items-center gap-1">
                    <i class="bi bi-trash"></i> Delete
                </button>
            </form>
        </div>
    </div>

    <div id="js-alert-container"></div>

    @if (session('success'))
        <div class="alert alert-success mb-4 text-xs">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger mb-4 text-xs">
            <strong class="font-semibold block mb-1">There were some problems with your input:</strong>
            <ul class="mb-0 text-xs list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <?php

use Carbon\Carbon;
    use Illuminate\Pagination\LengthAwarePaginator;
    use Illuminate\Pagination\Paginator;
    use Illuminate\Support\Collection;

            $circleCategories = collect();

    if (($categoryFeatureEnabled ?? false) && method_exists($circle, 'categories')) {
        try {
            $circleCategories = $circle->categories ?? collect();
        } catch (Throwable $e) {
            $circleCategories = collect();
        }
    }

    $circleStatus = data_get($circle, 'status') ?: 'active';
    $circleType = data_get($circle, 'type') ?: 'public';
    $circleSlug = data_get($circle, 'slug') ?: '—';
    $circleCity = data_get($circle, 'city.name') ?: (data_get($circle, 'city_name') ?: '—');
    $circleCountry = data_get($circle, 'city.country') ?: (data_get($circle, 'country') ?: '—');

    $founderUser = $circle->circleFounder ?? $circle->founder ?? null;
    $founderName = $founderUser
        ? ($founderUser->display_name
            ?: $founderUser->name
            ?: trim((string) ($founderUser->first_name ?? '').' '.(string) ($founderUser->last_name ?? '')))
        : null;
    if (empty($founderName)) {
        $founderName = data_get($circle, 'circleFounder.display_name')
            ?: data_get($circle, 'circleFounder.name')
            ?: data_get($circle, 'founder.display_name')
            ?: data_get($circle, 'founder.name');
    }
    $founderName = trim((string) $founderName) !== '' ? trim((string) $founderName) : '—';
    $circleFounder = $founderName;
    $founderId = $founderUser->id ?? null;

    $circleDescription = data_get($circle, 'description') ?: '—';
    $circlePurpose = data_get($circle, 'purpose') ?: '—';
    $circleAnnouncement = data_get($circle, 'announcement') ?: '—';

    $industryTags = data_get($circle, 'industry_tags');
    if (is_array($industryTags)) {
        $industryTagsText = implode(', ', array_filter($industryTags));
    } elseif (is_string($industryTags) && trim($industryTags) !== '') {
        $industryTagsText = $industryTags;
    } else {
        $industryTagsText = '—';
    }

    $displayValue = static function ($value) {
        if (is_string($value)) {
            $value = trim($value);
        }

        if (! filled($value)) {
            return '<span class="t3">—</span>';
        }

        if (str_contains((string) $value, '<a ')) {
            return '<span class="font-semibold t1">'.$value.'</span>';
        }

        return '<span class="font-semibold t1">'.e($value).'</span>';
    };
    $formatUser = static function ($user) {
        if (! $user) {
            return null;
        }

        if (is_array($user)) {
            $id = data_get($user, 'id');
            $name = data_get($user, 'display_name') ?: data_get($user, 'name') ?: trim((string) data_get($user, 'first_name', '').' '.(string) data_get($user, 'last_name', ''));

            return ['id' => $id ? (string) $id : null, 'name' => $name ?: '—'];
        }

        if (is_string($user) || is_numeric($user)) {
            $uStr = trim((string) $user);

            return $uStr !== '' ? ['id' => null, 'name' => $uStr] : null;
        }

        $name = data_get($user, 'display_name')
            ?: data_get($user, 'name')
            ?: trim((string) data_get($user, 'first_name', '').' '.(string) data_get($user, 'last_name', ''));

        $name = trim((string) $name);
        $email = trim((string) data_get($user, 'email', ''));

        $text = $name !== '' && $email !== '' ? $name.' ('.$email.')' : ($name !== '' ? $name : ($email !== '' ? $email : null));

        $userId = data_get($user, 'id');
        if (! $text && ! $userId) {
            return null;
        }

        return [
            'id' => $userId ? (string) $userId : null,
            'name' => $text ?: (string) $userId,
        ];
    };

    $calendar = is_array($circle->calendar ?? null) ? $circle->calendar : [];

    $meetingMode = data_get($circle, 'meeting_mode');
    if (! $meetingMode) {
        $meetingMode = data_get($calendar, 'settings.meeting_mode');
    }
    $meetingMode = $meetingMode ? ucfirst(strtolower((string) $meetingMode)) : null;

    $meetingFrequency = data_get($circle, 'meeting_frequency');
    if (! $meetingFrequency) {
        $meetingFrequency = data_get($calendar, 'settings.meeting_frequency');
    }
    $meetingFrequency = $meetingFrequency ? ucfirst(strtolower((string) $meetingFrequency)) : null;

    $launchDateRaw = data_get($circle, 'launch_date') ?: data_get($calendar, 'settings.launch_date');
    $launchDate = '—';
    if (! empty($launchDateRaw)) {
        try {
            $launchDate = Illuminate\Support\Carbon::parse($launchDateRaw)->format('d M Y');
        } catch (Throwable $e) {
            $launchDate = (string) $launchDateRaw;
        }
    }

    $meetingRepeat = data_get($circle, 'meeting_repeat');
    if (! is_array($meetingRepeat)) {
        $meetingRepeat = data_get($calendar, 'settings.meeting_repeat');
    }
    $meetingRepeat = is_array($meetingRepeat) ? $meetingRepeat : null;

    $coverFileId = data_get($circle, 'cover_file_id');
    if (! $coverFileId) {
        $coverFileId = data_get($calendar, 'cover.file_id');
    }

    $meetingLink = data_get($circle, 'meeting_link') ?: data_get($calendar, 'settings.meeting_link') ?: data_get($circle, 'zoho_join_url');
    $meetingPasscode = data_get($circle, 'meeting_passcode') ?: data_get($calendar, 'settings.meeting_passcode') ?: data_get($circle, 'zoho_meeting_password');
    $meetingVenue = data_get($circle, 'meeting_venue') ?: data_get($calendar, 'settings.meeting_venue') ?: data_get($calendar, 'settings.meeting_address');
    $meetingLandmark = data_get($circle, 'meeting_landmark') ?: data_get($calendar, 'settings.meeting_landmark');

    $peerFilters = is_array($peerFilters ?? null) ? $peerFilters : [
        'peer_name' => request('peer_name', ''),
        'peer_email' => request('peer_email', ''),
    ];

    $peerNameFilter = trim((string) ($peerFilters['peer_name'] ?? ''));
    $peerEmailFilter = trim((string) ($peerFilters['peer_email'] ?? ''));

    $membersSource = $peerMembers ?? ($circle->members ?? collect());

    $isPaginator = $membersSource instanceof LengthAwarePaginator
        || $membersSource instanceof Illuminate\Contracts\Pagination\Paginator
        || $membersSource instanceof Illuminate\Contracts\Pagination\LengthAwarePaginator;

    if ($isPaginator) {
        $peerMembers = $membersSource;
        $peerItems = collect($peerMembers->items());
        $peerCurrentPage = method_exists($peerMembers, 'currentPage') ? $peerMembers->currentPage() : 1;
        $peerHasPagination = true;
    } else {
        $peerItems = $membersSource instanceof Collection ? $membersSource : collect($membersSource);

        if ($peerNameFilter !== '' || $peerEmailFilter !== '') {
            $peerItems = $peerItems->filter(function ($membership) use ($peerNameFilter, $peerEmailFilter) {
                $member = $membership->user ?? null;

                $memberName = trim(
                    (string) data_get($member, 'name',
                        trim((string) data_get($member, 'first_name', '').' '.(string) data_get($member, 'last_name', ''))
                    )
                );

                if ($memberName === '') {
                    $memberName = trim((string) data_get($member, 'display_name', ''));
                }

                $memberEmail = trim((string) data_get($member, 'email', ''));

                $nameMatch = $peerNameFilter === '' || str_contains(mb_strtolower($memberName), mb_strtolower($peerNameFilter));
                $emailMatch = $peerEmailFilter === '' || str_contains(mb_strtolower($memberEmail), mb_strtolower($peerEmailFilter));

                return $nameMatch && $emailMatch;
            })->values();
        }

        $page = max((int) request('page', 1), 1);
        $perPage = 10;
        $total = $peerItems->count();
        $itemsForPage = $peerItems->slice(($page - 1) * $perPage, $perPage)->values();

        $peerMembers = new LengthAwarePaginator(
            $itemsForPage,
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'query' => request()->query(),
            ]
        );

        $peerCurrentPage = $page;
        $peerHasPagination = $total > $perPage;
        $peerItems = collect($peerMembers->items());
    }

    if ($isPaginator) {
        $peerItems = collect($peerMembers->items());
    }

    $circleStage = $circleStage ?? data_get($circle, 'stage.name') ?? data_get($circle, 'circleStage.name') ?? data_get($circle, 'circle_stage') ?? null;

    $safeStr = static function ($value, $default = '—') {
        if ($value === null) {
            return $default;
        }
        if (is_array($value)) {
            $filtered = array_filter(array_map('trim', array_map('strval', array_filter($value, fn ($v) => is_scalar($v)))));

            return ! empty($filtered) ? implode(', ', $filtered) : $default;
        }
        if (is_object($value)) {
            $val = data_get($value, 'name') ?: data_get($value, 'display_name') ?: (string) $value;

            return trim((string) $val) !== '' ? trim((string) $val) : $default;
        }
        $s = trim((string) $value);

        return $s !== '' ? $s : $default;
    };

    $peersJsonData = collect($peerItems)->map(function ($membership) use ($circle, $safeStr) {
        $m = $membership->user ?? null;

        $memberName = $m ? trim((string) (($m->first_name ?? '').' '.($m->last_name ?? ''))) : '';
        if ($memberName === '') {
            $memberName = $safeStr(data_get($m, 'display_name') ?: data_get($m, 'name'));
        }

        $memberCompany = $safeStr(data_get($m, 'company_name') ?: data_get($m, 'business_name'));
        $memberCity = $safeStr(data_get($m, 'city.name') ?: data_get($m, 'city_name') ?: data_get($m, 'city'));
        $memberCountry = $safeStr(data_get($m, 'city.country') ?: data_get($m, 'country'), 'India');
        $memberEmail = $safeStr(data_get($m, 'email'));
        $memberMobile = $safeStr(data_get($m, 'mobile') ?: data_get($m, 'phone'));
        $memberIndustry = $safeStr(data_get($m, 'industry') ?: data_get($m, 'industry_tags'));
        $memberCode = $safeStr(data_get($m, 'user_code') ?: data_get($m, 'id'));
        $status = ucfirst(strtolower((string) ($membership->status ?? data_get($m, 'status') ?? 'active')));
        $role = ucwords(str_replace('_', ' ', (string) ($membership->role ?? 'member')));

        $circleType = strtoupper((string) ($circle->type ?? 'PUBLIC'));
        $circleStage = $safeStr($circle->circle_stage ?? data_get($circle, 'stage.name'));
        $meetingMode = $safeStr(! empty($circle->meeting_mode) ? ucfirst(strtolower($circle->meeting_mode)) : null);
        $meetingFreq = $safeStr(! empty($circle->meeting_frequency) ? ucfirst(strtolower($circle->meeting_frequency)) : null);

        $joinedAt = optional($membership->joined_at ?? $membership->created_at ?? null)->format('d M Y') ?? '—';
        $paymentStatus = ucfirst(strtolower((string) ($membership->payment_status ?? data_get($m, 'payment_status') ?? 'paid')));
        $subscriptionStatus = ucfirst(strtolower((string) ($membership->subscription_status ?? 'active')));
        $billingTerm = ucfirst(strtolower((string) ($membership->billing_term ?? 'annual')));

        $startsAtRaw = $membership->paid_starts_at ?? data_get($m, 'membership_starts_at');
        $startsAt = $startsAtRaw ? Carbon::parse($startsAtRaw)->format('d M Y') : $joinedAt;

        $endsAtRaw = $membership->expires_at ?? $membership->paid_ends_at ?? data_get($m, 'membership_ends_at');
        $endsAt = $endsAtRaw ? Carbon::parse($endsAtRaw)->format('d M Y') : '—';

        $editUrl = $m && ! empty($m->id) ? route('admin.users.show', $m->id) : '#';

        return [
            'id' => (string) ($m ? $m->id : ''),
            'name' => $memberName,
            'email' => $memberEmail,
            'mobile' => $memberMobile,
            'company' => $memberCompany,
            'industry' => $memberIndustry,
            'city' => $memberCity,
            'country' => $memberCountry,
            'circle' => $circle->name ?? '—',
            'circle_type' => $circleType,
            'circle_stage' => $circleStage,
            'meeting_mode' => $meetingMode,
            'meeting_freq' => $meetingFreq,
            'role' => $role,
            'status' => $status,
            'mid' => $memberCode,
            'joined' => $joinedAt,
            'payment_status' => $paymentStatus,
            'subscription_status' => $subscriptionStatus,
            'billing_term' => $billingTerm,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'edit_url' => $editUrl,
            'avatar' => data_get($m, 'avatar_url'),
            'color' => '#6366F1',
        ];
    })->values();
    ?>

    <!-- TOP GRID CARDS -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        <!-- Circle Overview -->
        <div class="border bs rounded-xl p-4 surface flex flex-col justify-between">
            <div>
                <h3 class="font-display font-semibold text-xs uppercase tracking-wider text-indigo-400 mb-3 m-0 flex items-center gap-1.5">
                    <span>⭕</span> Circle Overview
                </h3>
                <div class="flex items-center gap-2 mb-4">
                    <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 uppercase">
                        • {{ $circleStatus }}
                    </span>
                    <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-indigo-50 text-indigo-600 border border-indigo-200 uppercase">
                        {{ $circleType }}
                    </span>
                </div>
                <div class="space-y-2.5 text-xs border bs rounded-xl p-3.5 surface-2">
                    <div class="flex justify-between gap-4"><span class="t3">Slug</span><span class="t1 font-medium font-mono">{{ $circleSlug }}</span></div>
                    <div class="flex justify-between"><span class="t3">City</span><span class="t1 font-medium">{{ $circleCity }}</span></div>
                    <div class="flex justify-between"><span class="t3">Country</span><span class="t1 font-medium">{{ $circleCountry }}</span></div>
                    <div class="flex justify-between items-center">
                        <span class="t3">Founder</span>
                        <span class="t1 font-medium">
                            @if(!empty($founderId))
                                <a href="#" data-peer-id="{{ $founderId }}" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $founderId }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                    {{ $founderName }}
                                </a>
                            @else
                                <span>{{ $founderName }}</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between"><span class="t3">Created</span><span class="t1 font-medium">{{ optional($circle->created_at)->format('Y-m-d H:i') ?: '—' }}</span></div>
                </div>
            </div>
        </div>

        <!-- Narrative & Tags -->
        <div class="border bs rounded-xl p-4 surface flex flex-col justify-between">
            <div>
                <h3 class="font-display font-semibold text-xs uppercase tracking-wider text-indigo-400 mb-3 m-0 flex items-center gap-1.5">
                    <i class="bi bi-globe admin-icon me-1" aria-hidden="true"></i> Narrative & Tags
                </h3>
                <div class="space-y-2.5 text-xs border bs rounded-xl p-3.5 surface-2">
                    <div><span class="t3 block mb-0.5 font-medium">Description</span><p class="t1 leading-relaxed m-0">{{ $circleDescription }}</p></div>
                    <div><span class="t3 block mb-0.5 font-medium">Purpose</span><p class="t1 leading-relaxed m-0">{{ $circlePurpose }}</p></div>
                    <div><span class="t3 block mb-0.5 font-medium">Announcement</span><p class="t1 leading-relaxed m-0">{{ $circleAnnouncement }}</p></div>
                    <div><span class="t3 block mb-0.5 font-medium">Industry Tags</span><p class="t1 leading-relaxed m-0">{{ $industryTagsText }}</p></div>
                    <div>
                        <span class="t3 block mb-1 font-medium">Categories</span>
                        <div class="flex flex-wrap gap-1.5 mt-1">
                            @forelse($circleCategories as $category)
                                <span class="px-2.5 py-0.5 text-[11px] font-semibold rounded-md border bs surface t1">
                                    {{ data_get($category, 'name', '—') }}
                                </span>
                            @empty
                                <span class="px-2.5 py-0.5 text-[11px] font-semibold rounded-md border bs surface t1">
                                    Manufacturing & Engineering Circles
                                </span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CIRCLE SETTINGS CARD -->
    <div class="border bs rounded-xl p-4 surface mb-4">
        <h3 class="font-display font-semibold text-xs uppercase tracking-wider text-indigo-400 mb-3 m-0 flex items-center gap-1.5">
            <i class="bi bi-gear admin-icon me-1" aria-hidden="true"></i> Circle Settings
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
            <div class="p-3 border bs rounded-xl surface-2">
                <span class="t3 block mb-1 font-medium">Meeting Mode</span>
                <span class="t1 font-semibold">{!! $displayValue($meetingMode) !!}</span>
            </div>
            <div class="p-3 border bs rounded-xl surface-2">
                <span class="t3 block mb-1 font-medium">Meeting Frequency</span>
                <span class="t1 font-semibold">{!! $displayValue($meetingFrequency) !!}</span>
            </div>
            <div class="p-3 border bs rounded-xl surface-2">
                <span class="t3 block mb-1 font-medium">Launch Date</span>
                <span class="t1 font-semibold">{!! $displayValue($launchDate !== '—' ? $launchDate : null) !!}</span>
            </div>
            <div class="p-3 border bs rounded-xl surface-2">
                <span class="t3 block mb-1 font-medium">Circle Stage</span>
                <span class="t1 font-semibold">{!! $displayValue($circleStage) !!}</span>
            </div>
            <div class="p-3 border bs rounded-xl surface-2">
                <span class="t3 block mb-1 font-medium">Circle Director</span>
                @php $usr = $formatUser($circle->circleDirector ?? $circle->director ?? null); @endphp
                @if(is_array($usr) && !empty($usr['name']))
                    @if(!empty($usr['id']))
                        <a href="#" data-peer-id="{{ $usr['id'] }}" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $usr['id'] }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                            {{ $usr['name'] }}
                        </a>
                    @else
                        <span class="font-semibold t1">{{ $usr['name'] }}</span>
                    @endif
                @elseif(is_string($usr) && trim($usr) !== '')
                    {!! $usr !!}
                @else
                    <span class="t3">—</span>
                @endif
            </div>
            <div class="p-3 border bs rounded-xl surface-2">
                <span class="t3 block mb-1 font-medium">Industry Director</span>
                @php $usr = $formatUser($circle->industryDirector ?? null); @endphp
                @if(is_array($usr) && !empty($usr['name']))
                    @if(!empty($usr['id']))
                        <a href="#" data-peer-id="{{ $usr['id'] }}" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $usr['id'] }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                            {{ $usr['name'] }}
                        </a>
                    @else
                        <span class="font-semibold t1">{{ $usr['name'] }}</span>
                    @endif
                @elseif(is_string($usr) && trim($usr) !== '')
                    {!! $usr !!}
                @else
                    <span class="t3">—</span>
                @endif
            </div>
            <div class="p-3 border bs rounded-xl surface-2">
                <span class="t3 block mb-1 font-medium">DED</span>
                @php $usr = $formatUser($circle->ded ?? null); @endphp
                @if(is_array($usr) && !empty($usr['name']))
                    @if(!empty($usr['id']))
                        <a href="#" data-peer-id="{{ $usr['id'] }}" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $usr['id'] }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                            {{ $usr['name'] }}
                        </a>
                    @else
                        <span class="font-semibold t1">{{ $usr['name'] }}</span>
                    @endif
                @elseif(is_string($usr) && trim($usr) !== '')
                    {!! $usr !!}
                @else
                    <span class="t3">—</span>
                @endif
            </div>
            <div class="p-3 border bs rounded-xl surface-2">
                <span class="t3 block mb-1 font-medium">EED</span>
                @php $usr = $formatUser($circle->eed ?? null); @endphp
                @if(is_array($usr) && !empty($usr['name']))
                    @if(!empty($usr['id']))
                        <a href="#" data-peer-id="{{ $usr['id'] }}" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $usr['id'] }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                            {{ $usr['name'] }}
                        </a>
                    @else
                        <span class="font-semibold t1">{{ $usr['name'] }}</span>
                    @endif
                @elseif(is_string($usr) && trim($usr) !== '')
                    {!! $usr !!}
                @else
                    <span class="t3">—</span>
                @endif
            </div>
            <div class="p-3 border bs rounded-xl surface-2 md:col-span-2">
                <span class="t3 block mb-1 font-medium">Meeting Repeat</span>
                @if ($meetingRepeat)
                    <div class="space-y-1 mt-1">
                        @foreach ($meetingRepeat as $key => $value)
                            <div>
                                <span class="t3">{{ ucwords(str_replace('_', ' ', (string) $key)) }}:</span>
                                <span class="t1 font-semibold">{{ is_scalar($value) ? $value : json_encode($value) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <span class="t3">—</span>
                @endif
            </div>
            <div class="p-3 border bs rounded-xl surface-2">
                <span class="t3 block mb-1 font-medium">Cover</span>
                @if ($coverFileId)
                    <div class="flex flex-col gap-2 mt-1">
                        <img src="{{ url('/api/v1/files/' . $coverFileId) }}" alt="Circle Cover" class="rounded-lg border bs max-h-24 w-auto object-cover">
                        <a href="{{ url('/api/v1/files/' . $coverFileId) }}" target="_blank" class="px-2.5 py-1 rounded border bs text-xs font-semibold text-indigo-600 no-underline self-start">View</a>
                    </div>
                @else
                    <span class="t3">—</span>
                @endif
            </div>
            @if ($meetingLink || $meetingPasscode)
                <div class="p-3 border bs rounded-xl surface-2 md:col-span-2">
                    <span class="t3 block mb-1 font-medium flex items-center gap-1 text-indigo-500">
                        <i class="bi bi-camera-video"></i> Online Meeting Details
                    </span>
                    @if ($meetingLink)
                        <div class="truncate">
                            <span class="t3">Link: </span>
                            <a href="{{ $meetingLink }}" target="_blank" class="text-indigo-600 hover:underline font-medium no-underline">{{ $meetingLink }}</a>
                        </div>
                    @endif
                    @if ($meetingPasscode)
                        <div class="mt-0.5"><span class="t3">Passcode / ID: </span><span class="t1 font-semibold">{{ $meetingPasscode }}</span></div>
                    @endif
                </div>
            @endif
            @if ($meetingVenue || $meetingLandmark)
                <div class="p-3 border bs rounded-xl surface-2 md:col-span-2">
                    <span class="t3 block mb-1 font-medium flex items-center gap-1 text-emerald-600">
                        <i class="bi bi-geo-alt"></i> Offline / Venue Details
                    </span>
                    @if ($meetingVenue)
                        <div class="t1 font-medium leading-relaxed">{{ $meetingVenue }}</div>
                    @endif
                    @if ($meetingLandmark)
                        <div class="mt-0.5 text-xs"><span class="t3">Landmark: </span><span class="t1 font-semibold">{{ $meetingLandmark }}</span></div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <?php
        $circleApiData = (new \App\Http\Resources\CircleResource($circle))->toArray(request());
        $leadershipTeamData = data_get($circleApiData, 'circle_leaders') ?: data_get($circleApiData, 'leadership_team', []);
        $regionalLeadersData = array_filter(data_get($circleApiData, 'regional_leaders', []) ?: []);

        $chairItem = data_get($leadershipTeamData, 'chair');
        $bgChairItem = data_get($leadershipTeamData, 'business_growth_committee_chair');
        $mgChairItem = data_get($leadershipTeamData, 'membership_growth_committee_chair');
        $eiChairItem = data_get($leadershipTeamData, 'events_impacts_committee_chair');

        $committeeChairsList = array_filter([
            'Business Growth Committee' => $bgChairItem,
            'Membership Growth Committee' => $mgChairItem,
            'Events & Impacts Committee' => $eiChairItem,
        ], fn ($item) => ! empty($item));

        $hasLeadershipTeam = ! empty($chairItem) || ! empty($committeeChairsList);
        $hasRegionalLeaders = ! empty($regionalLeadersData);
    ?>

    @if ($hasLeadershipTeam || $hasRegionalLeaders)
        <!-- CIRCLE LEADERSHIP & REGIONAL LEADERSHIP -->
        <div class="grid grid-cols-1 {{ $hasLeadershipTeam && $hasRegionalLeaders ? 'lg:grid-cols-2' : '' }} gap-4 mb-4">
            @if ($hasLeadershipTeam)
                <!-- Committee Leadership Section -->
                <div class="space-y-4">
                    @if (! empty($committeeChairsList) || ! empty($chairItem))
                        <div class="border bs rounded-xl p-4 surface">
                            <h3 class="font-display font-semibold text-xs uppercase tracking-wider text-indigo-400 mb-3 m-0 flex items-center gap-1.5">
                                <i class="bi bi-shield-check admin-icon me-1" aria-hidden="true"></i> Committee Leadership
                            </h3>

                            <!-- General Circle Chair (if assigned) -->
                            @if ($chairItem)
                                <div class="p-3 border bs rounded-xl surface-2 mb-3">
                                    <span class="t3 block mb-1 font-medium text-xs">Circle Chair</span>
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-xs">
                                            <i class="bi bi-person-badge"></i>
                                        </div>
                                        <div>
                                            <div class="t1 font-semibold text-xs">
                                                {{ data_get($chairItem, 'name') ?: data_get($chairItem, 'designation', 'Chair') }}
                                            </div>
                                            @if (data_get($chairItem, 'email'))
                                                <div class="text-[11px] t3">{{ data_get($chairItem, 'email') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <!-- Committee Headings & Chair Members -->
                            <div class="space-y-2">
                                @foreach ($committeeChairsList as $committeeTitle => $item)
                                    <div class="p-3 border bs rounded-xl surface-2 text-xs flex justify-between items-center flex-wrap gap-2">
                                        <div>
                                            <span class="text-xs font-bold text-indigo-400 block mb-0.5">{{ $committeeTitle }}</span>
                                            <div class="t1 font-semibold text-xs flex items-center gap-1.5">
                                                <span>{{ data_get($item, 'name') ?: data_get($item, 'designation', 'Chair') }}</span>
                                            </div>
                                            @if (data_get($item, 'company_name'))
                                                <span class="t3 text-[11px] block mt-0.5">{{ data_get($item, 'company_name') }}</span>
                                            @endif
                                        </div>
                                        @if (data_get($item, 'email') || data_get($item, 'phone'))
                                            <div class="text-right text-[11px]">
                                                @if (data_get($item, 'email'))
                                                    <div class="t2">{{ data_get($item, 'email') }}</div>
                                                @endif
                                                @if (data_get($item, 'phone'))
                                                    <div class="t3">{{ data_get($item, 'phone') }}</div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            @if ($hasRegionalLeaders)
                <!-- Regional Leadership Section -->
                <div class="border bs rounded-xl p-4 surface">
                    <h3 class="font-display font-semibold text-xs uppercase tracking-wider text-indigo-400 mb-3 m-0 flex items-center gap-1.5">
                        <i class="bi bi-geo-alt admin-icon me-1" aria-hidden="true"></i> Regional Leadership
                    </h3>

                    <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                        @foreach ($regionalLeadersData as $leader)
                            <div class="p-3 border bs rounded-xl surface-2 text-xs">
                                <div class="flex justify-between items-start mb-1 flex-wrap gap-1">
                                    <span class="t1 font-semibold">{{ data_get($leader, 'name') ?: data_get($leader, 'designation', 'Regional Leader') }}</span>
                                    @if (data_get($leader, 'region'))
                                        <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-indigo-50 text-indigo-600 border border-indigo-200">
                                            {{ data_get($leader, 'region') }}
                                        </span>
                                    @endif
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1 text-[11px]">
                                    @if (data_get($leader, 'designation') && data_get($leader, 'name'))
                                        <div><span class="t3">Designation:</span> <span class="t2 font-medium">{{ data_get($leader, 'designation') }}</span></div>
                                    @endif
                                    @if (data_get($leader, 'chapter'))
                                        <div><span class="t3">Chapter:</span> <span class="t2 font-medium">{{ data_get($leader, 'chapter') }}</span></div>
                                    @endif
                                    @if (data_get($leader, 'training_info'))
                                        <div class="col-span-full"><span class="t3">Training:</span> <span class="t2 font-medium">{{ data_get($leader, 'training_info') }}</span></div>
                                    @endif
                                    @if (data_get($leader, 'email'))
                                        <div><span class="t3">Email:</span> <span class="t2 font-medium">{{ data_get($leader, 'email') }}</span></div>
                                    @endif
                                    @if (data_get($leader, 'phone'))
                                        <div><span class="t3">Phone:</span> <span class="t2 font-medium">{{ data_get($leader, 'phone') }}</span></div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- RANKING & MEETING SCHEDULE -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        <div class="border bs rounded-xl p-4 surface">
            <h3 class="font-display font-semibold text-xs uppercase tracking-wider text-amber-500 mb-3 m-0 flex items-center gap-1.5">
                <span>⭐</span> Circle Ranking
            </h3>
            <div class="grid grid-cols-3 gap-3 text-xs">
                <div class="p-3 border bs rounded-xl surface-2">
                    <span class="t3 block mb-1 font-medium">Total Members</span>
                    <span class="t1 font-bold text-sm">{{ data_get($rankingData ?? [], 'total_members', 0) }}</span>
                </div>
                <div class="p-3 border bs rounded-xl surface-2">
                    <span class="t3 block mb-1 font-medium">Rank</span>
                    <span class="t1 font-bold text-sm">{{ data_get($rankingData ?? [], 'rank', '—') }}</span>
                </div>
                <div class="p-3 border bs rounded-xl surface-2">
                    <span class="t3 block mb-1 font-medium">Circle Title</span>
                    <span class="t1 font-bold text-sm">{{ data_get($rankingData ?? [], 'title', '—') }}</span>
                </div>
            </div>
        </div>

        <div class="border bs rounded-xl p-4 surface">
            <h3 class="font-display font-semibold text-xs uppercase tracking-wider text-indigo-400 mb-3 m-0 flex items-center gap-1.5">
                <i class="bi bi-calendar-event admin-icon me-1" aria-hidden="true"></i> Meeting Schedule
            </h3>
            @if (empty($meetingRows ?? []))
                <div class="t3 text-xs p-3 border bs rounded-xl surface-2">—</div>
            @else
                <ul class="divide-y divide-gray-200/50 p-0 m-0 list-none text-xs">
                    @foreach ($meetingRows as $meetingRow)
                        <li class="py-2 flex justify-between items-center">
                            <span class="t1 font-medium">{{ data_get($meetingRow, 'label', 'Meeting') }}:</span>
                            <span class="t2">{{ data_get($meetingRow, 'value', '—') }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="text-[11px] t3 mt-2">Timezone: {{ $timezone ?? config('app.timezone', 'UTC') }}</div>
            @endif
        </div>
    </div>

    <!-- PEERS CARD -->
    <div class="border bs rounded-xl p-4 surface mb-2">
        <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
            <h3 class="font-display font-semibold text-xs uppercase tracking-wider text-indigo-400 m-0 flex items-center gap-1.5">
                <i class="bi bi-people-fill admin-icon me-1" aria-hidden="true"></i> Peers
            </h3>
        </div>

        <form id="add-peer-form" action="{{ route('admin.circles.members.store', $circle) }}" method="POST" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end mb-4 p-3 border bs rounded-xl surface-2">
            @csrf
            <input type="hidden" name="peer_name" value="{{ $peerNameFilter }}">
            <input type="hidden" name="peer_email" value="{{ $peerEmailFilter }}">
            <input type="hidden" name="page" value="{{ $peerCurrentPage }}">

            <div class="md:col-span-6">
                <label class="block text-[11px] t3 mb-1 font-medium">Select Peer</label>
                <select id="peer_select" name="user_id" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" required></select>
            </div>

            <div class="md:col-span-3">
                <label class="block text-[11px] t3 mb-1 font-medium">Role</label>
                <select name="role" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" required>
                    @foreach (($roles ?? []) as $role)
                        <option value="{{ $role }}">{{ ucwords(str_replace('_', ' ', $role)) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-3">
                <button class="w-full px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring border-0 cursor-pointer">
                    <i class="bi bi-plus-lg admin-icon me-1" aria-hidden="true"></i> Add Peer
                </button>
            </div>
        </form>

        <form id="peerFilterForm" method="GET" action="{{ route('admin.circles.show', $circle) }}" class="d-none"></form>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 170px;">Peers</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 160px;">Email</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 180px;">Role</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 100px;">Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 120px;">Joined At</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right" style="min-width: 100px;">Actions</th>
                        </tr>
                        <tr class="surface-2 border-b bs filter-row">
                            <th class="px-2 py-1">
                                <input
                                    id="peer_name"
                                    type="text"
                                    name="peer_name"
                                    form="peerFilterForm"
                                    value="{{ $peerNameFilter }}"
                                    class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal"
                                    placeholder="Search peer name"
                                >
                            </th>
                            <th class="px-2 py-1">
                                <input
                                    id="peer_email"
                                    type="text"
                                    name="peer_email"
                                    form="peerFilterForm"
                                    value="{{ $peerEmailFilter }}"
                                    class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal"
                                    placeholder="Search email"
                                >
                            </th>
                            <th class="px-2 py-1"></th>
                            <th class="px-2 py-1"></th>
                            <th class="px-2 py-1"></th>
                            <th class="px-2 py-1 text-right">
                                <div class="flex justify-end">
                                    <a href="{{ route('admin.circles.show', $circle) }}" class="px-3 py-1 rounded-md border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline">Clear</a>
                                </div>
                            </th>
                        </tr>
                    </thead>

                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($peerItems as $membership)
                            @php
                                $member = $membership->user ?? null;

                                $memberName = $member ? trim((string) (($member->first_name ?? '') . ' ' . ($member->last_name ?? ''))) : '';
                                if ($memberName === '') {
                                    $memberName = $safeStr(data_get($member, 'display_name') ?: data_get($member, 'name'));
                                }

                                $memberCompany = $safeStr(data_get($member, 'company_name') ?: data_get($member, 'business_name'), 'No Company');
                                $memberCity = $safeStr(data_get($member, 'city.name') ?: data_get($member, 'city_name') ?: data_get($member, 'city'), 'No City');
                            @endphp

                            <tr class="hover:surface-2 transition border-b bs cursor-pointer" onclick="openPeerDrawer('{{ $member ? $member->id : '' }}')">
                                <td class="px-3 py-2.5">
                                    <div class="font-medium t1 text-[12.5px]">{{ $memberName }}</div>
                                    <div class="t3 text-[11px] mt-0.5">{{ $memberCompany }}</div>
                                    <div class="t3 text-[11px]">{{ $memberCity }}</div>
                                </td>

                                <td class="px-3 py-2.5 t1 text-[12.5px] font-mono">{{ $member->email ?? '—' }}</td>

                                <td class="px-3 py-2.5" onclick="event.stopPropagation()">
                                    <form method="POST" action="{{ route('admin.circles.members.update', [$circle, $membership]) }}"
                                          class="flex items-center gap-2" onclick="event.stopPropagation()">
                                        @csrf
                                        @method('PUT')

                                        <input type="hidden" name="peer_name" value="{{ $peerNameFilter }}">
                                        <input type="hidden" name="peer_email" value="{{ $peerEmailFilter }}">
                                        <input type="hidden" name="page" value="{{ $peerCurrentPage }}">

                                        <select name="role" class="px-2 py-1 rounded border bs surface text-xs t1 outline-none focus-ring" onclick="event.stopPropagation()">
                                            @foreach (($roles ?? []) as $role)
                                                <option value="{{ $role }}" @selected(($membership->role ?? null) === $role)>
                                                    {{ ucwords(str_replace('_', ' ', $role)) }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <button class="px-2.5 py-1 rounded-lg border bs text-xs font-medium text-indigo-600 hover:text-indigo-700 surface-2 transition cursor-pointer" onclick="event.stopPropagation()">Update</button>
                                    </form>
                                </td>

                                <td class="px-3 py-2.5">
                                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200 uppercase">
                                        {{ $membership->status ?? 'pending' }}
                                    </span>
                                </td>

                                <td class="px-3 py-2.5 t2 text-xs font-mono">{{ optional($membership->joined_at ?? $membership->created_at ?? null)->format('Y-m-d') ?? '—' }}</td>

                                <td class="px-3 py-2.5 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                                    <form method="POST" action="{{ route('admin.circles.members.destroy', [$circle, $membership]) }}"
                                          onsubmit="return confirm('Remove this peer from the circle?');" onclick="event.stopPropagation()">
                                        @csrf
                                        @method('DELETE')

                                        <input type="hidden" name="peer_name" value="{{ $peerNameFilter }}">
                                        <input type="hidden" name="peer_email" value="{{ $peerEmailFilter }}">
                                        <input type="hidden" name="page" value="{{ $peerCurrentPage }}">

                                        <button class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 border border-rose-200 text-xs font-semibold hover:bg-rose-100 transition cursor-pointer" onclick="event.stopPropagation()">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center t3 py-6 text-xs">No peers assigned yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($peerMembers instanceof \Illuminate\Contracts\Pagination\Paginator || $peerMembers instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                <div class="mt-3 flex justify-between items-center p-2 border-t bs">
                    {{ $peerMembers->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<!-- ============ MEMBER PREVIEW DRAWER ============ -->
<div id="drawer-scrim" onclick="closeDrawer()" class="scrim hidden fixed inset-0 bg-black/50 z-40"></div>
<aside id="drawer" class="drawer drawer-hidden fixed top-0 right-0 h-full w-full sm:w-[420px] bg-white border-l border-slate-200 z-50 flex flex-col shadow-2xl">
  <div class="flex items-center justify-between px-5 h-16 border-b border-slate-200 flex-none bg-white">
    <span class="font-display font-semibold text-[15px] text-slate-900">Member profile</span>
    <button onclick="closeDrawer()" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition border-0 bg-transparent cursor-pointer">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="flex-1 overflow-y-auto p-5 space-y-5 bg-white" id="drawer-body">
    <!-- filled by JS -->
  </div>
  <div class="flex-none p-4 border-t border-slate-200 bg-white flex gap-2.5">
    <a id="view-full-profile-btn" href="#" target="_blank" class="flex-1 py-2.5 rounded-xl bg-[#00bcd4] hover:bg-[#00acc1] text-white text-[12.5px] font-semibold transition shadow-sm text-center border-0 cursor-pointer no-underline">View full profile</a>
    <a id="quick-edit-profile-btn" href="#" target="_blank" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[12.5px] font-semibold transition cursor-pointer no-underline">Quick edit</a>
  </div>
</aside>
@endsection

@push('scripts')
<script>
    const peersData = @json($peersJsonData);

    function initials(n){
      if (!n || typeof n !== 'string') return '?';
      const clean = n.trim();
      if (!clean) return '?';
      const parts = clean.split(/\s+/).filter(Boolean);
      if (parts.length === 0) return '?';
      if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
      return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    function renderAvatar(m, sizeClass = 'w-14 h-14 text-[16px]') {
      const avatarUrl = (m && m.avatar && typeof m.avatar === 'string') ? m.avatar.trim() : '';
      const initialText = initials(m ? m.name : '');
      const bgColor = (m && m.color) ? m.color : '#6366F1';

      if (!avatarUrl) {
        return `<div class="avatar ${sizeClass} rounded-full font-bold flex items-center justify-center text-white flex-none" style="background:${bgColor}">${initialText}</div>`;
      }

      const safeUrl = avatarUrl.replace(/"/g, '&quot;');
      const safeName = String((m && m.name) || '').replace(/"/g, '&quot;');

      return `<img src="${safeUrl}" alt="${safeName}" class="${sizeClass} rounded-full object-cover flex-none" onerror="this.onerror=null; this.outerHTML='<div class=\\'avatar ${sizeClass} rounded-full font-bold flex items-center justify-center text-white flex-none\\' style=\\'background:${bgColor}\\'>${initialText}</div>';" />`;
    }

    function openPeerDrawer(id) {
        const m = peersData.find(x => String(x.id) === String(id));
        if (!m) return;

        document.getElementById('view-full-profile-btn').href = `/admin/users/${m.id}`;
        document.getElementById('quick-edit-profile-btn').href = m.edit_url;

        const statusClass = m.status.toLowerCase() === 'active' || m.status.toLowerCase() === 'approved'
            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' 
            : 'bg-slate-100 text-slate-700 border border-slate-200';

        document.getElementById('drawer-body').innerHTML = `
            <div class="flex items-center gap-3.5 mb-2">
                ${renderAvatar(m, 'w-14 h-14 text-[16px]')}
                <div>
                    <div class="font-display font-semibold text-[17px] text-slate-900">${m.name}</div>
                    <div class="text-[12px] text-slate-400 font-mono mt-0.5">${m.mid}</div>
                </div>
            </div>
            <div class="flex items-center gap-2 mb-6">
                <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full ${statusClass}">
                    ${m.status}
                </span>
                <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-indigo-50 text-indigo-600 border border-indigo-200 uppercase">
                    ${m.role}
                </span>
            </div>
            
            <div class="space-y-5 text-[12.5px] pb-4">
                <div>
                    <div class="font-display font-semibold text-[11px] uppercase tracking-wider text-indigo-600 mb-2 flex items-center gap-1.5">
                        <i class="bi bi-person-circle admin-icon me-1" aria-hidden="true"></i> MEMBER INFO GROUP
                    </div>
                    <div class="space-y-2.5 border border-slate-200/80 rounded-xl p-3.5 bg-[#f8fafc]">
                        <div class="flex justify-between gap-4"><span class="text-slate-400">Email</span><span class="text-slate-800 truncate max-w-[210px] text-right font-medium" title="${m.email}">${m.email}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Mobile</span><span class="text-slate-800 font-mono font-medium">${m.mobile}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Company</span><span class="text-slate-800 font-medium">${m.company}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Industry</span><span class="text-slate-800 font-medium">${m.industry}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Role</span><span class="text-slate-800 font-medium">${m.role}</span></div>
                    </div>
                </div>

                <div>
                    <div class="font-display font-semibold text-[11px] uppercase tracking-wider text-indigo-600 mb-2 flex items-center gap-1.5">
                        <i class="bi bi-globe admin-icon me-1" aria-hidden="true"></i> CIRCLE & REGION DETAILS
                    </div>
                    <div class="space-y-2.5 border border-slate-200/80 rounded-xl p-3.5 bg-[#f8fafc]">
                        <div class="flex justify-between"><span class="text-slate-400">Circle</span><span class="text-slate-800 font-semibold">${m.circle} (${m.circle_type})</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Circle Stage</span><span class="text-slate-800 font-medium">${m.circle_stage}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Meeting Mode</span><span class="text-slate-800 font-medium">${m.meeting_mode}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Meeting Frequency</span><span class="text-slate-800 font-medium">${m.meeting_freq}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">City</span><span class="text-slate-800 font-medium">${m.city}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Country</span><span class="text-slate-800 font-medium">${m.country}</span></div>
                    </div>
                </div>

                <div>
                    <div class="font-display font-semibold text-[11px] uppercase tracking-wider text-amber-500 mb-2 flex items-center gap-1.5">
                        <span>⭐</span> MEMBERSHIP & RENEWAL
                    </div>
                    <div class="space-y-2.5 border border-slate-200/80 rounded-xl p-3.5 bg-[#f8fafc]">
                        <div class="flex justify-between"><span class="text-slate-400">Payment Status</span><span class="text-emerald-600 font-semibold">${m.payment_status}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Subscription</span><span class="text-slate-800 font-medium">${m.subscription_status} (${m.billing_term})</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Joined Circle</span><span class="text-slate-800 font-medium">${m.joined}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Membership Starts</span><span class="text-slate-800 font-medium">${m.starts_at}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Membership Ends</span><span class="text-slate-800 font-medium">${m.ends_at}</span></div>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('drawer').classList.remove('drawer-hidden');
        document.getElementById('drawer-scrim').classList.remove('hidden');
    }

    function closeDrawer() {
        document.getElementById('drawer').classList.add('drawer-hidden');
        document.getElementById('drawer-scrim').classList.add('hidden');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const CIRCLE_ID = @json($circle->id ?? null);
        const jQuery = window.jQuery || window.$;

        if (jQuery) {
            const pendingSuccessMsg = sessionStorage.getItem('circle_member_success');
            if (pendingSuccessMsg) {
                const successAlertHtml = `
                    <div class="alert alert-success alert-dismissible fade show text-xs mb-3" role="alert">
                        ${pendingSuccessMsg}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                jQuery('#js-alert-container').html(successAlertHtml);
                sessionStorage.removeItem('circle_member_success');
            }
        }

        if (jQuery && jQuery('#peer_select').length && jQuery.fn.select2) {
            jQuery('#peer_select').select2({
                width: '100%',
                placeholder: 'Select peer',
                allowClear: true,
                ajax: {
                    url: `/admin/circles/${CIRCLE_ID}/peer-options`,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            page: params.page || 1
                        };
                    },
                    processResults: function (data, params) {
                        params.page = params.page || 1;
                        return {
                            results: data.results || [],
                            pagination: {
                                more: data.pagination && data.pagination.more
                            }
                        };
                    },
                    cache: true
                },
                language: {
                    noResults: function () {
                        return "No peers found.";
                    },
                    searching: function () {
                        return "Searching peers...";
                    }
                }
            });
        }

        const addPeerForm = jQuery('#add-peer-form');
        if (addPeerForm.length) {
            addPeerForm.on('submit', function (e) {
                e.preventDefault();
                
                addPeerForm.find('.alert-danger-custom').remove();
                jQuery('.alert-danger').remove();
                
                const url = addPeerForm.attr('action');
                const data = addPeerForm.serialize();
                
                jQuery.ajax({
                    url: url,
                    method: 'POST',
                    data: data,
                    dataType: 'json',
                    success: function (res) {
                        sessionStorage.setItem('circle_member_success', res.message || 'Member added to the circle.');
                        window.location.reload();
                    },
                    error: function (xhr) {
                        let msg = 'This peer is already a member of this circle.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        
                        const alertHtml = '<div class="alert alert-danger alert-danger-custom mt-2 w-100 text-xs">' + escapeHtml(msg) + '</div>';
                        addPeerForm.append(alertHtml);
                    }
                });
            });

            function escapeHtml(str) {
                if (!str) return '';
                return str
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }
        }
    });
</script>
@endpush
