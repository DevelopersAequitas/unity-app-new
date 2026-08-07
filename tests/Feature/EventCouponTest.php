<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\EventCoupon;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventCouponTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-07 12:00:00'));
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_create_list_update_and_delete_event_coupons(): void
    {
        $admin = $this->createUser();
        Sanctum::actingAs($admin);

        // 1. Create Coupon Manually
        $response = $this->postJson('/api/v1/admin/event-coupons', [
            'code' => 'SUMMER60',
            'name' => 'Summer Discount',
            'discount_type' => 'percentage',
            'discount_value' => 60,
            'max_uses' => 100,
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'SUMMER60')
            ->assertJsonPath('data.discount_value', 60);

        $couponId = $response->json('data.id');

        // 2. Generate Random Code Endpoint
        $genResponse = $this->postJson('/api/v1/admin/event-coupons/generate-code', ['prefix' => 'VIP']);
        $genResponse->assertOk()
            ->assertJsonPath('success', true);
        $this->assertStringStartsWith('VIP-', (string) $genResponse->json('data.code'));

        // 3. Create Coupon with Auto-generated Code
        $autoResponse = $this->postJson('/api/v1/admin/event-coupons', [
            'name' => 'VIP Access',
            'discount_type' => 'full',
            'generate_code' => true,
        ]);
        $autoResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.discount_type', 'full');

        // 4. List Coupons
        $listResponse = $this->getJson('/api/v1/admin/event-coupons');
        $listResponse->assertOk()
            ->assertJsonPath('success', true);

        // 5. Update Coupon
        $updateResponse = $this->putJson("/api/v1/admin/event-coupons/{$couponId}", [
            'name' => 'Updated Summer Discount 60%',
            'discount_value' => 60,
        ]);
        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Updated Summer Discount 60%');

        // 6. Delete Coupon
        $deleteResponse = $this->deleteJson("/api/v1/admin/event-coupons/{$couponId}");
        $deleteResponse->assertOk();
    }

    public function test_registration_request_with_invalid_coupon_returns_422(): void
    {
        $user = $this->createUser();
        $circle = $this->createCircle('Circle A');
        $eventId = (string) Str::uuid();
        $occurrenceId = (string) Str::uuid();

        $this->insertEvent($eventId, $circle->id, 'Paid Conference', 1000);
        $this->insertOccurrence($occurrenceId, $eventId);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/events/{$eventId}/occurrences/{$occurrenceId}/registration-request", [
            'coupon_code' => 'INVALIDCODE123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid or expired coupon code');
    }

    public function test_registration_request_with_valid_percentage_coupon_auto_approves_and_discounts_payment(): void
    {
        $user = $this->createUser();
        $circle = $this->createCircle('Circle A');
        $eventId = (string) Str::uuid();
        $occurrenceId = (string) Str::uuid();

        $this->insertEvent($eventId, $circle->id, 'Paid Conference', 1000);
        $this->insertOccurrence($occurrenceId, $eventId);

        // Create 60% OFF Coupon
        EventCoupon::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'VIPPASS60',
            'discount_type' => 'percentage',
            'discount_value' => 60,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/events/{$eventId}/occurrences/{$occurrenceId}/registration-request", [
            'coupon_code' => 'VIPPASS60',
            'request_reason' => 'Want to attend with coupon',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Registration successful')
            ->assertJsonPath('data.user_registration.status', 'approved')
            ->assertJsonPath('data.amount', '400')
            ->assertJsonPath('data.payment_required', true);

        // Verify coupon used count was incremented
        $coupon = EventCoupon::query()->where('code', 'VIPPASS60')->first();
        $this->assertEquals(1, $coupon->used_count);
    }

    public function test_registration_request_with_full_discount_coupon_auto_approves_and_generates_qr(): void
    {
        $user = $this->createUser();
        $circle = $this->createCircle('Circle A');
        $eventId = (string) Str::uuid();
        $occurrenceId = (string) Str::uuid();

        $this->insertEvent($eventId, $circle->id, 'Paid Summit', 1000);
        $this->insertOccurrence($occurrenceId, $eventId);

        // Create 100% Full Discount Coupon
        EventCoupon::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'FREEPASS100',
            'discount_type' => 'full',
            'discount_value' => 100,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/events/{$eventId}/occurrences/{$occurrenceId}/registration-request", [
            'coupon_code' => 'FREEPASS100',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_registration.status', 'approved')
            ->assertJsonPath('data.payment_required', false);
    }

    public function test_direct_registration_with_valid_fixed_amount_coupon(): void
    {
        $user = $this->createUser();
        $circle = $this->createCircle('Circle A');
        $eventId = (string) Str::uuid();
        $occurrenceId = (string) Str::uuid();

        $this->insertEvent($eventId, $circle->id, 'Paid Workshop', 1000);
        $this->insertOccurrence($occurrenceId, $eventId);

        // Add user to circle
        DB::table('circle_members')->insert([
            'id' => (string) Str::uuid(),
            'circle_id' => $circle->id,
            'user_id' => $user->id,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create ₹600 Fixed Discount Coupon
        EventCoupon::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'FLAT600OFF',
            'discount_type' => 'fixed',
            'discount_value' => 600,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/events/{$eventId}/occurrences/{$occurrenceId}/register", [
            'coupon_code' => 'FLAT600OFF',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', '400')
            ->assertJsonPath('data.payment_required', true);
    }

    private function createUser(): User
    {
        $user = new User;
        $user->id = (string) Str::uuid();
        $user->first_name = 'Test';
        $user->last_name = 'User';
        $user->email = 'user_'.Str::random(6).'@example.com';
        $user->password_hash = Hash::make('password');
        $user->save();

        return $user;
    }

    private function createCircle(string $name): Circle
    {
        $circle = new Circle;
        $circle->id = (string) Str::uuid();
        $circle->name = $name;
        $circle->save();

        return $circle;
    }

    private function insertEvent(string $id, string $circleId, string $title, float $ticketPrice): void
    {
        DB::table('events')->insert([
            'id' => $id,
            'circle_id' => $circleId,
            'title' => $title,
            'is_paid' => true,
            'ticket_price' => $ticketPrice,
            'visibility' => 'members',
            'status' => 'scheduled',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertOccurrence(string $id, string $eventId): void
    {
        DB::table('event_occurrences')->insert([
            'id' => $id,
            'event_id' => $eventId,
            'occurrence_date' => '2026-08-10',
            'start_at' => '2026-08-10 10:00:00',
            'end_at' => '2026-08-10 12:00:00',
            'status' => 'scheduled',
            'registered_count' => 0,
            'checked_in_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function setUpDatabase(): void
    {
        Schema::dropIfExists('event_registration_requests');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_coupons');
        Schema::dropIfExists('event_occurrences');
        Schema::dropIfExists('events');
        Schema::dropIfExists('circle_members');
        Schema::dropIfExists('circles');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password_hash');
            $table->string('membership_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id');
            $table->uuid('user_id');
            $table->string('status')->default('approved');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id')->nullable();
            $table->string('title')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->decimal('ticket_price', 10, 2)->default(0);
            $table->string('visibility')->default('members');
            $table->string('event_type')->nullable();
            $table->string('status')->default('scheduled');
            $table->boolean('is_active')->default(true);
            $table->boolean('visitor_registration_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_occurrences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->date('occurrence_date')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->string('status')->default('scheduled');
            $table->integer('registered_count')->default(0);
            $table->integer('checked_in_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_coupons', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 255)->nullable();
            $table->text('description')->nullable();
            $table->enum('discount_type', ['full', 'percentage', 'fixed'])->default('percentage');
            $table->decimal('discount_value', 12, 2)->default(0.00);
            $table->integer('max_uses')->nullable();
            $table->integer('used_count')->default(0);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('event_id')->nullable();
            $table->uuid('occurrence_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('occurrence_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('qr_token')->nullable();
            $table->string('qr_code_path')->nullable();
            $table->string('qr_code_url')->nullable();
            $table->string('qr_code_svg')->nullable();
            $table->string('status')->nullable();
            $table->string('checkin_status')->nullable();
            $table->boolean('payment_required')->default(false);
            $table->string('payment_status')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency', 10)->default('INR');
            $table->uuid('coupon_id')->nullable();
            $table->string('coupon_code', 50)->nullable();
            $table->decimal('original_amount', 12, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->string('registration_type')->nullable();
            $table->string('visitor_registration_form_url')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_registration_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('occurrence_id');
            $table->uuid('user_id');
            $table->uuid('event_circle_id')->nullable();
            $table->string('status')->default('pending');
            $table->text('request_reason')->nullable();
            $table->uuid('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('registration_id')->nullable();
            $table->uuid('coupon_id')->nullable();
            $table->string('coupon_code', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
