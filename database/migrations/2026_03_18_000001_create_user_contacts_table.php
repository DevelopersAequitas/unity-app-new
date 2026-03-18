<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('user_contacts')) {
            Schema::create('user_contacts', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('user_id');
                $table->string('phone_user_name', 255)->nullable();
                $table->string('contact_name', 255);
                $table->string('mobile', 20);
                $table->string('mobile_normalized', 20);
                $table->timestamps();

                $table->unique(['user_id', 'mobile_normalized']);
                $table->index('user_id');
                $table->index('mobile_normalized');
            });

            return;
        }

        if (! Schema::hasColumn('user_contacts', 'phone_user_name')) {
            Schema::table('user_contacts', function (Blueprint $table): void {
                $table->string('phone_user_name', 255)->nullable()->after('user_id');
            });
        }

        if (! Schema::hasColumn('user_contacts', 'contact_name')) {
            Schema::table('user_contacts', function (Blueprint $table): void {
                $table->string('contact_name', 255)->nullable()->after('phone_user_name');
            });

            if (Schema::hasColumn('user_contacts', 'name')) {
                DB::table('user_contacts')
                    ->whereNull('contact_name')
                    ->update([
                        'contact_name' => DB::raw("COALESCE(name, '')"),
                    ]);
            }

            DB::table('user_contacts')
                ->whereNull('contact_name')
                ->update(['contact_name' => 'Unknown']);

            Schema::table('user_contacts', function (Blueprint $table): void {
                $table->string('contact_name', 255)->nullable(false)->change();
            });
        }

        if (Schema::hasColumn('user_contacts', 'name')) {
            Schema::table('user_contacts', function (Blueprint $table): void {
                $table->dropColumn('name');
            });
        }

        if (Schema::hasColumn('user_contacts', 'device')) {
            Schema::table('user_contacts', function (Blueprint $table): void {
                $table->dropColumn('device');
            });
        }

        if (Schema::hasColumn('user_contacts', 'app_version')) {
            Schema::table('user_contacts', function (Blueprint $table): void {
                $table->dropColumn('app_version');
            });
        }

        Schema::table('user_contacts', function (Blueprint $table): void {
            $table->bigInteger('user_id')->change();
            $table->string('mobile', 20)->change();
            $table->string('mobile_normalized', 20)->change();
        });

        DB::statement('CREATE INDEX IF NOT EXISTS user_contacts_user_id_index ON user_contacts (user_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS user_contacts_mobile_normalized_index ON user_contacts (mobile_normalized)');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS user_contacts_user_id_mobile_normalized_unique ON user_contacts (user_id, mobile_normalized)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_contacts');
    }
};
