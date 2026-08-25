<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendWearTheBadgeWhatsappJob;
use App\Models\FileModel;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class WearTheBadgeWhatsappTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabaseSchema();

        WhatsappTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'template_key' => 'wear_the_badge',
            'template_name' => 'Wear The Badge Template',
            'webhook_url' => 'https://webhook.example.com/whatsapp/wear_the_badge',
            'webhook_secret' => 'TEST_SECRET_WEAR',
            'is_active' => true,
        ]);

        WhatsappTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'template_key' => 'welcome',
            'template_name' => 'Welcome Template',
            'webhook_url' => 'https://webhook.example.com/whatsapp/welcome',
            'webhook_secret' => 'TEST_SECRET_WELCOME',
            'is_active' => true,
        ]);
    }

    private function setUpDatabaseSchema(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('files');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('notification_delivery_logs');
        Schema::dropIfExists('posts');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 255)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('secondary_mobile', 50)->nullable();
            $table->string('gender', 20)->nullable();
            $table->date('dob')->nullable();
            $table->date('anniversary_date')->nullable();
            $table->string('city', 255)->nullable();
            $table->uuid('city_id')->nullable();
            $table->string('city_of_residence', 255)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->uuid('profile_photo_file_id')->nullable();
            $table->uuid('profile_photo_id')->nullable();
            $table->string('profile_photo_url', 2000)->nullable();
            $table->uuid('cover_photo_file_id')->nullable();
            $table->uuid('cover_photo_id')->nullable();
            $table->uuid('profile_video_id')->nullable();
            $table->uuid('intro_video_id')->nullable();
            $table->text('short_bio')->nullable();
            $table->text('long_bio_html')->nullable();
            $table->text('experience_summary')->nullable();

            $table->string('company_name', 255)->nullable();
            $table->string('designation', 255)->nullable();
            $table->uuid('business_category_id')->nullable();
            $table->uuid('main_business_category_id')->nullable();
            $table->string('business_sub_category', 255)->nullable();
            $table->string('company_type', 100)->nullable();
            $table->string('business_type', 100)->nullable();
            $table->integer('year_of_establishment')->nullable();
            $table->string('annual_revenue_range', 100)->nullable();
            $table->string('turnover_range', 100)->nullable();
            $table->string('number_of_employees', 100)->nullable();
            $table->string('gst_number', 100)->nullable();
            $table->string('business_website', 255)->nullable();
            $table->string('website', 255)->nullable();
            $table->text('superpower')->nullable();
            $table->json('i_can_help_with')->nullable();
            $table->json('i_am_looking_for')->nullable();
            $table->json('business_keywords')->nullable();
            $table->json('products_services_offered')->nullable();
            $table->text('business_address')->nullable();
            $table->string('business_pincode', 20)->nullable();
            $table->uuid('business_logo_id')->nullable();

            $table->json('skills')->nullable();
            $table->json('interests')->nullable();
            $table->json('hobbies_interests')->nullable();
            $table->json('industries_of_interest')->nullable();
            $table->json('collaboration_goals')->nullable();

            $table->string('linkedin_profile', 255)->nullable();
            $table->string('instagram_handle', 255)->nullable();
            $table->string('twitter_handle', 255)->nullable();
            $table->string('facebook_profile', 255)->nullable();
            $table->string('youtube_channel', 255)->nullable();
            $table->string('other_website', 255)->nullable();
            $table->json('social_links')->nullable();

            $table->string('contact_visibility', 50)->nullable();
            $table->string('membership_status', 50)->default('visitor');
            $table->timestamp('membership_expiry')->nullable();
            $table->timestamp('membership_starts_at')->nullable();
            $table->timestamp('membership_ends_at')->nullable();
            $table->timestamp('last_payment_at')->nullable();
            $table->string('welcome_creative_url', 2000)->nullable();
            $table->string('profile_card_image_url', 2000)->nullable();
            $table->string('status', 50)->default('active');
            $table->string('public_profile_slug', 80)->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uploader_user_id')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->bigInteger('size_bytes')->default(0);
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('duration')->nullable();
            $table->string('s3_key', 2000)->nullable();
            $table->boolean('is_orphaned')->default(false);
            $table->timestamps();
        });

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

        Schema::create('notification_delivery_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('notification_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->uuid('campaign_id')->nullable();
            $table->string('channel')->nullable();
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

        Schema::create('posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->json('media')->nullable();
            $table->string('post_type', 50)->nullable();
            $table->string('status', 50)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function get100PercentProfileData(): array
    {
        return [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '1234567890',
            'gender' => 'Male',
            'dob' => '1990-01-01',
            'anniversary_date' => '2015-05-15',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'country' => 'India',
            'profile_photo_url' => 'http://example.com/photo.jpg',
            'cover_photo_id' => (string) Str::uuid(),
            'profile_video_id' => (string) Str::uuid(),
            'short_bio' => 'A short bio of John Doe.',
            'company_name' => 'Acme Corp',
            'designation' => 'CEO',
            'business_category_id' => (string) Str::uuid(),
            'business_sub_category' => 'Technology',
            'company_type' => 'Private',
            'business_type' => 'Service',
            'year_of_establishment' => 2010,
            'annual_revenue_range' => '1M-5M',
            'number_of_employees' => '10-50',
            'gst_number' => '27AAACA1234A1Z1',
            'business_website' => 'http://acme.com',
            'superpower' => 'Networking',
            'i_can_help_with' => ['Sales', 'Marketing'],
            'i_am_looking_for' => ['Funding', 'Partners'],
            'business_keywords' => ['tech', 'startup'],
            'products_services_offered' => 'Software services',
            'business_address' => '123 Tech Park',
            'business_pincode' => '400001',
            'business_logo_id' => (string) Str::uuid(),
            'skills' => ['PHP', 'Laravel'],
            'interests' => ['Reading', 'Hiking'],
            'linkedin_profile' => 'https://linkedin.com/in/johndoe',
            'instagram_handle' => 'johndoe_insta',
            'twitter_handle' => 'johndoe_tweets',
            'facebook_profile' => 'https://facebook.com/johndoe',
            'youtube_channel' => 'https://youtube.com/johndoe',
            'other_website' => 'http://johndoe.me',
            'contact_visibility' => 'connections',
        ];
    }

    public function test_registration_creates_the_creative(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'display_name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '1234567890',
        ]);

        $this->assertNotNull($user->welcome_creative_url);
        $this->assertNotNull($user->profile_card_image_url);

        $uuid = null;
        if (preg_match('/\/api\/v1\/files\/([0-9a-fA-F-]{36})/', $user->welcome_creative_url, $matches)) {
            $uuid = $matches[1];
        }
        $this->assertNotNull($uuid);

        $fileRecord = FileModel::find($uuid);
        $this->assertNotNull($fileRecord);

        $disk = config('filesystems.default', 'public');
        $this->assertTrue(Storage::disk($disk)->exists($fileRecord->s3_key));
    }

    public function test_profile_reaching_100_percent_dispatches_job(): void
    {
        Queue::fake();

        // Create user with incomplete profile (e.g. 20%)
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Test',
            'email' => 'test@example.com',
            'phone' => '9876543210',
        ]);

        Queue::assertNotPushed(SendWearTheBadgeWhatsappJob::class);

        // Update profile fields to reach 100% completion
        $user->forceFill($this->get100PercentProfileData())->save();

        Queue::assertPushed(SendWearTheBadgeWhatsappJob::class, function (SendWearTheBadgeWhatsappJob $job) use ($user): bool {
            return $job->userId === (string) $user->id;
        });
    }

    public function test_first_payment_condition_dispatches_job_if_paid_membership_status(): void
    {
        Queue::fake();

        // Create user with incomplete profile
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'TestPayment',
            'email' => 'testpayment@example.com',
            'phone' => '9876543211',
            'membership_status' => 'visitor',
        ]);

        Queue::assertNotPushed(SendWearTheBadgeWhatsappJob::class);

        // Update membership status to a paid one
        $user->membership_status = 'Circle Peer';
        $user->save();

        Queue::assertPushed(SendWearTheBadgeWhatsappJob::class, function (SendWearTheBadgeWhatsappJob $job) use ($user): bool {
            return $job->userId === (string) $user->id;
        });
    }

    public function test_first_payment_condition_dispatches_job_if_last_payment_at_set(): void
    {
        Queue::fake();

        // Create user with incomplete profile
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'TestPaymentAt',
            'email' => 'testpaymentat@example.com',
            'phone' => '9876543212',
            'membership_status' => 'visitor',
        ]);

        Queue::assertNotPushed(SendWearTheBadgeWhatsappJob::class);

        // Set last_payment_at
        $user->last_payment_at = now();
        $user->save();

        Queue::assertPushed(SendWearTheBadgeWhatsappJob::class, function (SendWearTheBadgeWhatsappJob $job) use ($user): bool {
            return $job->userId === (string) $user->id;
        });
    }

    public function test_incomplete_profile_does_not_dispatch_job(): void
    {
        Queue::fake();

        // Create user
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'IncompleteUser',
            'email' => 'incomplete@example.com',
            'phone' => '9876543213',
        ]);

        Queue::assertNotPushed(SendWearTheBadgeWhatsappJob::class);

        // Add some but not all profile data (e.g. 50%)
        $user->company_name = 'Acme Corp';
        $user->designation = 'CEO';
        $user->save();

        Queue::assertNotPushed(SendWearTheBadgeWhatsappJob::class);
    }

    public function test_duplicate_updates_do_not_send_duplicate_messages(): void
    {
        Queue::fake();

        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'DuplicateUser',
            'email' => 'duplicate@example.com',
            'phone' => '9876543214',
        ]);

        // Reach 100% profile completion -> first dispatch
        $user->forceFill($this->get100PercentProfileData())->save();
        Queue::assertPushed(SendWearTheBadgeWhatsappJob::class, 1);

        // Create a delivery log representing successful send
        NotificationDeliveryLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'channel' => 'whatsapp',
            'provider' => 'wear_the_badge',
            'status' => 'sent',
            'attempted_at' => now(),
            'delivered_at' => now(),
        ]);

        // Another update on the user model -> should NOT dispatch again since alreadySent is true
        $user->last_name = 'Smith';
        $user->save();

        // Assert job was still only pushed once total
        Queue::assertPushed(SendWearTheBadgeWhatsappJob::class, 1);
    }

    public function test_job_receives_correct_user_and_real_welcome_creative_url(): void
    {
        Http::fake([
            'https://webhook.example.com/whatsapp/wear_the_badge' => Http::response(['success' => true], 200),
        ]);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'RealUrlUser',
            'last_name' => 'Doe',
            'display_name' => 'RealUrlUser Doe',
            'email' => 'realurl@example.com',
            'phone' => '9876543215',
        ]);

        $this->assertNotNull($user->welcome_creative_url);

        // Dispatch job synchronously
        SendWearTheBadgeWhatsappJob::dispatchSync($user->id);

        Http::assertSent(function (Request $request) use ($user): bool {
            return $request->url() === 'https://webhook.example.com/whatsapp/wear_the_badge'
                && $request['phone'] === '919876543215'
                && $request['welcome_creative_url'] === $user->welcome_creative_url;
        });

        // Verify notification delivery log was created
        $this->assertDatabaseHas('notification_delivery_logs', [
            'user_id' => $user->id,
            'channel' => 'whatsapp',
            'provider' => 'wear_the_badge',
            'status' => 'sent',
        ]);
    }
}
