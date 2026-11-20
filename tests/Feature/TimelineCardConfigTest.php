<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TimelineCardConfig;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TimelineCardConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.connections.pgsql.database' => 'unityappbackend',
            'database.connections.pgsql.password' => '2004',
        ]);
        DB::purge('pgsql');
        Cache::flush();
    }

    public function test_public_client_can_retrieve_timeline_cards_config(): void
    {
        TimelineCardConfig::query()->updateOrCreate(
            ['id' => 1],
            [
                'profile_completion_enabled' => true,
                'you_can_help_enabled' => true,
                'events_spotlight_enabled' => true,
                'circle_vacancy_enabled' => true,
                'suggested_peers_enabled' => true,
            ]
        );

        $response = $this->getJson('/api/v1/app-config/timeline-cards');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Timeline cards configuration retrieved successfully',
                'data' => [
                    'timeline_cards' => [
                        'profile_completion_enabled' => true,
                        'you_can_help_enabled' => true,
                        'events_spotlight_enabled' => true,
                        'circle_vacancy_enabled' => true,
                        'suggested_peers_enabled' => true,
                    ],
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_update_timeline_cards_config(): void
    {
        $response = $this->putJson('/api/v1/admin/timeline-cards/config', [
            'events_spotlight_enabled' => false,
        ]);

        $response->assertStatus(401);
    }

    public function test_admin_can_update_timeline_cards_config_and_support_partial_updates(): void
    {
        $admin = new User([
            'id' => (string) Str::uuid(),
            'email' => 'admin.timeline.test@peersglobal.test',
            'first_name' => 'Admin',
            'last_name' => 'Tester',
            'display_name' => 'Admin Tester',
        ]);
        Sanctum::actingAs($admin, ['*']);

        // Set initial state
        TimelineCardConfig::query()->updateOrCreate(
            ['id' => 1],
            [
                'profile_completion_enabled' => true,
                'you_can_help_enabled' => true,
                'events_spotlight_enabled' => true,
                'circle_vacancy_enabled' => true,
                'suggested_peers_enabled' => true,
            ]
        );

        // Turn off events spotlight
        $updateResponse = $this->putJson('/api/v1/admin/timeline-cards/config', [
            'events_spotlight_enabled' => false,
        ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Timeline cards configuration updated successfully',
                'data' => [
                    'timeline_cards' => [
                        'profile_completion_enabled' => true,
                        'you_can_help_enabled' => true,
                        'events_spotlight_enabled' => false,
                        'circle_vacancy_enabled' => true,
                        'suggested_peers_enabled' => true,
                    ],
                ],
            ]);

        // Subsequent GET request reflects the change
        $getResponse = $this->getJson('/api/v1/app-config/timeline-cards');
        $getResponse->assertStatus(200)
            ->assertJsonPath('data.timeline_cards.events_spotlight_enabled', false)
            ->assertJsonPath('data.timeline_cards.profile_completion_enabled', true);
    }

    public function test_admin_update_validates_boolean_types(): void
    {
        $admin = new User([
            'id' => (string) Str::uuid(),
            'email' => 'admin.timeline.test2@peersglobal.test',
            'first_name' => 'Admin',
            'last_name' => 'Tester',
            'display_name' => 'Admin Tester',
        ]);
        Sanctum::actingAs($admin, ['*']);

        $response = $this->putJson('/api/v1/admin/timeline-cards/config', [
            'events_spotlight_enabled' => 'not-a-boolean-value',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['events_spotlight_enabled']);
    }
}
