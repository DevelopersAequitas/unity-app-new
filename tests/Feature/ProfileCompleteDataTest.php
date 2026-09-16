<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileCompleteDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('peer_id', 50)->nullable()->unique();
            $table->string('public_profile_slug')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('designation')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->string('password_hash')->nullable();
            $table->string('introduced_by')->nullable();
            $table->uuid('city_id')->nullable();
            $table->string('city')->nullable();
            $table->string('city_of_residence')->nullable();
            $table->string('membership_status')->nullable();
            $table->timestamp('membership_expiry')->nullable();
            $table->timestamp('membership_starts_at')->nullable();
            $table->timestamp('membership_ends_at')->nullable();
            $table->string('zoho_plan_code')->nullable();
            $table->string('zoho_last_invoice_id')->nullable();
            $table->uuid('active_circle_id')->nullable();
            $table->string('active_circle_addon_code')->nullable();
            $table->string('active_circle_addon_name')->nullable();
            $table->timestamp('circle_joined_at')->nullable();
            $table->timestamp('circle_expires_at')->nullable();
            $table->uuid('active_circle_subscription_id')->nullable();
            $table->string('contact_visibility')->nullable();
            $table->integer('coins_balance')->nullable();
            $table->integer('life_impacted_count')->nullable();
            $table->string('business_type')->nullable();
            $table->string('turnover_range')->nullable();
            $table->string('gender')->nullable();
            $table->date('dob')->nullable();
            $table->date('anniversary_date')->nullable();
            $table->integer('experience_years')->nullable();
            $table->text('experience_summary')->nullable();
            $table->text('bio')->nullable();
            $table->text('short_bio')->nullable();
            $table->text('long_bio_html')->nullable();
            $table->json('industry_tags')->nullable();
            $table->json('skills')->nullable();
            $table->json('interests')->nullable();
            $table->json('target_regions')->nullable();
            $table->json('target_business_categories')->nullable();
            $table->json('hobbies_interests')->nullable();
            $table->json('leadership_roles')->nullable();
            $table->json('special_recognitions')->nullable();
            $table->json('social_links')->nullable();
            $table->json('media')->nullable();
            $table->uuid('profile_photo_file_id')->nullable();
            $table->uuid('cover_photo_file_id')->nullable();
            $table->uuid('profile_video_id')->nullable();
            $table->text('address')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('timezone')->nullable();
            $table->string('pincode')->nullable();
            $table->boolean('is_verified')->nullable();
            $table->boolean('is_sponsored_member')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('website')->nullable();
            $table->text('sustainability_contribution')->nullable();
            $table->json('sustainability_areas')->nullable();
            $table->json('greenpreneur_goals')->nullable();
            $table->string('community_directory_listing')->nullable();
            $table->json('bookmarks')->nullable();

            $table->uuid('business_logo_id')->nullable();
            $table->uuid('business_category_id')->nullable();
            $table->string('business_sub_category')->nullable();
            $table->string('company_type')->nullable();
            $table->integer('year_of_establishment')->nullable();
            $table->string('annual_revenue_range')->nullable();
            $table->integer('number_of_employees')->nullable();
            $table->string('gst_number')->nullable();
            $table->string('business_website')->nullable();
            $table->string('superpower')->nullable();
            $table->json('i_can_help_with')->nullable();
            $table->json('i_am_looking_for')->nullable();
            $table->json('business_keywords')->nullable();
            $table->text('products_services_offered')->nullable();
            $table->string('preferred_language')->nullable();
            $table->string('secondary_mobile')->nullable();
            $table->string('linkedin_profile')->nullable();
            $table->string('instagram_handle')->nullable();
            $table->string('twitter_handle')->nullable();
            $table->string('facebook_profile')->nullable();
            $table->string('youtube_channel')->nullable();
            $table->string('other_website')->nullable();
            $table->text('business_address')->nullable();
            $table->string('business_city')->nullable();
            $table->string('business_state')->nullable();
            $table->string('business_pincode')->nullable();
            $table->string('business_country')->nullable();
            $table->decimal('google_maps_latitude', 10, 7)->nullable();
            $table->decimal('google_maps_longitude', 10, 7)->nullable();
            $table->json('industries_of_interest')->nullable();
            $table->json('collaboration_goals')->nullable();
            $table->string('preferred_meeting_format')->nullable();
            $table->boolean('willing_to_mentor')->nullable();
            $table->boolean('open_to_cross_city_collaboration')->nullable();
            $table->boolean('open_to_speaking_at_events')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('title')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();
        });

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('status')->default('approved');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('paid_starts_at')->nullable();
            $table->timestamp('paid_ends_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        if (! Schema::hasTable('circle_subscriptions')) {
            Schema::create('circle_subscriptions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('circle_id')->nullable();
                $table->uuid('user_id')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_milestone_badges')) {
            Schema::create('user_milestone_badges', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('badge_id')->nullable();
                $table->string('milestone_type')->nullable();
                $table->integer('achieved_count')->default(0);
                $table->string('status')->default('earned');
                $table->timestamp('earned_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('p2p_meetings')) {
            Schema::create('p2p_meetings', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('initiator_user_id');
                $table->uuid('peer_user_id');
                $table->date('meeting_date')->nullable();
                $table->string('meeting_place')->nullable();
                $table->text('remarks')->nullable();
                $table->json('media')->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('referrals')) {
            Schema::create('referrals', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('from_user_id');
                $table->uuid('to_user_id');
                $table->string('referral_type')->nullable();
                $table->date('referral_date')->nullable();
                $table->string('referral_of')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->text('address')->nullable();
                $table->string('hot_value')->nullable();
                $table->text('remarks')->nullable();
                $table->uuid('status_id')->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('business_deals')) {
            Schema::create('business_deals', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('from_user_id');
                $table->uuid('to_user_id');
                $table->date('deal_date')->nullable();
                $table->decimal('deal_amount', 12, 2)->default(0);
                $table->string('business_type')->nullable();
                $table->text('comment')->nullable();
                $table->uuid('referral_id')->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('circles')) {
            Schema::create('circles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->uuid('city_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('circle_categories')) {
            Schema::create('circle_categories', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('circle_category_level2')) {
            Schema::create('circle_category_level2', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('circle_category_id')->nullable();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('circle_category_level3')) {
            Schema::create('circle_category_level3', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('level2_id')->nullable();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('circle_category_level4')) {
            Schema::create('circle_category_level4', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('level3_id')->nullable();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('joined_circle_categories')) {
            Schema::create('joined_circle_categories', function (Blueprint $table): void {
                $table->id();
                $table->uuid('user_id');
                $table->uuid('circle_id')->nullable();
                $table->uuid('circle_member_id')->nullable();
                $table->unsignedInteger('level1_category_id')->nullable();
                $table->unsignedInteger('level2_category_id')->nullable();
                $table->unsignedInteger('level3_category_id')->nullable();
                $table->unsignedInteger('level4_category_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_get_profile_returns_complete_member_and_profile_data(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'first_name' => 'Chirag',
            'last_name' => 'Mali',
            'display_name' => 'Chirag Mali',
            'email' => 'chirag.mali@example.com',
            'company_name' => 'Greenpreneur',
            'designation' => 'Founder',
            'membership_status' => 'unity_peer',
            'coins_balance' => 500,
            'bio' => 'Sustainability leader',
            'short_bio' => 'Short bio test',
            'superpower' => 'Networking',
            'i_can_help_with' => ['Mentorship', 'Growth'],
            'i_am_looking_for' => ['Investors', 'Partners'],
            'business_keywords' => ['EV', 'Solar'],
            'skills' => ['Laravel', 'Leadership'],
            'interests' => ['CleanTech', 'Renewable'],
            'sustainability_areas' => ['Solar', 'Recycling'],
            'greenpreneur_goals' => ['NetZero2030'],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Profile fetched successfully')
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.first_name', 'Chirag')
            ->assertJsonPath('data.last_name', 'Mali')
            ->assertJsonPath('data.display_name', 'Chirag Mali')
            ->assertJsonPath('data.email', 'chirag.mali@example.com')
            ->assertJsonPath('data.company_name', 'Greenpreneur')
            ->assertJsonPath('data.designation', 'Founder')
            ->assertJsonPath('data.membership_status', 'unity_peer')
            ->assertJsonPath('data.membership_status_label', 'Green Member')
            ->assertJsonPath('data.coins_balance', 500)
            ->assertJsonPath('data.bio', 'Short bio test')
            ->assertJsonPath('data.superpower', 'Networking')
            ->assertJsonPath('data.i_can_help_with', ['Mentorship', 'Growth'])
            ->assertJsonPath('data.i_am_looking_for', ['Investors', 'Partners'])
            ->assertJsonPath('data.business_keywords', ['EV', 'Solar'])
            ->assertJsonPath('data.skills', ['Laravel', 'Leadership'])
            ->assertJsonPath('data.interests', ['CleanTech', 'Renewable'])
            ->assertJsonPath('data.sustainability_areas', ['Solar', 'Recycling'])
            ->assertJsonPath('data.greenpreneur_goals', ['NetZero2030']);

        $data = $response->json('data');

        $requiredKeys = [
            'id',
            'public_profile_slug',
            'profile_photo_id',
            'cover_photo_id',
            'profile_video_id',
            'profile_video_url',
            'first_name',
            'last_name',
            'display_name',
            'company_name',
            'designation',
            'email',
            'phone',
            'introduced_by_user',
            'city',
            'membership_status',
            'membership_status_label',
            'is_multi_circle_peer',
            'membership_starts_at',
            'membership_ends_at',
            'zoho_plan_code',
            'zoho_last_invoice_id',
            'active_circle',
            'circle_memberships',
            'contact_visibility',
            'connection_count',
            'followers_count',
            'following_count',
            'posts_count',
            'coins_balance',
            'life_impacted_count',
            'badges_count',
            'p2p_meetings_count',
            'referrals_count',
            'given_referrals_count',
            'received_referrals_count',
            'business_deals_count',
            'given_business_deals_count',
            'received_business_deals_count',
            'business_type',
            'turnover_range',
            'gender',
            'dob',
            'anniversary_date',
            'experience_years',
            'experience_summary',
            'bio',
            'long_bio_html',
            'industry_tags',
            'skills',
            'interests',
            'target_regions',
            'target_business_categories',
            'hobbies_interests',
            'leadership_roles',
            'special_recognitions',
            'media',
            'profile_photo_url',
            'cover_photo_url',
            'welcome_creative_url',
            'address',
            'state',
            'country',
            'timezone',
            'pincode',
            'is_verified',
            'is_sponsored_member',
            'last_login_at',
            'created_at',
            'updated_at',
            'sustainability_contribution',
            'sustainability_areas',
            'greenpreneur_goals',
            'community_directory_listing',
            'is_bookmark',
            'business_logo_id',
            'business_category',
            'company_type',
            'year_of_establishment',
            'annual_revenue_range',
            'number_of_employees',
            'gst_number',
            'business_website',
            'superpower',
            'i_can_help_with',
            'i_am_looking_for',
            'business_keywords',
            'products_services_offered',
            'preferred_language',
            'secondary_mobile',
            'linkedin_profile',
            'instagram_handle',
            'twitter_handle',
            'facebook_profile',
            'youtube_channel',
            'business_address',
            'business_city',
            'business_state',
            'business_pincode',
            'business_country',
            'google_maps_latitude',
            'google_maps_longitude',
            'industries_of_interest',
            'collaboration_goals',
            'preferred_meeting_format',
            'willing_to_mentor',
            'open_to_cross_city_collaboration',
            'open_to_speaking_at_events',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $data, "Expected key [{$key}] was missing from profile API response data.");
        }

        $duplicateKeys = [
            'intro_video_id',
            'intro_video_url',
            'city_of_residence',
            'posts',
            'profile_card_image_url',
            'custom_category_name',
            'about',
            'profile_video',
            'introduced_by',
            'active_circle_id',
            'active_circle_addon_code',
            'active_circle_addon_name',
            'circle_joined_at',
            'circle_expires_at',
            'active_circle_subscription_id',
            'my_badges_count',
            'p2p_count',
            'deals_count',
            'social_links',
            'website',
            'business_category_id',
            'business_sub_category',
            'other_website',
            'categories',
        ];

        foreach ($duplicateKeys as $key) {
            $this->assertArrayNotHasKey($key, $data, "Duplicate key [{$key}] should have been removed from profile API response data.");
        }
    }

    public function test_get_profile_returns_correct_activity_and_badge_counts(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
        ]);

        /** @var User $peer */
        $peer = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@example.com',
        ]);

        // Insert badges
        DB::table('user_milestone_badges')->insert([
            [
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'badge_id' => (string) Str::uuid(),
                'milestone_type' => 'CONNECTOR',
                'achieved_count' => 1,
                'status' => 'earned',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'badge_id' => (string) Str::uuid(),
                'milestone_type' => 'CATALYST',
                'achieved_count' => 3,
                'status' => 'earned',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'badge_id' => (string) Str::uuid(),
                'milestone_type' => 'REVOKED_TEST',
                'achieved_count' => 1,
                'status' => 'revoked',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Insert P2P meetings (1 as initiator, 1 as peer, 1 soft-deleted)
        DB::table('p2p_meetings')->insert([
            [
                'id' => (string) Str::uuid(),
                'initiator_user_id' => $user->id,
                'peer_user_id' => $peer->id,
                'meeting_date' => now()->toDateString(),
                'is_deleted' => false,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'initiator_user_id' => $peer->id,
                'peer_user_id' => $user->id,
                'meeting_date' => now()->toDateString(),
                'is_deleted' => false,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'initiator_user_id' => $user->id,
                'peer_user_id' => $peer->id,
                'meeting_date' => now()->toDateString(),
                'is_deleted' => true,
                'deleted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Insert referrals (2 given, 1 received, 1 deleted)
        DB::table('referrals')->insert([
            [
                'id' => (string) Str::uuid(),
                'from_user_id' => $user->id,
                'to_user_id' => $peer->id,
                'referral_type' => 'given',
                'is_deleted' => false,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'from_user_id' => $user->id,
                'to_user_id' => $peer->id,
                'referral_type' => 'given',
                'is_deleted' => false,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'from_user_id' => $peer->id,
                'to_user_id' => $user->id,
                'referral_type' => 'received',
                'is_deleted' => false,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'from_user_id' => $user->id,
                'to_user_id' => $peer->id,
                'referral_type' => 'given',
                'is_deleted' => true,
                'deleted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Insert business deals (1 given, 2 received, 1 deleted)
        DB::table('business_deals')->insert([
            [
                'id' => (string) Str::uuid(),
                'from_user_id' => $user->id,
                'to_user_id' => $peer->id,
                'deal_amount' => 50000,
                'is_deleted' => false,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'from_user_id' => $peer->id,
                'to_user_id' => $user->id,
                'deal_amount' => 150000,
                'is_deleted' => false,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'from_user_id' => $peer->id,
                'to_user_id' => $user->id,
                'deal_amount' => 25000,
                'is_deleted' => false,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'from_user_id' => $user->id,
                'to_user_id' => $peer->id,
                'deal_amount' => 10000,
                'is_deleted' => true,
                'deleted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.badges_count', 2)
            ->assertJsonPath('data.p2p_meetings_count', 2)
            ->assertJsonPath('data.referrals_count', 3)
            ->assertJsonPath('data.given_referrals_count', 2)
            ->assertJsonPath('data.received_referrals_count', 1)
            ->assertJsonPath('data.business_deals_count', 3)
            ->assertJsonPath('data.given_business_deals_count', 1)
            ->assertJsonPath('data.received_business_deals_count', 2);
    }

    public function test_circle_membership_contains_selected_category_path_from_joined_circle_categories(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'membership_status' => 'free_peer',
        ]);

        $circleId = (string) Str::uuid();
        $memberId = (string) Str::uuid();

        DB::table('circles')->insert([
            'id' => $circleId,
            'name' => 'Ahmedabad tech',
            'slug' => 'ahmedabad-tech',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('circle_members')->insert([
            'id' => $memberId,
            'circle_id' => $circleId,
            'user_id' => $user->id,
            'status' => 'approved',
            'joined_at' => now(),
            'paid_starts_at' => now(),
            'paid_ends_at' => now()->addYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cat1Id = DB::table('circle_categories')->insertGetId([
            'name' => 'Manufacturing & Engineering Circles',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cat2Id = DB::table('circle_category_level2')->insertGetId([
            'circle_category_id' => $cat1Id,
            'name' => 'CORE MANUFACTURING INDUSTRIES',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cat3Id = DB::table('circle_category_level3')->insertGetId([
            'level2_id' => $cat2Id,
            'name' => 'Heavy & Industrial Manufacturing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cat4Id = DB::table('circle_category_level4')->insertGetId([
            'level3_id' => $cat3Id,
            'name' => 'Iron & Metal Processing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('joined_circle_categories')->insert([
            'user_id' => $user->id,
            'circle_id' => $circleId,
            'circle_member_id' => $memberId,
            'level1_category_id' => $cat1Id,
            'level2_category_id' => $cat2Id,
            'level3_category_id' => $cat3Id,
            'level4_category_id' => $cat4Id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.membership_status', 'circle_peer')
            ->assertJsonPath('data.membership_status_label', 'Circle Peer')
            ->assertJsonPath('data.is_multi_circle_peer', false)
            ->assertJsonPath('data.circle_memberships.0.circle_id', $circleId)
            ->assertJsonPath('data.circle_memberships.0.circle_name', 'Ahmedabad tech')
            ->assertJsonPath('data.circle_memberships.0.selected_category_path.level1.id', $cat1Id)
            ->assertJsonPath('data.circle_memberships.0.selected_category_path.level1.name', 'Manufacturing & Engineering Circles')
            ->assertJsonPath('data.circle_memberships.0.selected_category_path.level2.id', $cat2Id)
            ->assertJsonPath('data.circle_memberships.0.selected_category_path.level2.name', 'CORE MANUFACTURING INDUSTRIES')
            ->assertJsonPath('data.circle_memberships.0.selected_category_path.level3.id', $cat3Id)
            ->assertJsonPath('data.circle_memberships.0.selected_category_path.level3.name', 'Heavy & Industrial Manufacturing')
            ->assertJsonPath('data.circle_memberships.0.selected_category_path.level4.id', $cat4Id)
            ->assertJsonPath('data.circle_memberships.0.selected_category_path.level4.name', 'Iron & Metal Processing');

        $data = $response->json('data');
        $this->assertArrayNotHasKey('categories', $data, 'categories array should be removed from profile API response');
    }

    public function test_peer_with_multiple_circles_shows_multi_circle_peer(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'membership_status' => 'free_peer',
        ]);

        $circleId1 = (string) Str::uuid();
        $circleId2 = (string) Str::uuid();

        DB::table('circles')->insert([
            [
                'id' => $circleId1,
                'name' => 'Ahmedabad tech',
                'slug' => 'ahmedabad-tech',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $circleId2,
                'name' => 'Satellite Business Circle',
                'slug' => 'satellite-business-circle',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('circle_members')->insert([
            [
                'id' => (string) Str::uuid(),
                'circle_id' => $circleId1,
                'user_id' => $user->id,
                'status' => 'approved',
                'joined_at' => now(),
                'paid_starts_at' => now(),
                'paid_ends_at' => now()->addYear(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'circle_id' => $circleId2,
                'user_id' => $user->id,
                'status' => 'approved',
                'joined_at' => now(),
                'paid_starts_at' => now(),
                'paid_ends_at' => now()->addYear(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.membership_status', 'multi_circle_peer')
            ->assertJsonPath('data.membership_status_label', 'Multi Circle Peer')
            ->assertJsonPath('data.is_multi_circle_peer', true);

        $this->assertCount(2, $response->json('data.circle_memberships'));
    }

    public function test_global_peer_transitions_to_circle_peer_when_joined_to_circle(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'membership_status' => 'Only Unity Peer',
        ]);

        $circleId = (string) Str::uuid();
        DB::table('circles')->insert([
            'id' => $circleId,
            'name' => 'Ahmedabad Investors Circle',
            'slug' => 'ahmedabad-investors-circle',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('circle_members')->insert([
            'id' => (string) Str::uuid(),
            'circle_id' => $circleId,
            'user_id' => $user->id,
            'status' => 'approved',
            'joined_at' => now(),
            'paid_starts_at' => now(),
            'paid_ends_at' => now()->addYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.membership_status', 'circle_peer')
            ->assertJsonPath('data.membership_status_label', 'Circle Peer')
            ->assertJsonPath('data.is_multi_circle_peer', false);
    }

    public function test_chartered_peer_preserves_status_when_joined_to_circle(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'membership_status' => 'chartered_peer',
        ]);

        $circleId = (string) Str::uuid();
        DB::table('circles')->insert([
            'id' => $circleId,
            'name' => 'Ahmedabad Investors Circle',
            'slug' => 'ahmedabad-investors-circle',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('circle_members')->insert([
            'id' => (string) Str::uuid(),
            'circle_id' => $circleId,
            'user_id' => $user->id,
            'status' => 'approved',
            'joined_at' => now(),
            'paid_starts_at' => now(),
            'paid_ends_at' => now()->addYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.membership_status', 'chartered_peer')
            ->assertJsonPath('data.membership_status_label', 'Premium Green Member')
            ->assertJsonPath('data.is_multi_circle_peer', false);
    }

    public function test_profile_endpoint_returns_life_impacted_count_from_history_when_column_is_zero(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'life_impacted_count' => 0,
        ]);

        if (! Schema::hasTable('life_impact_histories')) {
            Schema::create('life_impact_histories', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->integer('impact_value')->default(0);
                $table->integer('life_impacted')->default(0);
                $table->string('status')->nullable();
                $table->boolean('counted_in_total')->default(true);
                $table->timestamps();
            });
        }

        DB::table('life_impact_histories')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'impact_value' => 7,
            'life_impacted' => 7,
            'status' => 'approved',
            'counted_in_total' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.life_impacted_count', 7);
    }
}
