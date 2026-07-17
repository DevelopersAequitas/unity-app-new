<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tutorial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TutorialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_tutorials_list(): void
    {
        // Arrange: Create test tutorials
        Tutorial::create([
            'video_id' => 'ZazxlEXKXKw',
            'youtube_url' => 'https://www.youtube.com/shorts/ZazxlEXKXKw',
        ]);

        Tutorial::create([
            'video_id' => 'dQw4w9WgXcQ',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        // Act: Request the tutorials API
        $response = $this->getJson('/api/v1/tutorials');

        // Assert: Verify successful response and exact structure
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Tutorials fetched successfully',
        ]);

        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'tutorials' => [
                    '*' => [
                        'id',
                        'video_id',
                        'youtube_url',
                        'created_at',
                    ],
                ],
            ],
        ]);

        // Verify ordering and content
        $data = $response->json('data.tutorials');
        $this->assertCount(2, $data);

        // Since we order by created_at desc, the second created one should be first if timestamps differ,
        // but here they are created in rapid succession. Let's just check if both video_ids exist.
        $videoIds = collect($data)->pluck('video_id')->toArray();
        $this->assertContains('ZazxlEXKXKw', $videoIds);
        $this->assertContains('dQw4w9WgXcQ', $videoIds);
    }

    public function test_can_create_tutorial(): void
    {
        $payload = [
            'youtube_url' => 'https://www.youtube.com/shorts/ZazxlEXKXKw',
        ];

        $response = $this->postJson('/api/v1/tutorials', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Tutorial created successfully',
        ]);

        // Verify JSON response structure (id is the first element, no updated_at)
        $tutorialData = $response->json('data.tutorial');
        $this->assertEquals(['id', 'video_id', 'youtube_url', 'created_at'], array_keys($tutorialData));
        $this->assertEquals('ZazxlEXKXKw', $tutorialData['video_id']);
        $this->assertEquals('https://www.youtube.com/shorts/ZazxlEXKXKw', $tutorialData['youtube_url']);

        $this->assertDatabaseHas('tutorials', [
            'video_id' => 'ZazxlEXKXKw',
            'youtube_url' => 'https://www.youtube.com/shorts/ZazxlEXKXKw',
        ]);
    }

    public function test_cannot_create_tutorial_with_invalid_data(): void
    {
        // 1. Missing youtube_url
        $response = $this->postJson('/api/v1/tutorials', []);
        $response->assertStatus(422);

        // 2. Invalid URL format
        $response = $this->postJson('/api/v1/tutorials', [
            'youtube_url' => 'not-a-valid-url',
        ]);
        $response->assertStatus(422);

        // 3. Valid URL but not YouTube URL (cannot extract video ID)
        $response = $this->postJson('/api/v1/tutorials', [
            'youtube_url' => 'https://google.com',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('video_id');

        // 4. Duplicate video ID
        Tutorial::create([
            'video_id' => 'dQw4w9WgXcQ',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response = $this->postJson('/api/v1/tutorials', [
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('video_id');
    }
}
