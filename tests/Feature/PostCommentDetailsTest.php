<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CircleCategoryLevel4;
use App\Models\Post;
use App\Models\User;
use App\Services\Firebase\FcmService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostCommentDetailsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpInMemoryDatabase();

        $fcmMock = $this->mock(FcmService::class);
        $fcmMock->shouldReceive('sendToDevice')->andReturn([
            'success' => true,
            'firebase_response' => ['name' => 'mock-id'],
            'error' => null,
        ]);
    }

    protected function setUpInMemoryDatabase(): void
    {
        Schema::create('users', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->string('city')->nullable();
            $table->string('designation')->nullable();
            $table->string('company_name')->nullable();
            $table->unsignedBigInteger('business_category_id')->nullable();
            $table->integer('coins_balance')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circle_category_level4', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('posts', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->text('content_text')->nullable();
            $table->string('visibility')->default('public');
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('post_comments', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('post_id');
            $table->uuid('user_id');
            $table->text('content');
            $table->uuid('parent_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('app_notifications', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function test_post_comment_api_returns_user_details_including_city_designation_company_level4_and_coins(): void
    {
        $level4 = CircleCategoryLevel4::create([
            'name' => 'Solar Energy Equipment',
        ]);

        $author = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Author',
            'last_name' => 'User',
            'display_name' => 'Author User',
            'status' => 'active',
        ]);

        $commenter = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'display_name' => 'John Doe',
            'city' => 'Ahmedabad',
            'designation' => 'Managing Director',
            'company_name' => 'Sunlight Technologies',
            'business_category_id' => $level4->id,
            'coins_balance' => 450,
            'status' => 'active',
        ]);

        $post = Post::create([
            'id' => (string) Str::uuid(),
            'user_id' => $author->id,
            'content_text' => 'Hello World Post',
        ]);

        // 1. Test POST /api/v1/posts/{id}/comments
        $storeResponse = $this->actingAs($commenter, 'sanctum')
            ->postJson("/api/v1/posts/{$post->id}/comments", [
                'content' => 'Great initiative!',
            ]);

        $storeResponse->assertStatus(201);
        $storeResponse->assertJsonPath('data.user.id', (string) $commenter->id);
        $storeResponse->assertJsonPath('data.user.city', 'Ahmedabad');
        $storeResponse->assertJsonPath('data.user.designation', 'Managing Director');
        $storeResponse->assertJsonPath('data.user.company_name', 'Sunlight Technologies');
        $storeResponse->assertJsonPath('data.user.level4_category', 'Solar Energy Equipment');
        $storeResponse->assertJsonPath('data.user.impact_coins', 450);
        $storeResponse->assertJsonMissingPath('data.user.subcategory_level4');
        $storeResponse->assertJsonMissingPath('data.user.impact_coin');
        $storeResponse->assertJsonMissingPath('data.user.coins_balance');

        // 2. Test GET /api/v1/posts/{id}/comments
        $listResponse = $this->actingAs($author, 'sanctum')
            ->getJson("/api/v1/posts/{$post->id}/comments");

        $listResponse->assertStatus(200);
        $listResponse->assertJsonPath('data.items.0.user.city', 'Ahmedabad');
        $listResponse->assertJsonPath('data.items.0.user.designation', 'Managing Director');
        $listResponse->assertJsonPath('data.items.0.user.company_name', 'Sunlight Technologies');
        $listResponse->assertJsonPath('data.items.0.user.level4_category', 'Solar Energy Equipment');
        $listResponse->assertJsonPath('data.items.0.user.impact_coins', 450);
        $listResponse->assertJsonMissingPath('data.items.0.user.subcategory_level4');
        $listResponse->assertJsonMissingPath('data.items.0.user.impact_coin');
        $listResponse->assertJsonMissingPath('data.items.0.user.coins_balance');
    }
}
