<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('testimonials')) {
            Schema::create('testimonials', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('from_user_id');
                $table->uuid('to_user_id');
                $table->text('content')->nullable();
                $table->json('media')->nullable();
                $table->integer('rating')->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('from_user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('to_user_id')->references('id')->on('users')->onDelete('cascade');
            });
        } else {
            Schema::table('testimonials', function (Blueprint $table) {
                if (! Schema::hasColumn('testimonials', 'rating')) {
                    $table->integer('rating')->nullable()->after('media');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('testimonials')) {
            Schema::table('testimonials', function (Blueprint $table) {
                if (Schema::hasColumn('testimonials', 'rating')) {
                    $table->dropColumn('rating');
                }
            });
        }
    }
};
