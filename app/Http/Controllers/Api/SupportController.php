<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Support\StoreSupportRequest;
use App\Http\Requests\Support\UpdateSupportRequest;
use App\Http\Resources\SupportRequestResource;
use App\Models\SupportRequest;
use Illuminate\Http\Request;

class SupportController extends BaseApiController
{
    public function store(StoreSupportRequest $request)
    {
        $authUser = $request->user();
        $data = $request->validated();

        $ticket = new SupportRequest;
        $ticket->user_id = $authUser->id;
        $ticket->support_type = $data['support_type'];
        $ticket->details = $data['details'] ?? null;
        $ticket->attachments = $data['attachments'] ?? null;
        $ticket->status = 'open';
        $ticket->routed_to_user_id = null;
        $ticket->save();

        $ticket->refresh();
        $ticket->load(['user', 'routedToUser']);

        return $this->success(new SupportRequestResource($ticket), 'Support request created successfully', 201);
    }

    public function mySupportRequests(Request $request)
    {
        $authUser = $request->user();

        $query = SupportRequest::with(['user', 'routedToUser'])
            ->where('user_id', $authUser->id);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $paginator = $query
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $data = [
            'items' => SupportRequestResource::collection($paginator),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];

        return $this->success($data);
    }

    public function adminIndex(Request $request)
    {
        $admin = $request->user();
        // TODO: enforce admin/support role authorization via middleware / gates

        $query = SupportRequest::with(['user', 'routedToUser']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($supportType = $request->input('support_type')) {
            $query->where('support_type', $supportType);
        }

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($routedToUserId = $request->input('routed_to_user_id')) {
            $query->where('routed_to_user_id', $routedToUserId);
        }

        if ($request->boolean('unassigned', false)) {
            $query->whereNull('routed_to_user_id');
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $paginator = $query
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $data = [
            'items' => SupportRequestResource::collection($paginator),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];

        return $this->success($data);
    }

    public function adminUpdate(UpdateSupportRequest $request, string $id)
    {
        $admin = $request->user();
        // TODO: enforce admin/support role authorization via middleware / gates

        $ticket = SupportRequest::with(['user', 'routedToUser'])->find($id);

        if (! $ticket) {
            return $this->error('Support request not found', 404);
        }

        $data = $request->validated();

        $ticket->fill($data);
        $ticket->save();

        $ticket->load(['user', 'routedToUser']);

        return $this->success(new SupportRequestResource($ticket), 'Support request updated successfully');
    }
}
