<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CircleMember;
use App\Models\Role;
use Illuminate\Support\Str;

class CircleMemberRoleSyncTest extends \Tests\TestCase
{
    public function test_setting_role_key_syncs_role_id(): void
    {
        $roleId = (string) Str::uuid();
        $role = new Role([
            'key' => 'vice_chair',
            'name' => 'Vice Chair',
        ]);
        $role->id = $roleId;

        Role::shouldReceive('find')
            ->with($roleId)
            ->andReturn($role);

        Role::shouldReceive('mustIdByKey')
            ->with('vice_chair')
            ->andReturn($roleId);

        $member = new CircleMember([
            'circle_id' => (string) Str::uuid(),
            'user_id' => (string) Str::uuid(),
            'role' => 'vice_chair',
            'status' => 'approved',
        ]);

        $member->save();

        $this->assertEquals('vice_chair', $member->role);
        $this->assertEquals($roleId, $member->role_id);
    }

    public function test_setting_role_id_syncs_role_key(): void
    {
        $roleId = (string) Str::uuid();
        $role = new Role([
            'key' => 'chair',
            'name' => 'Chair',
        ]);
        $role->id = $roleId;

        Role::shouldReceive('find')
            ->with($roleId)
            ->andReturn($role);

        $member = new CircleMember([
            'circle_id' => (string) Str::uuid(),
            'user_id' => (string) Str::uuid(),
            'role_id' => $roleId,
            'status' => 'approved',
        ]);

        $member->save();

        $this->assertEquals('chair', $member->role);
        $this->assertEquals($roleId, $member->role_id);
    }

    public function test_setting_role_to_role_uuid_syncs_both_fields(): void
    {
        $roleId = (string) Str::uuid();
        $role = new Role([
            'key' => 'secretary',
            'name' => 'Secretary',
        ]);
        $role->id = $roleId;

        Role::shouldReceive('find')
            ->with($roleId)
            ->andReturn($role);

        $member = new CircleMember([
            'circle_id' => (string) Str::uuid(),
            'user_id' => (string) Str::uuid(),
            'role' => $roleId,
            'status' => 'approved',
        ]);

        $member->save();

        $this->assertEquals('secretary', $member->role);
        $this->assertEquals($roleId, $member->role_id);
    }
}
