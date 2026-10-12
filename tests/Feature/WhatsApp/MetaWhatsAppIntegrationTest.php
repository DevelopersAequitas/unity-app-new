<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Exceptions\MetaWhatsAppException;
use App\Http\Middleware\AdminCircleScope;
use App\Http\Middleware\AdminRoleMiddleware;
use App\Http\Middleware\CheckDynamicPermission;
use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Models\WhatsappWebhookEvent;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MetaWhatsAppIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Use isolated in-memory sqlite for clean, fast, deterministic testing
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
            $table->string('language', 20)->default('en_US');
            $table->string('category', 50)->default('UTILITY');
            $table->string('status', 50)->default('PENDING');
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
            $table->uuid('user_id')->nullable();
            $table->string('recipient_phone', 50);
            $table->uuid('whatsapp_template_id')->nullable();
            $table->string('meta_template_id')->nullable();
            $table->string('template_name');
            $table->string('template_language', 20)->default('en_US');
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

        Schema::create('whatsapp_webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_type', 100);
            $table->string('waba_id', 100)->nullable();
            $table->string('phone_number_id', 100)->nullable();
            $table->string('meta_message_id', 150)->nullable();
            $table->string('meta_template_id', 150)->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->json('payload');
            $table->string('processing_status', 50)->default('processed');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Config::set('whatsapp.meta.access_token', 'test_meta_system_user_token_abc123');
        Config::set('whatsapp.meta.api_version', 'v22.0');
        Config::set('whatsapp.meta.business_account_id', '1475250733815899');
        Config::set('whatsapp.meta.phone_number_id', '1148211571702139');
        Config::set('whatsapp.meta.business_id', '5418283234857525');
        Config::set('whatsapp.meta.app_id', '757348670325961');
        Config::set('whatsapp.meta.webhook_verify_token', 'test_verify_token_123');
        Config::set('whatsapp.testing.recipient', '+916353025164');
        Config::set('whatsapp.testing.restrict_to_test_recipient', true);
    }

    /** 1. Configuration & Environment Test */
    public function test_configuration_and_environment_values_loaded(): void
    {
        $this->assertSame('1475250733815899', config('whatsapp.meta.business_account_id'));
        $this->assertSame('1148211571702139', config('whatsapp.meta.phone_number_id'));
        $this->assertSame('+916353025164', config('whatsapp.testing.recipient'));
        $this->assertSame('v22.0', config('whatsapp.meta.api_version'));
    }

    /** 2. Meta Service Authentication & Diagnostic Test */
    public function test_validate_configuration_success(): void
    {
        Http::fake([
            'https://graph.facebook.com/v22.0/1148211571702139' => Http::response([
                'id' => '1148211571702139',
                'display_phone_number' => '+91 96646 20704',
                'verified_name' => 'Peers Global Business Media Pvt Ltd',
                'quality_rating' => 'GREEN',
                'code_verification_status' => 'VERIFIED',
            ], 200),
            'https://graph.facebook.com/v22.0/1475250733815899*' => Http::response([
                'id' => '1475250733815899',
                'name' => 'Peers Global WABA',
                'timezone_id' => 'Asia/Kolkata',
                'currency' => 'INR',
            ], 200),
            'https://graph.facebook.com/v22.0/debug_token*' => Http::response([
                'data' => [
                    'type' => 'SYSTEM_USER',
                    'is_valid' => true,
                    'scopes' => ['whatsapp_business_management', 'whatsapp_business_messaging'],
                ],
            ], 200),
        ]);

        $service = new MetaWhatsAppService;
        $report = $service->validateConfiguration();

        $this->assertTrue($report['connected']);
        $this->assertSame('VALID', $report['token_status']);
        $this->assertSame('AVAILABLE', $report['waba_access']);
        $this->assertSame('AVAILABLE', $report['phone_number_access']);
    }

    /** 3. Validation handles missing access token */
    public function test_validate_configuration_handles_missing_token(): void
    {
        Config::set('whatsapp.meta.access_token', null);

        $service = new MetaWhatsAppService;
        $report = $service->validateConfiguration();

        $this->assertFalse($report['connected']);
        $this->assertSame('INVALID', $report['token_status']);
        $this->assertNotNull($report['error']);
    }

    /** 4. Template List Parsing & 5. Idempotent Synchronization */
    public function test_sync_templates_idempotently(): void
    {
        Http::fake([
            'https://graph.facebook.com/v22.0/1475250733815899/message_templates*' => Http::response([
                'data' => [
                    [
                        'id' => '998877665544',
                        'name' => 'peers_welcome_test',
                        'language' => 'en_US',
                        'status' => 'APPROVED',
                        'category' => 'UTILITY',
                        'components' => [
                            ['type' => 'BODY', 'text' => 'Hello {{1}}, welcome!'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new MetaWhatsAppService;

        // First Sync
        $firstResult = $service->syncTemplates();
        $this->assertSame(1, $firstResult['total_synced']);
        $this->assertSame(1, $firstResult['created']);

        $this->assertDatabaseHas('whatsapp_templates', [
            'meta_template_id' => '998877665544',
            'name' => 'peers_welcome_test',
            'status' => 'APPROVED',
        ]);

        // Second Sync: Idempotent check (should update, not create duplicate)
        $secondResult = $service->syncTemplates();
        $this->assertSame(1, $secondResult['total_synced']);
        $this->assertSame(0, $secondResult['created']);
        $this->assertSame(1, $secondResult['updated']);

        $this->assertSame(1, WhatsappTemplate::where('name', 'peers_welcome_test')->count());
    }

    /** 6. Template Creation Payload Format */
    public function test_create_template_submission(): void
    {
        Http::fake([
            'https://graph.facebook.com/v22.0/1475250733815899/message_templates' => Http::response([
                'id' => '112233445566',
                'status' => 'IN_REVIEW',
                'category' => 'UTILITY',
            ], 200),
        ]);

        $service = new MetaWhatsAppService;
        $result = $service->createTemplate([
            'name' => 'membership_renewal_notice',
            'category' => 'UTILITY',
            'language' => 'en_US',
            'components' => [
                [
                    'type' => 'BODY',
                    'text' => 'Hello {{1}}, your membership expires on {{2}}.',
                    'example' => [
                        'body_text' => [['John Doe', '15 Nov 2026']],
                    ],
                ],
            ],
        ]);

        $this->assertSame('112233445566', $result['id']);
        $this->assertSame('IN_REVIEW', $result['status']);

        $this->assertDatabaseHas('whatsapp_templates', [
            'name' => 'membership_renewal_notice',
            'status' => 'IN_REVIEW',
            'meta_template_id' => '112233445566',
        ]);
    }

    /** 7. Invalid Template Name Rejection */
    public function test_create_template_rejects_invalid_name(): void
    {
        $this->expectException(MetaWhatsAppException::class);

        $service = new MetaWhatsAppService;
        $service->createTemplate([
            'name' => 'Invalid Template Name with Spaces!',
            'category' => 'UTILITY',
            'language' => 'en_US',
            'components' => [],
        ]);
    }

    /** 8. Delete Template */
    public function test_delete_template(): void
    {
        WhatsappTemplate::create([
            'meta_template_id' => '555666777',
            'name' => 'temp_to_delete',
            'language' => 'en_US',
            'category' => 'UTILITY',
            'status' => 'APPROVED',
        ]);

        Http::fake([
            'https://graph.facebook.com/v22.0/1475250733815899/message_templates*' => Http::response(['success' => true], 200),
        ]);

        $service = new MetaWhatsAppService;
        $this->assertTrue($service->deleteTemplate('temp_to_delete'));

        $this->assertDatabaseHas('whatsapp_templates', [
            'name' => 'temp_to_delete',
            'status' => 'DELETED',
        ]);
    }

    /** 8b. Upload Media Sample for Template Approval */
    public function test_upload_template_media_sample(): void
    {
        Http::fake([
            'https://graph.facebook.com/v22.0/757348670325961/uploads*' => Http::response([
                'id' => 'upload:session_12345',
            ], 200),
            'https://graph.facebook.com/v22.0/upload:session_12345' => Http::response([
                'h' => '4::sample_handle_abc123',
            ], 200),
        ]);

        $file = UploadedFile::fake()->image('header_sample.jpg', 600, 400);

        $service = new MetaWhatsAppService;
        $handle = $service->uploadTemplateMediaSample($file);

        $this->assertSame('4::sample_handle_abc123', $handle);
    }

    /** 9. Webhook Verification Handshake Success */
    public function test_webhook_verification_handshake_success(): void
    {
        $response = $this->get('/api/whatsapp/webhook?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'test_verify_token_123',
            'hub_challenge' => 'challenge_response_token_xyz',
        ]));

        $response->assertStatus(200);
        $this->assertSame('challenge_response_token_xyz', $response->getContent());
    }

    /** 10. Webhook Verification Handshake Failure */
    public function test_webhook_verification_handshake_failure(): void
    {
        $response = $this->get('/api/whatsapp/webhook?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'wrong_token',
            'hub_challenge' => 'challenge_token',
        ]));

        $response->assertStatus(403);
    }

    /** 11. Webhook Message Status Delivered & Read */
    public function test_webhook_message_status_delivered_and_read(): void
    {
        $message = WhatsappMessage::create([
            'recipient_phone' => '916353025164',
            'template_name' => 'peers_welcome_test',
            'template_language' => 'en_US',
            'status' => 'SENT',
            'meta_message_id' => 'wamid.HBgMOTE2MzUzMDI1MTY0FQIAERgSMTQ1',
            'sent_at' => now(),
        ]);

        // Inbound DELIVERED webhook
        $deliveredPayload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '1475250733815899',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => ['phone_number_id' => '1148211571702139'],
                                'statuses' => [
                                    [
                                        'id' => 'wamid.HBgMOTE2MzUzMDI1MTY0FQIAERgSMTQ1',
                                        'status' => 'delivered',
                                        'timestamp' => (string) now()->timestamp,
                                        'recipient_id' => '916353025164',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $res1 = $this->postJson('/api/whatsapp/webhook', $deliveredPayload);
        $res1->assertStatus(200);

        $message->refresh();
        $this->assertSame('DELIVERED', $message->status);
        $this->assertNotNull($message->delivered_at);

        // Inbound READ webhook
        $readPayload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '1475250733815899',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'statuses' => [
                                    [
                                        'id' => 'wamid.HBgMOTE2MzUzMDI1MTY0FQIAERgSMTQ1',
                                        'status' => 'read',
                                        'timestamp' => (string) now()->timestamp,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $res2 = $this->postJson('/api/whatsapp/webhook', $readPayload);
        $res2->assertStatus(200);

        $message->refresh();
        $this->assertSame('READ', $message->status);
        $this->assertNotNull($message->read_at);
    }

    /** 12. Webhook Message Status Failed with Meta Error Details */
    public function test_webhook_message_status_failed_with_meta_error(): void
    {
        $message = WhatsappMessage::create([
            'recipient_phone' => '916353025164',
            'template_name' => 'peers_welcome_test',
            'template_language' => 'en_US',
            'status' => 'SENT',
            'meta_message_id' => 'wamid.HBgMOTE2MzUzMDI1MTY0FQIAERgSMTQ5',
        ]);

        $failedPayload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '1475250733815899',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'statuses' => [
                                    [
                                        'id' => 'wamid.HBgMOTE2MzUzMDI1MTY0FQIAERgSMTQ5',
                                        'status' => 'failed',
                                        'timestamp' => (string) now()->timestamp,
                                        'errors' => [
                                            [
                                                'code' => 131026,
                                                'title' => 'Message undeliverable',
                                                'message' => 'Message undeliverable',
                                                'error_data' => [
                                                    'details' => 'Recipient cannot receive messages.',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $res = $this->postJson('/api/whatsapp/webhook', $failedPayload);
        $res->assertStatus(200);

        $message->refresh();
        $this->assertSame('FAILED', $message->status);
        $this->assertSame('131026', $message->error_code);
        $this->assertStringContainsString('Message undeliverable', $message->error_message);
    }

    /** 13. Duplicate Webhook Payload Deduplication */
    public function test_duplicate_webhook_payload_deduplication(): void
    {
        $payload = [
            'entry' => [
                [
                    'id' => '1475250733815899',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'statuses' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $res1 = $this->postJson('/api/whatsapp/webhook', $payload);
        $res1->assertStatus(200);

        $initialCount = WhatsappWebhookEvent::count();

        // Exact duplicate
        $res2 = $this->postJson('/api/whatsapp/webhook', $payload);
        $res2->assertStatus(200);
        $res2->assertJson(['status' => 'duplicate_ignored']);

        $this->assertSame($initialCount, WhatsappWebhookEvent::count());
    }

    /** 14. Template Status Webhook Update */
    public function test_webhook_template_status_update(): void
    {
        $template = WhatsappTemplate::create([
            'meta_template_id' => '888999000',
            'name' => 'approval_sync_test',
            'language' => 'en_US',
            'category' => 'UTILITY',
            'status' => 'IN_REVIEW',
        ]);

        $webhookPayload = [
            'entry' => [
                [
                    'id' => '1475250733815899',
                    'changes' => [
                        [
                            'field' => 'message_template_status_update',
                            'value' => [
                                'message_template_id' => '888999000',
                                'message_template_name' => 'approval_sync_test',
                                'message_template_language' => 'en_US',
                                'event' => 'APPROVED',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $res = $this->postJson('/api/whatsapp/webhook', $webhookPayload);
        $res->assertStatus(200);

        $template->refresh();
        $this->assertSame('APPROVED', $template->status);
    }

    /** 15. Send Template Message Success */
    public function test_send_template_message_success(): void
    {
        WhatsappTemplate::create([
            'meta_template_id' => '111222333',
            'name' => 'verified_template',
            'language' => 'en_US',
            'status' => 'APPROVED',
        ]);

        Http::fake([
            'https://graph.facebook.com/v22.0/1148211571702139/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '916353025164', 'wa_id' => '916353025164']],
                'messages' => [['id' => 'wamid.HBgMOTE2MzUzMDI1MTY0FQIAERgSREU0']],
            ], 200),
        ]);

        $service = new MetaWhatsAppService;
        $result = $service->sendTemplateMessage(
            '+916353025164',
            'verified_template',
            'en_US',
            []
        );

        $this->assertTrue($result['success']);
        $this->assertSame('wamid.HBgMOTE2MzUzMDI1MTY0FQIAERgSREU0', $result['meta_message_id']);

        $this->assertDatabaseHas('whatsapp_messages', [
            'id' => $result['whatsapp_message_id'],
            'recipient_phone' => '916353025164',
            'template_name' => 'verified_template',
            'status' => 'SENT',
            'meta_message_id' => 'wamid.HBgMOTE2MzUzMDI1MTY0FQIAERgSREU0',
        ]);
    }

    /** 16. Send Message Blocks Unapproved Template */
    public function test_send_template_message_blocks_unapproved_template(): void
    {
        WhatsappTemplate::create([
            'meta_template_id' => '444555666',
            'name' => 'unapproved_sample',
            'language' => 'en_US',
            'status' => 'IN_REVIEW',
        ]);

        $this->expectException(MetaWhatsAppException::class);
        $this->expectExceptionCode(422);

        $service = new MetaWhatsAppService;
        $service->sendTemplateMessage(
            '+916353025164',
            'unapproved_sample',
            'en_US'
        );
    }

    /** 17. Test Recipient Restriction Enforcement (Allows +916353025164 and +917041151714 when enabled) */
    public function test_send_template_message_enforces_test_recipient_restriction(): void
    {
        Config::set('whatsapp.testing.restrict_to_test_recipient', true);

        WhatsappTemplate::create([
            'name' => 'approved_tmpl',
            'language' => 'en_US',
            'status' => 'APPROVED',
        ]);

        Http::fake([
            'https://graph.facebook.com/v22.0/1148211571702139/messages' => Http::response([
                'messages' => [['id' => 'wamid.HBgTEST']],
            ], 200),
        ]);

        $service = new MetaWhatsAppService;

        // Permitted recipient 1 (+916353025164)
        $res1 = $service->sendTemplateMessage('+916353025164', 'approved_tmpl', 'en_US');
        $this->assertTrue($res1['success']);

        // Permitted recipient 2 (+917041151714)
        $res2 = $service->sendTemplateMessage('+917041151714', 'approved_tmpl', 'en_US');
        $this->assertTrue($res2['success']);

        // Attempting to send to an unauthorized number must fail with 403
        try {
            $service->sendTemplateMessage('+919876543210', 'approved_tmpl', 'en_US');
            $this->fail('Expected MetaWhatsAppException with 403 status was not thrown.');
        } catch (MetaWhatsAppException $e) {
            $this->assertSame(403, $e->getHttpStatus());
            $this->assertSame(131026, $e->getMetaErrorCode());
            $this->assertStringContainsString('+917041151714', $e->getMessage());
        }
    }

    /** 17b. Allows Any Recipient Number When Restriction Disabled */
    public function test_send_template_message_allows_any_number_when_restriction_disabled(): void
    {
        Config::set('whatsapp.testing.restrict_to_test_recipient', false);

        WhatsappTemplate::create([
            'name' => 'unrestricted_tmpl',
            'language' => 'en_US',
            'status' => 'APPROVED',
        ]);

        Http::fake([
            'https://graph.facebook.com/v22.0/1148211571702139/messages' => Http::response([
                'messages' => [['id' => 'wamid.ANY_NUMBER_TEST']],
            ], 200),
        ]);

        $service = new MetaWhatsAppService;
        $res = $service->sendTemplateMessage('+919999888877', 'unrestricted_tmpl', 'en_US');
        $this->assertTrue($res['success']);
    }

    /** 18. Meta API Error Handling & Exception Mapping */
    public function test_meta_error_response_handling(): void
    {
        WhatsappTemplate::create([
            'name' => 'test_error_template',
            'language' => 'en_US',
            'status' => 'APPROVED',
        ]);

        Http::fake([
            'https://graph.facebook.com/v22.0/1148211571702139/messages' => Http::response([
                'error' => [
                    'message' => 'Rate limit exceeded',
                    'type' => 'OAuthException',
                    'code' => 80007,
                    'fbtrace_id' => 'AnG7x98Pz',
                ],
            ], 400),
        ]);

        try {
            $service = new MetaWhatsAppService;
            $service->sendTemplateMessage(
                '+916353025164',
                'test_error_template',
                'en_US'
            );
        } catch (MetaWhatsAppException $e) {
            $this->assertSame(400, $e->getHttpStatus(), 'Exception caught: '.$e->getMessage());
            $this->assertSame(80007, $e->getMetaErrorCode());
            $this->assertStringContainsString('Rate limit exceeded', $e->getUserFriendlyMessage());
        }
    }

    /** 19. Verify Admin WhatsApp Views Render Without Blade Parse Errors */
    public function test_admin_whatsapp_views_render_cleanly(): void
    {
        $this->withoutMiddleware([
            EnsureAdminAuthenticated::class,
            AdminRoleMiddleware::class,
            AdminCircleScope::class,
            CheckDynamicPermission::class,
        ]);

        $template = WhatsappTemplate::create([
            'name' => 'sample_notification',
            'language' => 'en_US',
            'category' => 'UTILITY',
            'status' => 'APPROVED',
            'meta_template_id' => '1234567890',
            'components' => [
                [
                    'type' => 'HEADER',
                    'format' => 'TEXT',
                    'text' => 'Important Notification',
                ],
                [
                    'type' => 'BODY',
                    'text' => 'Hello {{1}}, your order {{2}} has been confirmed.',
                ],
                [
                    'type' => 'FOOTER',
                    'text' => 'Peers Global Unity Support',
                ],
                [
                    'type' => 'BUTTONS',
                    'buttons' => [
                        [
                            'type' => 'URL',
                            'text' => 'View Order',
                            'url' => 'https://example.com/orders',
                        ],
                    ],
                ],
            ],
        ]);

        $responseIndex = $this->get(route('admin.whatsapp.templates'));
        $responseIndex->assertStatus(200);

        $responseCreate = $this->get(route('admin.whatsapp.templates.create'));
        $responseCreate->assertStatus(200);

        $responseTestMsg = $this->get(route('admin.whatsapp.test_message'));
        $responseTestMsg->assertStatus(200);

        $responseShow = $this->get(route('admin.whatsapp.templates.show', $template->id));
        $responseShow->assertStatus(200);
    }

    /** 20. Template Filtering and Latest-First Ordering */
    public function test_template_filtering_and_latest_ordering(): void
    {
        $this->withoutMiddleware([
            EnsureAdminAuthenticated::class,
            AdminRoleMiddleware::class,
            AdminCircleScope::class,
            CheckDynamicPermission::class,
        ]);

        $t1 = WhatsappTemplate::create([
            'name' => 'first_created_tmpl',
            'language' => 'en_US',
            'status' => 'APPROVED',
            'category' => 'UTILITY',
            'meta_template_id' => '111111',
            'created_at' => now()->subHours(2),
        ]);

        $t2 = WhatsappTemplate::create([
            'name' => 'second_created_tmpl',
            'language' => 'en_US',
            'status' => 'REJECTED',
            'category' => 'MARKETING',
            'meta_template_id' => '222222',
            'created_at' => now()->subMinute(),
        ]);

        // 1. Check ordering: latest created first
        $response = $this->getJson(route('admin.whatsapp.templates'));
        $response->assertStatus(200);
        $templatesData = $response->json('templates.data');
        $this->assertSame($t2->name, $templatesData[0]['name']);

        // 2. Check status filter
        $filterStatusResponse = $this->getJson(route('admin.whatsapp.templates', ['status' => 'REJECTED']));
        $filterStatusResponse->assertStatus(200);
        $rejectedData = $filterStatusResponse->json('templates.data');
        $this->assertCount(1, $rejectedData);
        $this->assertSame('second_created_tmpl', $rejectedData[0]['name']);

        // 3. Check search filter
        $searchResponse = $this->getJson(route('admin.whatsapp.templates', ['search' => 'first_created']));
        $searchResponse->assertStatus(200);
        $searchData = $searchResponse->json('templates.data');
        $this->assertCount(1, $searchData);
        $this->assertSame('first_created_tmpl', $searchData[0]['name']);
    }

    /** 21. Upload Media and Send Test Message With Image Header */
    public function test_send_test_message_with_image_header(): void
    {
        $this->withoutMiddleware([
            EnsureAdminAuthenticated::class,
            AdminRoleMiddleware::class,
            AdminCircleScope::class,
            CheckDynamicPermission::class,
        ]);

        $imageTemplate = WhatsappTemplate::create([
            'name' => 'image_header_template',
            'language' => 'en_US',
            'status' => 'APPROVED',
            'category' => 'MARKETING',
            'meta_template_id' => '333444555',
            'components' => [
                [
                    'type' => 'HEADER',
                    'format' => 'IMAGE',
                    'example' => [
                        'header_handle' => ['https://example.com/sample.png'],
                    ],
                ],
                [
                    'type' => 'BODY',
                    'text' => 'Hello {{1}}, test body',
                ],
            ],
        ]);

        Http::fake([
            'https://graph.facebook.com/v22.0/1148211571702139/messages' => function ($request) {
                $payload = $request->data();
                // Ensure header with image is in components
                $components = $payload['template']['components'] ?? [];
                $hasHeader = false;
                foreach ($components as $c) {
                    if (($c['type'] ?? '') === 'header' && isset($c['parameters'][0]['image'])) {
                        $hasHeader = true;
                    }
                }
                if (! $hasHeader) {
                    return Http::response(['error' => 'Missing header image'], 400);
                }

                return Http::response([
                    'messaging_product' => 'whatsapp',
                    'messages' => [['id' => 'wamid.IMAGE_TEST_HEADER']],
                ], 200);
            },
        ]);

        $res = $this->postJson(route('admin.whatsapp.test_message.send'), [
            'recipient' => '+916353025164',
            'template_name' => 'image_header_template',
            'language' => 'en_US',
            'variables' => ['1' => 'Jay'],
            'header_image_url' => 'https://example.com/custom.png',
        ]);

        $res->assertStatus(200);
        $res->assertJson(['success' => true]);
        $this->assertDatabaseHas('whatsapp_messages', [
            'recipient_phone' => '916353025164',
            'template_name' => 'image_header_template',
            'status' => 'SENT',
        ]);
    }
}
