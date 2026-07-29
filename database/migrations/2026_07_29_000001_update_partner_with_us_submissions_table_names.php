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
            $table->string('first_name', 255)->nullable()->after('id');
            $table->string('last_name', 255)->nullable()->after('first_name');
            $table->dropColumn('full_name');
        });
    }

    public function down(): void
    {
        Schema::table('partner_with_us_submissions', function (Blueprint $table) {
            $table->string('full_name', 255)->after('id');
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
