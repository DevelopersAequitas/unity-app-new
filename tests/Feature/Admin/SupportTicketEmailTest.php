<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Mail\SupportTicketResponseMail;
use App\Models\AdminUser;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Notifications\AppNotification;
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
                $table->string('peer_id')->nullable();
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

        if (! Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->boolean('push_enabled')->default(true);
                $table->boolean('email_enabled')->default(true);
                $table->boolean('chat_enabled')->default(true);
                $table->boolean('event_enabled')->default(true);
                $table->boolean('circle_enabled')->default(true);
                $table->boolean('business_enabled')->default(true);
                $table->boolean('campaign_enabled')->default(true);
                $table->string('quiet_hours_start')->nullable();
                $table->string('quiet_hours_end')->nullable();
                $table->text('config')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_push_tokens')) {
            Schema::create('user_push_tokens', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable();
                $table->string('token');
                $table->string('platform')->nullable();
                $table->string('device_id')->nullable();
                $table->string('app_version')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_update_notification_sent_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->string('failure_reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('app_notifications')) {
            Schema::create('app_notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('campaign_id')->nullable();
                $table->string('type');
                $table->string('category')->nullable();
                $table->string('title');
                $table->text('body');
                $table->string('channel')->default('push');
                $table->string('priority')->default('medium');
                $table->string('reference_type')->nullable();
                $table->string('reference_id')->nullable();
                $table->string('screen')->nullable();
                $table->text('data')->nullable();
                $table->string('dedupe_key')->nullable();
                $table->string('status')->default('pending');
                $table->timestamp('read_at')->nullable();
                $table->timestamp('clicked_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->string('failure_reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notification_delivery_logs')) {
            Schema::create('notification_delivery_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('notification_id');
                $table->string('campaign_id')->nullable();
                $table->uuid('user_id');
                $table->string('channel');
                $table->string('provider');
                $table->string('status');
                $table->text('request_payload')->nullable();
                $table->text('response_payload')->nullable();
                $table->timestamp('attempted_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notification_suppression_logs')) {
            Schema::create('notification_suppression_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('type');
                $table->string('dedupe_key');
                $table->string('campaign_id')->nullable();
                $table->integer('send_count')->default(0);
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamps();
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

    public function test_admin_can_send_only_push_notification(): void
    {
        Mail::fake();

        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test User',
            'email' => 'testuser@example.test',
            'peer_id' => 'PG-12345',
        ]);

        $ticket = SupportTicket::create([
            'ticket_number' => 'SUP-20260729-0005',
            'user_id' => $user->id,
            'contact_name' => 'Test User',
            'email' => 'testuser@example.test',
            'subject' => 'App Issue',
            'description' => 'App keeps crashing',
            'status' => 'open',
            'priority' => 'normal',
        ]);



        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.support-tickets.send-email', $ticket->id), [
                'action' => 'send_notification',
                'status' => 'in_progress',
            ]);

        $response->assertRedirect(route('admin.support-tickets.show', $ticket->id));
        $response->assertSessionHas('success');

        Mail::assertNothingSent();

        $ticket->refresh();
        $this->assertSame('in_progress', $ticket->status);
        $this->assertStringContainsString('[Push Notification Sent', $ticket->admin_note);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $user->id,
            'type' => 'support_ticket_response',
            'title' => 'Support Ticket Update',
            'body' => 'Your support ticket #SUP-20260729-0005 request has been accepted by our team. To see more details, please check your email.',
        ]);
    }

    public function test_admin_can_send_both_email_and_push_notification(): void
    {
        Mail::fake();

        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test User',
            'email' => 'testuser@example.test',
            'peer_id' => 'PG-12345',
        ]);

        $ticket = SupportTicket::create([
            'ticket_number' => 'SUP-20260729-0006',
            'user_id' => $user->id,
            'contact_name' => 'Test User',
            'email' => 'testuser@example.test',
            'subject' => 'App Issue',
            'description' => 'App keeps crashing',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.support-tickets.send-email', $ticket->id), [
                'action' => 'send_both',
                'subject' => 'Re: App Issue Resolved',
                'message' => 'Please update your app to the latest version.',
                'status' => 'resolved',
            ]);

        $response->assertRedirect(route('admin.support-tickets.show', $ticket->id));
        $response->assertSessionHas('success');

        Mail::assertSent(SupportTicketResponseMail::class);

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertStringContainsString('[Email & Push Notification Sent', $ticket->admin_note);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $user->id,
            'type' => 'support_ticket_response',
            'title' => 'Support Ticket Update',
        ]);
    }
}
