<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'membership_starts_at')) {
                $table->timestamp('membership_starts_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'membership_ends_at')) {
                $table->timestamp('membership_ends_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'approval_status')) {
                $table->string('approval_status')->nullable()->default('pending');
            }
        });

        if (! Schema::hasColumn('users', 'membership_status') && ! Schema::hasColumn('users', 'membership_type')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('membership_type')->nullable();
            });
        }

        $membershipColumn = Schema::hasColumn('users', 'membership_status') ? 'membership_status' : (Schema::hasColumn('users', 'membership_type') ? 'membership_type' : null);

        if ($membershipColumn !== null) {
            $this->ensureOnlyUnityPeerEnumValue();
            $this->normalizeMembershipValue($membershipColumn, 'Only_Unity_Peer', 'only_unity_peer');
            $this->normalizeMembershipValue($membershipColumn, 'Only Unity Peer', 'only_unity_peer');
            $this->normalizeMembershipValue($membershipColumn, 'Circle Peer', 'circle_peer');
            $this->normalizeMembershipValue($membershipColumn, 'Multi Circle Peer', 'multi_circle_peer');
            $this->normalizeMembershipValue($membershipColumn, 'Free_peer', 'free_peer');
            $this->normalizeMembershipValue($membershipColumn, 'Free_trial_peer', 'free_trial_peer');

            DB::statement("CREATE INDEX IF NOT EXISTS users_{$membershipColumn}_index ON users ({$membershipColumn})");
        }

        DB::statement('CREATE INDEX IF NOT EXISTS users_membership_ends_at_index ON users (membership_ends_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS users_approval_status_index ON users (approval_status)');
    }

    private function ensureOnlyUnityPeerEnumValue(): void
    {
        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_type WHERE typname = 'membership_status_enum') THEN
        ALTER TYPE membership_status_enum ADD VALUE IF NOT EXISTS 'only_unity_peer';
    END IF;
END $$;
SQL);

    }

    private function normalizeMembershipValue(string $membershipColumn, string $legacyValue, string $normalizedValue): void
    {
        DB::table('users')
            ->whereRaw("{$membershipColumn}::text = ?", [$legacyValue])
            ->update([$membershipColumn => $normalizedValue]);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_approval_status_index');
        DB::statement('DROP INDEX IF EXISTS users_membership_ends_at_index');
        DB::statement('DROP INDEX IF EXISTS users_membership_status_index');
        DB::statement('DROP INDEX IF EXISTS users_membership_type_index');
    }
};
