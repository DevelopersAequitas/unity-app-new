<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\AdClick;
use App\Models\AdminUser;
use App\Models\AdView;
use App\Models\AdvertiserApplication;
use App\Models\AdvertiserCampaign;
use App\Models\AdvertiserCampaignAd;
use App\Models\AdvertiserPackage;
use App\Models\AdvertiserSubscription;
use App\Models\User;
use App\Services\Advertiser\AdvertiserAnalyticsService;
use App\Services\Advertiser\AdvertiserCampaignService;
use App\Services\Advertiser\AdvertiserPublicationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdvertiserCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected User $advertiserUser;
    protected User $otherUser;
    protected AdminUser $adminUser;
    protected AdvertiserPackage $package;
    protected AdvertiserApplication $application;
    protected AdvertiserSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTablesExist();

        // Seed basic fixtures
        $this->advertiserUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Advertiser',
            'display_name' => 'John Advertiser',
            'email' => 'john@advertiser.com',
            'phone' => '+919999999999',
            'company_name' => 'Acme Corporation',
        ]);

        $this->otherUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'display_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+918888888888',
        ]);

        $this->adminUser = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Global Admin',
            'email' => 'admin@peersunity.com',
            'password' => bcrypt('password'),
        ]);

        $this->package = AdvertiserPackage::create([
            'id' => (string) Str::uuid(),
            'name' => 'Growth Advertiser',
            'slug' => 'growth-advertiser',
            'duration_months' => 6,
            'price' => 25000.0,
            'currency' => 'INR',
            'is_active' => true,
        ]);

        $this->application = AdvertiserApplication::create([
            'id' => (string) Str::uuid(),
            'application_number' => 'ADV-2026-000001',
            'user_id' => $this->advertiserUser->id,
            'package_id' => $this->package->id,
            'company_name' => 'Acme Corporation',
            'applicant_name' => 'John Advertiser',
            'applicant_email' => 'john@advertiser.com',
            'applicant_phone' => '+919999999999',
            'industry' => 'Technology',
            'brand_description' => 'Acme builds top products.',
            'status' => AdvertiserApplication::STATUS_APPROVED,
        ]);

        $this->subscription = AdvertiserSubscription::create([
            'id' => (string) Str::uuid(),
            'application_id' => $this->application->id,
            'user_id' => $this->advertiserUser->id,
            'package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'duration_months' => 6,
            'amount' => 25000.0,
            'currency' => 'INR',
            'status' => AdvertiserSubscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'expires_at' => now()->addMonths(6),
            'activated_at' => now(),
        ]);
    }

    protected function ensureTablesExist(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('first_name', 100)->nullable();
                $table->string('last_name', 100)->nullable();
                $table->string('display_name', 150)->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('company_name', 255)->nullable();
                $table->string('membership_status', 50)->default('visitor');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('advertiser_packages')) {
            Schema::create('advertiser_packages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->integer('duration_months');
                $table->decimal('price', 12, 2);
                $table->string('currency', 3)->default('INR');
                $table->json('features')->nullable();
                $table->string('banner_placement')->nullable();
                $table->integer('max_banner_assets')->default(5);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('advertiser_applications')) {
            Schema::create('advertiser_applications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('application_number')->unique();
                $table->uuid('user_id')->nullable();
                $table->uuid('package_id')->nullable();
                $table->uuid('brand_partner_id')->nullable();
                $table->string('source')->default('website');
                $table->string('applicant_name');
                $table->string('applicant_email');
                $table->string('applicant_phone');
                $table->boolean('applicant_email_verified')->default(false);
                $table->string('company_name');
                $table->string('company_legal_name')->nullable();
                $table->string('company_type')->nullable();
                $table->string('industry');
                $table->string('company_website')->nullable();
                $table->string('company_email')->nullable();
                $table->string('company_phone')->nullable();
                $table->string('whatsapp_number')->nullable();
                $table->text('address_line')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('country')->default('India');
                $table->string('postal_code')->nullable();
                $table->string('contact_person_name')->nullable();
                $table->string('contact_person_designation')->nullable();
                $table->text('brand_description');
                $table->text('target_audience')->nullable();
                $table->json('target_locations')->nullable();
                $table->string('advertising_objective')->nullable();
                $table->date('requested_start_date')->nullable();
                $table->text('additional_requirements')->nullable();
                $table->integer('requested_duration_months')->nullable();
                $table->string('requested_package_name')->nullable();
                $table->decimal('requested_package_price', 12, 2)->nullable();
                $table->string('requested_currency', 3)->default('INR');
                $table->string('status')->default('submitted');
                $table->text('admin_notes')->nullable();
                $table->text('applicant_response')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->uuid('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->string('idempotency_key')->nullable();
                $table->boolean('consent_to_terms')->default(false);
                $table->boolean('consent_to_privacy')->default(false);
                $table->boolean('marketing_consent')->default(false);
                $table->timestamp('consented_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('advertiser_subscriptions')) {
            Schema::create('advertiser_subscriptions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('application_id');
                $table->uuid('user_id')->nullable();
                $table->uuid('brand_partner_id')->nullable();
                $table->uuid('package_id')->nullable();
                $table->string('package_name_snapshot');
                $table->integer('duration_months');
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('INR');
                $table->string('status')->default('pending_payment');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->boolean('auto_renew')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ads')) {
            Schema::create('ads', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->string('subtitle')->nullable();
                $table->text('description')->nullable();
                $table->string('image_path')->nullable();
                $table->string('redirect_url')->nullable();
                $table->string('button_text')->nullable();
                $table->string('placement')->nullable();
                $table->string('page_name')->nullable();
                $table->integer('timeline_position')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('ad_views')) {
            Schema::create('ad_views', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable();
                $table->uuid('ad_id');
                $table->timestamp('viewed_at')->useCurrent();
                $table->string('session_id')->nullable();
                $table->string('ip_address')->nullable();
            });
        }

        if (!Schema::hasTable('ad_clicks')) {
            Schema::create('ad_clicks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable();
                $table->uuid('ad_id');
                $table->string('click_type')->default('visit');
                $table->string('ip')->nullable();
                $table->text('device')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->string('session_id')->nullable();
                $table->string('ip_address')->nullable();
            });
        }

        if (!Schema::hasTable('advertiser_campaigns')) {
            Schema::create('advertiser_campaigns', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('application_id');
                $table->uuid('subscription_id');
                $table->uuid('user_id')->nullable();
                $table->uuid('brand_partner_id')->nullable();
                $table->string('campaign_number')->unique();
                $table->string('name');
                $table->string('objective')->nullable();
                $table->text('description')->nullable();
                $table->text('target_audience')->nullable();
                $table->json('target_locations')->nullable();
                $table->json('requested_placements')->nullable();
                $table->string('cta_text')->nullable();
                $table->string('destination_url')->nullable();
                $table->string('status')->default('draft');
                $table->timestamp('requested_starts_at')->nullable();
                $table->timestamp('requested_ends_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('paused_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('admin_notes')->nullable();
                $table->text('applicant_feedback')->nullable();
                $table->uuid('created_by')->nullable();
                $table->uuid('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('advertiser_campaign_ads')) {
            Schema::create('advertiser_campaign_ads', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('campaign_id');
                $table->uuid('ad_id');
                $table->string('creative_role')->default('primary');
                $table->string('status')->default('pending_review');
                $table->boolean('is_primary')->default(false);
                $table->uuid('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('detached_at')->nullable();
                $table->text('admin_notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('advertiser_campaign_status_history')) {
            Schema::create('advertiser_campaign_status_history', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('campaign_id');
                $table->string('old_status')->nullable();
                $table->string('new_status');
                $table->uuid('changed_by')->nullable();
                $table->string('actor_type');
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function test_advertiser_can_create_campaign_draft(): void
    {
        $campaignService = app(AdvertiserCampaignService::class);

        $campaign = $campaignService->createDraft($this->advertiserUser, [
            'name' => 'Fall Super Sale',
            'objective' => 'lead_generation',
            'description' => 'Huge discounts on enterprise IT.',
            'cta_text' => 'Order Now',
            'destination_url' => 'https://acme.com/sale',
            'subscription_id' => $this->subscription->id,
        ]);

        $this->assertInstanceOf(AdvertiserCampaign::class, $campaign);
        $this->assertEquals('draft', $campaign->status);
        $this->assertEquals('Fall Super Sale', $campaign->name);
        $this->assertStringStartsWith('CMP-', $campaign->campaign_number);
        $this->assertEquals($this->advertiserUser->id, $campaign->user_id);
    }

    public function test_advertiser_can_submit_campaign_for_review(): void
    {
        $campaignService = app(AdvertiserCampaignService::class);

        $campaign = $campaignService->createDraft($this->advertiserUser, [
            'name' => 'Diwali Campaign',
            'cta_text' => 'Learn More',
            'destination_url' => 'https://acme.com/diwali',
        ]);

        $submitted = $campaignService->submitForReview($campaign, 'Please review our creative', $this->advertiserUser);

        $this->assertEquals(AdvertiserCampaign::STATUS_PENDING_REVIEW, $submitted->status);
        $this->assertDatabaseHas('advertiser_campaign_status_history', [
            'campaign_id' => $campaign->id,
            'new_status' => AdvertiserCampaign::STATUS_PENDING_REVIEW,
        ]);
    }

    public function test_admin_approval_publishes_ad_in_feed_engine(): void
    {
        $campaignService = app(AdvertiserCampaignService::class);

        $campaign = $campaignService->createDraft($this->advertiserUser, [
            'name' => 'Global Brand Campaign',
            'cta_text' => 'Contact Us',
            'destination_url' => 'https://acme.com/contact',
        ]);

        $campaignService->submitForReview($campaign, 'Ready', $this->advertiserUser);

        // Admin approves
        $reviewed = $campaignService->reviewCampaign(
            $campaign,
            'approved',
            'Creative approved for main feed',
            $this->advertiserUser
        );

        $this->assertEquals(AdvertiserCampaign::STATUS_ACTIVE, $reviewed->status);

        // Verify Ad created in existing ads table
        $this->assertDatabaseHas('ads', [
            'title' => 'Global Brand Campaign',
            'redirect_url' => 'https://acme.com/contact',
            'is_active' => true,
        ]);

        // Verify relationship created in advertiser_campaign_ads
        $this->assertDatabaseHas('advertiser_campaign_ads', [
            'campaign_id' => $campaign->id,
            'status' => 'published',
            'is_primary' => true,
        ]);
    }

    public function test_campaign_pause_and_resume_updates_feed_ad(): void
    {
        $campaignService = app(AdvertiserCampaignService::class);

        $campaign = $campaignService->createDraft($this->advertiserUser, [
            'name' => 'Product Launch',
            'cta_text' => 'Get Started',
            'destination_url' => 'https://acme.com/launch',
        ]);

        $campaignService->reviewCampaign($campaign, 'approved', 'OK', $this->advertiserUser);

        $linkedAd = $campaign->ads()->first();
        $this->assertNotNull($linkedAd);
        $this->assertTrue((bool) $linkedAd->is_active);

        // Pause
        $campaignService->pauseCampaign($campaign, 'Budget paused', $this->advertiserUser);
        $this->assertEquals(AdvertiserCampaign::STATUS_PAUSED, $campaign->fresh()->status);
        $this->assertFalse((bool) $linkedAd->fresh()->is_active);

        // Resume
        $campaignService->resumeCampaign($campaign, $this->advertiserUser);
        $this->assertEquals(AdvertiserCampaign::STATUS_ACTIVE, $campaign->fresh()->status);
        $this->assertTrue((bool) $linkedAd->fresh()->is_active);
    }

    public function test_campaign_analytics_scopes_strictly_to_linked_ads(): void
    {
        $campaignService = app(AdvertiserCampaignService::class);
        $analyticsService = app(AdvertiserAnalyticsService::class);

        $campaign = $campaignService->createDraft($this->advertiserUser, [
            'name' => 'Analytics Test Campaign',
            'cta_text' => 'Click',
            'destination_url' => 'https://acme.com',
        ]);

        $campaignService->reviewCampaign($campaign, 'approved', 'Approved', $this->advertiserUser);
        $linkedAd = $campaign->ads()->first();

        // Create an unrelated ad
        $unrelatedAd = Ad::create([
            'id' => (string) Str::uuid(),
            'title' => 'Unrelated Third-Party Ad',
            'is_active' => true,
        ]);

        // Record views on linked ad
        AdView::create([
            'id' => (string) Str::uuid(),
            'ad_id' => $linkedAd->id,
            'user_id' => $this->advertiserUser->id,
            'viewed_at' => now(),
        ]);
        AdClick::create([
            'id' => (string) Str::uuid(),
            'ad_id' => $linkedAd->id,
            'user_id' => $this->advertiserUser->id,
            'created_at' => now(),
        ]);

        // Record views on unrelated ad
        AdView::create([
            'id' => (string) Str::uuid(),
            'ad_id' => $unrelatedAd->id,
            'user_id' => $this->otherUser->id,
            'viewed_at' => now(),
        ]);

        $stats = $analyticsService->getCampaignAnalytics($campaign);

        // Must count only 1 view and 1 click, ignoring the unrelated ad
        $this->assertEquals(1, $stats['views']);
        $this->assertEquals(1, $stats['clicks']);
        $this->assertEquals(100.0, $stats['ctr_percent']);
    }

    public function test_other_user_cannot_modify_unauthorized_campaign(): void
    {
        $campaignService = app(AdvertiserCampaignService::class);

        $campaign = $campaignService->createDraft($this->advertiserUser, [
            'name' => 'Protected Campaign',
            'cta_text' => 'Click',
            'destination_url' => 'https://acme.com',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        // Jane Doe tries to update John's campaign
        $campaignService->updateDraft($campaign, [
            'name' => 'Hacked Name',
        ], $this->otherUser);
    }
}
