<?php

namespace App\Services\Membership;

use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserMembership;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class MembershipUpgradeService
{
    public const ONLY_GREEN_PEER_STATUS = 'Only Unity Peer';

    public const ONLY_GREEN_PEER_LABEL = 'Global Peer';

    public function markAsOnlyUnityPeerAfterPayment(User $user, array|Model|null $paymentOrPlanData = null): User
    {
        return $this->markAsOnlyGreenPeerAfterPayment($user, $paymentOrPlanData);
    }

    /**
     * Mark a successfully paid membership purchase as Only Green Peer without touching coins or circle data.
     */
    public function markAsOnlyGreenPeerAfterPayment(User $user, array|Model|null $paymentOrPlanData = null): User
    {
        $data = $this->normalizeData($paymentOrPlanData);
        $payment = $paymentOrPlanData instanceof Payment
            ? $paymentOrPlanData
            : null;

        if (! $payment && ! empty($data['payment_id']) && Schema::hasTable('payments')) {
            $payment = Payment::query()->whereKey($data['payment_id'])->first();
        }

        // Determine if user has an existing active or queued plan with validity extending into the future
        // Done safely before transaction to avoid PostgreSQL transaction aborts
        $latestMembershipEnd = null;
        if (
            Schema::hasTable('user_memberships') &&
            Schema::hasColumn('user_memberships', 'user_id') &&
            Schema::hasColumn('user_memberships', 'status') &&
            Schema::hasColumn('user_memberships', 'ends_at')
        ) {
            try {
                $latestMembershipEnd = UserMembership::query()
                    ->where('user_id', $user->getKey())
                    ->when($payment?->id && Schema::hasColumn('user_memberships', 'payment_id'), fn ($q) => $q->where('payment_id', '!=', $payment->id))
                    ->whereIn('status', ['active', 'queued'])
                    ->whereNotNull('ends_at')
                    ->where('ends_at', '>', now())
                    ->max('ends_at');
            } catch (Throwable) {
                $latestMembershipEnd = null;
            }
        }

        $result = DB::transaction(function () use ($user, $data, $payment, $latestMembershipEnd): array {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $lockedPayment = $payment ? Payment::query()->whereKey($payment->getKey())->lockForUpdate()->first() : null;

            $explicitStart = $this->parseDate($data['membership_starts_at'] ?? $data['starts_at'] ?? $data['start_date'] ?? null);
            $explicitEnd = $this->parseDate($data['membership_ends_at'] ?? $data['ends_at'] ?? $data['end_date'] ?? null);

            $anchorDate = $this->parseDate($latestMembershipEnd);
            if (! $anchorDate && ! $lockedPayment && $lockedUser->membership_ends_at && Carbon::parse($lockedUser->membership_ends_at)->isFuture()) {
                $anchorDate = Carbon::parse($lockedUser->membership_ends_at);
            }

            $forceDates = (bool) ($data['force_dates'] ?? false);

            if (! $forceDates && $anchorDate && $anchorDate->isFuture()) {
                // User has remaining validity: queue new purchase directly after latest end date
                $startedAt = $anchorDate->copy();
                $expiresAt = $this->calculateExpiry($startedAt, $data, $lockedPayment);
                $status = 'queued';
            } else {
                // User is new or previously expired, or explicit forced dates provided
                $startedAt = $explicitStart ?? now();
                $expiresAt = $explicitEnd ?? $this->calculateExpiry($startedAt, $data, $lockedPayment);
                $status = $startedAt->isFuture() ? 'queued' : 'active';
            }

            // For users table: preserve earliest active start date, extend end date to furthest stacked expiry
            $userStartsAt = ($anchorDate && $anchorDate->isFuture() && $lockedUser->membership_starts_at)
                ? $lockedUser->membership_starts_at
                : $startedAt;

            $userEndsAt = ($lockedUser->membership_ends_at && Carbon::parse($lockedUser->membership_ends_at)->gt($expiresAt))
                ? Carbon::parse($lockedUser->membership_ends_at)
                : $expiresAt;

            $currentStatus = (string) ($lockedUser->membership_status ?? '');
            $normalizedCurrent = strtolower(trim(str_replace(' ', '_', $currentStatus)));

            $targetMembershipStatus = self::ONLY_GREEN_PEER_STATUS;
            if (! in_array($normalizedCurrent, ['free_trial_peer', 'free_peer', 'free_trial', 'free', 'visitor', ''], true)) {
                $targetMembershipStatus = $lockedUser->membership_status;
            }

            $userUpdates = $this->filterColumns('users', [
                'membership_status' => $targetMembershipStatus,
                'membership_starts_at' => $userStartsAt,
                'membership_ends_at' => $userEndsAt,
                'membership_start_date' => Carbon::parse($userStartsAt)->toDateString(),
                'membership_end_date' => Carbon::parse($userEndsAt)->toDateString(),
                'membership_expiry' => $userEndsAt,
                'membership_approved_at' => $data['membership_approved_at'] ?? now(),
                'membership_approved_by' => $data['membership_approved_by'] ?? null,
                'last_payment_at' => $this->parseDate($data['paid_at'] ?? $data['payment_date'] ?? null) ?? now(),
                'zoho_customer_id' => $data['zoho_customer_id'] ?? null,
                'zoho_subscription_id' => $data['zoho_subscription_id'] ?? null,
                'zoho_plan_code' => $data['zoho_plan_code'] ?? $data['plan_code'] ?? null,
                'zoho_last_invoice_id' => $data['zoho_invoice_id'] ?? $data['invoice_id'] ?? null,
            ]);

            $lockedUser->forceFill($userUpdates)->save();

            if ($lockedPayment) {
                $paymentUpdates = $this->filterColumns($lockedPayment->getTable(), [
                    'status' => $data['payment_status'] ?? 'paid',
                    'paid_at' => $this->parseDate($data['paid_at'] ?? $data['payment_date'] ?? null) ?? now(),
                    'zoho_payment_id' => $data['zoho_payment_id'] ?? $data['payment_id'] ?? null,
                    'zoho_invoice_id' => $data['zoho_invoice_id'] ?? $data['invoice_id'] ?? null,
                    'zoho_invoice_number' => $data['zoho_invoice_number'] ?? $data['invoice_number'] ?? null,
                    'zoho_subscription_id' => $data['zoho_subscription_id'] ?? $data['subscription_id'] ?? null,
                    'zoho_hostedpage_id' => $data['zoho_hostedpage_id'] ?? $data['hostedpage_id'] ?? $data['hosted_page_id'] ?? null,
                    'zoho_plan_code' => $data['zoho_plan_code'] ?? $data['plan_code'] ?? null,
                ]);

                if ($paymentUpdates !== []) {
                    $lockedPayment->forceFill($paymentUpdates)->save();
                }
            }

            return [
                'user' => $lockedUser,
                'payment' => $lockedPayment,
                'startedAt' => $startedAt,
                'expiresAt' => $expiresAt,
                'data' => $data,
                'status' => $status,
            ];
        });

        $freshUser = $result['user'] ?? $user;
        $freshPayment = $result['payment'] ?? null;

        Log::info('Membership payment completed', [
            'user_id' => (string) $freshUser->id,
            'payment_id' => $freshPayment?->getKey(),
            'membership_status' => self::ONLY_GREEN_PEER_STATUS,
            'status' => $result['status'] ?? 'active',
            'membership_starts_at' => optional($result['startedAt'])->toDateTimeString(),
            'membership_expires_at' => optional($result['expiresAt'])->toDateTimeString(),
        ]);

        try {
            $this->syncUserMembershipRow($freshUser, $freshPayment, $result['startedAt'], $result['expiresAt'], $result['data'], $result['status']);
        } catch (Throwable $throwable) {
            Log::warning('Membership payment user_memberships sync skipped', [
                'user_id' => (string) $freshUser->id,
                'payment_id' => $freshPayment?->id,
                'error' => $throwable->getMessage(),
            ]);
        }

        try {
            app(MembershipWelcomeEmailService::class)->sendIfEligible($freshUser);
        } catch (Throwable $throwable) {
            Log::warning('Membership welcome email delivery skipped', [
                'user_id' => (string) $freshUser->id,
                'error' => $throwable->getMessage(),
            ]);
        }

        return $freshUser;
    }

    public function membershipResponseData(User $user, string $paymentStatus = 'paid'): array
    {
        return [
            'payment_status' => $paymentStatus,
            'membership_status' => $user->membership_status,
            'membership_badge' => strtoupper(str_replace(' ', '_', (string) $user->membership_status)),
            'membership_label' => self::ONLY_GREEN_PEER_LABEL,
            'membership_started_at' => $user->membership_starts_at ?? $user->membership_start_date ?? null,
            'membership_expires_at' => $user->membership_ends_at ?? $user->membership_end_date ?? $user->membership_expiry ?? null,
        ];
    }

    private function calculateExpiry(Carbon $startedAt, array $data, ?Payment $payment): Carbon
    {
        $durationMonths = $this->durationMonths($data, $payment);

        return $startedAt->copy()->addMonths($durationMonths)->endOfDay();
    }

    private function durationMonths(array $data, ?Payment $payment): int
    {
        foreach (['duration_months', 'months'] as $key) {
            if ((int) ($data[$key] ?? 0) > 0) {
                return (int) $data[$key];
            }
        }

        $planId = $data['membership_plan_id'] ?? $payment?->membership_plan_id ?? null;
        if ($planId && Schema::hasTable('membership_plans')) {
            $planIdStr = trim((string) $planId);
            $plan = Str::isUuid($planIdStr)
                ? MembershipPlan::query()->find($planIdStr)
                : MembershipPlan::query()->where('slug', $planIdStr)->first();
            if ($plan && (int) $plan->duration_months > 0) {
                return (int) $plan->duration_months;
            }
        }

        $amount = $this->normalizeAmount($data['amount'] ?? $data['total_amount'] ?? $data['base_amount'] ?? $payment?->total_amount ?? $payment?->base_amount ?? null);

        return match ($amount) {
            3600 => 1,
            18000 => 12,
            25000 => 24,
            100000 => 12,
            default => $this->durationFromPlanText($data),
        };
    }

    private function durationFromPlanText(array $data): int
    {
        $text = strtolower(trim(implode(' ', array_filter([
            $data['plan_name'] ?? null,
            $data['plan'] ?? null,
            $data['zoho_plan_code'] ?? $data['plan_code'] ?? null,
        ], static fn ($value) => is_scalar($value) && trim((string) $value) !== ''))));

        if (str_contains($text, 'leader') || str_contains($text, '2 year') || str_contains($text, '24') || str_contains($text, '014')) {
            return 24;
        }

        if (str_contains($text, 'starter') || str_contains($text, '1 month') || str_contains($text, 'monthly') || str_contains($text, '012')) {
            return 1;
        }

        if (str_contains($text, '1 year') || str_contains($text, 'annual') || str_contains($text, '013') || str_contains($text, '015')) {
            return 12;
        }

        return 12;
    }

    private function normalizeAmount(mixed $amount): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $numeric = (float) preg_replace('/[^0-9.]/', '', (string) $amount);
        if ($numeric > 1000000) {
            $numeric = $numeric / 100;
        }

        return (int) round($numeric);
    }

    private function normalizeData(array|Model|null $paymentOrPlanData): array
    {
        if ($paymentOrPlanData instanceof Model) {
            return $paymentOrPlanData->getAttributes();
        }

        return $paymentOrPlanData ?? [];
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    public function syncUserMembershipRow(User $user, ?Payment $payment, CarbonInterface|\DateTimeInterface|string $startedAt, CarbonInterface|\DateTimeInterface|string $expiresAt, array $data = [], string $status = 'active'): ?UserMembership
    {
        if (! Schema::hasTable('user_memberships')) {
            return null;
        }

        $startedAt = Carbon::parse($startedAt);
        $expiresAt = Carbon::parse($expiresAt);

        try {
            $planId = $data['membership_plan_id'] ?? $payment?->membership_plan_id ?? null;
            if (! $planId && Schema::hasTable('membership_plans')) {
                $planCode = (string) ($data['zoho_plan_code'] ?? $data['plan_code'] ?? $payment?->zoho_plan_code ?? $user->zoho_plan_code ?? '');
                if ($planCode !== '') {
                    $matchedPlan = MembershipPlan::query()
                        ->where('slug', strtolower($planCode))
                        ->orWhere('name', $planCode)
                        ->first();

                    if ($matchedPlan) {
                        $planId = $matchedPlan->id;
                    } else {
                        $planName = $data['plan_name'] ?? "Pro Plan ({$planCode})";
                        $durationMonths = (int) ($data['duration_months'] ?? 1);
                        $newPlan = MembershipPlan::query()->create([
                            'id' => (string) Str::uuid(),
                            'name' => $planName,
                            'slug' => strtolower($planCode),
                            'price' => (float) ($data['amount'] ?? 0),
                            'duration_days' => $durationMonths * 30,
                            'duration_months' => $durationMonths,
                            'is_active' => true,
                            'is_free' => false,
                            'sort_order' => 10,
                        ]);
                        $planId = $newPlan->id;
                    }
                }

                if (! $planId) {
                    $planId = MembershipPlan::query()->value('id');
                }
            }

            // Expire previous memberships whose validity has already elapsed
            UserMembership::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNotNull('ends_at')
                ->where('ends_at', '<=', now())
                ->update(['status' => 'expired']);

            $existing = null;
            if ($payment) {
                $existing = UserMembership::query()->where('payment_id', $payment->id)->first();
            }

            if (! $existing) {
                $existing = UserMembership::query()
                    ->where('user_id', $user->id)
                    ->where('starts_at', $startedAt)
                    ->where('ends_at', $expiresAt)
                    ->first();
            }

            if ($existing) {
                $existing->forceFill([
                    'starts_at' => $startedAt,
                    'ends_at' => $expiresAt,
                    'status' => $status,
                    'payment_id' => $payment?->id ?? $existing->payment_id,
                    'membership_plan_id' => $planId ?? $existing->membership_plan_id,
                ])->save();

                return $existing;
            }

            return UserMembership::query()->create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'membership_plan_id' => $planId,
                'starts_at' => $startedAt,
                'ends_at' => $expiresAt,
                'status' => $status,
                'payment_id' => $payment?->id,
            ]);
        } catch (Throwable $throwable) {
            Log::warning('Membership payment user_memberships sync skipped', [
                'user_id' => (string) $user->id,
                'payment_id' => $payment?->id,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @var array<string, array<string, int>>
     */
    private static array $tableColumnsCache = [];

    private function filterColumns(string $table, array $values): array
    {
        if (! isset(self::$tableColumnsCache[$table])) {
            self::$tableColumnsCache[$table] = array_flip(Schema::getColumnListing($table));
        }

        $tableColumns = self::$tableColumnsCache[$table];

        return collect($values)
            ->filter(fn ($value, string $column) => $value !== null && isset($tableColumns[$column]))
            ->all();
    }
}
