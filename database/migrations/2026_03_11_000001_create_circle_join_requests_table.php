<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("DO $$ BEGIN
            CREATE TYPE circle_join_request_status_enum AS ENUM (
                'pending_cd_approval',
                'pending_id_approval',
                'pending_circle_fee',
                'circle_member',
                'rejected_by_cd',
                'rejected_by_id',
                'cancelled'
            );
        EXCEPTION WHEN duplicate_object THEN null; END $$;");

        Schema::create('circle_join_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('circle_id')->constrained('circles')->cascadeOnDelete();
            $table->text('reason_for_joining')->nullable();
            $table->enum('status', [
                'pending_cd_approval',
                'pending_id_approval',
                'pending_circle_fee',
                'circle_member',
                'rejected_by_cd',
                'rejected_by_id',
                'cancelled',
            ])->default('pending_cd_approval');
            $table->timestamp('requested_at')->nullable();
            $table->foreignUuid('cd_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cd_approved_at')->nullable();
            $table->foreignUuid('cd_rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cd_rejected_at')->nullable();
            $table->text('cd_rejection_reason')->nullable();
            $table->foreignUuid('id_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('id_approved_at')->nullable();
            $table->foreignUuid('id_rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('id_rejected_at')->nullable();
            $table->text('id_rejection_reason')->nullable();
            $table->timestamp('fee_marked_at')->nullable();
            $table->timestamp('fee_paid_at')->nullable();
            $table->jsonb('notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('circle_id');
            $table->index('status');
            $table->index(['circle_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        DB::statement("ALTER TABLE circle_join_requests ALTER COLUMN status TYPE circle_join_request_status_enum USING status::circle_join_request_status_enum");
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_join_requests');
        DB::statement('DROP TYPE IF EXISTS circle_join_request_status_enum');
    }
};
