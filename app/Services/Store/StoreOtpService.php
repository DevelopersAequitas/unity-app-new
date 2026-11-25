<?php

namespace App\Services\Store;

use App\Constants\StoreErrorCodes;
use App\Models\Store\StoreOtpChallenge;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StoreOtpService
{
    public function sendChallenge(User $user, string $purpose, ?string $refType = null, ?string $refId = null): array
    {
        $otp = (string) random_int(100000, 999999);
        $otpHash = Hash::make($otp);
        $phoneHash = Hash::make($user->phone ?? '');

        // Invalidate older challenges for same purpose & user
        StoreOtpChallenge::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->delete();

        $challenge = StoreOtpChallenge::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'phone_hash' => $phoneHash,
            'otp_hash' => $otpHash,
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);

        return [
            'challenge_id' => $challenge->id,
            'expires_at' => $challenge->expires_at->toIso8601String(),
            'phone_hint' => substr($user->phone ?? '0000000000', -4),
            'debug_otp' => app()->environment('local', 'testing') ? $otp : null,
        ];
    }

    public function verifyChallenge(User $user, string $challengeId, string $otp): array
    {
        $challenge = StoreOtpChallenge::where('id', $challengeId)
            ->where('user_id', $user->id)
            ->whereNull('verified_at')
            ->where('expires_at', '>=', now())
            ->first();

        if (! $challenge) {
            throw new Exception(StoreErrorCodes::OTP_INVALID, 422);
        }

        if ($challenge->attempts >= $challenge->max_attempts) {
            throw new Exception(StoreErrorCodes::OTP_INVALID, 422);
        }

        $challenge->increment('attempts');

        $valid = Hash::check($otp, $challenge->otp_hash);
        if (! $valid) {
            throw new Exception(StoreErrorCodes::OTP_INVALID, 422);
        }

        $token = 'store_otp_tok_'.Str::random(40);

        $challenge->update([
            'verified_at' => now(),
            'verification_token' => $token,
        ]);

        return [
            'verified' => true,
            'verification_token' => $token,
        ];
    }

    public function validateToken(User $user, string $token, string $purpose): bool
    {
        if ($token === '') {
            return false;
        }

        return StoreOtpChallenge::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->where('verification_token', $token)
            ->whereNotNull('verified_at')
            ->exists();
    }
}
