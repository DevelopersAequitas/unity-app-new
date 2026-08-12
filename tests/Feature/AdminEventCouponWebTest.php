<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\EventCoupon;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminEventCouponWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00'));
        $this->setUpDatabase();
    }

    private function setUpDatabase(): void
    {
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_coupons');
        Schema::dropIfExists('events');
        Schema::dropIfExists('admin_user_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('admin_users');
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

        Schema::create('admin_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('key')->unique();
            $table->timestamps();
        });

        Schema::create('admin_user_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->decimal('ticket_price', 10, 2)->default(0);
            $table->string('visibility')->default('members');
            $table->string('status')->default('scheduled');
            $table->boolean('is_active')->default(true);
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_view_event_coupons_management_page(): void
    {
        $admin = $this->createAdminUser();

        // Create sample coupons
        EventCoupon::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'WELCOME50',
            'name' => 'Welcome 50% Off',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'max_uses' => 100,
            'used_count' => 10,
            'is_active' => true,
        ]);

        EventCoupon::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'FREEPASS',
            'name' => '100% Free Pass',
            'discount_type' => 'full',
            'discount_value' => 100,
            'max_uses' => 50,
            'used_count' => 5,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->get('/admin/event-coupons');

        $response->assertOk();
        $response->assertSee('Event Coupons Management');
        $response->assertSee('WELCOME50');
        $response->assertSee('FREEPASS');
        $response->assertSee('Welcome 50% Off');
        $response->assertSee('100% OFF');
        $response->assertSee('50% OFF');
    }

    public function test_admin_can_create_coupon_with_manual_code(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin, 'admin')->post('/admin/event-coupons', [
            'code' => 'VIP2026',
            'name' => 'VIP 2026 Promo',
            'description' => 'Special promo code for VIP delegates',
            'discount_type' => 'percentage',
            'discount_value' => 75,
            'max_uses' => 200,
            'valid_from' => '2026-08-01',
            'valid_until' => '2026-12-31',
            'is_active' => '1',
        ]);

        $response->assertRedirect('/admin/event-coupons');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('event_coupons', [
            'code' => 'VIP2026',
            'name' => 'VIP 2026 Promo',
            'discount_type' => 'percentage',
            'discount_value' => 75,
            'max_uses' => 200,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_coupon_with_auto_generated_code(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin, 'admin')->post('/admin/event-coupons', [
            'generate_code' => '1',
            'code_prefix' => 'CONF',
            'name' => 'Conference Special',
            'discount_type' => 'fixed',
            'discount_value' => 500,
            'is_active' => '1',
        ]);

        $response->assertRedirect('/admin/event-coupons');
        $response->assertSessionHas('success');

        $coupon = EventCoupon::query()->where('name', 'Conference Special')->first();
        $this->assertNotNull($coupon);
        $this->assertStringStartsWith('CONF-', $coupon->code);
        $this->assertEquals('fixed', $coupon->discount_type);
        $this->assertEquals(500, $coupon->discount_value);
    }

    public function test_admin_can_update_existing_coupon(): void
    {
        $admin = $this->createAdminUser();

        $coupon = EventCoupon::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'EARLYBIRD',
            'name' => 'Early Bird 30%',
            'discount_type' => 'percentage',
            'discount_value' => 30,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->put("/admin/event-coupons/{$coupon->id}", [
            'code' => 'EARLYBIRD40',
            'name' => 'Early Bird 40% Off',
            'description' => 'Updated early bird discount',
            'discount_type' => 'percentage',
            'discount_value' => 40,
            'max_uses' => 150,
            'is_active' => '1',
        ]);

        $response->assertRedirect('/admin/event-coupons');
        $response->assertSessionHas('success');

        $coupon->refresh();
        $this->assertEquals('EARLYBIRD40', $coupon->code);
        $this->assertEquals('Early Bird 40% Off', $coupon->name);
        $this->assertEquals(40, $coupon->discount_value);
        $this->assertEquals(150, $coupon->max_uses);
    }

    public function test_admin_can_delete_coupon(): void
    {
        $admin = $this->createAdminUser();

        $coupon = EventCoupon::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'DELETE_ME',
            'discount_type' => 'full',
            'discount_value' => 100,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->delete("/admin/event-coupons/{$coupon->id}");

        $response->assertRedirect('/admin/event-coupons');
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('event_coupons', ['id' => $coupon->id]);
    }

    public function test_duplicate_coupon_code_fails_validation(): void
    {
        $admin = $this->createAdminUser();

        EventCoupon::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'UNIQUEPASS',
            'discount_type' => 'full',
            'discount_value' => 100,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->post('/admin/event-coupons', [
            'code' => 'UNIQUEPASS',
            'discount_type' => 'percentage',
            'discount_value' => 20,
        ]);

        $response->assertSessionHasErrors(['code']);
    }

    private function createAdminUser(): AdminUser
    {
        $admin = new AdminUser;
        $admin->id = (string) Str::uuid();
        $admin->name = 'Global Admin';
        $admin->email = 'admin_'.Str::random(6).'@example.com';
        $admin->save();

        $role = new Role;
        $role->id = (string) Str::uuid();
        $role->name = 'Global Admin';
        $role->key = 'global_admin';
        $role->save();

        DB::table('admin_user_roles')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $admin;
    }
}
