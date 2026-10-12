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
        if (! Schema::hasTable('whatsapp_message_logs')) {
            Schema::create('whatsapp_message_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('whatsapp_template_id')->nullable()->index();
                $table->string('template_key', 150)->index();
                $table->string('template_name', 255)->nullable();
                $table->string('event_key', 150)->nullable()->index();
                $table->uuid('user_id')->nullable()->index();
                $table->string('recipient_phone', 50)->index();
                $table->string('requested_recipient_phone', 50)->nullable()->index();
                $table->string('actual_recipient_phone', 50)->nullable()->index();
                $table->jsonb('payload')->nullable();
                $table->jsonb('template_variables')->nullable();
                $table->jsonb('request_payload')->nullable();
                $table->jsonb('provider_response')->nullable();
                $table->string('provider_message_id', 255)->nullable()->index();
                $table->string('status', 50)->default('pending')->index();
                $table->string('error_code', 50)->nullable()->index();
                $table->text('error_message')->nullable();
                $table->string('source', 100)->nullable();
                $table->string('environment', 50)->nullable();
                $table->boolean('is_test')->default(false)->index();
                $table->string('idempotency_key', 255)->nullable()->index();
                $table->timestamp('sent_at')->nullable()->index();
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
        Schema::dropIfExists('whatsapp_message_logs');
    }
};
