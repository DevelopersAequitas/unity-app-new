<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('circle_join_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('circle_join_requests', 'ded_approval_status')) {
                $table->string('ded_approval_status', 50)->default('pending');
            }
            if (! Schema::hasColumn('circle_join_requests', 'ded_approved_by')) {
                $table->uuid('ded_approved_by')->nullable();
            }
            if (! Schema::hasColumn('circle_join_requests', 'ded_approved_at')) {
                $table->timestamp('ded_approved_at')->nullable();
            }
        });

        // Safely drop any incorrect foreign key constraints using standard PostgreSQL DO blocks
        DB::statement("
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'circle_join_requests_ded_approved_by_fk'
                ) THEN
                    ALTER TABLE circle_join_requests DROP CONSTRAINT circle_join_requests_ded_approved_by_fk;
                END IF;
            END $$;
        ");

        DB::statement("
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'circle_join_requests_ded_approved_by_foreign'
                ) THEN
                    ALTER TABLE circle_join_requests DROP CONSTRAINT circle_join_requests_ded_approved_by_foreign;
                END IF;
            END $$;
        ");

        // Add the correct constraint referencing users table
        Schema::table('circle_join_requests', function (Blueprint $table): void {
            $table->foreign('ded_approved_by', 'circle_join_requests_ded_approved_by_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('circle_join_requests', function (Blueprint $table): void {
            DB::statement("
                DO $$
                BEGIN
                    IF EXISTS (
                        SELECT 1 FROM pg_constraint WHERE conname = 'circle_join_requests_ded_approved_by_foreign'
                    ) THEN
                        ALTER TABLE circle_join_requests DROP CONSTRAINT circle_join_requests_ded_approved_by_foreign;
                    END IF;
                END $$;
            ");
        });
    }
};
