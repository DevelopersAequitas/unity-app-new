<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // 1. Fetch current enum labels for membership_status_enum
            $enumLabels = DB::select(
                "SELECT enumlabel FROM pg_enum
                 JOIN pg_type ON pg_enum.enumtypid = pg_type.oid
                 WHERE pg_type.typname = 'membership_status_enum'"
            );

            $validEnumLabels = collect($enumLabels)->pluck('enumlabel')->toArray();

            $candidates = ['Only Green Peer', 'only_green_peer', 'only_unity_peer', 'unity_peer'];

            // Filter out candidates that do not exist in the enum type in the database
            $existingCandidates = array_intersect($candidates, $validEnumLabels);

            if (! empty($existingCandidates)) {
                DB::table('users')
                    ->whereIn('membership_status', $existingCandidates)
                    ->update([
                        'membership_status' => 'Only Unity Peer',
                        'updated_at' => now(),
                    ]);
            }
        }

        // 2. Ensure the app_membership_labels table doesn't have incorrect/duplicate entries
        if (Schema::hasTable('app_membership_labels')) {
            DB::table('app_membership_labels')
                ->where('membership_key', 'only_green_peer')
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We cannot easily rollback this migration since it normalizes legacy/duplicate data to a single value.
    }
};
