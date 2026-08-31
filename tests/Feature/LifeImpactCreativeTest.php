<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Role;
use App\Models\User;
use App\Services\Creative\LifeImpactCreativeGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LifeImpactCreativeTest extends TestCase
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
                $table->uuid('city_id')->nullable();
                $table->uuid('business_category_id')->nullable();
                $table->string('business_sub_category', 255)->nullable();
                $table->string('membership_status', 50)->default('circle_peer');
                $table->string('status', 50)->default('active');
                $table->integer('life_impacted_count')->default(0);
                $table->uuid('profile_photo_file_id')->nullable();
                $table->string('profile_photo_url', 2000)->nullable();
                $table->string('password', 255)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name', 255)->nullable();
                $table->string('email', 255)->nullable();
                $table->string('status', 50)->default('active');
                $table->timestamps();
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

        if (! Schema::hasTable('circles')) {
            Schema::create('circles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name', 255);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('circle_members')) {
            Schema::create('circle_members', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('circle_id');
                $table->uuid('user_id');
                $table->string('status', 50)->default('approved');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('life_impact_histories')) {
            Schema::create('life_impact_histories', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->integer('impact_value')->default(1);
                $table->integer('life_impacted')->default(1);
                $table->string('action', 255)->nullable();
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
            'status' => 'active',
        ]);

        DB::table('admin_user_roles')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'role_id' => $role->id,
        ]);

        return $admin;
    }

    public function test_all_12_recognition_levels_are_configured(): void
    {
        /** @var LifeImpactCreativeGenerator $generator */
        $generator = app(LifeImpactCreativeGenerator::class);
        $levels = $generator->getAllRecognitionLevels();

        $this->assertCount(12, $levels);

        $expectedTiers = [
            25 => 'IMPACT CREATOR',
            50 => 'CHANGE MAKER',
            100 => 'LIFE CHANGER',
            250 => 'IMPACT BUILDER',
            500 => 'ECOSYSTEM BUILDER',
            1000 => 'IMPACT ARCHITECT',
            2500 => 'LEGACY MAKER',
            5000 => 'TORCHBEARER',
            10000 => 'WORLD CHANGER',
            25000 => 'HUMANITARIAN',
            50000 => 'HISTORY MAKER',
            100000 => 'PEERS GLOBAL LEGEND',
        ];

        foreach ($expectedTiers as $threshold => $title) {
            $this->assertArrayHasKey($threshold, $levels);
            $this->assertSame($title, $levels[$threshold]['title']);
            $this->assertSame($threshold, $levels[$threshold]['required_count']);
            $this->assertNotEmpty($levels[$threshold]['hashtag']);
            $this->assertNotEmpty($levels[$threshold]['badge_image']);
        }
    }

    public function test_recognition_meta_resolves_correct_tier_for_counts(): void
    {
        /** @var LifeImpactCreativeGenerator $generator */
        $generator = app(LifeImpactCreativeGenerator::class);

        $this->assertSame('IMPACT CREATOR', $generator->getRecognitionMeta(10)['title']);
        $this->assertSame('IMPACT CREATOR', $generator->getRecognitionMeta(25)['title']);
        $this->assertSame('IMPACT CREATOR', $generator->getRecognitionMeta(49)['title']);
        $this->assertSame('CHANGE MAKER', $generator->getRecognitionMeta(50)['title']);
        $this->assertSame('LIFE CHANGER', $generator->getRecognitionMeta(100)['title']);
        $this->assertSame('IMPACT BUILDER', $generator->getRecognitionMeta(250)['title']);
        $this->assertSame('ECOSYSTEM BUILDER', $generator->getRecognitionMeta(500)['title']);
        $this->assertSame('IMPACT ARCHITECT', $generator->getRecognitionMeta(1000)['title']);
        $this->assertSame('LEGACY MAKER', $generator->getRecognitionMeta(2500)['title']);
        $this->assertSame('TORCHBEARER', $generator->getRecognitionMeta(5000)['title']);
        $this->assertSame('WORLD CHANGER', $generator->getRecognitionMeta(10000)['title']);
        $this->assertSame('HUMANITARIAN', $generator->getRecognitionMeta(25000)['title']);
        $this->assertSame('HISTORY MAKER', $generator->getRecognitionMeta(50000)['title']);
        $this->assertSame('PEERS GLOBAL LEGEND', $generator->getRecognitionMeta(100000)['title']);
        $this->assertSame('PEERS GLOBAL LEGEND', $generator->getRecognitionMeta(500000)['title']);
    }

    public function test_caption_formatting_matches_exact_template(): void
    {
        /** @var LifeImpactCreativeGenerator $generator */
        $generator = app(LifeImpactCreativeGenerator::class);

        $user = new User([
            'id' => (string) Str::uuid(),
            'first_name' => 'Chirag',
            'last_name' => 'Mali',
            'display_name' => 'Chirag Mali',
            'email' => 'chirag@example.com',
            'life_impacted_count' => 100,
        ]);

        $caption = $generator->formatCaption($user, 100);

        $this->assertStringContainsString('🎉 **BIG CONGRATULATIONS!**', $caption);
        $this->assertStringContainsString('Congratulations to **Chirag Mali** on becoming a **LIFE CHANGER** for impacting **100 lives**.', $caption);
        $this->assertStringContainsString('Your contribution is making a lasting difference and supporting our mission of impacting **1 Million Entrepreneurs.**', $caption);
        $this->assertStringContainsString('**1 Action = 1 Life Impacted.** 🌍', $caption);
        $this->assertStringContainsString('#PeersGlobal #LifeChanger #ImpactLife #1MillionEntrepreneurs', $caption);
    }

    public function test_creative_image_generation_produces_file(): void
    {
        /** @var LifeImpactCreativeGenerator $generator */
        $generator = app(LifeImpactCreativeGenerator::class);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Chirag',
            'last_name' => 'Mali',
            'display_name' => 'Chirag Mali',
            'company_name' => 'TaskMate AI',
            'city' => 'Ahmedabad',
            'email' => 'chirag_test_'.Str::random(4).'@example.com',
            'life_impacted_count' => 25,
        ]);

        $fileModel = $generator->generate($user, 25);

        $this->assertNotNull($fileModel);
        $this->assertNotEmpty($fileModel->id);
    }

    public function test_admin_life_impact_recognitions_index_loads(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.life-impact-recognitions.index'));
        $response->assertStatus(200);
        $response->assertSee('Life Impact Recognitions');
    }

    public function test_admin_creative_preview_endpoint_returns_json_and_progression(): void
    {
        $admin = $this->createAdmin();

        $peer = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Peer',
            'display_name' => 'Test Peer',
            'email' => 'peer_'.Str::random(4).'@test.com',
            'life_impacted_count' => 50,
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->getJson(route('admin.life-impact-recognitions.creative-preview', ['id' => $peer->id]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'meta' => ['title', 'required_count', 'hashtag'],
            'caption',
            'preview_url',
            'peer' => ['id', 'name', 'life_impacted_count'],
            'peer_progression',
            'timeline_status',
        ]);
    }

    public function test_admin_post_creative_to_timeline(): void
    {
        $admin = $this->createAdmin();

        $peer = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Peer',
            'display_name' => 'Test Peer',
            'email' => 'peer_'.Str::random(4).'@test.com',
            'life_impacted_count' => 100,
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.life-impact-recognitions.post-creative', ['id' => $peer->id]), [
                'threshold' => 100,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('posts', [
            'source_type' => 'life_impact',
            'source_id' => $peer->id,
            'post_type' => 'life_impact_recognition',
        ]);
    }
}
