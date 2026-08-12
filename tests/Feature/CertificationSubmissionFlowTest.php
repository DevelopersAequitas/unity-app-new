<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\CertificationApprovedMail;
use App\Models\AdminUser;
use App\Models\CertificationSubmission;
use App\Models\EntrepreneurCertificationSubmission;
use App\Models\LeadershipCertificationSubmission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CertificationSubmissionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('display_name')->nullable();
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('company_name')->nullable();
                $table->string('membership_status')->nullable();
                $table->integer('coins_balance')->default(0);
                $table->string('password_hash')->nullable();
                $table->string('public_profile_slug')->nullable();
                $table->string('status')->default('active');
                $table->text('bookmarks')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name')->nullable();
                $table->string('key')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_user_roles')) {
            Schema::create('admin_user_roles', function (Blueprint $table): void {
                $table->uuid('user_id');
                $table->uuid('role_id');
            });
        }

        if (! Schema::hasTable('tbl_permission_cache')) {
            Schema::create('tbl_permission_cache', function (Blueprint $table): void {
                $table->id();
                $table->uuid('user_id');
                $table->json('circle_ids')->nullable();
                $table->text('permissions')->nullable();
                $table->timestamp('computed_at')->nullable();
                $table->integer('version')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table): void {
                $table->id();
                $table->string('tokenable_type');
                $table->string('tokenable_id');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('certification_submissions')) {
            Schema::create('certification_submissions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('certification_type');
                $table->uuid('user_id')->nullable();
                $table->string('full_name');
                $table->string('business_name')->nullable();
                $table->string('email');
                $table->string('contact_no')->nullable();
                $table->integer('total_score')->default(0);
                $table->integer('percentage')->default(0);
                $table->string('certification_level')->nullable();
                $table->string('certification_title')->nullable();
                $table->string('certificate_number')->nullable();
                $table->string('certificate_file_path')->nullable();
                $table->string('certificate_download_url')->nullable();
                $table->timestamp('certificate_generated_at')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->json('answers')->nullable();
                $table->string('status')->default('new');
                $table->text('admin_note')->nullable();
                $table->uuid('approved_by')->nullable();
                $table->uuid('rejected_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('leadership_certification_submissions')) {
            Schema::create('leadership_certification_submissions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('full_name');
                $table->string('business_name')->nullable();
                $table->string('email');
                $table->string('contact_no')->nullable();
                $table->string('status')->default('new');
                $table->text('notes')->nullable();
                $table->integer('total_score')->default(0);
                $table->float('percentage')->default(0);
                $table->string('certification_level')->nullable();
                foreach (LeadershipCertificationSubmission::QUIZ_FIELDS as $field) {
                    $table->text($field)->nullable();
                }
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('entrepreneur_certification_submissions')) {
            Schema::create('entrepreneur_certification_submissions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('full_name');
                $table->string('business_name')->nullable();
                $table->string('email');
                $table->string('contact_no')->nullable();
                $table->string('status')->default('new');
                $table->text('notes')->nullable();
                $table->integer('total_score')->default(0);
                $table->float('percentage')->default(0);
                $table->string('certification_tier')->nullable();
                foreach (EntrepreneurCertificationSubmission::QUIZ_FIELDS as $field) {
                    $table->text($field)->nullable();
                }
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('type');
                $table->json('payload')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('app_notifications')) {
            Schema::create('app_notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('type')->nullable();
                $table->string('category')->nullable();
                $table->string('title')->nullable();
                $table->text('body')->nullable();
                $table->text('message')->nullable();
                $table->string('channel')->nullable();
                $table->string('priority')->nullable();
                $table->string('reference_type')->nullable();
                $table->string('reference_id')->nullable();
                $table->string('screen')->nullable();
                $table->json('data')->nullable();
                $table->string('status')->default('pending');
                $table->timestamps();
            });
        }
    }

    public function test_user_can_submit_leadership_certification_form_and_admin_can_approve_it(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'leader@example.com',
            'status' => 'active',
        ]);

        $admin = $this->createAdminWithRole('admin@example.com');

        $payload = [
            'full_name' => 'Jane Leader',
            'business_name' => 'Leader Corp',
            'email' => 'leader@example.com',
            'contact_no' => '+1234567890',
        ];

        foreach (LeadershipCertificationSubmission::QUIZ_FIELDS as $field) {
            $payload[$field] = LeadershipCertificationSubmission::CORRECT_ANSWERS[$field] ?? 'Sample answer';
        }

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/leadership-certification', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.full_name', 'Jane Leader');

        $submissionId = $response->json('data.id');

        $this->assertDatabaseHas('certification_submissions', [
            'id' => $submissionId,
            'certification_type' => CertificationSubmission::TYPE_LEADERSHIP,
            'full_name' => 'Jane Leader',
            'email' => 'leader@example.com',
            'status' => 'new',
            'user_id' => $user->id,
        ]);

        // 2. Admin pending requests view
        $adminResponse = $this->actingAs($admin, 'admin')
            ->get('/admin/pending-requests/certifications?status=new');

        $adminResponse->assertStatus(200)
            ->assertSee('Jane Leader');

        // 3. Admin approves submission
        $approveResponse = $this->actingAs($admin, 'admin')
            ->post("/admin/pending-requests/certifications/{$submissionId}/approve", [
                'admin_note' => 'Outstanding score and leadership qualities.',
            ]);

        $approveResponse->assertRedirect();

        $submission = CertificationSubmission::findOrFail($submissionId);
        $this->assertEquals(CertificationSubmission::STATUS_APPROVED, $submission->status);
        $this->assertNotNull($submission->certificate_number);
        $this->assertNotNull($submission->issued_at);
        $this->assertNotNull($submission->certificate_download_url);

        Mail::assertSent(CertificationApprovedMail::class, function ($mail) use ($submission) {
            return $mail->hasTo('leader@example.com') && $mail->submission->id === $submission->id;
        });

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
        ]);

        // 4. View generated certificate
        $certViewResponse = $this->actingAs($admin, 'admin')
            ->get("/admin/certificates/{$submissionId}/view");

        $certViewResponse->assertStatus(200)
            ->assertSee('Jane Leader')
            ->assertSee('Leadership Certification')
            ->assertSee($submission->certificate_number);
    }

    public function test_user_can_submit_entrepreneur_certification_form_and_admin_can_approve_it(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'entrepreneur@example.com',
            'status' => 'active',
        ]);

        $admin = $this->createAdminWithRole('admin2@example.com');

        $payload = [
            'full_name' => 'John Builder',
            'business_name' => 'Venture LLC',
            'email' => 'entrepreneur@example.com',
            'contact_no' => '+9876543210',
        ];

        foreach (EntrepreneurCertificationSubmission::QUIZ_FIELDS as $field) {
            $payload[$field] = EntrepreneurCertificationSubmission::CORRECT_ANSWERS[$field] ?? 'Sample answer';
        }

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/entrepreneur-certification', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.full_name', 'John Builder');

        $submissionId = $response->json('data.id');

        $this->assertDatabaseHas('certification_submissions', [
            'id' => $submissionId,
            'certification_type' => CertificationSubmission::TYPE_ENTREPRENEUR,
            'full_name' => 'John Builder',
            'email' => 'entrepreneur@example.com',
            'status' => 'new',
            'user_id' => $user->id,
        ]);

        // Admin approves
        $approveResponse = $this->actingAs($admin, 'admin')
            ->post("/admin/pending-requests/certifications/{$submissionId}/approve", [
                'admin_note' => 'Approved after review.',
            ]);

        $approveResponse->assertRedirect();

        $submission = CertificationSubmission::findOrFail($submissionId);
        $this->assertEquals(CertificationSubmission::STATUS_APPROVED, $submission->status);

        Mail::assertSent(CertificationApprovedMail::class, function ($mail) use ($submission) {
            return $mail->hasTo('entrepreneur@example.com') && $mail->submission->id === $submission->id;
        });

        // Certificate view
        $certViewResponse = $this->actingAs($admin, 'admin')
            ->get("/admin/certificates/{$submissionId}/view");

        $certViewResponse->assertStatus(200)
            ->assertSee('John Builder')
            ->assertSee('Entrepreneur Certification');
    }

    public function test_admin_can_approve_and_reject_certification_via_json_api(): void
    {
        Mail::fake();

        $admin = $this->createAdminWithRole('admin_api@example.com');

        $submission = CertificationSubmission::create([
            'id' => (string) Str::uuid(),
            'certification_type' => CertificationSubmission::TYPE_ENTREPRENEUR,
            'full_name' => 'API Applicant',
            'email' => 'api_applicant@example.com',
            'status' => CertificationSubmission::STATUS_NEW,
        ]);

        // Approve via JSON
        $approveResponse = $this->actingAs($admin, 'admin')
            ->postJson("/admin/pending-requests/certifications/{$submission->id}/approve", [
                'admin_note' => 'Approved via JSON API',
            ]);

        $approveResponse->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.status', 'approved');

        // Reject via JSON
        $rejectResponse = $this->actingAs($admin, 'admin')
            ->postJson("/admin/pending-requests/certifications/{$submission->id}/reject", [
                'admin_note' => 'Rejected via JSON API',
            ]);

        $rejectResponse->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_admin_can_authenticate_via_sanctum_bearer_token_on_admin_routes(): void
    {
        $admin = $this->createAdminWithRole('admin_bearer@example.com');
        $token = $admin->createToken('admin-token')->plainTextToken;

        $submission = CertificationSubmission::create([
            'id' => (string) Str::uuid(),
            'certification_type' => CertificationSubmission::TYPE_ENTREPRENEUR,
            'full_name' => 'Bearer Applicant',
            'email' => 'bearer_applicant@example.com',
            'status' => CertificationSubmission::STATUS_NEW,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/admin/pending-requests/certifications?status=new');

        $response->assertStatus(200)
            ->assertJsonPath('status', true);
    }

    private function createAdminWithRole(string $email = 'admin@example.com'): AdminUser
    {
        $role = \App\Models\Role::firstOrCreate(
            ['key' => 'global_admin'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Global Admin',
            ]
        );

        $admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        \Illuminate\Support\Facades\DB::table('admin_user_roles')->insert([
            'user_id' => $admin->id,
            'role_id' => $role->id,
        ]);

        return $admin;
    }
}
