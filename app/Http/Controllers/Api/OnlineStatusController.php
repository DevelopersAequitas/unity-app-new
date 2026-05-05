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

    public function offline(Request $request, OnlineStatusService $onlineStatusService)
    {
        $payload = $onlineStatusService->markOffline($request->user(), true, 'Last seen just now');

        return $this->success($payload, 'Online status updated');
    }

    public function show(string $id, OnlineStatusService $onlineStatusService)
    {
        return $this->success($onlineStatusService->getStatus($id));
    }

    public function index(Request $request, OnlineStatusService $onlineStatusService)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'uuid'],
        ]);

        return $this->success($onlineStatusService->getStatuses($validated['ids']));
    }

    public function updateStatus(Request $request, OnlineStatusService $onlineStatusService)
    {
        $validated = $request->validate([
            'is_online' => ['required', 'boolean'],
        ]);

        $payload = $onlineStatusService->updateOnlineStatus($request->user(), (bool) $validated['is_online']);

        return $this->success($payload, 'Online status updated successfully');
    }

    public function myConnectionsOnlineStatus(Request $request, OnlineStatusService $onlineStatusService)
    {
        $data = $onlineStatusService->getConnectionStatusesFor($request->user());

        return $this->success($data);
    }
}
