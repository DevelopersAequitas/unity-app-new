<?php

namespace App\Services\Circles;

use App\Models\CircleJoinRequest;
use App\Models\CircleSubscription;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CircleJoinRequestPaymentSyncService
{
    public function __construct(
        private readonly CircleJoinRequestNotificationService $notificationService,
        private readonly CircleMembershipSyncService $circleMembershipSyncService,
    ) {
    }

    public function syncPaidSubscription(CircleSubscription $subscription, array $context = []): void
    {
        DB::transaction(function () use ($subscription, $context): void {
            $lockedSubscription = CircleSubscription::query()
                ->with(['user', 'circle'])
                ->where('id', $subscription->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedSubscription || ! $lockedSubscription->user) {
                return;
            }

            $user = $lockedSubscription->user;

            $joinRequest = CircleJoinRequest::query()
                ->where('user_id', $lockedSubscription->user_id)
                ->where('circle_id', $lockedSubscription->circle_id)
                ->whereIn('status', [
                    CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE,
                    CircleJoinRequest::STATUS_PENDING_ID_APPROVAL,
                    CircleJoinRequest::STATUS_PAID,
                    CircleJoinRequest::STATUS_CIRCLE_MEMBER,
                ])
                ->latest('requested_at')
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            $member = $this->circleMembershipSyncService->syncPaidMembershipFromSubscription(
                $lockedSubscription,
                $lockedSubscription->paid_at ?: now(),
                $lockedSubscription->started_at ?: now(),
                $lockedSubscription->expires_at
            );

            $paymentId = $this->upsertCirclePaymentAudit($lockedSubscription, $context);

            Log::info('circle membership synced from paid subscription', [
                'circle_subscription_id' => $lockedSubscription->id,
                'user_id' => $lockedSubscription->user_id,
                'circle_id' => $lockedSubscription->circle_id,
                'membership_id' => $member->id ?? null,
            ]);

            if ($joinRequest) {
                $newStatus = in_array((string) $joinRequest->status, [
                    CircleJoinRequest::STATUS_PAID,
                    CircleJoinRequest::STATUS_CIRCLE_MEMBER,
                ], true)
                    ? (string) $joinRequest->status
                    : CircleJoinRequest::STATUS_CIRCLE_MEMBER;

                $joinRequest->forceFill([
                    'circle_subscription_id' => $lockedSubscription->id,
                    'payment_id' => $paymentId ?: ($context['payment_id'] ?? null),
                    'payment_reference' => $lockedSubscription->zoho_payment_id
                        ?: ($context['payment_reference'] ?? $context['payment_number'] ?? null),
                    'payment_status' => 'paid',
                    'paid_at' => $lockedSubscription->paid_at ?: now(),
                    'approved_membership_id' => $member->id ?? $joinRequest->approved_membership_id,
                    'status' => $newStatus,
                    'fee_marked_at' => $joinRequest->fee_marked_at ?: now(),
                    'fee_paid_at' => $joinRequest->fee_paid_at ?: ($lockedSubscription->paid_at ?: now()),
                ])->save();

                Log::info('circle join request marked as paid from subscription', [
                    'circle_join_request_id' => $joinRequest->id,
                    'circle_subscription_id' => $lockedSubscription->id,
                    'membership_id' => $member->id ?? null,
                    'payment_reference' => $joinRequest->payment_reference,
                    'status' => $joinRequest->status,
                ]);

                try {
                    $this->notificationService->sendCircleMemberConfirmedToUser(
                        $joinRequest->fresh(['user', 'circle'])
                    );
                } catch (Throwable $exception) {
                    Log::warning('Circle join request paid notification failed after payment sync', [
                        'circle_join_request_id' => $joinRequest->id,
                        'user_id' => $user->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            $this->circleMembershipSyncService->refreshUserActiveCircleSummary($user);
            $this->updateUserCircleMembershipTier($user->fresh());
        });
    }

    private function upsertCirclePaymentAudit(CircleSubscription $subscription, array $context): ?string
    {
        if (! Schema::hasTable('payments')) {
            return null;
        }

        $paymentId = (string) ($context['payment_id'] ?? '');

        if ($paymentId === '' || ! Str::isUuid($paymentId)) {
            $paymentId = (string) Str::uuid();
        }

        $payload = [];

        if (Schema::hasColumn('payments', 'id')) {
            $payload['id'] = $paymentId;
        }

        if (Schema::hasColumn('payments', 'user_id')) {
            $payload['user_id'] = $subscription->user_id;
        }

        if (Schema::hasColumn('payments', 'status')) {
            $payload['status'] = Payment::STATUS_SUCCESS;
        }

        if (Schema::hasColumn('payments', 'paid_at')) {
            $payload['paid_at'] = $subscription->paid_at ?: now();
        }

        if (Schema::hasColumn('payments', 'total_amount')) {
            $payload['total_amount'] = (float) ($context['amount'] ?? 0);
        }

        if (Schema::hasColumn('payments', 'base_amount') && ! isset($payload['base_amount'])) {
            $payload['base_amount'] = (float) ($context['amount'] ?? 0);
        }

        if (Schema::hasColumn('payments', 'gst_percent')) {
            $payload['gst_percent'] = 0;
        }

        if (Schema::hasColumn('payments', 'gst_amount')) {
            $payload['gst_amount'] = 0;
        }

        if (Schema::hasColumn('payments', 'razorpay_payment_id')) {
            $payload['razorpay_payment_id'] = $subscription->zoho_payment_id
                ?: ($context['payment_reference'] ?? $context['payment_number'] ?? null);
        }

        if (count($payload) === 0) {
            return null;
        }

        if (Schema::hasColumn('payments', 'id')) {
            Payment::query()->updateOrCreate(
                ['id' => $paymentId],
                $payload
            );
        } else {
            Payment::query()->create($payload);
        }

        Log::info('circle payment audit upserted', [
            'payment_id' => $paymentId,
            'circle_subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
        ]);

        return $paymentId;
    }

    public function markRequestPaidFromUserCircle(User $user): void
    {
        $freshUser = User::query()->find($user->id);

        if (! $freshUser) {
            Log::warning('circle join request sync skipped - user not found during refresh', [
                'user_id' => $user->id,
            ]);

            return;
        }

        $activeCircleId = (string) ($freshUser->active_circle_id ?? '');

        Log::info('circle join request payment sync lookup started', [
            'user_id' => $freshUser->id,
            'active_circle_id' => $activeCircleId,
        ]);

        if ($activeCircleId === '') {
            Log::info('circle join request payment sync skipped - empty active_circle_id', [
                'user_id' => $freshUser->id,
            ]);

            return;
        }

        $joinRequest = CircleJoinRequest::query()
            ->where('user_id', $freshUser->id)
            ->where('circle_id', $activeCircleId)
            ->where('status', CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE)
            ->latest('created_at')
            ->first();

        if (! $joinRequest) {
            Log::info('circle join request sync skipped - no matching pending request found', [
                'user_id' => $freshUser->id,
                'active_circle_id' => $activeCircleId,
                'expected_status' => CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE,
            ]);

            return;
        }

        $joinRequest->forceFill([
            'status' => CircleJoinRequest::STATUS_PAID,
            'payment_status' => 'paid',
            'paid_at' => $joinRequest->paid_at ?: now(),
            'fee_marked_at' => $joinRequest->fee_marked_at ?: now(),
            'fee_paid_at' => $joinRequest->fee_paid_at ?: now(),
            'updated_at' => now(),
        ])->save();

        Log::info('circle join request synced to paid', [
            'request_id' => $joinRequest->id,
            'user_id' => $freshUser->id,
            'circle_id' => $activeCircleId,
            'old_status' => CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE,
            'new_status' => CircleJoinRequest::STATUS_PAID,
        ]);

        try {
            $this->notificationService->sendCircleMemberConfirmedToUser($joinRequest->fresh(['user', 'circle']));
        } catch (Throwable $exception) {
            Log::warning('Circle join request paid notification failed after payment sync', [
                'circle_join_request_id' => $joinRequest->id,
                'user_id' => $freshUser->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function updateUserCircleMembershipTier(User $user): void
    {
        try {
            $activePaidCircleCount = CircleSubscription::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->count();

            if ($activePaidCircleCount <= 0) {
                return;
            }

            $nextStatus = $activePaidCircleCount > 1 ? 'Multi Circle Peer' : 'Circle Peer';

            if ((string) $user->membership_status !== $nextStatus) {
                $user->forceFill(['membership_status' => $nextStatus])->save();
            }
        } catch (Throwable $exception) {
            Log::warning('Failed to sync user circle membership tier', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
