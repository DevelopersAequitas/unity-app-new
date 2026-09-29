<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Services\Recommendation\MemberMatchingService;
use Tests\TestCase;

class MemberMatchingServiceTest extends TestCase
{
    protected MemberMatchingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MemberMatchingService;
    }

    public function test_self_match_returns_100_percent(): void
    {
        $user = new User([
            'id' => '00000000-0000-0000-0000-000000000001',
            'city' => 'Ahmedabad',
        ]);

        $result = $this->service->calculateMatchScore($user, $user);

        $this->assertSame(100, $result['match_percentage']);
        $this->assertSame(25, $result['breakdown']['category_score']);
        $this->assertSame(20, $result['breakdown']['location_score']);
    }

    public function test_category_match_weightage(): void
    {
        $authUser = new User([
            'id' => '00000000-0000-0000-0000-000000000001',
            'business_category_id' => 10,
            'business_sub_category' => 'IT Services',
        ]);

        $candidate = new User([
            'id' => '00000000-0000-0000-0000-000000000002',
            'business_category_id' => 10,
            'business_sub_category' => 'IT Services',
        ]);

        $result = $this->service->calculateMatchScore($authUser, $candidate);

        $this->assertSame(25, $result['breakdown']['category_score']);
    }

    public function test_location_and_industry_skills_match_weightage(): void
    {
        $authUser = new User([
            'id' => '00000000-0000-0000-0000-000000000001',
            'city' => 'Ahmedabad',
            'industries_of_interest' => ['Information Technology', 'Software'],
            'skills' => ['Laravel', 'VueJS'],
            'industry_tags' => ['Tech', 'SaaS'],
        ]);

        $candidate = new User([
            'id' => '00000000-0000-0000-0000-000000000002',
            'city' => 'Ahmedabad',
            'industries_of_interest' => ['Information Technology'],
            'skills' => ['Laravel'],
            'industry_tags' => ['Tech'],
        ]);

        $result = $this->service->calculateMatchScore($authUser, $candidate);

        $this->assertSame(20, $result['breakdown']['location_score']);
        $this->assertGreaterThanOrEqual(13, $result['breakdown']['industry_skills_score']);
    }

    public function test_synergy_and_mutual_connections_match_weightage(): void
    {
        $authUser = new User([
            'id' => '00000000-0000-0000-0000-000000000001',
            'i_can_help_with' => ['Digital Marketing', 'SEO'],
            'i_am_looking_for' => ['Web Development'],
            'interests' => ['Reading', 'Technology'],
            'collaboration_goals' => ['Joint Ventures'],
        ]);

        $candidate = new User([
            'id' => '00000000-0000-0000-0000-000000000002',
            'i_can_help_with' => ['Web Development'],
            'i_am_looking_for' => ['Digital Marketing'],
            'interests' => ['Technology'],
            'collaboration_goals' => ['Joint Ventures'],
        ]);

        $authConnections = ['user-10', 'user-20', 'user-30'];
        $candidateConnections = ['user-10', 'user-20', 'user-40'];

        $result = $this->service->calculateMatchScore($authUser, $candidate, $authConnections, $candidateConnections);

        // 2 mutual connections = 10 pts
        $this->assertSame(10, $result['breakdown']['mutual_connections_score']);
        // 2-way synergy match = 10 pts
        $this->assertSame(10, $result['breakdown']['synergy_score']);
        // Interests + Goals match = 5 pts
        $this->assertGreaterThanOrEqual(5, $result['breakdown']['interests_score']);
    }

    public function test_determine_is_pro_correctly_identifies_pro_members(): void
    {
        $verifiedUser = new User(['is_verified' => true]);
        $circlePeer = new User(['membership_status' => 'Circle Peer']);
        $onlyUnity = new User(['membership_status' => 'Only Unity Peer']);
        $freePeer = new User(['membership_status' => 'free_peer', 'is_verified' => false]);
        $freeTrial = new User(['membership_status' => 'free_trial_peer', 'is_verified' => false]);

        $this->assertTrue($this->service->determineIsPro($verifiedUser));
        $this->assertTrue($this->service->determineIsPro($circlePeer));
        $this->assertTrue($this->service->determineIsPro($onlyUnity));
        $this->assertFalse($this->service->determineIsPro($freePeer));
        $this->assertFalse($this->service->determineIsPro($freeTrial));
    }

    public function test_determine_tier_priority_levels(): void
    {
        // Tier 1: Pro & Not Connected (can connect)
        $this->assertSame(1, $this->service->determineTier(isPro: true, isConnected: false, isRequested: false, connectionStatus: null));

        // Tier 2: Pro & Requested (pending)
        $this->assertSame(2, $this->service->determineTier(isPro: true, isConnected: false, isRequested: true, connectionStatus: 'pending_sent'));
        $this->assertSame(2, $this->service->determineTier(isPro: true, isConnected: false, isRequested: false, connectionStatus: 'pending_received'));

        // Tier 3: Pro & Connected
        $this->assertSame(3, $this->service->determineTier(isPro: true, isConnected: true, isRequested: false, connectionStatus: 'connected'));

        // Tier 4: Non-Pro & Not Connected
        $this->assertSame(4, $this->service->determineTier(isPro: false, isConnected: false, isRequested: false, connectionStatus: null));

        // Tier 5: Non-Pro & Requested
        $this->assertSame(5, $this->service->determineTier(isPro: false, isConnected: false, isRequested: true, connectionStatus: 'pending_sent'));

        // Tier 6: Non-Pro & Connected
        $this->assertSame(6, $this->service->determineTier(isPro: false, isConnected: true, isRequested: false, connectionStatus: 'connected'));
    }
}
