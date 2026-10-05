<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Store\StoreReturnRequest;
use App\Models\Store\Refund;
use App\Services\Store\StoreReturnService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReturnController extends BaseApiController
{
    protected StoreReturnService $returnService;

    public function __construct(StoreReturnService $returnService)
    {
        $this->returnService = $returnService;
    }

    public function requestReturn(StoreReturnRequest $request, string $orderId): JsonResponse
    {
        try {
            $user = $request->user();
            $return = $this->returnService->createReturnRequest($user, $orderId, $request->validated());

            return $this->success($return, 'Return request submitted successfully', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->input('per_page', 20);

        $returns = $this->returnService->getUserReturns($user, $perPage);

        return $this->success($returns, 'Return requests retrieved');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $return = $this->returnService->getReturnDetails($user, $id);

            return $this->success($return, 'Return request details retrieved');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        }
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $return = $this->returnService->getReturnDetails($user, $id);

            if ($return->status !== 'REQUESTED') {
                return $this->error('Only pending return requests can be cancelled', 422);
            }

            $return->update(['status' => 'CANCELLED']);

            return $this->success($return, 'Return request cancelled');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        }
    }

    public function showRefund(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $refund = Refund::with(['order', 'returnModel'])
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $refund) {
            return $this->error('Refund record not found', 404);
        }

        return $this->success($refund, 'Refund details retrieved');
    }
}
