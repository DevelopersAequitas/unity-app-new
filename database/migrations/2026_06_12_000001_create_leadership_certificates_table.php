<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('leadership_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('leadership_certification_submission_id')->unique();
            $table->string('full_name')->nullable();
            $table->string('business_name')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_no', 50)->nullable();
            $table->string('certification_level')->nullable();
            $table->integer('total_score')->nullable();
            $table->decimal('percentage', 8, 2)->nullable();
            $table->string('status', 50)->default('issued');
            $table->string('certificate_number', 100)->unique();
            $table->text('certificate_pdf_path')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->index('leadership_certification_submission_id', 'idx_leadership_certificates_submission_id');
            $table->index('email', 'idx_leadership_certificates_email');
            $table->index('status', 'idx_leadership_certificates_status');

            $table->foreign('leadership_certification_submission_id', 'fk_leadership_certificates_submission')
                ->references('id')
                ->on('leadership_certification_submissions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leadership_certificates');
    }
};
