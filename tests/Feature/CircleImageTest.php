<?php

namespace Tests\Feature;

use App\Http\Resources\CircleResource;
use App\Models\Circle;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CircleImageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInMemoryDatabase();
    }

    private function setUpInMemoryDatabase(): void
    {
        Schema::dropIfExists('circle_category_mappings');
        Schema::dropIfExists('circle_categories');
        Schema::dropIfExists('circle_members');
        Schema::dropIfExists('circles');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('users');

        Schema::create('circle_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('circle_category_mappings', function (Blueprint $table) {
            $table->id();
            $table->uuid('circle_id');
            $table->unsignedBigInteger('category_id');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email', 255)->unique();
            $table->string('phone', 50)->nullable();
            $table->string('status', 50)->default('active');
            $table->string('profile_photo_url')->nullable();
            $table->string('company_name')->nullable();
            $table->string('city')->nullable();
            $table->uuid('city_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('state')->nullable();
            $table->string('district')->nullable();
            $table->string('country')->default('India');
            $table->string('country_code', 10)->nullable();
            $table->timestamps();
        });

        Schema::create('circles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->text('purpose')->nullable();
            $table->text('announcement')->nullable();
            $table->uuid('city_id')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('India');
            $table->string('type')->default('public');
            $table->string('status')->default('active');
            $table->uuid('circle_founder_user_id')->nullable();
            $table->uuid('circle_director_user_id')->nullable();
            $table->uuid('industry_director_user_id')->nullable();
            $table->uuid('ded_user_id')->nullable();
            $table->uuid('eed_user_id')->nullable();
            $table->uuid('cover_file_id')->nullable();
            $table->uuid('circle_image_file_id')->nullable();
            $table->json('calendar')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circle_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('circle_id');
            $table->uuid('user_id');
            $table->string('role')->default('member');
            $table->string('status')->default('approved');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_create_circle_without_circle_image(): void
    {
        $coverFileId = (string) Str::uuid();

        $circle = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'Cover Only Circle',
            'slug' => 'cover-only-circle',
            'cover_file_id' => $coverFileId,
            'circle_image_file_id' => null,
            'status' => 'active',
        ]);

        $this->assertEquals($coverFileId, $circle->cover_file_id);
        $this->assertNull($circle->circle_image_file_id);
        $this->assertStringContainsString($coverFileId, $circle->cover_image_url);
        $this->assertNull($circle->circle_image_url);

        $resource = (new CircleResource($circle))->toArray(Request::create('/api/v1/circles/'.$circle->id, 'GET'));

        $this->assertNotNull($resource['cover_image']);
        $this->assertEquals($coverFileId, $resource['cover_image']['id']);
        $this->assertNull($resource['circle_image']);
    }

    public function test_create_circle_with_both_cover_and_circle_image(): void
    {
        $coverFileId = (string) Str::uuid();
        $circleImageFileId = (string) Str::uuid();

        $this->assertNotEquals($coverFileId, $circleImageFileId);

        $circle = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'Full Media Circle',
            'slug' => 'full-media-circle',
            'cover_file_id' => $coverFileId,
            'circle_image_file_id' => $circleImageFileId,
            'status' => 'active',
        ]);

        $this->assertEquals($coverFileId, $circle->cover_file_id);
        $this->assertEquals($circleImageFileId, $circle->circle_image_file_id);

        $resource = (new CircleResource($circle))->toArray(Request::create('/api/v1/circles/'.$circle->id, 'GET'));

        $this->assertEquals($coverFileId, $resource['cover_image']['id']);
        $this->assertEquals($circleImageFileId, $resource['circle_image']['id']);
        $this->assertStringContainsString($coverFileId, $resource['cover_image_url']);
        $this->assertStringContainsString($circleImageFileId, $resource['circle_image_url']);
    }

    public function test_edit_circle_image_keeps_cover_image_intact(): void
    {
        $coverFileId = (string) Str::uuid();
        $initialCircleImgId = (string) Str::uuid();
        $newCircleImgId = (string) Str::uuid();

        $circle = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'Editable Circle',
            'slug' => 'editable-circle',
            'cover_file_id' => $coverFileId,
            'circle_image_file_id' => $initialCircleImgId,
            'status' => 'active',
        ]);

        // Replace only circle_image_file_id
        $circle->update([
            'circle_image_file_id' => $newCircleImgId,
        ]);

        $circle->refresh();

        $this->assertEquals($coverFileId, $circle->cover_file_id);
        $this->assertEquals($newCircleImgId, $circle->circle_image_file_id);
    }

    public function test_existing_circle_without_circle_image_works_gracefully_in_api(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@test.com',
            'status' => 'active',
        ]);

        $circle = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'Legacy Circle',
            'slug' => 'legacy-circle',
            'circle_founder_user_id' => $user->id,
            'cover_file_id' => null,
            'circle_image_file_id' => null,
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/circles/'.$circle->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Legacy Circle')
            ->assertJsonPath('data.cover_image', null)
            ->assertJsonPath('data.circle_image', null)
            ->assertJsonPath('data.cover_image_url', null)
            ->assertJsonPath('data.circle_image_url', null);
    }
}
