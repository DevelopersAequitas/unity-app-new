<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('role_data_scope')) {
            return;
        }

        Schema::create('role_data_scope', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('role_id')->nullable();
            $table->uuid('admin_user_id')->nullable();
            $table->string('scope_type', 30);
            $table->uuid('scope_id')->nullable();
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('admin_user_id')->references('id')->on('admin_users')->cascadeOnDelete();
            $table->index(['role_id', 'scope_type']);
            $table->index(['admin_user_id', 'scope_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_data_scope');
    }
};
