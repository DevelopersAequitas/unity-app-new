<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        $authUser = auth()->user();

        $isSaved = false;

        if ($authUser) {
            if (isset($this->is_saved_by_me)) {
                $isSaved = (bool) $this->is_saved_by_me;
            } elseif ($this->relationLoaded('saves')) {
                $isSaved = $this->saves->contains('user_id', $authUser->id);
            }
        }

        $savesCount = isset($this->saves_count)
            ? (int) $this->saves_count
            : ($this->relationLoaded('saves') ? $this->saves->count() : 0);

        $isSystemCreative = (method_exists($this->resource, 'isSystemCreativePost') && $this->resource->isSystemCreativePost());
        $systemPhoto = (($this->relationLoaded('user') && $this->user?->isSystemUser() && $this->user?->profile_photo_url)
            ? $this->user->profile_photo_url
            : (($this->relationLoaded('author') && $this->author?->isSystemUser() && $this->author?->profile_photo_url)
                ? $this->author->profile_photo_url
                : User::getSystemUserPhotoUrl()));
        $systemUserId = (string) (
            ($this->relationLoaded('user') && $this->user?->isSystemUser()) ? $this->user->id : (
                ($this->relationLoaded('author') && $this->author?->isSystemUser()) ? $this->author->id : (
                    $this->user_id ?? $this->author?->id ?? User::getSystemUserId()
                )
            )
        );

        $systemAuthor = [
            'id' => $systemUserId,
            'name' => 'Peers Global Genie',
            'display_name' => 'Peers Global Genie',
            'first_name' => 'Peers Global',
            'last_name' => 'Genie',
            'company_name' => 'Peers Global',
            'designation' => 'Peers Global Genie',
            'profile_photo_url' => $systemPhoto,
            'profile_photo_image' => $systemPhoto,
            'is_online' => true,
        ];

        return [
            'id' => $this->id,
            'caption' => $this->content_text,
            'content' => $this->content_text,
            'visibility' => $this->visibility,
            'created_at' => $this->created_at,
            'author' => $isSystemCreative
                ? $systemAuthor
                : new UserMiniResource($this->whenLoaded('author')),
            'user' => $isSystemCreative
                ? $systemAuthor
                : new UserMiniResource($this->whenLoaded('author') ?: $this->whenLoaded('user')),
            'is_system_announcement' => $isSystemCreative,
            'media' => PostMediaResource::collection(collect($this->media ?? [])),
            'likes_count' => (int) ($this->likes_count ?? 0),
            'comments_count' => (int) ($this->comments_count ?? 0),
            'liked_by_me' => (bool) ($this->liked_by_me ?? $this->is_liked_by_me ?? false),
            'saves_count' => $savesCount,
            'is_saved' => $isSaved,
            'latest_comments' => PostCommentMiniResource::collection($this->whenLoaded('comments')),
        ];
    }
}
