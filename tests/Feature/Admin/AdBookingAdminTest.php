<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdBooking;
use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdBookingAdminTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestSchemas();
    }

    private function createTestSchemas(): void
    {
        if (! Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('first_name', 100)->nullable();
                $table->string('last_name', 100)->nullable();
                $table->string('email')->unique();
                $table->string('password')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('key')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_user_roles')) {
            Schema::create('admin_user_roles', function (Blueprint $table): void {
                $table->uuid('user_id');
                $table->uuid('role_id');
            });
        }

        if (! Schema::hasTable('role_module_access')) {
            Schema::create('role_module_access', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('role_id');
                $table->uuid('module_id');
            });
        }

        if (! Schema::hasTable('admin_modules')) {
            Schema::create('admin_modules', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->unique();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
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

        if (! Schema::hasTable('ads')) {
            Schema::create('ads', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->string('subtitle')->nullable();
                $table->text('description')->nullable();
                $table->string('image_path')->nullable();
                $table->string('redirect_url')->nullable();
                $table->string('button_text')->nullable();
                $table->string('placement')->nullable();
                $table->string('page_name')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->uuid('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ad_bookings')) {
            Schema::create('ad_bookings', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('title');
                $table->string('subtitle')->nullable();
                $table->text('description')->nullable();
                $table->string('image_file_id')->nullable();
                $table->string('redirect_url')->nullable();
                $table->string('button_text')->nullable();
                $table->string('placement')->nullable();
                $table->string('page_name')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->string('status')->default('pending');
                $table->text('admin_remarks')->nullable();
                $table->uuid('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->uuid('ad_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_admin_can_approve_ad_booking_when_authenticated_via_sanctum(): void
    {
        $adminUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
        ]);

        $booking = AdBooking::create([
            'id' => (string) Str::uuid(),
            'user_id' => $adminUser->id,
            'title' => 'Test Banner Ad',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($adminUser, 'sanctum')
            ->postJson("/api/v1/admin/ad-bookings/{$booking->id}/review", [
                'status' => 'approved',
                'admin_remarks' => 'Looks good',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('ad_bookings', [
            'id' => $booking->id,
            'status' => 'approved',
            'reviewed_by' => $adminUser->id,
            'admin_remarks' => 'Looks good',
        ]);
    }

    public function test_admin_can_reject_ad_booking_when_authenticated_via_sanctum(): void
    {
        $adminUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin2@example.com',
        ]);

        $booking = AdBooking::create([
            'id' => (string) Str::uuid(),
            'user_id' => $adminUser->id,
            'title' => 'Test Banner Ad 2',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($adminUser, 'sanctum')
            ->postJson("/api/v1/admin/ad-bookings/{$booking->id}/review", [
                'status' => 'rejected',
                'admin_remarks' => 'Not matching guidelines',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('ad_bookings', [
            'id' => $booking->id,
            'status' => 'rejected',
            'reviewed_by' => $adminUser->id,
            'admin_remarks' => 'Not matching guidelines',
        ]);
    }

    private function createGlobalAdminUser(string $email): AdminUser
    {
        $role = DB::table('roles')->where('key', 'global_admin')->first();
        if (! $role) {
            $roleId = (string) Str::uuid();
            DB::table('roles')->insert([
                'id' => $roleId,
                'name' => 'Global Admin',
                'key' => 'global_admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $roleId = $roleId;
        } else {
            $roleId = $role->id;
        }

        $admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'WebAdmin',
            'last_name' => 'User',
            'email' => $email,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        DB::table('admin_user_roles')->insert([
            'user_id' => $admin->id,
            'role_id' => $roleId,
        ]);

        return $admin;
    }

    public function test_admin_can_view_ad_bookings_web_index_page(): void
    {
        $admin = $this->createGlobalAdminUser('webadmin1@example.com');

        $booking = AdBooking::create([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'title' => 'Web Banner Test',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.ad-bookings.index'));

        $response->assertStatus(200);
        $response->assertSee('Ad Booking Requests');
        $response->assertSee('Web Banner Test');
    }

    public function test_admin_can_view_ad_booking_web_show_page(): void
    {
        $admin = $this->createGlobalAdminUser('webadmin2@example.com');

        $booking = AdBooking::create([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'title' => 'Web Banner Show Test',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.ad-bookings.show', $booking->id));

        $response->assertStatus(200);
        $response->assertSee('Web Banner Show Test');
        $response->assertSee('Pending Review');
    }

    public function test_admin_can_approve_ad_booking_via_web_route(): void
    {
        $admin = $this->createGlobalAdminUser('webadmin3@example.com');

        $booking = AdBooking::create([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'title' => 'Approve Web Request Test',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.ad-bookings.review', $booking->id), [
                'status' => 'approved',
                'admin_remarks' => 'Approved via web dashboard',
            ]);

        $response->assertRedirect(route('admin.ad-bookings.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ad_bookings', [
            'id' => $booking->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'admin_remarks' => 'Approved via web dashboard',
        ]);

        $this->assertDatabaseHas('ads', [
            'title' => 'Approve Web Request Test',
            'is_active' => true,
        ]);
    }
}
