<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\FollowerResource;
use App\Models\UserFollow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class FollowerController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        try {
            $user = $request->user();
            $perPage = (int) ($validated['per_page'] ?? 20);
            $search = trim((string) ($validated['search'] ?? ''));

            $followers = UserFollow::query()
                ->with(['follower:id,first_name,last_name,display_name,email,phone,company_name,designation,city,profile_photo_url,profile_photo_file_id,profile_photo_id,public_profile_slug'])
                ->where('following_id', $user->id)
                ->where('status', 'accepted')
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->whereHas('follower', function (Builder $query) use ($search): void {
                        $searchTerm = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

                        $query->where(function (Builder $query) use ($searchTerm): void {
                            $query->where('first_name', 'ILIKE', $searchTerm)
                                ->orWhere('last_name', 'ILIKE', $searchTerm)
                                ->orWhere('display_name', 'ILIKE', $searchTerm)
                                ->orWhere('company_name', 'ILIKE', $searchTerm)
                                ->orWhere('email', 'ILIKE', $searchTerm)
                                ->orWhere('phone', 'ILIKE', $searchTerm);
                        });
                    });
                })
                ->orderByRaw('COALESCE(accepted_at, created_at) DESC')
                ->paginate($perPage);

            return $this->success([
                'total' => $followers->total(),
                'items' => FollowerResource::collection($followers->getCollection())->resolve($request),
                'pagination' => [
                    'current_page' => $followers->currentPage(),
                    'per_page' => $followers->perPage(),
                    'last_page' => $followers->lastPage(),
                    'total' => $followers->total(),
                ],
            ], 'Followers fetched successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->error('Unable to fetch followers.', 500);
        }
    }
}
