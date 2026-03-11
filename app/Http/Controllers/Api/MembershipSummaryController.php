<?php

namespace App\Http\Controllers\Api;

use App\Services\MembershipSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipSummaryController extends BaseApiController
{
    public function __construct(private readonly MembershipSummaryService $membershipSummaryService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $data = $this->membershipSummaryService->getSummary($request->user());

        return $this->success($data, 'Membership summary fetched successfully.');
    }
}
