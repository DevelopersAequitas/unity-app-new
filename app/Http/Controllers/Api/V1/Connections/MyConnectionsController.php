<?php

namespace App\Http\Controllers\Api\V1\Connections;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Connection\ConnectionUserResource;
use App\Http\Resources\Connection\SentConnectionResource;
use App\Models\Connection;
use Illuminate\Http\Request;

class MyConnectionsController extends BaseApiController
{
    public function index(Request $request)
    {
        $authUser = $request->user();

        $connections = Connection::query()
            ->with([
                'requester.profilePhotoFile',
                'requester.city',
                'requester.level4Category',
                'addressee.profilePhotoFile',
                'addressee.city',
                'addressee.level4Category',
            ])
            ->where('is_approved', true)
            ->where(function ($query) use ($authUser) {
                $query->where('requester_id', $authUser->id)
                    ->orWhere('addressee_id', $authUser->id);
            })
            ->orderByDesc('created_at')
            ->get();

        $items = $connections->map(function (Connection $connection) use ($authUser) {
            $otherUser = $connection->requester_id === $authUser->id
                ? $connection->addressee
                : $connection->requester;

            if (! $otherUser || $otherUser->id === $authUser->id) {
                return null;
            }

            return [
                'connected_at' => $connection->created_at,
                'user' => new ConnectionUserResource($otherUser),
            ];
        })->filter()->values();

        return $this->success([
            'items' => $items,
        ]);
    }

    public function sent()
    {
        $authUser = auth()->user();

        $connections = Connection::query()
            ->with([
                'addressee.profilePhotoFile',
                'addressee.city',
            ])
            ->where('requester_id', $authUser->id)
            ->where('is_approved', false)
            ->orderByDesc('created_at')
            ->get();

        $items = SentConnectionResource::collection($connections);

        return $this->success([
            'total' => $items->count(),
            'items' => $items,
        ]);
    }

    public function cancelSent(string $addresseeId)
    {
        $connection = Connection::query()
            ->where('requester_id', auth()->id())
            ->where('addressee_id', $addresseeId)
            ->first();

        if (! $connection) {
            return $this->error('Request not found', 404);
        }

        if ($connection->is_approved) {
            return $this->error('Request already approved. Use remove connection API.', 422);
        }

        $connection->delete();

        return $this->success(null, 'Connection request cancelled');
    }
}
