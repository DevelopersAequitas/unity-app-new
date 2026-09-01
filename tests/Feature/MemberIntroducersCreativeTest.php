<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\File;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberIntroducersCreativeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTablesExist();
    }

    private function ensureTablesExist(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name', 255)->nullable();
                $table->string('first_name', 100)->nullable();
                $table->string('last_name', 100)->nullable();
                $table->string('display_name', 150)->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('company', 255)->nullable();
                $table->string('company_name', 255)->nullable();
                $table->string('business_name', 255)->nullable();
                $table->string('designation', 255)->nullable();
                $table->string('city', 255)->nullable();
                $table->string('membership_status', 50)->default('circle_peer');
                $table->string('status', 50)->default('active');
                $table->string('contribution_award_name', 255)->nullable();
                $table->string('contribution_award_recognition', 255)->nullable();
                $table->uuid('introduced_by')->nullable();
                $table->integer('members_introduced_count')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name', 255)->nullable();
                $table->string('email', 255)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('posts')) {
            Schema::create('posts', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('circle_id')->nullable();
                $table->text('content_text')->nullable();
                $table->json('media')->nullable();
                $table->json('tags')->nullable();
                $table->string('visibility', 50)->default('public');
                $table->string('moderation_status', 50)->default('approved');
                $table->boolean('sponsored')->default(false);
                $table->boolean('is_deleted')->default(false);
                $table->string('source_type', 50)->nullable();
                $table->string('source_id', 100)->nullable();
                $table->string('source_event', 50)->nullable();
                $table->string('post_type', 50)->nullable();
                $table->string('title', 255)->nullable();
                $table->text('description')->nullable();
                $table->string('image', 2000)->nullable();
                $table->string('status', 50)->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name', 255);
                $table->string('key', 100);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_user_roles')) {
            Schema::create('admin_user_roles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('role_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('role_module_access')) {
            Schema::create('role_module_access', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('role_id');
                $table->string('module_key', 100);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_modules')) {
            Schema::create('admin_modules', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('key', 100)->nullable();
                $table->string('name', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('files')) {
            Schema::create('files', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('uploader_user_id')->nullable();
                $table->string('original_name', 255)->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->bigInteger('file_size')->default(0);
                $table->bigInteger('size_bytes')->default(0);
                $table->integer('width')->nullable();
                $table->integer('height')->nullable();
                $table->integer('duration')->nullable();
                $table->string('s3_key', 2000)->nullable();
                $table->string('disk', 50)->default('public');
                $table->timestamps();
            });
        }
    }

    private function createAdmin(): AdminUser
    {
        $role = Role::firstOrCreate(
            ['key' => 'global_admin'],
            ['id' => (string) Str::uuid(), 'name' => 'Global Admin']
        );

        $admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Admin Tester',
            'email' => 'admin.'.Str::random(6).'@example.com',
        ]);

        DB::table('admin_user_roles')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'role_id' => $role->id,
        ]);

        return $admin;
    }

    public function test_can_fetch_introduced_peers_list_for_introducer(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin');

        $introducer = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Sara',
            'last_name' => 'Introducer',
            'email' => 'sara.'.Str::random(6).'@example.com',
            'members_introduced_count' => 2,
        ]);

        $peer1 = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alex',
            'last_name' => 'Introduced',
            'email' => 'alex.'.Str::random(6).'@example.com',
            'introduced_by' => $introducer->id,
        ]);

        $response = $this->getJson(route('admin.member-introducers.introduced-peers', $introducer->id));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('introducer.name', 'Sara Introducer');
        $this->assertCount(1, $response->json('introduced_peers'));
    }

    public function test_can_preview_creative_and_post_to_timeline(): void
    {
        $this->withoutExceptionHandling();
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin');

        $introducer = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Mark',
            'last_name' => 'Growth',
            'display_name' => 'Mark Growth',
            'email' => 'mark.'.Str::random(6).'@example.com',
            'company_name' => 'Peers Tech Ltd',
            'members_introduced_count' => 5,
        ]);

        // Preview route test
        $previewResponse = $this->getJson(route('admin.member-introducers.creative-preview', $introducer->id));
        $previewResponse->assertStatus(200);
        $previewResponse->assertJsonPath('success', true);
        $previewResponse->assertJsonPath('meta.title', 'INFLUENCER');

        // Post creative to timeline test
        $postResponse = $this->postJson(route('admin.member-introducers.post-creative', $introducer->id));
        if ($postResponse->status() !== 200) {
            fwrite(STDERR, "\nPOST ERROR: ".json_encode($postResponse->json())."\n");
        }
        $postResponse->assertStatus(200);
        $postResponse->assertJsonPath('success', true);

        $this->assertDatabaseHas('posts', [
            'source_type' => 'member_introduction',
            'source_id' => $introducer->id,
            'post_type' => 'growth_honour',
        ]);
    }

    public function test_can_render_member_introducers_index_with_creative_showcase(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $response = $this->get(route('admin.member-introducers.index'));
        $response->assertStatus(200);
        $response->assertSee('Introducers List');
        $response->assertSee('Peers Creative Post in Timeline');
        $response->assertSee('memberIntroducersSubmenu');
    }

    public function test_can_render_creative_studio_tab(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $introducer = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Emma',
            'last_name' => 'Stone',
            'display_name' => 'Emma Stone',
            'email' => 'emma.'.Str::random(6).'@example.com',
            'members_introduced_count' => 10,
        ]);

        $response = $this->get(route('admin.member-introducers.index', ['tab' => 'creative', 'peer_id' => $introducer->id]));
        $response->assertStatus(200);
        $response->assertSee('Peers Recognition Creative Studio &amp; Timeline Publisher', false);
        $response->assertSee('Post Creative to Timeline');
        $response->assertSee('Track 1 Growth Honours Recognition Creatives');
        $response->assertSee('CONNECTOR');
        $response->assertSee('GLOBAL ICON');
    }

    public function test_creative_studio_supports_peer_with_zero_introductions(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $peer = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Zero',
            'last_name' => 'Introductions',
            'display_name' => 'Zero Introductions',
            'email' => 'zero.'.Str::random(6).'@example.com',
            'members_introduced_count' => 0,
        ]);

        // When visiting index without introducers, peer is listed in Studio
        $indexResponse = $this->get(route('admin.member-introducers.index', ['tab' => 'creative']));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('Zero Introductions');

        // Preview should default to Level 1 CONNECTOR
        $previewResponse = $this->getJson(route('admin.member-introducers.creative-preview', $peer->id));
        $previewResponse->assertStatus(200);
        $previewResponse->assertJsonPath('meta.title', 'CONNECTOR');

        // Post to timeline should succeed
        $postResponse = $this->postJson(route('admin.member-introducers.post-creative', $peer->id));
        $postResponse->assertStatus(200);
        $postResponse->assertJsonPath('success', true);

        $this->assertDatabaseHas('posts', [
            'source_type' => 'member_introduction',
            'source_id' => $peer->id,
            'post_type' => 'growth_honour',
        ]);
    }

    public function test_file_serving_self_heals_missing_creative_file(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $peer = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Self',
            'last_name' => 'Healing',
            'display_name' => 'Self Healing',
            'email' => 'self.'.Str::random(6).'@example.com',
            'members_introduced_count' => 5,
        ]);

        // Post to timeline
        $this->postJson(route('admin.member-introducers.post-creative', $peer->id))->assertStatus(200);

        // Fetch file record
        $post = Post::where('source_id', $peer->id)->where('post_type', 'growth_honour')->firstOrFail();
        $mediaItem = $post->media[0];
        $fileId = $mediaItem['id'];

        $fileRecord = File::findOrFail($fileId);

        // Physically delete file from storage to simulate missing physical file
        $disk = config('filesystems.default', 'public');
        if (Storage::disk($disk)->exists($fileRecord->s3_key)) {
            Storage::disk($disk)->delete($fileRecord->s3_key);
        }
        if (Storage::disk('public')->exists($fileRecord->s3_key)) {
            Storage::disk('public')->delete($fileRecord->s3_key);
        }

        $this->assertFalse(Storage::disk($disk)->exists($fileRecord->s3_key));

        // Serve file via FileController -> Should regenerate on-the-fly and respond successfully
        $response = $this->get("/api/v1/files/{$fileId}");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/webp');

        // Verify the file was restored physically in storage
        $this->assertTrue(Storage::disk($disk)->exists($fileRecord->s3_key));
    }

    public function test_file_serving_self_heals_missing_database_record_and_file(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $peer = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Db',
            'last_name' => 'Healing',
            'display_name' => 'Db Healing',
            'email' => 'db.'.Str::random(6).'@example.com',
            'members_introduced_count' => 3,
        ]);

        // Post to timeline
        $this->postJson(route('admin.member-introducers.post-creative', $peer->id))->assertStatus(200);

        // Fetch file record
        $post = Post::where('source_id', $peer->id)->where('post_type', 'growth_honour')->firstOrFail();
        $mediaItem = $post->media[0];
        $fileId = $mediaItem['id'];

        $fileRecord = File::findOrFail($fileId);

        // Physically delete file from storage
        $disk = config('filesystems.default', 'public');
        if (Storage::disk($disk)->exists($fileRecord->s3_key)) {
            Storage::disk($disk)->delete($fileRecord->s3_key);
        }

        // Delete database record of the file
        $fileRecord->delete();

        $this->assertNull(File::find($fileId));

        // Serve file via FileController -> Should recreate database record & regenerate file on-the-fly and respond successfully
        $response = $this->get("/api/v1/files/{$fileId}");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/webp');

        // Verify the database record and file exist now
        $newFileRecord = File::findOrFail($fileId);
        $this->assertTrue(Storage::disk($disk)->exists($newFileRecord->s3_key));
    }
}
