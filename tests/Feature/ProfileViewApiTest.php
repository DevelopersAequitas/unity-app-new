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
        Schema::dropIfExists('users');
        Schema::dropIfExists('personal_access_tokens');

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email', 255)->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('password_hash')->nullable();
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

    private function createUser(string $firstName, string $lastName): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $firstName.' '.$lastName,
            'email' => strtolower($firstName.'.'.$lastName.'-'.Str::random(4).'@example.com'),
            'phone' => (string) random_int(1000000000, 9999999999),
        ]);
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
        $viewer1 = $this->createUser('Viewer', 'One');
        $viewer2 = $this->createUser('Viewer', 'Two');

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
            ->assertJsonPath('data.views.1.viewer.id', $viewer1->id);
    }
}
