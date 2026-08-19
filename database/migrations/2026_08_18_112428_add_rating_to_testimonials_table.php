<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('testimonials', 'rating')) {
            Schema::table('testimonials', function (Blueprint $table) {
                $table->integer('rating')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('testimonials', 'rating')) {
            Schema::table('testimonials', function (Blueprint $table) {
                $table->dropColumn('rating');
            });
        }
    }
};
