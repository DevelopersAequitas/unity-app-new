<?php

use App\Models\EntrepreneurCertificationSubmission;
use App\Models\LeadershipCertificationSubmission;
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
        if (! Schema::hasTable('certification_submissions')) {
            Schema::create('certification_submissions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('certification_type');
                $table->uuid('user_id')->nullable();
                $table->string('full_name');
                $table->string('business_name')->nullable();
                $table->string('email');
                $table->string('contact_no')->nullable();
                $table->integer('total_score')->default(0);
                $table->integer('percentage')->default(0);
                $table->string('certification_level')->nullable();
                $table->string('certification_title')->nullable();
                $table->string('certificate_number')->nullable();
                $table->string('certificate_file_path')->nullable();
                $table->string('certificate_download_url')->nullable();
                $table->timestamp('certificate_generated_at')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->json('answers')->nullable();
                $table->string('status')->default('new');
                $table->text('admin_note')->nullable();
                $table->uuid('approved_by')->nullable();
                $table->uuid('rejected_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('leadership_certification_submissions')) {
            Schema::create('leadership_certification_submissions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('full_name');
                $table->string('business_name')->nullable();
                $table->string('email');
                $table->string('contact_no')->nullable();
                $table->string('status')->default('new');
                $table->text('notes')->nullable();
                $table->integer('total_score')->default(0);
                $table->float('percentage')->default(0);
                $table->string('certification_level')->nullable();
                foreach (LeadershipCertificationSubmission::QUIZ_FIELDS as $field) {
                    $table->text($field)->nullable();
                }
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('entrepreneur_certification_submissions')) {
            Schema::create('entrepreneur_certification_submissions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('full_name');
                $table->string('business_name')->nullable();
                $table->string('email');
                $table->string('contact_no')->nullable();
                $table->string('status')->default('new');
                $table->text('notes')->nullable();
                $table->integer('total_score')->default(0);
                $table->float('percentage')->default(0);
                $table->string('certification_tier')->nullable();
                foreach (EntrepreneurCertificationSubmission::QUIZ_FIELDS as $field) {
                    $table->text($field)->nullable();
                }
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entrepreneur_certification_submissions');
        Schema::dropIfExists('leadership_certification_submissions');
        Schema::dropIfExists('certification_submissions');
    }
};
