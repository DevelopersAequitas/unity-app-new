<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add columns to events table if they do not exist
        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                if (! Schema::hasColumn('events', 'event_category')) {
                    $table->string('event_category', 100)->nullable();
                }
                if (! Schema::hasColumn('events', 'mode')) {
                    $table->string('mode', 20)->default('offline');
                }
                if (! Schema::hasColumn('events', 'recurrence_type')) {
                    $table->string('recurrence_type', 20)->default('none');
                }
                if (! Schema::hasColumn('events', 'recurrence_interval')) {
                    $table->integer('recurrence_interval')->default(1);
                }
                if (! Schema::hasColumn('events', 'recurrence_day_of_week')) {
                    $table->integer('recurrence_day_of_week')->nullable();
                }
                if (! Schema::hasColumn('events', 'recurrence_week_of_month')) {
                    $table->integer('recurrence_week_of_month')->nullable();
                }
                if (! Schema::hasColumn('events', 'recurrence_day_of_month')) {
                    $table->integer('recurrence_day_of_month')->nullable();
                }
                if (! Schema::hasColumn('events', 'recurrence_month')) {
                    $table->integer('recurrence_month')->nullable();
                }
                if (! Schema::hasColumn('events', 'recurrence_ends_at')) {
                    $table->timestamp('recurrence_ends_at')->nullable();
                }
                if (! Schema::hasColumn('events', 'visitor_registration_enabled')) {
                    $table->boolean('visitor_registration_enabled')->default(false);
                }
                if (! Schema::hasColumn('events', 'member_registration_enabled')) {
                    $table->boolean('member_registration_enabled')->default(true);
                }
                if (! Schema::hasColumn('events', 'online_meeting_url')) {
                    $table->text('online_meeting_url')->nullable();
                }
                if (! Schema::hasColumn('events', 'zoho_form_url')) {
                    $table->text('zoho_form_url')->nullable();
                }
            });
        }

        // 2. Create event_occurrences table
        if (! Schema::hasTable('event_occurrences')) {
            Schema::create('event_occurrences', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
                $table->date('occurrence_date');
                $table->timestamp('start_at');
                $table->timestamp('end_at')->nullable();
                $table->string('status', 30)->default('scheduled');
                $table->integer('sequence')->default(1);
                $table->integer('registration_limit')->nullable();
                $table->integer('registered_count')->default(0);
                $table->integer('checked_in_count')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['event_id', 'occurrence_date']);
                $table->index('event_id');
                $table->index('occurrence_date');
                $table->index('start_at');
            });
        }

        // 3. Create event_registrations table
        if (! Schema::hasTable('event_registrations')) {
            Schema::create('event_registrations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
                $table->foreignUuid('occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
                $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->string('qr_token')->unique();
                $table->text('qr_code_path')->nullable();
                $table->text('qr_code_url')->nullable();
                $table->text('qr_code_svg')->nullable();
                $table->timestamp('qr_generated_at')->nullable();
                $table->timestamp('last_qr_scan_at')->nullable();
                $table->text('scan_device_info')->nullable();
                $table->string('attendance_source', 50)->nullable();
                $table->string('status', 30)->default('registered');
                $table->string('checkin_status', 30)->default('pending');
                $table->timestamp('registered_at')->useCurrent();
                $table->timestamp('checked_in_at')->nullable();
                $table->foreignUuid('checked_in_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('source', 30)->default('app');
                $table->string('visitor_name')->nullable();
                $table->string('visitor_email')->nullable();
                $table->string('visitor_phone', 50)->nullable();
                $table->string('visitor_company')->nullable();
                $table->string('visitor_city')->nullable();
                $table->string('zoho_form_entry_id')->nullable();
                $table->string('zoho_payment_id')->nullable();
                $table->string('zoho_payment_status', 100)->nullable();
                $table->json('metadata')->nullable();

                // Razorpay and Zoho extensions
                $table->boolean('payment_required')->default(false);
                $table->string('payment_status', 30)->default('not_required');
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('currency', 10)->default('INR');
                $table->string('zoho_customer_id')->nullable();
                $table->string('zoho_hosted_page_id')->nullable();
                $table->text('zoho_checkout_url')->nullable();
                $table->string('zoho_invoice_id')->nullable();
                $table->string('zoho_invoice_number')->nullable();
                $table->timestamp('payment_completed_at')->nullable();
                $table->string('registration_type', 30)->nullable();
                $table->string('razorpay_order_id')->nullable();
                $table->string('razorpay_payment_id')->nullable();
                $table->text('razorpay_signature')->nullable();
                $table->string('razorpay_payment_status', 50)->nullable();
                $table->timestamp('razorpay_paid_at')->nullable();
                $table->text('zoho_invoice_url')->nullable();
                $table->text('zoho_invoice_pdf_url')->nullable();
                $table->timestamp('zoho_invoice_synced_at')->nullable();
                $table->text('zoho_invoice_sync_error')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index('payment_status');
                $table->index('zoho_hosted_page_id');
                $table->index('zoho_invoice_id');
                $table->index('razorpay_order_id');
                $table->index('razorpay_payment_id');
                $table->index('event_id');
                $table->index('occurrence_id');
                $table->index('user_id');
                $table->index('qr_token');
                $table->index('checkin_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_occurrences');
    }
};
