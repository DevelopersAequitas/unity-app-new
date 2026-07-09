<?php

namespace App\Http\Resources;

use App\Models\File;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

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

        $isAnniversary = ($this->post_type === 'anniversary' || $this->source_type === 'anniversary');
        $image = $this->image;
        if ($isAnniversary && $this->image) {
            $path = parse_url($this->image, PHP_URL_PATH);
            $fileId = basename($path);
            if (Str::isUuid($fileId)) {
                $fileModel = File::find($fileId);
                if ($fileModel && $fileModel->s3_key) {
                    $image = 'https://dev.peersunity.com/storage/anniversary/'.ltrim($fileModel->s3_key, '/');
                }
            }
        }

        $response = [
            'id' => $this->id,

            'content_text' => $this->content_text,
            'content' => $this->content_text,
            'post_type' => $this->post_type ?? 'standard',
            'template_id' => $this->template_id ?? null,
            'title' => $this->title ?? null,
            'description' => $this->description ?? $this->content_text,
            'image' => $image,
            'status' => $this->status ?? ($this->active ? 'active' : 'inactive'),
            'media' => $this->media
                ? collect($this->media)->map(function ($item) use ($isAnniversary) {
                    if (! is_array($item)) {
                        return null;
                    }

                    $id = $item['id'] ?? null;
                    $url = $id ? url("/api/v1/files/{$id}") : null;

                    if ($isAnniversary && $id) {
                        $fileModel = File::find($id);
                        if ($fileModel && $fileModel->s3_key) {
                            $url = 'https://dev.peersunity.com/storage/anniversary/'.ltrim($fileModel->s3_key, '/');
                        }
                    }

                    return [
                        'id' => $id,
                        'type' => $item['type'] ?? null,
                        'url' => $url,
                    ];
                })->filter()->values()->all()
                : null,
            'tags' => $this->tags,
            'visibility' => $this->visibility,
            'moderation_status' => $this->moderation_status ?? null,
            'is_system_announcement' => $isAnniversary,

            'author' => $isAnniversary
                ? [
                    'id' => null,
                    'display_name' => 'PeersGlobal Unity',
                    'first_name' => 'PeersGlobal',
                    'last_name' => 'Unity',
                    'profile_photo_url' => null,
                ]
                : $this->when(
                    ($this->relationLoaded('user') && $this->user)
                    || ($this->relationLoaded('author') && $this->author),
                    function () {
                        $author = $this->user ?? $this->author;

                        return [
                            'id' => $author?->id,
                            'display_name' => $author?->display_name,
                            'first_name' => $author?->first_name,
                            'last_name' => $author?->last_name,
                            'profile_photo_url' => $author?->profile_photo_url,
                        ];
                    }
                ),

            'circle' => $this->when(
                $this->relationLoaded('circle') && $this->circle,
                function () {
                    return [
                        'id' => $this->circle->id,
                        'name' => $this->circle->name,
                    ];
                }
            ),

            'likes_count' => isset($this->likes_count) ? (int) $this->likes_count : 0,
            'comments_count' => isset($this->comments_count) ? (int) $this->comments_count : 0,

            'is_liked_by_me' => (bool) ($this->is_liked_by_me ?? false),
            'saves_count' => $savesCount,
            'is_saved' => $isSaved,

            'created_at' => $this->formatToDefaultDateTime($this->created_at),
            'updated_at' => $this->formatToDefaultDateTime($this->updated_at),
        ];

        if ($isAnniversary) {
            $response['user'] = [
                'id' => null,
                'display_name' => 'PeersGlobal Unity',
                'first_name' => 'PeersGlobal',
                'last_name' => 'Unity',
                'profile_photo_url' => null,
            ];
            $response['author'] = $response['user'];
        }

        return $response;
    }

    private function formatToDefaultDateTime(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::parse((string) $value)
            ->timezone(config('app.timezone', 'UTC'))
            ->format('Y-m-d H:i:s');
    }
}
