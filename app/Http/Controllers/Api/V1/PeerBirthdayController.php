<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\GetPeerBirthdaysRequest;
use App\Services\Peers\PeerCelebrationService;
use Illuminate\Http\JsonResponse;

class PeerBirthdayController extends BaseApiController
{
    public function __construct(private readonly PeerCelebrationService $celebrationService) {}

    public function index(GetPeerBirthdaysRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $page = (int) $request->input('page', 1);

        $result = $this->celebrationService->getBirthdays($filters, $page);

        return $this->success($result, 'Peer birthdays fetched successfully.');
    }
}
