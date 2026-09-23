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
        Schema::table('cities', function (Blueprint $table): void {
            if (! Schema::hasColumn('cities', 'state_code')) {
                $table->string('state_code', 10)->nullable()->after('state');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table): void {
            if (Schema::hasColumn('cities', 'state_code')) {
                $table->dropColumn('state_code');
            }
        });
    }
};
