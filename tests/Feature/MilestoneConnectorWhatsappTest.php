<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendMilestoneConnectorWhatsappJob;
use App\Models\FileModel;
use App\Models\User;
use App\Models\WhatsappTemplate;
use App\Services\Creative\IntroducedPeerCreativeGenerator;
use App\Services\Notifications\MilestoneConnectorWhatsappService;
use App\Services\Notifications\WhatsappNotificationService;
use App\Services\Referrals\ReferralService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MilestoneConnectorWhatsappTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTablesExist();
    }

    private function ensureTablesExist(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('files');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('milestone_badges');
        Schema::dropIfExists('introduced_peers');
        Schema::dropIfExists('referral_links');
        Schema::dropIfExists('notification_delivery_logs');

        Schema::create('whatsapp_templates', function (Blueprint $table): void {
            $table->uuid('id')->nullable();
            $table->string('template_key', 100)->nullable();
            $table->string('template_name', 100)->nullable();
            $table->string('language_code', 20)->default('en_IN');
            $table->string('webhook_url', 500)->nullable();
            $table->string('webhook_secret', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 255)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('secondary_mobile', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('profile_card_image_url', 2000)->nullable();
            $table->string('welcome_creative_url', 2000)->nullable();
            $table->string('connector_creative_url', 2000)->nullable();
            $table->string('growth_creative_url', 2000)->nullable();
            $table->string('company_name', 255)->nullable();
            $table->string('business_category_name', 255)->nullable();
            $table->uuid('business_category_id')->nullable();
            $table->string('city', 255)->nullable();
            $table->uuid('introduced_by')->nullable();
            $table->integer('members_introduced_count')->default(0);
            $table->string('contribution_award_name', 255)->nullable();
            $table->string('contribution_award_recognition', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('milestone_badges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('type');
            $table->integer('required_count');
            $table->string('badge_image_url');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('introduced_peers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('introducer_id');
            $table->uuid('peer_id');
            $table->string('status', 50)->default('completed');
            $table->timestamps();
        });

        Schema::create('referral_links', function (Blueprint $table): void {
            $table->id();
            $table->uuid('user_id');
            $table->string('referral_code', 100)->nullable();
            $table->string('token', 100)->nullable();
            $table->string('referral_link', 2000)->nullable();
            $table->timestamps();
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uploader_user_id')->nullable();
            $table->string('s3_key', 2000)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->bigInteger('size_bytes')->default(0);
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_delivery_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('channel')->default('whatsapp');
            $table->string('provider')->nullable();
            $table->string('status')->nullable();
            $table->json('request_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    private function createTemplate(): WhatsappTemplate
    {
        return WhatsappTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'template_key' => 'milestone_connector',
            'template_name' => 'milestone_connector_v2',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/85295ba0-fe2c-487f-93ef-ab7bb6124a3d',
            'webhook_secret' => 'PGU_MILESTONE_CONNECTOR_2026_9Kx7Lm2Qa8',
            'is_active' => true,
        ]);
    }

    public function test_job_sends_whatsapp_payload_with_valid_parameters(): void
    {
        Http::fake([
            'https://fleximsg.com/*' => Http::response([
                'success' => true,
                'message_status' => 'accepted',
            ], 200),
        ]);

        $this->createTemplate();

        $user = new User;
        $user->id = (string) Str::uuid();
        $user->name = 'Nitin Chavda';
        $user->first_name = 'Nitin';
        $user->last_name = 'Chavda';
        $user->phone_number = '9904978744';
        $user->phone = '9904978744';
        $user->members_introduced_count = 1;
        $user->save();

        $job = new SendMilestoneConnectorWhatsappJob($user->id);
        $job->handle(
            app(WhatsappNotificationService::class),
            app(ReferralService::class),
            app(IntroducedPeerCreativeGenerator::class)
        );

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://fleximsg.com/api/webhooks/85295ba0-fe2c-487f-93ef-ab7bb6124a3d'
                && $data['phone'] === '919904978744'
                && str_starts_with($data['header_media_url'], 'https://peersunity.com/storage/uploads/')
                && $data['body_param_1'] === 'Nitin Chavda'
                && $data['body_param_2'] === 'Nitin Chavda'
                && $data['body_param_3'] === 'https://peersunity.com/share?type=referrals';
        });
    }

    public function test_job_normalizes_10_digit_indian_mobile_number_to_91_prefix(): void
    {
        Http::fake([
            'https://fleximsg.com/*' => Http::response(['success' => true], 200),
        ]);

        $this->createTemplate();

        $user = new User;
        $user->id = (string) Str::uuid();
        $user->name = 'Piyush Vyada';
        $user->phone = '9265898194';
        $user->members_introduced_count = 1;
        $user->save();

        $job = new SendMilestoneConnectorWhatsappJob($user->id);
        $job->handle(
            app(WhatsappNotificationService::class),
            app(ReferralService::class),
            app(IntroducedPeerCreativeGenerator::class)
        );

        Http::assertSent(function ($request) {
            return $request->data()['phone'] === '919265898194';
        });
    }

    public function test_job_skips_when_phone_number_is_missing(): void
    {
        Http::fake();

        $user = new User;
        $user->id = (string) Str::uuid();
        $user->name = 'No Phone User';
        $user->phone = null;
        $user->secondary_mobile = null;
        $user->save();

        $job = new SendMilestoneConnectorWhatsappJob($user->id);
        $job->handle(
            app(WhatsappNotificationService::class),
            app(ReferralService::class),
            app(IntroducedPeerCreativeGenerator::class)
        );

        Http::assertNothingSent();
    }

    public function test_job_skips_when_user_not_found(): void
    {
        Http::fake();

        $job = new SendMilestoneConnectorWhatsappJob((string) Str::uuid());
        $job->handle(
            app(WhatsappNotificationService::class),
            app(ReferralService::class),
            app(IntroducedPeerCreativeGenerator::class)
        );

        Http::assertNothingSent();
    }

    public function test_job_constructs_correct_fleximsg_headers_and_payload_structure(): void
    {
        Http::fake([
            'https://fleximsg.com/*' => Http::response(['success' => true], 200),
        ]);

        $this->createTemplate();

        $user = new User;
        $user->id = (string) Str::uuid();
        $user->name = 'Test Member';
        $user->phone = '9876543210';
        $user->members_introduced_count = 1;
        $user->save();

        $job = new SendMilestoneConnectorWhatsappJob($user->id);
        $job->handle(
            app(WhatsappNotificationService::class),
            app(ReferralService::class),
            app(IntroducedPeerCreativeGenerator::class)
        );

        Http::assertSent(function ($request) {
            $hasSecretHeader = $request->hasHeader('X-Webhook-Secret', 'PGU_MILESTONE_CONNECTOR_2026_9Kx7Lm2Qa8');
            $data = $request->data();

            return $hasSecretHeader
                && isset($data['phone'])
                && isset($data['header_media_url'])
                && isset($data['body_param_1'])
                && isset($data['body_param_2'])
                && isset($data['body_param_3']);
        });
    }

    public function test_job_logs_failed_attempt_when_fleximsg_returns_error(): void
    {
        Http::fake([
            'https://fleximsg.com/*' => Http::response([
                'error' => 'Invalid webhook secret',
            ], 403),
        ]);

        $this->createTemplate();

        $user = new User;
        $user->id = (string) Str::uuid();
        $user->name = 'Test Member';
        $user->phone = '9876543210';
        $user->members_introduced_count = 1;
        $user->save();

        $job = new SendMilestoneConnectorWhatsappJob($user->id);
        $job->handle(
            app(WhatsappNotificationService::class),
            app(ReferralService::class),
            app(IntroducedPeerCreativeGenerator::class)
        );

        Http::assertSentCount(1);
        $this->assertDatabaseHas('notification_delivery_logs', [
            'user_id' => $user->id,
            'provider' => 'milestone_connector',
            'status' => 'failed',
        ]);
    }

    public function test_milestone_connector_service_dispatches_job_for_first_introduction(): void
    {
        Queue::fake();

        $introducer = new User;
        $introducer->id = (string) Str::uuid();
        $introducer->name = 'Introducer User';
        $introducer->phone = '9904978744';
        $introducer->members_introduced_count = 1;
        $introducer->save();

        $service = app(MilestoneConnectorWhatsappService::class);
        $service->handleFirstIntroduction($introducer);

        Queue::assertPushed(SendMilestoneConnectorWhatsappJob::class, function ($job) use ($introducer) {
            return $job->userId === $introducer->id;
        });
    }

    public function test_milestone_connector_service_skips_when_count_is_not_one(): void
    {
        Queue::fake();

        $introducer = new User;
        $introducer->id = (string) Str::uuid();
        $introducer->name = 'Introducer User';
        $introducer->phone = '9904978744';
        $introducer->members_introduced_count = 5;
        $introducer->save();

        $service = app(MilestoneConnectorWhatsappService::class);
        $service->handleFirstIntroduction($introducer);

        Queue::assertNotPushed(SendMilestoneConnectorWhatsappJob::class);
    }

    public function test_job_generates_personalized_creative_image(): void
    {
        $user = new User;
        $user->id = (string) Str::uuid();
        $user->name = 'Piyush Vyada';
        $user->first_name = 'Piyush';
        $user->last_name = 'Vyada';
        $user->phone = '9265898194';
        $user->company_name = 'Vyada Technologies';
        $user->business_category_name = 'Information Technology';
        $user->city = 'Surat';
        $user->members_introduced_count = 1;
        $user->save();

        $generator = app(IntroducedPeerCreativeGenerator::class);
        $publicUrl = $generator->generateOrGetUrl($user, 1);

        $this->assertNotNull($publicUrl);
        $this->assertStringStartsWith('https://peersunity.com/storage/', $publicUrl);
        $this->assertStringEndsWith('.png', $publicUrl);
        $this->assertStringNotContainsString('/api/v1/files/', $publicUrl);
    }

    public function test_media_url_validation_rules(): void
    {
        $job = new SendMilestoneConnectorWhatsappJob((string) Str::uuid());

        Storage::disk('public')->put('uploads/2026/09/03/creative.png', 'sample_content');

        $this->assertTrue($job->isValidPublicMediaUrl('https://peersunity.com/images/member_introduce_badges/Connector.png'));
        $this->assertTrue($job->isValidPublicMediaUrl('https://peersunity.com/storage/uploads/2026/09/03/creative.png'));

        $this->assertFalse($job->isValidPublicMediaUrl('http://peersunity.com/images/Connector.png'));
        $this->assertFalse($job->isValidPublicMediaUrl('https://peersunity.com/api/v1/files/01a065c0-bddc-72bd-af8c-bb2bd67c2a19'));
        $this->assertFalse($job->isValidPublicMediaUrl('https://localhost/images/Connector.png'));
        $this->assertFalse($job->isValidPublicMediaUrl('https://armband-unrelated-bonanza.ngrok-free.dev/api/v1/files/uuid'));
        $this->assertFalse($job->isValidPublicMediaUrl(''));
    }

    public function test_generated_creative_physical_file_properties_and_public_url_format(): void
    {
        $user = new User;
        $user->id = (string) Str::uuid();
        $user->name = 'Piyush Vyada';
        $user->first_name = 'Piyush';
        $user->last_name = 'Vyada';
        $user->phone = '9265898194';
        $user->company_name = 'Vyada Technologies';
        $user->business_category_name = 'Information Technology';
        $user->city = 'Surat';
        $user->members_introduced_count = 1;
        $user->save();

        $generator = app(IntroducedPeerCreativeGenerator::class);
        $publicUrl = $generator->generateOrGetUrl($user, 1);

        $this->assertNotNull($publicUrl);
        $this->assertStringStartsWith('https://peersunity.com/storage/', $publicUrl);
        $this->assertStringEndsWith('.png', $publicUrl);
        $this->assertStringNotContainsString('/api/v1/files/', $publicUrl);

        // A. Generated file physically exists on disk
        $s3Key = preg_replace('#^https?://[^/]+/storage/#i', '', $publicUrl);
        $fileRecord = FileModel::where('s3_key', $s3Key)->first();
        $this->assertNotNull($fileRecord);
        $this->assertTrue(Storage::disk('public')->exists($fileRecord->s3_key));
        $this->assertTrue(file_exists(public_path('storage/'.$fileRecord->s3_key)));

        // B. Generated file has PNG content
        $physicalPath = public_path('storage/'.$fileRecord->s3_key);
        $fileBytes = file_get_contents($physicalPath);
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $fileBytes, 'File header must match PNG binary magic bytes');

        // C. Generated file has expected dimensions (1080x1350)
        [$imgWidth, $imgHeight] = getimagesize($physicalPath);
        $this->assertEquals(1080, $imgWidth);
        $this->assertEquals(1350, $imgHeight);
        $this->assertEquals('image/png', $fileRecord->mime_type);
        $this->assertGreaterThan(10000, $fileRecord->size_bytes);
    }

    public function test_different_users_get_different_personalized_creatives(): void
    {
        $userA = new User;
        $userA->id = (string) Str::uuid();
        $userA->name = 'Nitin Chavda';
        $userA->phone = '9904978744';
        $userA->save();

        $userB = new User;
        $userB->id = (string) Str::uuid();
        $userB->name = 'Piyush Vyada';
        $userB->phone = '9265898194';
        $userB->save();

        $generator = app(IntroducedPeerCreativeGenerator::class);
        $urlA = $generator->generateOrGetUrl($userA, 1);
        $urlB = $generator->generateOrGetUrl($userB, 1);

        $this->assertNotNull($urlA);
        $this->assertNotNull($urlB);
        $this->assertNotEquals($urlA, $urlB, 'Different users should receive distinct creative URLs');
    }

    public function test_creative_url_generation_is_environment_aware(): void
    {
        $generator = app(IntroducedPeerCreativeGenerator::class);

        // DEV environment simulation
        config(['app.url' => 'https://dev.example.test', 'app.public_url' => null]);
        $userDev = new User;
        $userDev->id = (string) Str::uuid();
        $userDev->name = 'Dev User';
        $userDev->phone = '9876543210';
        $userDev->save();

        $devUrl = $generator->generateOrGetUrl($userDev, 1);
        $this->assertStringStartsWith('https://dev.example.test/storage/uploads/', $devUrl);
        $this->assertStringEndsWith('.png', $devUrl);

        // LIVE environment simulation
        config(['app.url' => 'https://peersunity.com', 'app.public_url' => null]);
        $userLive = new User;
        $userLive->id = (string) Str::uuid();
        $userLive->name = 'Live User';
        $userLive->phone = '9876543211';
        $userLive->save();

        $liveUrl = $generator->generateOrGetUrl($userLive, 1);
        $this->assertStringStartsWith('https://peersunity.com/storage/uploads/', $liveUrl);
        $this->assertStringEndsWith('.png', $liveUrl);
    }

    public function test_job_uses_configured_dev_app_url_for_payload_and_referral_link(): void
    {
        Http::fake([
            'https://fleximsg.com/*' => Http::response(['success' => true], 200),
        ]);

        config(['app.url' => 'https://dev.peersunity.com', 'app.public_url' => null]);
        $this->createTemplate();

        $user = new User;
        $user->id = (string) Str::uuid();
        $user->name = 'Dev Connector Member';
        $user->phone = '9265898194';
        $user->members_introduced_count = 1;
        $user->save();

        $job = new SendMilestoneConnectorWhatsappJob($user->id);
        $job->handle(
            app(WhatsappNotificationService::class),
            app(ReferralService::class),
            app(IntroducedPeerCreativeGenerator::class)
        );

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $data['phone'] === '919265898194'
                && str_starts_with($data['header_media_url'], 'https://dev.peersunity.com/storage/uploads/')
                && $data['body_param_3'] === 'https://dev.peersunity.com/share?type=referrals';
        });
    }

    public function test_media_url_validation_accepts_dev_and_live_domains_and_rejects_invalid(): void
    {
        $job = new SendMilestoneConnectorWhatsappJob((string) Str::uuid());

        // Environment-aware valid HTTPS domains
        $this->assertTrue($job->isValidPublicMediaUrl('https://dev.peersunity.com/images/member_introduce_badges/Connector.png'));
        $this->assertTrue($job->isValidPublicMediaUrl('https://peersunity.com/images/member_introduce_badges/Connector.png'));
        $this->assertTrue($job->isValidPublicMediaUrl('https://dev.example.test/images/member_introduce_badges/Connector.png'));

        // Invalid domains / protocols
        $this->assertFalse($job->isValidPublicMediaUrl('http://dev.peersunity.com/images/Connector.png'));
        $this->assertFalse($job->isValidPublicMediaUrl('https://localhost/images/Connector.png'));
        $this->assertFalse($job->isValidPublicMediaUrl('https://127.0.0.1/images/Connector.png'));
        $this->assertFalse($job->isValidPublicMediaUrl('https://sample.ngrok-free.app/storage/uploads/sample.png'));
        $this->assertFalse($job->isValidPublicMediaUrl('https://dev.peersunity.com/api/v1/files/01a065c0-bddc-72bd-af8c-bb2bd67c2a19'));
    }
}
