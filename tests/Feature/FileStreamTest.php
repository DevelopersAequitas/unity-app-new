<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Resources\FileResource;
use App\Models\File;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FileStreamTest extends TestCase
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
    }

    public function test_file_stream_returns_correct_video_mime_types(): void
    {
        Storage::fake('public');
        $videoContent = str_repeat('A', 1024);
        Storage::disk('public')->put('uploads/test-intro.mp4', $videoContent);

        $fileMp4 = File::create([
            'id' => (string) Str::uuid(),
            's3_key' => 'uploads/test-intro.mp4',
            'mime_type' => 'application/octet-stream',
            'size_bytes' => 1024,
        ]);

        $response = $this->get('/api/v1/files/'.$fileMp4->id);
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'video/mp4');
        $response->assertHeader('Accept-Ranges', 'bytes');

        Storage::disk('public')->put('uploads/test-intro.mov', $videoContent);
        $fileMov = File::create([
            'id' => (string) Str::uuid(),
            's3_key' => 'uploads/test-intro.mov',
            'mime_type' => 'application/octet-stream',
            'size_bytes' => 1024,
        ]);

        $responseMov = $this->get('/api/v1/files/'.$fileMov->id);
        $responseMov->assertStatus(200);
        $responseMov->assertHeader('Content-Type', 'video/quicktime');
    }

    public function test_file_stream_supports_byte_range_requests_206_partial_content(): void
    {
        Storage::fake('public');
        $dummyContent = str_repeat('X', 5000);
        Storage::disk('public')->put('uploads/range-test.mp4', $dummyContent);

        $file = File::create([
            'id' => (string) Str::uuid(),
            's3_key' => 'uploads/range-test.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 5000,
        ]);

        $response = $this->withHeaders([
            'Range' => 'bytes=0-1023',
        ])->get('/api/v1/files/'.$file->id);

        $response->assertStatus(206);
        $response->assertHeader('Content-Type', 'video/mp4');
        $response->assertHeader('Accept-Ranges', 'bytes');
        $response->assertHeader('Content-Range', 'bytes 0-1023/5000');
        $response->assertHeader('Content-Length', '1024');

        $streamContent = $response->streamedContent();
        $this->assertEquals(1024, strlen($streamContent));
    }

    public function test_file_resource_url_uses_api_streaming_endpoint(): void
    {
        Storage::fake('public');
        $file = File::create([
            'id' => (string) Str::uuid(),
            's3_key' => 'uploads/resource-test.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 1024,
        ]);

        $resource = (new FileResource($file))->toArray(request());
        $this->assertEquals(url('/api/v1/files/'.$file->id), $resource['url']);
    }
}
