@php
    $adminUser = Auth::guard('admin')->user();
    $adminUser?->loadMissing('roles:id,key');
    $isSuper = \App\Support\AdminAccess::isSuper($adminUser);
    $isCircleScoped = \App\Support\AdminAccess::isCircleScoped($adminUser);
    $isDed = \App\Support\AdminAccess::isDed($adminUser);
    $isCircleCommittee = \App\Support\AdminAccess::isCircleCommittee($adminUser);
    $isGlobalAdmin = \App\Support\AdminAccess::isGlobalAdmin($adminUser);
    $isIndustryDirector = $adminUser?->roles?->pluck('key')->contains('industry_director') ?? false;

    if (request()->is('admin/industry-director*')) {
        $isGlobalAdmin = false;
        $isSuper = false;
        $isDed = false;
        $isCircleScoped = false;
        $isCircleCommittee = false;
        $isIndustryDirector = true;
    }


    $dashboardItem = $isIndustryDirector
        ? ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'route' => 'admin.industry-director.dashboard']
        : (($isCircleScoped || $isDed)
            ? ($isDed ? ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'route' => 'admin.ded.dashboard'] : ['icon' => 'bi-speedometer2', 'label' => 'Circle Dashboard', 'route' => 'admin.circle-member.dashboard'])
            : ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'route' => 'admin.dashboard']);


    $navItems = $isIndustryDirector
        ? [
            ['icon' => 'bi-people', 'label' => 'Peers', 'route' => 'admin.users.index'],
            ['icon' => 'bi-coin', 'label' => 'Coins', 'route' => 'admin.coins.index'],
            ['icon' => 'bi-heart-pulse', 'label' => 'Life Impact', 'route' => 'admin.life-impact.index'],
        ]
        : (($isCircleScoped || $isDed)
            ? [
                ['icon' => 'bi-people', 'label' => 'Peers', 'route' => 'admin.users.index'],
                ...($isDed ? [
                    ['icon' => 'bi-diagram-3', 'label' => 'Circles', 'route' => 'admin.circles.index'],
                    ['icon' => 'bi-diagram-2', 'label' => 'Industries', 'route' => 'admin.ded.dashboard.industries']
                ] : []),
                ['icon' => 'bi-coin', 'label' => 'Coins', 'route' => 'admin.coins.index'],
                ['icon' => 'bi-heart-pulse', 'label' => 'Life Impact', 'route' => 'admin.life-impact.index'],
                ...(! $isDed && ! $isCircleCommittee ? [
                    ['icon' => 'bi-envelope-paper', 'label' => 'Email Logs', 'route' => 'admin.email-logs.index'],
                    ['icon' => 'bi-ticket-perforated', 'label' => 'Support Tickets', 'route' => 'admin.support-tickets.index']
                ] : []),
                ...($isGlobalAdmin ? [
                    ['icon' => 'bi-calendar-check', 'label' => 'Events Management', 'route' => 'admin.events.index', 'active_routes' => ['admin.events.*', 'admin.event-joining-requests.*']],
                    ['icon' => 'bi-images', 'label' => 'Event Gallery', 'route' => 'admin.event-gallery.index'],
                    ['icon' => 'bi-tags', 'label' => 'Circle Categories', 'route' => 'admin.categories.index'],
                    ['icon' => 'bi-megaphone', 'label' => 'Ads', 'route' => 'admin.ads.index', 'active_routes' => ['admin.ads.*']],
                    ['icon' => 'bi-lightning-charge', 'label' => 'Impact Option', 'route' => 'admin.impacts.index', 'active_routes' => ['admin.impacts.index', 'admin.impacts.store', 'admin.impacts.show', 'admin.impacts.posts']],
                    ['icon' => 'bi-diagram-3', 'label' => 'Role Hierarchy', 'route' => 'admin.rbac.hierarchy', 'active_routes' => ['admin.rbac.*']],
                ] : []),
            ]
            : [
                ['icon' => 'bi-people', 'label' => 'Peers', 'route' => 'admin.users.index'],
                ['icon' => 'bi-people', 'label' => 'Member Introducers', 'route' => 'admin.member-introducers.index'],
                ['icon' => 'bi-person-lines-fill', 'label' => 'Unity Contacts', 'route' => 'admin.contacts.index', 'active_routes' => ['admin.contacts.*']],
                ['icon' => 'bi-person-badge', 'label' => 'Leadership', 'route' => 'admin.execution.leadership'],
                ['icon' => 'bi-diagram-2', 'label' => 'Industries', 'route' => 'admin.execution.industries'],
                ...($isGlobalAdmin ? [['icon' => 'bi-clock-history', 'label' => 'Login History', 'route' => 'admin.login-history.index']] : []),
                ['icon' => 'bi-diagram-3', 'label' => 'Circles', 'route' => 'admin.circles.index'],
                ['icon' => 'bi-megaphone', 'label' => 'Circulars', 'route' => 'admin.circulars.index'],
                ['icon' => 'bi-coin', 'label' => 'Coins', 'route' => 'admin.coins.index'],
                ['icon' => 'bi-heart-pulse', 'label' => 'Life Impact', 'route' => 'admin.life-impact.index'],
                ['icon' => 'bi-bell', 'label' => 'Notifications & Email', 'route' => 'admin.campaigns.index', 'active_routes' => ['admin.campaigns.*', 'admin.campaign-pamphlets.*', 'admin.campaign-email-templates.*', 'admin.email-logs.*', 'admin.execution.communications', 'admin.daily-notifications.*']],
                ...(! $isCircleCommittee ? [
                    ['icon' => 'bi-envelope-paper', 'label' => 'Email Logs', 'route' => 'admin.email-logs.index'],
                    ['icon' => 'bi-ticket-perforated', 'label' => 'Support Tickets', 'route' => 'admin.support-tickets.index']
                ] : []),
                ...($isGlobalAdmin ? [
                    ['icon' => 'bi-calendar-check', 'label' => 'Events Management', 'route' => 'admin.events.index', 'active_routes' => ['admin.events.*', 'admin.event-joining-requests.*']],
                    ['icon' => 'bi-images', 'label' => 'Event Gallery', 'route' => 'admin.event-gallery.index'],
                    ['icon' => 'bi-tags', 'label' => 'Circle Categories', 'route' => 'admin.categories.index'],
                    ['icon' => 'bi-megaphone', 'label' => 'Ads', 'route' => 'admin.ads.index', 'active_routes' => ['admin.ads.*']],
                    ['icon' => 'bi-lightning-charge', 'label' => 'Impact Option', 'route' => 'admin.impacts.index', 'active_routes' => ['admin.impacts.index', 'admin.impacts.store', 'admin.impacts.show', 'admin.impacts.posts']],
                    ['icon' => 'bi-diagram-3', 'label' => 'Role Hierarchy', 'route' => 'admin.rbac.hierarchy', 'active_routes' => ['admin.rbac.*']],
                ] : []),
            ]);

    $fullActivityMenu = [
        ['label' => 'Summary', 'route' => 'admin.activities.index'],
        ['label' => 'Testimonials', 'route' => 'admin.activities.testimonials.index'],
        ['label' => 'Requirements', 'route' => 'admin.activities.requirements.index'],
        ['label' => 'Referrals', 'route' => 'admin.activities.referrals.index'],
        ['label' => 'P2P Meetings', 'route' => 'admin.activities.p2p-meetings.index'],
        ['label' => 'Business Deals', 'route' => 'admin.activities.business-deals.index'],
        ['label' => 'Leadership Requests', 'route' => 'admin.activities.become-a-leader.index'],
        ['label' => 'Recommended Peers', 'route' => 'admin.activities.recommend-peer.index'],
        ['label' => 'Collaborations', 'route' => 'admin.collaborations.index'],
        ['label' => 'Registered Visitor', 'route' => 'admin.activities.register-visitor.index'],
    ];

    $activityMenu = ($isIndustryDirector || $isSuper || $isCircleScoped || $isDed) ? $fullActivityMenu : [];

    if ($isDed) {
        $activityMenu = array_values(array_filter($activityMenu, function ($item) {
            return !in_array($item['label'], ['Registered Visitor', 'Recommended Peers', 'Collaborations'], true);
        }));
    }

    $activityActive = request()->routeIs('admin.activities.*') || request()->routeIs('admin.collaborations.*');
    $referralReportItem = (! $isCircleCommittee && ($isSuper || $isCircleScoped || $isDed || $isIndustryDirector))
        ? ['icon' => 'bi-person-lines-fill', 'label' => 'Referral Report', 'route' => 'admin.referral-report.index', 'active_routes' => ['admin.referral-report.*']]
        : null;
    $activityExpanded = $activityActive;

    $postsMenu = ($isGlobalAdmin) ? [
        ['label' => 'All Posts', 'route' => 'admin.posts.index'],
        ['label' => 'Post Reports', 'route' => 'admin.post-reports.index'],
    ] : [];
    $postsActive = request()->routeIs('admin.posts.*') || request()->routeIs('admin.post-reports.*');

    $leadsMenu = [
        ['label' => 'Entrepreneur Certification', 'route' => 'admin.leads.entrepreneur-certification.index'],
        ['label' => 'Leadership Certification', 'route' => 'admin.leads.leadership-certification.index'],
        ['label' => 'Partner With Us', 'route' => 'admin.leads.partner-with-us.index'],
        ['label' => 'Become Speaker', 'route' => 'admin.leads.become-speaker.index'],
        ['label' => 'Become Mentor', 'route' => 'admin.leads.become-mentor.index'],
    ];

    $pendingRequestsMenu = $isIndustryDirector
        ? [
            ['label' => 'Circle Joining Requests', 'route' => 'admin.circle-joining-requests.index'],
            ['label' => 'Account Deletion Requests', 'route' => 'admin.account-deletion.index'],
            ['label' => 'Account Deletion Emails', 'route' => 'admin.account-deletion.emails'],
        ]
        : [
            ['label' => 'Visitor Registrations', 'route' => 'admin.visitor-registrations.index'],
            ['label' => 'Event Joining Requests', 'route' => 'admin.event-joining-requests.index'],
            ['label' => 'Coin Claims', 'route' => 'admin.coin-claims.index'],
            ['label' => 'Circle Joining Requests', 'route' => 'admin.circle-joining-requests.index'],
            ['label' => 'Certifications', 'route' => 'admin.certifications.index'],
            ['label' => 'Story Submissions', 'route' => 'admin.stories.index'],
            ['label' => 'Pending Impacts', 'route' => 'admin.impacts.pending'],
            ['label' => 'Account Deletion Requests', 'route' => 'admin.account-deletion.index'],
            ['label' => 'Account Deletion Emails', 'route' => 'admin.account-deletion.emails'],
            ['label' => 'Introduction Requests', 'route' => 'admin.introduction-requests.index'],
        ];

    if ($isCircleCommittee) {
        $pendingRequestsMenu = array_values(array_filter(
            $pendingRequestsMenu,
            fn ($item) => ! in_array(($item['label'] ?? null), ['Circle Joining Requests', 'Certifications'], true)
        ));
    }

    if ($isDed) {
        $pendingRequestsMenu = array_values(array_filter(
            $pendingRequestsMenu,
            fn ($item) => ! in_array(($item['label'] ?? null), ['Account Deletion Requests', 'Account Deletion Emails'], true)
        ));
    }

    $leadsActive = request()->routeIs('admin.leads.*');
    $pendingRequestsActive =
        request()->routeIs('admin.visitor-registrations.*') ||
        request()->routeIs('admin.coin-claims.*') ||
        request()->routeIs('admin.event-joining-requests.*') ||
        request()->routeIs('admin.circle-joining-requests.*') ||
        request()->routeIs('admin.certifications.*') ||
        request()->routeIs('admin.stories.*') ||
        request()->routeIs('admin.impacts.pending') ||
        request()->routeIs('admin.account-deletion.*') ||
        request()->routeIs('admin.introduction-requests.*');

    $leadsMenu = ($isIndustryDirector || $isCircleCommittee) ? [] : $leadsMenu;

    $campaignsMenu = [
        ['label' => 'Campaign Dashboard', 'route' => 'admin.campaigns.index', 'active_routes' => ['admin.campaigns.index', 'admin.campaigns.show', 'admin.campaigns.edit']],
        ['label' => 'Create Campaign', 'route' => 'admin.campaigns.create', 'active_routes' => ['admin.campaigns.create']],
        ['label' => 'Campaign Email Templates', 'route' => 'admin.campaign-email-templates.index', 'active_routes' => ['admin.campaign-email-templates.*']],
        ['label' => 'Pamphlets', 'route' => 'admin.campaign-pamphlets.index', 'active_routes' => ['admin.campaign-pamphlets.*']],
        ['label' => 'Email Logs', 'route' => 'admin.email-logs.index', 'active_routes' => ['admin.email-logs.*']],
        ['label' => 'Daily Notification Reminder', 'route' => 'admin.daily-notifications.index', 'active_routes' => ['admin.daily-notifications.*']],
    ];
    $campaignsActive = request()->routeIs('admin.campaigns.*')
        || request()->routeIs('admin.campaign-pamphlets.*')
        || request()->routeIs('admin.campaign-email-templates.*')
        || request()->routeIs('admin.email-logs.*')
        || request()->routeIs('admin.execution.communications')
        || request()->routeIs('admin.daily-notifications.*');
    $notificationsMenu = [
        ['label' => 'Overview', 'route' => 'admin.notifications.dashboard', 'icon' => 'bi-speedometer2', 'active_routes' => ['admin.notifications.dashboard']],
        ['label' => 'Campaigns', 'route' => 'admin.notifications.campaigns', 'icon' => 'bi-megaphone', 'active_routes' => ['admin.notifications.campaigns', 'admin.notifications.campaigns.*']],
        ['label' => 'Send Notification', 'route' => 'admin.notifications.send-test', 'icon' => 'bi-send', 'active_routes' => ['admin.notifications.send-test', 'admin.notifications.send-test.store']],
        ['label' => 'Delivery Logs', 'route' => 'admin.notifications.logs', 'icon' => 'bi-clock-history', 'active_routes' => ['admin.notifications.logs']],
        ['label' => 'Push Tokens', 'route' => 'admin.notifications.push-tokens', 'icon' => 'bi-phone', 'active_routes' => ['admin.notifications.push-tokens', 'admin.notifications.push-tokens.*']],
        ['label' => 'User Inbox', 'route' => 'admin.notifications.user-notifications', 'icon' => 'bi-inbox', 'active_routes' => ['admin.notifications.user-notifications', 'admin.notifications.mark-read', 'admin.notifications.destroy', 'admin.notifications.clear-user']],
    ];
    $notificationsActive = request()->routeIs('admin.notifications.*') || request()->is('admin/notifications*');
    $eventsManagementMenu = [
        ['label' => 'Events', 'route' => 'admin.events.index'],
        ['label' => 'Event Scan Credentials', 'route' => 'admin.event-scan-credentials.index'],
        ['label' => 'Event Gallery', 'route' => 'admin.event-gallery.index'],
    ];

    $eventsManagementActive = request()->routeIs('admin.events.*');
    $bottomNavItems = array_values(array_filter($navItems, fn ($item) => ($item['label'] ?? null) === 'Email Logs'));
    $bottomNavItems = (! $isCircleScoped && ! $isDed && ! $isIndustryDirector) ? [] : $bottomNavItems;
    $navItems = array_values(array_filter($navItems, fn ($item) => ! in_array(($item['label'] ?? null), ['Events Management', 'Email Logs', 'Event Gallery'], true)));
    $eventsManagementActive = request()->routeIs('admin.events.*') || request()->routeIs('admin.event-scan-credentials.*') || request()->routeIs('admin.event-gallery.*');
    $navItems = array_values(array_filter($navItems, fn ($item) => ! in_array(($item['label'] ?? null), ['Events Management', 'Event Gallery'], true)));
    $eventsManagementActive = request()->routeIs('admin.events.*') || request()->routeIs('admin.event-joining-requests.*');
    $bottomNavItems = array_map(function ($item) {
        if ($item['label'] === 'Email Logs') {
            $item['active_routes'] = ['admin.email-logs.*'];
        } elseif ($item['label'] === 'Support Tickets') {
            $item['active_routes'] = ['admin.support-tickets.*'];
        }
        return $item;
    }, array_values(array_filter($navItems, fn ($item) => in_array(($item['label'] ?? null), ['Email Logs', 'Support Tickets'], true))));
    $navItems = array_values(array_filter($navItems, fn ($item) => ! in_array(($item['label'] ?? null), ['Events Management', 'Email Logs', 'Support Tickets'], true)));
    $eventsManagementActive = request()->routeIs('admin.events.*') || request()->routeIs('admin.event-joining-requests.*') || request()->routeIs('admin.event-scan-credentials.*');
    $campaignsMenu = $isIndustryDirector ? [] : $campaignsMenu;
    
    $brandPartnersActive = request()->routeIs('admin.brand-partners.*');
    $brandPartnersMenu = [
        ['label' => 'Dashboard', 'route' => 'admin.brand-partners.dashboard'],
        ['label' => 'All Partners', 'route' => 'admin.brand-partners.index'],
        ['label' => 'Categories', 'route' => 'admin.brand-partners.categories.index'],
        ['label' => 'Offers', 'route' => 'admin.brand-partners.offers'],
        ['label' => 'Analytics', 'route' => 'admin.brand-partners.analytics'],
        ['label' => 'Settings', 'route' => 'admin.brand-partners.settings'],
    ];
    $hasBrandPartnersRole = $adminUser?->roles?->pluck('key')->intersect(['global_admin', 'marketing_team', 'analytics_team', 'content_team', 'read_only'])->isNotEmpty() ?? false;

    $dedLeadershipMenu = [
        ['label' => 'Industry Directors', 'route' => 'admin.ded.dashboard.leadership', 'params' => ['role' => 'industry_director']],
        ['label' => 'Circle Founders', 'route' => 'admin.ded.dashboard.leadership', 'params' => ['role' => 'founder']],
        ['label' => 'Circle Directors', 'route' => 'admin.ded.dashboard.leadership', 'params' => ['role' => 'director']],
        ['label' => 'Chairs', 'route' => 'admin.ded.dashboard.leadership', 'params' => ['role' => 'chair']],
        ['label' => 'Vice Chairs', 'route' => 'admin.ded.dashboard.leadership', 'params' => ['role' => 'vice_chair']],
        ['label' => 'Secretaries', 'route' => 'admin.ded.dashboard.leadership', 'params' => ['role' => 'secretary']],
        ['label' => 'Members', 'route' => 'admin.ded.dashboard.leadership', 'params' => ['role' => 'member']],
    ];
    $dedLeadershipActive = request()->routeIs('admin.ded.dashboard.leadership');

    $dedAnalyticsMenu = [
        ['label' => 'Active Members', 'route' => 'admin.ded.dashboard.health.active-members'],
        ['label' => 'Leadership Spots', 'route' => 'admin.ded.dashboard.health.leadership-spots'],
        ['label' => 'Membership Conversion', 'route' => 'admin.ded.dashboard.health.membership-conversion'],
        ['label' => 'Referral Activity', 'route' => 'admin.ded.dashboard.health.referral-activity'],
    ];
    $dedAnalyticsActive = request()->routeIs('admin.ded.dashboard.health.*');

    // Filter allowed sidebar sections
    if ($dashboardItem && !\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Dashboard')) {
        $dashboardItem = null;
    }
    if ($activityMenu && !\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Activities')) {
        $activityMenu = [];
    }
    if ($referralReportItem && !\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Referral Report')) {
        $referralReportItem = null;
    }
    if ($postsMenu && !\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Posts & Timeline')) {
        $postsMenu = [];
    }
    if ($leadsMenu && !\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Leads')) {
        $leadsMenu = [];
    }

    $navItems = array_values(array_filter($navItems, function ($item) use ($adminUser) {
        $label = $item['label'] ?? null;
        if ($label && !\App\Support\AdminAccess::isSectionAllowed($adminUser, $label)) {
            return false;
        }
        return true;
    }));

    $bottomNavItems = array_values(array_filter($bottomNavItems, function ($item) use ($adminUser) {
        $label = $item['label'] ?? null;
        if ($label && !\App\Support\AdminAccess::isSectionAllowed($adminUser, $label)) {
            return false;
        }
        return true;
    }));
@endphp

<aside class="admin-sidebar d-flex flex-column">
    {{-- Brand Logo --}}
    <div class="text-center mb-2">
        <a href="{{ route($isIndustryDirector ? 'admin.industry-director.dashboard' : 'admin.users.index') }}" class="d-inline-block">
            <img
                src="{{ asset('images/peersglobal-logo.png') }}"
                alt="PeersGlobal"
                style="max-height:68px; width:auto;"
                class="d-block mx-auto my-3"
                loading="lazy"
            />
        </a>
    </div>

    <nav class="flex-grow-1">
        <ul class="nav flex-column">
            @if ($dashboardItem)
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs($dashboardItem['route']) ? 'active' : '' }}" href="{{ route($dashboardItem['route']) }}">
                        <i class="bi {{ $dashboardItem['icon'] }} me-2"></i>{{ $dashboardItem['label'] }}
                    </a>
                </li>
            @endif

            @if ($activityMenu)
                <li class="nav-item menu-parent {{ $activityExpanded ? 'open' : '' }}">
                    <a class="nav-link d-flex justify-content-between align-items-center {{ $activityExpanded ? 'active' : '' }}" href="#activitiesSubmenu" role="button" aria-expanded="{{ $activityExpanded ? 'true' : 'false' }}" aria-controls="activitiesSubmenu">
                        <span><i class="bi bi-activity me-2"></i>Activities</span>
                        <i class="bi bi-chevron-right menu-arrow"></i>
                    </a>
                    <div class="collapse {{ $activityExpanded ? 'show' : '' }}" id="activitiesSubmenu">
                        <ul class="nav flex-column ms-3">
                            @foreach ($activityMenu as $item)
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs($item['route']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                                        {{ $item['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </li>
            @endif

            @if ($referralReportItem)
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs(...$referralReportItem['active_routes']) ? 'active' : '' }}" href="{{ route($referralReportItem['route']) }}">
                        <i class="bi {{ $referralReportItem['icon'] }} me-2"></i>{{ $referralReportItem['label'] }}
                    </a>
                </li>
            @endif

            @if ($postsMenu)
                <li class="nav-item menu-parent {{ $postsActive ? 'open' : '' }}">
                    <a class="nav-link d-flex justify-content-between align-items-center {{ $postsActive ? 'active' : '' }}" href="#postsSubmenu" role="button" aria-expanded="{{ $postsActive ? 'true' : 'false' }}" aria-controls="postsSubmenu">
                        <span><i class="bi bi-chat-dots me-2"></i>Posts &amp; Timeline</span>
                        <i class="bi bi-chevron-right menu-arrow"></i>
                    </a>
                    <div class="collapse {{ $postsActive ? 'show' : '' }}" id="postsSubmenu">
                        <ul class="nav flex-column ms-3">
                            @foreach ($postsMenu as $item)
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs($item['route']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                                        {{ $item['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </li>
            @endif

            @if (\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Pending Requests'))
            <li class="nav-item menu-parent {{ $pendingRequestsActive ? 'open' : '' }}">
                <a class="nav-link d-flex justify-content-between align-items-center {{ $pendingRequestsActive ? 'active' : '' }}" href="#pendingRequestsSubmenu" role="button" aria-expanded="{{ $pendingRequestsActive ? 'true' : 'false' }}" aria-controls="pendingRequestsSubmenu">
                    <span><i class="bi bi-hourglass-split me-2"></i>Pending Requests</span>
                    <i class="bi bi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ $pendingRequestsActive ? 'show' : '' }}" id="pendingRequestsSubmenu">
                    <ul class="nav flex-column ms-3">
                        @foreach ($pendingRequestsMenu as $item)
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs($item['route']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </li>
            @endif

            @if ($isDed)
                @if (\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Leadership'))
                <li class="nav-item menu-parent {{ $dedLeadershipActive ? 'open' : '' }}">
                    <a class="nav-link d-flex justify-content-between align-items-center {{ $dedLeadershipActive ? 'active' : '' }}" href="#dedLeadershipSubmenu" role="button" aria-expanded="{{ $dedLeadershipActive ? 'true' : 'false' }}" aria-controls="dedLeadershipSubmenu">
                        <span><i class="bi bi-person-badge me-2"></i>Leadership</span>
                        <i class="bi bi-chevron-right menu-arrow"></i>
                    </a>
                    <div class="collapse {{ $dedLeadershipActive ? 'show' : '' }}" id="dedLeadershipSubmenu">
                        <ul class="nav flex-column ms-3">
                            @foreach ($dedLeadershipMenu as $item)
                                <li class="nav-item">
                                    <a class="nav-link {{ (request()->routeIs($item['route']) && request()->route('role') === $item['params']['role']) ? 'active' : '' }}" href="{{ route($item['route'], $item['params']) }}">
                                        {{ $item['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </li>
                @endif

                <li class="nav-item menu-parent {{ $dedAnalyticsActive ? 'open' : '' }}">
                    <a class="nav-link d-flex justify-content-between align-items-center {{ $dedAnalyticsActive ? 'active' : '' }}" href="#dedAnalyticsSubmenu" role="button" aria-expanded="{{ $dedAnalyticsActive ? 'true' : 'false' }}" aria-controls="dedAnalyticsSubmenu">
                        <span><i class="bi bi-graph-up-arrow me-2"></i>Analytics</span>
                        <i class="bi bi-chevron-right menu-arrow"></i>
                    </a>
                    <div class="collapse {{ $dedAnalyticsActive ? 'show' : '' }}" id="dedAnalyticsSubmenu">
                        <ul class="nav flex-column ms-3">
                            @foreach ($dedAnalyticsMenu as $item)
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs($item['route']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                                        {{ $item['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </li>
            @endif


            @foreach ($bottomNavItems as $item)
                <li class="nav-item">
                    <a class="nav-link {{ (isset($item['active_routes']) ? request()->routeIs(...$item['active_routes']) : request()->routeIs($item['route'])) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                        <i class="bi {{ $item['icon'] }} me-2"></i>{{ $item['label'] }}
                    </a>
                </li>
            @endforeach

            @if (($isGlobalAdmin || $isIndustryDirector) && \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Events Management'))
                <li class="nav-item menu-parent {{ $eventsManagementActive ? 'open' : '' }}">
                    <a class="nav-link d-flex justify-content-between align-items-center {{ $eventsManagementActive ? 'active' : '' }}" href="#eventsManagementSubmenu" role="button" aria-expanded="{{ $eventsManagementActive ? 'true' : 'false' }}" aria-controls="eventsManagementSubmenu">
                        <span><i class="bi bi-calendar-check me-2"></i>Events Management</span>
                        <i class="bi bi-chevron-right menu-arrow"></i>
                    </a>
                    <div class="collapse {{ $eventsManagementActive ? 'show' : '' }}" id="eventsManagementSubmenu">
                        <ul class="nav flex-column ms-3">
                            @foreach ($eventsManagementMenu as $eventItem)
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs($eventItem['route']) ? 'active' : '' }}" href="{{ route($eventItem['route']) }}">{{ $eventItem['label'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </li>
            @endif

            @if ($hasBrandPartnersRole && \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Brand Partners'))
                <li class="nav-item menu-parent {{ $brandPartnersActive ? 'open' : '' }}">
                    <a class="nav-link d-flex justify-content-between align-items-center {{ $brandPartnersActive ? 'active' : '' }}" href="#brandPartnersSubmenu" role="button" aria-expanded="{{ $brandPartnersActive ? 'true' : 'false' }}" aria-controls="brandPartnersSubmenu">
                        <span><i class="bi bi-briefcase me-2"></i>Brand Partners</span>
                        <i class="bi bi-chevron-right menu-arrow"></i>
                    </a>
                    <div class="collapse {{ $brandPartnersActive ? 'show' : '' }}" id="brandPartnersSubmenu">
                        <ul class="nav flex-column ms-3">
                            @foreach ($brandPartnersMenu as $item)
                                @if (Route::has($item['route']))
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs($item['route']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                                            {{ $item['label'] }}
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                </li>
            @endif

            @foreach ($navItems as $item)
                @if ($item['label'] === 'Notifications & Email')
                    @if (Route::has($item['route']))
                        <li class="nav-item menu-parent {{ $campaignsActive ? 'open' : '' }}">
                            <a class="nav-link d-flex justify-content-between align-items-center {{ $campaignsActive ? 'active' : '' }}" href="#campaignsSubmenu" role="button" aria-expanded="{{ $campaignsActive ? 'true' : 'false' }}" aria-controls="campaignsSubmenu">
                                <span><i class="bi {{ $item['icon'] }} me-2"></i>{{ $item['label'] }}</span>
                                <i class="bi bi-chevron-right menu-arrow"></i>
                            </a>
                            <div class="collapse {{ $campaignsActive ? 'show' : '' }}" id="campaignsSubmenu">
                                <ul class="nav flex-column ms-3">
                                    @foreach ($campaignsMenu as $campaignItem)
                                        @if (Route::has($campaignItem['route']))
                                            <li class="nav-item">
                                                <a class="nav-link {{ request()->routeIs($campaignItem['route']) ? 'active' : '' }}" href="{{ route($campaignItem['route']) }}">{{ $campaignItem['label'] }}</a>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        </li>
                    @endif
                @else
                    <li class="nav-item">
                        @if ($item['route'] === '#')
                            <span class="nav-link disabled">
                                <i class="bi {{ $item['icon'] }} me-2"></i>{{ $item['label'] }}
                            </span>
                        @else
                            @if (Route::has($item['route']))
                                <a class="nav-link {{ (isset($item['active_routes']) ? request()->routeIs(...$item['active_routes']) : request()->routeIs($item['route'])) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                                    <i class="bi {{ $item['icon'] }} me-2"></i>{{ $item['label'] }}
                                </a>
                            @endif
                        @endif
                    </li>
                @endif
            @endforeach



            @if ($isGlobalAdmin)
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.app-config.*') ? 'active' : '' }}" href="{{ route('admin.app-config.index') }}">
                        <i class="bi bi-sliders me-2"></i>App Configuration
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.birthday-creative.*') ? 'active' : '' }}" href="{{ route('admin.birthday-creative.index') }}">
                        <i class="bi bi-gift me-2"></i>Birthday Creative
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.anniversary-creatives.*') ? 'active' : '' }}" href="{{ route('admin.anniversary-creatives.index') }}">
                        <i class="bi bi-images me-2"></i>Anniversary Creative
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.tutorials.*') ? 'active' : '' }}" href="{{ route('admin.tutorials.index') }}">
                        <i class="bi bi-play-btn me-2"></i>Tutorials
                    </a>
                </li>
            @endif


            @if (! $isDed && ! $isCircleCommittee && $leadsMenu !== [] && \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Leads'))
            <li class="nav-item menu-parent {{ $leadsActive ? 'open' : '' }}">
                <a class="nav-link d-flex justify-content-between align-items-center {{ $leadsActive ? 'active' : '' }}" href="#leadsSubmenu" role="button" aria-expanded="{{ $leadsActive ? 'true' : 'false' }}" aria-controls="leadsSubmenu">
                    <span><i class="bi bi-person-lines-fill me-2"></i>Leads</span>
                    <i class="bi bi-chevron-right menu-arrow"></i>
                </a>
                <div class="collapse {{ $leadsActive ? 'show' : '' }}" id="leadsSubmenu">
                    <ul class="nav flex-column ms-3">
                        @foreach ($leadsMenu as $item)
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs($item['route']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </li>
            @endif


        </ul>
    </nav>

    <div class="sidebar-footer">
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button class="btn btn-outline-secondary w-100">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
            </button>
        </form>
    </div>
</aside>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const submenuIds = [
                'activitiesSubmenu',
                'postsSubmenu',
                'pendingRequestsSubmenu',
                'leadsSubmenu',
                'campaignsSubmenu',
                'brandPartnersSubmenu',
                'eventsManagementSubmenu',
                'dedLeadershipSubmenu',
                'dedAnalyticsSubmenu',
            ];

            // Collect all existing submenus
            const submenus = submenuIds
                .map(id => document.getElementById(id))
                .filter(Boolean);

            submenus.forEach((submenu) => {
                const parentItem = submenu.closest('.menu-parent');
                if (!parentItem) return;

                // Sync the .open class on page load
                if (submenu.classList.contains('show')) {
                    parentItem.classList.add('open');
                }

                // Find the toggle anchor for this submenu (matches href="#submenuId")
                const toggle = parentItem.querySelector(`a[href="#${submenu.id}"]`);
                if (!toggle) return;

                toggle.addEventListener('click', (e) => {
                    e.preventDefault();

                    const isCurrentlyOpen = submenu.classList.contains('show');

                    // Close ALL open submenus first
                    submenus.forEach((otherSubmenu) => {
                        if (otherSubmenu !== submenu && otherSubmenu.classList.contains('show')) {
                            const bsCollapse = bootstrap.Collapse.getInstance(otherSubmenu);
                            if (bsCollapse) {
                                bsCollapse.hide();
                            } else {
                                new bootstrap.Collapse(otherSubmenu, { toggle: false }).hide();
                            }
                            const otherParent = otherSubmenu.closest('.menu-parent');
                            if (otherParent) otherParent.classList.remove('open');
                        }
                    });

                    // Toggle the clicked submenu
                    if (isCurrentlyOpen) {
                        const bsCollapse = bootstrap.Collapse.getInstance(submenu);
                        if (bsCollapse) {
                            bsCollapse.hide();
                        } else {
                            new bootstrap.Collapse(submenu, { toggle: false }).hide();
                        }
                        parentItem.classList.remove('open');
                    } else {
                        const bsCollapse = bootstrap.Collapse.getInstance(submenu);
                        if (bsCollapse) {
                            bsCollapse.show();
                        } else {
                            new bootstrap.Collapse(submenu, { toggle: false }).show();
                        }
                        parentItem.classList.add('open');
                    }
                });
            });
        });
    </script>
@endpush
