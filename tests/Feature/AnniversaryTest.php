<?php

namespace Tests\Feature;

use App\Models\Notifications\AppNotification;
use App\Models\Notifications\NotificationPreference;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnniversaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email', 255)->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('password_hash');
            $table->string('company_name', 150)->nullable();
            $table->string('designation', 100)->nullable();
            $table->uuid('city_id')->nullable();
            $table->string('status', 50)->default('inactive');
            $table->string('registration_source', 100)->nullable();
            $table->string('membership_status', 50)->default('visitor');
            $table->timestamp('membership_expiry')->nullable();
            $table->timestamp('membership_starts_at')->nullable();
            $table->timestamp('membership_ends_at')->nullable();
            $table->bigInteger('coins_balance')->default(0);
            $table->string('public_profile_slug', 80)->nullable()->unique();
            $table->string('website', 255)->nullable();
            $table->text('sustainability_contribution')->nullable();
            $table->json('sustainability_areas')->nullable();
            $table->json('greenpreneur_goals')->nullable();
            $table->json('interests')->nullable();
            $table->string('community_directory_listing', 10)->nullable();
            $table->date('anniversary_date')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        // Create anniversary_templates table if not exists for testing
        if (! Schema::hasTable('anniversary_templates')) {
            Schema::create('anniversary_templates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('image_path', 255);
                $table->text('message');
                $table->boolean('is_active')->default(false);
                $table->timestamps();
            });
        }

        // Add anniversary columns to posts table if not exists for testing
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'post_type')) {
                $table->string('post_type', 50)->nullable()->default('standard');
            }
            if (! Schema::hasColumn('posts', 'template_id')) {
                $table->uuid('template_id')->nullable();
            }
            if (! Schema::hasColumn('posts', 'title')) {
                $table->string('title', 255)->nullable();
            }
            if (! Schema::hasColumn('posts', 'description')) {
                $table->text('description')->nullable();
            }
            if (! Schema::hasColumn('posts', 'image')) {
                $table->text('image')->nullable();
            }
            if (! Schema::hasColumn('posts', 'status')) {
                $table->string('status', 50)->nullable()->default('active');
            }
        });
    }

    /**
     * Test registration validates and saves anniversary_date.
     */
    public function test_registration_saves_anniversary_date(): void
    {
        $payload = [
            'first_name' => 'Alice',
            'last_name' => 'Green',
            'email' => 'alice@example.com',
            'phone' => '9999999999',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'anniversary_date' => '2018-06-15',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);
        $response->assertStatus(201);

        $user = User::where('email', 'alice@example.com')->firstOrFail();
        $this->assertEquals('2018-06-15', $user->anniversary_date->format('Y-m-d'));
    }

    /**
     * Test profile update validates, whitelists and updates anniversary_date.
     */
    public function test_profile_update_saves_anniversary_date(): void
    {
        $user = User::factory()->create([
            'email' => 'bob@example.com',
            'anniversary_date' => null,
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        // V1 Profile controller test
        $response = $this->patchJson('/api/v1/profile', [
            'anniversary_date' => '2012-10-25',
        ]);
        $response->assertOk();

        $user->refresh();
        $this->assertEquals('2012-10-25', $user->anniversary_date->format('Y-m-d'));

        // Profile controller API resources test
        $resource = $response->json('data');
        $this->assertEquals('2012-10-25', $resource['anniversary_date']);
    }

    /**
     * Test dedicated scheduler command identifies anniversary users,
     * creates timeline posts, sends push notifications, and prevents duplicates.
     */
    public function test_anniversary_notifications_command_execution(): void
    {
        // Fix time to specific date (config timezone context)
        Carbon::setTestNow(Carbon::parse('2026-08-15 10:00:00', config('app.timezone', 'UTC')));

        // Celebrating user today
        $celebratingUser = User::factory()->create([
            'first_name' => 'Celebrate',
            'display_name' => 'Celebrate User',
            'status' => 'active',
            'anniversary_date' => '2015-08-15',
        ]);

        // Enable push notifications for the celebrating user
        NotificationPreference::create([
            'user_id' => $celebratingUser->id,
            'push_enabled' => true,
        ]);

        // Non-celebrating user today (tomorrow anniversary)
        $nonCelebratingUser = User::factory()->create([
            'first_name' => 'Tomorrow',
            'status' => 'active',
            'anniversary_date' => '2015-08-16',
        ]);

        // User with NULL anniversary_date (backward compatibility)
        $nullAnniversaryUser = User::factory()->create([
            'first_name' => 'NullDate',
            'status' => 'active',
            'anniversary_date' => null,
        ]);

        // 1. Run the command
        $this->artisan('app:send-anniversary-notifications')->assertExitCode(0);

        // Verify Timeline Post was created for celebrating user
        $this->assertDatabaseHas('posts', [
            'user_id' => $celebratingUser->id,
            'source_type' => 'anniversary',
            'source_id' => $celebratingUser->id,
            'source_event' => 'anniversary',
            'visibility' => 'public',
            'moderation_status' => 'approved',
        ]);

        $post = Post::where('user_id', $celebratingUser->id)->where('source_type', 'anniversary')->firstOrFail();
        $this->assertStringContainsString($celebratingUser->display_name, $post->content_text);

        // Verify post has creative fields populated correctly
        $this->assertEquals('anniversary', $post->post_type);
        $this->assertEquals('Happy Anniversary! 🎉', $post->title);
        $this->assertNotNull($post->image);
        $this->assertEquals('active', $post->status);

        // Verify generated creative is stored as webp in files table
        $fileId = basename(parse_url($post->image, PHP_URL_PATH));
        $this->assertDatabaseHas('files', [
            'id' => $fileId,
            'mime_type' => 'image/webp',
        ]);

        // Verify Push Notification was dispatched via AppNotification
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $celebratingUser->id,
            'type' => 'birthday_anniversary',
            'title' => 'Happy Anniversary! 🎉',
        ]);

        // Verify NO timeline post created for other users
        $this->assertDatabaseMissing('posts', [
            'user_id' => $nonCelebratingUser->id,
            'source_type' => 'anniversary',
        ]);
        $this->assertDatabaseMissing('posts', [
            'user_id' => $nullAnniversaryUser->id,
            'source_type' => 'anniversary',
        ]);

        // Verify NO push notification sent to other users
        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $nonCelebratingUser->id,
            'type' => 'birthday_anniversary',
        ]);
        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $nullAnniversaryUser->id,
            'type' => 'birthday_anniversary',
        ]);

        // 2. Run the command a second time (Duplicate Check verification)
        $this->artisan('app:send-anniversary-notifications')->assertExitCode(0);

        // Verify duplicate prevention worked: only ONE post and ONE notification exist
        $this->assertEquals(1, Post::where('user_id', $celebratingUser->id)->where('source_type', 'anniversary')->count());
        $this->assertEquals(1, AppNotification::where('user_id', $celebratingUser->id)->where('type', 'birthday_anniversary')->count());

        Carbon::setTestNow();
    }

    /**
     * Test Anniversary Creative Admin Template Management Workflow.
     */
    public function test_anniversary_template_admin_workflow(): void
    {
        // 1. Create global admin role and user
        $role = \App\Models\Role::firstOrCreate(
            ['key' => 'global_admin'],
            ['name' => 'Global Admin']
        );
        $admin = \App\Models\AdminUser::forceCreate([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Global Admin User',
            'email' => 'admin_test@example.com',
        ]);
        $admin->roles()->attach($role->id);

        $this->actingAs($admin, 'admin');

        // 2. Upload template image
        $disk = config('filesystems.default', 'public');
        \Illuminate\Support\Facades\Storage::fake($disk);
        $file = \Illuminate\Http\UploadedFile::fake()->image('test_template.png', 1080, 1080);

        $payload = [
            'image' => $file,
            'message' => 'Congratulations on your work anniversary! 🍾🥂',
            'is_active' => '1',
        ];

        $response = $this->post('/admin/anniversary-creatives', $payload);
        $response->assertRedirect('/admin/anniversary-creatives');

        $this->assertDatabaseHas('anniversary_templates', [
            'message' => 'Congratulations on your work anniversary! 🍾🥂',
            'is_active' => true,
        ]);

        $template = \App\Models\AnniversaryTemplate::where('message', 'like', '%work anniversary%')->firstOrFail();
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk($disk)->exists($template->image_path));

        // 3. Toggle template status
        $response = $this->post("/admin/anniversary-creatives/{$template->id}/toggle");
        $response->assertRedirect('/admin/anniversary-creatives');
        $this->assertFalse($template->fresh()->is_active);

        // 4. Delete template
        $response = $this->delete("/admin/anniversary-creatives/{$template->id}");
        $response->assertRedirect('/admin/anniversary-creatives');
        $this->assertDatabaseMissing('anniversary_templates', ['id' => $template->id]);
        $this->assertFalse(\Illuminate\Support\Facades\Storage::disk($disk)->exists($template->image_path));
    }
}
