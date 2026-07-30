<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\VisitorRegistration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisitorRegistrationFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->integer('life_impacted_count')->default(0);
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('users', 'life_impacted_count')) {
            Schema::table('users', function (Blueprint $table) {
                $table->integer('life_impacted_count')->default(0);
            });
        }

        if (! Schema::hasTable('visitor_registrations')) {
            Schema::create('visitor_registrations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('event_type');
                $table->string('event_name');
                $table->timestamp('event_date')->nullable();
                $table->string('visitor_full_name');
                $table->string('visitor_mobile');
                $table->string('visitor_email')->nullable();
                $table->string('visitor_city');
                $table->string('visitor_business');
                $table->unsignedBigInteger('visitor_business_category_id')->nullable();
                $table->string('visitor_business_category', 150)->nullable();
                $table->string('visitor_business_website', 255)->nullable();
                $table->string('invited_by_type', 50)->nullable();
                $table->uuid('invited_by_user_id')->nullable();
                $table->string('how_known')->nullable();
                $table->text('note')->nullable();
                $table->string('status')->default('pending');
                $table->uuid('reviewed_by_admin_user_id')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->boolean('coins_awarded')->default(false);
                $table->timestamp('coins_awarded_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('life_impact_histories')) {
            Schema::create('life_impact_histories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('triggered_by_user_id')->nullable();
                $table->string('activity_type')->nullable();
                $table->string('activity_id')->nullable();
                $table->integer('impact_value')->default(1);
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_user_can_submit_visitor_registration_form(): void
    {
        $user = User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'life_impacted_count' => 0,
        ]);

        $payload = [
            'event_type' => 'physical',
            'event_name' => 'Unity Meetup',
            'event_date' => '2026-02-01',
            'visitor_full_name' => 'Visitor Test',
            'visitor_mobile' => '7777777777',
            'visitor_email' => 'visitor@example.com',
            'visitor_city' => 'Ahmedabad',
            'visitor_business' => 'Textile',
            'how_known' => 'friend',
            'note' => 'Please welcome this visitor.',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/forms/register-visitor', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Visitor registration submitted successfully.',
            ]);

        $this->assertDatabaseHas('visitor_registrations', [
            'user_id' => $user->id,
            'visitor_full_name' => 'Visitor Test',
            'visitor_mobile' => '7777777777',
            'visitor_email' => 'visitor@example.com',
            'visitor_city' => 'Ahmedabad',
            'visitor_business' => 'Textile',
            'how_known' => 'friend',
            'note' => 'Please welcome this visitor.',
        ]);
    }

    public function test_user_can_fetch_my_visitor_registrations(): void
    {
        $user = User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Test User',
            'email' => 'testuser@example.com',
        ]);

        VisitorRegistration::create([
            'user_id' => $user->id,
            'event_type' => 'physical',
            'event_name' => 'Unity Meetup',
            'event_date' => '2026-02-01',
            'visitor_full_name' => 'Visitor Test',
            'visitor_mobile' => '7777777777',
            'visitor_city' => 'Ahmedabad',
            'visitor_business' => 'Textile',
            'how_known' => 'friend',
            'note' => 'Please welcome this visitor.',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/forms/register-visitor/my');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertCount(1, $response->json('data.items'));
    }
}
