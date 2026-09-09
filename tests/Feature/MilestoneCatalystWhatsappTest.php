<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendMilestoneCatalystWhatsappJob;
use App\Models\FileModel;
use App\Models\IntroductionCreative;
use App\Models\User;
use App\Models\WhatsappTemplate;
use App\Services\Creative\IntroducedPeerCreativeGenerator;
use App\Services\Notifications\MilestoneCatalystWhatsappService;
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

class MilestoneCatalystWhatsappTest extends TestCase
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
        Schema::dropIfExists('introduction_creatives');

        Schema::create('introduction_creatives', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('introduction_request_id')->nullable();
            $table->uuid('introducer_id');
            $table->uuid('requester_id')->nullable();
            $table->integer('introduced_count')->default(3);
            $table->string('image_url', 2000)->nullable();
            $table->timestamps();
        });

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
            $table->string('provider_message_id')->nullable();
            $table->string('status')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
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
            'template_key' => 'pgu_catalyst_3',
            'template_name' => 'pgu_catalyst_3',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/5176b75b-a639-4dc0-891e-0d0e503dcc31',
            'webhook_secret' => 'PGU_MILESTONE_CATALYST_2026_SECRET',
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
        $user->name = 'Vinit Patel';
        $user->first_name = 'Vinit';
        $user->last_name = 'Patel';
        $user->phone_number = '9904978744';
        $user->phone = '9904978744';
        $user->members_introduced_count = 3;
        $user->save();

        $job = new SendMilestoneCatalystWhatsappJob($user->id);
        $job->handle(
            app(WhatsappNotificationService::class),
            app(ReferralService::class),
            app(IntroducedPeerCreativeGenerator::class)
        );

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://fleximsg.com/api/webhooks/5176b75b-a639-4dc0-891e-0d0e503dcc31'
                && $data['phone'] === '919904978744'
                && str_starts_with($data['header_media_url'], 'https://peersunity.com/storage/uploads/')
                && $data['body_param_1'] === 'Vinit Patel'
                && $data['body_param_2'] === 'Vinit Patel'
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
        $user->members_introduced_count = 3;
        $user->save();

        $job = new SendMilestoneCatalystWhatsappJob($user->id);
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
        $user->members_introduced_count = 3;
        $user->save();

        $job = new SendMilestoneCatalystWhatsappJob($user->id);
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

        $job = new SendMilestoneCatalystWhatsappJob((string) Str::uuid());
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
        $user->name = 'Catalyst Member';
        $user->phone = '9876543210';
        $user->members_introduced_count = 3;
        $user->save();

        $job = new SendMilestoneCatalystWhatsappJob($user->id);
        $job->handle(
            app(WhatsappNotificationService::class),
            app(ReferralService::class),
            app(IntroducedPeerCreativeGenerator::class)
        );

        Http::assertSent(function ($request) {
            $data = $request->data();

            return isset($data['phone'])
                && isset($data['header_media_url'])
                && isset($data['body_param_1'])
                && isset($data['body_param_2'])
                && isset($data['body_param_3'])
                && $data['milestone'] === 'Catalyst';
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
        $user->name = 'Catalyst Member';
        $user->phone = '9876543210';
        $user->members_introduced_count = 3;
        $user->save();

        $job = new SendMilestoneCatalystWhatsappJob($user->id);
        $job->handle(
            app(WhatsappNotificationService::class),
            app(ReferralService::class),
            app(IntroducedPeerCreativeGenerator::class)
        );

        Http::assertSentCount(1);
        $this->assertDatabaseHas('notification_delivery_logs', [
            'user_id' => $user->id,
            'provider' => 'pgu_catalyst_3',
            'status' => 'failed',
        ]);
    }

    public function test_milestone_catalyst_service_dispatches_job_for_third_introduction(): void
    {
        Queue::fake();

        $introducer = new User;
        $introducer->id = (string) Str::uuid();
        $introducer->name = 'Catalyst User';
        $introducer->phone = '9904978744';
        $introducer->members_introduced_count = 3;
        $introducer->save();

        $service = app(MilestoneCatalystWhatsappService::class);
        $service->handleCatalystMilestone($introducer);

        Queue::assertPushed(SendMilestoneCatalystWhatsappJob::class, function ($job) use ($introducer) {
            return $job->userId === $introducer->id;
        });
    }

    public function test_milestone_catalyst_service_skips_when_count_is_less_than_three(): void
    {
        Queue::fake();

        $introducer = new User;
        $introducer->id = (string) Str::uuid();
        $introducer->name = 'Under Threshold User';
        $introducer->phone = '9904978744';
        $introducer->members_introduced_count = 2;
        $introducer->save();

        $service = app(MilestoneCatalystWhatsappService::class);
        $service->handleCatalystMilestone($introducer);

        Queue::assertNotPushed(SendMilestoneCatalystWhatsappJob::class);
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
        $user->members_introduced_count = 3;
        $user->save();

        $generator = app(IntroducedPeerCreativeGenerator::class);
        $publicUrl = $generator->generateOrGetUrl($user, 3);

        $this->assertNotNull($publicUrl);
        $this->assertStringStartsWith('https://peersunity.com/storage/', $publicUrl);
        $this->assertStringEndsWith('.png', $publicUrl);
        $this->assertStringNotContainsString('/api/v1/files/', $publicUrl);
    }

    public function test_media_url_validation_rules(): void
    {
        $job = new SendMilestoneCatalystWhatsappJob((string) Str::uuid());

        Storage::disk('public')->put('uploads/2026/09/03/creative_catalyst.png', 'sample_content');

        $this->assertTrue($job->isValidPublicMediaUrl('https://peersunity.com/storage/uploads/2026/09/03/creative_catalyst.png'));

        // Rejects raw template
        $this->assertFalse($job->isValidPublicMediaUrl('https://peersunity.com/images/member_introduce_badges/Catalyst.png'));
        $this->assertFalse($job->isValidPublicMediaUrl('http://peersunity.com/images/Catalyst.png'));
        $this->assertFalse($job->isValidPublicMediaUrl('https://peersunity.com/api/v1/files/01a065c0-bddc-72bd-af8c-bb2bd67c2a19'));
        $this->assertFalse($job->isValidPublicMediaUrl('https://localhost/images/Catalyst.png'));
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
        $user->members_introduced_count = 3;
        $user->save();

        $generator = app(IntroducedPeerCreativeGenerator::class);
        $publicUrl = $generator->generateOrGetUrl($user, 3);

        $this->assertNotNull($publicUrl);
        $this->assertStringStartsWith('https://peersunity.com/storage/', $publicUrl);
        $this->assertStringEndsWith('.png', $publicUrl);
        $this->assertStringNotContainsString('/api/v1/files/', $publicUrl);

        $s3Key = preg_replace('#^https?://[^/]+/storage/#i', '', $publicUrl);
        $fileRecord = FileModel::where('s3_key', $s3Key)->first();
        $this->assertNotNull($fileRecord);
        $this->assertTrue(Storage::disk('public')->exists($fileRecord->s3_key));
        $this->assertTrue(file_exists(public_path('storage/'.$fileRecord->s3_key)));

        $physicalPath = public_path('storage/'.$fileRecord->s3_key);
        $fileBytes = file_get_contents($physicalPath);
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $fileBytes, 'File header must match PNG binary magic bytes');

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
        $userA->members_introduced_count = 3;
        $userA->save();

        $userB = new User;
        $userB->id = (string) Str::uuid();
        $userB->name = 'Piyush Vyada';
        $userB->phone = '9265898194';
        $userB->members_introduced_count = 3;
        $userB->save();

        $generator = app(IntroducedPeerCreativeGenerator::class);
        $urlA = $generator->generateOrGetUrl($userA, 3);
        $urlB = $generator->generateOrGetUrl($userB, 3);

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
        $userDev->members_introduced_count = 3;
        $userDev->save();

        $devUrl = $generator->generateOrGetUrl($userDev, 3);
        $this->assertStringStartsWith('https://dev.example.test/storage/uploads/', $devUrl);
        $this->assertStringEndsWith('.png', $devUrl);

        // LIVE environment simulation
        config(['app.url' => 'https://peersunity.com', 'app.public_url' => null]);
        $userLive = new User;
        $userLive->id = (string) Str::uuid();
        $userLive->name = 'Live User';
        $userLive->phone = '9876543211';
        $userLive->members_introduced_count = 3;
        $userLive->save();

        $liveUrl = $generator->generateOrGetUrl($userLive, 3);
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
        $user->name = 'Dev Catalyst Member';
        $user->phone = '9265898194';
        $user->members_introduced_count = 3;
        $user->save();

        $job = new SendMilestoneCatalystWhatsappJob($user->id);
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

    public function test_job_uses_existing_valid_creative_from_introduction_creatives_without_regenerating(): void
    {
        Http::fake([
            'https://fleximsg.com/*' => Http::response(['success' => true], 200),
        ]);

        $this->createTemplate();

        $user = new User;
        $user->id = (string) Str::uuid();
        $user->name = 'Pre Existing Catalyst User';
        $user->phone = '9904978744';
        $user->members_introduced_count = 3;
        $user->save();

        // Create a fake existing personalized PNG on public disk
        $relativeS3Key = 'uploads/2026/09/09/pre_existing_catalyst_creative.png';
        Storage::disk('public')->put($relativeS3Key, "\x89PNG\r\n\x1a\nfake_content");
        $existingUrl = 'https://peersunity.com/storage/'.$relativeS3Key;

        $creative = IntroductionCreative::create([
            'id' => (string) Str::uuid(),
            'introducer_id' => $user->id,
            'introduced_count' => 3,
            'image_url' => $existingUrl,
        ]);

        $job = new SendMilestoneCatalystWhatsappJob($user->id);
        $job->handle(
            app(WhatsappNotificationService::class),
            app(ReferralService::class),
            app(IntroducedPeerCreativeGenerator::class)
        );

        Http::assertSent(function ($request) use ($existingUrl) {
            return $request->data()['header_media_url'] === $existingUrl;
        });

        // Verify introduction creative record was preserved with the same URL
        $this->assertEquals($existingUrl, $creative->fresh()->image_url);
    }

    public function test_job_heals_missing_physical_file_when_creative_record_exists(): void
    {
        Http::fake([
            'https://fleximsg.com/*' => Http::response(['success' => true], 200),
        ]);

        $this->createTemplate();

        $user = new User;
        $user->id = (string) Str::uuid();
        $user->name = 'Healed Catalyst User';
        $user->phone = '9904978744';
        $user->members_introduced_count = 3;
        $user->save();

        // Point to non-existent physical file
        $staleUrl = 'https://peersunity.com/storage/uploads/2026/09/09/missing_catalyst_file.png';
        $creative = IntroductionCreative::create([
            'id' => (string) Str::uuid(),
            'introducer_id' => $user->id,
            'introduced_count' => 3,
            'image_url' => $staleUrl,
        ]);

        $job = new SendMilestoneCatalystWhatsappJob($user->id);
        $job->handle(
            app(WhatsappNotificationService::class),
            app(ReferralService::class),
            app(IntroducedPeerCreativeGenerator::class)
        );

        Http::assertSent(function ($request) use ($staleUrl) {
            $mediaUrl = $request->data()['header_media_url'];

            return $mediaUrl !== $staleUrl
                && str_starts_with($mediaUrl, 'https://peersunity.com/storage/uploads/')
                && str_ends_with($mediaUrl, '.png');
        });

        // Verify introduction creative record was updated with the regenerated valid URL
        $updatedUrl = $creative->fresh()->image_url;
        $this->assertNotEquals($staleUrl, $updatedUrl);
        $this->assertStringStartsWith('https://peersunity.com/storage/uploads/', $updatedUrl);
    }

    public function test_job_rejects_raw_canva_template_in_introduction_creatives_and_regenerates_personalized(): void
    {
        Http::fake([
            'https://fleximsg.com/*' => Http::response(['success' => true], 200),
        ]);

        $this->createTemplate();

        $user = new User;
        $user->id = (string) Str::uuid();
        $user->name = 'Canva Rejection Catalyst User';
        $user->phone = '9904978744';
        $user->members_introduced_count = 3;
        $user->save();

        $rawCanvaUrl = 'https://peersunity.com/images/member_introduce_badges/Catalyst.png';
        $creative = IntroductionCreative::create([
            'id' => (string) Str::uuid(),
            'introducer_id' => $user->id,
            'introduced_count' => 3,
            'image_url' => $rawCanvaUrl,
        ]);

        $job = new SendMilestoneCatalystWhatsappJob($user->id);
        $job->handle(
            app(WhatsappNotificationService::class),
            app(ReferralService::class),
            app(IntroducedPeerCreativeGenerator::class)
        );

        Http::assertSent(function ($request) use ($rawCanvaUrl) {
            $mediaUrl = $request->data()['header_media_url'];

            // Must NOT be the raw Canva badge URL
            return $mediaUrl !== $rawCanvaUrl
                && ! str_contains($mediaUrl, '/images/member_introduce_badges/')
                && str_starts_with($mediaUrl, 'https://peersunity.com/storage/uploads/')
                && str_ends_with($mediaUrl, '.png');
        });

        // Creative record must have been updated to the personalized creative
        $updatedUrl = $creative->fresh()->image_url;
        $this->assertNotEquals($rawCanvaUrl, $updatedUrl);
        $this->assertStringStartsWith('https://peersunity.com/storage/uploads/', $updatedUrl);
    }
}
