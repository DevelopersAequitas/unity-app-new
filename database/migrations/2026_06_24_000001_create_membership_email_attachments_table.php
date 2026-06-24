<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('membership_email_attachments')) {
            return;
        }

        Schema::create('membership_email_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('file_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('file_id')->references('id')->on('files')->cascadeOnDelete();
            $table->unique('file_id');
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_email_attachments');
    }
};
