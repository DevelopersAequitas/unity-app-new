<?php

namespace App\Http\Resources;

use App\Http\Resources\Ask\AskPreviewResource;
use App\Models\Ask\Ask;
use App\Models\Ask\AskTimelineLink;
use App\Models\File;
use App\Models\PostMention;
use App\Models\User;
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
            'mentions' => (function () {
                $mentions = [];

                if ($this->relationLoaded('postMentions') && $this->postMentions) {
                    foreach ($this->postMentions as $pm) {
                        $peer = $pm->peer;
                        if ($peer) {
                            $name = $peer->display_name ?: trim(($peer->first_name ?? '').' '.($peer->last_name ?? ''));
                            $mentions[] = [
                                'id' => (string) $peer->id,
                                'name' => $name !== '' ? $name : 'Peer Member',
                                'profile_photo_url' => $peer->profile_photo_file_id
                                    ? url('/api/v1/files/'.$peer->profile_photo_file_id)
                                    : null,
                            ];
                        }
                    }
                } elseif (! empty($this->id)) {
                    $loadedMentions = PostMention::with('peer')
                        ->where('post_id', $this->id)
                        ->get();
                    foreach ($loadedMentions as $pm) {
                        $peer = $pm->peer;
                        if ($peer) {
                            $name = $peer->display_name ?: trim(($peer->first_name ?? '').' '.($peer->last_name ?? ''));
                            $mentions[] = [
                                'id' => (string) $peer->id,
                                'name' => $name !== '' ? $name : 'Peer Member',
                                'profile_photo_url' => $peer->profile_photo_file_id
                                    ? url('/api/v1/files/'.$peer->profile_photo_file_id)
                                    : null,
                            ];
                        }
                    }
                }

                $isRecognition = in_array((string) ($this->source_type ?? ''), ['life_impact', 'member_introduction', 'recognition', 'growth_honour'], true)
                    || in_array((string) ($this->post_type ?? ''), ['life_impact_recognition', 'growth_honour'], true);

                if ($isRecognition && ! empty($this->source_id)) {
                    $alreadyIncluded = collect($mentions)->contains('id', (string) $this->source_id);
                    if (! $alreadyIncluded) {
                        $recognizedPeer = User::find($this->source_id);
                        if ($recognizedPeer) {
                            $recName = $recognizedPeer->display_name ?: trim(($recognizedPeer->first_name ?? '').' '.($recognizedPeer->last_name ?? ''));
                            $mentions[] = [
                                'id' => (string) $recognizedPeer->id,
                                'name' => $recName !== '' ? $recName : 'Peer Member',
                                'profile_photo_url' => $recognizedPeer->profile_photo_file_id
                                    ? url('/api/v1/files/'.$recognizedPeer->profile_photo_file_id)
                                    : null,
                            ];
                        }
                    }
                }

                return array_values($mentions);
            })(),
            'visibility' => $this->visibility,
            'moderation_status' => $this->moderation_status ?? null,
            'is_system_announcement' => $isAnniversary,

            'author' => $isAnniversary
                ? [
                    'id' => null,
                    'display_name' => 'PeersGlobal Unity',
                    'first_name' => 'PeersGlobal',
                    'last_name' => 'Unity',
                    'company_name' => 'PeersGlobal',
                    'designation' => 'Unity Admin',
                    'level4_category' => null,
                    'profile_photo_url' => null,
                ]
                : $this->when(
                    ($this->relationLoaded('user') && $this->user)
                    || ($this->relationLoaded('author') && $this->author),
                    function () {
                        $author = $this->user ?? $this->author;

                        $subCategory = $author?->level4Category?->name
                            ?? $author?->business_sub_category
                            ?? null;

                        return [
                            'id' => $author?->id,
                            'display_name' => $author?->display_name,
                            'first_name' => $author?->first_name,
                            'last_name' => $author?->last_name,
                            'company_name' => $author?->company_name ?: null,
                            'designation' => $author?->designation ?? null,
                            'level4_category' => $author?->level4Category?->name
                                ?? $author?->business_sub_category
                                ?? $author?->businessCategory?->name
                                ?? $author?->mainBusinessCategory?->name
                                ?? null,
                            'business_sub_category' => $subCategory,
                            'profile_photo_url' => $author?->profile_photo_url,
                            'profile_photo_image' => $author?->profile_photo_url,
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

        if ($this->source_type === 'ask' || $this->post_type === 'ask') {
            $askId = $this->source_id;
            if (! $askId) {
                $link = AskTimelineLink::query()->where('post_id', $this->id)->first();
                $askId = $link?->ask_id;
            }
            if ($askId) {
                $ask = Ask::query()
                    ->with(['flow', 'type', 'answers.option', 'district', 'circle', 'user'])
                    ->withCount(['responses', 'matches'])
                    ->find($askId);
                if ($ask) {
                    $response['ask'] = (new AskPreviewResource($ask))->toArray($request);
                }
            }
        }

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
