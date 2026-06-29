<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone', 100)->nullable()->after('country');
            }

            if (! Schema::hasColumn('users', 'utc_offset')) {
                $table->string('utc_offset', 10)->nullable()->after('timezone');
            }

            if (! Schema::hasColumn('users', 'timezone_abbreviation')) {
                $table->string('timezone_abbreviation', 10)->nullable()->after('utc_offset');
            }

            if (! Schema::hasColumn('users', 'current_local_time')) {
                $table->timestamp('current_local_time')->nullable()->after('timezone_abbreviation');
            }

            if (! Schema::hasColumn('users', 'timezone_updated_at')) {
                $table->timestamp('timezone_updated_at')->nullable()->after('current_local_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'timezone_updated_at',
                'current_local_time',
                'timezone_abbreviation',
                'utc_offset',
                'timezone',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
