<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('page_groups')) {
            Schema::create('page_groups', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name', 100);
                $table->string('slug', 100)->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('page_group_items')) {
            Schema::create('page_group_items', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('page_group_id');
                $table->uuid('page_id');
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('page_group_id')->references('id')->on('page_groups')->cascadeOnDelete();
                $table->foreign('page_id')->references('id')->on('admin_pages')->cascadeOnDelete();
                $table->unique(['page_group_id', 'page_id']);
            });
        }

        if (! Schema::hasTable('role_page_groups')) {
            Schema::create('role_page_groups', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('role_id');
                $table->uuid('page_group_id');
                $table->timestamps();

                $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
                $table->foreign('page_group_id')->references('id')->on('page_groups')->cascadeOnDelete();
                $table->unique(['role_id', 'page_group_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_page_groups');
        Schema::dropIfExists('page_group_items');
        Schema::dropIfExists('page_groups');
    }
};
