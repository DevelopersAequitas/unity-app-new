<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Resources\CircleResource;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class CircleDetailsLeadershipTest extends TestCase
{
    public function test_circle_resource_returns_leadership_team_and_regional_leaders_structure(): void
    {
        $circle = new Circle([
            'name' => 'Leadership Test Circle',
            'slug' => 'leadership-test-circle',
            'status' => 'active',
            'type' => 'public',
            'calendar' => [
                'leadership_team' => [
                    'chair' => [
                        'name' => 'John Doe',
                        'designation' => 'Chair',
                        'email' => 'chair@example.com',
                    ],
                    'business_growth_committee_chair' => [
                        'name' => 'Sara Growth',
                        'designation' => 'Business Growth Committee Chair',
                    ],
                    'membership_growth_committee_chair' => [
                        'name' => 'Mark Member',
                        'designation' => 'Membership Growth Committee Chair',
                    ],
                    'events_impacts_committee_chair' => [
                        'name' => 'Elena Events',
                        'designation' => 'Events & Impacts Committee Chair',
                    ],
                    'power_house_chair_1' => [
                        'name' => 'Alice Smith',
                        'designation' => 'Power House Chair 1',
                    ],
                    'power_house_chair_2' => [
                        'name' => 'Bob Jones',
                        'designation' => 'Power House Chair 2',
                    ],
                    'power_house_chair_3' => [
                        'name' => 'Charlie Brown',
                        'designation' => 'Power House Chair 3',
                    ],
                ],
                'regional_leaders' => [
                    [
                        'name' => 'David Regional',
                        'designation' => 'Regional Director',
                        'region' => 'North Region',
                        'chapter' => 'Alpha Chapter',
                        'training_info' => 'Level 2 Certified',
                    ],
                ],
            ],
        ]);
        $circle->id = 'circle-123';
        $circle->setRelation('members', collect());

        $resourceArray = (new CircleResource($circle))->toArray(Request::create('/'));

        $this->assertArrayHasKey('circle_leaders', $resourceArray);
        $this->assertArrayNotHasKey('leadership_team', $resourceArray);
        $this->assertSame('Chair', data_get($resourceArray, 'circle_leaders.chair.designation'));
        $this->assertSame('Sara Growth', data_get($resourceArray, 'circle_leaders.business_growth_committee_chair.name'));
        $this->assertSame('Mark Member', data_get($resourceArray, 'circle_leaders.membership_growth_committee_chair.name'));
        $this->assertSame('Elena Events', data_get($resourceArray, 'circle_leaders.events_impacts_committee_chair.name'));
        $this->assertSame('Alice Smith', data_get($resourceArray, 'circle_leaders.power_house_chair_1.name'));
        $this->assertSame('Bob Jones', data_get($resourceArray, 'circle_leaders.power_house_chair_2.name'));
        $this->assertSame('Charlie Brown', data_get($resourceArray, 'circle_leaders.power_house_chair_3.name'));
        $this->assertSame('North Region', data_get($resourceArray, 'regional_leaders.0.region'));
        $this->assertSame('Alpha Chapter', data_get($resourceArray, 'regional_leaders.0.chapter'));
    }

    public function test_circle_resource_resolves_members_and_handles_empty_leadership_gracefully(): void
    {
        $circle = new Circle([
            'name' => 'Empty Leadership Circle',
            'slug' => 'empty-leadership-circle',
            'status' => 'active',
            'type' => 'public',
        ]);
        $circle->id = 'circle-456';
        $circle->setRelation('members', collect());

        $resourceArray = (new CircleResource($circle))->toArray(Request::create('/'));

        $this->assertArrayHasKey('circle_leaders', $resourceArray);
        $this->assertArrayNotHasKey('leadership_team', $resourceArray);
        $this->assertNull(data_get($resourceArray, 'circle_leaders.chair'));
        $this->assertSame([], data_get($resourceArray, 'circle_leaders.power_house_chairs'));
        $this->assertSame([], data_get($resourceArray, 'regional_leaders'));
    }

    public function test_circle_resource_extracts_leadership_from_loaded_members(): void
    {
        $chairUser = new User([
            'first_name' => 'Jane',
            'last_name' => 'Leader',
            'display_name' => 'Jane Leader',
            'email' => 'jane@example.com',
        ]);
        $chairUser->id = 'user-chair';

        $chairMember = new CircleMember([
            'role' => 'chair',
            'meta' => ['designation' => 'Business Growth Committee Chair'],
        ]);
        $chairMember->setRelation('user', $chairUser);

        $regUser = new User([
            'first_name' => 'Robert',
            'last_name' => 'Regional',
            'display_name' => 'Robert Regional',
            'email' => 'robert@example.com',
        ]);
        $regUser->id = 'user-reg';

        $regMember = new CircleMember([
            'role' => 'regional_leader',
            'meta' => [
                'designation' => 'Regional Director',
                'region' => 'West Region',
                'chapter' => 'Beta Chapter',
                'training_info' => 'Advanced Leadership',
            ],
        ]);
        $regMember->setRelation('user', $regUser);

        $circle = new Circle([
            'name' => 'Member Leadership Circle',
            'slug' => 'member-leadership-circle',
        ]);
        $circle->id = 'circle-789';
        $circle->setRelation('members', collect([$chairMember, $regMember]));

        $resourceArray = (new CircleResource($circle))->toArray(Request::create('/'));

        $this->assertSame('Jane Leader', data_get($resourceArray, 'circle_leaders.chair.name'));
        $this->assertSame('Business Growth Committee Chair', data_get($resourceArray, 'circle_leaders.chair.designation'));
        $this->assertSame('Robert Regional', data_get($resourceArray, 'regional_leaders.0.name'));
        $this->assertSame('West Region', data_get($resourceArray, 'regional_leaders.0.region'));
    }

    public function test_sync_leadership_from_members_handles_null_circle_gracefully(): void
    {
        Circle::syncLeadershipFromMembers('non-existent-id');
        $this->assertTrue(true);
    }
}
