<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disable transaction block to allow ALTER TYPE ... ADD VALUE on Postgres.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Rename columns and add new eed_user_id column in circles table
        Schema::table('circles', function (Blueprint $table) {
            if (Schema::hasColumn('circles', 'founder_user_id')) {
                $table->renameColumn('founder_user_id', 'circle_founder_user_id');
            }
            if (Schema::hasColumn('circles', 'director_user_id')) {
                $table->renameColumn('director_user_id', 'circle_director_user_id');
            }
            if (! Schema::hasColumn('circles', 'eed_user_id')) {
                $table->foreignUuid('eed_user_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        // 2. Modify database enums for PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            if ($this->enumValExists('circle_member_role_enum', 'director')) {
                DB::statement("ALTER TYPE circle_member_role_enum RENAME VALUE 'director' TO 'circle_director'");
            }
            if ($this->enumValExists('circle_member_role_enum', 'founder')) {
                DB::statement("ALTER TYPE circle_member_role_enum RENAME VALUE 'founder' TO 'circle_founder'");
            }

            $this->addEnumVal('circle_member_role_enum', 'industry_director');
            $this->addEnumVal('circle_member_role_enum', 'ded');
            $this->addEnumVal('circle_member_role_enum', 'eed');

            if ($this->enumValExists('admin_role_key_enum', 'director')) {
                DB::statement("ALTER TYPE admin_role_key_enum RENAME VALUE 'director' TO 'circle_director'");
            }
            if ($this->enumValExists('admin_role_key_enum', 'founder')) {
                DB::statement("ALTER TYPE admin_role_key_enum RENAME VALUE 'founder' TO 'circle_founder'");
            }

            $this->addEnumVal('admin_role_key_enum', 'eed');
        }

        // 3. Update records in roles table
        if (DB::getDriverName() === 'pgsql') {
            DB::table('roles')
                ->where('key', 'circle_director')
                ->update([
                    'name' => 'Circle Director',
                ]);
            DB::table('roles')
                ->where('key', 'circle_founder')
                ->update([
                    'name' => 'Circle Founder',
                ]);
        } else {
            DB::table('roles')
                ->where('key', 'director')
                ->update([
                    'key' => 'circle_director',
                    'name' => 'Circle Director',
                ]);
            DB::table('roles')
                ->where('key', 'founder')
                ->update([
                    'key' => 'circle_founder',
                    'name' => 'Circle Founder',
                ]);
        }

        if (! DB::table('roles')->where('key', 'eed')->exists()) {
            DB::table('roles')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'key' => 'eed',
                'name' => 'EED',
                'description' => 'Executive Executive Director',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Update role values in circle_members table
        if (DB::getDriverName() !== 'pgsql') {
            DB::table('circle_members')
                ->where('role', 'director')
                ->update(['role' => 'circle_director']);

            DB::table('circle_members')
                ->where('role', 'founder')
                ->update(['role' => 'circle_founder']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Rename columns and drop eed_user_id in circles table
        Schema::table('circles', function (Blueprint $table) {
            if (Schema::hasColumn('circles', 'eed_user_id')) {
                $table->dropForeign(['eed_user_id']);
                $table->dropColumn('eed_user_id');
            }
            if (Schema::hasColumn('circles', 'circle_founder_user_id')) {
                $table->renameColumn('circle_founder_user_id', 'founder_user_id');
            }
            if (Schema::hasColumn('circles', 'circle_director_user_id')) {
                $table->renameColumn('circle_director_user_id', 'director_user_id');
            }
        });

        // 2. Revert role values in circle_members table
        if (DB::getDriverName() !== 'pgsql') {
            DB::table('circle_members')
                ->where('role', 'circle_director')
                ->update(['role' => 'director']);

            DB::table('circle_members')
                ->where('role', 'circle_founder')
                ->update(['role' => 'founder']);
        }

        // 3. Revert records in roles table
        if (DB::getDriverName() === 'pgsql') {
            DB::table('roles')
                ->where('key', 'director')
                ->update([
                    'name' => 'Circle Director',
                ]);
            DB::table('roles')
                ->where('key', 'founder')
                ->update([
                    'name' => 'Circle Founder',
                ]);
        } else {
            DB::table('roles')
                ->where('key', 'circle_director')
                ->update([
                    'key' => 'director',
                    'name' => 'Circle Director',
                ]);
            DB::table('roles')
                ->where('key', 'circle_founder')
                ->update([
                    'key' => 'founder',
                    'name' => 'Circle Founder',
                ]);
        }

        DB::table('roles')->where('key', 'eed')->delete();

        // 4. PostgreSQL enums revert
        if (DB::getDriverName() === 'pgsql') {
            if ($this->enumValExists('circle_member_role_enum', 'circle_director')) {
                DB::statement("ALTER TYPE circle_member_role_enum RENAME VALUE 'circle_director' TO 'director'");
            }
            if ($this->enumValExists('circle_member_role_enum', 'circle_founder')) {
                DB::statement("ALTER TYPE circle_member_role_enum RENAME VALUE 'circle_founder' TO 'founder'");
            }
            if ($this->enumValExists('admin_role_key_enum', 'circle_director')) {
                DB::statement("ALTER TYPE admin_role_key_enum RENAME VALUE 'circle_director' TO 'director'");
            }
            if ($this->enumValExists('admin_role_key_enum', 'circle_founder')) {
                DB::statement("ALTER TYPE admin_role_key_enum RENAME VALUE 'circle_founder' TO 'founder'");
            }
        }
    }

    /**
     * Helper to add value to PostgreSQL enum if not exists.
     */
    private function addEnumVal(string $enumName, string $val): void
    {
        if (! $this->enumValExists($enumName, $val)) {
            DB::statement("ALTER TYPE {$enumName} ADD VALUE '{$val}'");
        }
    }

    /**
     * Helper to check if value exists in PostgreSQL enum.
     */
    private function enumValExists(string $enumName, string $val): bool
    {
        $exists = DB::select('
            SELECT 1 FROM pg_enum
            JOIN pg_type ON pg_enum.enumtypid = pg_type.oid
            WHERE pg_type.typname = :enum
              AND pg_enum.enumlabel = :val
        ', ['enum' => $enumName, 'val' => $val]);

        return ! empty($exists);
    }
};
