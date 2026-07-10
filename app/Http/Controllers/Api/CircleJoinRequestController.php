<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\CircleJoinRequests\ListMyCircleJoinRequests;
use App\Http\Requests\Api\CircleJoinRequests\StoreCircleJoinRequest;
use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Models\User;
use App\Services\Circles\CircleJoinRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CircleJoinRequestController extends BaseApiController
{
    public function __construct(private readonly CircleJoinRequestService $service) {}

    public function store(StoreCircleJoinRequest $request): JsonResponse
    {
        $circle = Circle::query()->where('id', $request->validated('circle_id'))->firstOrFail();

        if ($circle->status !== 'active') {
            return $this->error('Circle is not active.', 422);
        }

        try {
            $record = $this->service->submitRequest(
                $request->user(),
                $circle,
                $request->validated('reason_for_joining'),
                [
                    'level1_category_id' => $request->validated('category_id'),
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
                'circle.categories',
                'level1Category:id,name',
                'level2Category:id,name',
                'level3Category:id,name',
                'level4Category:id,name',
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
            'circle.categories',
            'user',
            'cdApprovedBy',
            'cdRejectedBy',
            'idApprovedBy',
            'idRejectedBy',
            'level1Category:id,name',
            'level2Category:id,name',
            'level3Category:id,name',
            'level4Category:id,name',
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

        $payload = array_merge($request->toArray(), [
            'level1_category_id' => $this->resolveCategoryIdFromJoinRequest($request, 'level1_category_id'),
            'level2_category_id' => $this->resolveCategoryIdFromJoinRequest($request, 'level2_category_id'),
            'level3_category_id' => $this->resolveCategoryIdFromJoinRequest($request, 'level3_category_id'),
            'level4_category_id' => $this->resolveCategoryIdFromJoinRequest($request, 'level4_category_id'),
            'status_label' => $isPaid ? 'Paid' : $this->statusLabel($status),
            'payment_status' => $isPaid ? 'paid' : 'unpaid',
            'display_status' => $isPaid ? 'Paid' : $this->statusLabel($status),
        ]);

        $circleCategory = $request->circleCategory;
        $level1Id = $circleCategory?->id;
        $payload['circle_category_id'] = $level1Id;
        $payload['category_id'] = $level1Id;
        $payload['circle_category_name'] = $circleCategory?->name;
        $payload['category_name'] = $circleCategory?->name;

        if ($circleCategory) {
            $payload['level1_category'] = ['id' => $circleCategory->id, 'name' => $circleCategory->name];
        }

        if ($request->relationLoaded('level2Category') && $request->level2Category) {
            $payload['level2_category'] = ['id' => $request->level2Category->id, 'name' => $request->level2Category->name];
        }

        if ($request->relationLoaded('level3Category') && $request->level3Category) {
            $payload['level3_category'] = ['id' => $request->level3Category->id, 'name' => $request->level3Category->name];
        }

        if ($request->relationLoaded('level4Category') && $request->level4Category) {
            $payload['level4_category'] = ['id' => $request->level4Category->id, 'name' => $request->level4Category->name];
        }

        if ($request->relationLoaded('circle') && $request->circle) {
            $circleArray = [
                'id' => $request->circle->id,
                'name' => $request->circle->name,
            ];
            if ($request->circle->relationLoaded('categories')) {
                $circleArray['categories'] = $request->circle->categories->map(fn ($cat) => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                ])->toArray();

                $payload['circle_categories'] = $circleArray['categories'];
            }
            $payload['circle'] = $circleArray;
        }

        return $payload;
    }

    private function resolveCategoryIdFromJoinRequest(CircleJoinRequest $request, string $key): ?int
    {
        $value = $request->getAttribute($key);
        if ($value !== null) {
            return (int) $value;
        }

        $notes = $request->notes;
        $notesSelection = is_array($notes) ? ($notes['category_selection'] ?? null) : null;

        if (! is_array($notesSelection) || ! array_key_exists($key, $notesSelection) || $notesSelection[$key] === null) {
            return null;
        }

        return (int) $notesSelection[$key];
    }

    public function status(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->id;

        $record = CircleJoinRequest::query()
            ->with([
                'circle',
                'user',
                'cdApprovedBy',
                'idApprovedBy',
                'cdRejectedBy',
                'idRejectedBy',
                'circleCategory',
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

        $circleName = $record->circle?->name ?? 'N/A';
        $categoryId = $record->level1_category_id ?? ($record->circleCategory?->id ? (int) $record->circleCategory->id : null);
        $categoryName = $record->circleCategory?->name ?? 'N/A';

        // CD Approval
        $cdStatus = 'pending';
        if ($record->cd_approved_at !== null) {
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
        }

        // ID Approval
        $idStatus = 'pending';
        if ($record->id_approved_at !== null) {
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
            $paymentUrl = app(\App\Services\Circles\CircleJoinRequestNotificationService::class)->resolvePaymentUrl($record);
            $canPay = $paymentUrl !== null;
        }

        $paidAt = null;
        if ($isPaid) {
            $paidAtTimestamp = $record->fee_paid_at ?: $record->fee_marked_at ?: $record->updated_at;
            $paidAt = $paidAtTimestamp ? $paidAtTimestamp->toIso8601String() : null;
        }

        $data = [
            'id' => (string) $record->id,
            'user_id' => (string) $record->user_id,
            'circle_id' => (string) $record->circle_id,
            'circle_name' => $circleName,
            'circle_category_id' => $categoryId,
            'category_name' => $categoryName,
            'status' => (string) $record->status,
            'status_label' => $this->statusLabel($record->status),
            'cd_approval' => [
                'status' => $cdStatus,
                'approved_at' => $record->cd_approved_at ? $record->cd_approved_at->toIso8601String() : null,
                'approved_by' => $cdApprovedBy,
            ],
            'id_approval' => [
                'status' => $idStatus,
                'approved_at' => $record->id_approved_at ? $record->id_approved_at->toIso8601String() : null,
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
