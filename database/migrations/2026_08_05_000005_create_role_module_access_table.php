<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_module_access', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('role_id');
            $table->uuid('module_id');
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('module_id')->references('id')->on('admin_modules')->cascadeOnDelete();
            $table->unique(['role_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_module_access');
    }
};
