<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_with_us_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('partner_with_us_submissions', 'first_name')) {
                $table->string('first_name', 255)->nullable()->after('id');
            }
            if (! Schema::hasColumn('partner_with_us_submissions', 'last_name')) {
                $table->string('last_name', 255)->nullable()->after('first_name');
            }
            if (Schema::hasColumn('partner_with_us_submissions', 'full_name')) {
                $table->dropColumn('full_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partner_with_us_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('partner_with_us_submissions', 'full_name')) {
                $table->string('full_name', 255)->after('id');
            }
            $columnsToDrop = [];
            if (Schema::hasColumn('partner_with_us_submissions', 'first_name')) {
                $columnsToDrop[] = 'first_name';
            }
            if (Schema::hasColumn('partner_with_us_submissions', 'last_name')) {
                $columnsToDrop[] = 'last_name';
            }
            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
