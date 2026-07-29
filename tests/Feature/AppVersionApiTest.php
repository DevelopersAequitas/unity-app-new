<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppVersionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_version_returns_configured_store_urls(): void
    {
        AppVersion::query()->create([
            'platform' => 'android',
            'latest_version' => '1.8.0',
            'min_version' => '1.8.0',
            'update_type' => 'optional',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/app/version');

        $response->assertOk()
            ->assertJson([
                'status' => true,
                'data' => [
                    'latest_version' => '1.8.0',
                    'min_version' => '1.8.0',
                    'update_type' => 'optional',
                    'playstore_url' => 'https://play.google.com/store/apps/details?id=com.peers.peersunity&pcampaignid=web_share',
                    'appstore_url' => 'https://apps.apple.com/in/app/peers-global-unity/id6739198477',
                ],
            ]);
    }
}
