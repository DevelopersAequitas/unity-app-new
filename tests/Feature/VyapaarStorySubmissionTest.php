<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SmeBusinessStorySubmission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VyapaarStorySubmissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->createSchema();
    }

    protected function createSchema(): void
    {
        Schema::dropIfExists('admin_user_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('sme_business_story_submissions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('files');

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

        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('admin_user_roles', function (Blueprint $table): void {
            $table->uuid('role_id');
            $table->uuid('user_id');
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uploader_user_id')->nullable();
            $table->string('s3_key');
            $table->string('mime_type');
            $table->integer('size_bytes');
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('duration')->nullable();
            $table->timestamps();
        });

        Schema::create('sme_business_story_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('business_name')->nullable();
            $table->text('company_introduction')->nullable();
            $table->text('co_founders_and_partners_details')->nullable();
            $table->string('status', 30)->default('new');
            $table->text('notes')->nullable();

            // Vyapaar Jagat additional fields
            $table->string('designation')->nullable();
            $table->string('company_name')->nullable();
            $table->string('website')->nullable();
            $table->uuid('profile_photo')->nullable();
            $table->uuid('company_logo')->nullable();
            $table->text('entrepreneurial_journey')->nullable();
            $table->text('business_description')->nullable();
            $table->text('biggest_challenge')->nullable();
            $table->text('biggest_achievement')->nullable();
            $table->text('business_impact')->nullable();
            $table->text('future_goals')->nullable();
            $table->text('advice_for_entrepreneurs')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->boolean('consent')->default(false);
            $table->text('admin_remark')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            // Legacy / Sme fields
            $table->string('title')->nullable();
            $table->text('story')->nullable();
            $table->text('short_description')->nullable();
            $table->uuid('cover_image')->nullable();
            $table->json('attachments')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();

            $table->timestamps();
        });
    }

    public function test_guest_cannot_submit_story(): void
    {
        $response = $this->postJson('/api/v1/story-submission', []);
        $response->assertStatus(401);
    }

    public function test_validation_fails_for_missing_required_fields(): void
    {
        $user = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/story-submission', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'full_name',
            'designation',
            'company_name',
            'profile_photo',
            'company_logo',
            'entrepreneurial_journey',
            'business_description',
            'biggest_challenge',
            'biggest_achievement',
            'business_impact',
            'future_goals',
            'advice_for_entrepreneurs',
            'consent',
        ]);
    }

    public function test_validation_fails_for_consent_false(): void
    {
        $user = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/story-submission', [
            'full_name' => 'John Doe',
            'designation' => 'Founder',
            'company_name' => 'Acme Inc',
            'consent' => false,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['consent']);
    }

    public function test_successful_story_submission(): void
    {
        Storage::fake('public');
        config([
            'filesystems.default' => 'public',
            'media.processing.mode' => 'sync',
            'media.keep_original' => true,
        ]);

        $user = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        Sanctum::actingAs($user);

        $profilePhoto = UploadedFile::fake()->image('profile.jpg', 100, 100);
        $companyLogo = UploadedFile::fake()->image('logo.png', 100, 100);

        $payload = [
            'full_name' => 'John Doe',
            'designation' => 'Founder & CEO',
            'company_name' => 'Acme Corporation',
            'website' => 'https://acme.example.com',
            'profile_photo' => $profilePhoto,
            'company_logo' => $companyLogo,
            'entrepreneurial_journey' => 'Started in a garage...',
            'business_description' => 'We build widgets.',
            'biggest_challenge' => 'Scaling widget production.',
            'biggest_achievement' => 'First million widgets sold.',
            'business_impact' => 'Creating local jobs.',
            'future_goals' => 'Go global next year.',
            'advice_for_entrepreneurs' => 'Never give up.',
            'linkedin_url' => 'https://linkedin.com/in/johndoe',
            'facebook_url' => 'https://facebook.com/johndoe',
            'instagram_url' => 'https://instagram.com/johndoe',
            'twitter_url' => 'https://x.com/johndoe',
            'consent' => true,
        ];

        $response = $this->postJson('/api/v1/story-submission', $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Your story has been submitted successfully. Our editorial team will review it before publishing.');

        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('Pending', $data['status']);

        // Verify database records
        $submission = SmeBusinessStorySubmission::findOrFail($data['id']);
        $this->assertEquals($user->id, $submission->user_id);
        $this->assertEquals('John Doe', $submission->full_name);
        $this->assertEquals('Founder & CEO', $submission->designation);
        $this->assertEquals('Acme Corporation', $submission->company_name);
        $this->assertEquals('Acme Corporation', $submission->business_name);
        $this->assertEquals('https://acme.example.com', $submission->website);
        $this->assertEquals('Started in a garage...', $submission->entrepreneurial_journey);
        $this->assertEquals('Started in a garage...', $submission->story);
        $this->assertEquals('We build widgets.', $submission->business_description);
        $this->assertEquals('We build widgets.', $submission->short_description);
        $this->assertEquals('Scaling widget production.', $submission->biggest_challenge);
        $this->assertEquals('First million widgets sold.', $submission->biggest_achievement);
        $this->assertEquals('Creating local jobs.', $submission->business_impact);
        $this->assertEquals('Go global next year.', $submission->future_goals);
        $this->assertEquals('Never give up.', $submission->advice_for_entrepreneurs);
        $this->assertEquals('https://linkedin.com/in/johndoe', $submission->linkedin_url);
        $this->assertTrue($submission->consent);
        $this->assertEquals('Pending', $submission->status);
        $this->assertNotNull($submission->profile_photo);
        $this->assertNotNull($submission->company_logo);
    }
}
