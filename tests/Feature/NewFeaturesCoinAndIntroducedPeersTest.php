<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\CheckDynamicPermission;
use App\Models\AdminUser;
use App\Models\CoinClaimRequest;
use App\Models\CoinsLedger;
use App\Models\FileModel;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NewFeaturesCoinAndIntroducedPeersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('public_profile_slug')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password_hash')->nullable();
            $table->string('membership_status')->nullable();
            $table->integer('coins_balance')->default(0);
            $table->integer('life_impacted_count')->nullable();
            $table->uuid('profile_photo_file_id')->nullable();
            $table->uuid('cover_photo_file_id')->nullable();
            $table->uuid('profile_video_id')->nullable();
            $table->string('profile_video_url')->nullable();
            $table->uuid('city_id')->nullable();
            $table->string('city')->nullable();
            $table->string('business_type')->nullable();
            $table->string('status')->nullable();
            $table->string('role')->nullable();
            $table->string('designation')->nullable();
            $table->unsignedBigInteger('business_category_id')->nullable();
            $table->uuid('introduced_by')->nullable();
            $table->integer('members_introduced_count')->default(0);
            $table->string('coin_medal_rank')->nullable();
            $table->string('coin_milestone_title')->nullable();
            $table->text('coin_milestone_meaning')->nullable();
            $table->string('contribution_award_name')->nullable();
            $table->text('contribution_award_recognition')->nullable();
            $table->json('bookmarks')->nullable();
            $table->timestamp('membership_ends_at')->nullable();
            $table->string('zoho_plan_code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('coin_claim_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('activity_code');
            $table->json('payload')->nullable();
            $table->string('status')->default('pending');
            $table->integer('coins_awarded')->nullable();
            $table->text('admin_notes')->nullable();
            $table->uuid('reviewed_by_admin_id')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('coins_ledger', function (Blueprint $table): void {
            $table->uuid('transaction_id')->primary();
            $table->uuid('user_id');
            $table->integer('amount');
            $table->integer('balance_after');
            $table->string('activity_id')->nullable();
            $table->string('reference')->nullable();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->text('remark')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uploader_user_id')->nullable();
            $table->string('s3_key')->nullable();
            $table->string('mime_type')->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('duration')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type');
            $table->json('payload')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('app_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('campaign_id')->nullable();
            $table->string('type');
            $table->string('category')->nullable();
            $table->string('title');
            $table->text('body');
            $table->text('message')->nullable();
            $table->string('channel')->default('push');
            $table->string('priority')->default('medium');
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('screen')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('admin_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_user_roles', function (Blueprint $table): void {
            $table->uuid('id')->nullable();
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->timestamps();
        });
    }

    public function test_peers_global_feedback_video_claim_submission_and_approval_flow(): void
    {
        Mail::fake();
        Storage::fake('public');

        $user = User::factory()->create([
            'coins_balance' => 0,
        ]);

        Sanctum::actingAs($user);

        // 1. Submit feedback video coin claim
        $videoFile = UploadedFile::fake()->create('feedback.mp4', 1024, 'video/mp4');

        $response = $this->postJson('/api/v1/coin-claims', [
            'activity_code' => 'peers_global_feedback_video',
            'files' => [
                'feedback_video' => $videoFile,
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $claimId = $response->json('data.id');
        $this->assertNotNull($claimId);

        $claim = CoinClaimRequest::findOrFail($claimId);
        $this->assertSame('pending', $claim->status);
        $this->assertNull($claim->coins_awarded);
        $this->assertSame('peers_global_feedback_video', $claim->activity_code);

        // 2. Admin approves claim
        $superRole = Role::create([
            'id' => (string) Str::uuid(),
            'key' => 'global_admin',
            'name' => 'Global Admin',
        ]);
        $adminUser = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Admin User',
            'email' => 'admin@example.com',
            'role' => 'global_admin',
        ]);
        $adminUser->roles()->attach($superRole->id);

        $this->actingAs($adminUser, 'admin');
        $this->withoutMiddleware([CheckDynamicPermission::class]);

        $approveResponse = $this->post(route('admin.coin-claims.approve', $claim->id), [
            'admin_notes' => 'Great feedback video!',
        ]);

        $approveResponse->assertRedirect();

        $claim->refresh();
        $user->refresh();

        $this->assertSame('approved', $claim->status);
        $this->assertSame(5000, $claim->coins_awarded);
        $this->assertSame(5000, $user->coins_balance);

        // Verify Coin Ledger entry created
        $ledger = CoinsLedger::where('user_id', $user->id)->first();
        $this->assertNotNull($ledger);
        $this->assertSame(5000, $ledger->amount);

        // 3. Duplicate approval protection - approving again must not add coins again
        $this->post(route('admin.coin-claims.approve', $claim->id));

        $user->refresh();
        $this->assertSame(5000, $user->coins_balance);
        $this->assertSame(1, CoinsLedger::where('user_id', $user->id)->count());
    }

    public function test_peers_global_feedback_video_claim_with_url_payload(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/coin-claims', [
            'activity_code' => 'peers_global_feedback_video',
            'payload' => [
                'feedback_video' => 'https://example.com/videos/my-feedback.mp4',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $claimId = $response->json('data.id');
        $claim = CoinClaimRequest::findOrFail($claimId);
        $this->assertSame('pending', $claim->status);
        $this->assertNotNull(data_get($claim->payload, 'feedback_video'));
    }

    public function test_peers_global_feedback_video_claim_requires_video(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/coin-claims', [
            'activity_code' => 'peers_global_feedback_video',
            'fields' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fields.feedback_video']);
    }

    public function test_introduced_peers_api_returns_sorted_by_introduced_count(): void
    {
        $currentUser = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($currentUser);

        // Peer A: introduced 3 members
        $peerA = User::factory()->create([
            'introduced_by' => $currentUser->id,
            'display_name' => 'Peer A',
            'status' => 'active',
        ]);
        User::factory()->count(3)->create(['introduced_by' => $peerA->id, 'status' => 'active']);

        // Peer B: introduced 1 member
        $peerB = User::factory()->create([
            'introduced_by' => $currentUser->id,
            'display_name' => 'Peer B',
            'status' => 'active',
        ]);
        User::factory()->count(1)->create(['introduced_by' => $peerB->id, 'status' => 'active']);

        // Peer C: introduced 0 members
        $peerC = User::factory()->create([
            'introduced_by' => $currentUser->id,
            'display_name' => 'Peer C',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/introduced-peers');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');

        $data = $response->json('data');

        // Verify sorting: Peer A (3) -> Peer B (1) -> Peer C (0)
        $this->assertSame($peerA->id, $data[0]['id']);
        $this->assertSame(3, $data[0]['introduced_count']);

        $this->assertSame($peerB->id, $data[1]['id']);
        $this->assertSame(1, $data[1]['introduced_count']);

        $this->assertSame($peerC->id, $data[2]['id']);
        $this->assertSame(0, $data[2]['introduced_count']);
    }

    public function test_intro_video_upload_awards_1000_coins_only_once(): void
    {
        $user = User::factory()->create([
            'coins_balance' => 0,
            'profile_video_id' => null,
        ]);

        $file1 = FileModel::create([
            'uploader_user_id' => $user->id,
            's3_key' => 'videos/intro1.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 1024,
        ]);

        $file2 = FileModel::create([
            'uploader_user_id' => $user->id,
            's3_key' => 'videos/intro2.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 2048,
        ]);

        Sanctum::actingAs($user);

        // 1. First time uploading intro video -> +1,000 coins awarded
        $response1 = $this->postJson('/api/v1/intro-videos', [
            'intro_video_id' => (string) $file1->id,
        ]);

        $response1->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.coins_earned', 1000)
            ->assertJsonPath('data.coins_balance', 1000);

        $user->refresh();
        $this->assertSame(1000, $user->coins_balance);
        $this->assertSame((string) $file1->id, (string) $user->profile_video_id);

        // Verify Coin Ledger has 1 record
        $this->assertSame(1, CoinsLedger::where('user_id', $user->id)->count());

        // 2. Updating / replacing intro video -> no additional coins awarded
        $response2 = $this->postJson('/api/v1/intro-videos', [
            'intro_video_id' => (string) $file2->id,
        ]);

        $response2->assertOk()
            ->assertJsonPath('success', true);

        $user->refresh();
        $this->assertSame(1000, $user->coins_balance);
        $this->assertSame((string) $file2->id, (string) $user->profile_video_id);
        $this->assertSame(1, CoinsLedger::where('user_id', $user->id)->count());
    }
}
