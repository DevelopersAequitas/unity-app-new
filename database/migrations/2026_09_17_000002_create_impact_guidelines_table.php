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
        Schema::create('impact_guidelines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title')->default('Your Life Impact Score');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('action');
            $table->string('category')->default('Business & Growth');
            $table->unsignedInteger('impact_value')->default(1);
            $table->string('impact_unit')->default('Lives');
            $table->integer('display_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('impact_guidelines');
    }
};
