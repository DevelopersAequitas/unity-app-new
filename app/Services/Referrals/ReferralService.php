<?php

namespace App\Services\Referrals;

use App\Http\Resources\MemberDetailResource;
use App\Jobs\SendPushNotificationJob;
use App\Mail\ReferralJoinedMail;
use App\Models\CoinsLedger;
use App\Models\Notification;
use App\Models\ReferralData;
use App\Models\User;
use App\Services\Coins\CoinsService;
use App\Services\EmailLogs\EmailLogService;
use App\Services\LifeImpact\LifeImpactService;
use App\Services\Notifications\NotifyUserService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class ReferralService
{
    public function __construct(
        private readonly ReferralCodeService $referralCodeService,
        private readonly CoinsService $coinsService,
        private readonly LifeImpactService $lifeImpactService,
        private readonly NotifyUserService $notifyUserService,
    ) {
    }

    public function generateOrGetReferral(User $user): array
    {
        $codeColumn = $this->referralLinksCodeColumn();
        $existing = $this->getReferralLinkRowByUserId((string) $user->id);

        $existingCode = is_string($existing?->referral_code) ? trim($existing->referral_code) : '';

        if ($existing && $existingCode !== '') {
            $link = $this->buildReferralLinkFromToken($existingCode);

            if (($existing->referral_link ?? null) !== $link) {
                DB::table('referral_links')
                    ->where('id', $existing->id)
                    ->update([
                        'referral_link' => $link,
                        'updated_at' => now(),
                    ]);
            }

            Log::info('referral.code.existing_returned', [
                'referrer_user_id' => (string) $user->id,
                'referral_code' => $existingCode,
            ]);

            return [
                'referral_code' => $existingCode,
                'referral_link' => $link,
                'is_existing' => true,
            ];
        }

        $code = $this->generateUniqueReferralToken($user, $codeColumn);
        $link = $this->buildReferralLinkFromToken($code);

        if ($existing && ! empty($existing->id)) {
            DB::table('referral_links')
                ->where('id', $existing->id)
                ->update([
                    $codeColumn => $code,
                    'referral_link' => $link,
                    'updated_at' => now(),
                ]);

            return [
                'referral_code' => $code,
                'referral_link' => $link,
                'is_existing' => true,
            ];
        }

        $insertPayload = [
            $this->referralLinksUserColumn() => $user->id,
            $codeColumn => $code,
            'referral_link' => $link,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('referral_links', 'status')) {
            $insertPayload['status'] = 'active';
        }

        if (Schema::hasColumn('referral_links', 'stats')) {
            $insertPayload['stats'] = json_encode(new \stdClass());
        }

        if (Schema::hasColumn('referral_links', 'expires_at')) {
            $insertPayload['expires_at'] = null;
        }

        DB::table('referral_links')->insert($insertPayload);

        Log::info('referral.code.generated', [
            'referrer_user_id' => (string) $user->id,
            'referral_code' => $code,
            'payload' => $insertPayload,
        ]);

        return [
            'referral_code' => $code,
            'referral_link' => $link,
            'is_existing' => false,
        ];
    }

    public function validateReferralCode(string $code): ?array
    {
        $lookup = $this->lookupReferralCode($code);

        if (! $lookup) {
            Log::info('referral.code.validated', [
                'referral_code' => strtoupper(trim($code)),
                'valid' => false,
                'referrer_user_id' => null,
            ]);

            return null;
        }

        Log::info('referral.code.validated', [
            'referral_code' => (string) $lookup['referral_code'],
            'valid' => true,
            'referrer_user_id' => (string) $lookup['referrer_user_id'],
        ]);

        return [
            'referrer_user_id' => (string) $lookup['referrer_user_id'],
            'referral_code' => (string) $lookup['referral_code'],
            'referral_link' => $this->buildReferralLinkFromToken((string) $lookup['referral_code']),
            'referrer_name' => (string) ($lookup['referrer_name'] ?? ''),
            'referrer_email' => (string) ($lookup['referrer_email'] ?? ''),
        ];
    }

    public function lookupReferralCode(string $code): ?array
    {
        $normalized = trim($code);

        if ($normalized === '') {
            return null;
        }

        $row = $this->lookupReferralLinkCode($normalized) ?? $this->lookupUserReferralCode($normalized);

        if (! $row) {
            return null;
        }

        return [
            'referral_code' => (string) $row->referral_code,
            'referrer_user_id' => (string) $row->user_id,
            'referrer_name' => $this->referrerName($row),
            'referrer_email' => (string) ($row->email ?? ''),
            'referrer_profile_photo_url' => $this->referrerProfilePhotoUrl($row),
        ];
    }


    private function lookupReferralLinkCode(string $code): ?object
    {
        if (! Schema::hasTable('referral_links')) {
            return null;
        }

        $userColumn = $this->referralLinksUserColumn();
        $codeColumn = $this->referralLinksCodeColumn();

        $query = DB::table('referral_links as rl')
            ->join('users as u', 'u.id', '=', 'rl.' . $userColumn)
            ->whereRaw('LOWER(rl."' . $codeColumn . '") = ?', [strtolower($code)]);

        if (Schema::hasColumn('referral_links', 'status')) {
            $query->where('rl.status', 'active');
        }

        $this->applyActiveReferrerFilters($query);

        return $query->select($this->referrerSelectColumns('rl."' . $userColumn . '"', 'rl."' . $codeColumn . '"'))->first();
    }

    private function lookupUserReferralCode(string $code): ?object
    {
        if (! Schema::hasColumn('users', 'referral_code')) {
            return null;
        }

        $query = DB::table('users as u')
            ->whereRaw('LOWER(u.referral_code) = ?', [strtolower($code)]);

        $this->applyActiveReferrerFilters($query);

        return $query->select($this->referrerSelectColumns('u.id', 'u.referral_code'))->first();
    }

    private function applyActiveReferrerFilters($query): void
    {
        $query->whereNull('u.deleted_at');

        if (Schema::hasColumn('users', 'gdpr_deleted_at')) {
            $query->whereNull('u.gdpr_deleted_at');
        }

        if (Schema::hasColumn('users', 'status')) {
            $query->whereIn('u.status', ['active', 'approved']);
        }

        if (Schema::hasColumn('users', 'membership_status')) {
            $query->where('u.membership_status', '!=', 'suspended');
        }
    }

    private function referrerSelectColumns(string $userIdExpression, string $codeExpression): array
    {
        return [
            DB::raw($userIdExpression . ' as "user_id"'),
            DB::raw($codeExpression . ' as "referral_code"'),
            'u.first_name',
            'u.last_name',
            'u.display_name',
            'u.email',
            'u.profile_photo_url',
            'u.profile_photo_file_id',
            'u.profile_photo_id',
        ];
    }

    private function referrerName(object $row): ?string
    {
        $name = trim((string) ($row->display_name ?? ''));

        if ($name === '') {
            $name = trim((string) (($row->first_name ?? '') . ' ' . ($row->last_name ?? '')));
        }

        return $name !== '' ? $name : null;
    }

    private function referrerProfilePhotoUrl(object $row): ?string
    {
        $profilePhotoId = $row->profile_photo_file_id ?? $row->profile_photo_id ?? null;

        if ($profilePhotoId) {
            return url('/api/v1/files/' . $profilePhotoId);
        }

        $profilePhotoUrl = $row->profile_photo_url ?? null;

        if (blank($profilePhotoUrl)) {
            return null;
        }

        if (filter_var($profilePhotoUrl, FILTER_VALIDATE_URL)) {
            return $profilePhotoUrl;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($profilePhotoUrl);
    }

    public function validateReferralCodeOrFail(?string $code): ?array
    {
        if (blank($code)) {
            return null;
        }

        $row = $this->validateReferralCode((string) $code);

        if (! $row) {
            Log::warning('referral.code.invalid', [
                'referral_code' => strtoupper(trim((string) $code)),
            ]);

            throw ValidationException::withMessages([
                'referral_code' => ['The selected referral code is invalid.'],
            ]);
        }

        return $row;
    }

    public function applyReferralOnRegistration(User $newUser, string $code): array
    {
        $normalized = strtoupper(trim($code));
        $userColumn = $this->referralLinksUserColumn();
        $codeColumn = $this->referralLinksCodeColumn();

        try {
            return DB::transaction(function () use ($newUser, $normalized, $userColumn, $codeColumn) {
            $link = DB::table('referral_links')
                ->whereRaw('LOWER("' . $codeColumn . '") = ?', [strtolower($normalized)])
                ->lockForUpdate()
                ->first();

            if (! $link) {
                throw ValidationException::withMessages([
                    'referral_code' => ['The selected referral code is invalid.'],
                ]);
            }

            $referrerUserId = (string) $link->{$userColumn};
            $newUserId = (string) $newUser->id;

            Log::info('referral.registration.link_resolved', [
                'referrer_user_id' => $referrerUserId,
                'referral_code' => $normalized,
            ]);

            if ($referrerUserId === $newUserId) {
                throw ValidationException::withMessages([
                    'referral_code' => ['A user cannot refer themselves.'],
                ]);
            }

            $alreadyReferred = ReferralData::query()
                ->where('referred_user_id', $newUserId)
                ->exists();
            $alreadyGrantedForReferredUser = ReferralData::query()
                ->where('referred_user_id', $newUserId)
                ->where('reward_status', 'granted')
                ->exists();

            Log::info('referral.registration.referred_lookup', [
                'referred_user_id' => $newUserId,
                'already_referred' => $alreadyReferred,
                'referral_code' => $normalized,
            ]);

            if ($alreadyReferred) {
                Log::warning('referral.registration.duplicate_referred_user', [
                    'referred_user_id' => $newUserId,
                ]);

                throw ValidationException::withMessages([
                    'referral_code' => ['Referral already applied for this user.'],
                ]);
            }

            $alreadyRewarded = CoinsLedger::query()
                ->where('reference', 'referral_signup:' . $newUserId)
                ->exists();

            if ($alreadyRewarded) {
                throw ValidationException::withMessages([
                    'referral_code' => ['Referral reward already processed for this user.'],
                ]);
            }

            $rewardCoins = (int) config('coins.activity_rewards.referral_signup', 100);

            $referrer = User::query()->find($referrerUserId);

            Log::info('referral.registration.referrer_resolved', [
                'referrer_user_id' => $referrerUserId,
                'found' => $referrer !== null,
            ]);

            $insertPayload = [
                'referrer_user_id' => $referrerUserId,
                'referred_user_id' => $newUserId,
                'referral_code' => $normalized,
                'referrer_email' => $referrer?->email,
                'coins' => $rewardCoins,
                'reward_status' => 'granted',
                'used_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            Log::info('referral.registration.before_insert', [
                'referrer_user_id' => $referrerUserId,
                'referred_user_id' => $newUserId,
                'referral_code' => $normalized,
                'coins' => $rewardCoins,
                'payload' => $insertPayload,
            ]);

            $data = ReferralData::query()->create($insertPayload);

            if (! $data->exists || ! $data->id) {
                throw new \RuntimeException('Referral registration failed: referraldata row was not created.');
            }

            Log::info('referral.registration.insert_success', [
                'referral_data_id' => (int) $data->id,
                'referred_user_id' => $newUserId,
                'referrer_user_id' => $referrerUserId,
            ]);

            if ($referrer && blank($data->referrer_email)) {
                $data->referrer_email = $referrer->email;
                $data->save();
            }

            if ($referrer && $rewardCoins > 0) {
                $this->coinsService->reward(
                    $referrer,
                    $rewardCoins,
                    'referral_signup:' . $newUserId,
                    [
                        'source' => 'referral_signup',
                        'referral_code' => $normalized,
                        'referred_user_id' => $newUserId,
                        'referrer_user_id' => $referrerUserId,
                        'coins' => $rewardCoins,
                    ],
                    (string) $newUserId
                );

                Log::info('referral.reward.granted', [
                    'referrer_user_id' => (string) $referrer->id,
                    'referred_user_id' => $newUserId,
                    'coins' => $rewardCoins,
                    'referral_data_id' => (int) $data->id,
                ]);
            }

            if (! $alreadyGrantedForReferredUser) {
                $lifeImpactActivityId = (is_string($data->id) && Str::isUuid($data->id))
                    ? (string) $data->id
                    : null;

                $updatedLifeImpacted = $this->lifeImpactService->addLifeImpact(
                    $referrerUserId,
                    $newUserId,
                    'referral_registration',
                    $lifeImpactActivityId,
                    5,
                    'New referral joined successfully',
                    'Life impact added for successful referral-based registration.',
                    [
                        'to_user_id' => $newUserId,
                        'referred_user_id' => $newUserId,
                        'referrer_user_id' => $referrerUserId,
                        'referral_code' => $normalized,
                        'coins' => $rewardCoins,
                    ]
                );

                Log::info('referral.life_impacted.incremented', [
                    'referrer_user_id' => $referrerUserId,
                    'referred_user_id' => $newUserId,
                    'increment_by' => 5,
                    'updated_total' => $updatedLifeImpacted,
                    'referral_data_id' => (int) $data->id,
                ]);
            }

            $referrerLifeImpactedCount = (int) (User::query()
                ->whereKey($referrerUserId)
                ->value('life_impacted_count') ?? 0);

            if ($referrer) {
                $this->notifyReferralJoined($referrer, $newUser, $normalized, $data);
                $this->sendReferralEmail($referrer, $newUser, $normalized);
            }

            Log::info('referral.registration.applied', [
                'referral_data_id' => (int) $data->id,
                'referrer_user_id' => $referrerUserId,
                'referred_user_id' => $newUserId,
                'referral_code' => $normalized,
            ]);

            return [
                'referrer_user_id' => $referrerUserId,
                'referrer_email' => (string) ($data->referrer_email ?? ''),
                'referral_code' => $normalized,
                'coins' => (int) $rewardCoins,
                'reward_status' => 'granted',
                'referrer_life_impacted_count' => $referrerLifeImpactedCount,
            ];
            });
        } catch (\Throwable $exception) {
            Log::error('referral.registration.failed', [
                'new_user_id' => (string) $newUser->id,
                'referral_code' => $normalized,
                'error' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            throw $exception;
        }
    }


    private function notifyReferralJoined(User $referrer, User $newUser, string $referralCode, ReferralData $referralData): ?Notification
    {
        $newUserName = trim((string) ($newUser->display_name ?: ($newUser->first_name . ' ' . $newUser->last_name)));
        if ($newUserName === '') {
            $newUserName = 'A new peer';
        }

        $title = 'New Referral Joined';
        $body = $newUserName . ' joined Peers Global Unity using your referral code.';
        $payloadData = [
            'type' => 'referral_joined',
            'new_user_id' => (string) $newUser->id,
            'new_user_name' => $newUserName,
            'referral_code' => $referralCode,
            'referral_data_id' => (string) $referralData->id,
        ];

        $notification = Notification::query()->create([
            'user_id' => (string) $referrer->id,
            'type' => 'referral_joined',
            'payload' => [
                'notification_type' => 'referral_joined',
                'title' => $title,
                'body' => $body,
                'from_user_id' => (string) $newUser->id,
                'to_user_id' => (string) $referrer->id,
                'data' => $payloadData,
                'notifiable_type' => ReferralData::class,
                'notifiable_id' => (string) $referralData->id,
            ],
            'data' => $payloadData,
            'title' => $title,
            'message' => $body,
            'source_type' => 'referral',
            'source_id' => Str::isUuid((string) $referralData->id) ? (string) $referralData->id : null,
            'source_event' => 'referral_joined',
            'is_read' => false,
            'created_at' => now(),
            'read_at' => null,
        ]);

        try {
            SendPushNotificationJob::dispatch($referrer, $title, $body, $payloadData + [
                'notification_id' => (string) $notification->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('referral.registration.push_dispatch_failed', [
                'notification_id' => (string) $notification->id,
                'referrer_user_id' => (string) $referrer->id,
                'new_user_id' => (string) $newUser->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $notification;
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

        $referrer = User::query()
            ->with(['city', 'activeCircle.cityRef'])
            ->find($user->id);

        $referredUsers = ReferralData::query()
            ->with([
                'referredUser',
                'referredUser.city',
                'referredUser.activeCircle.cityRef',
            ])
            ->where('referrer_user_id', $user->id)
            ->whereNotNull('referred_user_id')
            ->orderByRaw('used_at DESC NULLS LAST')
            ->orderByDesc('created_at')
            ->get()
            ->pluck('referredUser')
            ->filter()
            ->map(fn (User $referredUser): array => (new MemberDetailResource($referredUser))->resolve())
            ->values()
            ->all();

        $referrerProfile = $referrer ? (new MemberDetailResource($referrer))->resolve() : null;

        return [
            'total_referrals' => (clone $query)->whereNotNull('referred_user_id')->count(),
            'total_referral_coins' => (int) (clone $query)->where('reward_status', 'granted')->sum('coins'),
            'granted_referrals' => (clone $query)->where('reward_status', 'granted')->whereNotNull('referred_user_id')->count(),
            'pending_referrals' => (clone $query)->where('reward_status', 'pending')->whereNull('referred_user_id')->count(),
            'referrer' => $referrerProfile,
            'referred_users' => $referredUsers,
        ];
    }

    public function getMyReferralSummary(User $user): array
    {
        $link = $this->getReferralLinkRowByUserId((string) $user->id);

        if (! $link) {
            $generated = $this->generateOrGetReferral($user);
            $link = (object) [
                'referral_code' => $generated['referral_code'],
                'referral_link' => $generated['referral_link'],
            ];
        }

        $stats = $this->getReferralStats($user);

        return array_merge([
            'referral_code' => (string) ($link->referral_code ?? ''),
            'referral_link' => (string) ($link->referral_link ?? ''),
        ], $stats);
    }

    private function generateUniqueReferralToken(User $user, string $codeColumn): string
    {
        return $this->referralCodeService->generateUniqueCode(
            $this->generateReferralPrefixSource($user),
            $codeColumn
        );
    }

    private function generateReferralPrefixSource(User $user): string
    {
        $fullName = trim(trim((string) ($user->first_name ?? '')) . ' ' . trim((string) ($user->last_name ?? '')));

        if ($fullName !== '') {
            return $fullName;
        }

        $displayName = trim((string) ($user->display_name ?? ''));

        if ($displayName !== '') {
            return $displayName;
        }

        return 'PEERS';
    }

    private function sendReferralEmail(User $referrer, User $referredUser, string $referralCode): void
    {
        if (blank($referrer->email)) {
            return;
        }

        $referrerName = trim((string) (($referrer->display_name ?: '') ?: (($referrer->first_name ?? '') . ' ' . ($referrer->last_name ?? ''))));
        $peerName = trim((string) (($referredUser->display_name ?: '') ?: (($referredUser->first_name ?? '') . ' ' . ($referredUser->last_name ?? ''))));
        $mailable = new ReferralJoinedMail(
            $referrerName !== '' ? $referrerName : 'Peer',
            $peerName !== '' ? $peerName : 'New Peer',
            $referralCode
        );

        try {
            Mail::to($referrer->email)->send($mailable);

            app(EmailLogService::class)->logMailableSent($mailable, [
                'user_id' => (string) $referrer->id,
                'to_email' => (string) $referrer->email,
                'to_name' => $referrerName !== '' ? $referrerName : null,
                'template_key' => 'referral_joined',
                'source_module' => 'Referral',
                'related_type' => User::class,
                'related_id' => (string) $referredUser->id,
                'payload' => [
                    'referrer_user_id' => (string) $referrer->id,
                    'referred_user_id' => (string) $referredUser->id,
                    'referral_code' => $referralCode,
                ],
            ]);

            Log::info('referral.email.sent', [
                'referrer_user_id' => (string) $referrer->id,
                'referrer_email' => (string) $referrer->email,
            ]);
        } catch (\Throwable $exception) {
            if ($exception instanceof QueryException) {
                throw $exception;
            }

            try {
                app(EmailLogService::class)->logMailableFailed($mailable, [
                    'user_id' => (string) $referrer->id,
                    'to_email' => (string) $referrer->email,
                    'to_name' => $referrerName !== '' ? $referrerName : null,
                    'template_key' => 'referral_joined',
                    'source_module' => 'Referral',
                    'related_type' => User::class,
                    'related_id' => (string) $referredUser->id,
                    'payload' => [
                        'referrer_user_id' => (string) $referrer->id,
                        'referred_user_id' => (string) $referredUser->id,
                        'referral_code' => $referralCode,
                    ],
                ], $exception);
            } catch (\Throwable $logFailureException) {
                if ($logFailureException instanceof QueryException) {
                    throw $logFailureException;
                }

                Log::warning('referral.email.log_failed', [
                    'referrer_user_id' => (string) $referrer->id,
                    'original_error' => $exception->getMessage(),
                    'logging_error' => $logFailureException->getMessage(),
                ]);
            }

            Log::warning('referral.email.failed', [
                'referrer_user_id' => (string) $referrer->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function getReferralLinkRowByUserId(string $userId): ?object
    {
        $userColumn = $this->referralLinksUserColumn();
        $codeColumn = $this->referralLinksCodeColumn();

        $row = DB::table('referral_links')
            ->where($userColumn, $userId)
            ->orderBy('id', 'asc')
            ->select([
                'id',
                DB::raw('"' . $codeColumn . '" as "referral_code"'),
                'referral_link',
            ])
            ->first();

        if (! $row) {
            return null;
        }

        return $row;
    }

    private function referralLinksUserColumn(): string
    {
        if (Schema::hasColumn('referral_links', 'user_id')) {
            return 'user_id';
        }

        return 'referrer_user_id';
    }

    private function buildReferralLinkFromToken(string $token): string
    {
        return $this->referralCodeService->buildReferralLink($token);
    }

    private function referralLinksCodeColumn(): string
    {
        foreach (['token', 'referral_code', 'code', 'ref_code', 'invite_code'] as $column) {
            if (Schema::hasColumn('referral_links', $column)) {
                return $column;
            }
        }

        throw new \RuntimeException('No referral code column found on referral_links table.');
    }
}
