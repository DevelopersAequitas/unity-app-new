<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coin_claim_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('coin_claim_requests', 'reviewed_by_admin_id')) {
                $table->uuid('reviewed_by_admin_id')->nullable();
            }

            if (! Schema::hasColumn('coin_claim_requests', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable();
            }

            if (! Schema::hasColumn('coin_claim_requests', 'admin_note')) {
                $table->text('admin_note')->nullable();
            }
        });

        DB::statement('CREATE INDEX IF NOT EXISTS coin_claim_requests_reviewed_by_admin_id_idx ON coin_claim_requests (reviewed_by_admin_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS coin_claim_requests_reviewed_at_idx ON coin_claim_requests (reviewed_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS coin_claim_requests_reviewed_by_admin_id_idx');
        DB::statement('DROP INDEX IF EXISTS coin_claim_requests_reviewed_at_idx');

        Schema::table('coin_claim_requests', function (Blueprint $table): void {
            $dropColumns = [];

            if (Schema::hasColumn('coin_claim_requests', 'reviewed_by_admin_id')) {
                $dropColumns[] = 'reviewed_by_admin_id';
            }

            if (Schema::hasColumn('coin_claim_requests', 'reviewed_at')) {
                $dropColumns[] = 'reviewed_at';
            }

            if (Schema::hasColumn('coin_claim_requests', 'admin_note')) {
                $dropColumns[] = 'admin_note';
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
