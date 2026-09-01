<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\GetPeerAnniversariesRequest;
use App\Services\Peers\PeerCelebrationService;
use Illuminate\Http\JsonResponse;

class PeerAnniversaryController extends BaseApiController
{
    public function __construct(private readonly PeerCelebrationService $celebrationService) {}

    public function index(GetPeerAnniversariesRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $page = (int) $request->input('page', 1);

        $result = $this->celebrationService->getAnniversaries($filters, $page);

        return $this->success($result, 'Peer anniversaries fetched successfully.');
    }
}
