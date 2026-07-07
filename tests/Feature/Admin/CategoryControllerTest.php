<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\CircleCategory;
use App\Models\CircleCategoryLevel2;
use App\Models\CircleCategoryLevel3;
use App\Models\CircleCategoryLevel4;
use App\Models\Role;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;

    private CircleCategory $category1;

    private CircleCategoryLevel2 $level2;

    private CircleCategoryLevel3 $level3;

    private CircleCategoryLevel4 $level4;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('circle_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('circle_key')->nullable();
            $table->integer('level')->default(1);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('circle_category_level2', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('circle_category_id');
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('circle_category_level3', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('circle_category_id');
            $table->foreignId('level2_id');
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('circle_category_level4', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('circle_category_id');
            $table->foreignId('level2_id');
            $table->foreignId('level3_id');
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        $globalAdminRole = Role::where('key', 'global_admin')->firstOrFail();

        $this->admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Global Admin',
            'email' => 'admin.test@example.com',
        ]);
        $this->admin->roles()->attach($globalAdminRole->id);

        $this->category1 = CircleCategory::create([
            'name' => 'Level 1 Category',
            'slug' => 'level-1-category',
            'level' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->level2 = CircleCategoryLevel2::create([
            'circle_category_id' => $this->category1->id,
            'name' => 'Level 2 Category',
            'slug' => 'level-2-category',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->level3 = CircleCategoryLevel3::create([
            'circle_category_id' => $this->category1->id,
            'level2_id' => $this->level2->id,
            'name' => 'Level 3 Category',
            'slug' => 'level-3-category',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->level4 = CircleCategoryLevel4::create([
            'circle_category_id' => $this->category1->id,
            'level2_id' => $this->level2->id,
            'level3_id' => $this->level3->id,
            'name' => 'Level 4 Category',
            'slug' => 'level-4-category',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_can_view_category(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.categories.view', $this->category1));

        $response->assertStatus(200);
        $response->assertSee('Level 2 Category');
        $response->assertSee('Level 3 Category');
        $response->assertSee('Level 4 Category');
    }


    public function test_can_delete_level4_category(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->from(route('admin.categories.view', $this->category1))
            ->delete(route('admin.categories.level4.destroy', $this->level4));

        $response->assertRedirect(route('admin.categories.view', $this->category1));
        $response->assertSessionHas('success');

        $this->assertFalse($this->level4->refresh()->is_active);
        $this->assertTrue($this->level3->refresh()->is_active);
        $this->assertTrue($this->level2->refresh()->is_active);
    }

    public function test_can_delete_level3_category_and_its_children(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->from(route('admin.categories.view', $this->category1))
            ->delete(route('admin.categories.level3.destroy', $this->level3));

        $response->assertRedirect(route('admin.categories.view', $this->category1));
        $response->assertSessionHas('success');

        $this->assertFalse($this->level3->refresh()->is_active);
        $this->assertFalse($this->level4->refresh()->is_active);
        $this->assertTrue($this->level2->refresh()->is_active);
    }

    public function test_can_delete_level2_category_and_its_children(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->from(route('admin.categories.view', $this->category1))
            ->delete(route('admin.categories.level2.destroy', $this->level2));

        $response->assertRedirect(route('admin.categories.view', $this->category1));
        $response->assertSessionHas('success');

        $this->assertFalse($this->level2->refresh()->is_active);
        $this->assertFalse($this->level3->refresh()->is_active);
        $this->assertFalse($this->level4->refresh()->is_active);
    }
}
