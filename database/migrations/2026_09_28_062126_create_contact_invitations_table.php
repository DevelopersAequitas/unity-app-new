<?php

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
        Schema::create('contact_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->uuid('contact_post_id')->nullable()->index();
            $table->string('contact_name');
            $table->string('contact_phone', 50)->index();
            $table->string('contact_email')->nullable();
            $table->string('mobile_normalized', 50)->nullable()->index();
            $table->text('invitation_message')->nullable();
            $table->string('status', 50)->default('pending')->index();
            $table->string('whatsapp_status', 50)->default('not_completed')->index();
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->string('whatsapp_provider_id')->nullable();
            $table->json('whatsapp_response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_invitations');
    }
};
