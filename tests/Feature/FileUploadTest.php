<?php

namespace Tests\Feature;

use App\Models\FileModel;
use App\Models\User;
use App\Support\Media\Probe;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FileUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('files')) {
            Schema::create('files', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('uploader_user_id')->nullable();
                $table->string('s3_key');
                $table->string('mime_type');
                $table->integer('size_bytes');
                $table->integer('width')->nullable();
                $table->integer('height')->nullable();
                $table->integer('duration')->nullable();
                $table->boolean('is_orphaned')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('first_name', 100)->nullable();
                $table->string('last_name', 100)->nullable();
                $table->string('display_name', 150)->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('company_name', 255)->nullable();
                $table->string('membership_status', 50)->default('visitor');
                $table->integer('coins_balance')->default(0);
                $table->string('password_hash')->nullable();
                $table->string('public_profile_slug', 80)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function test_image_is_optimized_and_thumbnail_exists(): void
    {
        config([
            'filesystems.default' => 'public',
            'media.processing.mode' => 'sync',
            'media.keep_original' => true,
        ]);

        Storage::fake('public');

        $user = $this->makeUser();
        $image = UploadedFile::fake()->image('large-photo.jpg', 3000, 2000)->size(6000);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/v1/files/upload', ['file' => $image]);

        $response->assertCreated()->assertJsonPath('success', true);

        $file = FileModel::firstOrFail();

        $this->assertNotNull($file->width);
        $this->assertNotNull($file->height);
        $this->assertLessThanOrEqual(1600, $file->width);
        $this->assertLessThanOrEqual(1600, $file->height);
        $this->assertTrue(Storage::disk('public')->exists($file->s3_key));

        $files = Storage::disk('public')->allFiles('uploads');
        $this->assertCount(1, $files);
        $this->assertEquals($file->s3_key, $files[0]);
    }

    public function test_video_is_transcoded_and_poster_generated_when_ffmpeg_exists(): void
    {
        $probe = app(Probe::class);
        if (! $probe->ffmpegAvailable()) {
            $this->markTestSkipped('FFmpeg is not available in this environment.');
        }

        config([
            'filesystems.default' => 'public',
            'media.processing.mode' => 'sync',
            'media.keep_original' => true,
        ]);

        Storage::fake('public');

        $user = $this->makeUser();
        $videoPath = sys_get_temp_dir().'/upload-source-video.mp4';

        $generator = new Process([
            'ffmpeg',
            '-y',
            '-f',
            'lavfi',
            '-i',
            'testsrc=size=640x360:rate=24',
            '-t',
            '1.5',
            $videoPath,
        ]);

        $generator->run();

        if (! $generator->isSuccessful()) {
            $this->fail('Failed to generate test video: '.$generator->getErrorOutput());
        }

        $video = new UploadedFile($videoPath, 'sample.mov', 'video/mp4', null, true);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/v1/files/upload', ['file' => $video]);

        $response->assertCreated()->assertJsonPath('success', true);

        $file = FileModel::firstOrFail();
        $this->assertSame('video/mp4', $file->mime_type);
        $this->assertNotNull($file->duration);
        $this->assertTrue(Storage::disk('public')->exists($file->s3_key));

        $files = Storage::disk('public')->allFiles('uploads');
        $this->assertCount(1, $files);
        $this->assertEquals($file->s3_key, $files[0]);
    }

    public function test_file_show_endpoint_supports_path_fallback(): void
    {
        if (! Schema::hasTable('files')) {
            Schema::create('files', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('uploader_user_id')->nullable();
                $table->string('s3_key');
                $table->string('mime_type');
                $table->integer('size_bytes');
                $table->integer('width')->nullable();
                $table->integer('height')->nullable();
                $table->integer('duration')->nullable();
                $table->boolean('is_orphaned')->default(false);
                $table->timestamps();
            });
        }

        Storage::fake('public');
        Storage::disk('public')->put('ads/sample-ad-image.png', 'fake image content');

        $response = $this->get('/api/v1/files/ads/sample-ad-image.png');
        $response->assertStatus(200);

        $response2 = $this->get('/api/v1/files/sample-ad-image.png');
        $response2->assertStatus(200);
    }

    private function makeUser(): User
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('first_name', 100)->nullable();
                $table->string('last_name', 100)->nullable();
                $table->string('display_name', 150)->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('company_name', 255)->nullable();
                $table->string('membership_status', 50)->default('visitor');
                $table->integer('coins_balance')->default(0);
                $table->string('password_hash')->nullable();
                $table->string('public_profile_slug', 80)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        return User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => Str::uuid().'@example.com',
            'password_hash' => Hash::make('password'),
        ]);
    }
}
