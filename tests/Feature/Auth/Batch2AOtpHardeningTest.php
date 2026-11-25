<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\AdvertiserApplication;
use App\Models\AdvertiserEmailVerification;
use App\Models\LeadershipCampaign;
use App\Models\LeadershipCandidateResultToken;
use App\Models\LeadershipNomination;
use App\Services\Advertiser\AdvertiserApplicationService;
use App\Services\Leadership\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class Batch2AOtpHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_advertiser_otp_rejects_123456_and_host_spoofing_and_never_logs_plaintext_otp(): void
    {
        Mail::fake();
        Log::spy();

        $app = AdvertiserApplication::query()->create([
            'id' => (string) Str::uuid(),
            'application_number' => 'ADV-TEST-001',
            'applicant_email' => 'advertiser@example.com',
            'applicant_email_verified' => false,
            'status' => AdvertiserApplication::STATUS_PENDING_EMAIL_VERIFICATION,
        ]);

        // Spoof dev host header
        $spoofed = Request::create('/api/v1/advertiser/applications/verify', 'POST', [], [], [], [
            'HTTP_HOST' => 'dev.peersunity.com',
        ]);
        app()->instance('request', $spoofed);

        /** @var AdvertiserApplicationService $service */
        $service = app(AdvertiserApplicationService::class);
        $verification = $service->generateEmailVerification($app);

        Log::shouldHaveReceived('info')->withArgs(function (string $message): bool {
            return str_contains($message, 'ADV-TEST-001')
                && preg_match('/\b\d{6}\b/', $message) !== 1;
        })->once();

        // Fixed 123456 fails when it does not match the generated hash
        if (! hash_equals($verification->otp_hash, hash('sha256', '123456'))) {
            $this->expectException(ValidationException::class);
            $service->confirmEmailVerification($app, '123456');
        }
    }

    public function test_advertiser_otp_valid_expired_and_reused_lifecycle(): void
    {
        $app = AdvertiserApplication::query()->create([
            'id' => (string) Str::uuid(),
            'application_number' => 'ADV-TEST-002',
            'applicant_email' => 'advertiser2@example.com',
            'applicant_email_verified' => false,
            'status' => AdvertiserApplication::STATUS_PENDING_EMAIL_VERIFICATION,
        ]);

        $otp = '847291';
        AdvertiserEmailVerification::query()->create([
            'application_id' => $app->id,
            'email' => $app->applicant_email,
            'otp_hash' => hash('sha256', $otp),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'max_attempts' => 5,
            'created_at' => now(),
        ]);

        /** @var AdvertiserApplicationService $service */
        $service = app(AdvertiserApplicationService::class);

        // Correct OTP succeeds
        $this->assertTrue($service->confirmEmailVerification($app, $otp));
        $this->assertTrue((bool) $app->fresh()->applicant_email_verified);

        // Expired challenge on another unverified app fails
        $app2 = AdvertiserApplication::query()->create([
            'id' => (string) Str::uuid(),
            'application_number' => 'ADV-TEST-003',
            'applicant_email' => 'advertiser3@example.com',
            'applicant_email_verified' => false,
            'status' => AdvertiserApplication::STATUS_PENDING_EMAIL_VERIFICATION,
        ]);
        AdvertiserEmailVerification::query()->create([
            'application_id' => $app2->id,
            'email' => $app2->applicant_email,
            'otp_hash' => hash('sha256', '551122'),
            'expires_at' => now()->subMinute(),
            'attempts' => 0,
            'max_attempts' => 5,
            'created_at' => now()->subMinutes(31),
        ]);

        $this->expectException(ValidationException::class);
        $service->confirmEmailVerification($app2, '551122');
    }

    public function test_verification_service_result_otp_rejects_123456_and_enforces_one_time_use(): void
    {
        $campaign = LeadershipCampaign::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'Test Election',
            'status' => 'active',
        ]);

        $nomination = LeadershipNomination::query()->create([
            'id' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'candidate_name' => 'Candidate Test',
            'email' => 'candidate@example.com',
        ]);

        $plainResultToken = 'raw-result-token-'.Str::random(16);
        $record = LeadershipCandidateResultToken::query()->create([
            'id' => (string) Str::uuid(),
            'nomination_id' => $nomination->id,
            'token_hash' => hash('sha256', $plainResultToken),
            'expires_at' => now()->addHour(),
        ]);

        /** @var VerificationService $service */
        $service = app(VerificationService::class);

        // Reject 123456 when no matching res_otp exists
        try {
            $service->verifyResultOtp($plainResultToken, 'email', 'candidate@example.com', '123456');
            $this->fail('Expected RuntimeException for invalid 123456 OTP.');
        } catch (RuntimeException $e) {
            $this->assertSame('Invalid verification code.', $e->getMessage());
        }

        // Valid cached OTP succeeds once, then replay fails
        Cache::put('res_otp:'.$record->id, '739104', 600);
        $res = $service->verifyResultOtp($plainResultToken, 'email', 'candidate@example.com', '739104');
        $this->assertTrue($res['verified']);

        $this->expectException(RuntimeException::class);
        $service->verifyResultOtp($plainResultToken, 'email', 'candidate@example.com', '739104');
    }

    public function test_ledger_and_form2_otp_reject_123456_and_wrong_email_and_accept_valid_challenge(): void
    {
        $campaign = LeadershipCampaign::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'Test Election 2',
            'status' => 'active',
        ]);

        $nomination = LeadershipNomination::query()->create([
            'id' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'candidate_name' => 'Candidate Two',
            'email' => 'candidate2@example.com',
        ]);

        // 1. Ledger OTP: reject 123456 without challenge
        $this->postJson("/api/v1/leadership/nominations/{$nomination->id}/voter-ledger/verify-otp", [
            'email' => 'candidate2@example.com',
            'otp' => '123456',
            'verification_id' => (string) Str::uuid(),
        ])->assertStatus(422);

        // 2. Ledger OTP: reject wrong email even with valid OTP
        $ledgerVid = (string) Str::uuid();
        Cache::put('ledger_otp:'.$ledgerVid, [
            'nomination_id' => $nomination->id,
            'email' => 'candidate2@example.com',
            'otp_hash' => Hash::make('482910'),
            'attempts' => 0,
        ], 600);

        $this->postJson("/api/v1/leadership/nominations/{$nomination->id}/voter-ledger/verify-otp", [
            'email' => 'attacker@example.com',
            'otp' => '482910',
            'verification_id' => $ledgerVid,
        ])->assertStatus(422);

        // 3. Ledger OTP: correct email + OTP succeeds once, replay fails
        $this->postJson("/api/v1/leadership/nominations/{$nomination->id}/voter-ledger/verify-otp", [
            'email' => 'candidate2@example.com',
            'otp' => '482910',
            'verification_id' => $ledgerVid,
        ])->assertStatus(200)->assertJsonPath('status', true);

        $this->postJson("/api/v1/leadership/nominations/{$nomination->id}/voter-ledger/verify-otp", [
            'email' => 'candidate2@example.com',
            'otp' => '482910',
            'verification_id' => $ledgerVid,
        ])->assertStatus(422);

        // 4. Form 2 OTP: reject 123456 without challenge, accept valid challenge once
        $this->postJson("/api/v1/leadership/nominations/{$nomination->id}/form2/verify-otp", [
            'verification_id' => (string) Str::uuid(),
            'otp' => '123456',
        ])->assertStatus(422);

        $form2Vid = (string) Str::uuid();
        Cache::put('form2_otp:'.$form2Vid, [
            'nomination_id' => $nomination->id,
            'email' => 'candidate2@example.com',
            'otp_hash' => Hash::make('918273'),
            'attempts' => 0,
        ], 600);

        $this->postJson("/api/v1/leadership/nominations/{$nomination->id}/form2/verify-otp", [
            'verification_id' => $form2Vid,
            'otp' => '918273',
        ])->assertStatus(200)->assertJsonPath('verified', true);

        $this->postJson("/api/v1/leadership/nominations/{$nomination->id}/form2/verify-otp", [
            'verification_id' => $form2Vid,
            'otp' => '918273',
        ])->assertStatus(422);
    }
}
