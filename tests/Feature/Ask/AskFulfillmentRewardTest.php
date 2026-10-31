<?php

declare(strict_types=1);

namespace Tests\Feature\Ask;

use App\Models\Ask\Ask;
use App\Models\Ask\AskFlow;
use App\Models\Ask\AskResponse;
use App\Models\Ask\AskType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AskFulfillmentRewardTest extends TestCase
{
    use DatabaseTransactions;

    protected User $userA;

    protected User $userB;

    protected User $userC;

    protected AskFlow $collabFlow;

    protected AskFlow $helpFlow;

    protected AskFlow $referralFlow;

    protected AskType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->create([
            'status' => 'active',
            'display_name' => 'User A',
            'coins_balance' => 0,
            'life_impacted_count' => 0,
        ]);

        $this->userB = User::factory()->create([
            'status' => 'active',
            'display_name' => 'User B',
            'coins_balance' => 0,
            'life_impacted_count' => 0,
        ]);

        $this->userC = User::factory()->create([
            'status' => 'active',
            'display_name' => 'User C',
            'coins_balance' => 0,
            'life_impacted_count' => 0,
        ]);

        $this->collabFlow = AskFlow::query()->firstOrCreate(
            ['code' => 'collaboration'],
            [
                'name' => 'Find a Collaborator',
                'description' => 'Collaboration flow',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $this->helpFlow = AskFlow::query()->firstOrCreate(
            ['code' => 'help'],
            [
                'name' => 'Get Help',
                'description' => 'Help flow',
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        $this->referralFlow = AskFlow::query()->firstOrCreate(
            ['code' => 'referral'],
            [
                'name' => 'Referral',
                'description' => 'Referral flow',
                'sort_order' => 3,
                'is_active' => true,
            ]
        );

        $this->type = AskType::query()->firstOrCreate(
            ['flow_id' => $this->collabFlow->id, 'code' => 'partnership'],
            [
                'name' => 'Partnership',
                'description' => 'Strategic partnership',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );
    }

    public function test_user_c_receives_1_life_impact_and_2500_coins_on_close_and_thank_fulfillment(): void
    {
        // User A creates an Ask
        $ask = Ask::create([
            'user_id' => $this->userA->id,
            'flow_id' => $this->collabFlow->id,
            'type_id' => $this->type->id,
            'title' => 'Need Tech Partner for Expansion',
            'status' => Ask::STATUS_PUBLISHED,
            'visibility_type' => Ask::VISIBILITY_ALL_PEERS,
        ]);

        // User B offers help
        AskResponse::create([
            'ask_id' => $ask->id,
            'responder_user_id' => $this->userB->id,
            'response_type' => AskResponse::TYPE_CAN_HELP_DIRECTLY,
            'message' => 'I can help with advisory.',
            'status' => AskResponse::STATUS_PENDING,
            'responded_at' => now()->subHours(2),
        ]);

        // User C offers help
        AskResponse::create([
            'ask_id' => $ask->id,
            'responder_user_id' => $this->userC->id,
            'response_type' => AskResponse::TYPE_CAN_HELP_DIRECTLY,
            'message' => 'I can build the tech stack.',
            'status' => AskResponse::STATUS_ACCEPTED,
            'responded_at' => now()->subHour(),
        ]);

        // Initial sanity check
        $this->assertSame(0, (int) $this->userA->fresh()->coins_balance);
        $this->assertSame(0, (int) $this->userA->fresh()->life_impacted_count);
        $this->assertSame(0, (int) $this->userB->fresh()->coins_balance);
        $this->assertSame(0, (int) $this->userB->fresh()->life_impacted_count);
        $this->assertSame(0, (int) $this->userC->fresh()->coins_balance);
        $this->assertSame(0, (int) $this->userC->fresh()->life_impacted_count);

        // User A fulfills the ask with User C's help via close-and-thank
        Sanctum::actingAs($this->userA);

        $response = $this->postJson("/api/v1/asks/{$ask->id}/close-and-thank", [
            'giver_id' => $this->userC->id,
            'gratitude_note' => 'Thank you User C for outstanding technical execution!',
            'publish_story_to_feed' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'fulfilled');

        // User C (helper) MUST receive +2,500 Coins and +1 Life Impact
        $this->userC->refresh();
        $this->assertSame(2500, (int) $this->userC->coins_balance);
        $this->assertSame(1, (int) $this->userC->life_impacted_count);

        // User A (creator) MUST NOT receive coins or life impact
        $this->userA->refresh();
        $this->assertSame(0, (int) $this->userA->coins_balance);
        $this->assertSame(0, (int) $this->userA->life_impacted_count);

        // User B (other helper) MUST NOT receive coins or life impact
        $this->userB->refresh();
        $this->assertSame(0, (int) $this->userB->coins_balance);
        $this->assertSame(0, (int) $this->userB->life_impacted_count);

        // Verify idempotency: duplicate fulfillment attempt does not award coins/impact again
        $repeatResponse = $this->postJson("/api/v1/asks/{$ask->id}/close-and-thank", [
            'giver_id' => $this->userC->id,
            'gratitude_note' => 'Repeated close call',
        ]);
        $repeatResponse->assertOk();

        $this->userC->refresh();
        $this->assertSame(2500, (int) $this->userC->coins_balance);
        $this->assertSame(1, (int) $this->userC->life_impacted_count);
    }

    public function test_user_c_receives_rewards_when_ask_status_is_updated_to_fulfilled(): void
    {
        // User A creates a Get Help Ask
        $ask = Ask::create([
            'user_id' => $this->userA->id,
            'flow_id' => $this->helpFlow->id,
            'type_id' => $this->type->id,
            'title' => 'Need Guidance on Marketing Strategy',
            'status' => Ask::STATUS_PUBLISHED,
            'visibility_type' => Ask::VISIBILITY_ALL_PEERS,
        ]);

        // User B offers help
        AskResponse::create([
            'ask_id' => $ask->id,
            'responder_user_id' => $this->userB->id,
            'response_type' => AskResponse::TYPE_CAN_HELP_DIRECTLY,
            'message' => 'Check online guides.',
            'status' => AskResponse::STATUS_DECLINED,
            'responded_at' => now()->subHours(3),
        ]);

        // User C offers help
        AskResponse::create([
            'ask_id' => $ask->id,
            'responder_user_id' => $this->userC->id,
            'response_type' => AskResponse::TYPE_CAN_HELP_DIRECTLY,
            'message' => 'I have 10 years experience in PPC campaigns.',
            'status' => AskResponse::STATUS_ACCEPTED,
            'responded_at' => now()->subHours(2),
        ]);

        // User A updates ask status to fulfilled with User C as helper
        Sanctum::actingAs($this->userA);

        $response = $this->patchJson("/api/v1/asks/help/{$ask->id}/status", [
            'status' => 'fulfilled',
            'status_id' => 4,
            'status_label' => 'Got the Business',
            'giver_id' => $this->userC->id,
            'remarks' => 'Marketing plan executed successfully!',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        // User C gets +2500 Coins and +1 Life Impact
        $this->userC->refresh();
        $this->assertSame(2500, (int) $this->userC->coins_balance);
        $this->assertSame(1, (int) $this->userC->life_impacted_count);

        // Neither A nor B get coins or life impact
        $this->assertSame(0, (int) $this->userA->fresh()->coins_balance);
        $this->assertSame(0, (int) $this->userA->fresh()->life_impacted_count);
        $this->assertSame(0, (int) $this->userB->fresh()->coins_balance);
        $this->assertSame(0, (int) $this->userB->fresh()->life_impacted_count);
    }

    public function test_user_c_receives_rewards_when_response_is_completed(): void
    {
        // User A creates a Referral Ask
        $ask = Ask::create([
            'user_id' => $this->userA->id,
            'flow_id' => $this->referralFlow->id,
            'type_id' => $this->type->id,
            'title' => 'Looking for Architect in Mumbai',
            'status' => Ask::STATUS_PUBLISHED,
            'visibility_type' => Ask::VISIBILITY_ALL_PEERS,
        ]);

        // User B offers help
        AskResponse::create([
            'ask_id' => $ask->id,
            'responder_user_id' => $this->userB->id,
            'response_type' => AskResponse::TYPE_CAN_HELP_DIRECTLY,
            'message' => 'I know an engineer.',
            'status' => AskResponse::STATUS_PENDING,
            'responded_at' => now()->subHours(2),
        ]);

        // User C introduces the right contact
        $responseC = AskResponse::create([
            'ask_id' => $ask->id,
            'responder_user_id' => $this->userC->id,
            'response_type' => AskResponse::TYPE_CAN_INTRODUCE_PEER,
            'message' => 'Introducing Top Mumbai Architect.',
            'status' => AskResponse::STATUS_PENDING,
            'responded_at' => now()->subHour(),
        ]);

        // User A marks User C's response as completed (status_id = 4 or 5)
        Sanctum::actingAs($this->userA);

        $response = $this->patchJson("/api/v1/asks/responses/{$responseC->id}/status", [
            'status_id' => 4,
            'status_label' => 'Got the Business',
            'remarks' => 'Contract signed with the architect!',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        // User C gets +2500 Coins and +1 Life Impact
        $this->userC->refresh();
        $this->assertSame(2500, (int) $this->userC->coins_balance);
        $this->assertSame(1, (int) $this->userC->life_impacted_count);

        // Ask status should also be fulfilled
        $ask->refresh();
        $this->assertSame(Ask::STATUS_FULFILLED, $ask->status);

        // User A and User B get nothing
        $this->assertSame(0, (int) $this->userA->fresh()->coins_balance);
        $this->assertSame(0, (int) $this->userA->fresh()->life_impacted_count);
        $this->assertSame(0, (int) $this->userB->fresh()->coins_balance);
        $this->assertSame(0, (int) $this->userB->fresh()->life_impacted_count);
    }
}
