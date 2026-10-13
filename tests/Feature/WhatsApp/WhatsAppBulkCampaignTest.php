<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Models\WhatsAppBulkCampaignRecipient;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\MetaWhatsAppService;
use App\Services\WhatsApp\WhatsAppBulkCampaignService;
use App\Services\WhatsApp\WhatsAppCampaignCsvImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsAppBulkCampaignTest extends TestCase
{
    protected WhatsAppCampaignCsvImportService $csvService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->csvService = new WhatsAppCampaignCsvImportService;
        Storage::fake('local');
    }

    public function test_csv_parser_validates_name_and_phone(): void
    {
        $csvContent = "name,phone,company\nAmit Patel,919876543210,ABC Corp\nNeha Shah,+919876543211,XYZ Corp\n";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csvContent);

        $result = $this->csvService->parseAndValidate($file);

        $this->assertNotEmpty($result['import_id']);
        $this->assertEquals(2, $result['total_rows']);
        $this->assertEquals(2, $result['valid_count']);
        $this->assertEquals(0, $result['invalid_count']);
        $this->assertEquals(0, $result['duplicate_count']);
        $this->assertContains('name', $result['headers']);
        $this->assertContains('phone', $result['headers']);
    }

    public function test_csv_parser_detects_invalid_phone_and_duplicates(): void
    {
        $csvContent = "name,phone\nValid Person,919876543210\nDuplicate Person,919876543210\nInvalid Phone Person,12345\nMissing Name Person,\n";
        $file = UploadedFile::fake()->createWithContent('contacts_with_errors.csv', $csvContent);

        $result = $this->csvService->parseAndValidate($file);

        $this->assertEquals(4, $result['total_rows']);
        $this->assertEquals(1, $result['valid_count']);
        $this->assertGreaterThanOrEqual(2, $result['invalid_count']);
        $this->assertGreaterThanOrEqual(1, $result['duplicate_count']);
    }

    public function test_phone_normalization_handles_various_formats(): void
    {
        // 10-digit Indian mobile
        $res1 = $this->csvService->normalizePhone('9876543210');
        $this->assertTrue($res1['valid']);
        $this->assertEquals('919876543210', $res1['phone']);

        // With leading +
        $res2 = $this->csvService->normalizePhone('+919876543210');
        $this->assertTrue($res2['valid']);
        $this->assertEquals('919876543210', $res2['phone']);

        // Leading 0 + 10 digits
        $res3 = $this->csvService->normalizePhone('09876543210');
        $this->assertTrue($res3['valid']);
        $this->assertEquals('919876543210', $res3['phone']);

        // International with country code (e.g. US +1)
        $res4 = $this->csvService->normalizePhone('+14155552671');
        $this->assertTrue($res4['valid']);
        $this->assertEquals('14155552671', $res4['phone']);

        // Invalid length
        $res5 = $this->csvService->normalizePhone('1234');
        $this->assertFalse($res5['valid']);
    }

    public function test_csv_injection_prevention(): void
    {
        $this->assertEquals("'+1234", $this->csvService->sanitizeForCsvInjection('+1234'));
        $this->assertEquals("'=SUM(A1:A10)", $this->csvService->sanitizeForCsvInjection('=SUM(A1:A10)'));
        $this->assertEquals("'-100", $this->csvService->sanitizeForCsvInjection('-100'));
        $this->assertEquals('Amit Patel', $this->csvService->sanitizeForCsvInjection('Amit Patel'));
    }

    public function test_template_component_analysis(): void
    {
        $template = new WhatsappTemplate([
            'name' => 'test_campaign_template',
            'language' => 'en_IN',
            'status' => 'APPROVED',
            'components' => [
                [
                    'type' => 'HEADER',
                    'format' => 'IMAGE',
                ],
                [
                    'type' => 'BODY',
                    'text' => 'Hello {{1}}, welcome to {{2}}! Your code is {{3}}.',
                ],
                [
                    'type' => 'BUTTONS',
                    'buttons' => [
                        [
                            'type' => 'URL',
                            'text' => 'Visit Site',
                            'url' => 'https://example.com/ref={{1}}',
                        ],
                    ],
                ],
            ],
        ]);

        $metaService = $this->createMock(MetaWhatsAppService::class);
        $campaignService = new WhatsAppBulkCampaignService($this->csvService, $metaService);

        $analysis = $campaignService->analyzeTemplateComponents($template);

        $this->assertEquals('test_campaign_template', $analysis['name']);
        $this->assertEquals('Hello {{1}}, welcome to {{2}}! Your code is {{3}}.', $analysis['body_text']);
        $this->assertTrue($analysis['header']['is_media']);
        $this->assertEquals('IMAGE', $analysis['header']['format']);
        $this->assertCount(3, $analysis['body_variables']);
        $this->assertEquals('{{1}}', $analysis['body_variables'][0]['token']);
        $this->assertCount(1, $analysis['buttons']);
        $this->assertTrue($analysis['buttons'][0]['has_variable']);
    }

    public function test_recipient_variable_resolution(): void
    {
        $metaService = $this->createMock(MetaWhatsAppService::class);
        $campaignService = new WhatsAppBulkCampaignService($this->csvService, $metaService);

        $templateAnalysis = [
            'header' => ['is_media' => false, 'variables' => []],
            'body_variables' => [
                ['index' => 1, 'token' => '{{1}}'],
                ['index' => 2, 'token' => '{{2}}'],
            ],
            'buttons' => [
                ['index' => 0, 'text' => 'View', 'has_variable' => true],
            ],
        ];

        $recipientData = [
            'name' => 'Amit Patel',
            'phone' => '919876543210',
            'custom_data' => [
                'city' => 'Ahmedabad',
            ],
        ];

        $variableMapping = [
            'body_var_1' => ['source' => 'csv_column', 'column' => 'name'],
            'body_var_2' => ['source' => 'csv_column', 'column' => 'city'],
            'button_var_0' => ['source' => 'fixed', 'value' => 'promo_2026'],
        ];

        $payload = $campaignService->resolveRecipientPayload($recipientData, $variableMapping, [], $templateAnalysis);

        $this->assertEmpty($payload['errors']);
        $this->assertCount(2, $payload['components']); // body and button
        $this->assertEquals('Amit Patel', $payload['components'][0]['parameters'][0]['text']);
        $this->assertEquals('Ahmedabad', $payload['components'][0]['parameters'][1]['text']);
        $this->assertEquals('promo_2026', $payload['components'][1]['parameters'][0]['text']);
    }

    public function test_recipient_model_status_helper_methods(): void
    {
        $deliveredRecipient = new WhatsAppBulkCampaignRecipient([
            'status' => 'delivered',
        ]);
        $this->assertTrue($deliveredRecipient->isDelivered());
        $this->assertFalse($deliveredRecipient->isRead());

        $readRecipient = new WhatsAppBulkCampaignRecipient([
            'status' => 'read',
        ]);
        $this->assertTrue($readRecipient->isDelivered());
        $this->assertTrue($readRecipient->isRead());

        $failedRecipient = new WhatsAppBulkCampaignRecipient([
            'status' => 'failed',
            'whatsapp_message_id' => null,
        ]);
        $this->assertTrue($failedRecipient->isFailed());
        $this->assertTrue($failedRecipient->canBeRetried());
    }
}
