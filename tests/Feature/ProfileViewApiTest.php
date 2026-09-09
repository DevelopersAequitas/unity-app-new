<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileViewApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInMemoryDatabase();
    }

    protected function setUpInMemoryDatabase(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('profile_views');
        Schema::dropIfExists('circle_category_level4');
        Schema::dropIfExists('users');
        Schema::dropIfExists('personal_access_tokens');

        Schema::create('circle_category_level4', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email', 255)->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('password_hash')->nullable();
            $table->string('company_name')->nullable();
            $table->string('city')->nullable();
            $table->string('designation')->nullable();
            $table->string('business_sub_category')->nullable();
            $table->unsignedBigInteger('business_category_id')->nullable();
            $table->string('timezone')->nullable();
            $table->string('industry')->nullable();
            $table->integer('life_impacted_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('profile_views', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('viewed_id');
            $table->uuid('viewer_id');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type');
            $table->json('payload')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('read_at')->nullable();
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type');
            $table->string('category')->nullable();
            $table->string('title');
            $table->text('body');
            $table->string('channel')->nullable();
            $table->string('priority')->nullable();
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('screen')->nullable();
            $table->json('data')->nullable();
            $table->string('status');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->uuid('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    private function createUser(string $firstName, string $lastName, array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $firstName.' '.$lastName,
            'email' => strtolower($firstName.'.'.$lastName.'-'.Str::random(4).'@example.com'),
            'phone' => (string) random_int(1000000000, 9999999999),
        ], $attributes));
    }

    public function test_record_profile_view_successfully(): void
    {
        $viewer = $this->createUser('John', 'Doe');
        $viewed = $this->createUser('Jane', 'Smith');

        Sanctum::actingAs($viewer);

        $response = $this->postJson('/api/v1/profile/view', [
            'viewed_id' => $viewed->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Profile view recorded and notification sent successfully.')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'viewed_id',
                    'viewer_id',
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('profile_views', [
            'viewed_id' => $viewed->id,
            'viewer_id' => $viewer->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $viewed->id,
            'type' => 'activity_update',
        ]);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $viewed->id,
            'type' => 'activity_update',
            'category' => 'profile_viewed',
        ]);
    }

    public function test_cannot_record_own_profile_view(): void
    {
        $user = $this->createUser('Self', 'User');

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/profile/view', [
            'viewed_id' => $user->id,
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You cannot record a view of your own profile.');

        $this->assertDatabaseCount('profile_views', 0);
    }

    public function test_get_profile_views_history(): void
    {
        $me = $this->createUser('Me', 'User');
        $viewer1 = $this->createUser('Viewer', 'One', [
            'designation' => 'Founder & CEO',
            'business_sub_category' => 'FinTech SaaS',
            'timezone' => 'Asia/Kolkata',
            'industry' => 'Technology',
        ]);
        $viewer2 = $this->createUser('Viewer', 'Two', [
            'designation' => 'Managing Director',
            'business_sub_category' => 'EdTech Platform',
            'timezone' => 'UTC',
            'industry' => 'Education',
        ]);

        // Insert views manually
        DB::table('profile_views')->insert([
            'id' => (string) Str::uuid(),
            'viewed_id' => $me->id,
            'viewer_id' => $viewer1->id,
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        DB::table('profile_views')->insert([
            'id' => (string) Str::uuid(),
            'viewed_id' => $me->id,
            'viewer_id' => $viewer2->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($me);

        $response = $this->getJson('/api/v1/profile/views');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_views', 2)
            ->assertJsonCount(2, 'data.views')
            ->assertJsonPath('data.views.0.viewer.id', $viewer2->id) // Ordered by desc
            ->assertJsonPath('data.views.0.viewer.designation', 'Managing Director')
            ->assertJsonPath('data.views.0.viewer.level4_category', 'EdTech Platform')
            ->assertJsonMissingPath('data.views.0.viewer.subcategory')
            ->assertJsonMissingPath('data.views.0.viewer.sub_category')
            ->assertJsonMissingPath('data.views.0.viewer.timezone')
            ->assertJsonMissingPath('data.views.0.viewer.industry')
            ->assertJsonPath('data.views.1.viewer.id', $viewer1->id)
            ->assertJsonPath('data.views.1.viewer.designation', 'Founder & CEO')
            ->assertJsonPath('data.views.1.viewer.level4_category', 'FinTech SaaS')
            ->assertJsonMissingPath('data.views.1.viewer.subcategory')
            ->assertJsonMissingPath('data.views.1.viewer.sub_category')
            ->assertJsonMissingPath('data.views.1.viewer.timezone')
            ->assertJsonMissingPath('data.views.1.viewer.industry');
    }

    public function test_repeat_profile_view_does_not_duplicate_record_or_count(): void
    {
        $viewer = $this->createUser('John', 'Doe');
        $viewed = $this->createUser('Jane', 'Smith');

        Sanctum::actingAs($viewer);

        // First view
        $response1 = $this->postJson('/api/v1/profile/view', [
            'viewed_id' => $viewed->id,
        ]);
        $response1->assertStatus(200);

        // Second view from same viewer
        $response2 = $this->postJson('/api/v1/profile/view', [
            'viewed_id' => $viewed->id,
        ]);
        $response2->assertStatus(200);

        // Only 1 record should exist in database
        $this->assertDatabaseCount('profile_views', 1);

        // Check getViews for viewed user
        Sanctum::actingAs($viewed);
        $viewsResponse = $this->getJson('/api/v1/profile/views');
        $viewsResponse->assertStatus(200)
            ->assertJsonPath('data.total_views', 1)
            ->assertJsonCount(1, 'data.views');
    }

    public function test_profile_views_resolves_level4_category_from_relation(): void
    {
        $level4Id = DB::table('circle_category_level4')->insertGetId([
            'name' => 'Artificial Intelligence & ML',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $me = $this->createUser('Target', 'User');
        $viewer = $this->createUser('AI', 'Expert', [
            'designation' => 'Chief AI Scientist',
            'business_category_id' => $level4Id,
        ]);

        DB::table('profile_views')->insert([
            'id' => (string) Str::uuid(),
            'viewed_id' => $me->id,
            'viewer_id' => $viewer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($me);

        $response = $this->getJson('/api/v1/profile/views');

        $response->assertStatus(200)
            ->assertJsonPath('data.views.0.viewer.designation', 'Chief AI Scientist')
            ->assertJsonPath('data.views.0.viewer.level4_category', 'Artificial Intelligence & ML')
            ->assertJsonMissingPath('data.views.0.viewer.subcategory')
            ->assertJsonMissingPath('data.views.0.viewer.sub_category')
            ->assertJsonMissingPath('data.views.0.viewer.timezone')
            ->assertJsonMissingPath('data.views.0.viewer.industry');
    }

    public function test_get_profile_views_supports_pagination(): void
    {
        $me = $this->createUser('Target', 'PaginationUser');
        $viewer1 = $this->createUser('Viewer', 'First');
        $viewer2 = $this->createUser('Viewer', 'Second');
        $viewer3 = $this->createUser('Viewer', 'Third');

        DB::table('profile_views')->insert([
            'id' => (string) Str::uuid(),
            'viewed_id' => $me->id,
            'viewer_id' => $viewer1->id,
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        DB::table('profile_views')->insert([
            'id' => (string) Str::uuid(),
            'viewed_id' => $me->id,
            'viewer_id' => $viewer2->id,
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        DB::table('profile_views')->insert([
            'id' => (string) Str::uuid(),
            'viewed_id' => $me->id,
            'viewer_id' => $viewer3->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($me);

        // Page 1 with per_page = 2
        $responsePage1 = $this->getJson('/api/v1/profile/views?per_page=2&page=1');
        $responsePage1->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_views', 3)
            ->assertJsonCount(2, 'data.views')
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.views.0.viewer.id', $viewer3->id)
            ->assertJsonPath('data.views.1.viewer.id', $viewer2->id);

        // Page 2 with per_page = 2
        $responsePage2 = $this->getJson('/api/v1/profile/views?per_page=2&page=2');
        $responsePage2->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_views', 3)
            ->assertJsonCount(1, 'data.views')
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.views.0.viewer.id', $viewer1->id);
    }
}
