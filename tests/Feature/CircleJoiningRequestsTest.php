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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CircleJoiningRequestsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();

        $roleKeys = ['global_admin', 'industry_director', 'ded', 'circle_leader', 'chair', 'vice_chair', 'secretary', 'member'];
        foreach ($roleKeys as $k) {
            $role = new Role();
            $role->id = (string) Str::uuid();
            $role->name = ucfirst(str_replace('_', ' ', $k));
            $role->key = $k;
            $role->save();
        }
    }

    public function test_can_view_circle_joining_requests_list_with_circle_details(): void
    {
        $this->withoutExceptionHandling();
        // 1. Create role and admin
        $admin = AdminUser::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
        ]);

        $role = Role::query()->where('key', 'global_admin')->firstOrFail();

        $admin->roles()->attach($role->id);

        // 2. Create Template
        $template = new CircleTemplate();
        $template->id = (string) Str::uuid();
        $template->name = 'Standard Template';
        $template->slug = 'standard-template-slug';
        $template->description = 'A standard template';
        $template->save();

        // 3. Create Circle with Template
        $circle = new Circle();
        $circle->id = (string) Str::uuid();
        $circle->name = 'Aequitas Ahmedabad Circle';
        $circle->slug = 'aequitas-ahmedabad-circle';
        $circle->status = 'active';
        $circle->template_id = $template->id;
        $circle->save();

        // 4. Create Category and link to Circle
        $category = CircleCategory::query()->create([
            'name' => 'Manufacturing Circles',
            'slug' => 'manufacturing-circles',
            'level' => 1,
            'is_active' => true,
        ]);

        DB::table('circle_category_mappings')->insert([
            'circle_id' => $circle->id,
            'category_id' => $category->id,
        ]);

        // 5. Create Peer user and Join Request
        $user = new User();
        $user->id = (string) Str::uuid();
        $user->first_name = 'Rahul';
        $user->last_name = 'Parmar';
        $user->email = 'rahul@example.com';
        $user->status = 'active';
        $user->password_hash = bcrypt('password');
        $user->save();

        $joinRequest = new CircleJoinRequest();
        $joinRequest->id = (string) Str::uuid();
        $joinRequest->user_id = $user->id;
        $joinRequest->circle_id = $circle->id;
        $joinRequest->level1_category_id = $category->id;
        $joinRequest->status = CircleJoinRequest::STATUS_PENDING_CD_APPROVAL;
        $joinRequest->requested_at = now();
        $joinRequest->save();

        // 6. Act as Admin and request index page
        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/pending-requests/circle-joining-requests');

        $response->assertOk();
        $response->assertSee($category->name);
        $response->assertSee('Category ID: ' . $category->id);

        // 7. Request show page
        $responseShow = $this->actingAs($admin, 'admin')
            ->get('/admin/pending-requests/circle-joining-requests/' . $joinRequest->id);

        $responseShow->assertOk();
        $responseShow->assertSee($circle->name);
        $responseShow->assertSee($circle->id);
        $responseShow->assertSee($template->name);
        $responseShow->assertSee($template->slug);
        $responseShow->assertSee($category->name);

        // 8. Act as User and request myRequests API
        Sanctum::actingAs($user);
        $apiResponseMy = $this->getJson('/api/v1/circle-join-requests/my');
        $apiResponseMy->assertOk();
        $apiResponseMy->assertJsonFragment([
            'circle_id' => $circle->id,
        ]);
        
        $myRequestsData = $apiResponseMy->json('data.items');
        $this->assertNotEmpty($myRequestsData);
        $this->assertEquals($circle->id, $myRequestsData[0]['circle']['id']);
        $this->assertEquals($category->name, $myRequestsData[0]['circle']['categories'][0]['name']);
        $this->assertEquals($category->name, $myRequestsData[0]['circle_categories'][0]['name']);
        $this->assertEquals($category->id, $myRequestsData[0]['circle_category_id']);
        $this->assertEquals($category->name, $myRequestsData[0]['circle_category_name']);
        $this->assertEquals($category->id, $myRequestsData[0]['category_id']);
        $this->assertEquals($category->name, $myRequestsData[0]['category_name']);

        // 9. Act as User and request show API
        Sanctum::actingAs($user);
        $apiResponseShow = $this->getJson('/api/v1/circle-join-requests/' . $joinRequest->id);
        $apiResponseShow->assertOk();
        $apiResponseShow->assertJsonFragment([
            'circle_id' => $circle->id,
        ]);
        
        $showRequestData = $apiResponseShow->json('data');
        $this->assertEquals($circle->id, $showRequestData['circle']['id']);
        $this->assertEquals($category->name, $showRequestData['circle']['categories'][0]['name']);
        $this->assertEquals($category->name, $showRequestData['circle_categories'][0]['name']);
        $this->assertEquals($category->id, $showRequestData['circle_category_id']);
        $this->assertEquals($category->name, $showRequestData['circle_category_name']);
        $this->assertEquals($category->id, $showRequestData['category_id']);
        $this->assertEquals($category->name, $showRequestData['category_name']);
    }

    public function test_api_submit_join_request_validation_and_backward_compatibility(): void
    {
        // 1. Create role and admin
        $admin = AdminUser::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
        ]);

        // 2. Create Template
        $template = new CircleTemplate();
        $template->id = (string) Str::uuid();
        $template->name = 'Standard Template';
        $template->slug = 'standard-template-slug';
        $template->description = 'A standard template';
        $template->save();

        // 3. Create Circle with Template
        $circle1 = new Circle();
        $circle1->id = (string) Str::uuid();
        $circle1->name = 'First active Circle';
        $circle1->slug = 'first-active-circle';
        $circle1->status = 'active';
        $circle1->template_id = $template->id;
        $circle1->save();

        $circle2 = new Circle();
        $circle2->id = (string) Str::uuid();
        $circle2->name = 'Second active Circle';
        $circle2->slug = 'second-active-circle';
        $circle2->status = 'active';
        $circle2->template_id = $template->id;
        $circle2->save();

        // 4. Create Category
        $category = CircleCategory::query()->create([
            'name' => 'Test Target Categories',
            'slug' => 'test-target-categories',
            'level' => 1,
            'is_active' => true,
        ]);

        // 5. Create Peer user
        $user = new User();
        $user->id = (string) Str::uuid();
        $user->first_name = 'Amit';
        $user->last_name = 'Shah';
        $user->email = 'amit@example.com';
        $user->status = 'active';
        $user->password_hash = bcrypt('password');
        $user->save();

        // Test POST with valid circle_category_id
        Sanctum::actingAs($user);
        $payload1 = [
            'circle_id' => $circle1->id,
            'reason_for_joining' => 'Interest in first active circle',
            'circle_category_id' => $category->id,
        ];
        $response1 = $this->postJson('/api/v1/circle-join-requests', $payload1);
        $response1->assertStatus(201);
        $response1->assertJsonFragment([
            'circle_id' => $circle1->id,
            'circle_category_id' => $category->id,
            'circle_category_name' => $category->name,
            'category_id' => $category->id,
            'category_name' => $category->name,
        ]);

        // Test POST with valid category_id
        Sanctum::actingAs($user);
        $payload2 = [
            'circle_id' => $circle2->id,
            'reason_for_joining' => 'Interest in second active circle',
            'category_id' => $category->id,
        ];
        $response2 = $this->postJson('/api/v1/circle-join-requests', $payload2);
        $response2->assertStatus(201);
        $response2->assertJsonFragment([
            'circle_id' => $circle2->id,
            'circle_category_id' => $category->id,
            'circle_category_name' => $category->name,
            'category_id' => $category->id,
            'category_name' => $category->name,
        ]);

        // Test POST with invalid category id returns 422
        $circle3 = new Circle();
        $circle3->id = (string) Str::uuid();
        $circle3->name = 'Third active Circle';
        $circle3->slug = 'third-active-circle';
        $circle3->status = 'active';
        $circle3->template_id = $template->id;
        $circle3->save();

        Sanctum::actingAs($user);
        $payload3 = [
            'circle_id' => $circle3->id,
            'reason_for_joining' => 'Interest in third active circle',
            'circle_category_id' => 999999, // invalid ID
        ];
        $response3 = $this->postJson('/api/v1/circle-join-requests', $payload3);
        $response3->assertStatus(422);
        $response3->assertJsonValidationErrors(['circle_category_id']);
    }

    public function test_dynamic_category_tracing_fallback(): void
    {
        // 1. Create Category
        $category = CircleCategory::query()->create([
            'name' => 'Manufacturing & Engineering Circles',
            'slug' => 'manufacturing-engineering',
            'level' => 1,
            'is_active' => true,
        ]);

        // 2. Create Level 4 Category pointing to Category
        DB::table('circle_category_level4')->insert([
            'id' => 1,
            'circle_category_id' => $category->id,
            'name' => 'Specialized Manufacturing Subcategory',
        ]);

        // 3. Create Circle
        $circle = new Circle();
        $circle->id = (string) Str::uuid();
        $circle->name = 'Test Circle';
        $circle->slug = 'test-circle';
        $circle->status = 'active';
        $circle->save();

        // 4. Create User
        $user = new User();
        $user->id = (string) Str::uuid();
        $user->first_name = 'Amit';
        $user->last_name = 'Shah';
        $user->email = 'amit@example.com';
        $user->status = 'active';
        $user->password_hash = bcrypt('password');
        $user->save();

        // 5. Create Join Request with only level4_category_id = 1
        $joinRequest = new CircleJoinRequest();
        $joinRequest->id = (string) Str::uuid();
        $joinRequest->user_id = $user->id;
        $joinRequest->circle_id = $circle->id;
        $joinRequest->level4_category_id = 1;
        $joinRequest->status = CircleJoinRequest::STATUS_PENDING_CD_APPROVAL;
        $joinRequest->requested_at = now();
        $joinRequest->save();

        // Verify that the relationship resolves successfully to the level 1 Category
        $resolvedCategory = $joinRequest->circleCategory;
        $this->assertNotNull($resolvedCategory);
        $this->assertEquals($category->id, $resolvedCategory->id);
        $this->assertEquals($category->name, $resolvedCategory->name);
    }

    private function createSchema(): void
    {
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

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->uuid('city_id')->nullable();
            $table->string('city')->nullable();
            $table->uuid('circle_founder_user_id')->nullable();
            $table->uuid('circle_director_user_id')->nullable();
            $table->uuid('industry_director_user_id')->nullable();
            $table->uuid('template_id')->nullable();
            $table->string('status')->default('active');
            $table->string('circle_stage')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circle_categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->integer('level')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('circle_category_level2', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('circle_category_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('circle_category_level3', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('circle_category_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('circle_category_level4', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('circle_category_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('circle_category_mappings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('circle_id');
            $table->unsignedBigInteger('category_id');
            $table->timestamps();
        });

        Schema::create('circle_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->jsonb('config')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password_hash');
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('city')->nullable();
            $table->string('membership_status')->default('visitor');
            $table->string('status')->default('inactive');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circle_join_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('circle_id');
            $table->text('reason_for_joining')->nullable();
            $table->unsignedBigInteger('level1_category_id')->nullable();
            $table->unsignedBigInteger('level2_category_id')->nullable();
            $table->unsignedBigInteger('level3_category_id')->nullable();
            $table->unsignedBigInteger('level4_category_id')->nullable();
            $table->string('status')->nullable();
            $table->string('ded_approval_status')->nullable();
            $table->timestamp('ded_approved_at')->nullable();
            $table->timestamp('fee_paid_at')->nullable();
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
    }
}
