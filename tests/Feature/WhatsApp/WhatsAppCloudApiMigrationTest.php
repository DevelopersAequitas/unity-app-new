<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Models\WhatsAppMessageLog;
use App\Models\WhatsappTemplate;
use App\Services\Notifications\WhatsappNotificationService;
use App\Services\WhatsApp\WhatsAppMessageService;
use App\Services\WhatsApp\WhatsAppTemplatePayloadBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppCloudApiMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Use isolated in-memory SQLite for safe, fast local testing
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge();

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('whatsapp_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('template_key')->unique();
            $table->string('template_name');
            $table->text('webhook_url')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('recipient_phone', 50);
            $table->uuid('whatsapp_template_id')->nullable();
            $table->string('meta_template_id')->nullable();
            $table->string('template_name');
            $table->string('template_language', 20)->default('en_IN');
            $table->string('message_type', 50)->default('template');
            $table->string('status', 50)->default('PENDING');
            $table->string('meta_message_id')->nullable();
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_message_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('whatsapp_template_id')->nullable();
            $table->string('template_key');
            $table->string('template_name');
            $table->string('event_key')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('recipient_phone');
            $table->string('requested_recipient_phone')->nullable();
            $table->string('actual_recipient_phone')->nullable();
            $table->json('payload');
            $table->json('template_variables')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('provider_response')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->string('source')->nullable();
            $table->string('environment')->nullable();
            $table->boolean('is_test')->default(false);
            $table->string('idempotency_key')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('otp_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('code');
            $table->string('purpose')->default('whatsapp_login');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // Seed basic active templates
        $this->seedTemplates();

        // Meta Cloud API configuration
        Config::set('whatsapp.meta.access_token', 'test_meta_access_token_123');
        Config::set('whatsapp.meta.phone_number_id', '1148211571702139');
        Config::set('whatsapp.meta.business_account_id', '1475250733815899');
        Config::set('whatsapp.meta.api_version', 'v22.0');
        Config::set('whatsapp.testing.test_mode', false);
        Config::set('whatsapp.testing.dry_run', false);
        Config::set('whatsapp.testing.test_phone', null);
    }

    private function seedTemplates(): void
    {
        $templates = [
            'otp_verification' => 'OTP Verification',
            'welcome' => 'Welcome to the Tribe',
            'engagement_founder' => 'peer_engagement',
            'event_registration' => 'Event Registration WhatsApp',
            'profile_completion_reminder' => 'profile_completion_reminder',
            'circle_calling_day3' => 'MSG 8 - Your Circle is Calling',
            'pgu_pr_media_visibility_v2' => 'Peers Global PR & Media Visibility WhatsApp',
            'wear_the_badge' => 'Wear The Badge',
        ];

        foreach ($templates as $key => $name) {
            WhatsappTemplate::create([
                'id' => (string) Str::uuid(),
                'template_key' => $key,
                'template_name' => $name,
                'webhook_url' => 'https://fleximsg.com/api/webhooks/mock-'.$key,
                'webhook_secret' => 'MOCK_SECRET_'.$key,
                'description' => 'Test description for '.$key,
                'is_active' => true,
            ]);
        }
    }

    /**
     * 1. OTP message construction
     */
    public function test_otp_message_construction(): void
    {
        $payload = [
            'phone' => '919876543210',
            'code' => '654321',
        ];

        $built = WhatsAppTemplatePayloadBuilder::build('otp_verification', $payload);

        $this->assertEquals('919876543210', $built['to']);
        $this->assertEquals('otp_verification', $built['template']['name']);
        $this->assertEquals('en_IN', $built['template']['language']['code']);

        // Check authentication body component
        $body = collect($built['template']['components'])->firstWhere('type', 'body');
        $this->assertNotNull($body);
        $this->assertEquals('654321', $body['parameters'][0]['text']);

        // Check authentication button component (copy_code)
        $button = collect($built['template']['components'])->firstWhere('type', 'button');
        $this->assertNotNull($button);
        $this->assertEquals('654321', $button['parameters'][0]['text']);
    }

    /**
     * 2. OTP masking in logs
     */
    public function test_otp_is_masked_in_logs(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '919876543210', 'wa_id' => '919876543210']],
                'messages' => [['id' => 'wamid.HBgLOTkxOTg3NjU0MzIxMBUCABEYEjE0...']],
            ], 200),
        ]);

        $service = app(WhatsAppMessageService::class);
        $result = $service->send(
            templateKey: 'otp_verification',
            payload: ['phone' => '919876543210', 'code' => '998877'],
            eventKey: 'auth.otp_requested'
        );

        $this->assertTrue($result['success']);

        $log = WhatsAppMessageLog::where('template_key', 'otp_verification')->latest()->first();
        $this->assertNotNull($log);

        // Ensure raw OTP is masked in template_variables
        $this->assertEquals('******', $log->template_variables['code']);
        $this->assertStringNotContainsString('998877', json_encode($log->template_variables));
    }

    /**
     * 3. Ensure only otp_verification is migrated, other templates are not
     */
    public function test_only_otp_verification_is_migrated(): void
    {
        $this->assertTrue(WhatsAppTemplatePayloadBuilder::isMigrated('otp_verification'));

        $this->assertFalse(WhatsAppTemplatePayloadBuilder::isMigrated('welcome'));
        $this->assertFalse(WhatsAppTemplatePayloadBuilder::isMigrated('welcome_to_the_unity'));
        $this->assertFalse(WhatsAppTemplatePayloadBuilder::isMigrated('engagement_founder'));
        $this->assertFalse(WhatsAppTemplatePayloadBuilder::isMigrated('peer_engagement'));
        $this->assertFalse(WhatsAppTemplatePayloadBuilder::isMigrated('event_registration'));
        $this->assertFalse(WhatsAppTemplatePayloadBuilder::isMigrated('event_registration_qr_pass'));
        $this->assertFalse(WhatsAppTemplatePayloadBuilder::isMigrated('pgu_pr_media_visibility_v2'));
        $this->assertFalse(WhatsAppTemplatePayloadBuilder::isMigrated('profile_completion_reminder'));
        $this->assertFalse(WhatsAppTemplatePayloadBuilder::isMigrated('wear_the_badge'));
        $this->assertFalse(WhatsAppTemplatePayloadBuilder::isMigrated('circle_calling_day3'));
        $this->assertFalse(WhatsAppTemplatePayloadBuilder::isMigrated('your_circle_is_calling'));
    }

    /**
     * 10. Phone normalization
     */
    public function test_phone_normalization(): void
    {
        $builder = app(WhatsAppTemplatePayloadBuilder::class);

        $this->assertEquals('919876543210', $builder->normalizePhone('+91 98765-43210'));
        $this->assertEquals('919876543210', $builder->normalizePhone('919876543210'));
        $this->assertEquals('919876543210', $builder->normalizePhone('  +91 98765 43210  '));
        $this->assertNull($builder->normalizePhone(''));
        $this->assertNull($builder->normalizePhone(null));
    }

    /**
     * 11. Missing recipient fails safe
     */
    public function test_missing_recipient_fails_safe(): void
    {
        Http::fake();

        $service = app(WhatsAppMessageService::class);
        $result = $service->send(
            templateKey: 'welcome',
            payload: ['first_name' => 'Anonymous'] // No phone or mobile
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_RECIPIENT', $result['error_code']);
        Http::assertNothingSent();

        $log = WhatsAppMessageLog::where('template_key', 'welcome')->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals(WhatsAppMessageLog::STATUS_FAILED, $log->status);
    }

    /**
     * 12. Inactive template skipped
     */
    public function test_inactive_template_is_skipped(): void
    {
        Http::fake();

        WhatsappTemplate::where('template_key', 'otp_verification')->update(['is_active' => false]);

        $service = app(WhatsAppMessageService::class);
        $result = $service->send(
            templateKey: 'otp_verification',
            payload: ['phone' => '919876543210', 'code' => '123456']
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('TEMPLATE_INACTIVE', $result['error_code']);
        Http::assertNothingSent();

        $log = WhatsAppMessageLog::where('template_key', 'otp_verification')->latest()->first();
        $this->assertEquals(WhatsAppMessageLog::STATUS_SKIPPED, $log->status);
    }

    /**
     * 13. Meta API success
     */
    public function test_meta_api_success(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '919876543210', 'wa_id' => '919876543210']],
                'messages' => [['id' => 'wamid.SUCCESS_MESSAGE_123']],
            ], 200),
        ]);

        $service = app(WhatsAppMessageService::class);
        $result = $service->send(
            templateKey: 'otp_verification',
            payload: ['phone' => '919876543210', 'code' => '123456']
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('wamid.SUCCESS_MESSAGE_123', $result['provider_message_id']);

        $log = WhatsAppMessageLog::where('provider_message_id', 'wamid.SUCCESS_MESSAGE_123')->first();
        $this->assertNotNull($log);
        $this->assertEquals(WhatsAppMessageLog::STATUS_SENT, $log->status);
    }

    /**
     * 14. Meta API failure
     */
    public function test_meta_api_failure(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => 'Template does not exist',
                    'type' => 'OAuthException',
                    'code' => 100,
                    'error_subcode' => 2388040,
                ],
            ], 400),
        ]);

        $service = app(WhatsAppMessageService::class);
        $result = $service->send(
            templateKey: 'otp_verification',
            payload: ['phone' => '919876543210', 'code' => '123456']
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('100', $result['error_code']);

        $log = WhatsAppMessageLog::where('template_key', 'otp_verification')->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals(WhatsAppMessageLog::STATUS_FAILED, $log->status);
        $this->assertStringNotContainsString('test_meta_access_token_123', json_encode($log->toArray()));
    }

    /**
     * 15. Test mode recipient override
     */
    public function test_test_mode_recipient_override(): void
    {
        Config::set('whatsapp.testing.test_mode', true);
        Config::set('whatsapp.testing.test_phone', '919999999999');

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '919999999999', 'wa_id' => '919999999999']],
                'messages' => [['id' => 'wamid.TEST_OVERRIDE_001']],
            ], 200),
        ]);

        $service = app(WhatsAppMessageService::class);
        $result = $service->send(
            templateKey: 'otp_verification',
            payload: ['phone' => '919876543210', 'code' => '123456']
        );

        $this->assertTrue($result['success']);

        // Assert HTTP sent to test phone, NOT production recipient
        Http::assertSent(function ($request) {
            return $request['to'] === '919999999999';
        });

        $log = WhatsAppMessageLog::where('provider_message_id', 'wamid.TEST_OVERRIDE_001')->first();
        $this->assertNotNull($log);
        $this->assertTrue($log->is_test);
        $this->assertEquals('919876543210', $log->requested_recipient_phone);
        $this->assertEquals('919999999999', $log->actual_recipient_phone);
    }

    /**
     * 16. Dry run mode
     */
    public function test_dry_run_mode(): void
    {
        Config::set('whatsapp.testing.dry_run', true);
        Http::fake();

        $service = app(WhatsAppMessageService::class);
        $result = $service->send(
            templateKey: 'otp_verification',
            payload: ['phone' => '919876543210', 'code' => '123456']
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('dry_run', $result['status']);
        Http::assertNothingSent();

        $log = WhatsAppMessageLog::where('template_key', 'otp_verification')->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals(WhatsAppMessageLog::STATUS_DRY_RUN, $log->status);
    }

    /**
     * 17. Duplicate prevention / Idempotency
     */
    public function test_duplicate_prevention_via_idempotency(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '919876543210', 'wa_id' => '919876543210']],
                'messages' => [['id' => 'wamid.FIRST_SEND']],
            ], 200),
        ]);

        $service = app(WhatsAppMessageService::class);

        // First send
        $first = $service->send(
            templateKey: 'otp_verification',
            payload: ['phone' => '919876543210', 'code' => '123456'],
            idempotencyKey: 'idemp_test_key_1001'
        );
        $this->assertTrue($first['success']);

        // Second send with same idempotency key
        $second = $service->send(
            templateKey: 'otp_verification',
            payload: ['phone' => '919876543210', 'code' => '123456'],
            idempotencyKey: 'idemp_test_key_1001'
        );

        $this->assertTrue($second['success']);
        $this->assertTrue($second['duplicate']);
        // Only one HTTP request should have been made
        Http::assertSentCount(1);
    }

    /**
     * 18. Provider message ID logging
     */
    public function test_provider_message_id_logging(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '919876543210', 'wa_id' => '919876543210']],
                'messages' => [['id' => 'wamid.PROV_ID_LOGGED_999']],
            ], 200),
        ]);

        $service = app(WhatsAppMessageService::class);
        $result = $service->send(
            templateKey: 'otp_verification',
            payload: ['phone' => '919876543210', 'code' => '123456']
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('wamid.PROV_ID_LOGGED_999', $result['provider_message_id']);

        $this->assertDatabaseHas('whatsapp_message_logs', [
            'provider_message_id' => 'wamid.PROV_ID_LOGGED_999',
            'status' => 'sent',
        ]);
    }

    /**
     * 19. Ensure ReshoBrand / fleximsg webhook is NOT called for migrated OTP, while unmigrated templates use fleximsg
     */
    public function test_migrated_templates_do_not_call_reshobrand_webhook(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.OTP_MIGRATED_SUCCESS']],
            ], 200),
            'https://fleximsg.com/*' => Http::response(['status' => 'error'], 500),
        ]);

        $notificationService = app(WhatsappNotificationService::class);

        // 1. otp_verification uses Meta Cloud API directly and NEVER calls fleximsg
        $otpRes = $notificationService->send('otp_verification', '919876543210', [
            'code' => '543210',
        ]);

        $this->assertTrue($otpRes);

        // Assert Graph API was called
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'graph.facebook.com');
        });

        // 2. Non-migrated template (e.g. welcome) falls back to fleximsg webhook
        $welcomeRes = $notificationService->send('welcome', '919876543210', [
            'first_name' => 'Jay',
        ]);

        // fleximsg returned 500, so it returns false, proving it routed to fleximsg webhook
        $this->assertFalse($welcomeRes);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'fleximsg.com');
        });
    }
}
