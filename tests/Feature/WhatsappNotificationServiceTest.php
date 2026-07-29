<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\WhatsappTemplate;
use App\Services\Notifications\WhatsappNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappNotificationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('whatsapp_templates');

        Schema::create('whatsapp_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('template_key')->unique();
            $table->string('template_name');
            $table->string('webhook_url');
            $table->string('webhook_secret');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_phone_normalization(): void
    {
        $this->assertSame('919876543210', WhatsappNotificationService::normalizePhone('9876543210'));
        $this->assertSame('919876543210', WhatsappNotificationService::normalizePhone('919876543210'));
        $this->assertSame('919876543210', WhatsappNotificationService::normalizePhone('+919876543210'));
        $this->assertSame('919876543210', WhatsappNotificationService::normalizePhone('09876543210'));
    }

    public function test_send_success(): void
    {
        Http::fake([
            'https://fleximsg.com/api/webhooks/*' => Http::response(['success' => true], 200),
        ]);

        WhatsappTemplate::query()->create([
            'id' => '7e2bfb69-ae58-4c30-95b0-b682aba34357',
            'template_key' => 'otp_verification',
            'template_name' => 'OTP Verification',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/65585c7b-c5c3-401c-b73f-5092dbe3a781',
            'webhook_secret' => 'SECRET_KEY_123',
            'is_active' => true,
        ]);

        $service = new WhatsappNotificationService;
        $result = $service->send('otp_verification', '9876543210', ['code' => '4829']);

        $this->assertTrue($result);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://fleximsg.com/api/webhooks/65585c7b-c5c3-401c-b73f-5092dbe3a781'
                && $request->hasHeader('X-Webhook-Secret', 'SECRET_KEY_123')
                && $request['phone'] === '919876543210'
                && $request['code'] === '4829';
        });
    }

    public function test_send_returns_false_when_template_is_inactive(): void
    {
        Http::fake();

        WhatsappTemplate::query()->create([
            'id' => '7e2bfb69-ae58-4c30-95b0-b682aba34357',
            'template_key' => 'otp_verification',
            'template_name' => 'OTP Verification',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/test',
            'webhook_secret' => 'SECRET_KEY_123',
            'is_active' => false,
        ]);

        $service = new WhatsappNotificationService;
        $result = $service->send('otp_verification', '9876543210', ['code' => '4829']);

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_send_returns_false_on_http_error_without_throwing(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Server Error'], 500),
        ]);

        WhatsappTemplate::query()->create([
            'id' => '7e2bfb69-ae58-4c30-95b0-b682aba34357',
            'template_key' => 'otp_verification',
            'template_name' => 'OTP Verification',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/test',
            'webhook_secret' => 'SECRET_KEY_123',
            'is_active' => true,
        ]);

        $service = new WhatsappNotificationService;
        $result = $service->send('otp_verification', '9876543210', ['code' => '4829']);

        $this->assertFalse($result);
    }
}
