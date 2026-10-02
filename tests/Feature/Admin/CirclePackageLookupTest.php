<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Models\City;
use App\Models\MembershipPlan;
use App\Models\Role;
use App\Models\User;
use App\Services\Circles\CirclePriceResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CirclePackageLookupTest extends TestCase
{
    use DatabaseTransactions;

    private AdminUser $admin;

    private City $city;

    private User $founder;

    private Circle $circle;

    private MembershipPlan $planWithSlug;

    private MembershipPlan $planRegular;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchemas();

        $role = Role::query()->where('key', 'global_admin')->first();
        if (! $role) {
            $role = Role::query()->create([
                'id' => (string) Str::uuid(),
                'name' => 'Global Admin',
                'key' => 'global_admin',
            ]);
        }

        $this->admin = AdminUser::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Package Test Admin',
            'email' => 'package.admin.'.Str::random(8).'@example.com',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        DB::table('admin_user_roles')->insert([
            'user_id' => $this->admin->id,
            'role_id' => $role->id,
        ]);

        $cityId = (string) Str::uuid();
        DB::table('cities')->insert([
            'id' => $cityId,
            'name' => 'Ahmedabad',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->city = City::query()->findOrFail($cityId);

        $this->founder = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Founder',
            'last_name' => 'User',
            'email' => 'founder.'.Str::random(8).'@example.com',
            'status' => 'active',
        ]);

        $this->circle = Circle::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Package Test Circle '.Str::random(6),
            'slug' => 'pkg-circle-'.Str::lower(Str::random(8)),
            'type' => 'public',
            'status' => 'active',
            'city_id' => $this->city->id,
            'country' => 'India',
            'circle_founder_user_id' => $this->founder->id,
        ]);

        $this->planWithSlug = MembershipPlan::query()->where('slug', 'circle_pakej_1')->first()
            ?? MembershipPlan::query()->create([
                'id' => (string) Str::uuid(),
                'name' => 'Circle Pakej 1',
                'slug' => 'circle_pakej_1',
                'price' => 15000.00,
                'gst_percent' => 18.00,
                'duration_months' => 12,
                'duration_days' => 365,
                'is_active' => true,
                'is_free' => false,
                'sort_order' => 1,
            ]);

        $this->planRegular = MembershipPlan::query()->where('slug', 'circle_standard_plan')->first()
            ?? MembershipPlan::query()->create([
                'id' => (string) Str::uuid(),
                'name' => 'Circle Standard Plan',
                'slug' => 'circle_standard_plan',
                'price' => 25000.00,
                'gst_percent' => 18.00,
                'duration_months' => 12,
                'duration_days' => 365,
                'is_active' => true,
                'is_free' => false,
                'sort_order' => 2,
            ]);
    }

    private function createTestSchemas(): void
    {
        if (! Schema::hasTable('cities')) {
            Schema::create('cities', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('country')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('key')->nullable();
                $table->string('slug')->nullable();
                $table->boolean('is_system')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_user_roles')) {
            Schema::create('admin_user_roles', function (Blueprint $table): void {
                $table->uuid('user_id');
                $table->uuid('role_id');
                $table->primary(['user_id', 'role_id']);
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('display_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('membership_plans')) {
            Schema::create('membership_plans', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->unique();
                $table->decimal('price', 10, 2)->default(0);
                $table->decimal('gst_percent', 5, 2)->default(18.00);
                $table->integer('duration_days')->default(365);
                $table->integer('duration_months')->default(12);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_free')->default(false);
                $table->integer('sort_order')->default(0);
                $table->integer('coins')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('circles')) {
            Schema::create('circles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('type')->default('regular');
                $table->string('status')->default('active');
                $table->string('circle_stage')->nullable();
                $table->uuid('city_id')->nullable();
                $table->string('country')->nullable();
                $table->text('description')->nullable();
                $table->text('purpose')->nullable();
                $table->text('announcement')->nullable();
                $table->uuid('circle_founder_user_id')->nullable();
                $table->uuid('circle_director_user_id')->nullable();
                $table->uuid('industry_director_user_id')->nullable();
                $table->uuid('ded_user_id')->nullable();
                $table->uuid('eed_user_id')->nullable();
                $table->uuid('cover_file_id')->nullable();
                $table->uuid('circle_image_file_id')->nullable();
                $table->decimal('circle_price_amount', 10, 2)->nullable();
                $table->string('circle_price_currency', 10)->default('INR');
                $table->decimal('circle_gst_percent', 5, 2)->default(18.00);
                $table->integer('circle_duration_months')->default(12);
                $table->boolean('is_package_active')->default(true);
                $table->string('zoho_addon_code')->nullable();
                $table->string('zoho_addon_id')->nullable();
                $table->string('zoho_addon_name')->nullable();
                $table->json('calendar')->nullable();
                $table->json('industry_tags')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('circles', function (Blueprint $table): void {
                if (! Schema::hasColumn('circles', 'type')) {
                    $table->string('type')->default('regular')->nullable();
                }
                if (! Schema::hasColumn('circles', 'country')) {
                    $table->string('country')->nullable();
                }
                if (! Schema::hasColumn('circles', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('circles', 'purpose')) {
                    $table->text('purpose')->nullable();
                }
                if (! Schema::hasColumn('circles', 'announcement')) {
                    $table->text('announcement')->nullable();
                }
                if (! Schema::hasColumn('circles', 'ded_user_id')) {
                    $table->uuid('ded_user_id')->nullable();
                }
                if (! Schema::hasColumn('circles', 'eed_user_id')) {
                    $table->uuid('eed_user_id')->nullable();
                }
                if (! Schema::hasColumn('circles', 'cover_file_id')) {
                    $table->uuid('cover_file_id')->nullable();
                }
                if (! Schema::hasColumn('circles', 'circle_image_file_id')) {
                    $table->uuid('circle_image_file_id')->nullable();
                }
                if (! Schema::hasColumn('circles', 'circle_price_amount')) {
                    $table->decimal('circle_price_amount', 10, 2)->nullable();
                }
                if (! Schema::hasColumn('circles', 'circle_price_currency')) {
                    $table->string('circle_price_currency', 10)->default('INR')->nullable();
                }
                if (! Schema::hasColumn('circles', 'circle_gst_percent')) {
                    $table->decimal('circle_gst_percent', 5, 2)->default(18.00)->nullable();
                }
                if (! Schema::hasColumn('circles', 'circle_duration_months')) {
                    $table->integer('circle_duration_months')->default(12)->nullable();
                }
                if (! Schema::hasColumn('circles', 'is_package_active')) {
                    $table->boolean('is_package_active')->default(true)->nullable();
                }
                if (! Schema::hasColumn('circles', 'zoho_addon_code')) {
                    $table->string('zoho_addon_code')->nullable();
                }
                if (! Schema::hasColumn('circles', 'zoho_addon_id')) {
                    $table->string('zoho_addon_id')->nullable();
                }
                if (! Schema::hasColumn('circles', 'zoho_addon_name')) {
                    $table->string('zoho_addon_name')->nullable();
                }
                if (! Schema::hasColumn('circles', 'calendar')) {
                    $table->json('calendar')->nullable();
                }
                if (! Schema::hasColumn('circles', 'industry_tags')) {
                    $table->json('industry_tags')->nullable();
                }
            });
        }

        if (! Schema::hasTable('circle_members')) {
            Schema::create('circle_members', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('circle_id');
                $table->uuid('user_id');
                $table->string('role')->default('member');
                $table->string('status')->default('approved');
                $table->timestamp('joined_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('circle_join_requests')) {
            Schema::create('circle_join_requests', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('circle_id')->nullable();
                $table->uuid('user_id')->nullable();
                $table->string('status')->default('pending_cd_approval');
                $table->json('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    /**
     * 1. Circle Package value = valid UUID -> must find membership plan by id.
     */
    public function test_circle_package_lookup_by_valid_uuid_resolves_plan(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->put(route('admin.circles.update', $this->circle), [
                'name' => $this->circle->name,
                'type' => 'public',
                'status' => 'active',
                'city_id' => $this->city->id,
                'country' => 'India',
                'circle_founder_user_id' => $this->founder->id,
                'circle_package' => $this->planWithSlug->id,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.circles.show', $this->circle));

        $this->assertDatabaseHas('circles', [
            'id' => $this->circle->id,
            'zoho_addon_code' => 'circle_pakej_1',
            'zoho_addon_id' => $this->planWithSlug->id,
            'zoho_addon_name' => 'Circle Pakej 1',
            'circle_price_amount' => 15000.00,
        ]);
    }

    /**
     * 2. Circle Package value = "circle_pakej_1" (slug) -> must find membership plan by slug without PostgreSQL UUID cast error.
     */
    public function test_circle_package_lookup_by_slug_resolves_plan_without_uuid_cast_error(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->put(route('admin.circles.update', $this->circle), [
                'name' => $this->circle->name,
                'type' => 'public',
                'status' => 'active',
                'city_id' => $this->city->id,
                'country' => 'India',
                'circle_founder_user_id' => $this->founder->id,
                'circle_package' => 'circle_pakej_1',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.circles.show', $this->circle));

        $this->assertDatabaseHas('circles', [
            'id' => $this->circle->id,
            'zoho_addon_code' => 'circle_pakej_1',
            'zoho_addon_id' => $this->planWithSlug->id,
            'zoho_addon_name' => 'Circle Pakej 1',
            'circle_price_amount' => 15000.00,
        ]);
    }

    /**
     * 3. Invalid/non-existing slug -> must return existing graceful fallback behavior, not HTTP 500.
     */
    public function test_circle_package_with_invalid_slug_does_not_throw_500(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->put(route('admin.circles.update', $this->circle), [
                'name' => $this->circle->name,
                'type' => 'public',
                'status' => 'active',
                'city_id' => $this->city->id,
                'country' => 'India',
                'circle_founder_user_id' => $this->founder->id,
                'circle_package' => 'non_existent_custom_slug',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.circles.show', $this->circle));

        $this->assertDatabaseHas('circles', [
            'id' => $this->circle->id,
            'zoho_addon_code' => 'non_existent_custom_slug',
            'zoho_addon_id' => null,
        ]);
    }

    /**
     * 4. Admin: Edit Circle -> Circle Package = circle_pakej_1 -> Save -> must save successfully.
     */
    public function test_admin_edit_circle_package_save_success(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->put(route('admin.circles.update', $this->circle), [
                'name' => 'Updated Circle Name '.Str::random(5),
                'type' => 'public',
                'status' => 'active',
                'city_id' => $this->city->id,
                'country' => 'India',
                'circle_founder_user_id' => $this->founder->id,
                'circle_package' => 'circle_pakej_1',
                'circle_price_amount' => 15000.00,
                'circle_gst_percent' => 18.00,
                'is_package_active' => true,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'Circle updated successfully.');
        $response->assertRedirect(route('admin.circles.show', $this->circle));

        $this->circle->refresh();
        $this->assertSame('circle_pakej_1', $this->circle->zoho_addon_code);
        $this->assertSame($this->planWithSlug->id, $this->circle->zoho_addon_id);
        $this->assertEquals(15000.00, (float) $this->circle->circle_price_amount);
    }

    /**
     * 5. Existing Membership Plan selection must continue working.
     */
    public function test_existing_membership_plan_selection_continues_working(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->put(route('admin.circles.update', $this->circle), [
                'name' => $this->circle->name,
                'type' => 'public',
                'status' => 'active',
                'city_id' => $this->city->id,
                'country' => 'India',
                'circle_founder_user_id' => $this->founder->id,
                'circle_package' => $this->planRegular->id,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.circles.show', $this->circle));

        $this->circle->refresh();
        $this->assertSame('circle_standard_plan', $this->circle->zoho_addon_code);
        $this->assertSame($this->planRegular->id, $this->circle->zoho_addon_id);
        $this->assertEquals(25000.00, (float) $this->circle->circle_price_amount);
    }

    /**
     * 6. CirclePriceResolver resolves safely when notes contain either UUID or slug.
     */
    public function test_circle_price_resolver_handles_both_uuid_and_slug_in_notes(): void
    {
        $resolver = app(CirclePriceResolver::class);

        // Test with UUID
        $requestWithUuid = new CircleJoinRequest;
        $requestWithUuid->notes = ['membership_plan_id' => $this->planWithSlug->id];
        $resolvedWithUuid = $resolver->resolve($this->circle, $requestWithUuid);
        $this->assertEquals(15000.00, $resolvedWithUuid['amount']);
        $this->assertSame($this->planWithSlug->id, $resolvedWithUuid['plan']?->id);

        // Test with slug (e.g. "circle_pakej_1")
        $requestWithSlug = new CircleJoinRequest;
        $requestWithSlug->notes = ['membership_plan_id' => 'circle_pakej_1'];
        $resolvedWithSlug = $resolver->resolve($this->circle, $requestWithSlug);
        $this->assertEquals(15000.00, $resolvedWithSlug['amount']);
        $this->assertSame($this->planWithSlug->id, $resolvedWithSlug['plan']?->id);

        // Test with invalid slug
        $requestWithInvalid = new CircleJoinRequest;
        $requestWithInvalid->notes = ['membership_plan_id' => 'non_existent_slug_123'];
        // Should safely fall through to circle override or fallback plan without throwing PostgreSQL 22P02
        $this->circle->circle_price_amount = 12000.00;
        $this->circle->circle_price_currency = 'INR';
        $resolvedFallback = $resolver->resolve($this->circle, $requestWithInvalid);
        $this->assertEquals(12000.00, $resolvedFallback['amount']);
    }
}
