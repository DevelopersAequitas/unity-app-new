<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_pages')) {
            return;
        }

        Schema::create('admin_pages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('module_id');
            $table->string('name', 100);
            $table->string('route_name', 255);
            $table->string('slug', 100);
            $table->string('icon', 50)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('module_id')->references('id')->on('admin_modules')->cascadeOnDelete();
            $table->index('route_name');
            $table->index('module_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_pages');
    }
};
