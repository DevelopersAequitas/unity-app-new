<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdvertiserApplication;
use App\Models\AdvertiserPackage;
use App\Models\AdvertiserPayment;
use App\Models\AdvertiserSubscription;
use App\Models\BrandPartner;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrandPartnerDirectPaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createTestTables();
    }

    private function createTestTables(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->integer('coins_balance')->default(0);
            $table->integer('life_impacted_count')->default(0);
            $table->integer('members_introduced_count')->default(0);
            $table->string('coin_medal_rank')->nullable();
            $table->string('coin_milestone_title')->nullable();
            $table->string('coin_milestone_meaning')->nullable();
            $table->string('contribution_award_name')->nullable();
            $table->string('contribution_award_recognition')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('advertiser_packages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->integer('duration_months')->default(3);
            $table->decimal('price', 12, 2)->default(15000);
            $table->string('currency', 10)->default('INR');
            $table->json('features')->nullable();
            $table->json('placements')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(1);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('advertiser_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('application_number')->unique();
            $table->uuid('user_id')->nullable()->index();
            $table->uuid('package_id')->nullable();
            $table->uuid('brand_partner_id')->nullable();
            $table->string('source')->default('app');
            $table->string('applicant_name')->nullable();
            $table->string('applicant_email')->nullable();
            $table->string('applicant_phone')->nullable();
            $table->boolean('applicant_email_verified')->default(true);
            $table->string('company_name')->nullable();
            $table->string('company_legal_name')->nullable();
            $table->string('company_type')->nullable();
            $table->string('industry')->nullable();
            $table->string('company_website')->nullable();
            $table->string('company_email')->nullable();
            $table->string('company_phone')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_designation')->nullable();
            $table->text('brand_description')->nullable();
            $table->text('target_audience')->nullable();
            $table->json('target_locations')->nullable();
            $table->string('advertising_objective')->nullable();
            $table->date('requested_start_date')->nullable();
            $table->text('additional_requirements')->nullable();
            $table->integer('requested_duration_months')->default(3);
            $table->string('requested_package_name')->nullable();
            $table->decimal('requested_package_price', 12, 2)->default(0);
            $table->string('requested_currency', 10)->default('INR');
            $table->string('status')->default('approved');
            $table->text('admin_notes')->nullable();
            $table->text('applicant_response')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->boolean('consent_to_terms')->default(true);
            $table->boolean('consent_to_privacy')->default(true);
            $table->boolean('marketing_consent')->default(false);
            $table->timestamp('consented_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('advertiser_application_assets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('application_id')->index();
            $table->string('asset_type')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('storage_disk')->default('public');
            $table->string('storage_key')->nullable();
            $table->string('mime_type')->nullable();
            $table->bigInteger('file_size_bytes')->default(0);
            $table->integer('display_order')->default(0);
            $table->string('review_status')->default('pending');
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('advertiser_application_status_history', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('application_id')->index();
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->uuid('changed_by')->nullable();
            $table->string('actor_type')->default('applicant');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('advertiser_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('application_id')->index();
            $table->uuid('user_id')->nullable()->index();
            $table->uuid('brand_partner_id')->nullable();
            $table->uuid('package_id')->nullable();
            $table->string('package_name_snapshot')->nullable();
            $table->integer('duration_months')->default(3);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 10)->default('INR');
            $table->string('status')->default('pending_payment');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('advertiser_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('subscription_id')->index();
            $table->string('provider')->default('razorpay');
            $table->string('provider_order_id')->nullable();
            $table->string('provider_payment_id')->nullable();
            $table->string('provider_signature')->nullable();
            $table->string('provider_event_id')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 10)->default('INR');
            $table->string('status')->default('created');
            $table->string('idempotency_key')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_description')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('brand_partner_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->integer('sort_order')->default(1);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('brand_partners', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->string('cover_image')->nullable();
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->uuid('category_id')->nullable();
            $table->string('website')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('address')->nullable();
            $table->string('offer_title')->nullable();
            $table->text('offer_description')->nullable();
            $table->string('coupon_code')->nullable();
            $table->string('discount_type')->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(true);
            $table->boolean('is_sponsored')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_submitting_brand_partner_application_immediately_enables_direct_payment_without_admin_approval(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Rohan',
            'last_name' => 'Patel',
            'display_name' => 'Rohan Patel',
            'email' => 'rohan@example.com',
            'phone' => '919876543210',
            'status' => 'active',
        ]);

        $package = AdvertiserPackage::create([
            'id' => (string) Str::uuid(),
            'name' => 'Quarterly Brand Partner Package',
            'slug' => 'quarterly-brand-partner',
            'duration_months' => 3,
            'price' => 15000.00,
            'currency' => 'INR',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        // 1. Submit Brand Partner application with start_date
        $submitResponse = $this->postJson('/api/v1/advertiser/applications', [
            'package_id' => $package->id,
            'start_date' => '2026-11-25',
            'applicant' => [
                'full_name' => 'Rohan Patel',
                'email' => 'rohan@example.com',
                'mobile_number' => '+919876543210',
            ],
            'company' => [
                'company_name' => 'Acme Brand Pvt Ltd',
                'industry' => (string) Str::uuid(),
                'website' => 'https://acme.example.com',
                'description' => 'Enterprise SaaS Solutions',
            ],
            'consents' => [
                'terms_accepted' => true,
                'privacy_notice_accepted' => true,
            ],
            'source' => 'app',
        ]);

        $submitResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', AdvertiserApplication::STATUS_APPROVED)
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.is_paid', false)
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.publication_status', 'pending_payment')
            ->assertJsonPath('data.start_date', '2026-11-25')
            ->assertJsonPath('data.end_date', '2027-02-25')
            ->assertJsonPath('data.expiry_date', '2027-02-25')
            ->assertJsonPath('data.payment_required', true)
            ->assertJsonPath('data.email_verification_required', false)
            ->assertJsonPath('data.next_action', 'complete_payment')
            ->assertJsonPath('data.package.id', (string) $package->id);

        $applicationId = (string) $submitResponse->json('data.application_id');
        $subscriptionId = (string) $submitResponse->json('data.subscription_id');

        $this->assertNotEmpty($applicationId);
        $this->assertNotEmpty($subscriptionId);

        // Verify pending subscription was automatically initialized
        $this->assertDatabaseHas('advertiser_subscriptions', [
            'id' => $subscriptionId,
            'application_id' => $applicationId,
            'user_id' => (string) $user->id,
            'status' => AdvertiserSubscription::STATUS_PENDING_PAYMENT,
        ]);

        // 2. Verify GET /api/v1/advertiser/my-applications returns complete_payment directly
        $myAppsResponse = $this->getJson('/api/v1/advertiser/my-applications');
        $myAppsResponse->assertOk()
            ->assertJsonPath('data.data.0.application_id', $applicationId)
            ->assertJsonPath('data.data.0.subscription_id', $subscriptionId)
            ->assertJsonPath('data.data.0.status', 'approved')
            ->assertJsonPath('data.data.0.payment_status', 'pending')
            ->assertJsonPath('data.data.0.is_paid', false)
            ->assertJsonPath('data.data.0.start_date', '2026-11-25')
            ->assertJsonPath('data.data.0.end_date', '2027-02-25')
            ->assertJsonPath('data.data.0.next_action', 'complete_payment');

        // 3. Simulate payment completion -> MUST NOT auto-publish BrandPartner; instead awaits admin approval to publish
        $payment = AdvertiserPayment::create([
            'id' => (string) Str::uuid(),
            'subscription_id' => $subscriptionId,
            'provider' => 'razorpay',
            'provider_order_id' => 'order_test_12345',
            'amount' => 15000.00,
            'currency' => 'INR',
            'status' => AdvertiserPayment::STATUS_CREATED,
        ]);

        $verifyResponse = $this->postJson('/api/v1/advertiser/payments/verify', [
            'payment_id' => (string) $payment->id,
            'razorpay_order_id' => 'order_test_12345',
            'razorpay_payment_id' => 'pay_test_67890',
            'razorpay_signature' => 'mock_test_signature',
        ]);

        $verifyResponse->assertOk()
            ->assertJsonPath('data.payment_status', 'captured')
            ->assertJsonPath('data.is_paid', true)
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.publication_status', 'pending_admin_approval')
            ->assertJsonPath('data.start_date', '2026-11-25')
            ->assertJsonPath('data.end_date', '2027-02-25')
            ->assertJsonPath('data.next_action', 'awaiting_admin_approval');

        $this->assertSame(0, BrandPartner::count(), 'Brand Partner must not be published before admin approval');

        // 4. Verify GET /api/v1/advertiser/applications/{id} shows paid & awaiting_admin_approval
        $showResponse = $this->getJson("/api/v1/advertiser/applications/{$applicationId}");
        $showResponse->assertOk()
            ->assertJsonPath('data.application_id', $applicationId)
            ->assertJsonPath('data.status', 'pending_publish')
            ->assertJsonPath('data.payment_status', 'captured')
            ->assertJsonPath('data.is_paid', true)
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.publication_status', 'pending_admin_approval')
            ->assertJsonPath('data.next_action', 'awaiting_admin_approval');

        // 5. Admin approves & publishes the paid Brand Partner application
        $publishResponse = $this->postJson("/api/v1/admin/advertiser/applications/{$applicationId}/approve", [
            'start_date' => '2026-11-25',
            'admin_note' => 'Payment verified and creatives approved for publish.',
        ]);

        $publishResponse->assertOk()
            ->assertJsonPath('data.is_published', true)
            ->assertJsonPath('data.publication_status', 'published')
            ->assertJsonPath('data.start_date', '2026-11-25')
            ->assertJsonPath('data.end_date', '2027-02-25');

        $this->assertSame(1, BrandPartner::count(), 'Brand Partner should be created upon admin publish');
        $partner = BrandPartner::first();
        $this->assertNotNull($partner);
        $this->assertSame('2026-11-25', $partner->valid_from?->format('Y-m-d'));
        $this->assertSame('2027-02-25', $partner->valid_to?->format('Y-m-d'));
    }
}
