<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_templates')) {
            Schema::create('whatsapp_templates', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('meta_template_id')->nullable()->index();
                $table->string('name')->index();
                $table->string('language', 20)->default('en_US')->index();
                $table->string('category', 50)->default('UTILITY')->index();
                $table->string('status', 50)->default('PENDING')->index();
                $table->string('quality', 50)->nullable();
                $table->string('parameter_format', 50)->default('positional');
                $table->json('components')->nullable();
                $table->json('meta_response')->nullable();
                $table->timestamp('last_synced_at')->nullable();

                // Backward-compatibility fields for legacy Resobrand/FlexiMsg templates
                $table->string('template_key')->nullable()->index();
                $table->string('template_name')->nullable();
                $table->text('webhook_url')->nullable();
                $table->string('webhook_secret')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();

                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
