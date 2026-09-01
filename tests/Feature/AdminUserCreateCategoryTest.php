<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\CircleCategory;
use App\Models\CircleCategoryLevel2;
use App\Models\CircleCategoryLevel3;
use App\Models\CircleCategoryLevel4;
use App\Models\City;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminUserCreateCategoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    private function createSchema(): void
    {
        Schema::dropIfExists('circle_category_level4');
        Schema::dropIfExists('circle_category_level3');
        Schema::dropIfExists('circle_category_level2');
        Schema::dropIfExists('circle_categories');
        Schema::dropIfExists('circle_members');
        Schema::dropIfExists('circles');
        Schema::dropIfExists('role_module_access');
        Schema::dropIfExists('admin_modules');
        Schema::dropIfExists('admin_user_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('admin_users');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('files');
        Schema::dropIfExists('users');
        Schema::dropIfExists('tbl_permission_cache');

        Schema::create('files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uploader_user_id')->nullable();
            $table->string('s3_key');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->boolean('is_orphaned')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('designation')->nullable();
            $table->string('company_name')->nullable();
            $table->string('business_type')->nullable();
            $table->string('turnover_range')->nullable();
            $table->string('gender')->nullable();
            $table->date('dob')->nullable();
            $table->integer('experience_years')->nullable();
            $table->text('experience_summary')->nullable();
            $table->text('short_bio')->nullable();
            $table->text('long_bio_html')->nullable();
            $table->string('public_profile_slug')->nullable();
            $table->string('membership_status')->nullable();
            $table->date('membership_expiry')->nullable();
            $table->date('membership_starts_at')->nullable();
            $table->date('membership_ends_at')->nullable();
            $table->string('zoho_plan_code')->nullable();
            $table->uuid('active_circle_id')->nullable();
            $table->string('active_circle_addon_code')->nullable();
            $table->string('active_circle_addon_name')->nullable();
            $table->date('circle_joined_at')->nullable();
            $table->date('circle_expires_at')->nullable();
            $table->integer('coins_balance')->default(0);
            $table->boolean('is_sponsored_member')->default(false);
            $table->uuid('introduced_by')->nullable();
            $table->integer('members_introduced_count')->default(0);
            $table->uuid('city_id')->nullable();
            $table->string('city')->nullable();
            $table->uuid('profile_photo_file_id')->nullable();
            $table->uuid('cover_photo_file_id')->nullable();
            $table->text('industry_tags')->nullable();
            $table->text('target_regions')->nullable();
            $table->text('target_business_categories')->nullable();
            $table->text('hobbies_interests')->nullable();
            $table->text('leadership_roles')->nullable();
            $table->text('special_recognitions')->nullable();
            $table->text('skills')->nullable();
            $table->text('interests')->nullable();
            $table->text('social_links')->nullable();
            $table->string('website')->nullable();
            $table->text('sustainability_contribution')->nullable();
            $table->text('sustainability_areas')->nullable();
            $table->text('greenpreneur_goals')->nullable();
            $table->string('community_directory_listing')->default('No');
            $table->string('password_hash')->nullable();
            $table->string('registration_source')->nullable();
            $table->unsignedBigInteger('main_business_category_id')->nullable();
            $table->unsignedBigInteger('business_category_id')->nullable();
            $table->string('status')->default('active');
            $table->integer('life_impacted_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
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
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->primary(['user_id', 'role_id']);
        });

        Schema::create('role_module_access', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('role_id');
            $table->string('module_key', 100);
            $table->timestamps();
        });

        Schema::create('admin_modules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('module_key', 100);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('zoho_addon_code')->nullable();
            $table->string('zoho_addon_name')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('circle_id');
            $table->string('status')->nullable();
            $table->string('role')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('level_1_category_id')->nullable();
            $table->unsignedBigInteger('level_2_category_id')->nullable();
            $table->unsignedBigInteger('level_3_category_id')->nullable();
            $table->unsignedBigInteger('level_4_category_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circle_categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->integer('level')->default(1);
            $table->string('circle_key')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('circle_category_level2', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('circle_category_id')->nullable();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('circle_category_level3', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('circle_category_id')->nullable();
            $table->unsignedBigInteger('level2_id')->nullable();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('circle_category_level4', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('circle_category_id')->nullable();
            $table->unsignedBigInteger('level2_id')->nullable();
            $table->unsignedBigInteger('level3_id')->nullable();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tbl_permission_cache', function (Blueprint $table): void {
            $table->uuid('user_id')->primary();
            $table->text('circle_ids')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->string('version')->nullable();
            $table->timestamps();
        });
    }

    private function createAdminUser(): AdminUser
    {
        $role = Role::create([
            'id' => (string) Str::uuid(),
            'name' => 'Global Admin',
            'key' => 'global_admin',
        ]);

        $admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Administrator',
            'email' => 'admin@example.com',
        ]);

        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_admin_can_view_create_page_with_categories(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin, 'admin');

        $mainCategory = CircleCategory::create([
            'name' => 'Technology & IT',
            'slug' => 'technology-it',
            'level' => 1,
            'is_active' => true,
        ]);

        $level2 = CircleCategoryLevel2::create([
            'circle_category_id' => $mainCategory->id,
            'name' => 'Software Development',
            'is_active' => true,
        ]);

        $level3 = CircleCategoryLevel3::create([
            'circle_category_id' => $mainCategory->id,
            'level2_id' => $level2->id,
            'name' => 'Web App Development',
            'is_active' => true,
        ]);

        CircleCategoryLevel4::create([
            'circle_category_id' => $mainCategory->id,
            'level2_id' => $level2->id,
            'level3_id' => $level3->id,
            'name' => 'Laravel Development',
            'is_active' => true,
        ]);

        $response = $this->get(route('admin.users.create'));

        $response->assertOk();
        $response->assertSee('Main Category');
        $response->assertSee('Sub Category');
        $response->assertSee('Technology &amp; IT', false);
        $response->assertSee('business_main_category_id');
        $response->assertSee('business_sub_category_id');
    }

    public function test_admin_can_create_user_with_main_and_sub_category(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $this->actingAs($admin, 'admin');

        $mainCategory = CircleCategory::create([
            'name' => 'Manufacturing',
            'slug' => 'manufacturing',
            'level' => 1,
            'is_active' => true,
        ]);

        $level4 = CircleCategoryLevel4::create([
            'circle_category_id' => $mainCategory->id,
            'name' => 'Industrial Machinery',
            'is_active' => true,
        ]);

        $city = new City;
        $city->id = (string) Str::uuid();
        $city->name = 'Mumbai';
        $city->state = 'Maharashtra';
        $city->country = 'India';
        $city->save();

        $payload = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'designation' => 'Managing Director',
            'company_name' => 'Acme Manufacturing Ltd',
            'business_type' => 'Manufacturer',
            'turnover_range' => '10-25 Cr',
            'main_business_category_id' => $mainCategory->id,
            'business_category_id' => $level4->id,
            'membership_status' => 'free_peer',
            'city' => 'Mumbai',
            'city_id' => $city->id,
            'community_directory_listing' => 'Yes',
        ];

        $response = $this->post(route('admin.users.store'), $payload);

        $response->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'john.doe@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals((string) $mainCategory->id, (string) $user->main_business_category_id);
        $this->assertEquals((string) $level4->id, (string) $user->business_category_id);
    }

    public function test_admin_can_create_sponsored_user_with_sponsor_member(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $this->actingAs($admin, 'admin');

        $sponsor = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Sponsor',
            'last_name' => 'Leader',
            'email' => 'sponsor.leader@example.com',
            'company_name' => 'Sponsor Corp',
            'membership_status' => 'Circle Peer',
            'city' => 'Mumbai',
        ]);

        $city = new City;
        $city->id = (string) Str::uuid();
        $city->name = 'Mumbai';
        $city->state = 'Maharashtra';
        $city->country = 'India';
        $city->save();

        $payload = [
            'first_name' => 'Sponsored',
            'last_name' => 'Member',
            'email' => 'sponsored.member@example.com',
            'designation' => 'Founder',
            'company_name' => 'New Venture Ltd',
            'membership_status' => 'Circle Peer',
            'is_sponsored_member' => '1',
            'introduced_by' => $sponsor->id,
            'city' => 'Mumbai',
            'city_id' => $city->id,
            'community_directory_listing' => 'Yes',
        ];

        $response = $this->post(route('admin.users.store'), $payload);

        $response->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'sponsored.member@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue((bool) $user->is_sponsored_member);
        $this->assertEquals((string) $sponsor->id, (string) $user->introduced_by);
    }

    public function test_non_sponsored_user_clears_introduced_by(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $this->actingAs($admin, 'admin');

        $sponsor = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Sponsor',
            'last_name' => 'Leader',
            'email' => 'sponsor2@example.com',
            'membership_status' => 'Circle Peer',
            'city' => 'Mumbai',
        ]);

        $city = new City;
        $city->id = (string) Str::uuid();
        $city->name = 'Mumbai';
        $city->state = 'Maharashtra';
        $city->country = 'India';
        $city->save();

        $payload = [
            'first_name' => 'Regular',
            'last_name' => 'Member',
            'email' => 'regular.member@example.com',
            'designation' => 'Founder',
            'company_name' => 'Regular Venture Ltd',
            'membership_status' => 'free_peer',
            'is_sponsored_member' => '0',
            'introduced_by' => $sponsor->id, // Should be cleared since is_sponsored_member is 0
            'city' => 'Mumbai',
            'city_id' => $city->id,
            'community_directory_listing' => 'Yes',
        ];

        $response = $this->post(route('admin.users.store'), $payload);

        $response->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'regular.member@example.com')->first();
        $this->assertNotNull($user);
        $this->assertFalse((bool) $user->is_sponsored_member);
        $this->assertNull($user->introduced_by);
    }

    public function test_admin_can_upload_photo_without_gd_extension(): void
    {
        Storage::fake('public');

        $admin = $this->createAdminUser();
        $this->actingAs($admin, 'admin');

        $file = UploadedFile::fake()->create('profile.png', 100, 'image/png');

        $response = $this->postJson(route('admin.files.upload'), [
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
            ],
        ]);
        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('data.id'));
    }
}
