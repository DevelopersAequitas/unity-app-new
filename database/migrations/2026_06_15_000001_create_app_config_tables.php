<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_branding_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('app_key')->unique()->default('greenpreneur');
            $table->string('app_name')->default('Greenpreneur');
            $table->text('app_logo_url')->nullable();
            $table->text('splash_logo_url')->nullable();
            $table->string('primary_color')->default('#2E7D32');
            $table->string('secondary_color')->default('#81C784');
            $table->string('accent_color')->default('#FFC107');
            $table->string('splash_bg_color')->default('#FFFFFF');
            $table->string('button_color')->default('#2E7D32');
            $table->string('text_color')->default('#212121');
            $table->text('playstore_url')->nullable();
            $table->text('appstore_url')->nullable();
            $table->text('website_url')->nullable()->default('https://greenpreneur.in');
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('app_labels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('label_key')->unique();
            $table->text('label_value');
            $table->string('group_name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('app_feature_toggles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('feature_key')->unique();
            $table->string('feature_name');
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('app_navigation_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('menu_type');
            $table->string('item_key');
            $table->string('label_key')->nullable();
            $table->string('display_label');
            $table->string('icon')->nullable();
            $table->string('route_name')->nullable();
            $table->string('feature_key')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();
            $table->unique(['menu_type', 'item_key']);
            $table->index(['menu_type', 'is_enabled', 'sort_order']);
            $table->index('feature_key');
        });

        Schema::create('app_dashboard_widgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('widget_key')->unique();
            $table->string('widget_name');
            $table->string('label_key')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('app_social_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('platform')->unique();
            $table->string('display_name');
            $table->text('url')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('app_membership_labels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('membership_key')->unique();
            $table->string('display_label');
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_membership_labels');
        Schema::dropIfExists('app_social_links');
        Schema::dropIfExists('app_dashboard_widgets');
        Schema::dropIfExists('app_navigation_items');
        Schema::dropIfExists('app_feature_toggles');
        Schema::dropIfExists('app_labels');
        Schema::dropIfExists('app_branding_settings');
    }
};
