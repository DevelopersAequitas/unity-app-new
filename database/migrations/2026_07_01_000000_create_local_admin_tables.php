<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('key')->unique();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_user_roles')) {
            Schema::create('admin_user_roles', function (Blueprint $table) {
                $table->uuid('user_id');
                $table->uuid('role_id');
                $table->primary(['user_id', 'role_id']);
            });
        }

        if (! Schema::hasTable('admin_login_otps')) {
            Schema::create('admin_login_otps', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('email');
                $table->string('otp_hash');
                $table->timestamp('expires_at');
                $table->timestamp('last_sent_at')->nullable();
                $table->integer('attempts')->default(0);
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
            });
        }

        // Seed default roles and admin user
        $globalAdminRoleId = (string) Str::uuid();
        $adminUserId = (string) Str::uuid();

        DB::table('roles')->insertOrIgnore([
            [
                'id' => $globalAdminRoleId,
                'name' => 'Global Admin',
                'key' => 'global_admin',
                'description' => 'Global Administrator access',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Industry Director',
                'key' => 'industry_director',
                'description' => 'Industry Director access',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'DED',
                'key' => 'ded',
                'description' => 'District Executive Director access',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Circle Leader',
                'key' => 'circle_leader',
                'description' => 'Circle Leader access',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('admin_users')->insertOrIgnore([
            'id' => $adminUserId,
            'name' => 'Jay Kanzariya',
            'email' => 'work.jaykanzariya@gmail.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_user_roles')->insertOrIgnore([
            'user_id' => $adminUserId,
            'role_id' => $globalAdminRoleId,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_roles');
        Schema::dropIfExists('admin_login_otps');
        Schema::dropIfExists('admin_users');
        Schema::dropIfExists('roles');
    }
};
