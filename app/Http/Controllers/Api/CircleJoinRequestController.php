<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\CircleJoinRequests\ListMyCircleJoinRequests;
use App\Http\Requests\Api\CircleJoinRequests\StoreCircleJoinRequest;
use App\Models\Circle;
use App\Models\CircleCategory;
use App\Models\CircleJoinRequest;
use App\Models\CustomCategoryRequest;
use App\Models\User;
use App\Services\Circles\CircleJoinRequestNotificationService;
use App\Services\Circles\CircleJoinRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CircleJoinRequestController extends BaseApiController
{
    public function __construct(private readonly CircleJoinRequestService $service) {}

    public function store(StoreCircleJoinRequest $request): JsonResponse
    {
        $categoryId = $request->validated('category_id') ?? $request->validated('level1_category_id');

        if (! $categoryId && $request->validated('level4_category_id')) {
            $level4Id = $request->validated('level4_category_id');
            $level4Table = Schema::hasTable('level4_categories') ? 'level4_categories' : 'circle_category_level4';
            if (Schema::hasTable($level4Table)) {
                $categoryId = DB::table($level4Table)
                    ->where('id', $level4Id)
                    ->value('circle_category_id');
            }
        }

        $circleId = null;
        if ($categoryId && Schema::hasTable('circle_category_mappings')) {
            $circleId = DB::table('circle_category_mappings')
                ->where('category_id', $categoryId)
                ->value('circle_id');
        }

        if (! $circleId && $categoryId && Schema::hasTable('circle_categories')) {
            $category = DB::table('circle_categories')->where('id', $categoryId)->first();
            if ($category) {
                $cleanName = trim(preg_replace('/\s+Circles?\b/i', '', (string) $category->name));
                $matchedCircle = Circle::query()
                    ->where(function ($q) use ($category, $cleanName) {
                        $q->where('slug', $category->slug)
                            ->orWhere('name', $category->name)
                            ->orWhere('name', 'like', '%'.$cleanName.'%');
                    })
                    ->first();

                if (! $matchedCircle) {
                    $matchedCircle = new Circle;
                    $matchedCircle->id = (string) Str::uuid();
                    $matchedCircle->name = (string) $category->name;
                    $matchedCircle->slug = (string) ($category->slug ?: Str::slug($category->name));
                    $matchedCircle->status = 'active';
                    $matchedCircle->type = 'public';
                    $matchedCircle->save();
                }

                $circleId = $matchedCircle->id;
                if (Schema::hasTable('circle_category_mappings')) {
                    DB::table('circle_category_mappings')->insertOrIgnore([
                        'category_id' => $categoryId,
                        'circle_id' => $circleId,
                    ]);
                }
            }
        }

        if (! $circleId) {
            return $this->error('Could not find or create Circle for the given category.', 422);
        }

        $circle = Circle::query()->where('id', $circleId)->firstOrFail();

        try {
            $reason = $request->validated('reason') ?? $request->validated('reason_for_joining');
            $categoryId = $request->validated('category_id');
            if (! $categoryId) {
                $categoryId = DB::table('circle_category_mappings')
                    ->where('circle_id', $circle->id)
                    ->value('category_id');
            }

            $otherCategoryName = trim((string) ($request->validated('other_category_name') ?? $request->validated('custom_category_name') ?? ''));

            if ($otherCategoryName !== '' && $categoryId && Schema::hasTable('custom_category_requests')) {
                CustomCategoryRequest::query()->create([
                    'user_id' => (string) $request->user()->id,
                    'level1_category_id' => (int) $categoryId,
                    'category_name' => $otherCategoryName,
                    'status' => 'pending',
                ]);
            }

            $record = $this->service->submitRequest(
                $request->user(),
                $circle,
                $reason,
                [
                    'level1_category_id' => $categoryId,
                    'level4_category_id' => $request->validated('level4_category_id'),
                ]
            );

            $record->load([
                'circle.categories',
                'user:id,display_name,email,phone,company_name,city',
                'level1Category:id,name',
                'level2Category:id,name',
                'level3Category:id,name',
                'level4Category:id,name',
            ]);

            $transformed = $this->transformJoinRequest($record);
            $transformed['user_id'] = $record->user_id;
            $transformed['category_id'] = $record->level1_category_id;
            $transformed['status'] = $record->status;

            return response()->json([
                'success' => true,
                'status' => true,
                'message' => 'Circle join request submitted successfully.',
                'data' => $transformed,
                'meta' => null,
            ], 201);
        } catch (ValidationException $exception) {
            return $this->error('Validation failed.', 422, $exception->errors());
        }
    }

    public function myRequests(ListMyCircleJoinRequests $request): JsonResponse
    {
        $status = $request->validated('status');

        $items = CircleJoinRequest::query()
            ->where('user_id', $request->user()->id)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with([
                'circle',
                'user',
                'cdApprovedBy',
                'idApprovedBy',
                'dedApprovedBy',
                'cdRejectedBy',
                'idRejectedBy',
            ])
            ->latest('created_at')
            ->paginate(20);

        return $this->success([
            'items' => collect($items->items())->map(fn (CircleJoinRequest $joinRequest) => $this->transformJoinRequest($joinRequest))->values(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $record = CircleJoinRequest::query()->with([
            'circle',
            'user',
            'cdApprovedBy',
            'cdRejectedBy',
            'idApprovedBy',
            'idRejectedBy',
            'dedApprovedBy',
        ])->findOrFail($id);

        if ((string) $record->user_id !== (string) $request->user()->id) {
            return $this->error('Forbidden.', 403);
        }

        return $this->success($this->transformJoinRequest($record));
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $record = CircleJoinRequest::query()->findOrFail($id);

        try {
            $updated = $this->service->cancelByUser($record, $request->user());

            return $this->success($this->transformJoinRequest($updated), 'Circle join request cancelled successfully.');
        } catch (ValidationException $exception) {
            return $this->error('Validation failed.', 422, $exception->errors());
        }
    }

    private function transformJoinRequest(CircleJoinRequest $request): array
    {
        $status = (string) $request->status;
        $isPaid = in_array($status, [CircleJoinRequest::STATUS_PAID, CircleJoinRequest::STATUS_CIRCLE_MEMBER], true) || $request->fee_paid_at !== null;

        $level1Id = $this->resolveCategoryIdFromJoinRequest($request, 'level1_category_id');
        $level4Id = $this->resolveCategoryIdFromJoinRequest($request, 'level4_category_id');

        $userId = $request->user_id;
        $otherCategoryReq = null;

        if (blank($level4Id) && $userId && $level1Id && Schema::hasTable('custom_category_requests')) {
            $otherCategoryReq = CustomCategoryRequest::query()
                ->where('user_id', (string) $userId)
                ->where('level1_category_id', (int) $level1Id)
                ->latest()
                ->first();
        }

        $otherName = $otherCategoryReq?->category_name ?? null;

        // Resolve Level 1 Category
        $level1Category = null;
        if ($level1Id) {
            $cat = CircleCategory::find($level1Id);
            if ($cat) {
                $level1Category = [
                    'id' => (int) $cat->id,
                    'name' => (string) $cat->name,
                    'slug' => $cat->slug,
                    'circle_key' => $cat->circle_key,
                ];
            }
        }

        // Resolve Level 4 Category / Specialization
        $level4Category = null;
        if ($otherName !== null && $otherName !== '') {
            $level4Category = [
                'id' => 'other',
                'name' => $otherName,
                'is_other' => true,
            ];
        } elseif ($level4Id) {
            $l4Table = Schema::hasTable('level4_categories') ? 'level4_categories' : 'circle_category_level4';
            if (Schema::hasTable($l4Table)) {
                $l4 = DB::table($l4Table)->where('id', $level4Id)->first();
                if ($l4) {
                    $level4Category = [
                        'id' => (int) $l4->id,
                        'name' => (string) $l4->name,
                    ];
                }
            }
        }

        $formatApprover = function ($user): ?array {
            if (! $user) {
                return null;
            }

            return [
                'id' => (string) $user->id,
                'name' => (string) ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: 'Admin'),
            ];
        };

        // Payment Info
        $paymentStatus = $isPaid ? 'paid' : 'unpaid';
        $paymentUrl = null;
        $canPay = false;

        if ($status === CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE) {
            $paymentUrl = app(CircleJoinRequestNotificationService::class)->resolvePaymentUrl($request);
            $canPay = $paymentUrl !== null;
        }

        $paidAt = null;
        if ($isPaid) {
            $paidAtTimestamp = $request->fee_paid_at ?: $request->fee_marked_at ?: $request->updated_at;
            $paidAt = $paidAtTimestamp ? $paidAtTimestamp->toIso8601String() : null;
        }

        // User & Pro status
        $user = $request->user;
        if (! $user && $request->user_id) {
            $user = User::query()->find($request->user_id);
        }

        $isPro = false;
        if ($user) {
            if ($user->getAttribute('is_pro') !== null) {
                $isPro = (bool) $user->getAttribute('is_pro');
            } elseif (isset($user->is_verified) && $user->is_verified !== null && (bool) $user->is_verified) {
                $isPro = true;
            } elseif (method_exists($user, 'isPaidMember')) {
                $isPro = (bool) $user->isPaidMember();
            } else {
                $membership = strtolower(trim((string) ($user->membership_status ?? $user->effective_membership_status ?? '')));
                $isPro = $membership !== '' && ! in_array($membership, ['free_peer', 'free_trial_peer', 'visitor', 'suspended', 'free peer', 'free'], true);
            }
        }

        return [
            'id' => (string) $request->id,
            'user_id' => (string) $request->user_id,
            'is_pro' => $isPro,
            'circle_id' => (string) $request->circle_id,
            'circle' => $request->circle ? [
                'id' => (string) $request->circle->id,
                'name' => (string) $request->circle->name,
                'slug' => (string) $request->circle->slug,
                'categories' => $level1Category ? [$level1Category] : [],
            ] : null,
            'circle_categories' => $level1Category ? [$level1Category] : [],
            'circle_category_id' => $level1Category ? $level1Category['id'] : null,
            'circle_category_name' => $level1Category ? $level1Category['name'] : null,
            'category_id' => $level1Category ? $level1Category['id'] : null,
            'category_name' => $level1Category ? $level1Category['name'] : null,
            'status' => $status,
            'status_label' => $isPaid ? 'Paid' : $this->statusLabel($status),
            'display_status' => $isPaid ? 'Paid' : $this->statusLabel($status),
            'payment_status' => $paymentStatus,
            'payment_url' => $paymentUrl,
            'can_pay' => $canPay,
            'payment' => [
                'required' => true,
                'status' => $paymentStatus,
                'amount' => (int) ($request->circle?->circle_price_amount ?: 5000),
                'currency' => $request->circle?->circle_price_currency ?: 'INR',
                'payment_url' => $paymentUrl,
                'button_label' => 'Pay Now',
                'paid_at' => $paidAt,
            ],
            'reason' => (string) ($request->reason_for_joining ?? ''),
            'reason_for_joining' => (string) ($request->reason_for_joining ?? ''),
            'level1_category' => $level1Category,
            'level4_category' => $level4Category,
            'is_other_category' => $otherName !== null && $otherName !== '',
            'other_category_name' => $otherName,
            'cd_approved_by' => $formatApprover($request->cdApprovedBy),
            'cd_approved_at' => $request->cd_approved_at ? $request->cd_approved_at->toIso8601String() : null,
            'cd_rejected_by' => $formatApprover($request->cdRejectedBy),
            'cd_rejected_at' => $request->cd_rejected_at ? $request->cd_rejected_at->toIso8601String() : null,
            'cd_rejection_reason' => $request->cd_rejection_reason,
            'id_approved_by' => $formatApprover($request->idApprovedBy),
            'id_approved_at' => $request->id_approved_at ? $request->id_approved_at->toIso8601String() : null,
            'id_rejected_by' => $formatApprover($request->idRejectedBy),
            'id_rejected_at' => $request->id_rejected_at ? $request->id_rejected_at->toIso8601String() : null,
            'id_rejection_reason' => $request->id_rejection_reason,
            'ded_approved_by' => $formatApprover($request->dedApprovedBy),
            'ded_approved_at' => $request->ded_approved_at ? $request->ded_approved_at->toIso8601String() : null,
            'ded_approval_status' => $request->ded_approval_status,
            'ded_approval_remarks' => $request->ded_approval_remarks,
            'fee_marked_at' => $request->fee_marked_at ? $request->fee_marked_at->toIso8601String() : null,
            'fee_paid_at' => $request->fee_paid_at ? $request->fee_paid_at->toIso8601String() : null,
            'requested_at' => $request->requested_at ? $request->requested_at->toIso8601String() : null,
            'created_at' => $request->created_at ? $request->created_at->toIso8601String() : null,
            'updated_at' => $request->updated_at ? $request->updated_at->toIso8601String() : null,
        ];
    }

    private function resolveCategoryIdFromJoinRequest(CircleJoinRequest $request, string $key): ?int
    {
        $value = $request->getAttribute($key);
        if ($value !== null && is_numeric($value)) {
            return (int) $value;
        }

        $notes = $request->notes;
        $notesSelection = is_array($notes) ? ($notes['category_selection'] ?? null) : null;

        if (! is_array($notesSelection) || ! array_key_exists($key, $notesSelection) || $notesSelection[$key] === null) {
            return null;
        }

        return is_numeric($notesSelection[$key]) ? (int) $notesSelection[$key] : null;
    }

    public function status(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->id;

        $record = CircleJoinRequest::query()
            ->with([
                'user',
                'cdApprovedBy',
                'idApprovedBy',
                'cdRejectedBy',
                'idRejectedBy',
                'dedApprovedBy',
            ])
            ->where('id', $id)
            ->first();

        if (! $record || (string) $record->user_id !== (string) $userId) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Circle joining request not found.',
                'data' => null,
                'meta' => null,
            ], 404);
        }

        $level1Id = $this->resolveCategoryIdFromJoinRequest($record, 'level1_category_id');
        $level4Id = $this->resolveCategoryIdFromJoinRequest($record, 'level4_category_id');

        $level1Category = null;
        if ($level1Id) {
            $cat = CircleCategory::find($level1Id);
            if ($cat) {
                $level1Category = [
                    'id' => (int) $cat->id,
                    'name' => (string) $cat->name,
                    'slug' => $cat->slug,
                    'circle_key' => $cat->circle_key,
                ];
            }
        }

        $otherCategoryReq = null;
        if (blank($level4Id) && $level1Id && Schema::hasTable('custom_category_requests')) {
            $otherCategoryReq = CustomCategoryRequest::query()
                ->where('user_id', (string) $userId)
                ->where('level1_category_id', (int) $level1Id)
                ->latest()
                ->first();
        }

        $otherName = $otherCategoryReq?->category_name ?? null;

        $level4Category = null;
        if ($otherName !== null && $otherName !== '') {
            $level4Category = [
                'id' => 'other',
                'name' => $otherName,
                'is_other' => true,
            ];
        } elseif ($level4Id) {
            $l4Table = Schema::hasTable('level4_categories') ? 'level4_categories' : 'circle_category_level4';
            if (Schema::hasTable($l4Table)) {
                $l4 = DB::table($l4Table)->where('id', $level4Id)->first();
                if ($l4) {
                    $level4Category = [
                        'id' => (int) $l4->id,
                        'name' => (string) $l4->name,
                    ];
                }
            }
        }

        $isPassed = in_array((string) $record->status, [
            CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE,
            CircleJoinRequest::STATUS_PAID,
            CircleJoinRequest::STATUS_CIRCLE_MEMBER,
        ], true) || (string) ($record->ded_approval_status ?? '') === 'approved';

        // CD Approval
        $cdStatus = 'pending';
        if ($record->cd_approved_at !== null || $isPassed) {
            $cdStatus = 'approved';
        } elseif ($record->cd_rejected_at !== null) {
            $cdStatus = 'rejected';
        }

        $cdApprovedBy = null;
        if ($record->cdApprovedBy) {
            $cdApprovedBy = [
                'id' => (string) $record->cdApprovedBy->id,
                'name' => (string) ($record->cdApprovedBy->display_name ?: trim(($record->cdApprovedBy->first_name ?? '').' '.($record->cdApprovedBy->last_name ?? '')) ?: 'Admin'),
            ];
        } elseif ($isPassed && $record->dedApprovedBy) {
            $cdApprovedBy = [
                'id' => (string) $record->dedApprovedBy->id,
                'name' => (string) ($record->dedApprovedBy->display_name ?: trim(($record->dedApprovedBy->first_name ?? '').' '.($record->dedApprovedBy->last_name ?? '')) ?: 'DED'),
            ];
        }

        // ID Approval
        $idStatus = 'pending';
        if ($record->id_approved_at !== null || $isPassed) {
            $idStatus = 'approved';
        } elseif ($record->id_rejected_at !== null) {
            $idStatus = 'rejected';
        }

        $idApprovedBy = null;
        if ($record->idApprovedBy) {
            $idApprovedBy = [
                'id' => (string) $record->idApprovedBy->id,
                'name' => (string) ($record->idApprovedBy->display_name ?: trim(($record->idApprovedBy->first_name ?? '').' '.($record->idApprovedBy->last_name ?? '')) ?: 'Admin'),
            ];
        } elseif ($isPassed && $record->dedApprovedBy) {
            $idApprovedBy = [
                'id' => (string) $record->dedApprovedBy->id,
                'name' => (string) ($record->dedApprovedBy->display_name ?: trim(($record->dedApprovedBy->first_name ?? '').' '.($record->dedApprovedBy->last_name ?? '')) ?: 'DED'),
            ];
        }

        // Rejection Info
        $isRejected = in_array((string) $record->status, [CircleJoinRequest::STATUS_REJECTED_BY_CD, CircleJoinRequest::STATUS_REJECTED_BY_ID], true);
        $rejectedBy = null;
        $rejectionReason = null;
        $rejectedAt = null;

        if ((string) $record->status === CircleJoinRequest::STATUS_REJECTED_BY_CD) {
            $rejectedBy = 'cd';
            $rejectionReason = $record->cd_rejection_reason;
            $rejectedAt = $record->cd_rejected_at ? $record->cd_rejected_at->toIso8601String() : null;
        } elseif ((string) $record->status === CircleJoinRequest::STATUS_REJECTED_BY_ID) {
            $rejectedBy = 'id';
            $rejectionReason = $record->id_rejection_reason;
            $rejectedAt = $record->id_rejected_at ? $record->id_rejected_at->toIso8601String() : null;
        }

        // Payment Info
        $paymentStatus = 'unpaid';
        $isPaid = in_array((string) $record->status, [CircleJoinRequest::STATUS_PAID, CircleJoinRequest::STATUS_CIRCLE_MEMBER], true) || $record->fee_paid_at !== null;
        if ($isPaid) {
            $paymentStatus = 'paid';
        }

        $paymentUrl = null;
        $canPay = false;

        if ((string) $record->status === CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE) {
            $paymentUrl = app(CircleJoinRequestNotificationService::class)->resolvePaymentUrl($record);
            $canPay = $paymentUrl !== null;
        }

        $paidAt = null;
        if ($isPaid) {
            $paidAtTimestamp = $record->fee_paid_at ?: $record->fee_marked_at ?: $record->updated_at;
            $paidAt = $paidAtTimestamp ? $paidAtTimestamp->toIso8601String() : null;
        }

        $user = $record->user;
        if (! $user && $record->user_id) {
            $user = User::query()->find($record->user_id);
        }

        $isPro = false;
        if ($user) {
            if ($user->getAttribute('is_pro') !== null) {
                $isPro = (bool) $user->getAttribute('is_pro');
            } elseif (isset($user->is_verified) && $user->is_verified !== null && (bool) $user->is_verified) {
                $isPro = true;
            } elseif (method_exists($user, 'isPaidMember')) {
                $isPro = (bool) $user->isPaidMember();
            } else {
                $membership = strtolower(trim((string) ($user->membership_status ?? $user->effective_membership_status ?? '')));
                $isPro = $membership !== '' && ! in_array($membership, ['free_peer', 'free_trial_peer', 'visitor', 'suspended', 'free peer', 'free'], true);
            }
        }

        $data = [
            'id' => (string) $record->id,
            'user_id' => (string) $record->user_id,
            'is_pro' => $isPro,
            'circle_id' => (string) $record->circle_id,
            'status' => (string) $record->status,
            'status_label' => $this->statusLabel($record->status),
            'display_status' => $this->statusLabel($record->status),
            'reason' => (string) ($record->reason_for_joining ?? ''),
            'level1_category' => $level1Category,
            'level4_category' => $level4Category,
            'is_other_category' => $otherName !== null && $otherName !== '',
            'other_category_name' => $otherName,
            'cd_approval' => [
                'status' => $cdStatus,
                'approved_at' => ($record->cd_approved_at ?: ($isPassed ? ($record->ded_approved_at ?: $record->updated_at) : null)) ? ($record->cd_approved_at ?: ($isPassed ? ($record->ded_approved_at ?: $record->updated_at) : null))->toIso8601String() : null,
                'approved_by' => $cdApprovedBy,
            ],
            'id_approval' => [
                'status' => $idStatus,
                'approved_at' => ($record->id_approved_at ?: ($isPassed ? ($record->ded_approved_at ?: $record->updated_at) : null)) ? ($record->id_approved_at ?: ($isPassed ? ($record->ded_approved_at ?: $record->updated_at) : null))->toIso8601String() : null,
                'approved_by' => $idApprovedBy,
            ],
            'rejection' => [
                'is_rejected' => $isRejected,
                'rejected_by' => $rejectedBy,
                'reason' => $rejectionReason,
                'rejected_at' => $rejectedAt,
            ],
            'payment' => [
                'required' => true,
                'status' => $paymentStatus,
                'amount' => (int) ($record->circle?->circle_price_amount ?: 5000),
                'currency' => $record->circle?->circle_price_currency ?: 'INR',
                'payment_url' => $paymentUrl,
                'button_label' => 'Pay Now',
                'paid_at' => $paidAt,
            ],
            'can_pay' => $canPay,
            'created_at' => $record->created_at ? $record->created_at->toIso8601String() : null,
            'updated_at' => $record->updated_at ? $record->updated_at->toIso8601String() : null,
        ];

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Circle joining request status fetched successfully.',
            'data' => $data,
            'meta' => null,
        ], 200);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            CircleJoinRequest::STATUS_PENDING_CD_APPROVAL => 'Pending for CD Approval',
            CircleJoinRequest::STATUS_PENDING_ID_APPROVAL => 'Pending for ID Approval',
            CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE => 'Pending for Circle Fee',
            CircleJoinRequest::STATUS_CIRCLE_MEMBER => 'Circle Member',
            CircleJoinRequest::STATUS_PAID => 'Paid',
            CircleJoinRequest::STATUS_REJECTED_BY_CD => 'Rejected by CD',
            CircleJoinRequest::STATUS_REJECTED_BY_ID => 'Rejected by ID',
            CircleJoinRequest::STATUS_CANCELLED => 'Cancelled',
            default => $status,
        };
    }
}
