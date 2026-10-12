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
        if (! Schema::hasTable('whatsapp_webhook_events')) {
            Schema::create('whatsapp_webhook_events', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('event_type', 100)->index();
                $table->string('waba_id', 100)->nullable()->index();
                $table->string('phone_number_id', 100)->nullable()->index();
                $table->string('meta_message_id', 150)->nullable()->index();
                $table->string('meta_template_id', 150)->nullable()->index();
                $table->string('payload_hash', 64)->nullable()->index();
                $table->json('payload');
                $table->string('processing_status', 50)->default('processed')->index();
                $table->text('error_message')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_webhook_events');
    }
};
