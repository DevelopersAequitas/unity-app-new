<?php

declare(strict_types=1);

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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'android_fcm_token')) {
                $table->string('android_fcm_token')->nullable();
            }
            if (! Schema::hasColumn('users', 'ios_fcm_token')) {
                $table->string('ios_fcm_token')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'android_fcm_token')) {
                $table->dropColumn('android_fcm_token');
            }
            if (Schema::hasColumn('users', 'ios_fcm_token')) {
                $table->dropColumn('ios_fcm_token');
            }
        });
    }
};
