<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\BulkUsersRequest;
use App\Http\Resources\V1\BulkUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function bulkUsers(BulkUsersRequest $request): JsonResponse
    {
        $requestedUserIds = $request->array('user_ids');
        $uniqueUserIds = array_values(array_unique($requestedUserIds));

        $usersById = User::query()
            ->select([
                'id',
                'display_name',
                'first_name',
                'last_name',
                'email',
                'phone',
                'profile_photo_id',
                'profile_photo_file_id',
            ])
            ->whereIn('id', $uniqueUserIds)
            ->where('status', 'active')
            ->get()
            ->keyBy('id');

        $users = [];
        $notFound = [];

        foreach ($uniqueUserIds as $userId) {
            $user = $usersById->get($userId);

            if (! $user) {
                $notFound[] = $userId;

                continue;
            }

            $users[] = (new BulkUserResource($user))->resolve($request);
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Users fetched successfully.',
            'data' => $users,
            'not_found' => $notFound,
        ]);
    }
}
