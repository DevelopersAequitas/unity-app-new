<?php

namespace App\Services\Referrals;

use App\Models\CoinsLedger;
use App\Models\ReferralData;
use App\Models\User;
use App\Services\Coins\CoinsService;
use App\Services\Notifications\NotifyUserService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ReferralService
{
    public function __construct(
        private readonly ReferralCodeService $referralCodeService,
        private readonly CoinsService $coinsService,
        private readonly NotifyUserService $notifyUserService,
    ) {
    }

    public function generateOrGetReferral(User $user): array
    {
        $existing = ReferralData::query()
            ->where('referrer_user_id', $user->id)
            ->whereNull('referred_user_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            Log::info('referral.code.existing_returned', [
                'referrer_user_id' => (string) $user->id,
                'referral_code' => (string) $existing->referral_code,
                'referral_row_id' => (int) $existing->id,
            ]);

            return [
                'referral_code' => (string) $existing->referral_code,
                'referral_link' => (string) $existing->referral_link,
            ];
        }

        $name = trim((string) ($user->display_name ?: ($user->first_name . ' ' . $user->last_name)));
        $code = $this->referralCodeService->generateUniqueCode($name);
        $link = $this->referralCodeService->buildReferralLink($code);

        ReferralData::query()->create([
            'referrer_user_id' => $user->id,
            'referred_user_id' => null,
            'referral_code' => $code,
            'referral_link' => $link,
            'referrer_email' => $user->email,
            'coins' => 0,
            'reward_status' => 'pending',
            'used_at' => null,
        ]);

        Log::info('referral.code.generated', [
            'referrer_user_id' => (string) $user->id,
            'referral_code' => $code,
        ]);

        return [
            'referral_code' => $code,
            'referral_link' => $link,
        ];
    }

    public function validateReferralCode(string $code): ?ReferralData
    {
        $normalized = strtoupper(trim($code));

        $row = ReferralData::query()
            ->with(['referrer:id,first_name,last_name,display_name'])
            ->where('referral_code', $normalized)
            ->whereNull('referred_user_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        Log::info('referral.code.validated', [
            'referral_code' => $normalized,
            'valid' => $row !== null,
            'referral_row_id' => $row?->id,
        ]);

        return $row;
    }

    public function validateReferralCodeOrFail(?string $code): ?ReferralData
    {
        if (blank($code)) {
            return null;
        }

        $row = $this->validateReferralCode((string) $code);

        if (! $row) {
            Log::warning('referral.code.invalid_or_used', [
                'referral_code' => strtoupper(trim((string) $code)),
            ]);

            throw ValidationException::withMessages([
                'referral_code' => ['The selected referral code is invalid or already used.'],
            ]);
        }

        return $row;
    }

    public function applyReferralOnRegistration(User $newUser, string $code): array
    {
        $normalized = strtoupper(trim($code));

        return DB::transaction(function () use ($newUser, $normalized) {
            $row = ReferralData::query()
                ->where('referral_code', $normalized)
                ->whereNull('referred_user_id')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $row) {
                Log::warning('referral.registration.row_not_found', [
                    'referred_user_id' => (string) $newUser->id,
                    'referral_code' => $normalized,
                ]);

                throw ValidationException::withMessages([
                    'referral_code' => ['The selected referral code is invalid or already used.'],
                ]);
            }

            if ((string) $row->referrer_user_id === (string) $newUser->id) {
                throw ValidationException::withMessages([
                    'referral_code' => ['A user cannot refer themselves.'],
                ]);
            }

            $alreadyRewarded = CoinsLedger::query()
                ->where('reference', 'referral_signup:' . $newUser->id)
                ->exists();

            if ($alreadyRewarded) {
                Log::warning('referral.registration.duplicate_reward_prevented', [
                    'referred_user_id' => (string) $newUser->id,
                    'referral_code' => $normalized,
                ]);

                throw ValidationException::withMessages([
                    'referral_code' => ['Referral reward already processed for this user.'],
                ]);
            }

            $rewardCoins = (int) config('coins.activity_rewards.referral_signup', 100);

            $row->forceFill([
                'referred_user_id' => $newUser->id,
                'coins' => $rewardCoins,
                'reward_status' => 'granted',
                'used_at' => now(),
                'updated_at' => now(),
            ])->save();

            $referrer = User::query()->find($row->referrer_user_id);

            if ($referrer && $rewardCoins > 0) {
                $this->coinsService->reward(
                    $referrer,
                    $rewardCoins,
                    'referral_signup:' . $newUser->id,
                    $newUser->id
                );

                Log::info('referral.reward.granted', [
                    'referrer_user_id' => (string) $referrer->id,
                    'referred_user_id' => (string) $newUser->id,
                    'coins' => $rewardCoins,
                    'referral_row_id' => (int) $row->id,
                ]);
            }

            if ($referrer) {
                $this->notifyUserService->notifyUser(
                    $referrer,
                    $newUser,
                    'referral_signup',
                    [
                        'title' => 'New Referral Joined',
                        'body' => 'A new peer has joined using your referral code.',
                        'referred_user_id' => (string) $newUser->id,
                        'referred_user_name' => trim((string) ($newUser->display_name ?: ($newUser->first_name . ' ' . $newUser->last_name))),
                        'referral_code_used' => $normalized,
                    ],
                    $row
                );

                $this->sendReferralEmail($referrer, $newUser, $normalized);
            }

            Log::info('referral.registration.applied', [
                'referral_row_id' => (int) $row->id,
                'referrer_user_id' => (string) $row->referrer_user_id,
                'referred_user_id' => (string) $newUser->id,
                'referral_code' => $normalized,
            ]);

            return [
                'referrer_user_id' => (string) $row->referrer_user_id,
                'referrer_email' => (string) ($row->referrer_email ?? ''),
                'referral_code' => (string) $row->referral_code,
                'coins' => (int) $row->coins,
                'reward_status' => (string) $row->reward_status,
            ];
        });
    }

    public function getReferralMembers(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 100));

        return ReferralData::query()
            ->with(['referredUser:id,first_name,last_name,display_name,email,company_name,designation,created_at'])
            ->where('referrer_user_id', $user->id)
            ->whereNotNull('referred_user_id')
            ->orderByRaw('used_at DESC NULLS LAST')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getReferralStats(User $user): array
    {
        $query = ReferralData::query()->where('referrer_user_id', $user->id);

        return [
            'total_referrals' => (clone $query)->whereNotNull('referred_user_id')->count(),
            'total_referral_coins' => (int) (clone $query)->where('reward_status', 'granted')->sum('coins'),
            'granted_referrals' => (clone $query)->where('reward_status', 'granted')->whereNotNull('referred_user_id')->count(),
            'pending_referrals' => (clone $query)->where('reward_status', 'pending')->whereNull('referred_user_id')->count(),
        ];
    }

    public function getMyReferralSummary(User $user): array
    {
        $available = ReferralData::query()
            ->where('referrer_user_id', $user->id)
            ->whereNull('referred_user_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $latest = $available ?: ReferralData::query()
            ->where('referrer_user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if (! $latest) {
            $generated = $this->generateOrGetReferral($user);
            $latest = (object) $generated;
        }

        $stats = $this->getReferralStats($user);

        return array_merge([
            'referral_code' => (string) ($latest->referral_code ?? ''),
            'referral_link' => (string) ($latest->referral_link ?? ''),
        ], $stats);
    }

    private function sendReferralEmail(User $referrer, User $referredUser, string $referralCode): void
    {
        if (blank($referrer->email)) {
            return;
        }

        try {
            Mail::raw(
                'A new peer has joined using your referral code. Peer: '
                . trim((string) ($referredUser->display_name ?: ($referredUser->first_name . ' ' . $referredUser->last_name)))
                . ' | Code: ' . $referralCode,
                static function ($message) use ($referrer): void {
                    $message->to($referrer->email)
                        ->subject('New Referral Joined');
                }
            );

            Log::info('referral.email.sent', [
                'referrer_user_id' => (string) $referrer->id,
                'referrer_email' => (string) $referrer->email,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('referral.email.failed', [
                'referrer_user_id' => (string) $referrer->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
