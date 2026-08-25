<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\File;
use App\Models\FileModel;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class WelcomeCreativeTest extends TestCase
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
                $table->string('welcome_creative_url', 2000)->nullable();
                $table->string('profile_card_image_url', 2000)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('posts')) {
            Schema::create('posts', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->json('media')->nullable();
                $table->string('post_type', 50)->nullable();
                $table->string('status', 50)->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('files')) {
            Schema::create('files', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('uploader_user_id')->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->bigInteger('size_bytes')->default(0);
                $table->integer('width')->nullable();
                $table->integer('height')->nullable();
                $table->integer('duration')->nullable();
                $table->string('s3_key', 2000)->nullable();
                $table->boolean('is_orphaned')->default(false);
                $table->timestamps();
            });
        }
    }

    public function test_user_creation_attempts_generating_welcome_creative(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'display_name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '1234567890',
        ]);

        // The welcome_creative_url should be set
        $this->assertNotNull($user->welcome_creative_url);
        $this->assertNotNull($user->profile_card_image_url);

        // Verify the file was physically saved
        $uuid = null;
        if (preg_match('/\/api\/v1\/files\/([0-9a-fA-F-]{36})/', $user->welcome_creative_url, $matches)) {
            $uuid = $matches[1];
        }
        $this->assertNotNull($uuid);

        $fileRecord = FileModel::find($uuid);
        $this->assertNotNull($fileRecord);

        $disk = config('filesystems.default', 'public');
        $this->assertTrue(Storage::disk($disk)->exists($fileRecord->s3_key));
    }

    public function test_resolve_welcome_creative_regenerates_when_physical_file_is_missing(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'display_name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
            'phone' => '0987654321',
        ]);

        $uuid = null;
        preg_match('/\/api\/v1\/files\/([0-9a-fA-F-]{36})/', $user->welcome_creative_url, $matches);
        $uuid = $matches[1];

        $fileRecord = FileModel::findOrFail($uuid);

        // Physically delete from disk
        $disk = config('filesystems.default', 'public');
        Storage::disk($disk)->delete($fileRecord->s3_key);
        Storage::disk('public')->delete($fileRecord->s3_key);

        $this->assertFalse(Storage::disk($disk)->exists($fileRecord->s3_key));

        // Call resolveWelcomeCreativeUrl -> should regenerate physical file and keep the same URL/UUID
        $url = $user->resolveWelcomeCreativeUrl();
        $this->assertEquals($user->welcome_creative_url, $url);

        // Check file exists physically now
        $this->assertTrue(Storage::disk($disk)->exists($fileRecord->s3_key));
    }

    public function test_api_heals_missing_physical_file(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Bob',
            'last_name' => 'Builder',
            'display_name' => 'Bob Builder',
            'email' => 'bob@example.com',
            'phone' => '1112223333',
        ]);

        preg_match('/\/api\/v1\/files\/([0-9a-fA-F-]{36})/', $user->welcome_creative_url, $matches);
        $uuid = $matches[1];

        $fileRecord = FileModel::findOrFail($uuid);

        // Physically delete from disk
        $disk = config('filesystems.default', 'public');
        Storage::disk($disk)->delete($fileRecord->s3_key);
        Storage::disk('public')->delete($fileRecord->s3_key);

        $this->assertFalse(Storage::disk($disk)->exists($fileRecord->s3_key));

        // Make GET request to file endpoint -> should trigger self-healing, recreate file, and return HTTP 200
        $response = $this->getJson("/api/v1/files/{$uuid}");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/png');

        // Check file exists physically now
        $this->assertTrue(Storage::disk($disk)->exists($fileRecord->s3_key));
    }

    public function test_api_heals_missing_db_record_and_file(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alice',
            'last_name' => 'Wonderland',
            'display_name' => 'Alice Wonderland',
            'email' => 'alice@example.com',
            'phone' => '4445556666',
        ]);

        preg_match('/\/api\/v1\/files\/([0-9a-fA-F-]{36})/', $user->welcome_creative_url, $matches);
        $uuid = $matches[1];

        $fileRecord = FileModel::findOrFail($uuid);

        // Physically delete from disk and database
        $disk = config('filesystems.default', 'public');
        Storage::disk($disk)->delete($fileRecord->s3_key);
        Storage::disk('public')->delete($fileRecord->s3_key);
        $fileRecord->delete();

        $this->assertNull(File::find($uuid));

        // Make GET request to file endpoint -> should recreate record, regenerate file, and return HTTP 200
        $response = $this->getJson("/api/v1/files/{$uuid}");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/png');

        // Check database record and physical file exist now
        $newRecord = FileModel::findOrFail($uuid);
        $this->assertTrue(Storage::disk($disk)->exists($newRecord->s3_key));
    }
}
