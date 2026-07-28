<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContactVisibilityProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('peer_id', 50)->nullable()->unique();
            $table->string('public_profile_slug')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password_hash')->nullable();
            $table->string('membership_status')->nullable();
            $table->integer('coins_balance')->nullable();
            $table->string('contact_visibility')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('title')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();
        });

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('status')->default('approved');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('paid_starts_at')->nullable();
            $table->timestamp('paid_ends_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circle_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    public function test_contact_visibility_valid_values_are_accepted_persisted_and_returned(): void
    {
        $user = User::factory()->create(['contact_visibility' => 'everyone']);
        Sanctum::actingAs($user);

        foreach (['everyone', 'connected_only', 'circle_only', 'hidden'] as $visibility) {
            $this->patchJson('/api/v1/profile', ['contact_visibility' => $visibility])
                ->assertOk()
                ->assertJsonPath('data.contact_visibility', $visibility);

            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'contact_visibility' => $visibility,
            ]);
        }
    }

    public function test_contact_visibility_invalid_value_is_rejected(): void
    {
        $user = User::factory()->create(['contact_visibility' => 'everyone']);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/profile', ['contact_visibility' => 'friends_only'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contact_visibility']);
    }

    public function test_legacy_contact_visibility_values_are_normalized_for_backward_compatibility(): void
    {
        $user = User::factory()->create(['contact_visibility' => 'everyone']);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/profile', ['contact_visibility' => 'connections'])
            ->assertOk()
            ->assertJsonPath('data.contact_visibility', 'connected_only');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'contact_visibility' => 'connected_only',
        ]);
    }

    public function test_profile_api_returns_current_contact_visibility(): void
    {
        $user = User::factory()->create(['contact_visibility' => 'circle_only']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.contact_visibility', 'circle_only');
    }
}
