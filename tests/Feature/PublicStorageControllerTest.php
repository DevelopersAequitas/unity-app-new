<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicStorageControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_storage_route_serves_static_file_without_auth(): void
    {
        Storage::fake('public');

        $content = "\x89PNG\r\n\x1a\n".str_repeat('A', 100);
        $path = 'uploads/2026/09/03/test_sample_'.Str::uuid().'.png';
        Storage::disk('public')->put($path, $content);

        // Also place in real test disk if needed
        $fullPath = storage_path('app/public/'.$path);
        @mkdir(dirname($fullPath), 0777, true);
        file_put_contents($fullPath, $content);

        $response = $this->get('/storage/'.$path);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertGreaterThan(0, (int) $response->headers->get('Content-Length'));

        @unlink($fullPath);
    }

    public function test_public_storage_route_returns_404_for_missing_file(): void
    {
        $response = $this->get('/storage/uploads/2026/09/03/non_existent_file_'.Str::uuid().'.png');
        $response->assertStatus(404);
    }

    public function test_public_storage_route_serves_head_request(): void
    {
        $content = "\x89PNG\r\n\x1a\n".str_repeat('B', 200);
        $path = 'uploads/2026/09/03/test_head_'.Str::uuid().'.png';
        $fullPath = storage_path('app/public/'.$path);
        @mkdir(dirname($fullPath), 0777, true);
        file_put_contents($fullPath, $content);

        $response = $this->head('/storage/'.$path);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/png');

        @unlink($fullPath);
    }

    public function test_public_storage_route_blocks_path_traversal(): void
    {
        $response = $this->get('/storage/../../etc/passwd');
        $response->assertStatus(404);
    }
}
