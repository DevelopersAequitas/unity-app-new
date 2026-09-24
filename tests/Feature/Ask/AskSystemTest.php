<?php

declare(strict_types=1);

namespace Tests\Feature\Ask;

use App\Models\Ask\Ask;
use App\Models\Ask\AskFlow;
use App\Models\Ask\AskOption;
use App\Models\Ask\AskOptionGroup;
use App\Models\Ask\AskType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AskSystemTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected User $peer;

    protected AskFlow $flow;

    protected AskType $type;

    protected AskOptionGroup $optionGroup;

    protected AskOption $option;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'status' => 'active',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'display_name' => 'John Doe',
        ]);

        $this->peer = User::factory()->create([
            'status' => 'active',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'display_name' => 'Jane Smith',
        ]);

        $this->flow = AskFlow::firstOrCreate(
            ['code' => 'collaboration'],
            [
                'name' => 'Find a Collaborator',
                'description' => 'Find the right peer for collaboration.',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $this->type = AskType::firstOrCreate(
            ['flow_id' => $this->flow->id, 'code' => 'joint_venture'],
            [
                'name' => 'Joint Venture',
                'level' => 1,
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $this->optionGroup = AskOptionGroup::firstOrCreate(
            ['code' => 'industry'],
            [
                'name' => 'Industry',
                'input_type' => 'single_select',
                'is_multi_select' => false,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $this->option = AskOption::firstOrCreate(
            ['option_group_id' => $this->optionGroup->id, 'code' => 'tech'],
            [
                'label' => 'Technology',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );
    }

    public function test_get_ask_flows(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/asks/flows');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['code' => 'collaboration']);
    }

    public function test_get_ask_types(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/asks/flows/{$this->flow->code}/types");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['code' => 'joint_venture']);
    }

    public function test_get_form_config(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/asks/form-config?flow={$this->flow->code}&type={$this->type->code}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['code' => 'industry']);
    }

    public function test_create_ask_draft_and_save_details(): void
    {
        Sanctum::actingAs($this->user);

        // 1. Create Draft
        $createResponse = $this->postJson('/api/asks', [
            'flow_id' => (string) $this->flow->id,
            'type_id' => (string) $this->type->id,
            'title' => 'Looking for Co-founder',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'draft');

        $askId = $createResponse->json('ask_id');
        $this->assertNotEmpty($askId);

        // 2. Save Details
        $detailsResponse = $this->putJson("/api/asks/{$askId}", [
            'answers' => [
                [
                    'field_key' => 'goal',
                    'value_text' => 'Build a SaaS platform together',
                ],
                [
                    'field_key' => 'industry',
                    'option_id' => (string) $this->option->id,
                ],
            ],
        ]);

        $detailsResponse->assertOk()
            ->assertJsonPath('success', true);

        // 3. Save Filters
        $filtersResponse = $this->putJson("/api/asks/{$askId}/filters", [
            'industry' => [(string) $this->option->id],
            'expected_outcome' => 'Launch in 6 months',
        ]);

        $filtersResponse->assertOk()
            ->assertJsonPath('success', true);

        // 4. Set Visibility
        $visibilityResponse = $this->putJson("/api/asks/{$askId}/visibility", [
            'visibility_type' => 'all_peers',
        ]);

        $visibilityResponse->assertOk()
            ->assertJsonPath('success', true);

        // 5. Set Timeline Preference
        $timelineResponse = $this->putJson("/api/asks/{$askId}/timeline-preference", [
            'publish_to_timeline' => true,
        ]);

        $timelineResponse->assertOk()
            ->assertJsonPath('success', true);

        // 6. Preview
        $previewResponse = $this->getJson("/api/asks/{$askId}/preview");
        $previewResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Looking for Co-founder');

        // 7. Publish
        $publishResponse = $this->postJson("/api/asks/{$askId}/publish");
        $publishResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'published');

        // 8. Generate and Get Matches
        $generateResponse = $this->postJson("/api/asks/{$askId}/matches/generate");
        $generateResponse->assertOk()
            ->assertJsonPath('success', true);

        $matchesResponse = $this->getJson("/api/asks/{$askId}/matches");
        $matchesResponse->assertOk()
            ->assertJsonPath('success', true);

        // 9. Peer Responder view and Submit Response
        Sanctum::actingAs($this->peer);

        $respondViewResponse = $this->getJson("/api/asks/{$askId}/respond");
        $respondViewResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['available_response_types']);

        $submitResponse = $this->postJson("/api/asks/{$askId}/responses", [
            'response_type' => 'can_help_directly',
            'message' => 'I have 10 years experience in SaaS development.',
        ]);

        $submitResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $responseId = $submitResponse->json('data.id');

        // 10. Ask Owner checks responses and updates status
        Sanctum::actingAs($this->user);

        $listResponses = $this->getJson("/api/asks/{$askId}/responses");
        $listResponses->assertOk()
            ->assertJsonPath('success', true);

        $updateStatusResponse = $this->patchJson("/api/asks/{$askId}/responses/{$responseId}", [
            'status' => 'accepted',
            'note' => 'Let us schedule an intro call.',
        ]);

        $updateStatusResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'accepted');

        // 11. Response status history
        $responseHistory = $this->getJson("/api/asks/{$askId}/responses/{$responseId}/history");
        $responseHistory->assertOk()
            ->assertJsonPath('success', true);

        // 12. Close Ask
        $closeResponse = $this->patchJson("/api/asks/{$askId}/status", [
            'status' => 'closed',
            'reason' => 'Co-founder found!',
        ]);

        $closeResponse->assertOk()
            ->assertJsonPath('success', true);

        // 13. Ask lifecycle history
        $askHistory = $this->getJson("/api/asks/{$askId}/history");
        $askHistory->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_submit_know_someone_response_and_update_contact(): void
    {
        Sanctum::actingAs($this->user);

        $ask = Ask::create([
            'user_id' => $this->user->id,
            'flow_id' => $this->flow->id,
            'type_id' => $this->type->id,
            'title' => 'Need Manufacturer',
            'status' => 'published',
            'visibility_type' => 'all_peers',
        ]);

        Sanctum::actingAs($this->peer);

        $submitResponse = $this->postJson("/api/asks/{$ask->id}/responses", [
            'response_type' => 'know_someone',
            'message' => 'I know a great factory owner',
            'contact' => [
                'full_name' => 'Rahul Shah',
                'company_name' => 'ABC Manufacturing',
                'designation' => 'Managing Director',
                'email' => 'rahul@example.com',
                'phone' => '+919999999999',
                'notes' => 'Connected via Gujarat chamber',
            ],
        ]);

        $submitResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.contact.full_name', 'Rahul Shah');

        $responseId = $submitResponse->json('data.id');

        // Update contact details (API 25)
        $updateContact = $this->patchJson("/api/asks/{$ask->id}/responses/{$responseId}/contact", [
            'designation' => 'Executive Director',
        ]);

        $updateContact->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.designation', 'Executive Director');
    }
}
