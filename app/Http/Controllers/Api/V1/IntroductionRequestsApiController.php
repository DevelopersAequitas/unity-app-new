<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\StoreIntroductionRequest;
use App\Models\IntroductionRequest;
use Illuminate\Http\JsonResponse;

class IntroductionRequestsApiController extends BaseApiController
{
    public function store(StoreIntroductionRequest $request): JsonResponse
    {
        $requester = $request->user();
        $introducerId = (string) $request->validated('introducer_id');

        $introductionRequest = IntroductionRequest::create([
            'requester_id' => $requester->id,
            'introducer_id' => $introducerId,
            'status' => 'pending',
        ]);

        return $this->success([
            'id' => $introductionRequest->id,
            'requester_id' => $introductionRequest->requester_id,
            'introducer_id' => $introductionRequest->introducer_id,
            'status' => $introductionRequest->status,
            'requested_at' => $introductionRequest->requested_at?->toIso8601String(),
        ], 'Introduction request submitted successfully.', 201);
    }
}
