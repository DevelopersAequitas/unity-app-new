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
        Schema::create('coin_guidelines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title')->default('The Coin Reward System');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('activity');
            $table->unsignedInteger('coins')->default(0);
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
        Schema::dropIfExists('coin_guidelines');
    }
};
