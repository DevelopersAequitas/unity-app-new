<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\Events\EventQrService;
use App\Services\Events\EventRegistrationQrService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventQrCodeImageMimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpInMemoryDatabase();
        config(['app.url' => 'http://127.0.0.1:8000']);
        Storage::fake('public');
    }

    public function test_qr_code_generation_creates_png_file_and_png_url(): void
    {
        $event = Event::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'Sample Conference',
            'qr_checkin_enabled' => true,
        ]);

        $registration = EventRegistration::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'qr_token' => 'sample_token_123',
            'status' => 'registered',
            'payment_status' => 'not_required',
        ]);

        $qrService = app(EventQrService::class);
        $qrService->generateAndStore($registration);

        $registration->refresh();

        $this->assertStringEndsWith('.png', $registration->qr_code_path);
        $this->assertStringEndsWith('.png', $registration->qr_code_url);
        Storage::disk('public')->assertExists($registration->qr_code_path);

        $content = Storage::disk('public')->get($registration->qr_code_path);
        $this->assertStringStartsWith("\x89PNG", $content);
    }

    public function test_qr_code_url_endpoint_returns_content_type_image_png_and_valid_png_bytes(): void
    {
        $event = Event::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'Tech Summit',
            'qr_checkin_enabled' => true,
        ]);

        $registration = EventRegistration::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'status' => 'registered',
            'payment_status' => 'not_required',
        ]);

        $registration = app(EventRegistrationQrService::class)->ensureQrGenerated($registration);

        $qrUrl = $registration->qr_code_url;
        $this->assertNotNull($qrUrl);
        $this->assertStringEndsWith('.png', $qrUrl);

        $path = parse_url($qrUrl, PHP_URL_PATH);

        $response = $this->get($path);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/png');

        $body = $response->getContent();
        $this->assertStringStartsWith("\x89PNG", $body);
        $this->assertStringNotContainsString('<svg', $body);
    }

    public function test_fallback_streaming_returns_image_png_when_file_is_missing_from_disk(): void
    {
        $eventId = (string) Str::uuid();
        $registrationId = (string) Str::uuid();

        EventRegistration::query()->create([
            'id' => $registrationId,
            'event_id' => $eventId,
            'qr_token' => 'fallback_token_999',
            'status' => 'registered',
            'payment_status' => 'not_required',
        ]);

        // Do not generate physical file on disk to trigger direct streaming
        $response = $this->get('/api/v1/event-qrcodes/'.$eventId.'/'.$registrationId.'.png');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/png');

        $body = $response->getContent();
        $this->assertStringStartsWith("\x89PNG", $body);
        $this->assertStringNotContainsString('<svg', $body);
    }

    private function setUpInMemoryDatabase(): void
    {
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_occurrences');
        Schema::dropIfExists('events');

        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title')->nullable();
            $table->boolean('qr_checkin_enabled')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_occurrences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('occurrence_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->text('qr_token')->nullable();
            $table->text('qr_code_path')->nullable();
            $table->text('qr_code_url')->nullable();
            $table->text('qr_code_svg')->nullable();
            $table->timestamp('qr_generated_at')->nullable();
            $table->string('status')->nullable();
            $table->string('payment_status')->nullable();
            $table->boolean('payment_required')->default(false);
            $table->string('checkin_status')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
