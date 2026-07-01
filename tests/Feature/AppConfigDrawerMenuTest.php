<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\AppConfigController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class AppConfigDrawerMenuTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('app_navigation_items');
        Schema::dropIfExists('app_icon_assets');
        Schema::create('app_navigation_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('app_instance_id');
            $table->string('menu_type');
            $table->string('item_key');
            $table->string('feature_key')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('app_icon_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('app_instance_id');
            $table->string('icon_key', 150);
            $table->string('icon_name')->nullable();
            $table->string('icon_group', 100)->nullable();
            $table->string('source_type', 50)->nullable();
            $table->string('icon_library', 100)->nullable();
            $table->string('default_icon')->nullable();
            $table->string('selected_icon')->nullable();
            $table->text('icon_url')->nullable();
            $table->text('selected_icon_url')->nullable();
            $table->string('fallback_asset')->nullable();
            $table->string('feature_key', 100)->nullable();
            $table->string('menu_key', 100)->nullable();
            $table->string('screen_name', 150)->nullable();
            $table->string('usage_location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function test_drawer_menu_uses_saved_navigation_enabled_state_over_icon_defaults(): void
    {
        $appInstanceId = '00000000-0000-0000-0000-000000000001';

        foreach ([
            ['drawer_circulars', 'Circulars', 'circulars', false, 1],
            ['drawer_gallery', 'Gallery', 'gallery', true, 2],
            ['drawer_videos', 'Videos', 'videos', false, 3],
            ['drawer_meeting_schedule', 'Meeting Schedule', 'meeting_schedule', true, 4],
            ['drawer_invoices', 'Invoices', 'invoices', false, 5],
        ] as [$iconKey, $iconName, $menuKey, $isActive, $sortOrder]) {
            DB::table('app_icon_assets')->insert([
                'id' => fake()->uuid(),
                'app_instance_id' => $appInstanceId,
                'icon_key' => $iconKey,
                'icon_name' => $iconName,
                'icon_group' => 'drawer_menu',
                'source_type' => 'iconsax',
                'icon_library' => 'Iconsax',
                'default_icon' => 'Iconsax.stub',
                'feature_key' => $menuKey,
                'menu_key' => $menuKey,
                'screen_name' => 'HomeDrawer',
                'usage_location' => 'Side Drawer / More Menu',
                'is_active' => true,
                'sort_order' => $sortOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('app_navigation_items')->insert([
                'id' => fake()->uuid(),
                'app_instance_id' => $appInstanceId,
                'menu_type' => 'drawer',
                'item_key' => $menuKey,
                'feature_key' => $menuKey,
                'is_enabled' => $isActive,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $method = new ReflectionMethod(AppConfigController::class, 'icons');
        $method->setAccessible(true);
        $icons = $method->invoke(null, $appInstanceId);
        $drawerMenu = collect($icons['drawer_menu'])->keyBy('menu_key');

        $this->assertFalse($drawerMenu['circulars']['is_active']);
        $this->assertTrue($drawerMenu['gallery']['is_active']);
        $this->assertFalse($drawerMenu['videos']['is_active']);
        $this->assertTrue($drawerMenu['meeting_schedule']['is_active']);
        $this->assertFalse($drawerMenu['invoices']['is_active']);
    }
}
