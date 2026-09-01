<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contact_posts')) {
            Schema::create('contact_posts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable()->index();
                $table->string('full_name')->nullable();
                $table->string('phone')->nullable()->index();
                $table->string('first_name')->nullable();
                $table->string('middle_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('nickname')->nullable();
                $table->string('email')->nullable()->index();
                $table->string('company')->nullable();
                $table->string('job_title')->nullable();
                $table->text('notes')->nullable();
                $table->json('emails')->nullable();
                $table->json('phones')->nullable();
                $table->json('addresses')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_posts');
    }
};
