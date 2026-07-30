<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Mail\SupportTicketResponseMail;
use App\Models\AdminUser;
use App\Models\Role;
use App\Models\SupportTicket;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportTicketEmailTest extends TestCase
{
    use DatabaseTransactions;

    private AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchemas();

        $roleId = (string) Str::uuid();

        Role::create([
            'id' => $roleId,
            'name' => 'Super Admin',
            'key' => 'super-admin',
            'slug' => 'super-admin',
            'is_system' => true,
        ]);

        $this->admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Support Admin',
            'email' => 'admin_'.Str::random(6).'@peersglobal.test',
            'password' => bcrypt('password'),
            'role_id' => $roleId,
            'is_active' => true,
        ]);

        DB::table('admin_user_roles')->insert([
            'user_id' => $this->admin->id,
            'role_id' => $roleId,
        ]);
    }

    private function createTestSchemas(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name')->nullable();
                $table->string('email')->unique();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('key')->nullable();
                $table->string('slug')->nullable();
                $table->boolean('is_system')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password')->nullable();
                $table->uuid('role_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_user_roles')) {
            Schema::create('admin_user_roles', function (Blueprint $table): void {
                $table->uuid('user_id');
                $table->uuid('role_id');
                $table->primary(['user_id', 'role_id']);
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
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function test_admin_can_send_direct_email_response_to_support_ticket_customer(): void
    {
        Mail::fake();

        $ticket = SupportTicket::create([
            'ticket_number' => 'SUP-20260729-0001',
            'contact_name' => 'Jatin Jadav',
            'email' => 'work.jatinjadav@gmail.com',
            'subject' => 'Error in My Badges',
            'description' => 'Failed to fetch latest milestone (500)',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.support-tickets.send-email', $ticket->id), [
                'subject' => 'Re: [Ticket #SUP-20260729-0001] Error in My Badges',
                'message' => 'We have resolved the badge error on your account. Please log in and check.',
                'status' => 'resolved',
            ]);

        $response->assertRedirect(route('admin.support-tickets.show', $ticket->id));
        $response->assertSessionHas('success');

        Mail::assertSent(SupportTicketResponseMail::class, function (SupportTicketResponseMail $mail): bool {
            return $mail->hasTo('work.jatinjadav@gmail.com')
                && $mail->emailSubject === 'Re: [Ticket #SUP-20260729-0001] Error in My Badges'
                && str_contains($mail->responseMessage, 'We have resolved the badge error');
        });

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertStringContainsString('We have resolved the badge error', (string) $ticket->admin_note);

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'work.jatinjadav@gmail.com',
            'template_key' => 'support_ticket_response',
            'related_id' => $ticket->id,
            'status' => 'sent',
        ]);
    }

    public function test_admin_can_send_email_response_with_media_attachments(): void
    {
        Mail::fake();
        Storage::fake('public');

        $ticket = SupportTicket::create([
            'ticket_number' => 'SUP-20260729-0003',
            'contact_name' => 'Media User',
            'email' => 'mediauser@example.com',
            'subject' => 'Media Test',
            'description' => 'Testing attachments',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $file = UploadedFile::fake()->image('screenshot.png');

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.support-tickets.send-email', $ticket->id), [
                'subject' => 'Re: Media Test',
                'message' => 'Please see the attached screenshot.',
                'status' => 'in_progress',
                'attachments' => [$file],
            ]);

        $response->assertRedirect(route('admin.support-tickets.show', $ticket->id));
        $response->assertSessionHas('success');

        Mail::assertSent(SupportTicketResponseMail::class, function (SupportTicketResponseMail $mail): bool {
            return $mail->hasTo('mediauser@example.com')
                && count($mail->attachmentsList) === 1
                && $mail->attachmentsList[0]['name'] === 'screenshot.png';
        });

        $ticket->refresh();
        $this->assertStringContainsString('Attachments: screenshot.png', (string) $ticket->admin_note);
    }

    public function test_send_email_validates_required_fields(): void
    {
        Mail::fake();

        $ticket = SupportTicket::create([
            'ticket_number' => 'SUP-20260729-0002',
            'contact_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'Help Needed',
            'description' => 'Cannot log in',
            'status' => 'open',
            'priority' => 'high',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.support-tickets.send-email', $ticket->id), [
                'subject' => '',
                'message' => '',
            ]);

        $response->assertSessionHasErrors(['subject', 'message']);
        Mail::assertNothingSent();
    }
}
