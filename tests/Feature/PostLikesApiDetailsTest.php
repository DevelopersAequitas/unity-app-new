<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\City;
use App\Models\CircleCategory;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostLikesApiDetailsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('post_likes');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('circle_categories');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('users');
        Schema::dropIfExists('personal_access_tokens');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email', 255)->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('password_hash')->nullable();
            $table->string('company_name')->nullable();
            $table->string('city_id')->nullable();
            $table->string('city')->nullable();
            $table->string('business_city')->nullable();
            $table->string('city_of_residence')->nullable();
            $table->unsignedBigInteger('business_category_id')->nullable();
            $table->unsignedBigInteger('main_business_category_id')->nullable();
            $table->string('business_sub_category')->nullable();
            $table->string('profile_photo_file_id')->nullable();
            $table->string('profile_photo_url')->nullable();
            $table->string('timezone')->nullable();
            $table->integer('life_impacted_count')->default(0);
            $table->string('membership_status')->nullable();
            $table->integer('coins_balance')->default(0);
            $table->string('public_profile_slug')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cities', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('state')->nullable();
            $table->string('district')->nullable();
            $table->string('country')->nullable();
            $table->string('country_code')->nullable();
            $table->timestamps();
        });

        Schema::create('circle_categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->integer('level')->default(1);
            $table->string('circle_key')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->text('content_text')->nullable();
            $table->string('visibility')->default('public');
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('post_likes', function (Blueprint $table): void {
            $table->uuid('post_id');
            $table->uuid('user_id');
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['post_id', 'user_id']);
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
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

    public function test_get_post_likes_returns_user_and_business_details(): void
    {
        $city = City::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ahmedabad',
        ]);

        $category = CircleCategory::create([
            'name' => 'Information Technology',
            'slug' => 'information-technology',
        ]);

        $author = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Post',
            'last_name' => 'Author',
            'display_name' => 'Post Author',
            'email' => 'author@example.com',
        ]);

        $liker = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Rahul',
            'last_name' => 'Sharma',
            'display_name' => 'Rahul Sharma',
            'email' => 'rahul@example.com',
            'company_name' => 'Sharma Tech Solutions',
            'city_id' => $city->id,
            'business_category_id' => $category->id,
        ]);

        $post = Post::create([
            'id' => (string) Str::uuid(),
            'user_id' => $author->id,
            'content_text' => 'Testing post likes with user and business details',
            'visibility' => 'public',
        ]);

        PostLike::create([
            'post_id' => $post->id,
            'user_id' => $liker->id,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($author);

        $response = $this->getJson("/api/v1/posts/{$post->id}/likes");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'items' => [
                    '*' => [
                        'liked_at',
                        'name',
                        'city',
                        'business',
                        'category',
                        'user' => [
                            'id',
                            'name',
                            'display_name',
                            'profile_photo_url',
                            'city',
                            'business',
                            'company_name',
                            'category',
                            'business_category',
                            'life_impacted_count',
                            'timezone',
                        ],
                    ],
                ],
            ],
        ]);

        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertSame('Rahul Sharma', $items[0]['name']);
        $this->assertSame('Ahmedabad', $items[0]['city']);
        $this->assertSame('Sharma Tech Solutions', $items[0]['business']);
        $this->assertSame('Information Technology', $items[0]['category']);
        $this->assertSame('Rahul Sharma', $items[0]['user']['name']);
        $this->assertSame('Ahmedabad', $items[0]['user']['city']);
        $this->assertSame('Sharma Tech Solutions', $items[0]['user']['business']);
        $this->assertSame('Information Technology', $items[0]['user']['category']);
    }

    public function test_get_post_likes_handles_missing_business_and_category(): void
    {
        $author = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Post',
            'last_name' => 'Author',
            'display_name' => 'Post Author',
            'email' => 'author2@example.com',
        ]);

        $likerWithoutBusiness = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Priya',
            'last_name' => 'Patel',
            'display_name' => 'Priya Patel',
            'email' => 'priya@example.com',
            'city' => 'Surat',
            'company_name' => null,
            'business_category_id' => null,
        ]);

        $post = Post::create([
            'id' => (string) Str::uuid(),
            'user_id' => $author->id,
            'content_text' => 'Another post',
            'visibility' => 'public',
        ]);

        PostLike::create([
            'post_id' => $post->id,
            'user_id' => $likerWithoutBusiness->id,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($author);

        $response = $this->getJson("/api/v1/posts/{$post->id}/likes");

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertSame('Priya Patel', $items[0]['name']);
        $this->assertSame('Surat', $items[0]['city']);
        $this->assertNull($items[0]['business']);
        $this->assertNull($items[0]['category']);
    }
}
