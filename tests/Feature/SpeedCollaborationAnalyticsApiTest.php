<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\CoinsLedger;
use App\Models\EventRegistration;
use App\Models\LifeImpactHistory;
use App\Models\P2pMeeting;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\SpeedNetworking\SpeedNetworkingEventSkip;
use App\Models\SpeedNetworking\SpeedNetworkingMatch;
use App\Models\SpeedNetworking\SpeedNetworkingParticipant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

class SpeedCollaborationAnalyticsApiTest extends TestCase
{
    private const TEST_SCHEMA = 'test_sn_analytics';

    protected AdminUser $adminUser;

    protected User $regularUser;

    protected User $peerA;

    protected User $peerB;

    protected User $peerC;

    protected User $peerD;

    protected User $peerE;

    protected SpeedNetworkingEvent $eventOne;

    protected SpeedNetworkingEvent $eventTwo;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('DROP SCHEMA IF EXISTS '.self::TEST_SCHEMA.' CASCADE');
        DB::statement('CREATE SCHEMA '.self::TEST_SCHEMA);
        DB::statement('SET search_path TO '.self::TEST_SCHEMA);
        config(['database.connections.pgsql.search_path' => self::TEST_SCHEMA]);

        $this->createMinimalSchema();

        $this->adminUser = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Super Admin',
            'email' => 'admin@peersglobal.test',
            'role' => 'super_admin',
        ]);

        $this->regularUser = $this->createPeer('Regular Member', 'regular@peersglobal.test', 'Regular Co', 50000, 99);
        $this->peerA = $this->createPeer('Rahul Patel', 'rahul@peersglobal.test', 'TechNova Pvt Ltd', 99000, 40);
        $this->peerB = $this->createPeer('Hardik Parmar', 'hardik@peersglobal.test', 'Peers Global', 88000, 35);
        $this->peerC = $this->createPeer('Chirag Mali', 'chirag@peersglobal.test', 'BuildFast LLP', 12000, 10);
        $this->peerD = $this->createPeer('Urvashi Chavda', 'urvashi@peersglobal.test', 'Aequitas Info', 4000, 5);
        $this->peerE = $this->createPeer('No Show Peer', 'noshow@peersglobal.test', 'Absent Co', 1000, 2);

        $this->seedEventsData();
    }

    protected function tearDown(): void
    {
        try {
            config(['database.connections.pgsql.search_path' => 'public']);
            DB::statement('SET search_path TO public');
            DB::statement('DROP SCHEMA IF EXISTS '.self::TEST_SCHEMA.' CASCADE');
        } finally {
            parent::tearDown();
        }
    }

    public function test_authorization_requires_admin_access(): void
    {
        $this->getJson('/api/v1/admin/speed-networking/events-analytics')
            ->assertStatus(401);

        Sanctum::actingAs($this->regularUser);
        $this->getJson('/api/v1/admin/speed-networking/events-analytics')
            ->assertStatus(403);

        Sanctum::actingAs($this->adminUser);
        $this->getJson('/api/v1/admin/speed-networking/events-analytics')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_event_overview_unique_peers_and_registration_vs_attendance(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Event One registrations: PeerA, PeerB, PeerC, PeerD, PeerE active; regularUser canceled -> 5 registered
        // Attended: PeerA, PeerB, PeerC, PeerD attended -> 4 attended, 1 not attended -> 80.0%
        // Valid connections: 3 repeat sessions A<->B + 1 active A<->C = 4 valid, plus 1 skipped, 1 break
        $res = $this->getJson("/api/v1/admin/speed-networking/events/{$this->eventOne->id}/analytics/overview");

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event.id', (string) $this->eventOne->id)
            ->assertJsonPath('data.event.display_status', 'live')
            ->assertJsonPath('data.kpis.registered_peers', 5)
            ->assertJsonPath('data.kpis.attended_peers', 4)
            ->assertJsonPath('data.kpis.not_attended_peers', 1)
            ->assertJsonPath('data.kpis.canceled_registrations', 1)
            ->assertJsonPath('data.kpis.attendance_percentage', 80)
            ->assertJsonPath('data.kpis.total_connections_made', 4)
            ->assertJsonPath('data.kpis.completed_meetings', 3)
            ->assertJsonPath('data.kpis.unique_pairs_connected', 2)
            ->assertJsonPath('data.kpis.unique_peers_connected', 3)
            ->assertJsonPath('data.kpis.total_coins_earned', 6000)
            ->assertJsonPath('data.kpis.total_life_impact_generated', 2);
    }

    public function test_rewards_analytics_attributes_only_event_transactions_and_ignores_balances(): void
    {
        Sanctum::actingAs($this->adminUser);

        $res = $this->getJson("/api/v1/admin/speed-networking/events/{$this->eventOne->id}/analytics/rewards");

        $res->assertStatus(200)
            ->assertJsonPath('data.coins.summary.total_coins_generated', 6000)
            ->assertJsonPath('data.coins.summary.peers_rewarded_count', 2)
            ->assertJsonPath('data.coins.summary.total_transactions', 2)
            ->assertJsonPath('data.life_impact.summary.total_impact_generated', 2)
            ->assertJsonPath('data.life_impact.summary.peers_generating_impact_count', 2)
            ->assertJsonPath('data.life_impact.summary.total_impact_activities', 2);

        // Verify PeerA's wallet balance (99000) & lifetime impact (40) are NOT mistaken for event coins (3000) / impact (1)
        $coinPerPeer = collect($res->json('data.coins.per_peer'));
        $peerACoins = $coinPerPeer->firstWhere('user_id', (string) $this->peerA->id);
        $this->assertSame(3000, $peerACoins['coins_earned_during_event']);
        $this->assertSame(99000, $peerACoins['current_balance_informational']);

        $impactPerPeer = collect($res->json('data.life_impact.per_peer'));
        $peerAImpact = $impactPerPeer->firstWhere('user_id', (string) $this->peerA->id);
        $this->assertSame(1, $peerAImpact['life_impact_earned_during_event']);
        $this->assertSame(40, $peerAImpact['current_total_impact_informational']);
    }

    public function test_live_monitor_attendees_connections_and_individual_peer_analytics(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Live monitor with category=skip
        $liveRes = $this->getJson("/api/v1/admin/speed-networking/events/{$this->eventOne->id}/analytics/live-monitor?category=skip");
        $liveRes->assertStatus(200)
            ->assertJsonPath('data.kpis.active_connection_sessions', 1)
            ->assertJsonPath('data.kpis.waiting_peers_count', 1)
            ->assertJsonPath('data.kpis.skips_count', 2)
            ->assertJsonPath('data.skips.0.user.name', 'Chirag Mali')
            ->assertJsonPath('data.skips.0.skipped_peer.name', 'Urvashi Chavda');

        $this->assertCount(1, $liveRes->json('data.active_connections'));
        $this->assertSame('Hardik Parmar', $liveRes->json('data.waiting_participants.0.name'));

        // Attendees search
        $attRes = $this->getJson("/api/v1/admin/speed-networking/events/{$this->eventOne->id}/analytics/attendees?search=Hardik&status=attended");
        $attRes->assertStatus(200);
        $this->assertCount(1, $attRes->json('data.data'));
        $this->assertSame(3, $attRes->json('data.data.0.connections_made'));
        $this->assertSame(1, $attRes->json('data.data.0.unique_peers_met'));
        $this->assertSame(2, $attRes->json('data.data.0.repeated_connections'));

        // Individual peer analytics: PeerA has 3 completed sessions with PeerB + 1 active session with PeerC = 4 connections, 2 unique peers
        $peerRes = $this->getJson("/api/v1/admin/speed-networking/events/{$this->eventOne->id}/analytics/peers/{$this->peerA->id}");
        $peerRes->assertStatus(200)
            ->assertJsonPath('data.peer.connections_made', 4)
            ->assertJsonPath('data.peer.unique_peers_met', 2)
            ->assertJsonPath('data.peer.repeated_connections', 2)
            ->assertJsonPath('data.peer.coins_earned', 3000)
            ->assertJsonPath('data.peer.life_impact_earned', 1);

        // Who Met Whom + Live Meets Matrix
        $connRes = $this->getJson("/api/v1/admin/speed-networking/events/{$this->eventOne->id}/analytics/connections");
        $connRes->assertStatus(200)
            ->assertJsonPath('data.unique_analysis.unique_pairs_count', 2)
            ->assertJsonPath('data.unique_analysis.repeated_pairs_count', 1);
    }

    public function test_events_list_comparison_reports_and_exports(): void
    {
        Sanctum::actingAs($this->adminUser);

        $listRes = $this->getJson('/api/v1/admin/speed-networking/events-analytics');
        $listRes->assertStatus(200)
            ->assertJsonPath('data.summary.total_events', 2);

        $events = collect($listRes->json('data.events'))->keyBy('id');
        $this->assertSame(6000, $events->get((string) $this->eventOne->id)['total_coins_earned']);
        $this->assertSame(0, $events->get((string) $this->eventTwo->id)['total_coins_earned']);

        // Comparison
        $cmpRes = $this->getJson("/api/v1/admin/speed-networking/events-comparison?event_ids[]={$this->eventOne->id}&event_ids[]={$this->eventTwo->id}");
        $cmpRes->assertStatus(200);
        $this->assertCount(2, $cmpRes->json('data.events'));

        // Reports
        $repRes = $this->getJson("/api/v1/admin/speed-networking/events/{$this->eventOne->id}/analytics/reports");
        $repRes->assertStatus(200)
            ->assertJsonPath('data.live_stats.introductions_made', 4)
            ->assertJsonCount(8, 'data.available_reports');

        // Excel Export (.xlsx)
        $xlsxRes = $this->get("/api/v1/admin/speed-networking/events/{$this->eventOne->id}/analytics/export/excel?report_type=full&search=Hardik");
        $xlsxRes->assertStatus(200);
        $xlsxBinary = $xlsxRes->getContent();
        $this->assertStringStartsWith('PK', (string) $xlsxBinary);

        $tmpXlsx = tempnam(sys_get_temp_dir(), 'test_xlsx_');
        file_put_contents($tmpXlsx, $xlsxBinary);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmpXlsx) === true);
        $workbookXml = (string) $zip->getFromName('xl/workbook.xml');
        $this->assertStringContainsString('Event Summary', $workbookXml);
        $this->assertStringContainsString('Attendees', $workbookXml);
        $this->assertStringContainsString('Connections', $workbookXml);
        $zip->close();
        @unlink($tmpXlsx);

        // PDF Export
        $pdfRes = $this->get("/api/v1/admin/speed-networking/events/{$this->eventOne->id}/analytics/export/pdf?report_type=full");
        $pdfRes->assertStatus(200);
        $this->assertStringStartsWith('%PDF', (string) $pdfRes->getContent());
    }

    protected function createPeer(string $name, string $email, string $company, int $coinsBalance, int $lifeImpact): User
    {
        $parts = explode(' ', $name, 2);

        return User::create([
            'id' => (string) Str::uuid(),
            'first_name' => $parts[0],
            'last_name' => $parts[1] ?? '',
            'display_name' => $name,
            'name' => $name,
            'email' => $email,
            'company_name' => $company,
            'coins_balance' => $coinsBalance,
            'life_impacted_count' => $lifeImpact,
        ]);
    }

    protected function seedEventsData(): void
    {
        $now = Carbon::now();

        $this->eventOne = SpeedNetworkingEvent::create([
            'id' => (string) Str::uuid(),
            'title' => 'Global Founders Speed Collab',
            'description' => 'Live cohort session',
            'status' => SpeedNetworkingEvent::STATUS_IN_PROGRESS,
            'round_duration_seconds' => 300,
            'break_duration_seconds' => 20,
            'max_skip_limit' => 2,
            'preview_duration_seconds' => 15,
            'total_rounds' => 5,
            'current_round' => 4,
            'scheduled_start_time' => $now->copy()->subMinutes(20),
            'actual_start_time' => $now->copy()->subMinutes(18),
        ]);

        $this->eventTwo = SpeedNetworkingEvent::create([
            'id' => (string) Str::uuid(),
            'title' => 'Past Tech Circle Speed Collab',
            'description' => 'Completed session',
            'status' => SpeedNetworkingEvent::STATUS_COMPLETED,
            'round_duration_seconds' => 300,
            'break_duration_seconds' => 20,
            'max_skip_limit' => 2,
            'preview_duration_seconds' => 15,
            'total_rounds' => 3,
            'current_round' => 3,
            'scheduled_start_time' => $now->copy()->subDays(3),
            'actual_start_time' => $now->copy()->subDays(3),
            'actual_end_time' => $now->copy()->subDays(3)->addMinutes(20),
        ]);

        // Registrations: 5 active + 1 canceled for Event One
        foreach ([$this->peerA, $this->peerB, $this->peerC, $this->peerD, $this->peerE] as $u) {
            EventRegistration::create([
                'id' => (string) Str::uuid(),
                'event_id' => $this->eventOne->id,
                'user_id' => $u->id,
                'status' => 'registered',
                'registered_at' => $now->copy()->subHours(2),
            ]);
        }
        EventRegistration::create([
            'id' => (string) Str::uuid(),
            'event_id' => $this->eventOne->id,
            'user_id' => $this->regularUser->id,
            'status' => 'cancelled',
            'registered_at' => $now->copy()->subHours(3),
        ]);

        // Participants: A (in_call), B (waiting), C (in_call), D never joined (Not Attended)
        SpeedNetworkingParticipant::create([
            'event_id' => $this->eventOne->id,
            'user_id' => $this->peerA->id,
            'status' => SpeedNetworkingParticipant::STATUS_IN_CALL,
            'rounds_completed' => 3,
            'joined_at' => $now->copy()->subMinutes(17),
            'last_heartbeat_at' => $now->copy()->subSeconds(5),
        ]);
        SpeedNetworkingParticipant::create([
            'event_id' => $this->eventOne->id,
            'user_id' => $this->peerB->id,
            'status' => SpeedNetworkingParticipant::STATUS_WAITING,
            'rounds_completed' => 3,
            'joined_at' => $now->copy()->subMinutes(17),
            'last_heartbeat_at' => $now->copy()->subSeconds(10),
        ]);
        SpeedNetworkingParticipant::create([
            'event_id' => $this->eventOne->id,
            'user_id' => $this->peerC->id,
            'status' => SpeedNetworkingParticipant::STATUS_IN_CALL,
            'rounds_completed' => 0,
            'joined_at' => $now->copy()->subMinutes(12),
            'last_heartbeat_at' => $now->copy()->subSeconds(6),
        ]);

        // 3 repeat completed sessions between PeerA and PeerB
        $firstCompletedMatch = null;
        for ($r = 1; $r <= 3; $r++) {
            $m = SpeedNetworkingMatch::create([
                'event_id' => $this->eventOne->id,
                'round_number' => $r,
                'table_number' => 1,
                'channel_id' => "ch_r{$r}",
                'user_a_id' => $this->peerA->id,
                'user_b_id' => $this->peerB->id,
                'status' => SpeedNetworkingMatch::STATUS_COMPLETED,
                'match_status' => SpeedNetworkingMatch::MATCH_STATUS_COMPLETED,
                'duration_seconds' => $r === 1 ? 180 : 90,
                'call_started_at' => $now->copy()->subMinutes(16 - ($r * 2)),
                'started_at' => $now->copy()->subMinutes(16 - ($r * 2)),
                'ended_at' => $now->copy()->subMinutes(15 - ($r * 2)),
            ]);
            if ($r === 1) {
                $firstCompletedMatch = $m;
            }
        }

        // 1 active call between PeerA and PeerC in Round 4
        SpeedNetworkingMatch::create([
            'event_id' => $this->eventOne->id,
            'round_number' => 4,
            'table_number' => 2,
            'channel_id' => 'ch_r4',
            'user_a_id' => $this->peerA->id,
            'user_b_id' => $this->peerC->id,
            'status' => SpeedNetworkingMatch::STATUS_ACTIVE,
            'match_status' => SpeedNetworkingMatch::MATCH_STATUS_IN_CALL,
            'user_a_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
            'user_b_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
            'call_started_at' => $now->copy()->subSeconds(75),
            'call_expires_at' => $now->copy()->addSeconds(225),
            'started_at' => $now->copy()->subSeconds(90),
        ]);

        // 1 skipped match + skip record
        SpeedNetworkingMatch::create([
            'event_id' => $this->eventOne->id,
            'round_number' => 2,
            'table_number' => 3,
            'channel_id' => 'ch_skip',
            'user_a_id' => $this->peerC->id,
            'user_b_id' => $this->peerD->id,
            'status' => SpeedNetworkingMatch::STATUS_SKIPPED,
            'match_status' => SpeedNetworkingMatch::MATCH_STATUS_SKIPPED,
            'started_at' => $now->copy()->subMinutes(10),
            'ended_at' => $now->copy()->subMinutes(10)->addSeconds(5),
        ]);
        SpeedNetworkingEventSkip::create([
            'event_id' => $this->eventOne->id,
            'user_id' => $this->peerC->id,
            'skipped_peer_id' => $this->peerD->id,
            'skip_count' => 2,
        ]);

        // 1 break round for PeerB
        SpeedNetworkingMatch::create([
            'event_id' => $this->eventOne->id,
            'round_number' => 4,
            'table_number' => 4,
            'channel_id' => 'ch_brk',
            'user_a_id' => $this->peerB->id,
            'user_b_id' => null,
            'status' => SpeedNetworkingMatch::STATUS_ACTIVE,
            'match_status' => SpeedNetworkingMatch::MATCH_STATUS_PREVIEW,
        ]);

        // Event-attributed P2P meeting + Coin Ledger + Life Impact for Match 1 ONLY
        $meeting = P2pMeeting::create([
            'initiator_user_id' => $this->peerA->id,
            'peer_user_id' => $this->peerB->id,
            'meeting_date' => $now->toDateString(),
            'meeting_place' => 'Speed Collaboration - '.$this->eventOne->title,
            'remarks' => "Qualified call [sn_match:{$firstCompletedMatch->id}]",
            'is_deleted' => false,
        ]);

        CoinsLedger::create([
            'transaction_id' => (string) Str::uuid(),
            'user_id' => $this->peerA->id,
            'amount' => 3000,
            'balance_after' => 99000,
            'source_type' => 'p2p_meeting',
            'source_id' => $meeting->id,
            'reference' => 'Speed Collaboration P2P Meeting',
            'remark' => 'Round 1 reward',
            'created_at' => $now->copy()->subMinutes(12),
        ]);
        CoinsLedger::create([
            'transaction_id' => (string) Str::uuid(),
            'user_id' => $this->peerB->id,
            'amount' => 3000,
            'balance_after' => 88000,
            'source_type' => 'p2p_meeting',
            'source_id' => $meeting->id,
            'reference' => 'Speed Collaboration P2P Meeting',
            'remark' => 'Round 1 reward',
            'created_at' => $now->copy()->subMinutes(12),
        ]);

        // Unrelated coin ledger entry for PeerA that MUST be excluded from Event One analytics
        CoinsLedger::create([
            'transaction_id' => (string) Str::uuid(),
            'user_id' => $this->peerA->id,
            'amount' => 25000,
            'balance_after' => 96000,
            'source_type' => 'referral',
            'source_id' => (string) Str::uuid(),
            'reference' => 'Unrelated referral bonus',
            'created_at' => $now->copy()->subHours(1),
        ]);

        LifeImpactHistory::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->peerA->id,
            'triggered_by_user_id' => $this->peerB->id,
            'activity_type' => 'p2p_meeting',
            'activity_id' => $meeting->id,
            'impact_value' => 1,
            'life_impacted' => 1,
            'impact_after' => 40,
            'counted_in_total' => true,
            'status' => 'approved',
            'title' => 'Speed Collaboration 1-on-1 Meeting',
            'description' => 'Session with Hardik Parmar',
            'meta' => ['speed_networking_event_id' => (string) $this->eventOne->id, 'match_id' => (string) $firstCompletedMatch->id],
        ]);
        LifeImpactHistory::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->peerB->id,
            'triggered_by_user_id' => $this->peerA->id,
            'activity_type' => 'p2p_meeting',
            'activity_id' => $meeting->id,
            'impact_value' => 1,
            'life_impacted' => 1,
            'impact_after' => 35,
            'counted_in_total' => true,
            'status' => 'approved',
            'title' => 'Speed Collaboration 1-on-1 Meeting',
            'description' => 'Session with Rahul Patel',
            'meta' => ['speed_networking_event_id' => (string) $this->eventOne->id, 'match_id' => (string) $firstCompletedMatch->id],
        ]);

        // Unrelated life impact entry for PeerA that MUST be excluded
        LifeImpactHistory::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->peerA->id,
            'triggered_by_user_id' => $this->peerC->id,
            'activity_type' => 'business_deal',
            'activity_id' => (string) Str::uuid(),
            'impact_value' => 15,
            'life_impacted' => 15,
            'counted_in_total' => true,
            'status' => 'approved',
            'title' => 'Unrelated business deal',
        ]);
    }

    protected function createMinimalSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('company_name')->nullable();
            $table->string('designation')->nullable();
            $table->string('city')->nullable();
            $table->string('business_city')->nullable();
            $table->string('profile_photo_url')->nullable();
            $table->integer('coins_balance')->default(0);
            $table->integer('life_impacted_count')->default(0);
            $table->boolean('is_admin')->default(false);
            $table->string('role')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('admin_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password_hash')->nullable();
            $table->string('role')->default('super_admin');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('banner_url')->nullable();
            $table->string('status')->default('scheduled');
            $table->string('event_type')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->decimal('ticket_price', 10, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('speed_networking_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('banner_url')->nullable();
            $table->string('status')->default('scheduled');
            $table->integer('round_duration_seconds')->default(300);
            $table->integer('break_duration_seconds')->default(20);
            $table->integer('max_skip_limit')->default(2);
            $table->integer('preview_duration_seconds')->default(15);
            $table->integer('total_rounds')->default(5);
            $table->integer('current_round')->default(0);
            $table->timestamp('scheduled_start_time')->nullable();
            $table->timestamp('scheduled_end_time')->nullable();
            $table->timestamp('actual_start_time')->nullable();
            $table->timestamp('actual_end_time')->nullable();
            $table->decimal('ticket_price', 10, 2)->default(0);
            $table->boolean('is_paid')->default(false);
            $table->string('currency')->default('INR');
            $table->uuid('circle_id')->nullable();
            $table->boolean('circle_restricted')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('user_id')->nullable();
            $table->string('status')->default('registered');
            $table->string('checkin_status')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->string('visitor_name')->nullable();
            $table->string('visitor_email')->nullable();
            $table->string('visitor_company')->nullable();
            $table->string('visitor_designation')->nullable();
            $table->string('visitor_city')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('speed_networking_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('user_id');
            $table->string('status')->default('registered');
            $table->integer('rounds_completed')->default(0);
            $table->integer('total_talk_time_seconds')->default(0);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('break_started_at')->nullable();
            $table->integer('break_remaining_seconds')->nullable();
            $table->timestamps();
        });

        Schema::create('speed_networking_matches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->integer('round_number')->default(1);
            $table->integer('table_number')->default(1);
            $table->string('channel_id')->nullable();
            $table->uuid('user_a_id');
            $table->uuid('user_b_id')->nullable();
            $table->string('status')->default('active');
            $table->string('match_status')->default('preview');
            $table->string('user_a_decision')->default('pending');
            $table->string('user_b_decision')->default('pending');
            $table->integer('preview_duration_seconds')->default(15);
            $table->timestamp('preview_expires_at')->nullable();
            $table->timestamp('call_started_at')->nullable();
            $table->timestamp('call_expires_at')->nullable();
            $table->boolean('extension_used')->default(false);
            $table->integer('extended_seconds')->default(0);
            $table->timestamp('extended_at')->nullable();
            $table->uuid('extended_by_user_id')->nullable();
            $table->integer('duration_seconds')->default(0);
            $table->text('user_a_notes')->nullable();
            $table->text('user_b_notes')->nullable();
            $table->text('meeting_notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('speed_networking_event_skips', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('user_id');
            $table->uuid('skipped_peer_id');
            $table->integer('skip_count')->default(1);
            $table->timestamps();
        });

        Schema::create('speed_networking_blocked_peers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('blocker_id');
            $table->uuid('blocked_id');
            $table->uuid('event_id')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('p2p_meetings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('initiator_user_id');
            $table->uuid('peer_user_id');
            $table->uuid('p2p_meeting_request_id')->nullable();
            $table->date('meeting_date')->nullable();
            $table->string('meeting_place')->nullable();
            $table->text('remarks')->nullable();
            $table->json('media')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('coins_ledger', function (Blueprint $table): void {
            $table->uuid('transaction_id')->primary();
            $table->uuid('user_id');
            $table->integer('amount');
            $table->integer('balance_after')->default(0);
            $table->uuid('activity_id')->nullable();
            $table->string('reference')->nullable();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->string('remark')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('life_impact_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('triggered_by_user_id')->nullable();
            $table->string('activity_type')->nullable();
            $table->string('activity_id')->nullable();
            $table->integer('impact_value')->default(0);
            $table->integer('impact_after')->default(0);
            $table->integer('life_impacted')->default(0);
            $table->boolean('counted_in_total')->default(true);
            $table->string('status')->default('approved');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('remarks')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }
}
