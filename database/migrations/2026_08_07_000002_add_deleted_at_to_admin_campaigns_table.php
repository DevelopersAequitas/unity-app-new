<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_campaigns', function (Blueprint $table): void {
            if (! Schema::hasColumn('admin_campaigns', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('admin_campaigns', function (Blueprint $table): void {
            if (Schema::hasColumn('admin_campaigns', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
