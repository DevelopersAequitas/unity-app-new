<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\User;
use App\Services\EmailLogs\EmailLogService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailLogDeduplicationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('email_logs');
        Schema::create('email_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('to_email');
            $table->string('to_name')->nullable();
            $table->string('template_key');
            $table->string('subject')->nullable();
            $table->string('source_module')->nullable();
            $table->string('related_type')->nullable();
            $table->string('related_id')->nullable();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->string('source_event')->nullable();
            $table->string('status');
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
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_mailable_log_merges_with_listener_raw_log_to_produce_single_entry(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Mohit',
            'last_name' => 'Chavda',
            'display_name' => 'Mohit Chavda',
            'email' => 'mohit@example.com',
        ]);

        $service = app(EmailLogService::class);

        // Simulate what AppServiceProvider's MessageSending listener creates during Mail::send()
        EmailLog::create([
            'id' => (string) Str::uuid(),
            'to_email' => 'mohit@example.com',
            'template_key' => 'raw_email',
            'subject' => 'Congratulations! Your Circle Joining Request Has Been Approved',
            'body_html' => '<html><body>Welcome to the circle</body></html>',
            'body_text' => 'Welcome to the circle',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
        ]);

        $this->assertEquals(1, EmailLog::count());

        // Now simulate the service logging the sent mailable
        $dummyMailable = new class extends Mailable
        {
            public function build(): self
            {
                return $this->subject('Congratulations! Your Circle Joining Request Has Been Approved')
                    ->html('<html><body>Welcome to the circle</body></html>');
            }
        };

        $service->logMailableSent($dummyMailable, [
            'user_id' => (string) $user->id,
            'to_email' => 'mohit@example.com',
            'to_name' => 'Mohit Chavda',
            'template_key' => 'circle_join_request_approved_congratulations',
            'source_module' => 'Circles',
            'related_type' => 'CircleJoinRequest',
            'related_id' => '12345',
        ]);

        // Verify there is still only ONE log, updated in-place with rich metadata
        $this->assertEquals(1, EmailLog::count());

        $log = EmailLog::first();
        $this->assertNotNull($log);
        $this->assertEquals('mohit@example.com', $log->to_email);
        $this->assertEquals('Mohit Chavda', $log->to_name);
        $this->assertEquals('circle_join_request_approved_congratulations', $log->template_key);
        $this->assertEquals('Circles', $log->source_module);
        $this->assertEquals('CircleJoinRequest', $log->related_type);
        $this->assertEquals('12345', $log->related_id);
        $this->assertEquals('sent', $log->status);
        $this->assertStringContainsString('Welcome to the circle', (string) $log->body_html);
    }

    public function test_mailable_failed_log_updates_raw_log_with_failed_status(): void
    {
        $service = app(EmailLogService::class);

        // Simulate raw_email log from listener
        EmailLog::create([
            'id' => (string) Str::uuid(),
            'to_email' => 'urvashi@example.com',
            'template_key' => 'raw_email',
            'subject' => 'Your Event QR Code Entry Pass',
            'body_html' => '<html><body>QR Code</body></html>',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
        ]);

        $this->assertEquals(1, EmailLog::count());

        $dummyMailable = new class extends Mailable
        {
            public function build(): self
            {
                return $this->subject('Your Event QR Code Entry Pass')->html('<html><body>QR Code</body></html>');
            }
        };

        $service->logMailableFailed($dummyMailable, [
            'to_email' => 'urvashi@example.com',
            'to_name' => 'Urvashi Chavda',
            'template_key' => 'event_visitor_qr',
            'source_module' => 'Events',
        ], 'SMTP connection refused');

        $this->assertEquals(1, EmailLog::count());

        $log = EmailLog::first();
        $this->assertNotNull($log);
        $this->assertEquals('event_visitor_qr', $log->template_key);
        $this->assertEquals('failed', $log->status);
        $this->assertStringContainsString('SMTP connection refused', (string) $log->error_message);
    }

    public function test_quick_succession_calls_with_same_template_and_entity_do_not_duplicate(): void
    {
        $service = app(EmailLogService::class);

        $service->logSent([
            'to_email' => 'jay@example.com',
            'to_name' => 'Jay User',
            'template_key' => 'event_visitor_qr',
            'source_module' => 'Events',
            'related_type' => 'EventRegistration',
            'related_id' => '999',
            'subject' => 'Your Event QR Code Entry Pass',
        ]);

        $this->assertEquals(1, EmailLog::count());

        // Accidental duplicate call
        $service->logSent([
            'to_email' => 'jay@example.com',
            'to_name' => 'Jay User',
            'template_key' => 'event_visitor_qr',
            'source_module' => 'Events',
            'related_type' => 'EventRegistration',
            'related_id' => '999',
            'subject' => 'Your Event QR Code Entry Pass',
        ]);

        $this->assertEquals(1, EmailLog::count());
    }

    public function test_full_mail_sending_flow_produces_exactly_one_log(): void
    {
        $service = app(EmailLogService::class);

        $mailable = new class extends Mailable
        {
            public function build(): self
            {
                return $this->subject('Welcome to Peers Unity')
                    ->html('<h1>Welcome</h1>');
            }
        };

        // Mail sending triggers MessageSending event
        Mail::to('harsh@example.com')->send($mailable);

        // Service logs mailable
        $service->logMailableSent($mailable, [
            'to_email' => 'harsh@example.com',
            'to_name' => 'Harsh Chauhan',
            'template_key' => 'welcome_peer',
            'source_module' => 'Auth',
        ]);

        // Assert exactly 1 log exists and is enriched
        $this->assertEquals(1, EmailLog::count());

        $log = EmailLog::first();
        $this->assertNotNull($log);
        $this->assertEquals('harsh@example.com', $log->to_email);
        $this->assertEquals('Harsh Chauhan', $log->to_name);
        $this->assertEquals('welcome_peer', $log->template_key);
        $this->assertEquals('Auth', $log->source_module);
    }

    public function test_admin_email_log_detail_view_renders_successfully(): void
    {
        $log = EmailLog::create([
            'id' => (string) Str::uuid(),
            'to_email' => 'mohit@example.com',
            'to_name' => 'Mohit Chavda',
            'template_key' => 'circle_join_request_approved_congratulations',
            'subject' => 'Congratulations! Your Circle Joining Request Has Been Approved',
            'source_module' => 'Circles',
            'status' => 'sent',
            'body_html' => '<h1>Hello Mohit</h1><p>Your circle request is approved.</p>',
            'body_text' => 'Hello Mohit, Your circle request is approved.',
            'payload' => ['circle_id' => '123'],
            'sent_at' => now(),
            'created_at' => now(),
        ]);

        $view = view('admin.email_logs.show', [
            'emailLog' => $log,
            'bodyHtml' => $log->body_html,
        ])->render();

        $this->assertStringContainsString('Email Log #', $view);
        $this->assertStringContainsString('Email Information', $view);
        $this->assertStringContainsString('Recipient Information', $view);
        $this->assertStringContainsString('Trigger Information', $view);
        $this->assertStringContainsString('Email Content', $view);
        $this->assertStringContainsString('Metadata / Payload', $view);
        $this->assertStringContainsString('Mohit Chavda', $view);
        $this->assertStringContainsString('mohit@example.com', $view);
        $this->assertStringContainsString('circle_join_request_approved_congratulations', $view);
        $this->assertStringContainsString('Circles', $view);
    }
}
