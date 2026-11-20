<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\MembershipPlan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MembershipPlanMonthlyPriceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('admin_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('membership_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('plan_type')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('gst_percent', 5, 2)->default(18);
            $table->integer('duration_days')->default(30);
            $table->integer('duration_months')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_free')->default(false);
            $table->integer('sort_order')->default(0);
            $table->integer('coins')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('membership_plan_id')->nullable();
            $table->timestamps();
        });

        Schema::create('user_memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('membership_plan_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_admin_can_manually_add_and_update_monthly_price_in_database_and_flutter_api_returns_it(): void
    {
        $admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'email' => 'admin@peersglobal.com',
        ]);

        Sanctum::actingAs($admin);

        // 1. Create a 6-month plan with Base Price = 15000 and Manual Monthly Price = 2500
        // (Notice monthly_price column is automatically ensured on membership_plans table)
        $createRes = $this->postJson('/api/v1/admin/plans/membership', [
            'name' => '6-Month Subscription – Unity Peer Only',
            'price' => 15000,
            'monthly_price' => 2500,
            'gst_percent' => 18,
            'duration_days' => 180,
            'duration_months' => 6,
            'coins' => 0,
            'is_free' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $createRes->assertCreated()
            ->assertJsonPath('data.price', '15000.00')
            ->assertJsonPath('data.monthly_price', 2500)
            ->assertJsonPath('data.display_monthly_price', 2500)
            ->assertJsonPath('data.gst_amount', 2700)
            ->assertJsonPath('data.total_amount', 17700);

        $planId = (string) $createRes->json('data.id');
        $this->assertTrue(Schema::hasColumn('membership_plans', 'monthly_price'));

        // Verify stored in membership_plans database table
        $planInDb = MembershipPlan::findOrFail($planId);
        $this->assertSame('2500.00', (string) $planInDb->monthly_price);

        // 2. Manually update monthly_price to a custom value (e.g. 2199/month while keeping base price 15000)
        $updateRes = $this->putJson("/api/v1/admin/plans/membership/{$planId}", [
            'name' => '6-Month Subscription – Unity Peer Only',
            'price' => 15000,
            'monthly_price' => 2199,
            'gst_percent' => 18,
            'duration_days' => 180,
            'duration_months' => 6,
            'coins' => 0,
            'is_free' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $updateRes->assertOk()
            ->assertJsonPath('data.monthly_price', 2199)
            ->assertJsonPath('data.display_monthly_price', 2199)
            ->assertJsonPath('data.total_amount', 17700);

        $this->assertSame('2199.00', (string) $planInDb->fresh()->monthly_price);

        // 3. Verify Flutter API (GET /api/v1/membership-plans) returns the exact manual monthly_price from DB
        $flutterRes = $this->getJson('/api/v1/membership-plans');
        $flutterRes->assertOk()
            ->assertJsonPath('data.0.id', $planId)
            ->assertJsonPath('data.0.price', 15000)
            ->assertJsonPath('data.0.monthly_price', 2199)
            ->assertJsonPath('data.0.display_monthly_price', 2199)
            ->assertJsonPath('data.0.gst_amount', 2700)
            ->assertJsonPath('data.0.total_amount', 17700);
    }
}
