<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Models\AdminUser;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\User;
use App\Models\WhatsappTemplate;
use App\Services\Notifications\EventRegistrationWhatsappService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class EventPassWhatsappAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge();
        $this->withoutMiddleware([
            \App\Http\Middleware\AdminRoleMiddleware::class,
            \App\Http\Middleware\CheckDynamicPermission::class,
            \App\Http\Middleware\AdminCircleScope::class,
        ]);

        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key')->default('super_admin');
            $table->string('name')->default('Super Admin');
            $table->timestamps();
        });

        Schema::create('admin_user_roles', function (Blueprint $table): void {
            $table->uuid('user_id');
            $table->uuid('role_id');
        });

        Schema::create('admin_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->default('Super Admin');
            $table->string('email')->unique();
            $table->string('password')->default('password');
            $table->string('role')->default('super_admin');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id')->nullable();
            $table->string('title');
            $table->string('venue')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('event_occurrences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->timestamp('start_at')->nullable();
            $table->timestamps();
        });

        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('occurrence_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('visitor_name')->nullable();
            $table->string('visitor_phone')->nullable();
            $table->string('visitor_email')->nullable();
            $table->string('status')->default('registered');
            $table->string('checkin_status')->default('not_checked_in');
            $table->string('qr_token')->nullable();
            $table->text('qr_code_svg')->nullable();
            $table->string('qr_code_path')->nullable();
            $table->string('qr_code_url')->nullable();
            $table->string('payment_status')->nullable();
            $table->boolean('payment_required')->default(false);
            $table->string('whatsapp_status')->nullable();
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->string('whatsapp_provider_id')->nullable();
            $table->json('whatsapp_response_payload')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('notification_delivery_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('notifiable_type')->nullable();
            $table->uuid('notifiable_id')->nullable();
            $table->string('channel')->default('whatsapp');
            $table->string('recipient')->nullable();
            $table->string('status')->default('sent');
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestamps();
        });

        Schema::create('email_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('to_email')->nullable();
            $table->string('to_name')->nullable();
            $table->string('template_key')->nullable();
            $table->string('subject')->nullable();
            $table->string('source_module')->nullable();
            $table->string('related_type')->nullable();
            $table->string('related_id')->nullable();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->string('source_event')->nullable();
            $table->string('status')->nullable();
            $table->text('body_html')->nullable();
            $table->text('body_text')->nullable();
            $table->text('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->string('triggered_by')->nullable();
            $table->string('triggered_user_id')->nullable();
            $table->string('mail_provider')->nullable();
            $table->string('queue_id')->nullable();
            $table->string('message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        DB::table('roles')->insert([
            'id' => (string) Str::uuid(),
            'key' => 'global_admin',
            'name' => 'Global Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createGlobalAdmin(string $email = 'admin@peersunity.com'): AdminUser
    {
        $admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'email' => $email,
        ]);

        $roleId = DB::table('roles')->where('key', 'global_admin')->value('id');
        DB::table('admin_user_roles')->insert([
            'user_id' => $admin->id,
            'role_id' => $roleId,
        ]);

        return $admin;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_event_passes_index_requires_authentication(): void
    {
        $response = $this->get(route('admin.whatsapp.event_passes.index'));
        $response->assertRedirect();
    }

    public function test_event_passes_index_renders_successfully_for_admin(): void
    {
        $admin = $this->createGlobalAdmin('admin1@peersunity.com');

        $event = Event::create([
            'id' => (string) Str::uuid(),
            'title' => 'Annual Conclave 2026',
            'start_at' => now()->addDays(5),
        ]);

        $reg = EventRegistration::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'visitor_name' => 'Urvashi Chavda',
            'visitor_phone' => '7043893431',
            'visitor_email' => 'urvashi@example.com',
            'status' => 'confirmed',
            'qr_token' => 'EVT-TEST-TOKEN',
            'qr_code_svg' => '<svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="40"/></svg>',
            'whatsapp_status' => 'delivered',
            'whatsapp_sent_at' => now(),
            'whatsapp_provider_id' => 'wamid.HBgMOTE3MDQzODkzNDMx',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.whatsapp.event_passes.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.whatsapp.event_passes.index');
        $response->assertSee('Event WhatsApp QR Passes');
        $response->assertSee('Urvashi Chavda');
        $response->assertSee('7043893431');
        $response->assertSee('Annual Conclave 2026');
        $response->assertSee('Delivered');
    }

    public function test_resend_whatsapp_pass_endpoint_dispatches_successfully(): void
    {
        $admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin2@peersunity.com',
            'is_super_admin' => true,
        ]);

        $event = Event::create([
            'id' => (string) Str::uuid(),
            'title' => 'Tech Summit 2026',
            'start_at' => now()->addDays(3),
        ]);

        $reg = EventRegistration::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'visitor_name' => 'Test User',
            'visitor_phone' => '9876543210',
            'status' => 'confirmed',
            'whatsapp_status' => 'failed',
        ]);

        $mockService = Mockery::mock(EventRegistrationWhatsappService::class);
        $mockService->shouldReceive('sendNotification')
            ->once()
            ->andReturnUsing(function ($registration, $force) {
                $registration->forceFill([
                    'whatsapp_status' => 'sent',
                    'whatsapp_sent_at' => now(),
                    'whatsapp_provider_id' => 'wamid.TEST_RESEND_123',
                ])->save();

                return true;
            });

        $this->app->instance(EventRegistrationWhatsappService::class, $mockService);

        $response = $this->actingAs($admin, 'admin')->postJson(route('admin.whatsapp.event_passes.resend', $reg->id));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'registration' => [
                'id' => $reg->id,
                'whatsapp_status' => 'sent',
                'whatsapp_provider_id' => 'wamid.TEST_RESEND_123',
            ],
        ]);
    }

    public function test_bulk_resend_endpoint_handles_multiple_registrations(): void
    {
        $admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin3@peersunity.com',
            'is_super_admin' => true,
        ]);

        $event = Event::create([
            'id' => (string) Str::uuid(),
            'title' => 'Leadership Meet',
        ]);

        $reg1 = EventRegistration::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'visitor_name' => 'User One',
            'visitor_phone' => '9111111111',
            'status' => 'confirmed',
        ]);

        $reg2 = EventRegistration::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'visitor_name' => 'User Two',
            'visitor_phone' => '9222222222',
            'status' => 'confirmed',
        ]);

        $mockService = Mockery::mock(EventRegistrationWhatsappService::class);
        $mockService->shouldReceive('sendNotification')
            ->twice()
            ->andReturn(true);

        $this->app->instance(EventRegistrationWhatsappService::class, $mockService);

        $response = $this->actingAs($admin, 'admin')->postJson(route('admin.whatsapp.event_passes.bulk_resend'), [
            'ids' => [$reg1->id, $reg2->id],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'success_count' => 2,
            'failed_count' => 0,
        ]);
    }

    public function test_export_streams_csv_file(): void
    {
        $admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin4@peersunity.com',
            'is_super_admin' => true,
        ]);

        $event = Event::create([
            'id' => (string) Str::uuid(),
            'title' => 'Export Event Test',
        ]);

        EventRegistration::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'visitor_name' => 'Export User',
            'visitor_phone' => '9998887776',
            'status' => 'confirmed',
            'whatsapp_status' => 'delivered',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.whatsapp.event_passes.export'));

        $response->assertStatus(200);
        $this->assertTrue(str_contains((string) $response->headers->get('content-type'), 'text/csv'));
    }
}
