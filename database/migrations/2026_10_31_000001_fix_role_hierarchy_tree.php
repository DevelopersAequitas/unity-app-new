<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\RoleHierarchy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $standardRoles = [
            'global_admin'                       => ['name' => 'Global Admin',                        'type' => 'admin', 'scope' => 'not_applicable'],
            'eed'                                => ['name' => 'EED',                                 'type' => 'admin', 'scope' => 'mandatory'],
            'ded'                                => ['name' => 'DED',                                 'type' => 'admin', 'scope' => 'mandatory'],
            'industry_director'                  => ['name' => 'Industry Director',                   'type' => 'admin', 'scope' => 'optional'],
            'circle_leader'                      => ['name' => 'Circle Leader',                       'type' => 'admin', 'scope' => 'mandatory'],
            'circle_director'                    => ['name' => 'Circle Director',                     'type' => 'admin', 'scope' => 'mandatory'],
            'circle_founder'                     => ['name' => 'Circle Founder',                      'type' => 'admin', 'scope' => 'mandatory'],
            'chair'                              => ['name' => 'Circle Chair',                        'type' => 'admin', 'scope' => 'mandatory'],
            'vice_chair'                         => ['name' => 'Vice Chair',                          'type' => 'admin', 'scope' => 'mandatory'],
            'secretary'                          => ['name' => 'Secretary',                           'type' => 'admin', 'scope' => 'mandatory'],
            'committee_leader'                   => ['name' => 'Committee Leader',                    'type' => 'admin', 'scope' => 'mandatory'],
            'marketing_team'                     => ['name' => 'Marketing Team',                      'type' => 'admin', 'scope' => 'optional'],
            'analytics_team'                     => ['name' => 'Analytics Team',                      'type' => 'admin', 'scope' => 'optional'],
            'content_team'                       => ['name' => 'Content Team',                        'type' => 'admin', 'scope' => 'optional'],
            'read_only'                          => ['name' => 'Read Only Staff',                     'type' => 'admin', 'scope' => 'optional'],
            'member'                             => ['name' => 'Circle Member',                       'type' => 'user',  'scope' => 'mandatory'],
            'chair_business_growth_committee'    => ['name' => 'Chair - Business Growth Committee',   'type' => 'user',  'scope' => 'mandatory'],
            'chair_membership_growth_committee'  => ['name' => 'Chair - Membership Growth Committee', 'type' => 'user',  'scope' => 'mandatory'],
            'chair_events_impacts_committee'     => ['name' => 'Chair - Events & Impacts Committee',  'type' => 'user',  'scope' => 'mandatory'],
        ];

        DB::transaction(function () use ($standardRoles): void {
            foreach ($standardRoles as $key => $meta) {
                Role::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'name'          => $meta['name'],
                        'role_type'     => $meta['type'],
                        'scope_rule'    => $meta['scope'],
                        'status'        => 'active',
                        'is_assignable' => true,
                        'role_code'     => $key,
                    ]
                );
            }

            Role::query()->whereNull('status')->orWhere('status', '')->update(['status' => 'active']);

            $roles = Role::all()->keyBy('key');

            $gAdmin     = $roles->get('global_admin')?->id;
            $eed        = $roles->get('eed')?->id;
            $ded        = $roles->get('ded')?->id;
            $indDir     = $roles->get('industry_director')?->id;
            $cLeader    = $roles->get('circle_leader')?->id;
            $cDirector  = $roles->get('circle_director')?->id;
            $cFounder   = $roles->get('circle_founder')?->id;
            $chair      = $roles->get('chair')?->id;
            $vChair     = $roles->get('vice_chair')?->id;
            $secretary  = $roles->get('secretary')?->id;
            $commLeader = $roles->get('committee_leader')?->id;
            $marketing  = $roles->get('marketing_team')?->id;
            $analytics  = $roles->get('analytics_team')?->id;
            $content    = $roles->get('content_team')?->id;
            $readOnly   = $roles->get('read_only')?->id;
            $member     = $roles->get('member')?->id;
            $bgcChair   = $roles->get('chair_business_growth_committee')?->id;
            $mgcChair   = $roles->get('chair_membership_growth_committee')?->id;
            $eicChair   = $roles->get('chair_events_impacts_committee')?->id;

            DB::table('role_hierarchies')->truncate();

            $links = [
                ['parent' => $gAdmin,     'child' => $eed],
                ['parent' => $gAdmin,     'child' => $bgcChair],
                ['parent' => $gAdmin,     'child' => $mgcChair],
                ['parent' => $gAdmin,     'child' => $eicChair],

                ['parent' => $eed,        'child' => $ded],

                ['parent' => $ded,        'child' => $indDir],

                ['parent' => $indDir,     'child' => $cLeader],

                ['parent' => $cLeader,    'child' => $cDirector],
                ['parent' => $cLeader,    'child' => $cFounder],
                ['parent' => $cLeader,    'child' => $chair],
                ['parent' => $cLeader,    'child' => $member],

                ['parent' => $chair,      'child' => $vChair],
                ['parent' => $chair,      'child' => $secretary],

                ['parent' => $vChair,     'child' => $commLeader],

                ['parent' => $commLeader, 'child' => $marketing],
                ['parent' => $commLeader, 'child' => $analytics],
                ['parent' => $commLeader, 'child' => $content],
                ['parent' => $commLeader, 'child' => $readOnly],
            ];

            foreach ($links as $link) {
                if ($link['parent'] && $link['child']) {
                    RoleHierarchy::create([
                        'id'             => (string) Str::uuid(),
                        'parent_role_id' => $link['parent'],
                        'child_role_id'  => $link['child'],
                    ]);
                }
            }

            foreach (Role::all() as $r) {
                $r->recomputeDepth();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('role_hierarchies')->truncate();
    }
};
