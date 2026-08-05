<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AdminModule;
use App\Models\AdminPage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleModuleAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermissions();
        $this->seedModules();
        $this->call(RoleHierarchySeeder::class);
        $this->seedModuleAccess();
    }

    // -------------------------------------------------------------------------
    // Permissions
    // -------------------------------------------------------------------------

    private function seedPermissions(): void
    {
        $permissions = [
            ['name' => 'View',    'key' => 'view',    'description' => 'View / read records',             'sort_order' => 1],
            ['name' => 'Create',  'key' => 'create',  'description' => 'Create new records',              'sort_order' => 2],
            ['name' => 'Edit',    'key' => 'edit',    'description' => 'Edit existing records',           'sort_order' => 3],
            ['name' => 'Delete',  'key' => 'delete',  'description' => 'Delete records',                  'sort_order' => 4],
            ['name' => 'Approve', 'key' => 'approve', 'description' => 'Approve pending items',           'sort_order' => 5],
            ['name' => 'Reject',  'key' => 'reject',  'description' => 'Reject pending items',            'sort_order' => 6],
            ['name' => 'Export',  'key' => 'export',  'description' => 'Export records to CSV / Excel',   'sort_order' => 7],
            ['name' => 'Import',  'key' => 'import',  'description' => 'Import records from CSV / Excel', 'sort_order' => 8],
            ['name' => 'Print',   'key' => 'print',   'description' => 'Print reports',                   'sort_order' => 9],
            ['name' => 'Restore', 'key' => 'restore', 'description' => 'Restore soft-deleted records',   'sort_order' => 10],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['key' => $perm['key']], $perm);
        }

        $this->command->info('✅ Permissions seeded.');
    }

    // -------------------------------------------------------------------------
    // Modules and their default pages
    // -------------------------------------------------------------------------

    private function seedModules(): void
    {
        $modules = [
            [
                'name' => 'Dashboard', 'slug' => 'dashboard', 'icon' => 'bi-speedometer2', 'sort_order' => 1,
                'pages' => [
                    ['page_name' => 'Overview',          'route_name' => 'admin.dashboard',                'sort_order' => 1],
                ],
            ],
            [
                'name' => 'RBAC & Role Hierarchy', 'slug' => 'rbac', 'icon' => 'bi-diagram-3', 'sort_order' => 2,
                'pages' => [
                    ['page_name' => 'Modules',           'route_name' => 'admin.rbac.modules.index',      'sort_order' => 1],
                    ['page_name' => 'Pages',             'route_name' => 'admin.rbac.pages.index',        'sort_order' => 2],
                    ['page_name' => 'Permissions',       'route_name' => 'admin.rbac.permissions.index',  'sort_order' => 3],
                    ['page_name' => 'Page Groups',       'route_name' => 'admin.rbac.page-groups.index',  'sort_order' => 4],
                    ['page_name' => 'Permission Matrix', 'route_name' => 'admin.rbac.matrix.index',       'sort_order' => 5],
                    ['page_name' => 'Module Access',     'route_name' => 'admin.rbac.module-access.index', 'sort_order' => 6],
                    ['page_name' => 'Page Group Access', 'route_name' => 'admin.rbac.page-group-access.index', 'sort_order' => 7],
                    ['page_name' => 'Data Scope',        'route_name' => 'admin.rbac.data-scope.index',  'sort_order' => 8],
                    ['page_name' => 'Workflow Rules',    'route_name' => 'admin.rbac.workflow-rules.index', 'sort_order' => 9],
                ],
            ],
            [
                'name' => 'Activities', 'slug' => 'activities', 'icon' => 'bi-activity', 'sort_order' => 3,
                'pages' => [
                    ['page_name' => 'All Activities',    'route_name' => 'admin.activities.index',        'sort_order' => 1],
                    ['page_name' => 'Business Deals',    'route_name' => 'admin.activities.business-deals.index', 'sort_order' => 2],
                    ['page_name' => 'Referrals',         'route_name' => 'admin.activities.referrals.index', 'sort_order' => 3],
                    ['page_name' => 'Testimonials',      'route_name' => 'admin.activities.testimonials.index', 'sort_order' => 4],
                    ['page_name' => 'Visitor Leads',     'route_name' => 'admin.activities.visitor-registrations.index', 'sort_order' => 5],
                ],
            ],
            [
                'name' => 'Referral Report', 'slug' => 'referral_report', 'icon' => 'bi-person-lines-fill', 'sort_order' => 4,
                'pages' => [
                    ['page_name' => 'Referral Reports',  'route_name' => 'admin.referral-reports.index',  'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Posts & Timeline', 'slug' => 'posts_timeline', 'icon' => 'bi-chat-dots', 'sort_order' => 5,
                'pages' => [
                    ['page_name' => 'All Posts',         'route_name' => 'admin.posts.index',             'sort_order' => 1],
                    ['page_name' => 'Post Reports',      'route_name' => 'admin.post-reports.index',      'sort_order' => 2],
                ],
            ],
            [
                'name' => 'Pending Requests', 'slug' => 'pending_requests', 'icon' => 'bi-hourglass-split', 'sort_order' => 6,
                'pages' => [
                    ['page_name' => 'Circle Joining Requests', 'route_name' => 'admin.circle-joining-requests.index', 'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Support Tickets', 'slug' => 'support_tickets', 'icon' => 'bi-ticket-perforated', 'sort_order' => 7,
                'pages' => [
                    ['page_name' => 'Tickets',           'route_name' => 'admin.support-tickets.index',   'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Events Management', 'slug' => 'events_management', 'icon' => 'bi-calendar-check', 'sort_order' => 8,
                'pages' => [
                    ['page_name' => 'All Events',        'route_name' => 'admin.events.index',            'sort_order' => 1],
                    ['page_name' => 'Event Gallery',     'route_name' => 'admin.event-gallery.index',     'sort_order' => 2],
                ],
            ],
            [
                'name' => 'Brand Partners', 'slug' => 'brand_partners', 'icon' => 'bi-briefcase', 'sort_order' => 9,
                'pages' => [
                    ['page_name' => 'Partners Overview', 'route_name' => 'admin.brand-partners.index',    'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Ads', 'slug' => 'ads', 'icon' => 'bi-megaphone', 'sort_order' => 10,
                'pages' => [
                    ['page_name' => 'All Ads',           'route_name' => 'admin.ads.index',               'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Peers', 'slug' => 'peers', 'icon' => 'bi-people', 'sort_order' => 11,
                'pages' => [
                    ['page_name' => 'All Members',       'route_name' => 'admin.users.index',              'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Member Introducers', 'slug' => 'member_introducers', 'icon' => 'bi-person-check', 'sort_order' => 12,
                'pages' => [
                    ['page_name' => 'Introducers List',  'route_name' => 'admin.member-introducers.index', 'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Sponsored Member Milestone Awards', 'slug' => 'sponsored_milestones', 'icon' => 'bi-trophy', 'sort_order' => 13,
                'pages' => [
                    ['page_name' => 'Milestones',        'route_name' => 'admin.sponsored-milestones.index', 'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Unity Contacts', 'slug' => 'unity_contacts', 'icon' => 'bi-person-lines-fill', 'sort_order' => 14,
                'pages' => [
                    ['page_name' => 'Contacts',          'route_name' => 'admin.contacts.index',          'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Industries', 'slug' => 'industries', 'icon' => 'bi-diagram-2', 'sort_order' => 15,
                'pages' => [
                    ['page_name' => 'Industries List',   'route_name' => 'admin.execution.industries',    'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Login History', 'slug' => 'login_history', 'icon' => 'bi-clock-history', 'sort_order' => 16,
                'pages' => [
                    ['page_name' => 'Login Logs',        'route_name' => 'admin.login-history.index',     'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Circles', 'slug' => 'circles', 'icon' => 'bi-diagram-3', 'sort_order' => 17,
                'pages' => [
                    ['page_name' => 'All Circles',       'route_name' => 'admin.circles.index',           'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Circulars', 'slug' => 'circulars', 'icon' => 'bi-megaphone', 'sort_order' => 18,
                'pages' => [
                    ['page_name' => 'All Circulars',     'route_name' => 'admin.circulars.index',         'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Coins', 'slug' => 'coins', 'icon' => 'bi-coin', 'sort_order' => 19,
                'pages' => [
                    ['page_name' => 'Coins Overview',    'route_name' => 'admin.coins.index',             'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Life Impact', 'slug' => 'life_impact', 'icon' => 'bi-heart-pulse', 'sort_order' => 20,
                'pages' => [
                    ['page_name' => 'Life Impact Log',   'route_name' => 'admin.life-impact.index',       'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Notifications & Email', 'slug' => 'notifications_email', 'icon' => 'bi-bell', 'sort_order' => 21,
                'pages' => [
                    ['page_name' => 'Notification Admin', 'route_name' => 'admin.notifications.index',     'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Circle Categories', 'slug' => 'circle_categories', 'icon' => 'bi-tags', 'sort_order' => 22,
                'pages' => [
                    ['page_name' => 'Categories',        'route_name' => 'admin.categories.index',        'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Impact Option', 'slug' => 'impact_option', 'icon' => 'bi-lightning-charge', 'sort_order' => 23,
                'pages' => [
                    ['page_name' => 'Impact Options List', 'route_name' => 'admin.impacts.index',         'sort_order' => 1],
                ],
            ],
            [
                'name' => 'App Configuration', 'slug' => 'app_config', 'icon' => 'bi-sliders', 'sort_order' => 24,
                'pages' => [
                    ['page_name' => 'App Config',        'route_name' => 'admin.app-config.index',        'sort_order' => 1],
                ],
            ],
            [
                'name' => 'App Updates Manager', 'slug' => 'app_updates', 'icon' => 'bi-arrow-up-circle', 'sort_order' => 25,
                'pages' => [
                    ['page_name' => 'App Updates',       'route_name' => 'admin.app-updates.index',       'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Birthday Creative', 'slug' => 'birthday_creative', 'icon' => 'bi-gift', 'sort_order' => 26,
                'pages' => [
                    ['page_name' => 'Birthday Creatives', 'route_name' => 'admin.birthday-creative.index', 'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Anniversary Creative', 'slug' => 'anniversary_creative', 'icon' => 'bi-images', 'sort_order' => 27,
                'pages' => [
                    ['page_name' => 'Anniversary Creatives', 'route_name' => 'admin.anniversary-creatives.index', 'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Tutorials', 'slug' => 'tutorials', 'icon' => 'bi-play-btn', 'sort_order' => 28,
                'pages' => [
                    ['page_name' => 'Tutorial Videos',   'route_name' => 'admin.tutorials.index',         'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Leads', 'slug' => 'leads', 'icon' => 'bi-person-lines-fill', 'sort_order' => 29,
                'pages' => [
                    ['page_name' => 'Leads List',        'route_name' => 'admin.stories.index',           'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Email Logs', 'slug' => 'email_logs', 'icon' => 'bi-envelope-paper', 'sort_order' => 30,
                'pages' => [
                    ['page_name' => 'Email Logs',        'route_name' => 'admin.email-logs.index',        'sort_order' => 1],
                ],
            ],
        ];

        foreach ($modules as $moduleData) {
            $pages = $moduleData['pages'];
            unset($moduleData['pages']);

            $module = AdminModule::firstOrCreate(
                ['slug' => $moduleData['slug']],
                array_merge($moduleData, ['id' => (string) Str::uuid()])
            );

            foreach ($pages as $pageData) {
                AdminPage::firstOrCreate(
                    ['route_name' => $pageData['route_name']],
                    array_merge($pageData, [
                        'id' => (string) Str::uuid(),
                        'module_id' => $module->id,
                    ])
                );
            }
        }

        $this->command->info('✅ Modules and pages seeded.');
    }

    private function seedModuleAccess(): void
    {
        $roles = Role::all();
        $modules = AdminModule::all();

        foreach ($roles as $role) {
            foreach ($modules as $module) {
                RoleModuleAccess::firstOrCreate(
                    [
                        'role_id' => $role->id,
                        'module_id' => $module->id,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'is_visible' => true,
                    ]
                );
            }
        }

        $this->command->info('✅ Default module access seeded.');
    }
}
