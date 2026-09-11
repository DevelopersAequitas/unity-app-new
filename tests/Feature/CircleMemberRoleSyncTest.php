<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CircleMemberRoleSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_member_role_syncs_circle_leadership_columns(): void
    {
        $circle = Circle::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Circle',
            'slug' => 'test-circle-'.Str::random(5),
            'status' => 'active',
            'type' => 'public',
        ]);

        $user1 = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.sync@example.com',
            'password' => bcrypt('password'),
        ]);

        $user2 = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.sync@example.com',
            'password' => bcrypt('password'),
        ]);

        $dedRole = Role::query()->firstOrCreate(
            ['key' => 'ded'],
            ['id' => (string) Str::uuid(), 'name' => 'DED', 'description' => 'DED Role']
        );

        $founderRole = Role::query()->firstOrCreate(
            ['key' => 'circle_founder'],
            ['id' => (string) Str::uuid(), 'name' => 'Circle Founder', 'description' => 'Circle Founder Role']
        );

        $member1 = CircleMember::query()->create([
            'id' => (string) Str::uuid(),
            'circle_id' => $circle->id,
            'user_id' => $user1->id,
            'role' => 'ded',
            'role_id' => $dedRole->id,
            'status' => 'approved',
        ]);

        $circle->refresh();
        $this->assertEquals($user1->id, $circle->ded_user_id);

        // Update member1 role to founder
        $member1->role = 'circle_founder';
        $member1->role_id = $founderRole->id;
        $member1->save();

        $circle->refresh();
        $this->assertNull($circle->ded_user_id);
        $this->assertEquals($user1->id, $circle->circle_founder_user_id);

        // Add member2 as DED
        CircleMember::query()->create([
            'id' => (string) Str::uuid(),
            'circle_id' => $circle->id,
            'user_id' => $user2->id,
            'role' => 'ded',
            'role_id' => $dedRole->id,
            'status' => 'approved',
        ]);

        $circle->refresh();
        $this->assertEquals($user2->id, $circle->ded_user_id);
        $this->assertEquals($user1->id, $circle->circle_founder_user_id);
    }
}
