<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Services\Notifications\EventRegistrationWhatsappService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventRegistrationQrWhatsappTest extends TestCase
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

        Schema::create('whatsapp_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('meta_template_id')->nullable();
            $table->string('name');
            $table->string('language', 20)->default('en_IN');
            $table->string('category', 50)->default('UTILITY');
            $table->string('status', 50)->default('APPROVED');
            $table->string('quality', 50)->nullable();
            $table->string('parameter_format', 50)->default('positional');
            $table->json('components')->nullable();
            $table->json('meta_response')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('template_key')->nullable();
            $table->string('template_name')->nullable();
            $table->text('webhook_url')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('user_id')->nullable();
            $table->string('recipient_phone');
            $table->uuid('whatsapp_template_id')->nullable();
            $table->string('meta_template_id')->nullable();
            $table->string('template_name');
            $table->string('template_language', 20)->default('en_IN');
            $table->string('message_type', 50)->default('template');
            $table->string('status', 50)->default('PENDING');
            $table->string('meta_message_id')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('admin_display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('membership_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('location_text')->nullable();
            $table->string('location')->nullable();
            $table->string('venue')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->decimal('ticket_price', 10, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_occurrences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('occurrence_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('status')->default('registered');
            $table->string('payment_status')->default('not_required');
            $table->boolean('payment_required')->default(false);
            $table->string('visitor_name')->nullable();
            $table->string('visitor_phone')->nullable();
            $table->string('visitor_email')->nullable();
            $table->string('qr_token')->nullable();
            $table->text('qr_code_url')->nullable();
            $table->string('qr_code_path')->nullable();
            $table->text('qr_code_svg')->nullable();
            $table->timestamp('qr_generated_at')->nullable();
            $table->string('whatsapp_status')->nullable();
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->string('whatsapp_provider_id')->nullable();
            $table->json('whatsapp_response_payload')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Config::set('whatsapp.meta.access_token', 'test_meta_token');
        Config::set('whatsapp.meta.phone_number_id', '1148211571702139');
        Config::set('whatsapp.meta.business_account_id', '1475250733815899');
        Config::set('whatsapp.testing.restrict_to_test_recipient', false);
    }

    private function createTemplate(): WhatsappTemplate
    {
        return WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'meta_template_id' => '3833459580136525',
            'name' => 'event_registration_qr_pass',
            'template_key' => 'event_registration_qr_pass',
            'language' => 'en_IN',
            'category' => 'UTILITY',
            'status' => 'APPROVED',
            'parameter_format' => 'positional',
            'components' => [
                [
                    'type' => 'HEADER',
                    'format' => 'IMAGE',
                    'example' => [
                        'header_handle' => [
                            'https://scontent.whatsapp.net/example-qr.png',
                        ],
                    ],
                ],
                [
                    'type' => 'BODY',
                    'text' => "Hello,\n\nYour registration for {{1}} is confirmed.\n\nRegistration ID: {{2}}\nName: {{3}}\nDate: {{4}}\nTime: {{5}}\nVenue: {{6}}\n\nYou can access your digital entry pass here:\n{{7}}\n\nThank you.",
                ],
            ],
            'is_active' => true,
        ]);
    }

    public function test_resolves_approved_event_registration_qr_pass_template(): void
    {
        $this->createTemplate();
        $service = app(EventRegistrationWhatsappService::class);

        $template = $service->resolveTemplate();

        $this->assertNotNull($template);
        $this->assertEquals('event_registration_qr_pass', $template->name);
        $this->assertEquals('en_IN', $template->language);
    }

    public function test_skips_notification_when_status_is_pending_payment(): void
    {
        $this->createTemplate();

        $event = Event::create([
            'id' => (string) Str::uuid(),
            'title' => 'Annual Unity Gala',
            'start_at' => now()->addDays(5),
            'end_at' => now()->addDays(5)->addHours(3),
            'location_text' => 'Grand Hyatt Mumbai',
            'is_paid' => true,
            'ticket_price' => 500,
        ]);

        $reg = EventRegistration::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'payment_required' => true,
            'visitor_name' => 'Rahul Sharma',
            'visitor_phone' => '+919876543210',
        ]);

        Http::fake();

        $service = app(EventRegistrationWhatsappService::class);
        $result = $service->sendNotification($reg);

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_sends_notification_with_qr_code_image_and_dynamic_parameters_when_registered(): void
    {
        $this->createTemplate();

        $event = Event::create([
            'id' => (string) Str::uuid(),
            'title' => 'Annual Unity Gala',
            'start_at' => now()->parse('2026-11-20 18:00:00'),
            'end_at' => now()->parse('2026-11-20 21:00:00'),
            'location_text' => 'Grand Hyatt Mumbai',
            'is_paid' => true,
            'ticket_price' => 500,
        ]);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '919876543210', 'wa_id' => '919876543210']],
                'messages' => [['id' => 'wamid.HBgMOTkyODcyNTc5FQIAEhggQ0ZBNTYy']],
            ], 200),
        ]);

        $reg = EventRegistration::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'status' => 'registered',
            'payment_status' => 'paid',
            'payment_required' => true,
            'visitor_name' => 'Rahul Sharma',
            'visitor_phone' => '+919876543210',
            'qr_token' => 'test-token-12345',
            'qr_code_url' => 'https://peersunity.com/api/v1/event-qrcodes/' . $event->id . '/test.png',
        ]);

        $reg->refresh();
        $this->assertEquals('sent', $reg->whatsapp_status);
        $this->assertEquals('wamid.HBgMOTkyODcyNTc5FQIAEhggQ0ZBNTYy', $reg->whatsapp_provider_id);
        $this->assertNotNull($reg->whatsapp_sent_at);

        Http::assertSent(function ($request) use ($reg) {
            $data = $request->data();
            $this->assertEquals('919876543210', $data['to']);
            $this->assertEquals('event_registration_qr_pass', $data['template']['name']);
            $this->assertEquals('en_IN', $data['template']['language']['code']);

            $components = $data['template']['components'];

            // Header component validation
            $this->assertEquals('header', $components[0]['type']);
            $this->assertEquals('image', $components[0]['parameters'][0]['type']);
            $this->assertStringContainsString('/api/v1/event-qrcodes/' . $reg->event_id . '/', $components[0]['parameters'][0]['image']['link']);

            // Body parameters validation (7 parameters)
            $body = $components[1];
            $this->assertEquals('body', $body['type']);
            $this->assertCount(7, $body['parameters']);
            $this->assertEquals('Annual Unity Gala', $body['parameters'][0]['text']); // {{1}}
            $this->assertEquals((string) $reg->id, $body['parameters'][1]['text']);   // {{2}}
            $this->assertEquals('Rahul Sharma', $body['parameters'][2]['text']);      // {{3}}
            $this->assertEquals('20 Nov 2026', $body['parameters'][3]['text']);       // {{4}}
            $this->assertEquals('06:00 PM - 09:00 PM', $body['parameters'][4]['text']); // {{5}}
            $this->assertEquals('Grand Hyatt Mumbai', $body['parameters'][5]['text']);  // {{6}}
            $this->assertStringContainsString('/api/v1/event-qrcodes/' . $reg->event_id . '/', $body['parameters'][6]['text']); // {{7}}

            return true;
        });
    }

    public function test_sends_notification_immediately_for_free_circle_member_registration(): void
    {
        $this->createTemplate();

        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Priya',
            'last_name' => 'Patel',
            'display_name' => 'Priya Patel',
            'phone' => '+91 9904978744',
            'membership_status' => 'active_peer',
        ]);

        $event = Event::create([
            'id' => (string) Str::uuid(),
            'title' => 'Circle Peer Meetup',
            'start_at' => now()->parse('2026-12-01 10:00:00'),
            'end_at' => now()->parse('2026-12-01 12:00:00'),
            'venue' => 'Unity Hub Ahmedabad',
            'is_paid' => false,
            'ticket_price' => 0,
        ]);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '919904978744', 'wa_id' => '919904978744']],
                'messages' => [['id' => 'wamid.CIRCLEFREE123']],
            ], 200),
        ]);

        $reg = EventRegistration::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'registered',
            'payment_status' => 'not_required',
            'payment_required' => false,
            'visitor_name' => null,
            'visitor_phone' => null,
            'qr_token' => 'circle-free-token',
            'qr_code_url' => 'https://peersunity.com/api/v1/event-qrcodes/' . $event->id . '/circle.png',
        ]);

        $reg->refresh();
        $this->assertEquals('sent', $reg->whatsapp_status);
        $this->assertEquals('wamid.CIRCLEFREE123', $reg->whatsapp_provider_id);

        Http::assertSent(function ($request) use ($reg) {
            $data = $request->data();
            $this->assertEquals('919904978744', $data['to']);
            $this->assertEquals('Priya Patel', $data['template']['components'][1]['parameters'][2]['text']);
            $this->assertEquals('Circle Peer Meetup', $data['template']['components'][1]['parameters'][0]['text']);
            return true;
        });
    }

    public function test_prevents_duplicate_sends(): void
    {
        $this->createTemplate();

        $event = Event::create([
            'id' => (string) Str::uuid(),
            'title' => 'Annual Unity Gala',
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(2)->addHours(2),
            'location_text' => 'Mumbai',
        ]);

        $reg = EventRegistration::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'status' => 'registered',
            'payment_status' => 'paid',
            'payment_required' => false,
            'visitor_name' => 'Amit Shah',
            'visitor_phone' => '9825098250',
            'whatsapp_status' => 'sent',
            'whatsapp_sent_at' => now(),
            'qr_token' => 'tok-dup',
            'qr_code_url' => 'https://peersunity.com/qr.png',
        ]);

        Http::fake();

        $service = app(EventRegistrationWhatsappService::class);
        $result = $service->sendNotification($reg);

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_force_resend_bypasses_duplicate_check(): void
    {
        $this->createTemplate();

        $event = Event::create([
            'id' => (string) Str::uuid(),
            'title' => 'Annual Unity Gala',
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(2)->addHours(2),
            'location_text' => 'Mumbai',
        ]);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '919825098250', 'wa_id' => '919825098250']],
                'messages' => [['id' => 'wamid.FORCE999']],
            ], 200),
        ]);

        $reg = EventRegistration::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'status' => 'registered',
            'payment_status' => 'paid',
            'payment_required' => false,
            'visitor_name' => 'Amit Shah',
            'visitor_phone' => '9825098250',
            'whatsapp_status' => 'sent',
            'whatsapp_sent_at' => now(),
            'qr_token' => 'tok-force',
            'qr_code_url' => 'https://peersunity.com/qr.png',
        ]);

        $service = app(EventRegistrationWhatsappService::class);
        $result = $service->sendNotification($reg, force: true);

        $this->assertTrue($result);
        $reg->refresh();
        $this->assertEquals('wamid.FORCE999', $reg->whatsapp_provider_id);
    }
}
