<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MutualConnectionsApiTest extends TestCase
{
    public function test_authenticated_request_returns_mutual_connections(): void
    {
        $this->createSchema();

        [$authUser, $targetUser] = $this->createUserPair();
        $mutual = $this->createUser('Charlie Mutual');
        $this->connect($authUser, $mutual);
        $this->connect($targetUser, $mutual);

        Sanctum::actingAs($authUser);

        $this->getJson("/api/v1/network/mutual-connections/{$targetUser->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Mutual connections fetched successfully.')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.target_user.uuid', $targetUser->id)
            ->assertJsonPath('data.connections.0.uuid', $mutual->id)
            ->assertJsonPath('data.connections.0.name', 'Charlie Mutual');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->createSchema();

        $targetUser = $this->createUser('Target User');

        $this->getJson("/api/v1/network/mutual-connections/{$targetUser->id}")
            ->assertUnauthorized();
    }

    public function test_invalid_uuid_returns_validation_error(): void
    {
        $this->createSchema();

        Sanctum::actingAs($this->createUser('Auth User'));

        $this->getJson('/api/v1/network/mutual-connections/not-a-uuid')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_target_user_not_found_returns_404(): void
    {
        $this->createSchema();

        Sanctum::actingAs($this->createUser('Auth User'));

        $this->getJson('/api/v1/network/mutual-connections/' . Str::uuid())
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Target user not found.');
    }

    public function test_no_mutual_connections_returns_empty_payload(): void
    {
        $this->createSchema();

        [$authUser, $targetUser] = $this->createUserPair();
        $authOnly = $this->createUser('Auth Only');
        $targetOnly = $this->createUser('Target Only');
        $this->connect($authUser, $authOnly);
        $this->connect($targetUser, $targetOnly);

        Sanctum::actingAs($authUser);

        $this->getJson("/api/v1/network/mutual-connections/{$targetUser->id}")
            ->assertOk()
            ->assertJsonPath('message', 'No mutual connections found.')
            ->assertJsonPath('data.target_user', [])
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.connections', []);
    }

    public function test_multiple_mutual_connections_are_sorted_and_filtered(): void
    {
        $this->createSchema();

        [$authUser, $targetUser] = $this->createUserPair();
        $zoe = $this->createUser('Zoe Mutual');
        $amy = $this->createUser('Amy Mutual');
        $inactive = $this->createUser('Inactive Mutual', ['status' => 'inactive']);
        $pending = $this->createUser('Pending Mutual');

        foreach ([$zoe, $amy, $inactive, $pending] as $user) {
            $this->connect($authUser, $user);
        }

        $this->connect($targetUser, $zoe);
        $this->connect($targetUser, $amy);
        $this->connect($targetUser, $inactive);
        $this->connect($targetUser, $pending, false);

        Sanctum::actingAs($authUser);

        $response = $this->getJson("/api/v1/network/mutual-connections/{$targetUser->id}")
            ->assertOk()
            ->assertJsonPath('data.total', 2);

        $this->assertSame(['Amy Mutual', 'Zoe Mutual'], array_column($response->json('data.connections'), 'name'));
    }

    public function test_pagination_limits_mutual_connections(): void
    {
        $this->createSchema();

        [$authUser, $targetUser] = $this->createUserPair();

        foreach (['Amy Mutual', 'Bob Mutual', 'Cara Mutual'] as $name) {
            $mutual = $this->createUser($name);
            $this->connect($authUser, $mutual);
            $this->connect($targetUser, $mutual);
        }

        Sanctum::actingAs($authUser);

        $this->getJson("/api/v1/network/mutual-connections/{$targetUser->id}?page=2&per_page=2")
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.connections.0.name', 'Cara Mutual');
    }

    private function createSchema(): void
    {
        Schema::dropIfExists('peer_blocks');
        Schema::dropIfExists('connections');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('designation')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('password_hash')->nullable();
            $table->uuid('profile_photo_file_id')->nullable();
            $table->string('profile_photo_url')->nullable();
            $table->uuid('city_id')->nullable();
            $table->string('city')->nullable();
            $table->string('status')->nullable();
            $table->string('membership_status')->nullable();
            $table->timestamp('gdpr_deleted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
        });

        Schema::create('connections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('requester_id');
            $table->uuid('addressee_id');
            $table->boolean('is_approved')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('approved_at')->nullable();
        });

        Schema::create('peer_blocks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('blocker_user_id');
            $table->uuid('blocked_user_id');
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function createUserPair(): array
    {
        return [
            $this->createUser('Auth User'),
            $this->createUser('Target User'),
        ];
    }

    private function createUser(string $name, array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'first_name' => strtok($name, ' ') ?: $name,
            'last_name' => trim(strstr($name, ' ') ?: ''),
            'display_name' => $name,
            'email' => Str::slug($name) . '-' . Str::random(6) . '@example.com',
            'status' => 'active',
            'membership_status' => 'premium',
        ], $attributes));
    }

    private function connect(User $first, User $second, bool $approved = true): void
    {
        DB::table('connections')->insert([
            'id' => (string) Str::uuid(),
            'requester_id' => (string) $first->id,
            'addressee_id' => (string) $second->id,
            'is_approved' => $approved,
            'created_at' => now(),
            'approved_at' => $approved ? now() : null,
        ]);
    }
}
