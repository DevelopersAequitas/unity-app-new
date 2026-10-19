<?php

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
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'deactivation_remarks')) {
                $table->text('deactivation_remarks')->nullable()->after('status');
            }
            if (! Schema::hasColumn('posts', 'deactivated_by')) {
                $table->uuid('deactivated_by')->nullable()->after('deactivation_remarks');
            }
            if (! Schema::hasColumn('posts', 'deactivated_at')) {
                $table->timestamp('deactivated_at')->nullable()->after('deactivated_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $dropCols = [];
            if (Schema::hasColumn('posts', 'deactivation_remarks')) {
                $dropCols[] = 'deactivation_remarks';
            }
            if (Schema::hasColumn('posts', 'deactivated_by')) {
                $dropCols[] = 'deactivated_by';
            }
            if (Schema::hasColumn('posts', 'deactivated_at')) {
                $dropCols[] = 'deactivated_at';
            }
            if (! empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });
    }
};
