<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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

        Schema::create('circle_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
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
            ->assertJsonPath('data.about', 'Short bio test')
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
            'profile_video',
            'profile_video_url',
            'intro_video_id',
            'intro_video_url',
            'first_name',
            'last_name',
            'display_name',
            'company_name',
            'designation',
            'email',
            'phone',
            'introduced_by',
            'introduced_by_user',
            'city',
            'city_of_residence',
            'membership_status',
            'membership_expiry',
            'membership_status_label',
            'membership_starts_at',
            'membership_ends_at',
            'zoho_plan_code',
            'zoho_last_invoice_id',
            'active_circle_id',
            'active_circle_addon_code',
            'active_circle_addon_name',
            'circle_joined_at',
            'circle_expires_at',
            'active_circle_subscription_id',
            'circle_memberships',
            'contact_visibility',
            'connection_count',
            'followers_count',
            'following_count',
            'posts',
            'posts_count',
            'coins_balance',
            'life_impacted_count',
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
            'social_links',
            'media',
            'profile_photo_url',
            'cover_photo_url',
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
            'website',
            'sustainability_contribution',
            'sustainability_areas',
            'greenpreneur_goals',
            'community_directory_listing',
            'is_bookmark',
            'business_logo_id',
            'business_category_id',
            'business_sub_category',
            'company_type',
            'year_of_establishment',
            'annual_revenue_range',
            'number_of_employees',
            'gst_number',
            'business_website',
            'about',
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
            'other_website',
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
    }
}
