<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\MutualConnectionResource;
use App\Models\User;
use App\Services\MutualConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MutualConnectionController extends BaseApiController
{
    /**
     * Return users who are accepted mutual connections for the authenticated and target users.
     */
    public function index(Request $request, string $userUuid, MutualConnectionService $service): JsonResponse
    {
        if (! $this->isUuid($userUuid)) {
            return $this->error('Invalid user UUID.', 422, [
                'user_uuid' => ['The user uuid field must be a valid UUID.'],
            ]);
        }

        $targetUser = User::query()
            ->select(['id', 'first_name', 'last_name', 'display_name'])
            ->find($userUuid);

        if (! $targetUser) {
            return $this->error('Target user not found.', 404);
        }

        $perPage = max(1, min((int) $request->integer('per_page', 20), 100));
        $connections = $service->paginate($request->user(), $targetUser, $perPage);

        $total = $connections->total();

        return $this->success([
            'target_user' => $total > 0 ? [
                'uuid' => (string) $targetUser->id,
                'name' => $this->displayName($targetUser),
            ] : (object) [],
            'total' => $total,
            'connections' => MutualConnectionResource::collection($connections->getCollection()),
            'pagination' => [
                'current_page' => $connections->currentPage(),
                'per_page' => $connections->perPage(),
                'last_page' => $connections->lastPage(),
                'total' => $total,
            ],
        ], $total > 0 ? 'Mutual connections fetched successfully.' : 'No mutual connections found.');
    }

    /**
     * Validate UUID strings without relying on route model binding.
     */
    private function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value);
    }

    /**
     * Resolve a user's display name for the target user payload.
     */
    private function displayName(User $user): string
    {
        $displayName = trim((string) ($user->display_name ?? ''));

        if ($displayName !== '') {
            return $displayName;
        }

        return trim((string) ($user->first_name ?? '').' '.(string) ($user->last_name ?? ''));
    }
}
