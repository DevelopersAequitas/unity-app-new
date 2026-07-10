<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BirthdayCreativeTest extends TestCase
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
            $table->string('designation', 100)->nullable();
            $table->string('status', 50)->default('inactive');
            $table->string('membership_status', 50)->default('visitor');
            $table->date('dob')->nullable();
            $table->string('timezone', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Add birthday columns to posts table if not exists for testing
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'post_type')) {
                $table->string('post_type', 50)->nullable()->default('standard');
            }
            if (! Schema::hasColumn('posts', 'title')) {
                $table->string('title', 255)->nullable();
            }
            if (! Schema::hasColumn('posts', 'description')) {
                $table->text('description')->nullable();
            }
            if (! Schema::hasColumn('posts', 'status')) {
                $table->string('status', 50)->nullable()->default('active');
            }
        });

        if (config('database.default') === 'sqlite') {
            $db = DB::connection()->getPdo();
            $db->sqliteCreateFunction('to_char', function ($value, $format) {
                if (empty($value)) {
                    return null;
                }
                $date = \Carbon\Carbon::parse($value);
                if ($format === 'MM-DD') {
                    return $date->format('m-d');
                }
                if ($format === 'YYYY-MM') {
                    return $date->format('Y-m');
                }

                return $date->format($format);
            });
        }
    }

    public function test_birthday_creative_generation_assigns_system_user(): void
    {
        Storage::fake('public');

        // Setup users:
        // 1. Celebrating user in America/New_York, currently 12:30 AM there
        $celebratingUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'display_name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password_hash' => bcrypt('password'),
            'dob' => '1990-07-10',
            'status' => 'active',
            'timezone' => 'America/New_York',
        ]);

        // 2. Celebrating user but local time is NOT 12:00 AM - 12:59 AM (e.g. currently 2:30 AM)
        $celebratingUserWrongHour = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Wrong',
            'last_name' => 'Hour',
            'display_name' => 'Wrong Hour',
            'email' => 'wrong.hour@example.com',
            'password_hash' => bcrypt('password'),
            'dob' => '1990-07-10',
            'status' => 'active',
            'timezone' => 'America/Chicago',
        ]);

        // 3. Non-celebrating user
        $nonCelebratingUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'display_name' => 'Jane Smith',
            'email' => 'jane.smith@example.com',
            'password_hash' => bcrypt('password'),
            'dob' => '1990-08-10',
            'status' => 'active',
            'timezone' => 'America/New_York',
        ]);

        // Set test now to: 2026-07-10 04:30:00 UTC
        // - America/New_York (UTC-4) -> 2026-07-10 00:30:00 (12:30 AM) -> MATCHES!
        // - America/Chicago (UTC-5) -> 2026-07-09 23:30:00 (11:30 PM) or if we adjust offset, let's make Chicago local time 01:30 AM
        // Let's set UTC time to 2026-07-10T04:30:00Z:
        Carbon::setTestNow(Carbon::createFromFormat('Y-m-d H:i:s', '2026-07-10 04:30:00', 'UTC'));

        // Run birthday creative command
        $this->artisan('birthday:generate-creatives')->assertExitCode(0);

        // Retrieve PeersGlobal Unity user
        $systemUser = User::where('email', 'info@peersglobal.com')->first();
        $this->assertNotNull($systemUser);
        $this->assertEquals('PeersGlobal Unity', $systemUser->display_name);

        // Verify birthday post was created for celebratingUser
        $post = Post::where('source_id', $celebratingUser->id)
            ->where('post_type', 'birthday')
            ->first();

        $this->assertNotNull($post);
        $this->assertEquals($systemUser->id, $post->user_id);
        $this->assertEquals('birthday', $post->source_type);
        $this->assertEquals('birthday', $post->source_event);
        $this->assertNotEmpty($post->media);

        // Verify wrong hour user has no birthday post
        $this->assertFalse(Post::where('source_id', $celebratingUserWrongHour->id)->exists());

        // Verify non-celebrating user has no birthday post
        $this->assertFalse(Post::where('source_id', $nonCelebratingUser->id)->exists());

        // Test duplicate prevention
        $this->artisan('birthday:generate-creatives')->assertExitCode(0);
        $this->assertEquals(1, Post::where('source_id', $celebratingUser->id)->where('post_type', 'birthday')->count());

        Carbon::setTestNow();
    }
}
