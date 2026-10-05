<?php

declare(strict_types=1);

namespace App\Services\Circles;

use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\CircleCategoryLevel3;
use App\Models\CircleCategoryLevel4;
use App\Models\CircleJoinRequest;
use App\Models\CircleMember;
use App\Models\CircleMemberCategorySelection;
use App\Models\JoinedCircleCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\Notifications\NotifyUserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CircleJoinRequestService
{
    public function __construct(
        private readonly NotifyUserService $notifyUserService,
        private readonly CircleJoinRequestNotificationService $circleJoinRequestNotificationService,
    ) {}

    public function submitRequest(User $user, Circle $circle, ?string $reason, array $categoryIds = []): CircleJoinRequest
    {
        $request = DB::transaction(function () use ($user, $circle, $reason, $categoryIds) {
            $alreadyMember = CircleMember::query()
                ->where('circle_id', $circle->id)
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->where('status', 'approved')
                ->exists();

            if ($alreadyMember) {
                throw ValidationException::withMessages([
                    'circle_id' => ['You are already a member of this circle.'],
                ]);
            }

            $existingRequest = CircleJoinRequest::query()
                ->where('user_id', $user->id)
                ->where('circle_id', $circle->id)
                ->whereIn('status', CircleJoinRequest::ACTIVE_STATUSES)
                ->first();

            $payload = [
                'user_id' => $user->id,
                'circle_id' => $circle->id,
                'reason_for_joining' => $reason,
                'status' => CircleJoinRequest::STATUS_PENDING_CD_APPROVAL,
                'requested_at' => now(),
            ];

            $selection = [
                'level1_category_id' => isset($categoryIds['level1_category_id']) ? (int) $categoryIds['level1_category_id'] : null,
                'level2_category_id' => isset($categoryIds['level2_category_id']) ? (int) $categoryIds['level2_category_id'] : null,
                'level3_category_id' => isset($categoryIds['level3_category_id']) ? (int) $categoryIds['level3_category_id'] : null,
                'level4_category_id' => isset($categoryIds['level4_category_id']) ? (int) $categoryIds['level4_category_id'] : null,
            ];

            foreach ($selection as $key => $value) {
                if ($value !== null && $value <= 0) {
                    $selection[$key] = null;
                }
            }

            $selection = $this->resolveCategorySelection($selection);

            $hasCategoryColumns = Schema::hasColumns('circle_join_requests', [
                'level1_category_id',
                'level2_category_id',
                'level3_category_id',
                'level4_category_id',
            ]);

            if ($hasCategoryColumns) {
                $payload = array_merge($payload, $selection);
            } else {
                $payload['notes'] = array_filter([
                    'category_selection' => array_filter($selection, fn ($value) => $value !== null),
                ]);
            }

            if ($existingRequest) {
                $existingRequest->fill($payload);
                $existingRequest->save();

                return $existingRequest;
            }

            return CircleJoinRequest::query()->create($payload);
        });

        $this->notifyStakeholders($request, $user);

        return $request;
    }

    private function resolveCategorySelection(array $selection): array
    {
        $level4CategoryId = (int) ($selection['level4_category_id'] ?? 0);
        if ($level4CategoryId <= 0) {
            return $selection;
        }

        $level4 = CircleCategoryLevel4::query()
            ->select(['id', 'level3_id', 'level2_id', 'circle_category_id'])
            ->find($level4CategoryId);

        if (! $level4) {
            return $selection;
        }

        $level3CategoryId = (int) ($selection['level3_category_id'] ?? 0);
        if ($level3CategoryId <= 0 && (int) $level4->level3_id > 0) {
            $selection['level3_category_id'] = (int) $level4->level3_id;
            $level3CategoryId = (int) $selection['level3_category_id'];
        }

        $level3Level2Id = null;
        if ($level3CategoryId > 0) {
            $level3 = CircleCategoryLevel3::query()
                ->select(['id', 'level2_id'])
                ->find($level3CategoryId);

            $level3Level2Id = $level3 ? (int) ($level3->level2_id ?? 0) : null;
        }

        $level2CategoryId = (int) ($selection['level2_category_id'] ?? 0);
        if ($level2CategoryId <= 0) {
            $level2FromLevel4 = (int) ($level4->level2_id ?? 0);
            $selection['level2_category_id'] = $level2FromLevel4 > 0 ? $level2FromLevel4 : (int) ($level3Level2Id ?? 0);
        }

        $level1CategoryId = (int) ($selection['level1_category_id'] ?? 0);
        if ($level1CategoryId <= 0 && (int) $level4->circle_category_id > 0) {
            $selection['level1_category_id'] = (int) $level4->circle_category_id;
        }

        foreach (['level1_category_id', 'level2_category_id', 'level3_category_id', 'level4_category_id'] as $key) {
            $selection[$key] = (int) ($selection[$key] ?? 0) > 0 ? (int) $selection[$key] : null;
        }

        return $selection;
    }

    public function approveByCd(CircleJoinRequest $request, User|AdminUser $admin, ?string $circleId = null): CircleJoinRequest
    {
        $updated = DB::transaction(function () use ($request, $admin, $circleId) {
            $locked = $this->lockOrFail($request->id);
            $this->ensureStatus($locked, CircleJoinRequest::STATUS_PENDING_CD_APPROVAL);

            $oldStatus = (string) $locked->status;
            $approverUserId = $this->resolveApproverUserId($admin);

            $updates = [
                'status' => CircleJoinRequest::STATUS_PENDING_ID_APPROVAL,
                'cd_approved_by' => $approverUserId,
                'cd_approved_at' => now(),
                'cd_rejected_by' => null,
                'cd_rejected_at' => null,
                'cd_rejection_reason' => null,
            ];

            if (! empty($circleId) && Circle::query()->where('id', $circleId)->exists()) {
                $updates['circle_id'] = $circleId;
            }

            $locked->forceFill($updates)->save();

            $updated = $locked->fresh(['user', 'circle']);

            Log::info('circle_join_request.approved_cd', [
                'request_id' => $updated->id,
                'old_status' => $oldStatus,
                'new_status' => (string) $updated->status,
                'approved_by' => $admin->id,
            ]);

            return $updated;
        });

        $this->safeSendTransitionNotifications(
            $updated,
            fn () => $this->circleJoinRequestNotificationService->sendCdApprovedToUser($updated)
        );

        $this->safeSendTransitionNotifications(
            $updated,
            fn () => $this->circleJoinRequestNotificationService->sendJoinRequestApprovedCongratulations($updated)
        );

        return $updated;
    }

    public function rejectByCd(CircleJoinRequest $request, User|AdminUser $admin, string $reason): CircleJoinRequest
    {
        $updated = DB::transaction(function () use ($request, $admin, $reason) {
            $locked = $this->lockOrFail($request->id);
            $this->ensureStatus($locked, CircleJoinRequest::STATUS_PENDING_CD_APPROVAL);

            $approverUserId = $this->resolveApproverUserId($admin);

            $locked->forceFill([
                'status' => CircleJoinRequest::STATUS_REJECTED_BY_CD,
                'cd_rejected_by' => $approverUserId,
                'cd_rejected_at' => now(),
                'cd_rejection_reason' => $reason,
            ])->save();

            return $locked->fresh(['user', 'circle']);
        });

        $this->safeSendTransitionNotifications(
            $updated,
            fn () => $this->circleJoinRequestNotificationService->sendCdRejectedToUser($updated)
        );

        return $updated;
    }

    public function approveById(CircleJoinRequest $request, User|AdminUser $admin, ?string $circleId = null): CircleJoinRequest
    {
        $updated = DB::transaction(function () use ($request, $admin, $circleId) {
            $locked = $this->lockOrFail($request->id);
            $this->ensureStatus($locked, CircleJoinRequest::STATUS_PENDING_ID_APPROVAL);

            $oldStatus = (string) $locked->status;
            $approverUserId = $this->resolveApproverUserId($admin);

            $updates = [
                'status' => CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE,
                'id_approved_by' => $approverUserId,
                'id_approved_at' => now(),
                'id_rejected_by' => null,
                'id_rejected_at' => null,
                'id_rejection_reason' => null,
            ];

            if (! empty($circleId) && Circle::query()->where('id', $circleId)->exists()) {
                $updates['circle_id'] = $circleId;
            }

            $locked->forceFill($updates)->save();

            $updated = $locked->fresh(['user', 'circle']);

            Log::info('circle_join_request.approved_id', [
                'request_id' => $updated->id,
                'old_status' => $oldStatus,
                'new_status' => (string) $updated->status,
                'approved_by' => $admin->id,
            ]);

            return $updated;
        });

        $this->safeSendTransitionNotifications(
            $updated,
            fn () => $this->circleJoinRequestNotificationService->sendIdApprovedToUser($updated)
        );

        $this->safeSendTransitionNotifications(
            $updated,
            fn () => $this->circleJoinRequestNotificationService->sendJoinRequestApprovedCongratulations($updated)
        );

        return $updated;
    }

    public function rejectById(CircleJoinRequest $request, User|AdminUser $admin, string $reason): CircleJoinRequest
    {
        $updated = DB::transaction(function () use ($request, $admin, $reason) {
            $locked = $this->lockOrFail($request->id);
            $this->ensureStatus($locked, CircleJoinRequest::STATUS_PENDING_ID_APPROVAL);

            $oldStatus = (string) $locked->status;
            $approverUserId = $this->resolveApproverUserId($admin);

            $locked->forceFill([
                'status' => CircleJoinRequest::STATUS_REJECTED_BY_ID,
                'id_rejected_by' => $approverUserId,
                'id_rejected_at' => now(),
                'id_rejection_reason' => $reason,
            ])->save();

            return $locked->fresh(['user', 'circle']);
        });

        $this->safeSendTransitionNotifications(
            $updated,
            fn () => $this->circleJoinRequestNotificationService->sendIdRejectedToUser($updated)
        );

        return $updated;
    }

    private function resolveApproverUserId(User|AdminUser $admin): ?string
    {
        if ($admin instanceof User) {
            return $admin->id;
        }

        return DB::table('users')->where('id', $admin->id)->exists() ? $admin->id : null;
    }

    public function cancelByUser(CircleJoinRequest $request, User $user): CircleJoinRequest
    {
        return DB::transaction(function () use ($request, $user) {
            $locked = $this->lockOrFail($request->id);

            if ((string) $locked->user_id !== (string) $user->id) {
                throw ValidationException::withMessages([
                    'id' => ['You can only cancel your own request.'],
                ]);
            }

            if (! in_array($locked->status, [
                CircleJoinRequest::STATUS_PENDING_CD_APPROVAL,
                CircleJoinRequest::STATUS_PENDING_ID_APPROVAL,
            ], true)) {
                throw ValidationException::withMessages([
                    'id' => ['This request can no longer be cancelled.'],
                ]);
            }

            $locked->forceFill([
                'status' => CircleJoinRequest::STATUS_CANCELLED,
            ])->save();

            return $locked->fresh(['user', 'circle']);
        });
    }

    public function markPaidAndConvertToMember(CircleJoinRequest $request, array $context = []): CircleJoinRequest
    {
        $updated = DB::transaction(function () use ($request, $context) {
            $locked = $this->lockOrFail($request->id);
            $this->ensureStatus($locked, CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE);

            $startedAt = $context['started_at'] ?? now();

            $member = CircleMember::withTrashed()
                ->where('circle_id', $locked->circle_id)
                ->where('user_id', $locked->user_id)
                ->first();

            if ($member) {
                if ($member->trashed()) {
                    $member->restore();
                }

                $member->forceFill([
                    'status' => 'approved',
                    'role' => $member->role ?: 'member',
                    'joined_at' => $member->joined_at ?: $startedAt,
                    'left_at' => null,
                ])->save();
            } else {
                CircleMember::query()->create([
                    'circle_id' => $locked->circle_id,
                    'user_id' => $locked->user_id,
                    'status' => 'approved',
                    'role' => 'member',
                    'joined_at' => $startedAt,
                ]);
            }

            $locked->forceFill([
                'status' => CircleJoinRequest::STATUS_CIRCLE_MEMBER,
                'fee_marked_at' => $locked->fee_marked_at ?: now(),
                'fee_paid_at' => now(),
                'notes' => array_merge((array) $locked->notes, $context),
            ])->save();

            return $locked->fresh(['user', 'circle']);
        });

        $this->safeSendTransitionNotifications(
            $updated,
            fn () => $this->circleJoinRequestNotificationService->sendCircleMemberConfirmedToUser($updated)
        );

        return $updated;
    }

    public function approveAndJoinCircle(CircleJoinRequest $request, User|AdminUser $admin, ?string $circleId = null): CircleJoinRequest
    {
        if (! empty($circleId) && Circle::query()->where('id', $circleId)->exists()) {
            $request->circle_id = $circleId;
            $request->save();
        }

        if (empty($request->circle_id)) {
            throw ValidationException::withMessages([
                'circle_id' => ['Requested Circle is missing or invalid.'],
            ]);
        }

        $circle = Circle::query()->find($request->circle_id);
        if (! $circle) {
            throw ValidationException::withMessages([
                'circle_id' => ['Requested Circle is missing or invalid.'],
            ]);
        }

        $updated = DB::transaction(function () use ($request, $admin, $circle): CircleJoinRequest {
            $locked = $this->lockOrFail($request->id);

            if (empty($locked->circle_id) || (string) $locked->circle_id !== (string) $circle->id) {
                throw ValidationException::withMessages([
                    'circle_id' => ['Requested Circle is missing or invalid.'],
                ]);
            }

            if (in_array((string) $locked->status, [CircleJoinRequest::STATUS_CIRCLE_MEMBER, CircleJoinRequest::STATUS_PAID], true)) {
                throw ValidationException::withMessages([
                    'status' => ['This request has already been approved.'],
                ]);
            }

            if (in_array((string) $locked->status, [
                CircleJoinRequest::STATUS_REJECTED_BY_CD,
                CircleJoinRequest::STATUS_REJECTED_BY_ID,
                CircleJoinRequest::STATUS_CANCELLED,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => ['This request has been rejected and cannot be approved.'],
                ]);
            }

            $approvableStatuses = [
                CircleJoinRequest::STATUS_PENDING_CD_APPROVAL,
                CircleJoinRequest::STATUS_PENDING_ID_APPROVAL,
                CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE,
            ];
            if (! in_array((string) $locked->status, $approvableStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => ["Invalid status transition from {$locked->status}."],
                ]);
            }

            $user = User::query()->find($locked->user_id);
            if (! $user) {
                throw ValidationException::withMessages([
                    'user_id' => ['Applicant user not found.'],
                ]);
            }

            $member = CircleMember::withTrashed()
                ->where('circle_id', $circle->id)
                ->where('user_id', $user->id)
                ->first();

            $joinedStatus = (string) config('circle.member_joined_status', 'approved');
            $now = now();

            $memberUpdates = [
                'status' => $joinedStatus,
                'role' => $member?->role ?: 'member',
                'joined_at' => $member?->joined_at ?: $now,
                'left_at' => null,
            ];

            if (Schema::hasColumn('circle_members', 'joined_via')) {
                $memberUpdates['joined_via'] = 'admin_approval';
            }
            if (Schema::hasColumn('circle_members', 'payment_status')) {
                $memberUpdates['payment_status'] = 'approved';
            }

            if ($member) {
                if ($member->trashed()) {
                    $member->restore();
                }
                $member->forceFill($memberUpdates)->save();
            } else {
                $member = CircleMember::query()->create(array_merge($memberUpdates, [
                    'circle_id' => $circle->id,
                    'user_id' => $user->id,
                ]));
            }

            $selection = $this->resolveCategorySelectionFromRequest($locked);
            $this->syncMemberCategorySelection($member, $locked, $selection);

            if (Schema::hasColumn('users', 'active_circle_id') && empty($user->active_circle_id)) {
                $user->forceFill(['active_circle_id' => $circle->id])->save();
            }

            $approverUserId = $this->resolveApproverUserId($admin);
            $oldStatus = (string) $locked->status;

            $requestUpdates = [
                'status' => CircleJoinRequest::STATUS_CIRCLE_MEMBER,
                'cd_approved_by' => $locked->cd_approved_by ?: $approverUserId,
                'cd_approved_at' => $locked->cd_approved_at ?: $now,
                'id_approved_by' => $locked->id_approved_by ?: $approverUserId,
                'id_approved_at' => $locked->id_approved_at ?: $now,
                'cd_rejected_by' => null,
                'cd_rejected_at' => null,
                'cd_rejection_reason' => null,
                'id_rejected_by' => null,
                'id_rejected_at' => null,
                'id_rejection_reason' => null,
            ];

            if (Schema::hasTable('circle_join_requests') && Schema::hasColumn('circle_join_requests', 'ded_approval_status')) {
                $dedAdminUserId = null;
                if ($approverUserId && Schema::hasTable('admin_users') && DB::table('admin_users')->where('id', $approverUserId)->exists()) {
                    $dedAdminUserId = $approverUserId;
                }
                $requestUpdates['ded_approval_status'] = 'approved';
                $requestUpdates['ded_approved_by'] = $dedAdminUserId;
                $requestUpdates['ded_approved_at'] = $now;
            }

            if (Schema::hasColumn('circle_join_requests', 'fee_marked_at') && ! $locked->fee_marked_at) {
                $requestUpdates['fee_marked_at'] = $now;
            }
            if (Schema::hasColumn('circle_join_requests', 'fee_paid_at') && ! $locked->fee_paid_at) {
                $requestUpdates['fee_paid_at'] = $now;
            }

            $locked->forceFill($requestUpdates)->save();

            $freshRecord = $locked->fresh(['user', 'circle']);

            Log::info('circle_join_request.approved_and_joined', [
                'request_id' => $freshRecord->id,
                'user_id' => $user->id,
                'circle_id' => $circle->id,
                'old_status' => $oldStatus,
                'new_status' => (string) $freshRecord->status,
                'approver_user_id' => $approverUserId,
            ]);

            return $freshRecord;
        });

        $this->safeSendTransitionNotifications(
            $updated,
            fn () => $this->circleJoinRequestNotificationService->sendCircleMemberConfirmedToUser($updated)
        );

        return $updated;
    }

    public function rejectGeneric(CircleJoinRequest $request, User|AdminUser $admin, string $reason): CircleJoinRequest
    {
        $updated = DB::transaction(function () use ($request, $admin, $reason): CircleJoinRequest {
            $locked = $this->lockOrFail($request->id);

            if (in_array((string) $locked->status, [CircleJoinRequest::STATUS_CIRCLE_MEMBER, CircleJoinRequest::STATUS_PAID], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Approved requests cannot be rejected.'],
                ]);
            }

            $approverUserId = $this->resolveApproverUserId($admin);
            $oldStatus = (string) $locked->status;
            $now = now();

            $notes = (array) $locked->notes;
            $notes['rejection_reason'] = $reason;

            $targetStatus = match ($oldStatus) {
                CircleJoinRequest::STATUS_PENDING_CD_APPROVAL => CircleJoinRequest::STATUS_REJECTED_BY_CD,
                CircleJoinRequest::STATUS_PENDING_ID_APPROVAL => CircleJoinRequest::STATUS_REJECTED_BY_ID,
                default => CircleJoinRequest::STATUS_CANCELLED,
            };

            $updates = [
                'status' => $targetStatus,
                'notes' => $notes,
            ];

            if ($targetStatus === CircleJoinRequest::STATUS_REJECTED_BY_CD) {
                $updates['cd_rejected_by'] = $approverUserId;
                $updates['cd_rejected_at'] = $now;
                $updates['cd_rejection_reason'] = $reason;
            } elseif ($targetStatus === CircleJoinRequest::STATUS_REJECTED_BY_ID) {
                $updates['id_rejected_by'] = $approverUserId;
                $updates['id_rejected_at'] = $now;
                $updates['id_rejection_reason'] = $reason;
            }

            if (Schema::hasTable('circle_join_requests') && Schema::hasColumn('circle_join_requests', 'ded_approval_status')) {
                $updates['ded_approval_status'] = 'rejected';
            }

            $locked->forceFill($updates)->save();

            Log::info('circle_join_request.rejected', [
                'request_id' => $locked->id,
                'old_status' => $oldStatus,
                'new_status' => (string) $locked->status,
                'rejected_by' => $approverUserId,
                'reason' => $reason,
            ]);

            return $locked->fresh(['user', 'circle']);
        });

        if ((string) $updated->status === CircleJoinRequest::STATUS_REJECTED_BY_CD) {
            $this->safeSendTransitionNotifications(
                $updated,
                fn () => $this->circleJoinRequestNotificationService->sendCdRejectedToUser($updated)
            );
        } elseif ((string) $updated->status === CircleJoinRequest::STATUS_REJECTED_BY_ID) {
            $this->safeSendTransitionNotifications(
                $updated,
                fn () => $this->circleJoinRequestNotificationService->sendIdRejectedToUser($updated)
            );
        }

        return $updated;
    }

    private function resolveCategorySelectionFromRequest(CircleJoinRequest $request): array
    {
        $notes = is_array($request->notes) ? $request->notes : [];
        $notesSelection = is_array($notes['category_selection'] ?? null) ? $notes['category_selection'] : [];

        $resolve = static function (string $key) use ($request, $notesSelection): ?int {
            $value = $request->getAttribute($key);
            if ($value !== null && $value !== '') {
                return (int) $value;
            }

            if (array_key_exists($key, $notesSelection) && $notesSelection[$key] !== null && $notesSelection[$key] !== '') {
                return (int) $notesSelection[$key];
            }

            return null;
        };

        return [
            'level1_category_id' => $resolve('level1_category_id'),
            'level2_category_id' => $resolve('level2_category_id'),
            'level3_category_id' => $resolve('level3_category_id'),
            'level4_category_id' => $resolve('level4_category_id'),
        ];
    }

    private function syncMemberCategorySelection(CircleMember $member, CircleJoinRequest $request, array $selection): void
    {
        try {
            $level4Id = $selection['level4_category_id'] ?? null;
            if ($level4Id) {
                $level4Table = Schema::hasTable('circle_category_level4')
                    ? 'circle_category_level4'
                    : (Schema::hasTable('level4_categories') ? 'level4_categories' : null);

                if ($level4Table && ! DB::table($level4Table)->where('id', $level4Id)->exists()) {
                    $level4Id = null;
                }
            }

            $level1Id = $selection['level1_category_id'] ?? null;
            if ($level1Id && Schema::hasTable('circle_categories')) {
                if (! DB::table('circle_categories')->where('id', $level1Id)->exists()) {
                    $level1Id = null;
                }
            }

            if (Schema::hasTable('circle_member_category_selections')) {
                CircleMemberCategorySelection::query()->updateOrCreate(
                    [
                        'circle_member_id' => $member->id,
                    ],
                    [
                        'user_id' => $request->user_id,
                        'circle_id' => $request->circle_id,
                        'level1_category_id' => $level1Id,
                        'level2_category_id' => $selection['level2_category_id'] ?? null,
                        'level3_category_id' => $selection['level3_category_id'] ?? null,
                        'level4_category_id' => $level4Id,
                    ]
                );
            }

            if (Schema::hasTable('joined_circle_categories')) {
                JoinedCircleCategory::query()->updateOrCreate(
                    [
                        'circle_member_id' => $member->id,
                    ],
                    [
                        'user_id' => $request->user_id,
                        'circle_id' => $request->circle_id,
                        'level1_category_id' => $level1Id,
                        'level2_category_id' => $selection['level2_category_id'] ?? null,
                        'level3_category_id' => $selection['level3_category_id'] ?? null,
                        'level4_category_id' => $level4Id,
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('syncMemberCategorySelection failed in CircleJoinRequestService', [
                'error' => $e->getMessage(),
                'circle_member_id' => $member->id,
                'request_id' => $request->id,
            ]);
        }
    }

    private function lockOrFail(string $id): CircleJoinRequest
    {
        return CircleJoinRequest::query()
            ->where('id', $id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureStatus(CircleJoinRequest $request, string $expected): void
    {
        if ($request->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => ["Invalid status transition from {$request->status}."],
            ]);
        }
    }

    private function safeSendTransitionNotifications(CircleJoinRequest $request, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            Log::warning('Circle join request transition notification failed', [
                'circle_join_request_id' => (string) $request->id,
                'status' => (string) $request->status,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function notifyStakeholders(CircleJoinRequest $request, User $actor): void
    {
        try {
            $circle = $request->circle()->first();

            if (! $circle) {
                return;
            }

            $targets = User::query()
                ->whereIn('id', array_filter([
                    $circle->director_user_id,
                    $circle->industry_director_user_id,
                    $circle->ded_user_id,
                ]))
                ->get();

            $globalAdminRoleId = Role::query()
                ->where('key', 'global_admin')
                ->value('id');

            if ($globalAdminRoleId) {
                $globalAdmins = User::query()
                    ->whereHas('roles', fn ($q) => $q->where('roles.id', $globalAdminRoleId))
                    ->get();

                $targets = $targets->merge($globalAdmins);
            }

            $targets->unique('id')->each(function (User $recipient) use ($actor, $request): void {
                try {
                    $this->notifyUser(
                        $recipient,
                        $actor,
                        'circle_join_request_submitted',
                        'A new circle join request was submitted.',
                        ['circle_join_request_id' => $request->id]
                    );
                } catch (\Throwable $e) {
                    Log::warning('Failed to notify stakeholder for circle join request', [
                        'recipient_id' => (string) $recipient->id,
                        'request_id' => (string) $request->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            Log::warning('notifyStakeholders failed for circle join request', [
                'request_id' => (string) $request->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function notifyUser(User $to, User $from, string $type, string $body, array $data = []): void
    {
        $this->notifyUserService->notifyUser(
            $to,
            $from,
            $type,
            array_merge($data, [
                'title' => 'Circle Joining Request',
                'body' => $body,
            ])
        );
    }
}
