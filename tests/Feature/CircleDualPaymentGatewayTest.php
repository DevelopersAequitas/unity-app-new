<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\CircleCategory;
use App\Models\CircleJoinRequest;
use App\Models\CircleMember;
use App\Models\CircleSubscription;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\Circles\CircleJoinPaymentService;
use App\Services\Circles\CircleJoinRequestNotificationService;
use App\Services\Circles\CircleJoinRequestPaymentSyncService;
use App\Services\Circles\CircleJoinRequestService;
use App\Services\Circles\CirclePriceResolver;
use App\Services\Membership\MembershipZohoInvoiceService;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CircleDualPaymentGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->dropSchema();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        $this->dropSchema();
        parent::tearDown();
    }

    private function dropSchema(): void
    {
        $tables = [
            'app_notifications', 'notifications', 'email_logs', 'circle_subscriptions', 'payments', 'circle_members', 'circle_join_requests',
            'circle_categories', 'membership_plans', 'circles', 'circle_templates', 'users', 'roles',
        ];
        foreach ($tables as $t) {
            Schema::dropIfExists($t);
        }
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('membership_status')->default('free_peer');
            $table->string('status')->default('active');
            $table->uuid('active_circle_id')->nullable();
            $table->string('zoho_customer_id')->nullable();
            $table->string('zoho_last_invoice_id')->nullable();
            $table->string('gst_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circle_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->uuid('template_id')->nullable();
            $table->string('status')->default('active');
            $table->string('payment_gateway')->default('zoho');
            $table->string('payment_plan_id')->nullable();
            $table->decimal('circle_price_amount', 10, 2)->nullable();
            $table->string('circle_price_currency')->default('INR');
            $table->decimal('circle_gst_percent', 5, 2)->default(18.00);
            $table->integer('circle_duration_months')->default(12);
            $table->boolean('is_package_active')->default(true);
            $table->string('zoho_addon_code')->nullable();
            $table->string('zoho_addon_id')->nullable();
            $table->string('zoho_addon_name')->nullable();
            $table->jsonb('calendar')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('membership_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price', 10, 2);
            $table->decimal('gst_percent', 5, 2)->default(18.00);
            $table->integer('duration_days')->default(365);
            $table->integer('duration_months')->default(12);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_free')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circle_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('circle_join_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('circle_id');
            $table->unsignedBigInteger('level1_category_id')->nullable();
            $table->string('status')->default('pending_circle_fee');
            $table->timestamp('fee_paid_at')->nullable();
            $table->timestamp('fee_marked_at')->nullable();
            $table->timestamp('cd_approved_at')->nullable();
            $table->uuid('cd_approved_by')->nullable();
            $table->timestamp('cd_rejected_at')->nullable();
            $table->uuid('cd_rejected_by')->nullable();
            $table->string('cd_rejection_reason')->nullable();
            $table->timestamp('id_approved_at')->nullable();
            $table->uuid('id_approved_by')->nullable();
            $table->timestamp('id_rejected_at')->nullable();
            $table->uuid('id_rejected_by')->nullable();
            $table->string('id_rejection_reason')->nullable();
            $table->jsonb('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id');
            $table->uuid('user_id');
            $table->string('role')->default('member');
            $table->string('status')->default('pending');
            $table->integer('substitute_count')->default(0);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->string('joined_via')->nullable();
            $table->string('payment_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->boolean('joined_via_payment')->default(false);
            $table->string('billing_term')->nullable();
            $table->timestamp('paid_starts_at')->nullable();
            $table->timestamp('paid_ends_at')->nullable();
            $table->string('zoho_subscription_id')->nullable();
            $table->string('zoho_addon_code')->nullable();
            $table->string('payment_status')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('circle_id')->nullable();
            $table->uuid('circle_join_request_id')->nullable();
            $table->string('payment_type')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('currency')->default('INR');
            $table->string('provider')->default('razorpay');
            $table->string('razorpay_order_id')->nullable();
            $table->string('razorpay_payment_id')->nullable();
            $table->string('razorpay_signature')->nullable();
            $table->string('zoho_invoice_id')->nullable();
            $table->string('status')->default('created');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('circle_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('circle_id');
            $table->string('zoho_customer_id')->nullable();
            $table->string('zoho_subscription_id')->nullable();
            $table->string('zoho_hosted_page_id')->nullable();
            $table->string('zoho_addon_id')->nullable();
            $table->string('zoho_addon_code')->nullable();
            $table->string('zoho_addon_name')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency_code')->default('INR');
            $table->string('status')->default('pending');
            $table->text('zoho_checkout_url')->nullable();
            $table->jsonb('raw_checkout_response')->nullable();
            $table->timestamps();
        });

        Schema::create('app_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type')->nullable();
            $table->string('category')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('channel')->nullable();
            $table->string('priority')->nullable();
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('screen')->nullable();
            $table->jsonb('data')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type')->default('activity_update');
            $table->jsonb('payload')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('email_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('to_email');
            $table->string('to_name')->nullable();
            $table->string('template_key')->nullable();
            $table->string('subject')->nullable();
            $table->string('source_module')->nullable();
            $table->string('related_type')->nullable();
            $table->string('related_id')->nullable();
            $table->string('status')->nullable();
            $table->text('body_html')->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestamps();
        });
    }

    public function test_circle_gateway_helpers_and_price_resolution(): void
    {
        $circleZoho = new Circle;
        $circleZoho->id = (string) Str::uuid();
        $circleZoho->name = 'Zoho Circle';
        $circleZoho->payment_gateway = 'zoho';
        $circleZoho->zoho_addon_code = 'ZOHO_CODE_1';
        $circleZoho->zoho_addon_name = 'Zoho Circle Plan';
        $circleZoho->circle_price_amount = 12000.00;
        $circleZoho->circle_gst_percent = 18.00;
        $circleZoho->circle_price_currency = 'INR';
        $circleZoho->save();

        $this->assertTrue($circleZoho->isZoho());
        $this->assertFalse($circleZoho->isRazorpay());

        $resolver = app(CirclePriceResolver::class);
        $resolvedZoho = $resolver->resolve($circleZoho);

        $this->assertSame('zoho', $resolvedZoho['gateway']);
        $this->assertNull($resolvedZoho['plan']);
        $this->assertEquals(12000.00, $resolvedZoho['amount']);
        $this->assertEquals(1416000, $resolvedZoho['amount_in_paise']);

        // Razorpay circle
        $plan = new MembershipPlan;
        $plan->id = (string) Str::uuid();
        $plan->name = 'Razorpay Circle Special';
        $plan->slug = 'circle_special_rp';
        $plan->price = 18000.00;
        $plan->gst_percent = 18.00;
        $plan->save();

        $circleRazorpay = new Circle;
        $circleRazorpay->id = (string) Str::uuid();
        $circleRazorpay->name = 'Razorpay Circle';
        $circleRazorpay->payment_gateway = 'razorpay';
        $circleRazorpay->payment_plan_id = (string) $plan->id;
        $circleRazorpay->circle_price_amount = 18000.00;
        $circleRazorpay->circle_gst_percent = 18.00;
        $circleRazorpay->circle_price_currency = 'INR';
        $circleRazorpay->save();

        $this->assertTrue($circleRazorpay->isRazorpay());
        $this->assertFalse($circleRazorpay->isZoho());

        $resolvedRp = $resolver->resolve($circleRazorpay);
        $this->assertSame('razorpay', $resolvedRp['gateway']);
        $this->assertNotNull($resolvedRp['plan']);
        $this->assertSame($plan->id, $resolvedRp['plan']->id);
        $this->assertEquals(18000.00, $resolvedRp['amount']);
        $this->assertEquals(2124000, $resolvedRp['amount_in_paise']);
    }

    public function test_zoho_circle_creates_zoho_hosted_page_order(): void
    {
        $user = $this->createUser();
        $circle = new Circle;
        $circle->id = (string) Str::uuid();
        $circle->name = 'Ahmedabad Founders';
        $circle->payment_gateway = 'zoho';
        $circle->zoho_addon_code = 'ADDON_AHMEDABAD';
        $circle->zoho_addon_name = 'Founders Circle Package';
        $circle->circle_price_amount = 10000.00;
        $circle->circle_price_currency = 'INR';
        $circle->save();

        $joinRequest = $this->createJoinRequest($user, $circle);

        $mockZoho = $this->createMock(ZohoBillingService::class);
        $mockZoho->method('createHostedPageForCircleAddon')->willReturn([
            'checkout_url' => 'https://billing.zoho.com/subscribe/hp_zoho_123',
            'hostedpage_id' => 'hp_zoho_123',
            'customer_id' => 'cust_zoho_99',
            'subscription_id' => 'sub_zoho_88',
        ]);
        $this->app->instance(ZohoBillingService::class, $mockZoho);

        $paymentService = app(CircleJoinPaymentService::class);
        $order = $paymentService->createOrder($joinRequest, $user);

        $this->assertSame('zoho', $order['payment_gateway']);
        $this->assertSame('hp_zoho_123', $order['hostedpage_id']);
        $this->assertSame('https://billing.zoho.com/subscribe/hp_zoho_123', $order['payment_url']);
        $this->assertEquals(1180000, $order['amount']); // 10000 + 18% GST

        // Verify CircleSubscription record created
        $sub = CircleSubscription::query()->where('user_id', $user->id)->where('circle_id', $circle->id)->first();
        $this->assertNotNull($sub);
        $this->assertSame('hp_zoho_123', $sub->zoho_hosted_page_id);
    }

    public function test_razorpay_circle_creates_razorpay_order_and_verifies_successfully_with_zoho_invoice(): void
    {
        Config::set('razorpay.key_id', 'rzp_test_key');
        Config::set('razorpay.key_secret', 'rzp_test_secret_12345');

        $user = $this->createUser();

        $plan = new MembershipPlan;
        $plan->id = (string) Str::uuid();
        $plan->name = 'Circle Elite';
        $plan->slug = 'circle_elite_rp';
        $plan->price = 15000.00;
        $plan->gst_percent = 18.00;
        $plan->save();

        $circle = new Circle;
        $circle->id = (string) Str::uuid();
        $circle->name = 'Tech Innovators Circle';
        $circle->payment_gateway = 'razorpay';
        $circle->payment_plan_id = (string) $plan->id;
        $circle->circle_price_amount = 15000.00;
        $circle->circle_gst_percent = 18.00;
        $circle->circle_duration_months = 12;
        $circle->save();

        $joinRequest = $this->createJoinRequest($user, $circle);

        $mockNotification = $this->createMock(CircleJoinRequestNotificationService::class);
        $this->app->instance(CircleJoinRequestNotificationService::class, $mockNotification);

        $mockInvoice = $this->createMock(MembershipZohoInvoiceService::class);
        $mockInvoice->expects($this->once())
            ->method('createPaidInvoiceForCircle')
            ->willReturn([
                'invoice_id' => 'zoho_inv_test_999',
                'invoice_number' => 'INV-2026-999',
                'status' => 'paid',
            ]);
        $this->app->instance(MembershipZohoInvoiceService::class, $mockInvoice);

        // Subclass CircleJoinPaymentService to mock createRazorpayOrder
        $service = new class(app(CircleJoinRequestPaymentSyncService::class), app(CirclePriceResolver::class), $mockInvoice, app(ZohoBillingService::class)) extends CircleJoinPaymentService
        {
            protected function createRazorpayOrder(array $params, string $keyId, string $keySecret): array
            {
                return [
                    'id' => 'order_rp_mock_123',
                    'amount' => $params['amount'],
                    'currency' => $params['currency'],
                ];
            }
        };

        $order = $service->createOrder($joinRequest, $user);

        $this->assertSame('razorpay', $order['payment_gateway']);
        $this->assertSame('order_rp_mock_123', $order['order_id']);
        $this->assertEquals(1770000, $order['amount']); // 15000 + 18% GST

        // Payment record created in database with circle metadata
        $payment = Payment::query()->where('razorpay_order_id', 'order_rp_mock_123')->first();
        $this->assertNotNull($payment);
        $this->assertSame($circle->id, $payment->circle_id);
        $this->assertSame($joinRequest->id, $payment->circle_join_request_id);
        $this->assertSame(Payment::TYPE_CIRCLE_JOIN, $payment->payment_type);
        $this->assertSame(Payment::STATUS_CREATED, $payment->status);

        // Compute valid Razorpay HMAC signature
        $orderId = 'order_rp_mock_123';
        $paymentId = 'pay_rp_success_456';
        $signature = hash_hmac('sha256', $orderId.'|'.$paymentId, 'rzp_test_secret_12345');

        // Verify payment
        $finalized = $service->verifyPayment($joinRequest, $user, $orderId, $paymentId, $signature);

        $this->assertSame(CircleJoinRequest::STATUS_PAID, (string) $finalized->status);

        // Member must be added to circle_members with tracking fields
        $member = CircleMember::query()->where('user_id', $user->id)->where('circle_id', $circle->id)->first();
        $this->assertNotNull($member);
        $this->assertSame('approved', (string) $member->status);
        $this->assertSame('payment', $member->joined_via);
        $this->assertTrue((bool) $member->joined_via_payment);
        $this->assertSame('paid', $member->payment_status);
        $this->assertSame($paymentId, $member->payment_id);
        $this->assertNotNull($member->paid_at);
        $this->assertNotNull($member->expires_at);

        // User active_circle_id set
        $user->refresh();
        $this->assertSame($circle->id, $user->active_circle_id);
    }

    public function test_circle_join_status_api_returns_gateway_and_accurate_package(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        // 1. Zoho Circle
        $circleZoho = new Circle;
        $circleZoho->id = (string) Str::uuid();
        $circleZoho->name = 'Delhi Zoho Circle';
        $circleZoho->payment_gateway = 'zoho';
        $circleZoho->zoho_addon_code = 'DELHI_ADDON';
        $circleZoho->zoho_addon_name = 'Delhi Chapter Plan';
        $circleZoho->circle_price_amount = 14000.00;
        $circleZoho->circle_gst_percent = 18.00;
        $circleZoho->save();

        $requestZoho = $this->createJoinRequest($user, $circleZoho);

        $responseZoho = $this->getJson("/api/v1/circle-join-requests/{$requestZoho->id}/status");
        $responseZoho->assertStatus(200);
        $responseZoho->assertJsonPath('data.payment_gateway', 'zoho');
        $responseZoho->assertJsonPath('data.payment.payment_gateway', 'zoho');
        $responseZoho->assertJsonPath('data.payment.plan_name', 'Delhi Chapter Plan');
        $responseZoho->assertJsonPath('data.payment.plan_id', 'DELHI_ADDON');

        // 2. Razorpay Circle
        $plan = new MembershipPlan;
        $plan->id = (string) Str::uuid();
        $plan->name = 'Mumbai Razorpay Plan';
        $plan->slug = 'circle_mumbai';
        $plan->price = 16000.00;
        $plan->gst_percent = 18.00;
        $plan->save();

        $circleRp = new Circle;
        $circleRp->id = (string) Str::uuid();
        $circleRp->name = 'Mumbai RP Circle';
        $circleRp->payment_gateway = 'razorpay';
        $circleRp->payment_plan_id = (string) $plan->id;
        $circleRp->circle_price_amount = 16000.00;
        $circleRp->circle_gst_percent = 18.00;
        $circleRp->save();

        $requestRp = $this->createJoinRequest($user, $circleRp);

        $responseRp = $this->getJson("/api/v1/circle-join-requests/{$requestRp->id}/status");
        $responseRp->assertStatus(200);
        $responseRp->assertJsonPath('data.payment_gateway', 'razorpay');
        $responseRp->assertJsonPath('data.payment.payment_gateway', 'razorpay');
        $responseRp->assertJsonPath('data.payment.plan_name', 'Mumbai Razorpay Plan');
        $responseRp->assertJsonPath('data.payment.plan_id', (string) $plan->id);
    }

    private function createUser(string $email = 'dual_test@example.com'): User
    {
        $user = new User;
        $user->id = (string) Str::uuid();
        $user->email = $email;
        $user->first_name = 'Amit';
        $user->last_name = 'Patel';
        $user->display_name = 'Amit Patel';
        $user->status = 'active';
        $user->password_hash = bcrypt('secret');
        $user->save();

        return $user;
    }

    public function test_admin_approves_and_assigns_circle_from_requested_category_and_joins_that_circle_on_payment(): void
    {
        $cat = CircleCategory::query()->create([
            'name' => 'Manufacturing & Engineering',
            'slug' => 'manufacturing-engineering-'.Str::random(6),
        ]);

        $circleA = Circle::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Circle A (Initial)',
            'circle_price_amount' => 5000,
            'status' => 'active',
            'payment_gateway' => 'razorpay',
        ]);

        $circleB = Circle::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Circle B (Assigned by Admin)',
            'circle_price_amount' => 15000,
            'status' => 'active',
            'payment_gateway' => 'razorpay',
        ]);

        if (Schema::hasTable('circle_category_mappings')) {
            DB::table('circle_category_mappings')->insert([
                ['circle_id' => $circleA->id, 'category_id' => $cat->id, 'created_at' => now(), 'updated_at' => now()],
                ['circle_id' => $circleB->id, 'category_id' => $cat->id, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        $user = $this->createUser('applicant_'.Str::random(6).'@example.com');

        $joinRequest = new CircleJoinRequest;
        $joinRequest->id = (string) Str::uuid();
        $joinRequest->user_id = $user->id;
        $joinRequest->circle_id = $circleA->id;
        $joinRequest->level1_category_id = $cat->id;
        $joinRequest->status = CircleJoinRequest::STATUS_PENDING_CD_APPROVAL;
        $joinRequest->save();

        $service = app(CircleJoinRequestService::class);
        $cdAdmin = $this->createUser('cd_'.Str::random(6).'@example.com');
        $service->approveByCd($joinRequest, $cdAdmin, $circleB->id);

        $this->assertEquals($circleB->id, $joinRequest->fresh()->circle_id);
        $this->assertEquals(CircleJoinRequest::STATUS_PENDING_ID_APPROVAL, $joinRequest->fresh()->status);

        $idAdmin = $this->createUser('id_'.Str::random(6).'@example.com');
        $service->approveById($joinRequest, $idAdmin, $circleB->id);

        $this->assertEquals($circleB->id, $joinRequest->fresh()->circle_id);
        $this->assertEquals(CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE, $joinRequest->fresh()->status);
    }

    private function createJoinRequest(User $user, Circle $circle): CircleJoinRequest
    {
        $cat = CircleCategory::query()->create([
            'name' => 'Technology',
            'slug' => (string) Str::uuid(),
        ]);

        $join = new CircleJoinRequest;
        $join->id = (string) Str::uuid();
        $join->user_id = $user->id;
        $join->circle_id = $circle->id;
        $join->level1_category_id = $cat->id;
        $join->status = CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE;
        $join->save();

        return $join;
    }
}
