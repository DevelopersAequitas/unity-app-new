<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workflow_approval_rules')) {
            return;
        }

        Schema::create('workflow_approval_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('module_id');
            $table->string('workflow_name', 100);
            $table->uuid('approver_role_id');
            $table->integer('step_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('module_id')->references('id')->on('admin_modules')->cascadeOnDelete();
            $table->foreign('approver_role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->index(['module_id', 'workflow_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_approval_rules');
    }
};
