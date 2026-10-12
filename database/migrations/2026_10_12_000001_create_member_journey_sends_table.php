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
        if (! Schema::hasTable('member_journey_sends')) {
            Schema::create('member_journey_sends', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->index();
                $table->integer('day_number')->index();
                $table->string('template_name');
                $table->string('language', 20)->default('en_IN');
                $table->timestamp('scheduled_at')->index();
                $table->timestamp('sent_at')->nullable();
                $table->string('status', 50)->default('scheduled')->index();
                $table->string('skip_reason')->nullable();
                $table->jsonb('payload_json')->nullable();
                $table->jsonb('provider_response_json')->nullable();
                $table->string('provider_message_id')->nullable();
                $table->text('error_message')->nullable();
                $table->boolean('is_test')->default(false);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_journey_sends');
    }
};
