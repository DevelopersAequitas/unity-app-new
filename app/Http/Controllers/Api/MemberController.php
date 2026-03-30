<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ConnectionResource;
use App\Http\Resources\MemberDetailResource;
use App\Models\Connection;
use App\Models\Role;
use App\Models\User;
use App\Services\Notifications\NotifyUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $authUser = $request->user();

        if (! $this->isAdminUser($authUser)) {
            return $this->error('Forbidden', 403);
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $query = User::query()
            ->select([
                'id',
                'display_name',
                'email',
                'phone',
                'public_profile_slug',
                'membership_status',
                'created_at',
            ]);

        $paginator = $query
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->appends($request->query());

        $items = $paginator->getCollection()->map(function (User $user): array {
            $name = trim((string) ($user->display_name ?? ''));

            return [
                'id' => $user->id,
                'name' => $name !== '' ? $name : null,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_slug' => $user->public_profile_slug,
                'membership_status' => $user->membership_status,
                'created_at' => optional($user->created_at)?->toISOString(),
            ];
        })->values();

        $data = [
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];

        return $this->success($data);
    }

    public function names()
    {
        $members = User::query()
            ->select('id', 'display_name')
            ->whereNull('deleted_at')
            ->where(function ($statusQuery) {
                $statusQuery->whereNull('status')->orWhere('status', 'active');
            })
            ->orderBy('display_name', 'asc')
            ->get();

        return $this->success(
            $members,
            'Member names fetched successfully.'
        );
    }

    private function isAdminUser(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $adminRoleKeys = [
            'global_admin',
            'industry_director',
            'ded',
            'circle_leader',
            'chair',
            'vice_chair',
            'secretary',
            'founder',
            'director',
            'committee_leader',
        ];

        $roleIds = Role::query()
            ->whereIn('key', $adminRoleKeys)
            ->pluck('id');

        if ($roleIds->isEmpty()) {
            return false;
        }

        return $user->roles()->whereIn('roles.id', $roleIds)->exists();
    }

    public function show(Request $request, string $id)
    {
        $user = User::with(['city', 'activeCircle.cityRef'])->find($id);

        if (! $user) {
            return $this->error('Member not found', 404);
        }

        return $this->success(new MemberDetailResource($user));
    }

    public function publicProfileBySlug(Request $request, string $slug)
    {
        $user = User::with(['city', 'activeCircle.cityRef'])
            ->where('public_profile_slug', $slug)
            ->first();

        if (! $user) {
            return $this->error('Public profile not found', 404);
        }

        return $this->success(new MemberDetailResource($user));
    }

    public function sendConnectionRequest(Request $request, string $id, NotifyUserService $notifyUserService)
    {
        $authUser = $request->user();

        if ($authUser->id === $id) {
            return $this->error('You cannot connect to yourself', 422);
        }

        $target = User::find($id);
        if (! $target) {
            return $this->error('Member not found', 404);
        }

        $existing = Connection::where(function ($q) use ($authUser, $target) {
                $q->where('requester_id', $authUser->id)
                    ->where('addressee_id', $target->id);
            })
            ->orWhere(function ($q) use ($authUser, $target) {
                $q->where('requester_id', $target->id)
                    ->where('addressee_id', $authUser->id);
            })
            ->first();

        if ($existing) {
            if ($existing->is_approved) {
                return $this->error('You are already connected with this member', 422);
            }

            return $this->error('A connection request already exists', 422);
        }

        $connection = Connection::create([
            'requester_id' => $authUser->id,
            'addressee_id' => $target->id,
            'is_approved' => false,
        ]);

        $connection->load(['requester', 'addressee']);

        $notifyUserService->notifyUser(
            $target,
            $authUser,
            'connection_request',
            [
                'request_id' => (string) $connection->id,
                'title' => 'New Connection Request',
                'body' => ($authUser->display_name ?? $authUser->name ?? 'A member') . ' sent you a connection request',
            ],
            $connection
        );

        // Postman example (send connection request):
        // POST /api/v1/members/{id}/connect
        // Verify SQL:
        // select * from notifications where user_id = '<receiver-user-uuid>' order by created_at desc limit 20;

        return $this->success(new ConnectionResource($connection), 'Connection request sent', 201);
    }

    public function acceptConnection(Request $request, string $id, NotifyUserService $notifyUserService)
    {
        $authUser = $request->user();

        $connection = Connection::where('requester_id', $id)
            ->where('addressee_id', $authUser->id)
            ->where('is_approved', false)
            ->first();

        if (! $connection) {
            return $this->error('Connection request not found', 404);
        }

        $connection->is_approved = true;
        $connection->approved_at = now();
        $connection->save();

        $connection->load(['requester', 'addressee']);

        $requesterUser = $connection->requester;

        if ($requesterUser) {
            $notifyUserService->notifyUser(
                $requesterUser,
                $authUser,
                'connection_accepted',
                [
                    'request_id' => (string) $connection->id,
                    'from_user_id' => (string) $authUser->id,
                    'to_user_id' => (string) $requesterUser->id,
                    'title' => 'Connection Accepted',
                    'body' => ($authUser->display_name ?? $authUser->name ?? 'A member') . ' accepted your connection request',
                ],
                $connection
            );
        }

        // Postman example (accept connection request):
        // POST /api/v1/members/{requesterUserId}/accept
        // Verify SQL:
        // select * from notifications where user_id = '<requester-user-uuid>' order by created_at desc limit 20;

        return $this->success(new ConnectionResource($connection), 'Connection request accepted');
    }

    public function deleteConnection(Request $request, string $id)
    {
        $authUser = $request->user();

        $connection = Connection::where(function ($q) use ($authUser, $id) {
                $q->where('requester_id', $authUser->id)
                    ->where('addressee_id', $id);
            })
            ->orWhere(function ($q) use ($authUser, $id) {
                $q->where('requester_id', $id)
                    ->where('addressee_id', $authUser->id);
            })
            ->first();

        if (! $connection) {
            return $this->error('Connection not found', 404);
        }

        $connection->delete();

        return $this->success(null, 'Connection removed');
    }

    public function myConnections(Request $request)
    {
        $authUser = $request->user();

        $connections = Connection::with([
            'requester',
            'requester.city',
            'addressee',
            'addressee.city',
        ])
            ->where('is_approved', true)
            ->where(function ($q) use ($authUser) {
                $q->where('requester_id', $authUser->id)
                    ->orWhere('addressee_id', $authUser->id);
            })
            ->orderBy('approved_at', 'desc')
            ->get();

        return $this->success(ConnectionResource::collection($connections));
    }

    public function myConnectionRequests(Request $request)
    {
        $authUser = $request->user();

        $connections = Connection::with([
            'requester',
            'requester.city',
            'addressee',
            'addressee.city',
        ])
            ->where('addressee_id', $authUser->id)
            ->where('is_approved', false)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(ConnectionResource::collection($connections));
    }
}
