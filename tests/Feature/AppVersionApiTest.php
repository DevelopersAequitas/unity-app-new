<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppVersion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppVersionApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('app_versions');

        Schema::create('app_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('platform');
            $table->string('latest_version');
            $table->string('min_version');
            $table->string('update_type');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_app_version_returns_configured_store_urls_and_platform_versions(): void
    {
        AppVersion::query()->create([
            'platform' => 'android',
            'latest_version' => '1.8.0',
            'min_version' => '1.8.0',
            'update_type' => 'optional',
            'is_active' => true,
        ]);

        AppVersion::query()->create([
            'platform' => 'ios',
            'latest_version' => '1.9.0',
            'min_version' => '1.8.0',
            'update_type' => 'optional',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/app/version');

        $response->assertOk()
            ->assertJson([
                'status' => true,
                'data' => [
                    'latest_version_android' => '1.8.0',
                    'latest_version_ios' => '1.9.0',
                    'min_version' => '1.8.0',
                    'update_type' => 'optional',
                    'playstore_url' => 'https://play.google.com/store/apps/details?id=com.peers.peersunity&pcampaignid=web_share',
                    'appstore_url' => 'https://apps.apple.com/in/app/peers-global-unity/id6739198477',
                ],
            ]);
    }
}
