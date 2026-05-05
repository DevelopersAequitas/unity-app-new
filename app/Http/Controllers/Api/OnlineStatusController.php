<?php

namespace App\Http\Controllers\Api;

use App\Services\OnlineStatusService;
use Illuminate\Http\Request;

class OnlineStatusController extends BaseApiController
{
    public function heartbeat(Request $request, OnlineStatusService $onlineStatusService)
    {
        $payload = $onlineStatusService->markOnline($request->user());

        return $this->success($payload, 'Online status updated');
    }

    public function show(string $id, OnlineStatusService $onlineStatusService)
    {
        return $this->success($onlineStatusService->getStatus($id));
    }

    public function index(Request $request, OnlineStatusService $onlineStatusService)
    {
        $ids = $request->input('ids', []);
        $ids = is_array($ids) ? array_values(array_filter($ids)) : [];

        return $this->success($onlineStatusService->getStatuses($ids));
    }
}
