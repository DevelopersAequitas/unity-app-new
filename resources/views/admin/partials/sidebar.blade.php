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
            ['icon' => 'bi-diagram-3', 'label' => 'Circles', 'route' => 'admin.circles.index'],
            ['icon' => 'bi-diagram-2', 'label' => 'Industries', 'route' => 'admin.execution.industries'],
            ['icon' => 'bi-coin', 'label' => 'Coins', 'route' => 'admin.coins.index'],
            ['icon' => 'bi-heart-pulse', 'label' => 'Life Impact', 'route' => 'admin.life-impact.index'],
            ['icon' => 'bi-bell', 'label' => 'Notifications & Email', 'route' => 'admin.campaigns.index', 'active_routes' => ['admin.campaigns.*', 'admin.campaign-pamphlets.*', 'admin.campaign-email-templates.*', 'admin.email-logs.*', 'admin.execution.communications', 'admin.daily-notifications.*']],
            ['icon' => 'bi-sliders', 'label' => 'App Configuration', 'route' => 'admin.app-config.index'],
        ]
        : (($isCircleScoped || $isDed)
            ? [
                ['icon' => 'bi-people', 'label' => 'Peers', 'route' => 'admin.users.index'],
                ...($isDed || \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Circles') ? [
                    ['icon' => 'bi-diagram-3', 'label' => 'Circles', 'route' => 'admin.circles.index'],
                ] : []),
                ...($isDed || \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Industries') ? [
                    ['icon' => 'bi-diagram-2', 'label' => 'Industries', 'route' => $isDed ? 'admin.ded.dashboard.industries' : 'admin.execution.industries'],
                ] : []),
                ['icon' => 'bi-coin', 'label' => 'Coins', 'route' => 'admin.coins.index'],
                ['icon' => 'bi-heart-pulse', 'label' => 'Life Impact', 'route' => 'admin.life-impact.index'],
                ...(\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Notifications & Email') ? [
                    ['icon' => 'bi-bell', 'label' => 'Notifications & Email', 'route' => 'admin.campaigns.index', 'active_routes' => ['admin.campaigns.*', 'admin.campaign-pamphlets.*', 'admin.campaign-email-templates.*', 'admin.email-logs.*', 'admin.execution.communications', 'admin.daily-notifications.*']],
                ] : []),
                ...(! $isDed && ! $isCircleCommittee ? [
                    ['icon' => 'bi-envelope-paper', 'label' => 'Email Logs', 'route' => 'admin.email-logs.index'],
                    ['icon' => 'bi-ticket-perforated', 'label' => 'Support Tickets', 'route' => 'admin.support-tickets.index'],
                    ['icon' => 'bi-envelope', 'label' => 'All Available Email Lists', 'route' => 'admin.email-templates.index', 'active_routes' => ['admin.email-templates.*']],
                    ['icon' => 'bi-bell', 'label' => 'All Available Notifications Lists', 'route' => 'admin.notification-templates.index', 'active_routes' => ['admin.notification-templates.*']],
                    ['icon' => 'bi-ticket-perforated', 'label' => 'Support Tickets', 'route' => 'admin.support-tickets.index']
                ] : []),
                ...($isGlobalAdmin || \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Events Management') || \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Events') ? [
                    ['icon' => 'bi-calendar-check', 'label' => 'Events Management', 'route' => 'admin.events.index', 'active_routes' => ['admin.events.*', 'admin.event-joining-requests.*']],
                    ['icon' => 'bi-ticket-perforated', 'label' => 'Event Coupons', 'route' => 'admin.event-coupons.index', 'active_routes' => ['admin.event-coupons.*']],
                    ['icon' => 'bi-images', 'label' => 'Event Gallery', 'route' => 'admin.event-gallery.index'],
                ] : []),
                ...(\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Circle Categories') ? [
                    ['icon' => 'bi-tags', 'label' => 'Circle Categories', 'route' => 'admin.categories.index'],
                ] : []),
                ...(\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Impact Option') && app(\App\Services\Admin\PermissionService::class)->canAccessRoute($adminUser, 'admin.impacts.index') ? [
                    ['icon' => 'bi-lightning-charge', 'label' => 'Impact Option', 'route' => 'admin.impacts.index', 'active_routes' => ['admin.impacts.index', 'admin.impacts.store', 'admin.impacts.show', 'admin.impacts.posts']],
                ] : []),
            ]
            : [
                ['icon' => 'bi-people', 'label' => 'Peers', 'route' => 'admin.users.index'],
                ['icon' => 'bi-person-check', 'label' => 'Member Introducers', 'route' => 'admin.member-introducers.index'],
                ['icon' => 'bi-trophy', 'label' => 'Sponsored Member Milestone Awards', 'route' => 'admin.sponsored-milestones.index', 'active_routes' => ['admin.sponsored-milestones.*']],
                ['icon' => 'bi-award', 'label' => 'Milestone Badges', 'route' => 'admin.milestone-badges.index', 'active_routes' => ['admin.milestone-badges.*']],
                ['icon' => 'bi-person-lines-fill', 'label' => 'Unity Contacts', 'route' => 'admin.contacts.index', 'active_routes' => ['admin.contacts.*']],
                ['icon' => 'bi-diagram-2', 'label' => 'Industries', 'route' => 'admin.execution.industries'],
                ...($isGlobalAdmin ? [['icon' => 'bi-clock-history', 'label' => 'Login History', 'route' => 'admin.login-history.index']] : []),
                ['icon' => 'bi-diagram-3', 'label' => 'Circles', 'route' => 'admin.circles.index'],
                ['icon' => 'bi-megaphone', 'label' => 'Circulars', 'route' => 'admin.circulars.index'],
                ['icon' => 'bi-coin', 'label' => 'Coins', 'route' => 'admin.coins.index'],
                ['icon' => 'bi-heart-pulse', 'label' => 'Life Impact', 'route' => 'admin.life-impact.index'],
                ['icon' => 'bi-bell', 'label' => 'Notifications & Email', 'route' => 'admin.campaigns.index', 'active_routes' => ['admin.campaigns.*', 'admin.campaign-pamphlets.*', 'admin.campaign-email-templates.*', 'admin.email-logs.*', 'admin.execution.communications', 'admin.daily-notifications.*']],
                ...(! $isCircleCommittee ? [
                    ['icon' => 'bi-envelope-paper', 'label' => 'Email Logs', 'route' => 'admin.email-logs.index'],
                    ['icon' => 'bi-envelope', 'label' => 'All Available Email Lists', 'route' => 'admin.email-templates.index', 'active_routes' => ['admin.email-templates.*']],
                    ['icon' => 'bi-bell', 'label' => 'All Available Notifications Lists', 'route' => 'admin.notification-templates.index', 'active_routes' => ['admin.notification-templates.*']],
                    ['icon' => 'bi-ticket-perforated', 'label' => 'Support Tickets', 'route' => 'admin.support-tickets.index']
                ] : []),
                ...($isGlobalAdmin || \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Events Management') ? [
                    ['icon' => 'bi-calendar-check', 'label' => 'Events Management', 'route' => 'admin.events.index', 'active_routes' => ['admin.events.*', 'admin.event-joining-requests.*']],
                    ['icon' => 'bi-ticket-perforated', 'label' => 'Event Coupons', 'route' => 'admin.event-coupons.index', 'active_routes' => ['admin.event-coupons.*']],
                    ['icon' => 'bi-images', 'label' => 'Event Gallery', 'route' => 'admin.event-gallery.index'],
                ] : []),
                ...(\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Circle Categories') ? [
                    ['icon' => 'bi-tags', 'label' => 'Circle Categories', 'route' => 'admin.categories.index'],
                ] : []),
                ...(\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Impact Option') && app(\App\Services\Admin\PermissionService::class)->canAccessRoute($adminUser, 'admin.impacts.index') ? [
                    ['icon' => 'bi-lightning-charge', 'label' => 'Impact Option', 'route' => 'admin.impacts.index', 'active_routes' => ['admin.impacts.index', 'admin.impacts.store', 'admin.impacts.show', 'admin.impacts.posts']],
                ] : []),
            ]);

    $fullActivityMenu = [
        ['label' => 'Summary', 'route' => 'admin.activities.index', 'active_routes' => ['admin.activities.index']],
        ['label' => 'Testimonials', 'route' => 'admin.activities.testimonials.index', 'active_routes' => ['admin.activities.testimonials*']],
        ['label' => 'Requirements', 'route' => 'admin.activities.requirements.index', 'active_routes' => ['admin.activities.requirements*']],
        ['label' => 'Referrals', 'route' => 'admin.activities.referrals.index', 'active_routes' => ['admin.activities.referrals*']],
        ['label' => 'P2P Meetings', 'route' => 'admin.activities.p2p-meetings.index', 'active_routes' => ['admin.activities.p2p-meetings*']],
        ['label' => 'Business Deals', 'route' => 'admin.activities.business-deals.index', 'active_routes' => ['admin.activities.business-deals*']],
        ['label' => 'Leadership Requests', 'route' => 'admin.activities.become-a-leader.index', 'active_routes' => ['admin.activities.become-a-leader*']],
        ['label' => 'Recommended Peers', 'route' => 'admin.activities.recommend-peer.index', 'active_routes' => ['admin.activities.recommend-peer*']],
        ['label' => 'Collaborations', 'route' => 'admin.collaborations.index', 'active_routes' => ['admin.collaborations*']],
        ['label' => 'Registered Visitor', 'route' => 'admin.activities.register-visitor.index', 'active_routes' => ['admin.activities.register-visitor*']],
    ];

    $activityMenu = ($isIndustryDirector || $isSuper || $isCircleScoped || $isDed) ? $fullActivityMenu : [];

    if ($isDed) {
        $activityMenu = array_values(array_filter($activityMenu, function ($item) use ($adminUser) {
            if (\App\Support\AdminAccess::isSectionAllowed($adminUser, $item['label'])) {
                return true;
            }
            return !in_array($item['label'], ['Registered Visitor', 'Recommended Peers', 'Collaborations'], true);
        }));
    }

    $activityActive = request()->routeIs('admin.activities*') || request()->routeIs('admin.collaborations*');
    $referralReportItem = (! $isCircleCommittee && ($isSuper || $isCircleScoped || $isDed || $isIndustryDirector))
        ? ['icon' => 'bi-person-lines-fill', 'label' => 'Referral Report', 'route' => 'admin.referral-report.index', 'active_routes' => ['admin.referral-report.*']]
        : null;
    $activityExpanded = $activityActive;

    $postsMenu = ($isGlobalAdmin || \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Content & Posts') || \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Posts & Timeline')) ? [
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
        ['label' => 'Story Submissions', 'route' => 'admin.stories.index'],
    ];

    $pendingRequestsMenu = $isIndustryDirector
        ? [
            ['label' => 'Circle Joining Requests', 'route' => 'admin.circle-joining-requests.index'],
            ['label' => 'Account Deletion Requests', 'route' => 'admin.account-deletion.index'],
            ['label' => 'Account Deletion Emails', 'route' => 'admin.account-deletion.emails'],
        ]
        : [
            ['label' => 'Visitor Registrations', 'route' => 'admin.visitor-registrations.index'],
            ['label' => 'Coin Claims', 'route' => 'admin.coin-claims.index'],
            ['label' => 'Circle Joining Requests', 'route' => 'admin.circle-joining-requests.index'],
            ['label' => 'Certifications', 'route' => 'admin.certifications.index'],
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

    $leadsActive = request()->routeIs('admin.leads.*') || request()->routeIs('admin.stories.*');
    $pendingRequestsActive =
        request()->routeIs('admin.visitor-registrations.*') ||
        request()->routeIs('admin.coin-claims.*') ||
        request()->routeIs('admin.circle-joining-requests.*') ||
        request()->routeIs('admin.certifications.*') ||
        request()->routeIs('admin.impacts.pending') ||
        request()->routeIs('admin.account-deletion.*') ||
        request()->routeIs('admin.introduction-requests.*');

    $leadsMenu = (! $isCircleCommittee && (\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Lead Submissions') || \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Leads'))) ? $leadsMenu : [];

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
        ['label' => 'Total Attendance', 'route' => 'admin.events.total-attendance'],
        ['label' => 'Total Registered', 'route' => 'admin.events.total-registered'],
        ['label' => 'Event Coupons', 'route' => 'admin.event-coupons.index', 'active_routes' => ['admin.event-coupons.*']],
        ['label' => 'Event Joining Requests', 'route' => 'admin.event-joining-requests.index'],
        ['label' => 'Event Scan Credentials', 'route' => 'admin.event-scan-credentials.index'],
        ['label' => 'Event Gallery', 'route' => 'admin.event-gallery.index'],
    ];

    $eventsManagementActive = request()->routeIs('admin.events.*')
        || request()->routeIs('admin.event-scan-credentials.*')
        || request()->routeIs('admin.event-gallery.*')
        || request()->routeIs('admin.event-joining-requests.*')
        || request()->routeIs('admin.event-coupons.*');
    $bottomNavItems = array_values(array_filter($navItems, fn ($item) => ($item['label'] ?? null) === 'Email Logs'));
    $bottomNavItems = (! $isCircleScoped && ! $isDed && ! $isIndustryDirector) ? [] : $bottomNavItems;
    $navItems = array_values(array_filter($navItems, fn ($item) => ! in_array(($item['label'] ?? null), ['Events Management', 'Event Coupons', 'Email Logs', 'Event Gallery'], true)));
    $bottomNavItems = array_map(function ($item) {
        if ($item['label'] === 'Email Logs') {
            $item['active_routes'] = ['admin.email-logs.*'];
        } elseif ($item['label'] === 'Support Tickets') {
            $item['active_routes'] = ['admin.support-tickets.*'];
        }
        return $item;
    }, array_values(array_filter($navItems, fn ($item) => in_array(($item['label'] ?? null), ['Email Logs', 'Support Tickets'], true))));
    $navItems = array_values(array_filter($navItems, fn ($item) => ! in_array(($item['label'] ?? null), ['Events Management', 'Email Logs', 'Support Tickets'], true)));
    $campaignsMenu = \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Notifications & Email') ? $campaignsMenu : [];
    $navItems = array_values(array_filter($navItems, fn ($item) => ! in_array(($item['label'] ?? null), ['Events Management', 'Event Coupons', 'Email Logs', 'Support Tickets'], true)));
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
    $hasBrandPartnersRole = $isGlobalAdmin || ($adminUser?->roles?->pluck('key')->intersect(['global_admin', 'global_founder', 'marketing_team', 'analytics_team', 'content_team', 'read_only'])->isNotEmpty() ?? false);

    $adsActive = request()->routeIs('admin.ads.*');
    $adsMenu = [
        ['label' => 'Dashboard', 'route' => 'admin.ads.dashboard'],
        ['label' => 'All Ads', 'route' => 'admin.ads.index', 'active_routes' => ['admin.ads.index', 'admin.ads.create', 'admin.ads.edit', 'admin.ads.show']],
        ['label' => 'Analytics', 'route' => 'admin.ads.analytics'],
    ];
    $hasAdsRole = $adminUser?->roles?->pluck('key')->intersect(['global_admin', 'marketing_team', 'analytics_team', 'content_team', 'read_only'])->isNotEmpty() ?? false;

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

    // Filter allowed sidebar sections and sub-items
    if ($dashboardItem && ! \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Dashboard')) {
        $dashboardItem = null;
    }
    if (! \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Activities')) {
        $activityMenu = [];
    }
    if ($referralReportItem && ! \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Referral Report')) {
        $referralReportItem = null;
    }
    if (! \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Posts & Timeline') && ! \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Content & Posts') && ! $isGlobalAdmin) {
        $postsMenu = [];
    }
    if (! \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Leads') && ! \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Lead Submissions')) {
        $leadsMenu = [];
    }

    if ($activityMenu) {
        $activityMenu = array_values(array_filter($activityMenu, function ($item) use ($adminUser) {
            return \App\Support\AdminAccess::isSectionAllowed($adminUser, $item['label']) || \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Activities');
        }));
    }
    if ($postsMenu) {
        $postsMenu = array_values(array_filter($postsMenu, function ($item) use ($adminUser) {
            return \App\Support\AdminAccess::isSectionAllowed($adminUser, $item['label']) || \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Posts & Timeline') || \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Content & Posts');
        }));
    }
    if ($pendingRequestsMenu) {
        $pendingRequestsMenu = array_values(array_filter($pendingRequestsMenu, function ($item) use ($adminUser) {
            return \App\Support\AdminAccess::isSectionAllowed($adminUser, $item['label']) || \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Pending Requests');
        }));
    }
    if ($eventsManagementMenu) {
        $eventsManagementMenu = array_values(array_filter($eventsManagementMenu, function ($item) use ($adminUser) {
            return \App\Support\AdminAccess::isSectionAllowed($adminUser, $item['label']) || \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Events Management') || \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Events');
        }));
    }

    $navItems = array_values(array_filter($navItems, function ($item) use ($adminUser) {
        $label = $item['label'] ?? null;
        if ($label && ! \App\Support\AdminAccess::isSectionAllowed($adminUser, $label)) {
            return false;
        }

        return true;
    }));

    $bottomNavItems = array_values(array_filter($bottomNavItems, function ($item) use ($adminUser) {
        $label = $item['label'] ?? null;
        if ($label && ! \App\Support\AdminAccess::isSectionAllowed($adminUser, $label)) {
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
                                    <a class="nav-link {{ (isset($item['active_routes']) ? request()->routeIs(...$item['active_routes']) : request()->routeIs($item['route'])) ? 'active' : '' }}" href="{{ route($item['route']) }}">
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

            @if ($isDed && (\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Analytics') || \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Finance & Analytics')))
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

            @if (\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Events Management'))
                <li class="nav-item menu-parent {{ $eventsManagementActive ? 'open' : '' }}">
                    <a class="nav-link d-flex justify-content-between align-items-center {{ $eventsManagementActive ? 'active' : '' }}" href="#eventsManagementSubmenu" role="button" aria-expanded="{{ $eventsManagementActive ? 'true' : 'false' }}" aria-controls="eventsManagementSubmenu">
                        <span><i class="bi bi-calendar-check me-2"></i>Events Management</span>
                        <i class="bi bi-chevron-right menu-arrow"></i>
                    </a>
                    <div class="collapse {{ $eventsManagementActive ? 'show' : '' }}" id="eventsManagementSubmenu">
                        <ul class="nav flex-column ms-3">
                            @foreach ($eventsManagementMenu as $eventItem)
                                <li class="nav-item">
                                    <a class="nav-link {{ (isset($eventItem['active_routes']) ? request()->routeIs(...$eventItem['active_routes']) : request()->routeIs($eventItem['route'])) ? 'active' : '' }}" href="{{ route($eventItem['route']) }}">{{ $eventItem['label'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </li>
            @endif

            @if (\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Brand Partners'))
                <li class="nav-item menu-parent {{ $brandPartnersActive ? 'open' : '' }}">
                    <a class="nav-link d-flex justify-content-between align-items-center {{ $brandPartnersActive ? 'active' : '' }}" href="#brandPartnersSubmenu" role="button" aria-expanded="{{ $brandPartnersActive ? 'true' : 'false' }}" aria-controls="brandPartnersSubmenu">
                        <span><i class="bi bi-briefcase me-2"></i>Brand Partners</span>
                        <i class="bi bi-chevron-right menu-arrow"></i>
                    </a>
                    <div class="collapse brand-partners-submenu {{ $brandPartnersActive ? 'show' : '' }}" id="brandPartnersSubmenu">
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

            @if (\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Ads'))
                <li class="nav-item menu-parent {{ $adsActive ? 'open' : '' }}">
                    <a class="nav-link d-flex justify-content-between align-items-center {{ $adsActive ? 'active' : '' }}" href="#adsSubmenu" role="button" aria-expanded="{{ $adsActive ? 'true' : 'false' }}" aria-controls="adsSubmenu">
                        <span><i class="bi bi-megaphone me-2"></i>Ads</span>
                        <i class="bi bi-chevron-right menu-arrow"></i>
                    </a>
                    <div class="collapse {{ $adsActive ? 'show' : '' }}" id="adsSubmenu">
                        <ul class="nav flex-column ms-3">
                            @foreach ($adsMenu as $item)
                                @if (Route::has($item['route']))
                                    <li class="nav-item">
                                        <a class="nav-link {{ (isset($item['active_routes']) ? request()->routeIs(...$item['active_routes']) : request()->routeIs($item['route'])) ? 'active' : '' }}" href="{{ route($item['route']) }}">
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
                @elseif ($item['label'] === 'Milestone Badges')
                    @php
                        $badgesActive = request()->routeIs('admin.milestone-badges.*');
                        $currentType = request('type');
                    @endphp
                    <li class="nav-item menu-parent {{ $badgesActive ? 'open' : '' }}">
                        <a class="nav-link d-flex justify-content-between align-items-center {{ $badgesActive ? 'active' : '' }}" href="#milestoneBadgesSubmenu" role="button" aria-expanded="{{ $badgesActive ? 'true' : 'false' }}" aria-controls="milestoneBadgesSubmenu">
                            <span><i class="bi bi-award me-2"></i>Milestone Badges</span>
                            <i class="bi bi-chevron-right menu-arrow"></i>
                        </a>
                        <div class="collapse {{ $badgesActive ? 'show' : '' }}" id="milestoneBadgesSubmenu">
                            <ul class="nav flex-column ms-3">
                                <li class="nav-item">
                                    <a class="nav-link {{ ($badgesActive && $currentType === 'life_impact') ? 'active' : '' }}" href="{{ route('admin.milestone-badges.index', ['type' => 'life_impact']) }}">
                                        Life Impact Badges
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ ($badgesActive && $currentType === 'coins') ? 'active' : '' }}" href="{{ route('admin.milestone-badges.index', ['type' => 'coins']) }}">
                                        Coin Badges
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ ($badgesActive && $currentType === 'member_introduction') ? 'active' : '' }}" href="{{ route('admin.milestone-badges.index', ['type' => 'member_introduction']) }}">
                                        Member Introduction Badges
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ ($badgesActive && empty($currentType)) ? 'active' : '' }}" href="{{ route('admin.milestone-badges.index') }}">
                                        All Badges
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                @elseif ($item['label'] === 'Peers')
                    @php
                        $peersActive = request()->routeIs('admin.users.*');
                    @endphp
                    <li class="nav-item menu-parent {{ $peersActive ? 'open' : '' }}">
                        <a class="nav-link d-flex justify-content-between align-items-center {{ $peersActive ? 'active' : '' }}" href="#peersSubmenu" role="button" aria-expanded="{{ $peersActive ? 'true' : 'false' }}" aria-controls="peersSubmenu">
                            <span><i class="bi bi-people me-2"></i>Peers</span>
                            <i class="bi bi-chevron-right menu-arrow"></i>
                        </a>
                        <div class="collapse {{ $peersActive ? 'show' : '' }}" id="peersSubmenu">
                            <ul class="nav flex-column ms-3">
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('admin.users.index') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">All Peers</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('admin.users.upcoming-events') ? 'active' : '' }}" href="{{ route('admin.users.upcoming-events') }}">Upcoming Birthdays &amp; Anniversaries</a>
                                </li>
                            </ul>
                        </div>
                    </li>
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



            @if (\App\Support\AdminAccess::isSectionAllowed($adminUser, 'App Configuration') || \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Settings'))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.app-config.*') ? 'active' : '' }}" href="{{ route('admin.app-config.index') }}">
                    <i class="bi bi-sliders me-2"></i>App Configuration
                </a>
            </li>
            @endif
                @if (\App\Support\AdminAccess::isSectionAllowed($adminUser, 'App Updates Manager'))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.app-updates.*') ? 'active' : '' }}" href="{{ route('admin.app-updates.index') }}">
                        <i class="bi bi-arrow-up-circle me-2"></i>App Updates Manager
                    </a>
                </li>
                @endif
                @if (\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Birthday Creative'))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.birthday-creative.*') ? 'active' : '' }}" href="{{ route('admin.birthday-creative.index') }}">
                        <i class="bi bi-gift me-2"></i>Birthday Creative
                    </a>
                </li>
                @endif
                @if (\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Anniversary Creative'))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.anniversary-creatives.*') ? 'active' : '' }}" href="{{ route('admin.anniversary-creatives.index') }}">
                        <i class="bi bi-images me-2"></i>Anniversary Creative
                    </a>
                </li>
                @endif
                @if (\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Tutorials'))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.tutorials.*') ? 'active' : '' }}" href="{{ route('admin.tutorials.index') }}">
                        <i class="bi bi-play-btn me-2"></i>Tutorials
                    </a>
                </li>
                @endif
                @if (\App\Support\AdminAccess::isSectionAllowed($adminUser, 'Dynamic RBAC') || \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Role Management'))
                {{-- Dynamic RBAC & Role Management Menu --}}
                <li class="nav-item menu-parent {{ request()->routeIs('admin.rbac.*') ? 'open' : '' }}">
                    <a class="nav-link d-flex justify-content-between align-items-center {{ request()->routeIs('admin.rbac.*') ? 'active' : '' }}" href="#rbacSubmenu" role="button" aria-expanded="{{ request()->routeIs('admin.rbac.*') ? 'true' : 'false' }}" aria-controls="rbacSubmenu">
                        <span><i class="bi bi-shield-lock me-2"></i>Dynamic RBAC</span>
                        <i class="bi bi-chevron-right menu-arrow"></i>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.rbac.*') ? 'show' : '' }}" id="rbacSubmenu">
                        <ul class="nav flex-column ms-3">
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('admin.rbac.permission-matrix.*') ? 'active' : '' }}" href="{{ route('admin.rbac.permission-matrix.index') }}">Permission Matrix</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('admin.rbac.module-access.*') ? 'active' : '' }}" href="{{ route('admin.rbac.module-access.index') }}">Module Access</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('admin.rbac.modules.*') ? 'active' : '' }}" href="{{ route('admin.rbac.modules.index') }}">Admin Modules</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('admin.rbac.pages.*') ? 'active' : '' }}" href="{{ route('admin.rbac.pages.index') }}">Admin Pages</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('admin.rbac.page-groups.*') ? 'active' : '' }}" href="{{ route('admin.rbac.page-groups.index') }}">Page Groups</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('admin.rbac.data-scope.*') ? 'active' : '' }}" href="{{ route('admin.rbac.data-scope.index') }}">Data Scope</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('admin.rbac.workflow-rules.*') ? 'active' : '' }}" href="{{ route('admin.rbac.workflow-rules.index') }}">Workflow Rules</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('admin.rbac.hierarchy') ? 'active' : '' }}" href="{{ route('admin.rbac.hierarchy') }}">Role Hierarchy</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('admin.rbac.lifespan.*') ? 'active' : '' }}" href="{{ route('admin.rbac.lifespan.index') }}">Role History</a>
                            </li>
                        </ul>
                    </div>
                </li>
                @endif


            @if ($leadsMenu !== [] && \App\Support\AdminAccess::isSectionAllowed($adminUser, 'Leads'))
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
            const sidebar = document.querySelector('.admin-sidebar');
            const menuParents = document.querySelectorAll('.admin-sidebar .menu-parent');

            if (sidebar) {
                const savedScroll = sessionStorage.getItem('sidebar_scroll_top');
                if (savedScroll !== null) {
                    sidebar.scrollTop = parseInt(savedScroll, 10);
                }
                const activeLink = sidebar.querySelector('.nav-link.active');
                if (activeLink) {
                    activeLink.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                }

                sidebar.addEventListener('scroll', () => {
                    sessionStorage.setItem('sidebar_scroll_top', sidebar.scrollTop);
                }, { passive: true });

                sidebar.querySelectorAll('a').forEach((link) => {
                    link.addEventListener('click', () => {
                        sessionStorage.setItem('sidebar_scroll_top', sidebar.scrollTop);
                    });
                });
            }

            menuParents.forEach((parentItem) => {
                const submenu = parentItem.querySelector('.collapse');
                if (!submenu) return;

                const toggle = parentItem.querySelector('a[role="button"]') || parentItem.querySelector(`a[href="#${submenu.id}"]`) || parentItem.querySelector('a');
                if (!toggle) return;

                // Sync initial state on load
                if (submenu.classList.contains('show') || parentItem.classList.contains('open')) {
                    parentItem.classList.add('open');
                    submenu.classList.add('show');
                    toggle.setAttribute('aria-expanded', 'true');
                } else {
                    parentItem.classList.remove('open');
                    submenu.classList.remove('show');
                    toggle.setAttribute('aria-expanded', 'false');
                }

                toggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();

                    const isOpen = parentItem.classList.contains('open') || submenu.classList.contains('show');

                    // Close other submenus for accordion behavior
                    menuParents.forEach((otherParent) => {
                        if (otherParent !== parentItem) {
                            otherParent.classList.remove('open');
                            const otherSub = otherParent.querySelector('.collapse');
                            if (otherSub) {
                                otherSub.classList.remove('show');
                            }
                            const otherTog = otherParent.querySelector('a[role="button"]') || otherParent.querySelector('a');
                            if (otherTog) otherTog.setAttribute('aria-expanded', 'false');
                        }
                    });

                    if (isOpen) {
                        parentItem.classList.remove('open');
                        submenu.classList.remove('show');
                        toggle.setAttribute('aria-expanded', 'false');
                    } else {
                        parentItem.classList.add('open');
                        submenu.classList.add('show');
                        toggle.setAttribute('aria-expanded', 'true');
                    }
                });
            });
        });
    </script>
@endpush

@push('styles')
    <style>
        .admin-sidebar .menu-parent.open > .collapse,
        .admin-sidebar .collapse.show {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            height: auto !important;
            overflow: visible !important;
        }
    </style>
@endpush
