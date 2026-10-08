<?php

declare(strict_types=1);

namespace Tests\Feature\Leadership;

use App\Models\AdminUser;
use App\Models\Leadership\LeadershipCampaign;
use App\Models\Leadership\LeadershipCampaignScope;
use App\Models\Leadership\LeadershipNomination;
use App\Models\Leadership\LeadershipVoterVerification;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadershipSelectionWorkflowTest extends TestCase
{
    protected Role $role;

    protected LeadershipCampaign $campaign;

    protected LeadershipCampaignScope $scope;

    protected AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::firstOrCreate(
            ['key' => 'ded_test'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'District Entrepreneur Director Test',
                'status' => 'active',
                'is_assignable' => true,
                'role_type' => 'admin',
                'hierarchy_depth' => 1,
            ]
        );

        $this->campaign = LeadershipCampaign::create([
            'role_id' => $this->role->id,
            'name' => '2026 Test Leadership Campaign',
            'slug' => '2026-test-leadership-campaign-'.Str::random(5),
            'campaign_year' => 2026,
            'status' => 'active',
            'nomination_starts_at' => Carbon::now()->subDays(5),
            'nomination_ends_at' => Carbon::now()->addDays(20),
            'voting_starts_at' => Carbon::now()->subDay(),
            'voting_ends_at' => Carbon::now()->addDays(10),
            'published_at' => Carbon::now(),
        ]);

        $this->scope = LeadershipCampaignScope::create([
            'campaign_id' => $this->campaign->id,
            'scope_type' => 'district',
            'scope_name' => 'Ahmedabad Test',
            'status' => 'active',
        ]);

        $this->admin = AdminUser::firstOrCreate(
            ['email' => 'admin_leadership_test@peersglobal.com'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Test Leadership Admin',
                'password' => bcrypt('password123'),
                'is_active' => true,
            ]
        );
    }

    public function test_can_fetch_public_active_campaigns(): void
    {
        $response = $this->getJson('/api/v1/leadership/public/campaigns');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_can_request_and_verify_nomination_otp(): void
    {
        // 1. Request OTP
        $reqResponse = $this->postJson('/api/v1/leadership/public/verification/nomination/request-otp', [
            'campaign_id' => $this->campaign->id,
            'contact_type' => 'mobile',
            'contact' => '+919999988888',
        ]);

        $reqResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $verificationId = $reqResponse->json('data.verification_id');
        $this->assertNotNull($verificationId);

        // 2. Verify OTP with test code 123456
        $verifyResponse = $this->postJson('/api/v1/leadership/public/verification/nomination/verify-otp', [
            'verification_id' => $verificationId,
            'otp' => '123456',
        ]);

        $verifyResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.verified', true);

        $token = $verifyResponse->json('data.verification_token');
        $this->assertNotNull($token);
    }

    public function test_can_save_nomination_draft_and_submit(): void
    {
        // Issue token in cache
        $token = 'nvt_test_'.Str::random(20);
        Cache::put('nomination_token:'.$token, [
            'campaign_id' => $this->campaign->id,
            'contact_type' => 'email',
            'contact' => 'candidate_test@example.com',
            'verified_at' => Carbon::now()->toIso8601String(),
        ], 900);

        // 1. Save Draft
        $draftResponse = $this->postJson('/api/v1/leadership/public/nominations/draft', [
            'campaign_id' => $this->campaign->id,
            'scope_id' => $this->scope->id,
            'verification_token' => $token,
            'profile' => [
                'full_name' => 'John Entrepreneur',
                'company_name' => 'Tech Pioneers Ltd',
            ],
            'answers' => [
                'leadership_statement' => 'Empowering leaders to scale impact.',
            ],
        ]);

        $draftResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft');

        $nominationId = $draftResponse->json('data.nomination_id');
        $this->assertNotNull($nominationId);

        // 2. Submit Nomination
        $submitResponse = $this->postJson("/api/v1/leadership/public/nominations/{$nominationId}/submit", [
            'verification_token' => $token,
            'declarations' => [
                'information_is_true' => true,
                'code_of_conduct_accepted' => true,
            ],
        ]);

        $submitResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'submitted');
    }

    public function test_admin_can_review_and_shortlist_nomination(): void
    {
        $nomination = LeadershipNomination::create([
            'campaign_id' => $this->campaign->id,
            'scope_id' => $this->scope->id,
            'application_number' => 'PG-2026-TEST-'.Str::random(4),
            'full_name' => 'Shortlist Candidate',
            'email' => 'shortlist@example.com',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson("/api/v1/leadership/admin/nominations/{$nomination->id}/shortlist", [
                'remarks' => 'Outstanding business credentials',
                'enable_public_voting' => true,
                'enable_jury_evaluation' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'shortlisted');
    }

    public function test_voting_prevents_duplicate_votes(): void
    {
        $nomination = LeadershipNomination::create([
            'campaign_id' => $this->campaign->id,
            'scope_id' => $this->scope->id,
            'application_number' => 'PG-2026-VOTE-'.Str::random(4),
            'full_name' => 'Votable Candidate',
            'email' => 'votable@example.com',
            'status' => 'shortlisted',
        ]);

        $contactHash = hash('sha256', 'voter@example.com');
        $verification = LeadershipVoterVerification::create([
            'campaign_id' => $this->campaign->id,
            'scope_id' => $this->scope->id,
            'contact_type' => 'email',
            'contact_hash' => $contactHash,
            'otp_hash' => Hash::make('123456'),
            'status' => 'verified',
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $votingToken = 'vvt_test_'.Str::random(20);
        Cache::put('voting_token:'.$votingToken, [
            'verification_id' => $verification->id,
            'campaign_id' => $this->campaign->id,
            'scope_id' => $this->scope->id,
            'contact_hash' => $contactHash,
        ], 600);

        // 1. Cast first vote
        $vote1 = $this->postJson('/api/v1/leadership/public/votes', [
            'campaign_id' => $this->campaign->id,
            'scope_id' => $this->scope->id,
            'nomination_id' => $nomination->id,
            'voting_token' => $votingToken,
        ]);

        $vote1->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'recorded');

        // 2. Cast second vote with newly generated token for same contact
        $votingToken2 = 'vvt_test2_'.Str::random(20);
        Cache::put('voting_token:'.$votingToken2, [
            'verification_id' => $verification->id,
            'campaign_id' => $this->campaign->id,
            'scope_id' => $this->scope->id,
            'contact_hash' => $contactHash,
        ], 600);

        $vote2 = $this->postJson('/api/v1/leadership/public/votes', [
            'campaign_id' => $this->campaign->id,
            'scope_id' => $this->scope->id,
            'nomination_id' => $nomination->id,
            'voting_token' => $votingToken2,
        ]);

        $vote2->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_create_and_publish_campaign(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/leadership/admin/campaigns', [
                'role_id' => $this->role->id,
                'name' => '2026 ED Selection Campaign',
                'campaign_year' => 2026,
                'nomination_starts_at' => Carbon::now()->toIso8601String(),
                'nomination_ends_at' => Carbon::now()->addMonth()->toIso8601String(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft');

        $campaignId = $response->json('data.id');

        // Publish campaign
        $pubResponse = $this->actingAs($this->admin, 'admin')
            ->postJson("/api/v1/leadership/admin/campaigns/{$campaignId}/publish", [
                'remarks' => 'Ready for launch',
            ]);

        $pubResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_form_builder_lifecycle(): void
    {
        // 1. Create form
        $formRes = $this->actingAs($this->admin, 'admin')
            ->postJson("/api/v1/leadership/admin/campaigns/{$this->campaign->id}/forms", [
                'form_type' => 'nomination',
                'name' => 'General Nomination Form',
            ]);

        $formRes->assertStatus(201);
        $formId = $formRes->json('data.id');

        // 2. Add Section
        $secRes = $this->actingAs($this->admin, 'admin')
            ->postJson("/api/v1/leadership/admin/forms/{$formId}/sections", [
                'title' => 'Personal Track Record',
                'sort_order' => 1,
            ]);

        $secRes->assertStatus(201);
        $sectionId = $secRes->json('data.id');

        // 3. Add Question
        $qRes = $this->actingAs($this->admin, 'admin')
            ->postJson("/api/v1/leadership/admin/sections/{$sectionId}/questions", [
                'question_key' => 'past_initiatives',
                'label' => 'List your top leadership initiatives',
                'field_type' => 'textarea',
                'is_required' => true,
            ]);

        $qRes->assertStatus(201);

        // 4. Publish Form
        $pubRes = $this->actingAs($this->admin, 'admin')
            ->postJson("/api/v1/leadership/admin/forms/{$formId}/publish");

        $pubRes->assertStatus(200)
            ->assertJsonPath('data.status', 'published');
    }

    public function test_candidate_private_results_access(): void
    {
        $nomination = LeadershipNomination::create([
            'campaign_id' => $this->campaign->id,
            'scope_id' => $this->scope->id,
            'application_number' => 'PG-2026-RES-'.Str::random(4),
            'full_name' => 'Candidate Results Test',
            'email' => 'results_cand@example.com',
            'status' => 'shortlisted',
        ]);

        // Generate result link
        $genRes = $this->actingAs($this->admin, 'admin')
            ->postJson("/api/v1/leadership/admin/nominations/{$nomination->id}/results-link");

        $genRes->assertStatus(200);
        $rawToken = $genRes->json('data.raw_token');

        // Verify result OTP
        $otpRes = $this->postJson('/api/v1/leadership/public/results/verify-otp', [
            'result_token' => $rawToken,
            'contact_type' => 'email',
            'contact' => 'results_cand@example.com',
            'otp' => '123456',
        ]);

        $otpRes->assertStatus(200);
        $accessToken = $otpRes->json('data.result_access_token');

        // Access private results
        $resultsRes = $this->withHeader('X-Result-Access-Token', $accessToken)
            ->getJson('/api/v1/leadership/public/results');

        $resultsRes->assertStatus(200)
            ->assertJsonPath('data.candidate.name', 'Candidate Results Test')
            ->assertJsonPath('data.results.total_votes', 0);
    }

    public function test_final_decision_and_winner_publishing(): void
    {
        $nomination = LeadershipNomination::create([
            'campaign_id' => $this->campaign->id,
            'scope_id' => $this->scope->id,
            'application_number' => 'PG-2026-WIN-'.Str::random(4),
            'full_name' => 'Winner Candidate',
            'email' => 'winner@example.com',
            'status' => 'shortlisted',
        ]);

        // Record Decision
        $decisionRes = $this->actingAs($this->admin, 'admin')
            ->postJson("/api/v1/leadership/admin/campaigns/{$this->campaign->id}/decisions", [
                'decisions' => [
                    [
                        'nomination_id' => $nomination->id,
                        'decision' => 'selected',
                        'is_winner' => true,
                        'decision_reason' => 'Exceptional jury score and voter support.',
                    ],
                ],
            ]);

        $decisionRes->assertStatus(200);
        $decisionId = $decisionRes->json('data.0.id');

        // Publish Winner
        $pubRes = $this->actingAs($this->admin, 'admin')
            ->postJson("/api/v1/leadership/admin/decisions/{$decisionId}/publish");

        $pubRes->assertStatus(200)
            ->assertJsonPath('data.publication_status', 'published');
    }

    public function test_dashboard_overview_stats(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->getJson('/api/v1/leadership/admin/dashboard/overview');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_campaigns',
                    'active_campaigns',
                    'total_nominations',
                    'total_votes',
                    'declared_winners',
                ],
            ]);
    }
}
