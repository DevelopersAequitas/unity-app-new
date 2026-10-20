<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\SupportTicketSubmittedMail;
use App\Models\SupportTicket;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicSupportPageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('first_name', 100)->nullable();
                $table->string('last_name', 100)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('phone', 30)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('files')) {
            Schema::create('files', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('uploader_user_id')->nullable();
                $table->string('s3_key');
                $table->string('mime_type');
                $table->unsignedBigInteger('size_bytes');
                $table->integer('width')->nullable();
                $table->integer('height')->nullable();
                $table->integer('duration')->nullable();
                $table->boolean('is_orphaned')->default(false);
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('ticket_number');
                $table->uuid('user_id')->nullable();
                $table->string('contact_name');
                $table->string('email');
                $table->string('subject');
                $table->text('description');
                $table->string('media_file_id')->nullable();
                $table->string('media_type')->nullable();
                $table->string('media_url')->nullable();
                $table->string('status')->default('open');
                $table->string('priority')->default('normal');
                $table->text('admin_note')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('email_logs')) {
            Schema::create('email_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable();
                $table->string('to_email');
                $table->string('to_name')->nullable();
                $table->string('template_key')->nullable();
                $table->string('subject')->nullable();
                $table->string('source_module')->nullable();
                $table->string('related_type')->nullable();
                $table->string('related_id')->nullable();
                $table->string('source_type')->nullable();
                $table->string('source_id')->nullable();
                $table->string('source_event')->nullable();
                $table->string('status')->default('sent');
                $table->text('body_html')->nullable();
                $table->text('body_text')->nullable();
                $table->text('payload')->nullable();
                $table->text('error_message')->nullable();
                $table->string('triggered_by')->nullable();
                $table->uuid('triggered_user_id')->nullable();
                $table->string('mail_provider')->nullable();
                $table->string('queue_id')->nullable();
                $table->string('message_id')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_support_page_loads_successfully(): void
    {
        $response = $this->get('/support');

        $response->assertStatus(200);
        $response->assertSee('How can we help you today?');
        $response->assertSee('Submit Ticket');
        $response->assertSee('Track Ticket');
    }

    public function test_support_ticket_submission_validation(): void
    {
        $response = $this->post('/support', []);

        $response->assertSessionHasErrors(['contact_name', 'email', 'subject', 'description']);
    }

    public function test_support_ticket_submission_creates_record_and_sends_email(): void
    {
        Mail::fake();
        Storage::fake('public');

        $payload = [
            'contact_name' => 'Amit Patel',
            'email' => 'amit.patel@example.com',
            'phone' => '+91 9876543210',
            'category' => 'Account & Login',
            'priority' => 'high',
            'subject' => 'Cannot receive login OTP',
            'description' => 'I tried logging into the Peers mobile app multiple times but did not receive the OTP SMS.',
            'media' => UploadedFile::fake()->image('screenshot.png'),
        ];

        $response = $this->post('/support', $payload);

        $response->assertRedirect('/support');
        $response->assertSessionHas('ticket_success');
        $response->assertSessionHas('ticket_number');

        $this->assertDatabaseHas('support_tickets', [
            'contact_name' => 'Amit Patel',
            'email' => 'amit.patel@example.com',
            'status' => 'open',
            'priority' => 'high',
        ]);

        Mail::assertSent(SupportTicketSubmittedMail::class, function (SupportTicketSubmittedMail $mail): bool {
            return $mail->hasTo('amit.patel@example.com');
        });
    }

    public function test_can_track_existing_support_ticket_status(): void
    {
        $ticket = SupportTicket::create([
            'id' => (string) Str::uuid(),
            'ticket_number' => 'SUP-20261020-9999',
            'contact_name' => 'Priya Shah',
            'email' => 'priya@example.com',
            'subject' => 'Event QR code query',
            'description' => 'Where can I find the event QR check-in pass?',
            'status' => 'resolved',
            'priority' => 'normal',
            'admin_note' => 'Your pass is accessible under My Events tab.',
            'resolved_at' => now(),
        ]);

        $response = $this->post('/support/check-status', [
            'ticket_number' => 'SUP-20261020-9999',
            'email' => 'priya@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertSee('SUP-20261020-9999');
        $response->assertSee('Resolved');
        $response->assertSee('Your pass is accessible under My Events tab.');
    }
}
