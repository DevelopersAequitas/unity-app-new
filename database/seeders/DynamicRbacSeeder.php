<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AdminModule;
use App\Models\AdminPage;
use App\Models\PageGroup;
use App\Models\PageGroupItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleModuleAccess;
use App\Models\RolePageGroup;
use App\Models\RolePagePermission;
use Illuminate\Database\Seeder;

class DynamicRbacSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermissions();
        $this->seedModulesAndPages();
        $this->seedPageGroups();
        $this->seedDefaultRoleAccess();
    }

    private function seedPermissions(): void
    {
        $permissions = [
            ['key' => 'view', 'name' => 'View', 'description' => 'View records and pages', 'sort_order' => 1],
            ['key' => 'create', 'name' => 'Create', 'description' => 'Create new records', 'sort_order' => 2],
            ['key' => 'edit', 'name' => 'Edit', 'description' => 'Edit existing records', 'sort_order' => 3],
            ['key' => 'delete', 'name' => 'Delete', 'description' => 'Delete records', 'sort_order' => 4],
            ['key' => 'approve', 'name' => 'Approve', 'description' => 'Approve pending items', 'sort_order' => 5],
            ['key' => 'reject', 'name' => 'Reject', 'description' => 'Reject pending items', 'sort_order' => 6],
            ['key' => 'export', 'name' => 'Export', 'description' => 'Export data to CSV/Excel', 'sort_order' => 7],
            ['key' => 'import', 'name' => 'Import', 'description' => 'Import data from files', 'sort_order' => 8],
            ['key' => 'print', 'name' => 'Print', 'description' => 'Print reports', 'sort_order' => 9],
            ['key' => 'restore', 'name' => 'Restore', 'description' => 'Restore deleted records', 'sort_order' => 10],
        ];

        foreach ($permissions as $perm) {
            Permission::query()->firstOrCreate(
                ['key' => $perm['key']],
                $perm,
            );
        }
    }

    private function seedModulesAndPages(): void
    {
        $modulesConfig = $this->getModulesConfig();

        foreach ($modulesConfig as $moduleData) {
            $module = AdminModule::query()->firstOrCreate(
                ['slug' => $moduleData['slug']],
                [
                    'name' => $moduleData['name'],
                    'slug' => $moduleData['slug'],
                    'icon' => $moduleData['icon'],
                    'sort_order' => $moduleData['sort_order'],
                    'is_active' => true,
                ],
            );

            foreach ($moduleData['pages'] as $pageData) {
                AdminPage::query()->firstOrCreate(
                    ['route_name' => $pageData['route_name']],
                    [
                        'module_id' => $module->id,
                        'name' => $pageData['name'],
                        'route_name' => $pageData['route_name'],
                        'slug' => $pageData['slug'],
                        'icon' => $pageData['icon'] ?? null,
                        'sort_order' => $pageData['sort_order'],
                        'is_active' => true,
                    ],
                );
            }
        }
    }

    private function seedPageGroups(): void
    {
        $groups = [
            [
                'name' => 'Membership Management',
                'slug' => 'membership-management',
                'description' => 'All pages related to member management',
                'pages' => ['admin.users.index', 'admin.users.show', 'admin.users.create', 'admin.users.edit', 'admin.users.import', 'admin.users.export.csv', 'admin.pending-registrations.index'],
            ],
            [
                'name' => 'Activity Management',
                'slug' => 'activity-management',
                'description' => 'Activity tracking and reports',
                'pages' => ['admin.activities.index', 'admin.activities.testimonials.index', 'admin.activities.referrals.index', 'admin.activities.p2p-meetings.index', 'admin.activities.business-deals.index', 'admin.activities.connections.index', 'admin.activities.become-a-leader.index', 'admin.activities.recommend-peer.index', 'admin.activities.register-visitor.index', 'admin.collaborations.index'],
            ],
            [
                'name' => 'Event Management',
                'slug' => 'event-management',
                'description' => 'Event creation, attendance and gallery',
                'pages' => ['admin.events.index', 'admin.events.create', 'admin.events.total-attendance', 'admin.events.total-registered', 'admin.event-joining-requests.index', 'admin.event-scan-credentials.index', 'admin.event-gallery.index'],
            ],
            [
                'name' => 'Circle Management',
                'slug' => 'circle-management',
                'description' => 'Circle CRUD and member management',
                'pages' => ['admin.circles.index', 'admin.circles.create', 'admin.circle-joining-requests.index'],
            ],
            [
                'name' => 'Notification Management',
                'slug' => 'notification-management',
                'description' => 'Campaigns, emails, and push notifications',
                'pages' => ['admin.campaigns.index', 'admin.campaigns.create', 'admin.campaign-email-templates.index', 'admin.campaign-pamphlets.index', 'admin.email-logs.index', 'admin.daily-notifications.index'],
            ],
            [
                'name' => 'Pending Requests',
                'slug' => 'pending-requests',
                'description' => 'All approval queues',
                'pages' => ['admin.visitor-registrations.index', 'admin.coin-claims.index', 'admin.circle-joining-requests.index', 'admin.certifications.index', 'admin.impacts.pending', 'admin.account-deletion.index', 'admin.introduction-requests.index'],
            ],
            [
                'name' => 'Financial & Coins',
                'slug' => 'financial-coins',
                'description' => 'Coin management and transactions',
                'pages' => ['admin.coins.index', 'admin.coins.create', 'admin.coin-claims.index'],
            ],
            [
                'name' => 'Settings & Config',
                'slug' => 'settings-config',
                'description' => 'Application configuration and settings',
                'pages' => ['admin.app-config.index', 'admin.app-updates.index', 'admin.birthday-creative.index', 'admin.anniversary-creatives.index', 'admin.tutorials.index', 'admin.categories.index'],
            ],
        ];

        foreach ($groups as $groupData) {
            $group = PageGroup::query()->firstOrCreate(
                ['slug' => $groupData['slug']],
                [
                    'name' => $groupData['name'],
                    'slug' => $groupData['slug'],
                    'description' => $groupData['description'],
                    'is_active' => true,
                ],
            );

            $sortOrder = 0;
            foreach ($groupData['pages'] as $routeName) {
                $page = AdminPage::query()->where('route_name', $routeName)->first();
                if ($page) {
                    PageGroupItem::query()->firstOrCreate(
                        ['page_group_id' => $group->id, 'page_id' => $page->id],
                        ['sort_order' => ++$sortOrder],
                    );
                }
            }
        }
    }

    private function seedDefaultRoleAccess(): void
    {
        $modules = AdminModule::query()->pluck('id', 'slug');
        $allModuleSlugs = $modules->keys()->all();

        // Define which modules each role can see
        $roleModuleMap = [
            'global_admin' => $allModuleSlugs,
            'global_founder' => $allModuleSlugs,
            'chair' => ['dashboard', 'members', 'activities', 'circles', 'events', 'coins', 'life-impact', 'pending-requests', 'referral-report'],
            'vice_chair' => ['dashboard', 'members', 'activities', 'circles', 'events', 'coins', 'life-impact', 'pending-requests', 'referral-report'],
            'secretary' => ['dashboard', 'members', 'activities', 'coins', 'life-impact', 'pending-requests'],
            'founder' => ['dashboard', 'members', 'activities', 'coins', 'life-impact', 'pending-requests'],
            'director' => ['dashboard', 'members', 'activities', 'coins', 'life-impact', 'pending-requests'],
            'industry_director' => ['dashboard', 'members', 'activities', 'coins', 'life-impact', 'pending-requests', 'circles'],
            'ded' => ['dashboard', 'members', 'activities', 'circles', 'coins', 'life-impact', 'pending-requests', 'industries'],
            'member' => ['dashboard', 'members', 'activities', 'coins', 'life-impact'],
        ];

        // Define which permissions each role gets on all accessible pages
        $rolePermissionMap = [
            'global_admin' => ['view', 'create', 'edit', 'delete', 'approve', 'reject', 'export', 'import', 'print', 'restore'],
            'global_founder' => ['view', 'create', 'edit', 'delete', 'approve', 'reject', 'export', 'import', 'print', 'restore'],
            'chair' => ['view', 'export', 'approve', 'reject'],
            'vice_chair' => ['view', 'export'],
            'secretary' => ['view', 'export'],
            'founder' => ['view'],
            'director' => ['view'],
            'industry_director' => ['view', 'export'],
            'ded' => ['view', 'export'],
            'member' => ['view'],
        ];

        $permissions = Permission::query()->pluck('id', 'key');

        foreach ($roleModuleMap as $roleKey => $moduleSlugs) {
            $role = Role::query()->where('key', $roleKey)->first();

            if (! $role) {
                continue;
            }

            // Seed role_module_access
            foreach ($allModuleSlugs as $slug) {
                $moduleId = $modules->get($slug);
                if (! $moduleId) {
                    continue;
                }

                RoleModuleAccess::query()->firstOrCreate(
                    ['role_id' => $role->id, 'module_id' => $moduleId],
                    ['is_visible' => in_array($slug, $moduleSlugs, true)],
                );
            }

            // Seed role_page_permissions for accessible pages
            $permKeys = $rolePermissionMap[$roleKey] ?? ['view'];
            $accessibleModuleIds = collect($moduleSlugs)
                ->map(fn (string $slug) => $modules->get($slug))
                ->filter()
                ->all();

            $pages = AdminPage::query()
                ->whereIn('module_id', $accessibleModuleIds)
                ->where('is_active', true)
                ->get()
                ->reject(function ($page) use ($roleKey) {
                    if (in_array($roleKey, ['global_admin', 'global_founder'], true)) {
                        return false;
                    }

                    if ($page->slug === 'main-dashboard') {
                        return true;
                    }

                    $allowedDashboardSlug = match ($roleKey) {
                        'ded' => 'ded-dashboard',
                        'industry_director' => 'id-dashboard',
                        default => 'circle-dashboard',
                    };

                    if (in_array($page->slug, ['main-dashboard', 'circle-dashboard', 'ded-dashboard', 'id-dashboard'], true)) {
                        return $page->slug !== $allowedDashboardSlug;
                    }

                    return false;
                });

            foreach ($pages as $page) {
                foreach ($permKeys as $permKey) {
                    $permId = $permissions->get($permKey);
                    if (! $permId) {
                        continue;
                    }

                    RolePagePermission::query()->firstOrCreate(
                        ['role_id' => $role->id, 'page_id' => $page->id, 'permission_id' => $permId],
                    );
                }
            }

            // Assign relevant page groups to the role
            $this->assignPageGroupsToRole($role, $roleKey);
        }
    }

    private function assignPageGroupsToRole(Role $role, string $roleKey): void
    {
        $groupAssignments = match ($roleKey) {
            'global_admin', 'global_founder' => [
                'membership-management', 'activity-management', 'event-management',
                'circle-management', 'notification-management', 'pending-requests',
                'financial-coins', 'settings-config',
            ],
            'chair', 'vice_chair' => [
                'membership-management', 'activity-management', 'event-management',
                'circle-management', 'pending-requests', 'financial-coins',
            ],
            'secretary', 'founder', 'director' => [
                'membership-management', 'activity-management', 'pending-requests', 'financial-coins',
            ],
            'industry_director' => [
                'membership-management', 'activity-management', 'circle-management', 'pending-requests',
            ],
            'ded' => [
                'membership-management', 'activity-management', 'circle-management', 'pending-requests',
            ],
            'member' => [
                'membership-management', 'activity-management',
            ],
            default => [],
        };

        foreach ($groupAssignments as $groupSlug) {
            $group = PageGroup::query()->where('slug', $groupSlug)->first();
            if ($group) {
                RolePageGroup::query()->firstOrCreate(
                    ['role_id' => $role->id, 'page_group_id' => $group->id],
                );
            }
        }
    }

    /**
     * Returns the full module + page configuration derived from the existing routes and sidebar.
     */
    private function getModulesConfig(): array
    {
        return [
            [
                'name' => 'Dashboard',
                'slug' => 'dashboard',
                'icon' => 'bi-speedometer2',
                'sort_order' => 1,
                'pages' => [
                    ['name' => 'Main Dashboard', 'route_name' => 'admin.dashboard', 'slug' => 'main-dashboard', 'sort_order' => 1],
                    ['name' => 'Circle Dashboard', 'route_name' => 'admin.circle-member.dashboard', 'slug' => 'circle-dashboard', 'sort_order' => 2],
                    ['name' => 'DED Dashboard', 'route_name' => 'admin.ded.dashboard', 'slug' => 'ded-dashboard', 'sort_order' => 3],
                    ['name' => 'Industry Director Dashboard', 'route_name' => 'admin.industry-director.dashboard', 'slug' => 'id-dashboard', 'sort_order' => 4],
                ],
            ],
            [
                'name' => 'Peers',
                'slug' => 'members',
                'icon' => 'bi-people',
                'sort_order' => 2,
                'pages' => [
                    ['name' => 'All Members', 'route_name' => 'admin.users.index', 'slug' => 'all-members', 'sort_order' => 1],
                    ['name' => 'View Member', 'route_name' => 'admin.users.show', 'slug' => 'view-member', 'sort_order' => 2],
                    ['name' => 'Create Member', 'route_name' => 'admin.users.create', 'slug' => 'create-member', 'sort_order' => 3],
                    ['name' => 'Edit Member', 'route_name' => 'admin.users.edit', 'slug' => 'edit-member', 'sort_order' => 4],
                    ['name' => 'Import Members', 'route_name' => 'admin.users.import', 'slug' => 'import-members', 'sort_order' => 5],
                    ['name' => 'Export Members', 'route_name' => 'admin.users.export.csv', 'slug' => 'export-members', 'sort_order' => 6],
                    ['name' => 'Member Search', 'route_name' => 'admin.users.search', 'slug' => 'member-search', 'sort_order' => 7],
                    ['name' => 'Member Introducers', 'route_name' => 'admin.member-introducers.index', 'slug' => 'member-introducers', 'sort_order' => 8],
                    ['name' => 'Sponsored Milestones', 'route_name' => 'admin.sponsored-milestones.index', 'slug' => 'sponsored-milestones', 'sort_order' => 9],
                    ['name' => 'Login History', 'route_name' => 'admin.login-history.index', 'slug' => 'login-history', 'sort_order' => 10],
                ],
            ],
            [
                'name' => 'Activities',
                'slug' => 'activities',
                'icon' => 'bi-activity',
                'sort_order' => 3,
                'pages' => [
                    ['name' => 'Activity Summary', 'route_name' => 'admin.activities.index', 'slug' => 'activity-summary', 'sort_order' => 1],
                    ['name' => 'Testimonials', 'route_name' => 'admin.activities.testimonials.index', 'slug' => 'testimonials', 'sort_order' => 2],
                    ['name' => 'Requirements', 'route_name' => 'admin.activities.requirements.index', 'slug' => 'requirements', 'sort_order' => 3],
                    ['name' => 'Referrals', 'route_name' => 'admin.activities.referrals.index', 'slug' => 'referrals', 'sort_order' => 4],
                    ['name' => 'P2P Meetings', 'route_name' => 'admin.activities.p2p-meetings.index', 'slug' => 'p2p-meetings', 'sort_order' => 5],
                    ['name' => 'Business Deals', 'route_name' => 'admin.activities.business-deals.index', 'slug' => 'business-deals', 'sort_order' => 6],
                    ['name' => 'Connections', 'route_name' => 'admin.activities.connections.index', 'slug' => 'connections', 'sort_order' => 7],
                    ['name' => 'Leadership Requests', 'route_name' => 'admin.activities.become-a-leader.index', 'slug' => 'leadership-requests', 'sort_order' => 8],
                    ['name' => 'Recommended Peers', 'route_name' => 'admin.activities.recommend-peer.index', 'slug' => 'recommended-peers', 'sort_order' => 9],
                    ['name' => 'Collaborations', 'route_name' => 'admin.collaborations.index', 'slug' => 'collaborations', 'sort_order' => 10],
                    ['name' => 'Registered Visitor', 'route_name' => 'admin.activities.register-visitor.index', 'slug' => 'registered-visitor', 'sort_order' => 11],
                    ['name' => 'Activity Creatives', 'route_name' => 'admin.activity-creatives.index', 'slug' => 'activity-creatives', 'sort_order' => 12],
                ],
            ],
            [
                'name' => 'Circles',
                'slug' => 'circles',
                'icon' => 'bi-diagram-3',
                'sort_order' => 4,
                'pages' => [
                    ['name' => 'All Circles', 'route_name' => 'admin.circles.index', 'slug' => 'all-circles', 'sort_order' => 1],
                    ['name' => 'Create Circle', 'route_name' => 'admin.circles.create', 'slug' => 'create-circle', 'sort_order' => 2],
                    ['name' => 'View Circle', 'route_name' => 'admin.circles.show', 'slug' => 'view-circle', 'sort_order' => 3],
                    ['name' => 'Edit Circle', 'route_name' => 'admin.circles.edit', 'slug' => 'edit-circle', 'sort_order' => 4],
                    ['name' => 'Circle Join Requests', 'route_name' => 'admin.circle-joining-requests.index', 'slug' => 'circle-join-requests', 'sort_order' => 5],
                ],
            ],
            [
                'name' => 'Events',
                'slug' => 'events',
                'icon' => 'bi-calendar-check',
                'sort_order' => 5,
                'pages' => [
                    ['name' => 'All Events', 'route_name' => 'admin.events.index', 'slug' => 'all-events', 'sort_order' => 1],
                    ['name' => 'Create Event', 'route_name' => 'admin.events.create', 'slug' => 'create-event', 'sort_order' => 2],
                    ['name' => 'Total Attendance', 'route_name' => 'admin.events.total-attendance', 'slug' => 'total-attendance', 'sort_order' => 3],
                    ['name' => 'Total Registered', 'route_name' => 'admin.events.total-registered', 'slug' => 'total-registered', 'sort_order' => 4],
                    ['name' => 'Event Joining Requests', 'route_name' => 'admin.event-joining-requests.index', 'slug' => 'event-joining-requests', 'sort_order' => 5],
                    ['name' => 'Event Scan Credentials', 'route_name' => 'admin.event-scan-credentials.index', 'slug' => 'event-scan-credentials', 'sort_order' => 6],
                    ['name' => 'Event Gallery', 'route_name' => 'admin.event-gallery.index', 'slug' => 'event-gallery', 'sort_order' => 7],
                ],
            ],
            [
                'name' => 'Coins',
                'slug' => 'coins',
                'icon' => 'bi-coin',
                'sort_order' => 6,
                'pages' => [
                    ['name' => 'Coins Overview', 'route_name' => 'admin.coins.index', 'slug' => 'coins-overview', 'sort_order' => 1],
                    ['name' => 'Award Coins', 'route_name' => 'admin.coins.create', 'slug' => 'award-coins', 'sort_order' => 2],
                    ['name' => 'Coin Claims', 'route_name' => 'admin.coin-claims.index', 'slug' => 'coin-claims', 'sort_order' => 3],
                    ['name' => 'Export Coins', 'route_name' => 'admin.coins.export', 'slug' => 'export-coins', 'sort_order' => 4],
                ],
            ],
            [
                'name' => 'Life Impact',
                'slug' => 'life-impact',
                'icon' => 'bi-heart-pulse',
                'sort_order' => 7,
                'pages' => [
                    ['name' => 'Life Impact Overview', 'route_name' => 'admin.life-impact.index', 'slug' => 'life-impact-overview', 'sort_order' => 1],
                    ['name' => 'Export Life Impact', 'route_name' => 'admin.life-impact.export', 'slug' => 'export-life-impact', 'sort_order' => 2],
                    ['name' => 'Pending Impacts', 'route_name' => 'admin.impacts.pending', 'slug' => 'pending-impacts', 'sort_order' => 3],
                    ['name' => 'Impact Options', 'route_name' => 'admin.impacts.index', 'slug' => 'impact-options', 'sort_order' => 4],
                ],
            ],
            [
                'name' => 'Notifications & Email',
                'slug' => 'notifications',
                'icon' => 'bi-bell',
                'sort_order' => 8,
                'pages' => [
                    ['name' => 'Campaign Dashboard', 'route_name' => 'admin.campaigns.index', 'slug' => 'campaign-dashboard', 'sort_order' => 1],
                    ['name' => 'Create Campaign', 'route_name' => 'admin.campaigns.create', 'slug' => 'create-campaign', 'sort_order' => 2],
                    ['name' => 'Email Templates', 'route_name' => 'admin.campaign-email-templates.index', 'slug' => 'email-templates', 'sort_order' => 3],
                    ['name' => 'Pamphlets', 'route_name' => 'admin.campaign-pamphlets.index', 'slug' => 'pamphlets', 'sort_order' => 4],
                    ['name' => 'Email Logs', 'route_name' => 'admin.email-logs.index', 'slug' => 'email-logs', 'sort_order' => 5],
                    ['name' => 'Daily Notifications', 'route_name' => 'admin.daily-notifications.index', 'slug' => 'daily-notifications', 'sort_order' => 6],
                    ['name' => 'Notification Dashboard', 'route_name' => 'admin.notifications.dashboard', 'slug' => 'notification-dashboard', 'sort_order' => 7],
                    ['name' => 'Push Tokens', 'route_name' => 'admin.notifications.push-tokens', 'slug' => 'push-tokens', 'sort_order' => 8],
                ],
            ],
            [
                'name' => 'Pending Requests',
                'slug' => 'pending-requests',
                'icon' => 'bi-hourglass-split',
                'sort_order' => 9,
                'pages' => [
                    ['name' => 'Visitor Registrations', 'route_name' => 'admin.visitor-registrations.index', 'slug' => 'visitor-registrations', 'sort_order' => 1],
                    ['name' => 'Pending Registrations', 'route_name' => 'admin.pending-registrations.index', 'slug' => 'pending-registrations', 'sort_order' => 2],
                    ['name' => 'Certifications', 'route_name' => 'admin.certifications.index', 'slug' => 'certifications', 'sort_order' => 3],
                    ['name' => 'Account Deletion Requests', 'route_name' => 'admin.account-deletion.index', 'slug' => 'account-deletion', 'sort_order' => 4],
                    ['name' => 'Introduction Requests', 'route_name' => 'admin.introduction-requests.index', 'slug' => 'introduction-requests', 'sort_order' => 5],
                ],
            ],
            [
                'name' => 'Referral Report',
                'slug' => 'referral-report',
                'icon' => 'bi-person-lines-fill',
                'sort_order' => 10,
                'pages' => [
                    ['name' => 'Referral Report', 'route_name' => 'admin.referral-report.index', 'slug' => 'referral-report-index', 'sort_order' => 1],
                    ['name' => 'Export Referral Report', 'route_name' => 'admin.referral-report.export', 'slug' => 'referral-report-export', 'sort_order' => 2],
                ],
            ],
            [
                'name' => 'Content & Posts',
                'slug' => 'content',
                'icon' => 'bi-file-post',
                'sort_order' => 11,
                'pages' => [
                    ['name' => 'All Posts', 'route_name' => 'admin.posts.index', 'slug' => 'all-posts', 'sort_order' => 1],
                    ['name' => 'Post Reports', 'route_name' => 'admin.post-reports.index', 'slug' => 'post-reports', 'sort_order' => 2],
                    ['name' => 'Circulars', 'route_name' => 'admin.circulars.index', 'slug' => 'circulars', 'sort_order' => 3],
                ],
            ],
            [
                'name' => 'Lead Submissions',
                'slug' => 'leads',
                'icon' => 'bi-clipboard-data',
                'sort_order' => 12,
                'pages' => [
                    ['name' => 'Entrepreneur Certification', 'route_name' => 'admin.leads.entrepreneur-certification.index', 'slug' => 'entrepreneur-cert', 'sort_order' => 1],
                    ['name' => 'Leadership Certification', 'route_name' => 'admin.leads.leadership-certification.index', 'slug' => 'leadership-cert', 'sort_order' => 2],
                    ['name' => 'Partner With Us', 'route_name' => 'admin.leads.partner-with-us.index', 'slug' => 'partner-with-us', 'sort_order' => 3],
                    ['name' => 'Become Speaker', 'route_name' => 'admin.leads.become-speaker.index', 'slug' => 'become-speaker', 'sort_order' => 4],
                    ['name' => 'Become Mentor', 'route_name' => 'admin.leads.become-mentor.index', 'slug' => 'become-mentor', 'sort_order' => 5],
                    ['name' => 'Story Submissions', 'route_name' => 'admin.stories.index', 'slug' => 'story-submissions', 'sort_order' => 6],
                ],
            ],
            [
                'name' => 'Industries',
                'slug' => 'industries',
                'icon' => 'bi-diagram-2',
                'sort_order' => 13,
                'pages' => [
                    ['name' => 'Industries Overview', 'route_name' => 'admin.execution.industries', 'slug' => 'industries-overview', 'sort_order' => 1],
                    ['name' => 'DED Industries', 'route_name' => 'admin.ded.dashboard.industries', 'slug' => 'ded-industries', 'sort_order' => 2],
                ],
            ],
            [
                'name' => 'Settings',
                'slug' => 'settings',
                'icon' => 'bi-gear',
                'sort_order' => 14,
                'pages' => [
                    ['name' => 'App Config', 'route_name' => 'admin.app-config.index', 'slug' => 'app-config', 'sort_order' => 1],
                    ['name' => 'App Updates', 'route_name' => 'admin.app-updates.index', 'slug' => 'app-updates', 'sort_order' => 2],
                    ['name' => 'Birthday Creative', 'route_name' => 'admin.birthday-creative.index', 'slug' => 'birthday-creative', 'sort_order' => 3],
                    ['name' => 'Anniversary Creatives', 'route_name' => 'admin.anniversary-creatives.index', 'slug' => 'anniversary-creatives', 'sort_order' => 4],
                    ['name' => 'Tutorials', 'route_name' => 'admin.tutorials.index', 'slug' => 'tutorials', 'sort_order' => 5],
                    ['name' => 'Categories', 'route_name' => 'admin.categories.index', 'slug' => 'categories', 'sort_order' => 6],
                    ['name' => 'Unity Peers Plans', 'route_name' => 'admin.unity-peers-plans.index', 'slug' => 'unity-peers-plans', 'sort_order' => 7],
                    ['name' => 'Unity Contacts', 'route_name' => 'admin.contacts.index', 'slug' => 'unity-contacts', 'sort_order' => 8],
                    ['name' => 'Support Tickets', 'route_name' => 'admin.support-tickets.index', 'slug' => 'support-tickets', 'sort_order' => 9],
                ],
            ],
            [
                'name' => 'Role Management',
                'slug' => 'role-management',
                'icon' => 'bi-shield-lock',
                'sort_order' => 15,
                'pages' => [
                    ['name' => 'Role Hierarchy', 'route_name' => 'admin.rbac.hierarchy', 'slug' => 'role-hierarchy', 'sort_order' => 1],
                    ['name' => 'RBAC Modules', 'route_name' => 'admin.rbac.modules.index', 'slug' => 'rbac-modules', 'sort_order' => 2],
                    ['name' => 'RBAC Pages', 'route_name' => 'admin.rbac.pages.index', 'slug' => 'rbac-pages', 'sort_order' => 3],
                    ['name' => 'Permission Matrix', 'route_name' => 'admin.rbac.permission-matrix.index', 'slug' => 'permission-matrix', 'sort_order' => 4],
                    ['name' => 'Module Access', 'route_name' => 'admin.rbac.module-access.index', 'slug' => 'module-access', 'sort_order' => 5],
                    ['name' => 'Page Groups', 'route_name' => 'admin.rbac.page-groups.index', 'slug' => 'page-groups', 'sort_order' => 6],
                    ['name' => 'Data Scope', 'route_name' => 'admin.rbac.data-scope.index', 'slug' => 'data-scope', 'sort_order' => 7],
                    ['name' => 'Workflow Rules', 'route_name' => 'admin.rbac.workflow-rules.index', 'slug' => 'workflow-rules', 'sort_order' => 8],
                ],
            ],
            [
                'name' => 'Brand Partners',
                'slug' => 'brand-partners',
                'icon' => 'bi-shop',
                'sort_order' => 16,
                'pages' => [
                    ['name' => 'All Brand Partners', 'route_name' => 'admin.brand-partners.index', 'slug' => 'all-brand-partners', 'sort_order' => 1],
                    ['name' => 'Brand Partner Categories', 'route_name' => 'admin.brand-partner-categories.index', 'slug' => 'brand-partner-categories', 'sort_order' => 2],
                    ['name' => 'Brand Partner Analytics', 'route_name' => 'admin.brand-partner-analytics.index', 'slug' => 'brand-partner-analytics', 'sort_order' => 3],
                    ['name' => 'Ads', 'route_name' => 'admin.ads.index', 'slug' => 'ads', 'sort_order' => 4],
                ],
            ],
        ];
    }
}
