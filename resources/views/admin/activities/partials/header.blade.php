@php
    $displayName = $displayName ?? function (?string $display, ?string $first, ?string $last): string {
        if ($display) {
            return $display;
        }
        $name = trim(($first ?? '') . ' ' . ($last ?? ''));
        return $name !== '' ? $name : '—';
    };

    $navItems = [
        [
            'label' => 'Summary',
            'route' => 'admin.activities.index',
            'active_check' => ['admin.activities.index'],
            'icon' => 'bi-grid-1x2-fill'
        ],
        [
            'label' => 'Testimonials',
            'route' => 'admin.activities.testimonials.index',
            'active_check' => ['admin.activities.testimonials*'],
            'icon' => 'bi-chat-quote-fill'
        ],
        [
            'label' => 'Requirements',
            'route' => 'admin.activities.requirements.index',
            'active_check' => ['admin.activities.requirements*'],
            'icon' => 'bi-file-earmark-text-fill'
        ],
        [
            'label' => 'Referrals',
            'route' => 'admin.activities.referrals.index',
            'active_check' => ['admin.activities.referrals*'],
            'icon' => 'bi-person-plus-fill'
        ],
        [
            'label' => 'P2P Meetings',
            'route' => 'admin.activities.p2p-meetings.index',
            'active_check' => ['admin.activities.p2p-meetings*'],
            'icon' => 'bi-people-fill'
        ],
        [
            'label' => 'Business Deals',
            'route' => 'admin.activities.business-deals.index',
            'active_check' => ['admin.activities.business-deals*'],
            'icon' => 'bi-briefcase-fill'
        ],
        [
            'label' => 'Leadership Requests',
            'route' => 'admin.activities.become-a-leader.index',
            'active_check' => ['admin.activities.become-a-leader*'],
            'icon' => 'bi-award-fill'
        ],
        [
            'label' => 'Recommended Peers',
            'route' => 'admin.activities.recommend-peer.index',
            'active_check' => ['admin.activities.recommend-peer*'],
            'icon' => 'bi-hand-thumbs-up-fill'
        ],
        [
            'label' => 'Collaborations',
            'route' => 'admin.collaborations.index',
            'active_check' => ['admin.collaborations*'],
            'icon' => 'bi-link-45deg'
        ],
        [
            'label' => 'Registered Visitor',
            'route' => 'admin.activities.register-visitor.index',
            'active_check' => ['admin.activities.register-visitor*'],
            'icon' => 'bi-person-vcard-fill'
        ]
    ];
@endphp

<div class="mb-4">
    <!-- Breadcrumbs / Top Title -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1 fw-bold text-dark" style="font-family: 'Outfit', sans-serif;">Activities Hub</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted">Admin</a></li>
                    <li class="breadcrumb-item text-muted">Activities</li>
                    <li class="breadcrumb-item active text-primary fw-medium" aria-current="page">{{ $title ?? 'Summary' }}</li>
                </ol>
            </nav>
        </div>
        @if(isset($actionButton))
            {!! $actionButton !!}
        @endif
    </div>

    <!-- Quick Navigation Pills -->
    <div class="activities-nav-container mb-4">
        @foreach($navItems as $item)
            <a href="{{ route($item['route']) }}" class="activities-nav-pill {{ request()->routeIs(...(array)$item['active_check']) ? 'active' : '' }}">
                <i class="bi {{ $item['icon'] }}"></i>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</div>
