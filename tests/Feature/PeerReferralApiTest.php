<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PeerReferralApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInMemoryDatabase();
    }

    private function setUpInMemoryDatabase(): void
    {
        Schema::dropIfExists('peer_referrals');
        Schema::dropIfExists('circles');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email', 255)->unique();
            $table->string('phone', 50)->nullable();
            $table->string('status', 50)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('peer_referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('referrer_user_id');
            $table->string('referred_name');
            $table->string('referred_phone', 50);
            $table->string('referred_email')->nullable();
            $table->string('referred_company_name')->nullable();
            $table->string('referred_designation')->nullable();
            $table->uuid('main_circle_id');
            $table->uuid('circle_id')->nullable();
            $table->string('open_category_id');
            $table->text('message')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function test_can_create_peer_referral_with_specific_circle(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'Referrer',
            'email' => 'ref_'.time().'@test.com',
            'status' => 'active',
        ]);

        $mainCircle = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'Main Circle Test',
            'slug' => 'main-circle-'.time(),
            'status' => 'active',
        ]);

        $subCircle = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'Specific Circle Test',
            'slug' => 'sub-circle-'.time(),
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $payload = [
            'referred_name' => 'Rahul Patel',
            'referred_phone' => '9876543210',
            'referred_email' => 'rahul@example.com',
            'referred_company_name' => 'ABC Enterprises',
            'referred_designation' => 'Founder',
            'main_circle_id' => $mainCircle->id,
            'circle_id' => $subCircle->id,
            'open_category_id' => (string) Str::uuid(),
            'message' => 'I would like to refer Rahul for this open category.',
        ];

        $response = $this->postJson('/api/v1/peer-referrals', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.referred_name', 'Rahul Patel')
            ->assertJsonPath('data.main_circle.id', (string) $mainCircle->id)
            ->assertJsonPath('data.circle.id', (string) $subCircle->id)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_can_create_peer_referral_with_null_circle(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'Referrer',
            'email' => 'ref_null_'.time().'@test.com',
            'status' => 'active',
        ]);

        $mainCircle = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'Main Circle Test Null',
            'slug' => 'main-circle-null-'.time(),
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $payload = [
            'referred_name' => 'Pooja Shah',
            'referred_phone' => '9876543211',
            'referred_email' => 'pooja@example.com',
            'referred_company_name' => 'XYZ Corp',
            'referred_designation' => 'Director',
            'main_circle_id' => $mainCircle->id,
            'circle_id' => null,
            'open_category_id' => (string) Str::uuid(),
            'message' => 'Main circle referral.',
        ];

        $response = $this->postJson('/api/v1/peer-referrals', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.referred_name', 'Pooja Shah')
            ->assertJsonPath('data.main_circle.id', (string) $mainCircle->id)
            ->assertJsonPath('data.circle', null)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_duplicate_referral_returns_validation_error(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'Referrer',
            'email' => 'ref_dup_'.time().'@test.com',
            'status' => 'active',
        ]);

        $mainCircle = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'Main Circle Dup',
            'slug' => 'main-circle-dup-'.time(),
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $catId = (string) Str::uuid();

        $payload = [
            'referred_name' => 'Same Person',
            'referred_phone' => '9988776655',
            'referred_email' => 'same@example.com',
            'main_circle_id' => $mainCircle->id,
            'circle_id' => null,
            'open_category_id' => $catId,
        ];

        $res1 = $this->postJson('/api/v1/peer-referrals', $payload);
        $res1->assertStatus(201);

        $res2 = $this->postJson('/api/v1/peer-referrals', $payload);
        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['referred_phone']);
    }
}
