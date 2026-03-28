<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Profile\StoreUserLinkRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UpdateUserLinkRequest;
use App\Http\Resources\UserLinkResource;
use App\Http\Resources\UserProfileResource;
use App\Services\Users\PublicProfileSlugService;
use Illuminate\Http\Request;

class ProfileController extends BaseApiController
{
    public function show(Request $request)
    {
        $user = $request->user()->load([
            'profilePhotoFile',
            'coverPhotoFile',
            'userLinks',
        ]);

        return $this->success(new UserProfileResource($user), 'Profile fetched successfully');
    }

    public function update(UpdateProfileRequest $request, PublicProfileSlugService $publicProfileSlugService)
    {
        $user = $request->user();
        $data = $request->validated();

        if (array_key_exists('skills', $data)) {
            $data['skills'] = $data['skills'] ?? [];
        }

        if (array_key_exists('interests', $data)) {
            $data['interests'] = $data['interests'] ?? [];
        }

        if (array_key_exists('social_links', $data)) {
            $data['social_links'] = $data['social_links'] ?? [];
        }

        if (array_key_exists('about', $data)) {
            $data['short_bio'] = $data['about'];
            unset($data['about']);
        }

        if (array_key_exists('profile_photo_id', $data)) {
            $data['profile_photo_file_id'] = $data['profile_photo_id'];
            unset($data['profile_photo_id']);
        }

        if (array_key_exists('cover_photo_id', $data)) {
            $data['cover_photo_file_id'] = $data['cover_photo_id'];
            unset($data['cover_photo_id']);
        }

        if (array_key_exists('first_name', $data) || array_key_exists('last_name', $data)) {
            $displayName = trim(($data['first_name'] ?? $user->first_name ?? '') . ' ' . ($data['last_name'] ?? $user->last_name ?? ''));
            $user->display_name = $displayName !== '' ? $displayName : $user->email;
        }

        $user->fill($data);

        if (empty($user->public_profile_slug)) {
            $user->public_profile_slug = $publicProfileSlugService->generateUniqueForUser($user);
        }

        $user->save();

        $user->load(['profilePhotoFile', 'coverPhotoFile', 'userLinks']);

        return $this->success(new UserProfileResource($user), 'Profile updated successfully');
    }

    public function links(Request $request)
    {
        $user = $request->user();
        $links = $user->userLinks()->orderByDesc('created_at')->get();

        return $this->success(UserLinkResource::collection($links));
    }

    public function storeLink(StoreUserLinkRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        $link = $user->userLinks()->create($data);

        return $this->success(new UserLinkResource($link), 'Link created successfully', 201);
    }

    public function updateLink(UpdateUserLinkRequest $request, string $id)
    {
        $user = $request->user();
        $data = $request->validated();

        $link = $user->userLinks()->where('id', $id)->first();

        if (! $link) {
            return $this->error('Link not found', 404);
        }

        $link->fill($data);
        $link->save();

        return $this->success(new UserLinkResource($link), 'Link updated successfully');
    }

    public function destroyLink(Request $request, string $id)
    {
        $user = $request->user();
        $link = $user->userLinks()->where('id', $id)->first();

        if (! $link) {
            return $this->error('Link not found', 404);
        }

        $link->delete();

        return $this->success(null, 'Link deleted successfully');
    }
}
