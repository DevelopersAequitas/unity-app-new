<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Services\Leadership\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends LeadershipBaseController
{
    public function __construct(
        protected VerificationService $verificationService
    ) {}

    /**
     * B1. Request nomination OTP.
     */
    public function requestNominationOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign_id' => 'required|uuid',
            'contact_type' => 'required|in:email,mobile',
            'contact' => 'required|string',
        ]);

        try {
            $res = $this->verificationService->requestNominationOtp(
                $validated['campaign_id'],
                $validated['contact_type'],
                $validated['contact'],
                $request->ip()
            );

            return $this->success($res, 'Verification code sent.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * B2. Verify nomination OTP.
     */
    public function verifyNominationOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'verification_id' => 'required|uuid',
            'otp' => 'required|string',
        ]);

        try {
            $res = $this->verificationService->verifyNominationOtp(
                $validated['verification_id'],
                $validated['otp']
            );

            return $this->success($res, 'Verification successful.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * B3. Request voting OTP.
     */
    public function requestVotingOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign_id' => 'required|uuid',
            'scope_id' => 'nullable|uuid',
            'contact_type' => 'required|in:email,mobile',
            'contact' => 'required|string',
        ]);

        try {
            $res = $this->verificationService->requestVotingOtp(
                $validated['campaign_id'],
                $validated['scope_id'] ?? null,
                $validated['contact_type'],
                $validated['contact'],
                $request->ip()
            );

            return $this->success($res, 'Verification code sent.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * B4. Verify voting OTP.
     */
    public function verifyVotingOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'verification_id' => 'required|uuid',
            'otp' => 'required|string',
        ]);

        try {
            $res = $this->verificationService->verifyVotingOtp(
                $validated['verification_id'],
                $validated['otp']
            );

            return $this->success($res, 'Voting authorization token issued.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * B5. Verify candidate result OTP.
     */
    public function verifyResultOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'result_token' => 'required|string',
            'contact_type' => 'required|in:email,mobile',
            'contact' => 'required|string',
            'otp' => 'required|string',
        ]);

        try {
            $res = $this->verificationService->verifyResultOtp(
                $validated['result_token'],
                $validated['contact_type'],
                $validated['contact'],
                $validated['otp']
            );

            return $this->success($res, 'Candidate result access granted.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
