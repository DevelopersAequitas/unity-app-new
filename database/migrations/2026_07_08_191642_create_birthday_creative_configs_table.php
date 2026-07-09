<?php

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
        Schema::create('birthday_creative_configs', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->uuid('template_file_id')->nullable();
            $table->string('background_gradient_start')->default('#FFB7B2');
            $table->string('background_gradient_end')->default('#FFD1FF');
            $table->string('text_color')->default('#333333');
            $table->timestamps();

            $table->foreign('template_file_id')->references('id')->on('files')->onDelete('set null');
        });

        // Insert initial default config record
        DB::table('birthday_creative_configs')->insert([
            'is_enabled' => true,
            'template_file_id' => null,
            'background_gradient_start' => '#8E2DE2',
            'background_gradient_end' => '#4A00E0',
            'text_color' => '#FFFFFF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('birthday_creative_configs');
    }
};
