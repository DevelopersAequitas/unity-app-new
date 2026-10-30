<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_ip_access_settings')) {
            Schema::create('admin_ip_access_settings', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('environment', 50)->unique('uq_admin_ip_access_settings_environment');
                $table->boolean('ip_access_enabled')->default(false);
                $table->uuid('updated_by_user_id')->nullable();
                $table->string('updated_by_email', 255)->nullable();
                $table->timestampsTz();

                $table->index(['environment', 'ip_access_enabled'], 'idx_admin_ip_access_settings_env_enabled');
            });

            $now = now();
            foreach (['production', 'development', 'local', 'testing'] as $env) {
                DB::table('admin_ip_access_settings')->updateOrInsert(
                    ['environment' => $env],
                    [
                        'id' => (string) Str::uuid(),
                        'ip_access_enabled' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        if (! Schema::hasTable('admin_ip_allowlists')) {
            Schema::create('admin_ip_allowlists', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('environment', 50)->default('production');
                $table->string('ip_address', 45);
                $table->smallInteger('ip_version')->default(4);
                $table->string('label', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->uuid('created_by_user_id')->nullable();
                $table->string('created_by_email', 255)->nullable();
                $table->uuid('updated_by_user_id')->nullable();
                $table->string('updated_by_email', 255)->nullable();
                $table->timestampsTz();

                $table->unique(['environment', 'ip_address'], 'uq_admin_ip_allowlists_env_ip');
                $table->index(['environment', 'is_active', 'ip_address'], 'idx_admin_ip_allowlists_lookup');
                $table->index(['environment', 'created_at'], 'idx_admin_ip_allowlists_created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_ip_allowlists');
        Schema::dropIfExists('admin_ip_access_settings');
    }
};

