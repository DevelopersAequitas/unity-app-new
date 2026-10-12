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
        if (! Schema::hasTable('whatsapp_messages')) {
            Schema::create('whatsapp_messages', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable()->index();
                $table->string('recipient_phone', 50)->index();
                $table->uuid('whatsapp_template_id')->nullable()->index();
                $table->string('meta_template_id')->nullable()->index();
                $table->string('template_name')->index();
                $table->string('template_language', 20)->default('en_US');
                $table->string('message_type', 50)->default('template');
                $table->string('status', 50)->default('PENDING')->index();
                $table->string('meta_message_id')->nullable()->index();
                $table->string('error_code', 50)->nullable()->index();
                $table->text('error_message')->nullable();
                $table->json('request_payload')->nullable();
                $table->json('response_payload')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
