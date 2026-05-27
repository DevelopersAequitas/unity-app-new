<?php

namespace Tests\Feature;

use App\Models\FileModel;
use App\Models\User;
use App\Support\Media\Probe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FileUploadTest extends TestCase
{
    use RefreshDatabase;

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
        $response->assertJsonPath('data.url', route('api.v1.files.show', ['id' => FileModel::firstOrFail()->id]));

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

    public function test_uploaded_file_can_be_fetched_via_api_route(): void
    {
        config([
            'filesystems.default' => 'public',
            'media.processing.mode' => 'sync',
        ]);

        Storage::fake('public');

        $user = $this->makeUser();
        $image = UploadedFile::fake()->image('photo.jpg', 1000, 800)->size(500);

        $uploadResponse = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/v1/files/upload', ['file' => $image]);

        $uploadResponse->assertCreated();

        $file = FileModel::firstOrFail();

        $fetchResponse = $this->get('/api/v1/files/' . $file->id);
        $fetchResponse->assertOk();
        $fetchResponse->assertHeader('Content-Type', $file->mime_type);
    }

    public function test_file_show_returns_json_404_when_record_or_file_missing(): void
    {
        config([
            'filesystems.default' => 'public',
        ]);

        Storage::fake('public');

        $this->getJson('/api/v1/files/' . (string) Str::uuid())
            ->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'File not found.',
                'data' => null,
            ]);

        $file = FileModel::create([
            'uploader_user_id' => null,
            's3_key' => 'uploads/does-not-exist.webp',
            'mime_type' => 'image/webp',
            'size_bytes' => 100,
            'width' => 10,
            'height' => 10,
            'duration' => null,
        ]);

        $this->getJson('/api/v1/files/' . $file->id)
            ->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'File not found.',
                'data' => null,
            ]);
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
        $videoPath = sys_get_temp_dir() . '/upload-source-video.mp4';

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
            $this->fail('Failed to generate test video: ' . $generator->getErrorOutput());
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

    private function makeUser(): User
    {
        return User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => Str::uuid() . '@example.com',
            'password_hash' => Hash::make('password'),
        ]);
    }
}
