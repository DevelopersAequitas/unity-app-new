<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_approval_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('module_id');
            $table->string('workflow_action');       // e.g. 'approve_membership', 'approve_event'
            $table->uuid('approver_role_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['module_id', 'workflow_action', 'approver_role_id'], 'war_unique');

            $table->foreign('module_id')
                ->references('id')
                ->on('admin_modules')
                ->cascadeOnDelete();

            $table->foreign('approver_role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_approval_rules');
    }
};
