<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circle_chat_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('circle_id');
            $table->uuid('sender_id');
            $table->string('message_type', 20);
            $table->text('message_text')->nullable();
            $table->uuid('file_id')->nullable();
            $table->uuid('reply_to_message_id')->nullable();
            $table->boolean('is_deleted_for_all')->default(false);
            $table->timestampTz('deleted_for_all_at')->nullable();
            $table->timestampsTz();

            $table->foreign('circle_id')->references('id')->on('circles')->cascadeOnDelete();
            $table->foreign('sender_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('file_id')->references('id')->on('files')->nullOnDelete();
            $table->foreign('reply_to_message_id')->references('id')->on('circle_chat_messages')->nullOnDelete();

            $table->index(['circle_id', 'created_at']);
            $table->index('sender_id');
            $table->index('is_deleted_for_all');
            $table->check("message_type IN ('text','image','video')");
            $table->check("(message_type = 'text' AND message_text IS NOT NULL) OR (message_type IN ('image','video') AND file_id IS NOT NULL)");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_chat_messages');
    }
};
