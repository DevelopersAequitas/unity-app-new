<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\PublishCelebrationPostJob;
use App\Models\CelebrationPublishLog;
use App\Models\CelebrationSchedule;
use App\Models\FileModel;
use App\Models\Notifications\AppNotification;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\Post;
use App\Models\PostMention;
use App\Models\User;
use App\Services\Celebrations\CelebrationCreativeService;
use App\Services\Celebrations\CelebrationDateResolver;
use App\Services\Celebrations\CelebrationPublishingService;
use App\Services\Celebrations\CelebrationScheduleSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class AutomatedCelebrationSystemTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Use an isolated in-memory SQLite database so tests are 100% self-contained and never touch live/prod DBs
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        // Register PostgreSQL-compatible helper functions for SQLite if needed
        $pdo = DB::connection('sqlite')->getPdo();
        $pdo->sqliteCreateFunction('ILIKE', fn ($a, $b) => stripos((string) $a, trim((string) $b, '%')) !== false, 2);

        $this->createMinimalCoreSchema();

        // Execute our REAL migration file!
        $migration = require database_path('migrations/2026_11_19_120000_create_celebration_schedules_and_logs_tables.php');
        $migration->up();

        Storage::fake('public');

        // Create system user (PeersGlobal Unity)
        User::withoutEvents(function (): void {
            User::create([
                'id' => (string) Str::uuid(),
                'first_name' => 'PeersGlobal',
                'last_name' => 'Unity',
                'display_name' => 'PeersGlobal Unity',
                'email' => 'info@peersglobal.com',
                'password_hash' => bcrypt('system-secret'),
                'status' => 'active',
                'membership_status' => 'active',
            ]);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createMinimalCoreSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('peer_id')->nullable();
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            $table->string('display_name', 160)->nullable();
            $table->string('email', 255)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('password_hash')->nullable();
            $table->string('company_name', 255)->nullable();
            $table->string('designation', 255)->nullable();
            $table->string('gender', 50)->nullable();
            $table->date('dob')->nullable();
            $table->date('anniversary_date')->nullable();
            $table->string('city', 255)->nullable();
            $table->uuid('city_id')->nullable();
            $table->string('state', 255)->nullable();
            $table->string('country', 255)->nullable();
            $table->string('timezone', 100)->nullable()->default('Asia/Kolkata');
            $table->string('status', 50)->default('active');
            $table->boolean('is_active')->default(true);
            $table->string('membership_status', 50)->default('Global Peer');
            $table->string('profile_photo_url')->nullable();
            $table->uuid('profile_photo_file_id')->nullable();
            $table->uuid('active_circle_id')->nullable();
            $table->integer('coins_balance')->default(0);
            $table->integer('life_impacted_count')->default(0);
            $table->integer('members_introduced_count')->default(0);
            $table->boolean('is_sponsored_member')->default(false);
            $table->timestamp('gdpr_deleted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uploader_user_id')->nullable();
            $table->string('s3_key', 500);
            $table->string('mime_type', 100)->nullable();
            $table->bigInteger('size_bytes')->default(0);
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->timestamps();
        });

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
            $table->boolean('active')->default(true);
            $table->string('status', 50)->default('active');
            $table->string('source_type', 64)->nullable();
            $table->uuid('source_id')->nullable();
            $table->string('source_event', 64)->nullable();
            $table->string('post_type', 64)->default('standard');
            $table->uuid('template_id')->nullable();
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
            $table->text('image')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('post_mentions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('post_id');
            $table->uuid('peer_id');
            $table->timestamps();
        });

        Schema::create('app_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('campaign_id')->nullable();
            $table->string('type', 100);
            $table->string('category', 100)->nullable();
            $table->string('title', 255);
            $table->text('body');
            $table->string('channel', 50)->default('in_app');
            $table->string('priority', 30)->default('medium');
            $table->string('reference_type', 100)->nullable();
            $table->string('reference_id', 100)->nullable();
            $table->string('screen', 100)->nullable();
            $table->json('data')->nullable();
            $table->string('image_url')->nullable();
            $table->string('dedupe_key', 255)->nullable();
            $table->string('status', 50)->default('pending');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->boolean('push_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('chat_enabled')->default(true);
            $table->boolean('event_enabled')->default(true);
            $table->boolean('circle_enabled')->default(true);
            $table->boolean('business_enabled')->default(true);
            $table->boolean('campaign_enabled')->default(true);
            $table->string('quiet_hours_start')->nullable();
            $table->string('quiet_hours_end')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_delivery_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('notification_id')->nullable();
            $table->uuid('campaign_id')->nullable();
            $table->uuid('user_id');
            $table->string('channel', 50);
            $table->string('provider', 50)->nullable();
            $table->string('status', 50);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_suppression_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('campaign_id')->nullable();
            $table->string('type', 100);
            $table->string('dedupe_key', 255);
            $table->integer('send_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 100)->unique();
            $table->string('name', 150);
            $table->timestamps();
        });

        Schema::create('admin_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('admin_user_roles', function (Blueprint $table): void {
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->timestamps();
        });

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id');
            $table->uuid('user_id');
            $table->string('role')->default('member');
            $table->string('status')->default('approved');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circle_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('admin_user_id')->nullable();
            $table->string('action')->nullable();
            $table->string('target_table')->nullable();
            $table->string('target_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('actor_user_id')->nullable();
            $table->string('action')->nullable();
            $table->string('entity_type')->nullable();
            $table->string('entity_id')->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->string('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    private function createPeer(array $overrides = []): User
    {
        if (isset($overrides['display_name']) && ! isset($overrides['first_name'])) {
            $parts = preg_split('/\s+/', trim((string) $overrides['display_name'])) ?: [];
            $overrides['first_name'] = $parts[0] ?? 'Peer';
            $overrides['last_name'] = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
        }

        return User::create(array_merge([
            'id' => (string) Str::uuid(),
            'first_name' => 'Rohan',
            'last_name' => 'Mehta',
            'display_name' => 'Rohan Mehta',
            'email' => 'peer-'.Str::random(8).'@example.com',
            'phone' => '98'.random_int(10000000, 99999999),
            'password_hash' => bcrypt('password'),
            'company_name' => 'Unity Tech Pvt Ltd',
            'designation' => 'Founder',
            'city' => 'Ahmedabad',
            'status' => 'active',
            'is_active' => true,
            'membership_status' => 'Global Peer',
        ], $overrides));
    }

    /**
     * Scenario 1 & Scenario 10:
     * Existing peer with a birthday is detected, published at 7:00 AM IST (not at 6:59 AM IST),
     * generates approved creative, publishes to actual Timeline (visible to other members),
     * and sends in-app notification to the celebrating member.
     */
    public function test_scenario_1_and_10_existing_peer_birthday_publishes_at_7am_ist_and_notifies_member(): void
    {
        // Create existing peer WITHOUT triggering model events (simulating pre-existing DB peer)
        $peer = User::withoutEvents(fn () => $this->createPeer([
            'first_name' => 'Aarav',
            'last_name' => 'Patel',
            'display_name' => 'Aarav Patel',
            'dob' => '1996-11-19',
        ]));

        // Before 7:00 AM IST (06:59 AM IST): scheduler syncs peer but MUST NOT publish yet!
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 6, 59, 0, 'Asia/Kolkata'));
        $this->artisan('celebrations:publish-due --sync')->assertExitCode(0);

        $this->assertDatabaseHas('celebration_schedules', [
            'user_id' => $peer->id,
            'event_type' => 'birthday',
            'event_year' => 2026,
            'status' => 'pending',
        ]);
        $this->assertEquals(0, Post::where('source_id', $peer->id)->where('post_type', 'birthday')->count());

        // At EXACTLY 7:00 AM IST: publishes automatically without anyone logging into admin panel!
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 7, 0, 0, 'Asia/Kolkata'));
        $this->artisan('celebrations:publish-due --sync')->assertExitCode(0);

        $schedule = CelebrationSchedule::where('user_id', $peer->id)
            ->where('event_type', 'birthday')
            ->where('event_year', 2026)
            ->firstOrFail();

        $this->assertEquals('published', $schedule->status);
        $this->assertNotNull($schedule->post_id);
        $this->assertNotNull($schedule->creative_file_id);
        $this->assertNotNull($schedule->notification_id);
        $this->assertEquals(30, $schedule->milestone_years);

        // Verify Timeline post exists, is public, active, approved, and contains member name + age
        $post = Post::findOrFail($schedule->post_id);
        $this->assertEquals('birthday', $post->post_type);
        $this->assertEquals('public', $post->visibility);
        $this->assertEquals('active', $post->status);
        $this->assertTrue($post->active);
        $this->assertEquals('approved', $post->moderation_status);
        $this->assertStringContainsString('Aarav Patel', $post->content_text);
        $this->assertStringContainsString('30', $post->content_text);
        $this->assertNotEmpty($post->media);

        // Verify PostMention exists so post links back to the peer
        $this->assertTrue(PostMention::where('post_id', $post->id)->where('peer_id', $peer->id)->exists());

        // Verify generated creative image is stored on disk & in files table
        $file = FileModel::findOrFail($schedule->creative_file_id);
        $this->assertTrue(Storage::disk('public')->exists($file->s3_key));
        $this->assertGreaterThan(1000, $file->size_bytes);

        // Verify Scenario 10: Member receives in-app notification + delivery log
        $notification = AppNotification::findOrFail($schedule->notification_id);
        $this->assertEquals($peer->id, $notification->user_id);
        $this->assertEquals('birthday_anniversary', $notification->type);
        $this->assertEquals('post', $notification->reference_type);
        $this->assertEquals($post->id, $notification->reference_id);
        $this->assertStringContainsString('Happy Birthday, Aarav Patel', $notification->title);
        $this->assertTrue(
            NotificationDeliveryLog::where('notification_id', $notification->id)
                ->where('channel', 'in_app')
                ->where('status', 'sent')
                ->exists()
        );

        // Verify Timeline visibility query (public active non-deleted post is visible to other eligible members)
        $visibleToOtherMember = Post::query()
            ->where('visibility', 'public')
            ->where('status', 'active')
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->whereKey($post->id)
            ->exists();
        $this->assertTrue($visibleToOtherMember);
    }

    /**
     * Scenario 2: Newly registered peer with a birthday is automatically enrolled in birthday tracking.
     */
    public function test_scenario_2_newly_registered_peer_automatically_tracked_and_published(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 6, 30, 0, 'Asia/Kolkata'));

        // New peer registers with DOB today
        $newPeer = $this->createPeer([
            'first_name' => 'Kavya',
            'last_name' => 'Shah',
            'display_name' => 'Kavya Shah',
            'dob' => '1999-11-19',
        ]);

        // Automatically enrolled via User::saved model hook (zero manual action!)
        $schedule = CelebrationSchedule::where('user_id', $newPeer->id)
            ->where('event_type', 'birthday')
            ->where('event_year', 2026)
            ->first();

        $this->assertNotNull($schedule);
        $this->assertEquals('pending', $schedule->status);
        $this->assertEquals('2026-11-19', $schedule->scheduled_date->format('Y-m-d'));
        $this->assertEquals(27, $schedule->milestone_years);

        // At 7:00 AM IST, scheduler publishes automatically
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 7, 0, 0, 'Asia/Kolkata'));
        $this->artisan('celebrations:publish-due --sync')->assertExitCode(0);

        $this->assertEquals('published', $schedule->fresh()->status);
        $this->assertEquals(1, Post::where('source_id', $newPeer->id)->where('post_type', 'birthday')->count());
    }

    /**
     * Scenario 3: Existing peer changes their birthday -> old pending schedule cancelled/replaced, new schedule created.
     */
    public function test_scenario_3_peer_changes_birthday_cancels_old_and_creates_new_schedule(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 10, 10, 0, 0, 'Asia/Kolkata'));

        $peer = $this->createPeer([
            'display_name' => 'Vikram Joshi',
            'dob' => '1992-11-20',
        ]);

        $schedule = CelebrationSchedule::where('user_id', $peer->id)
            ->where('event_type', 'birthday')
            ->firstOrFail();
        $this->assertEquals('2026-11-20', $schedule->scheduled_date->format('Y-m-d'));

        // Peer changes DOB to 1992-11-25
        $peer->update(['dob' => '1992-11-25']);

        $updated = CelebrationSchedule::where('user_id', $peer->id)
            ->where('event_type', 'birthday')
            ->where('event_year', 2026)
            ->firstOrFail();
        $this->assertEquals('2026-11-25', $updated->scheduled_date->format('Y-m-d'));
        $this->assertEquals('pending', $updated->status);

        // Verify log recorded cancellation of old date + rescheduling to new date
        $this->assertTrue(CelebrationPublishLog::where('user_id', $peer->id)->where('action', 'cancelled')->exists());
        $this->assertTrue(CelebrationPublishLog::where('user_id', $peer->id)->where('action', 'rescheduled')->exists());

        // On old date (Nov 20 at 7:00 AM IST): MUST NOT publish
        Carbon::setTestNow(Carbon::create(2026, 11, 20, 7, 0, 0, 'Asia/Kolkata'));
        $this->artisan('celebrations:publish-due --sync')->assertExitCode(0);
        $this->assertEquals(0, Post::where('source_id', $peer->id)->count());

        // On new date (Nov 25 at 7:00 AM IST): MUST publish
        Carbon::setTestNow(Carbon::create(2026, 11, 25, 7, 0, 0, 'Asia/Kolkata'));
        $this->artisan('celebrations:publish-due --sync')->assertExitCode(0);
        $this->assertEquals(1, Post::where('source_id', $peer->id)->where('post_type', 'birthday')->count());
    }

    /**
     * Scenario 4: Admin updates a peer's birthday & anniversary via NEW Admin Panel, plus deactivation & deletion handling.
     */
    public function test_scenario_4_admin_updates_peer_birthday_and_handles_deactivation_and_deletion(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 15, 10, 0, 0, 'Asia/Kolkata'));

        $admin = $this->createPeer(['email' => 'admin@peersglobal.com', 'display_name' => 'Super Admin']);
        $role = \App\Models\Role::create(['id' => (string) Str::uuid(), 'key' => 'global_admin', 'name' => 'Global Admin']);
        DB::table('admin_user_roles')->insert(['user_id' => $admin->id, 'role_id' => $role->id]);
        Sanctum::actingAs($admin);

        $peer = $this->createPeer([
            'display_name' => 'Neha Desai',
            'dob' => '1994-11-28',
        ]);

        // Admin updates DOB & adds wedding anniversary date through NEW Admin Panel API
        $res = $this->putJson("/api/v1/admin/peers/{$peer->id}", [
            'dob' => '1994-12-02',
            'anniversary_date' => '2020-12-05',
        ]);
        $res->assertOk();

        $this->assertDatabaseHas('celebration_schedules', [
            'user_id' => $peer->id,
            'event_type' => 'birthday',
            'scheduled_date' => '2026-12-02 00:00:00',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('celebration_schedules', [
            'user_id' => $peer->id,
            'event_type' => 'anniversary',
            'scheduled_date' => '2026-12-05 00:00:00',
            'status' => 'pending',
        ]);

        // Admin deactivates peer -> all pending schedules automatically cancelled!
        $this->patchJson("/api/v1/admin/peers/{$peer->id}/status", ['is_active' => false])->assertOk();
        $this->assertEquals(
            0,
            CelebrationSchedule::where('user_id', $peer->id)->where('status', 'pending')->count()
        );
        $this->assertEquals(
            2,
            CelebrationSchedule::where('user_id', $peer->id)->where('status', 'cancelled')->count()
        );

        // Admin re-activates peer -> schedules automatically restored to pending!
        $this->patchJson("/api/v1/admin/peers/{$peer->id}/status", ['is_active' => true])->assertOk();
        $this->assertEquals(
            2,
            CelebrationSchedule::where('user_id', $peer->id)->where('status', 'pending')->count()
        );

        // Admin deletes peer -> schedules cancelled permanently!
        $this->deleteJson("/api/v1/admin/peers/{$peer->id}")->assertOk();
        $this->assertEquals(
            0,
            CelebrationSchedule::where('user_id', $peer->id)->where('status', 'pending')->count()
        );
    }

    /**
     * Scenario 5: Peer has no profile photo -> creative renders initials inside circular frame cleanly.
     */
    public function test_scenario_5_peer_without_profile_photo_renders_initials_on_approved_creative(): void
    {
        $peer = $this->createPeer([
            'first_name' => 'Priya',
            'last_name' => 'Kapoor',
            'display_name' => 'Priya Kapoor',
            'dob' => '1995-11-19',
            'profile_photo_url' => null,
            'profile_photo_file_id' => null,
        ]);

        $creativeService = app(CelebrationCreativeService::class);
        $this->assertEquals('PK', $creativeService->resolveInitials($peer));

        $result = $creativeService->generate($peer, 'birthday');

        $this->assertTrue($result['used_initials']);
        $this->assertEquals('approved_birthday_template', $result['template_used']);
        $this->assertInstanceOf(FileModel::class, $result['file']);
        $this->assertTrue(Storage::disk('public')->exists($result['file']->s3_key));
        $this->assertGreaterThan(5000, $result['file']->size_bytes);
    }

    /**
     * Scenario 6: Birthday occurs on February 29 (leap year vs non-leap year handling).
     */
    public function test_scenario_6_february_29_birthday_handles_non_leap_and_leap_years(): void
    {
        $resolver = app(CelebrationDateResolver::class);
        $feb29Dob = Carbon::parse('1996-02-29');

        // Non-leap year 2026 -> resolves to 2026-02-28 at 07:00 AM IST
        $nonLeap = $resolver->resolveNextOccurrence($feb29Dob, Carbon::create(2026, 2, 1, 9, 0, 0, 'Asia/Kolkata'));
        $this->assertEquals('2026-02-28', $nonLeap['scheduled_date']->format('Y-m-d'));
        $this->assertTrue($nonLeap['is_feb29_adjusted']);
        $this->assertEquals(30, $nonLeap['milestone_years']);

        // Leap year 2028 -> resolves to 2028-02-29 at 07:00 AM IST
        $leap = $resolver->resolveNextOccurrence($feb29Dob, Carbon::create(2028, 2, 1, 9, 0, 0, 'Asia/Kolkata'));
        $this->assertEquals('2028-02-29', $leap['scheduled_date']->format('Y-m-d'));
        $this->assertFalse($leap['is_feb29_adjusted']);
        $this->assertEquals(32, $leap['milestone_years']);

        // End-to-end publish on Feb 28 in non-leap year 2026 at 7:00 AM IST
        Carbon::setTestNow(Carbon::create(2026, 2, 28, 7, 0, 0, 'Asia/Kolkata'));
        $leapPeer = $this->createPeer([
            'display_name' => 'Leap Year Peer',
            'dob' => '1996-02-29',
        ]);

        $this->artisan('celebrations:publish-due --sync')->assertExitCode(0);

        $this->assertDatabaseHas('celebration_schedules', [
            'user_id' => $leapPeer->id,
            'event_type' => 'birthday',
            'event_year' => 2026,
            'status' => 'published',
        ]);
        $this->assertEquals(1, Post::where('source_id', $leapPeer->id)->where('post_type', 'birthday')->count());
    }

    /**
     * Scenario 7 & Scenario 8:
     * Multiple peers have birthdays on the same date AND scheduler runs more than once ->
     * every peer gets exactly ONE Timeline post and ONE notification per year.
     */
    public function test_scenario_7_and_8_multiple_peers_same_date_and_idempotent_repeated_scheduler_runs(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 7, 0, 0, 'Asia/Kolkata'));

        $peer1 = $this->createPeer(['display_name' => 'Peer One', 'dob' => '1990-11-19']);
        $peer2 = $this->createPeer(['display_name' => 'Peer Two', 'dob' => '1995-11-19']);
        $peer3 = $this->createPeer(['display_name' => 'Peer Three', 'dob' => '2000-11-19']);

        // Run scheduler 3 times (simulating 07:00, 07:15, 07:30 runs)
        $this->artisan('celebrations:publish-due --sync')->assertExitCode(0);
        $this->artisan('celebrations:publish-due --sync')->assertExitCode(0);
        $this->artisan('celebrations:publish-due --sync')->assertExitCode(0);

        foreach ([$peer1, $peer2, $peer3] as $peer) {
            $this->assertEquals(
                1,
                Post::where('source_id', $peer->id)->where('post_type', 'birthday')->count(),
                "Expected exactly 1 birthday post for {$peer->display_name}"
            );
            $this->assertEquals(
                1,
                AppNotification::where('user_id', $peer->id)->where('type', 'birthday_anniversary')->count(),
                "Expected exactly 1 notification for {$peer->display_name}"
            );
        }
    }

    /**
     * Scenario 9: Timeline publishing fails transiently, logs failure, and succeeds on retry without duplicates.
     */
    public function test_scenario_9_timeline_publishing_failure_and_retry_succeeds(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 7, 0, 0, 'Asia/Kolkata'));

        $peer = $this->createPeer([
            'display_name' => 'Retry Test Peer',
            'dob' => '1993-11-19',
        ]);

        $schedule = CelebrationSchedule::where('user_id', $peer->id)->where('event_type', 'birthday')->firstOrFail();

        // Simulate transient failure on attempt 1 (e.g., temporary creative/storage outage)
        $failingCreativeMock = $this->createMock(CelebrationCreativeService::class);
        $failingCreativeMock->method('generate')->willThrowException(new RuntimeException('Simulated transient storage error'));
        $failingCreativeMock->method('resolvePeerDisplayName')->willReturn('Retry Test Peer');

        $failingService = new CelebrationPublishingService(
            $failingCreativeMock,
            app(CelebrationScheduleSyncService::class),
            app(CelebrationDateResolver::class),
            app(\App\Services\Notifications\NotificationService::class)
        );

        $failedAsExpected = false;
        try {
            $failingService->publishSchedule($schedule);
        } catch (RuntimeException $e) {
            $failedAsExpected = true;
            $this->assertStringContainsString('Simulated transient storage error', $e->getMessage());
        }
        $this->assertTrue($failedAsExpected);

        $schedule->refresh();
        $this->assertEquals('failed', $schedule->status);
        $this->assertEquals(1, $schedule->attempts);
        $this->assertStringContainsString('Simulated transient storage error', (string) $schedule->last_error);
        $this->assertTrue(CelebrationPublishLog::where('celebration_schedule_id', $schedule->id)->where('action', 'publish_failed')->exists());

        // Verify job retry policy configuration
        $job = new PublishCelebrationPostJob((string) $schedule->id);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals([60, 300, 900], $job->backoff);

        // Retry pass (07:15 AM IST): real service succeeds on attempt 2!
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 7, 15, 0, 'Asia/Kolkata'));
        $this->artisan('celebrations:publish-due --sync')->assertExitCode(0);

        $schedule->refresh();
        $this->assertEquals('published', $schedule->status);
        $this->assertEquals(2, $schedule->attempts);
        $this->assertNull($schedule->last_error);
        $this->assertNotNull($schedule->post_id);
        $this->assertNotNull($schedule->notification_id);
        $this->assertEquals(1, Post::where('source_id', $peer->id)->where('post_type', 'birthday')->count());
    }

    /**
     * Requirement 5 & NEW Admin Panel API:
     * Wedding Anniversary uses a SEPARATE creative (never the birthday template), publishes at 7:00 AM IST,
     * sends anniversary notification, prevents duplicates, and works cleanly via NEW Admin Panel APIs.
     */
    public function test_anniversary_automation_and_new_admin_panel_celebrations_api(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 7, 0, 0, 'Asia/Kolkata'));

        $admin = $this->createPeer(['email' => 'admin-api@peersglobal.com', 'display_name' => 'Admin User']);
        Sanctum::actingAs($admin);

        $annivPeer = $this->createPeer([
            'display_name' => 'Siddharth Verma',
            'dob' => '1988-05-10',
            'anniversary_date' => '2016-11-19',
        ]);

        // 1. Verify NEW Admin Panel GET /api/v1/admin/celebrations/upcoming returns real DB peer
        $upcomingRes = $this->getJson('/api/v1/admin/celebrations/upcoming?period=30');
        $upcomingRes->assertOk();
        $upcomingRes->assertJsonPath('success', true);
        $items = $upcomingRes->json('data.items');
        $annivItems = array_values(array_filter($items, fn ($i) => $i['peer_id'] === $annivPeer->id && $i['event_type'] === 'anniversary'));
        $this->assertCount(1, $annivItems);
        $this->assertEquals('10 Years', $annivItems[0]['milestone_text']);
        $this->assertEquals('07:00 AM IST', $annivItems[0]['scheduled_time_ist']);

        // 2. Upload a separate custom Anniversary Creative Template via NEW Admin Panel API
        $templateFile = UploadedFile::fake()->image('official_anniversary.jpg', 952, 1194);
        $uploadRes = $this->post('/api/v1/admin/celebrations/templates/anniversary', [
            'image' => $templateFile,
        ]);
        $uploadRes->assertOk();

        // 3. Publish anniversary via 7:00 AM IST scheduler
        $this->artisan('celebrations:publish-due --type=anniversary --sync')->assertExitCode(0);

        $schedule = CelebrationSchedule::where('user_id', $annivPeer->id)
            ->where('event_type', 'anniversary')
            ->where('event_year', 2026)
            ->firstOrFail();

        $this->assertEquals('published', $schedule->status);
        $this->assertStringStartsWith('custom_anniversary_template:', (string) ($schedule->meta['template_used'] ?? ''));
        $this->assertNotEquals('approved_birthday_template', (string) ($schedule->meta['template_used'] ?? ''));

        $post = Post::findOrFail($schedule->post_id);
        $this->assertEquals('anniversary', $post->post_type);
        $this->assertStringContainsString('Happy Wedding Anniversary, Siddharth Verma', $post->content_text);
        $this->assertStringContainsString('10 wonderful years', $post->content_text);

        // 4. Verify duplicate prevention via NEW Admin Panel POST /api/v1/admin/celebrations/publish
        $dupRes = $this->postJson('/api/v1/admin/celebrations/publish', [
            'user_id' => $annivPeer->id,
            'event_type' => 'anniversary',
        ]);
        $dupRes->assertOk();
        $dupRes->assertJsonPath('data.duplicate', true);
        $this->assertEquals(1, Post::where('source_id', $annivPeer->id)->where('post_type', 'anniversary')->count());

        // 5. Verify Publishing Logs endpoint returns audit trail
        $logsRes = $this->getJson('/api/v1/admin/celebrations/logs');
        $logsRes->assertOk();
        $this->assertNotEmpty($logsRes->json('data.items'));
    }
}
