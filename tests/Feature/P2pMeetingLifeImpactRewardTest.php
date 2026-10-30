<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ActivityCreated;
use App\Models\P2pMeeting;
use App\Models\User;
use App\Services\Notifications\NotifyUserService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class P2pMeetingLifeImpactRewardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        Queue::fake();

        config()->set('queue.default', 'null');
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->unique();
            $table->string('company_name')->nullable();
            $table->string('designation')->nullable();
            $table->string('membership_status')->nullable();
            $table->string('status')->default('active');
            $table->bigInteger('coins_balance')->default(0);
            $table->bigInteger('life_impacted_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uploader_user_id')->nullable();
            $table->string('s3_key')->nullable();
            $table->string('mime_type')->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->timestamps();
        });

        Schema::create('peer_blocks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('blocker_user_id');
            $table->uuid('blocked_user_id');
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('p2p_meetings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('initiator_user_id');
            $table->uuid('peer_user_id');
            $table->uuid('p2p_meeting_request_id')->nullable();
            $table->date('meeting_date');
            $table->string('meeting_place')->nullable();
            $table->text('remarks')->nullable();
            $table->json('media')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('p2p_meeting_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('requester_id');
            $table->uuid('invitee_id');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('place')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('is_logged')->default(false);
            $table->timestamp('logged_at')->nullable();
            $table->uuid('logged_by_user_id')->nullable();
            $table->uuid('p2p_meeting_id')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('circle_id')->nullable();
            $table->text('content_text')->nullable();
            $table->json('media')->nullable();
            $table->json('tags')->nullable();
            $table->string('visibility')->nullable();
            $table->string('moderation_status')->nullable();
            $table->boolean('sponsored')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->string('source_type')->nullable();
            $table->uuid('source_id')->nullable();
            $table->string('source_event')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('coins_ledger', function (Blueprint $table): void {
            $table->uuid('transaction_id')->primary();
            $table->uuid('user_id');
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->uuid('activity_id')->nullable();
            $table->string('reference')->nullable();
            $table->string('source_type', 100)->nullable();
            $table->uuid('source_id')->nullable();
            $table->text('remark')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('life_impact_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('triggered_by_user_id')->nullable();
            $table->string('activity_type')->nullable();
            $table->uuid('activity_id')->nullable();
            $table->bigInteger('impact_value')->default(0);
            $table->bigInteger('impact_after')->default(0);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('counted_in_total')->default(true);
            $table->string('status')->nullable();
            $table->timestamps();
        });

        $notify = Mockery::mock(NotifyUserService::class);
        $notify->shouldReceive('notifyUser')->andReturn(null);
        $this->app->instance(NotifyUserService::class, $notify);

        Event::fake([
            ActivityCreated::class,
        ]);
    }

    public function test_p2p_meeting_completion_increases_life_impact_by_5(): void
    {
        $creator = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Alice initiator',
            'display_name' => 'Alice Initiator',
            'email' => 'alice.initiator@example.com',
            'status' => 'active',
            'coins_balance' => 0,
            'life_impacted_count' => 10,
        ]);

        $peer = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Bob peer',
            'display_name' => 'Bob Peer',
            'email' => 'bob.peer@example.com',
            'status' => 'active',
            'coins_balance' => 0,
            'life_impacted_count' => 20,
        ]);

        $response = $this->actingAs($creator, 'sanctum')->postJson('/api/v1/activities/p2p-meetings', [
            'peer_user_id' => $peer->id,
            'meeting_place' => 'Ahmedabad HQ',
            'remarks' => 'Discussed business roadmap and collaborative ventures.',
            'meeting_date' => now()->toDateString(),
            'duration_minutes' => 45,
            'media_file_ids' => [],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.life_impact.earned', 5)
            ->assertJsonPath('data.life_impact.total', 15)
            ->assertJsonPath('data.coins.earned', 3000);

        $creator->refresh();
        $peer->refresh();

        // Initiator gets +5
        $this->assertSame(15, (int) $creator->life_impacted_count);

        // Peer also gets +5
        $this->assertSame(25, (int) $peer->life_impacted_count);

        // Verify Life Impact History records
        $meetingId = $response->json('data.id');
        $this->assertDatabaseHas('life_impact_histories', [
            'user_id' => $creator->id,
            'activity_type' => 'p2p_meeting',
            'activity_id' => $meetingId,
            'impact_value' => 5,
        ]);

        $this->assertDatabaseHas('life_impact_histories', [
            'user_id' => $peer->id,
            'activity_type' => 'p2p_meeting',
            'activity_id' => $meetingId,
            'impact_value' => 5,
        ]);
    }
}
