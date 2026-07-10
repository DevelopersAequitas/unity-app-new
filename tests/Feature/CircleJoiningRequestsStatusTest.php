<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\CircleCategory;
use App\Models\CircleJoinRequest;
use App\Models\CircleTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CircleJoiningRequestsStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();

        $roleKeys = ['global_admin', 'industry_director', 'ded', 'circle_leader', 'chair', 'vice_chair', 'secretary', 'member'];
        foreach ($roleKeys as $k) {
            $role = new Role;
            $role->id = (string) Str::uuid();
            $role->name = ucfirst(str_replace('_', ' ', $k));
            $role->key = $k;
            $role->save();
        }
    }

    public function test_authenticated_user_can_fetch_their_joining_request_status(): void
    {
        $user = $this->createUser();
        $circle = $this->createCircle();
        $request = $this->createJoinRequest($user, $circle, CircleJoinRequest::STATUS_PENDING_CD_APPROVAL);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/circle-join-requests/{$request->id}/status");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => true,
                'message' => 'Circle joining request status fetched successfully.',
            ])
            ->assertJsonPath('data.id', $request->id)
            ->assertJsonPath('data.status', CircleJoinRequest::STATUS_PENDING_CD_APPROVAL);
    }

    public function test_user_cannot_fetch_another_users_joining_request(): void
    {
        $user1 = $this->createUser('user1@example.com');
        $user2 = $this->createUser('user2@example.com');
        $circle = $this->createCircle();
        $request = $this->createJoinRequest($user1, $circle, CircleJoinRequest::STATUS_PENDING_CD_APPROVAL);

        Sanctum::actingAs($user2);

        $response = $this->getJson("/api/v1/circle-join-requests/{$request->id}/status");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'status' => false,
                'message' => 'Circle joining request not found.',
                'data' => null,
                'meta' => null,
            ]);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/circle-join-requests/' . (string) Str::uuid() . '/status');
        $response->assertStatus(401);
    }

    public function test_pending_cd_approval_response(): void
    {
        $user = $this->createUser();
        $circle = $this->createCircle();
        $request = $this->createJoinRequest($user, $circle, CircleJoinRequest::STATUS_PENDING_CD_APPROVAL);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/circle-join-requests/{$request->id}/status");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', CircleJoinRequest::STATUS_PENDING_CD_APPROVAL)
            ->assertJsonPath('data.cd_approval.status', 'pending')
            ->assertJsonPath('data.id_approval.status', 'pending')
            ->assertJsonPath('data.can_pay', false);
    }

    public function test_pending_id_approval_response(): void
    {
        $user = $this->createUser();
        $circle = $this->createCircle();
        $request = $this->createJoinRequest($user, $circle, CircleJoinRequest::STATUS_PENDING_ID_APPROVAL);
        $request->update([
            'cd_approved_at' => now(),
            'cd_approved_by' => (string) Str::uuid(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/circle-join-requests/{$request->id}/status");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', CircleJoinRequest::STATUS_PENDING_ID_APPROVAL)
            ->assertJsonPath('data.cd_approval.status', 'approved')
            ->assertJsonPath('data.id_approval.status', 'pending')
            ->assertJsonPath('data.can_pay', false);
    }

    public function test_pending_circle_fee_response_includes_payment_details_and_url(): void
    {
        $user = $this->createUser();
        $circle = $this->createCircle();
        $request = $this->createJoinRequest($user, $circle, CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE);
        $request->update([
            'cd_approved_at' => now(),
            'cd_approved_by' => (string) Str::uuid(),
            'id_approved_at' => now(),
            'id_approved_by' => (string) Str::uuid(),
        ]);

        // Mock ZohoBillingService
        $mock = $this->createMock(\App\Support\Zoho\ZohoBillingService::class);
        $mock->method('createHostedPageForCircleAddon')->willReturn([
            'checkout_url' => 'https://checkout.zoho.com/test-url',
            'hostedpage_id' => 'hp_123',
            'customer_id' => 'cust_123',
            'subscription_id' => 'sub_123',
            'raw' => ['hostedpage' => ['url' => 'https://checkout.zoho.com/test-url']],
        ]);
        $this->app->instance(\App\Support\Zoho\ZohoBillingService::class, $mock);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/circle-join-requests/{$request->id}/status");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE)
            ->assertJsonPath('data.payment.required', true)
            ->assertJsonPath('data.payment.status', 'unpaid')
            ->assertJsonPath('data.payment.payment_url', 'https://checkout.zoho.com/test-url')
            ->assertJsonPath('data.can_pay', true);
    }

    public function test_rejected_by_cd_response(): void
    {
        $user = $this->createUser();
        $circle = $this->createCircle();
        $request = $this->createJoinRequest($user, $circle, CircleJoinRequest::STATUS_REJECTED_BY_CD);
        $request->update([
            'cd_rejected_at' => now(),
            'cd_rejected_by' => (string) Str::uuid(),
            'cd_rejection_reason' => 'Invalid qualifications',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/circle-join-requests/{$request->id}/status");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', CircleJoinRequest::STATUS_REJECTED_BY_CD)
            ->assertJsonPath('data.rejection.is_rejected', true)
            ->assertJsonPath('data.rejection.rejected_by', 'cd')
            ->assertJsonPath('data.rejection.reason', 'Invalid qualifications')
            ->assertJsonPath('data.payment.payment_url', null)
            ->assertJsonPath('data.can_pay', false);
    }

    public function test_rejected_by_id_response(): void
    {
        $user = $this->createUser();
        $circle = $this->createCircle();
        $request = $this->createJoinRequest($user, $circle, CircleJoinRequest::STATUS_REJECTED_BY_ID);
        $request->update([
            'id_rejected_at' => now(),
            'id_rejected_by' => (string) Str::uuid(),
            'id_rejection_reason' => 'Director declined request',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/circle-join-requests/{$request->id}/status");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', CircleJoinRequest::STATUS_REJECTED_BY_ID)
            ->assertJsonPath('data.rejection.is_rejected', true)
            ->assertJsonPath('data.rejection.rejected_by', 'id')
            ->assertJsonPath('data.rejection.reason', 'Director declined request')
            ->assertJsonPath('data.payment.payment_url', null)
            ->assertJsonPath('data.can_pay', false);
    }

    public function test_paid_response(): void
    {
        $user = $this->createUser();
        $circle = $this->createCircle();
        $request = $this->createJoinRequest($user, $circle, CircleJoinRequest::STATUS_PAID);
        $request->update([
            'fee_paid_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/circle-join-requests/{$request->id}/status");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', CircleJoinRequest::STATUS_PAID)
            ->assertJsonPath('data.payment.status', 'paid')
            ->assertJsonPath('data.can_pay', false);
    }

    public function test_circle_member_response(): void
    {
        $user = $this->createUser();
        $circle = $this->createCircle();
        $request = $this->createJoinRequest($user, $circle, CircleJoinRequest::STATUS_CIRCLE_MEMBER);
        $request->update([
            'fee_paid_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/circle-join-requests/{$request->id}/status");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', CircleJoinRequest::STATUS_CIRCLE_MEMBER)
            ->assertJsonPath('data.payment.status', 'paid')
            ->assertJsonPath('data.can_pay', false);
    }

    public function test_completing_only_one_approval_does_not_send_payment_email(): void
    {
        Mail::fake();

        $admin = $this->createUser('admin@example.com');
        $user = $this->createUser();
        $circle = $this->createCircle();
        $request = $this->createJoinRequest($user, $circle, CircleJoinRequest::STATUS_PENDING_CD_APPROVAL);

        $service = app(\App\Services\Circles\CircleJoinRequestService::class);
        $service->approveByCd($request, $admin);

        Mail::assertNotSent(\App\Mail\CircleJoinCongratulationsMail::class);
    }

    public function test_completing_second_required_approval_sends_one_congratulations_email(): void
    {
        Mail::fake();

        // Mock ZohoBillingService
        $mock = $this->createMock(\App\Support\Zoho\ZohoBillingService::class);
        $mock->method('createHostedPageForCircleAddon')->willReturn([
            'checkout_url' => 'https://checkout.zoho.com/test-url',
            'hostedpage_id' => 'hp_123',
            'customer_id' => 'cust_123',
            'subscription_id' => 'sub_123',
            'raw' => ['hostedpage' => ['url' => 'https://checkout.zoho.com/test-url']],
        ]);
        $this->app->instance(\App\Support\Zoho\ZohoBillingService::class, $mock);

        $admin = $this->createUser('admin@example.com');
        $user = $this->createUser();
        $circle = $this->createCircle();
        $request = $this->createJoinRequest($user, $circle, CircleJoinRequest::STATUS_PENDING_ID_APPROVAL);
        $request->update([
            'cd_approved_at' => now()->subMinutes(5),
            'cd_approved_by' => $admin->id,
        ]);

        $service = app(\App\Services\Circles\CircleJoinRequestService::class);
        $service->approveById($request, $admin);

        Mail::assertSent(\App\Mail\CircleJoinCongratulationsMail::class, 1);
    }

    public function test_repeated_approval_does_not_send_duplicate_email(): void
    {
        Mail::fake();

        // Mock ZohoBillingService
        $mock = $this->createMock(\App\Support\Zoho\ZohoBillingService::class);
        $mock->method('createHostedPageForCircleAddon')->willReturn([
            'checkout_url' => 'https://checkout.zoho.com/test-url',
            'hostedpage_id' => 'hp_123',
            'customer_id' => 'cust_123',
            'subscription_id' => 'sub_123',
            'raw' => ['hostedpage' => ['url' => 'https://checkout.zoho.com/test-url']],
        ]);
        $this->app->instance(\App\Support\Zoho\ZohoBillingService::class, $mock);

        $admin = $this->createUser('admin@example.com');
        $user = $this->createUser();
        $circle = $this->createCircle();
        $request = $this->createJoinRequest($user, $circle, CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE);
        $request->update([
            'cd_approved_at' => now(),
            'cd_approved_by' => $admin->id,
            'id_approved_at' => now(),
            'id_approved_by' => $admin->id,
        ]);

        // Manually trigger congratulations twice
        $notifier = app(\App\Services\Circles\CircleJoinRequestNotificationService::class);
        $notifier->sendJoinRequestApprovedCongratulations($request);
        $notifier->sendJoinRequestApprovedCongratulations($request);

        Mail::assertSent(\App\Mail\CircleJoinCongratulationsMail::class, 1);
    }

    public function test_rejection_does_not_send_payment_email(): void
    {
        Mail::fake();

        $admin = $this->createUser('admin@example.com');
        $user = $this->createUser();
        $circle = $this->createCircle();
        $request = $this->createJoinRequest($user, $circle, CircleJoinRequest::STATUS_PENDING_CD_APPROVAL);

        $service = app(\App\Services\Circles\CircleJoinRequestService::class);
        $service->rejectByCd($request, $admin, 'Rejection reason');

        Mail::assertNotSent(\App\Mail\CircleJoinCongratulationsMail::class);
    }

    public function test_payment_url_generation_failure_is_logged_and_does_not_crash(): void
    {
        Mail::fake();

        // Mock ZohoBillingService to throw exception
        $mock = $this->createMock(\App\Support\Zoho\ZohoBillingService::class);
        $mock->method('createHostedPageForCircleAddon')->willThrowException(new \RuntimeException('Zoho down'));
        $this->app->instance(\App\Support\Zoho\ZohoBillingService::class, $mock);

        $admin = $this->createUser('admin@example.com');
        $user = $this->createUser();
        $circle = $this->createCircle();
        $request = $this->createJoinRequest($user, $circle, CircleJoinRequest::STATUS_PENDING_ID_APPROVAL);
        $request->update([
            'cd_approved_at' => now()->subMinutes(5),
            'cd_approved_by' => $admin->id,
        ]);

        $service = app(\App\Services\Circles\CircleJoinRequestService::class);
        
        // This approval should proceed successfully without crashing
        $result = $service->approveById($request, $admin);
        
        $this->assertEquals(CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE, $result->status);
        Mail::assertSent(\App\Mail\CircleJoinCongratulationsMail::class, function ($mail) {
            return $mail->paymentUrl === null;
        });
    }

    private function createUser(string $email = 'test@example.com'): User
    {
        $user = new User;
        $user->id = (string) Str::uuid();
        $user->email = $email;
        $user->first_name = 'First';
        $user->last_name = 'Last';
        $user->display_name = 'Display';
        $user->status = 'active';
        $user->password_hash = bcrypt('password');
        $user->save();

        return $user;
    }

    private function createCircle(): Circle
    {
        $template = new CircleTemplate;
        $template->id = (string) Str::uuid();
        $template->name = 'Template';
        $template->slug = (string) Str::uuid();
        $template->save();

        $circle = new Circle;
        $circle->id = (string) Str::uuid();
        $circle->name = 'Test Circle';
        $circle->slug = (string) Str::uuid();
        $circle->template_id = $template->id;
        $circle->status = 'active';
        $circle->circle_price_amount = 5000.00;
        $circle->circle_price_currency = 'INR';
        $circle->zoho_addon_code = 'ADDON_123';
        $circle->save();

        return $circle;
    }

    private function createJoinRequest(User $user, Circle $circle, string $status): CircleJoinRequest
    {
        $category = CircleCategory::query()->create([
            'name' => 'Category',
            'slug' => (string) Str::uuid(),
        ]);

        $request = new CircleJoinRequest;
        $request->id = (string) Str::uuid();
        $request->user_id = $user->id;
        $request->circle_id = $circle->id;
        $request->level1_category_id = $category->id;
        $request->status = $status;
        $request->save();

        return $request;
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('key')->unique();
            $table->timestamps();
        });

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->uuid('template_id')->nullable();
            $table->string('status')->default('active');
            $table->decimal('circle_price_amount', 10, 2)->nullable();
            $table->string('circle_price_currency')->nullable();
            $table->string('zoho_addon_code')->nullable();
            $table->string('zoho_addon_id')->nullable();
            $table->string('zoho_addon_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circle_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('circle_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('membership_status')->default('visitor');
            $table->string('status')->default('inactive');
            $table->string('zoho_customer_id')->nullable();
            $table->string('zoho_subscription_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circle_join_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('circle_id');
            $table->text('reason_for_joining')->nullable();
            $table->unsignedBigInteger('level1_category_id')->nullable();
            $table->string('status')->nullable();
            $table->uuid('cd_approved_by')->nullable();
            $table->timestamp('cd_approved_at')->nullable();
            $table->uuid('cd_rejected_by')->nullable();
            $table->timestamp('cd_rejected_at')->nullable();
            $table->text('cd_rejection_reason')->nullable();
            $table->uuid('id_approved_by')->nullable();
            $table->timestamp('id_approved_at')->nullable();
            $table->uuid('id_rejected_by')->nullable();
            $table->timestamp('id_rejected_at')->nullable();
            $table->text('id_rejection_reason')->nullable();
            $table->string('ded_approval_status')->nullable();
            $table->timestamp('ded_approved_at')->nullable();
            $table->timestamp('fee_paid_at')->nullable();
            $table->timestamp('fee_marked_at')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->jsonb('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id');
            $table->uuid('user_id');
            $table->string('role')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->string('source_event')->nullable();
            $table->string('status')->nullable();
            $table->text('body_html')->nullable();
            $table->jsonb('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->string('triggered_by')->nullable();
            $table->uuid('triggered_user_id')->nullable();
            $table->string('mail_provider')->nullable();
            $table->string('queue_id')->nullable();
            $table->string('message_id')->nullable();
            $table->text('body_text')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->nullable();
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
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency_code')->nullable();
            $table->string('status')->nullable();
            $table->text('zoho_checkout_url')->nullable();
            $table->jsonb('raw_checkout_response')->nullable();
            $table->timestamps();
        });
    }
}
