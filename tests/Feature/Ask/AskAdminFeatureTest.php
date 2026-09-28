<?php

declare(strict_types=1);

namespace Tests\Feature\Ask;

use App\Models\Ask\Ask;
use App\Models\Ask\AskFlow;
use App\Models\Ask\AskType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AskAdminFeatureTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;

    protected User $peerUser;

    protected AskFlow $flow;

    protected Ask $ask;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'status' => 'active',
            'first_name' => 'Admin',
            'last_name' => 'Tester',
        ]);

        $this->peerUser = User::factory()->create([
            'status' => 'active',
            'first_name' => 'Peer',
            'last_name' => 'Creator',
        ]);

        $this->flow = AskFlow::query()->firstOrCreate(
            ['code' => 'collaborate'],
            [
                'name' => 'Collaborate Flow',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $type = AskType::query()->firstOrCreate(
            ['code' => 'business-collaboration'],
            [
                'flow_id' => (string) $this->flow->id,
                'name' => 'Business Collaboration',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $this->ask = Ask::create([
            'user_id' => (string) $this->peerUser->id,
            'flow_id' => (string) $this->flow->id,
            'type_id' => (string) $type->id,
            'title' => 'Admin Test Ask Title',
            'description' => 'Admin Test Ask Description',
            'visibility_type' => Ask::VISIBILITY_ALL_PEERS,
            'status' => Ask::STATUS_PUBLISHED,
            'post_to_timeline' => false,
        ]);
    }

    public function test_admin_api_stats(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/asks/stats');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'stats' => [
                    'total_asks',
                    'published_asks',
                    'closed_asks',
                    'draft_asks',
                    'total_matches',
                    'total_responses',
                    'by_flow',
                ],
            ]);
    }

    public function test_admin_api_list_asks(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/asks?search=Admin+Test');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_admin_api_show_ask(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/admin/asks/{$this->ask->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', (string) $this->ask->id)
            ->assertJsonPath('data.title', 'Admin Test Ask Title');
    }

    public function test_admin_api_update_status(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->patchJson("/api/admin/asks/{$this->ask->id}/status", [
            'status' => 'closed',
            'reason' => 'Closed by automated admin test',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'closed');

        $this->assertDatabaseHas('ask_status_history', [
            'ask_id' => (string) $this->ask->id,
            'new_status' => 'closed',
            'reason' => 'Closed by automated admin test',
        ]);
    }

    public function test_admin_api_destroy_ask(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->deleteJson("/api/admin/asks/{$this->ask->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('asks', [
            'id' => (string) $this->ask->id,
        ]);
    }
}
