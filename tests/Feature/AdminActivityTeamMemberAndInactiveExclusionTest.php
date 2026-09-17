<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\BusinessDeal;
use App\Models\Impact;
use App\Models\LifeImpactHistory;
use App\Models\P2pMeeting;
use App\Models\Referral;
use App\Models\Requirement;
use App\Models\Role;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\UserTag;
use App\Support\ActivityUserFilter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminActivityTeamMemberAndInactiveExclusionTest extends TestCase
{
    private AdminUser $admin;

    private User $activeNormalUser;

    private User $inactiveUser;

    private User $teamMemberUser;

    private UserTag $teamMemberTag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        $this->createSchema();
        $this->seedTestData();
    }

    protected function createSchema(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $pdo = DB::connection()->getPdo();
            $pdo->sqliteCreateFunction('concat_ws', function (...$args) {
                $separator = array_shift($args);
                $nonNull = array_filter($args, fn ($v) => ! is_null($v) && $v !== '');

                return implode($separator, $nonNull);
            });
            $pdo->sqliteCreateFunction('concat', function (...$args) {
                return implode('', $args);
            });
            $pdo->sqliteCreateFunction('year', function ($date) {
                return $date ? date('Y', strtotime($date)) : null;
            });
            $pdo->sqliteCreateFunction('month', function ($date) {
                return $date ? date('n', strtotime($date)) : null;
            });
            $pdo->sqliteCreateFunction('monthname', function ($date) {
                return $date ? date('F', strtotime($date)) : null;
            });
            $pdo->sqliteCreateFunction('date_format', function ($date, $format) {
                return $date ? date('Y-m-d', strtotime($date)) : null;
            });
            $pdo->sqliteCreateFunction('ifnull', function ($val, $default) {
                return $val ?? $default;
            });
        }

        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('user_tag_assignments');
        Schema::dropIfExists('user_tags');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('requirements');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('p2p_meetings');
        Schema::dropIfExists('business_deals');
        Schema::dropIfExists('connections');
        Schema::dropIfExists('leader_interest_submissions');
        Schema::dropIfExists('peer_recommendations');
        Schema::dropIfExists('visitor_registrations');
        Schema::dropIfExists('life_impact_histories');
        Schema::dropIfExists('impacts');
        Schema::dropIfExists('coins_ledger');
        Schema::dropIfExists('referraldata');
        Schema::dropIfExists('circle_members');
        Schema::dropIfExists('circles');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('files');
        Schema::dropIfExists('admin_user_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('admin_users');
        Schema::dropIfExists('users');

        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('key')->unique();
            $table->timestamps();
        });

        Schema::create('admin_user_roles', function (Blueprint $table): void {
            $table->uuid('user_id');
            $table->uuid('role_id');
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('uploader_user_id')->nullable();
            $table->string('s3_key')->nullable();
            $table->string('mime_type')->nullable();
            $table->integer('size_bytes')->nullable();
            $table->boolean('is_orphaned')->default(false);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('designation')->nullable();
            $table->string('business_type')->nullable();
            $table->string('city')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->integer('coins_balance')->default(0);
            $table->integer('life_impacted_count')->default(0);
            $table->string('status')->default('active');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_tag_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('user_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();
            $table->unique(['user_id', 'tag_id']);
        });

        Schema::create('admin_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->id();
            $table->uuid('circle_id');
            $table->uuid('user_id');
            $table->string('role')->default('member');
            $table->string('status')->default('approved');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('testimonials', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('from_user_id');
            $table->uuid('to_user_id');
            $table->text('content')->nullable();
            $table->json('media')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('requirements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('subject')->nullable();
            $table->text('description')->nullable();
            $table->json('media')->nullable();
            $table->json('region_filter')->nullable();
            $table->json('category_filter')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('referrals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('from_user_id');
            $table->uuid('to_user_id')->nullable();
            $table->string('referral_type')->nullable();
            $table->string('referral_of')->nullable();
            $table->date('referral_date')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('hot_value')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('p2p_meetings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('initiator_user_id');
            $table->uuid('peer_user_id');
            $table->date('meeting_date')->nullable();
            $table->string('meeting_place')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('business_deals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('from_user_id');
            $table->uuid('to_user_id');
            $table->date('deal_date')->nullable();
            $table->decimal('deal_amount', 12, 2)->default(0);
            $table->string('business_type')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('connections', function (Blueprint $table): void {
            $table->uuid('requester_id');
            $table->uuid('addressee_id');
            $table->boolean('is_approved')->default(true);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('leader_interest_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('applying_for')->nullable();
            $table->string('referred_name')->nullable();
            $table->string('referred_mobile')->nullable();
            $table->json('leadership_roles')->nullable();
            $table->string('contribute_city')->nullable();
            $table->string('primary_domain')->nullable();
            $table->text('why_interested')->nullable();
            $table->timestamps();
        });

        Schema::create('peer_recommendations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('peer_name')->nullable();
            $table->string('peer_mobile')->nullable();
            $table->string('how_well_known')->nullable();
            $table->boolean('is_aware')->default(false);
            $table->integer('coins_awarded')->default(0);
            $table->timestamps();
        });

        Schema::create('visitor_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('event_type')->nullable();
            $table->string('event_name')->nullable();
            $table->date('event_date')->nullable();
            $table->string('visitor_full_name')->nullable();
            $table->string('visitor_mobile')->nullable();
            $table->string('visitor_city')->nullable();
            $table->string('visitor_business')->nullable();
            $table->string('status')->nullable();
            $table->integer('coins_awarded')->default(0);
            $table->timestamps();
        });

        Schema::create('life_impact_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('activity_type')->nullable();
            $table->string('impact_category')->nullable();
            $table->string('action_key')->nullable();
            $table->string('action_label')->nullable();
            $table->string('title')->nullable();
            $table->integer('impact_value')->default(1);
            $table->integer('life_impacted')->default(1);
            $table->boolean('counted_in_total')->default(true);
            $table->string('status')->default('approved');
            $table->timestamps();
        });

        Schema::create('impacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('impacted_peer_id')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->string('action')->nullable();
            $table->text('story_to_share')->nullable();
            $table->date('impact_date')->nullable();
            $table->string('status')->default('approved');
            $table->timestamps();
        });

        Schema::create('coins_ledger', function (Blueprint $table): void {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->uuid('user_id');
            $table->integer('amount')->default(0);
            $table->integer('balance_after')->default(0);
            $table->uuid('activity_id')->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();
        });

        Schema::create('referraldata', function (Blueprint $table): void {
            $table->id();
            $table->uuid('referrer_user_id');
            $table->uuid('referred_user_id');
            $table->string('referral_code')->nullable();
            $table->integer('coins_granted')->default(0);
            $table->string('reward_status')->default('granted');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    protected function seedTestData(): void
    {
        $this->admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
        ]);

        $role = Role::create([
            'id' => (string) Str::uuid(),
            'name' => 'Global Admin',
            'key' => 'global_admin',
        ]);
        $this->admin->roles()->attach($role->id);

        $this->teamMemberTag = UserTag::create([
            'name' => 'Team Member',
            'slug' => 'team_member',
            'description' => 'Internal team member excluded from activities and leaderboards',
            'is_active' => true,
        ]);

        $this->activeNormalUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Active',
            'last_name' => 'Member',
            'display_name' => 'Active Member',
            'email' => 'active@example.com',
            'phone' => '1111111111',
            'company_name' => 'Active Corp',
            'city' => 'Ahmedabad',
            'coins_balance' => 500,
            'life_impacted_count' => 10,
            'status' => 'active',
        ]);

        $this->inactiveUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Inactive',
            'last_name' => 'User',
            'display_name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'phone' => '2222222222',
            'company_name' => 'Inactive LLC',
            'city' => 'Surat',
            'coins_balance' => 300,
            'life_impacted_count' => 5,
            'status' => 'inactive',
        ]);

        $this->teamMemberUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Team',
            'last_name' => 'Member',
            'display_name' => 'Team Member User',
            'email' => 'team@example.com',
            'phone' => '3333333333',
            'company_name' => 'Unity Team',
            'city' => 'Vadodara',
            'coins_balance' => 9999,
            'life_impacted_count' => 100,
            'status' => 'active',
        ]);

        $this->teamMemberUser->assignTag($this->teamMemberTag);

        $receiverUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Receiver',
            'last_name' => 'User',
            'display_name' => 'Receiver User',
            'email' => 'receiver@example.com',
            'coins_balance' => 100,
            'status' => 'active',
        ]);

        // Seed activities for each
        // 1. Testimonials
        Testimonial::create(['id' => (string) Str::uuid(), 'from_user_id' => $this->activeNormalUser->id, 'to_user_id' => $receiverUser->id, 'content' => 'Active testimonial']);
        Testimonial::create(['id' => (string) Str::uuid(), 'from_user_id' => $this->inactiveUser->id, 'to_user_id' => $receiverUser->id, 'content' => 'Inactive testimonial']);
        Testimonial::create(['id' => (string) Str::uuid(), 'from_user_id' => $this->teamMemberUser->id, 'to_user_id' => $receiverUser->id, 'content' => 'Team member testimonial']);

        // 2. Requirements
        Requirement::create(['id' => (string) Str::uuid(), 'user_id' => $this->activeNormalUser->id, 'subject' => 'Active requirement', 'status' => 'open']);
        Requirement::create(['id' => (string) Str::uuid(), 'user_id' => $this->inactiveUser->id, 'subject' => 'Inactive requirement', 'status' => 'open']);
        Requirement::create(['id' => (string) Str::uuid(), 'user_id' => $this->teamMemberUser->id, 'subject' => 'Team requirement', 'status' => 'open']);

        // 3. Referrals
        Referral::create(['id' => (string) Str::uuid(), 'from_user_id' => $this->activeNormalUser->id, 'to_user_id' => $receiverUser->id, 'referral_of' => 'Active Referral']);
        Referral::create(['id' => (string) Str::uuid(), 'from_user_id' => $this->inactiveUser->id, 'to_user_id' => $receiverUser->id, 'referral_of' => 'Inactive Referral']);
        Referral::create(['id' => (string) Str::uuid(), 'from_user_id' => $this->teamMemberUser->id, 'to_user_id' => $receiverUser->id, 'referral_of' => 'Team Referral']);

        // 4. P2P Meetings
        P2pMeeting::create(['id' => (string) Str::uuid(), 'initiator_user_id' => $this->activeNormalUser->id, 'peer_user_id' => $receiverUser->id, 'meeting_date' => '2026-09-01']);
        P2pMeeting::create(['id' => (string) Str::uuid(), 'initiator_user_id' => $this->inactiveUser->id, 'peer_user_id' => $receiverUser->id, 'meeting_date' => '2026-09-01']);
        P2pMeeting::create(['id' => (string) Str::uuid(), 'initiator_user_id' => $this->teamMemberUser->id, 'peer_user_id' => $receiverUser->id, 'meeting_date' => '2026-09-01']);

        // 5. Business Deals
        BusinessDeal::create(['id' => (string) Str::uuid(), 'from_user_id' => $this->activeNormalUser->id, 'to_user_id' => $receiverUser->id, 'deal_amount' => 50000]);
        BusinessDeal::create(['id' => (string) Str::uuid(), 'from_user_id' => $this->inactiveUser->id, 'to_user_id' => $receiverUser->id, 'deal_amount' => 30000]);
        BusinessDeal::create(['id' => (string) Str::uuid(), 'from_user_id' => $this->teamMemberUser->id, 'to_user_id' => $receiverUser->id, 'deal_amount' => 100000]);

        // 6. Impacts
        Impact::create(['id' => (string) Str::uuid(), 'user_id' => $this->activeNormalUser->id, 'action' => 'Active impact', 'status' => 'approved']);
        Impact::create(['id' => (string) Str::uuid(), 'user_id' => $this->inactiveUser->id, 'action' => 'Inactive impact', 'status' => 'approved']);
        Impact::create(['id' => (string) Str::uuid(), 'user_id' => $this->teamMemberUser->id, 'action' => 'Team impact', 'status' => 'approved']);

        // 7. Life Impact History
        LifeImpactHistory::create(['id' => (string) Str::uuid(), 'user_id' => $this->activeNormalUser->id, 'impact_category' => 'referrals', 'impact_value' => 1, 'life_impacted' => 1, 'status' => 'approved']);
        LifeImpactHistory::create(['id' => (string) Str::uuid(), 'user_id' => $this->inactiveUser->id, 'impact_category' => 'referrals', 'impact_value' => 1, 'life_impacted' => 1, 'status' => 'approved']);
        LifeImpactHistory::create(['id' => (string) Str::uuid(), 'user_id' => $this->teamMemberUser->id, 'impact_category' => 'referrals', 'impact_value' => 1, 'life_impacted' => 1, 'status' => 'approved']);
    }

    public function test_activity_user_filter_helper_checks_correctly(): void
    {
        $this->assertTrue(ActivityUserFilter::isEligibleUser($this->activeNormalUser));
        $this->assertFalse(ActivityUserFilter::isEligibleUser($this->inactiveUser));
        $this->assertFalse(ActivityUserFilter::isEligibleUser($this->teamMemberUser));
    }

    public function test_testimonials_admin_list_and_total_exclude_inactive_and_team_members(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.activities.testimonials.index'));

        $response->assertOk();
        $items = $response->viewData('items');
        $total = $response->viewData('total');

        // Only 1 testimonial from activeNormalUser should be included
        $this->assertEquals(1, $total);
        $this->assertEquals(1, $items->count());
        $this->assertEquals($this->activeNormalUser->display_name, $items->first()->from_user_name);
    }

    public function test_requirements_admin_list_and_total_exclude_inactive_and_team_members(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.activities.requirements.index'));

        $response->assertOk();
        $items = $response->viewData('items');
        $total = $response->viewData('total');

        $this->assertEquals(1, $total);
        $this->assertEquals(1, $items->count());
        $this->assertEquals('Active requirement', $items->first()->subject);
    }

    public function test_referrals_admin_list_and_total_exclude_inactive_and_team_members(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.activities.referrals.index'));

        $response->assertOk();
        $items = $response->viewData('items');
        $total = $response->viewData('total');

        $this->assertEquals(1, $total);
        $this->assertEquals(1, $items->count());
        $this->assertEquals('Active Referral', $items->first()->referral_of);
    }

    public function test_p2p_meetings_admin_list_and_total_exclude_inactive_and_team_members(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.activities.p2p-meetings.index'));

        $response->assertOk();
        $items = $response->viewData('items');
        $total = $response->viewData('total');

        $this->assertEquals(1, $total);
        $this->assertEquals(1, $items->count());
    }

    public function test_business_deals_admin_list_and_total_exclude_inactive_and_team_members(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.activities.business-deals.index'));

        $response->assertOk();
        $items = $response->viewData('items');
        $total = $response->viewData('total');

        $this->assertEquals(1, $total);
        $this->assertEquals(1, $items->count());
        $this->assertEquals(50000, $items->first()->deal_amount);
    }

    public function test_coins_management_excludes_inactive_and_team_members(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.coins.index'));

        $response->assertOk();
        $members = $response->viewData('members');

        $memberEmails = $members->pluck('email')->all();
        $this->assertContains('active@example.com', $memberEmails);
        $this->assertNotContains('inactive@example.com', $memberEmails);
        $this->assertNotContains('team@example.com', $memberEmails);
    }

    public function test_life_impact_management_excludes_inactive_and_team_members(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.life-impact.index'));

        $response->assertOk();
        $members = $response->viewData('members');
        $summary = $response->viewData('summary');

        $memberEmails = $members->pluck('email')->all();
        $this->assertContains('active@example.com', $memberEmails);
        $this->assertNotContains('inactive@example.com', $memberEmails);
        $this->assertNotContains('team@example.com', $memberEmails);

        // Life impact of active member is 10, receiver is 0. Total should be 10, NOT 10 + 5 (inactive) + 100 (team)
        $this->assertEquals(10, $summary['total_life_impacted']);
    }

    public function test_activities_summary_page_excludes_inactive_and_team_members(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.activities.index'));

        $response->assertOk();
        $members = $response->viewData('members');

        $memberEmails = $members->pluck('email')->all();
        $this->assertContains('active@example.com', $memberEmails);
        $this->assertNotContains('inactive@example.com', $memberEmails);
        $this->assertNotContains('team@example.com', $memberEmails);
    }
}
