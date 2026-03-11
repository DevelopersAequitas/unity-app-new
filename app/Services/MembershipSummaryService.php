<?php

namespace App\Services;

use App\Models\CircleMember;
use App\Models\CircleSubscription;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class MembershipSummaryService
{
    public function getSummary(User $user): array
    {
        $user->loadMissing([
            'city:id,name',
            'cityRelation:id,name',
            'activeCircle:id,name',
        ]);

        $peerSinceAt = $this->resolveDateByPriority($user, [
            'membership_starts_at',
            'membership_started_at',
            'peer_since',
            'created_at',
        ]);

        $peerTillAt = $this->resolveDateByPriority($user, [
            'membership_expires_at',
            'membership_valid_till',
            'peer_till',
            'membership_ends_at',
            'membership_expiry',
        ]);

        return [
            'user_name' => $this->resolveUserName($user),
            'company_name' => $user->company_name,
            'city' => $this->resolveCityName($user),
            'circle' => $this->resolvePrimaryCircleName($user),
            'membership_status' => $this->firstFilledValue($user, ['membership_status', 'membership_type', 'membership']),
            'membership_expiry' => $this->formatDate($this->resolveDateByPriority($user, [
                'membership_expires_at',
                'membership_valid_till',
                'peer_till',
                'membership_ends_at',
                'membership_expiry',
            ])),
            'peer_since' => $this->formatDate($peerSinceAt),
            'peer_till' => $this->formatDate($peerTillAt),
            'total_experience' => $this->formatExperience($user),
            'peer_payment_details' => $this->getPeerPayments($user),
            'circle_wise_details' => $this->getCircleWiseDetails($user),
        ];
    }

    private function resolveUserName(User $user): ?string
    {
        $displayName = trim((string) ($user->display_name ?? ''));

        if ($displayName !== '') {
            return $displayName;
        }

        $fullName = trim(trim((string) ($user->first_name ?? '')) . ' ' . trim((string) ($user->last_name ?? '')));

        if ($fullName !== '') {
            return $fullName;
        }

        $name = trim((string) ($user->getAttribute('name') ?? ''));

        return $name !== '' ? $name : null;
    }

    private function resolveCityName(User $user): ?string
    {
        $cityFromRelation = trim((string) (
            $user->city?->name
            ?? $user->cityRelation?->name
            ?? ''
        ));

        if ($cityFromRelation !== '') {
            return $cityFromRelation;
        }

        $city = $this->firstFilledValue($user, ['city', 'current_city']);

        if (is_array($city)) {
            $cityName = trim((string) ($city['name'] ?? ''));

            return $cityName !== '' ? $cityName : null;
        }

        $cityString = trim((string) ($city ?? ''));

        return $cityString !== '' ? $cityString : null;
    }

    private function resolvePrimaryCircleName(User $user): ?string
    {
        if ($user->activeCircle?->name) {
            return $user->activeCircle->name;
        }

        $circleName = trim((string) ($this->firstFilledValue($user, ['active_circle_name', 'circle_name']) ?? ''));

        if ($circleName !== '') {
            return $circleName;
        }

        $currentMemberCircle = CircleMember::query()
            ->with('circle:id,name')
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->whereNull('left_at')
            ->orderByDesc('joined_at')
            ->first();

        if ($currentMemberCircle?->circle?->name) {
            return $currentMemberCircle->circle->name;
        }

        $latestMemberCircle = CircleMember::query()
            ->with('circle:id,name')
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->orderByDesc('joined_at')
            ->first();

        return $latestMemberCircle?->circle?->name;
    }

    private function getPeerPayments(User $user): array
    {
        $query = Payment::query()->where('user_id', $user->id);

        if (Schema::hasColumn('payments', 'membership_plan_id')) {
            $query->whereNotNull('membership_plan_id');
        }

        if (Schema::hasColumn('payments', 'created_at')) {
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('id');
        }

        return $query
            ->get()
            ->map(fn (Payment $payment): array => $this->mapPayment($payment))
            ->values()
            ->all();
    }

    private function getCircleWiseDetails(User $user): array
    {
        $circleMembers = CircleMember::query()
            ->with('circle:id,name')
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->orderByDesc('joined_at')
            ->get();

        if ($circleMembers->isEmpty()) {
            return [];
        }

        $circleIds = $circleMembers->pluck('circle_id')->filter()->unique()->values();

        $subscriptionsByCircle = CircleSubscription::query()
            ->where('user_id', $user->id)
            ->whereIn('circle_id', $circleIds)
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('circle_id');

        return $circleMembers->map(function (CircleMember $circleMember) use ($subscriptionsByCircle): array {
            /** @var Collection<int, CircleSubscription> $subscriptions */
            $subscriptions = $subscriptionsByCircle->get($circleMember->circle_id, collect());

            $paidSubscriptions = $subscriptions
                ->filter(function (CircleSubscription $subscription): bool {
                    $status = strtolower((string) ($subscription->status ?? ''));

                    return in_array($status, ['paid', 'success', 'completed', 'active'], true);
                })
                ->values();

            $membershipTill = $this->resolveDateByPriority($circleMember, [
                'membership_till',
                'membership_expires_at',
                'expires_at',
                'valid_till',
                'left_at',
            ]);

            if (! $membershipTill) {
                $membershipTill = $this->resolveDateByPriority($subscriptions->first(), ['expires_at']);
            }

            $joinDate = $this->resolveDateByPriority($circleMember, ['joined_at', 'created_at']);

            return [
                'circle_id' => (string) $circleMember->circle_id,
                'circle_name' => $circleMember->circle?->name,
                'circle_join_date' => $this->formatDate($joinDate),
                'circle_membership_till' => $this->formatDate($membershipTill),
                'payment_details' => $paidSubscriptions
                    ->map(fn (CircleSubscription $subscription): array => $this->mapCirclePayment($subscription))
                    ->values()
                    ->all(),
            ];
        })->values()->all();
    }

    private function mapPayment(Payment $payment): array
    {
        $reference = $this->firstFilledValue($payment, [
            'reference_id',
            'transaction_id',
            'razorpay_payment_id',
            'zoho_payment_id',
            'id',
        ]);

        $amount = $this->firstFilledValue($payment, ['total_amount', 'amount', 'base_amount']);
        $mode = $this->firstFilledValue($payment, ['mode', 'payment_mode', 'provider']) ?? 'online';
        $status = $this->firstFilledValue($payment, ['status']) ?? 'pending';
        $dateTimeValue = $this->resolveDateByPriority($payment, ['paid_at', 'created_at']);

        return [
            'amount' => $amount !== null ? (float) $amount : null,
            'mode' => (string) $mode,
            'date_time' => $this->formatDateTime($dateTimeValue),
            'reference_id' => $reference !== null ? (string) $reference : null,
            'status' => (string) $status,
        ];
    }

    private function mapCirclePayment(CircleSubscription $subscription): array
    {
        $reference = $this->firstFilledValue($subscription, [
            'reference_id',
            'transaction_id',
            'zoho_payment_id',
            'zoho_subscription_id',
            'id',
        ]);

        return [
            'amount' => $subscription->amount !== null ? (float) $subscription->amount : null,
            'mode' => $this->firstFilledValue($subscription, ['mode', 'payment_mode', 'provider']) ?? 'online',
            'date_time' => $this->formatDateTime($this->resolveDateByPriority($subscription, ['paid_at', 'created_at'])),
            'reference_id' => $reference !== null ? (string) $reference : null,
            'status' => (string) ($subscription->status ?? 'pending'),
        ];
    }

    private function resolveDateByPriority(mixed $model, array $fields): ?Carbon
    {
        if (! $model) {
            return null;
        }

        foreach ($fields as $field) {
            $value = data_get($model, $field);

            if (empty($value)) {
                continue;
            }

            try {
                return $value instanceof Carbon ? $value : Carbon::parse($value);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function formatDate(?Carbon $date): ?string
    {
        return $date?->format('d M Y');
    }

    private function formatDateTime(?Carbon $date): ?string
    {
        return $date?->format('d M Y, h:i A');
    }

    private function formatExperience(User $user): string
    {
        $years = (int) ($user->experience_years ?? 0);

        $months = 0;

        if (Schema::hasColumn('users', 'experience_months')) {
            $months = max(0, (int) ($user->getAttribute('experience_months') ?? 0));
        }

        if ($months > 0) {
            return sprintf('%d Years %d Months', $years, $months);
        }

        return sprintf('%d Years', max(0, $years));
    }

    private function firstFilledValue(mixed $model, array $fields): mixed
    {
        foreach ($fields as $field) {
            $value = data_get($model, $field);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
