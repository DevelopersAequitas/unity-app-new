<?php

namespace App\Http\Resources;

use App\Http\Resources\Ask\AskPreviewResource;
use App\Models\ActivityCreative;
use App\Models\Ask\Ask;
use App\Models\Ask\AskTimelineLink;
use App\Models\BusinessDeal;
use App\Models\File;
use App\Models\P2pMeeting;
use App\Models\Post;
use App\Models\PostMention;
use App\Models\Referral;
use App\Models\Testimonial;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;
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
        $isSystemPost = method_exists($this->resource, 'isSystemCreativePost')
            ? $this->resource->isSystemCreativePost()
            : Post::isSystemPostRow(
                $this->source_type,
                $this->post_type,
                $this->tags,
                $this->relationLoaded('user') ? $this->user?->email : null,
                $this->relationLoaded('user') ? $this->user?->display_name : null,
                $this->relationLoaded('author') ? $this->author?->email : null,
                $this->relationLoaded('author') ? $this->author?->display_name : null
            );

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
            'display_name' => 'Peers Global Genie',
            'first_name' => 'Peers Global',
            'last_name' => 'Genie',
            'company_name' => 'Peers Global',
            'designation' => 'Peers Global Genie',
            'level4_category' => null,
            'business_sub_category' => null,
            'profile_photo_url' => $systemPhoto,
            'profile_photo_image' => $systemPhoto,
            'is_online' => true,
        ];

        $image = $this->image;
        $baseUrl = rtrim((string) config('app.url', 'https://peersunity.com'), '/');
        if (empty($baseUrl) || str_contains($baseUrl, 'localhost') || str_contains($baseUrl, '127.0.0.1')) {
            $baseUrl = 'https://peersunity.com';
        }

        if ($isAnniversary && $this->image) {
            $path = parse_url($this->image, PHP_URL_PATH);
            $fileId = basename($path);
            if (Str::isUuid($fileId)) {
                $fileModel = File::find($fileId);
                if ($fileModel && $fileModel->s3_key) {
                    $image = $baseUrl.'/storage/anniversary/'.ltrim($fileModel->s3_key, '/');
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
            'media_url' => $this->media_url,
            'media_type' => $this->media_type,
            'media' => $this->media
                ? collect($this->media)->map(function ($item) use ($isAnniversary, $baseUrl) {
                    if (! is_array($item)) {
                        return null;
                    }

                    $id = $item['id'] ?? null;
                    $url = $id ? url("/api/v1/files/{$id}") : null;

                    if ($isAnniversary && $id) {
                        $fileModel = File::find($id);
                        if ($fileModel && $fileModel->s3_key) {
                            $url = $baseUrl.'/storage/anniversary/'.ltrim($fileModel->s3_key, '/');
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
                $authorId = (string) ($this->user_id ?? '');
                $mentions = [];

                if ($this->relationLoaded('postMentions') && $this->postMentions) {
                    foreach ($this->postMentions as $pm) {
                        $peer = $pm->peer;
                        if ($peer && (string) $peer->id !== $authorId) {
                            $name = $peer->display_name ?: trim(($peer->first_name ?? '').' '.($peer->last_name ?? ''));
                            $mentions[] = [
                                'id' => (string) $peer->id,
                                'name' => $name !== '' ? $name : 'Peer Member',
                                'profile_photo_url' => $peer->profile_photo_file_id
                                    ? url('/api/v1/files/'.$peer->profile_photo_file_id)
                                    : null,
                                'is_online' => (bool) ($peer->is_online ?? false),
                            ];
                        }
                    }
                } elseif (! empty($this->id) && Schema::hasTable('post_mentions')) {
                    $loadedMentions = PostMention::with('peer')
                        ->where('post_id', $this->id)
                        ->get();
                    foreach ($loadedMentions as $pm) {
                        $peer = $pm->peer;
                        if ($peer && (string) $peer->id !== $authorId) {
                            $name = $peer->display_name ?: trim(($peer->first_name ?? '').' '.($peer->last_name ?? ''));
                            $mentions[] = [
                                'id' => (string) $peer->id,
                                'name' => $name !== '' ? $name : 'Peer Member',
                                'profile_photo_url' => $peer->profile_photo_file_id
                                    ? url('/api/v1/files/'.$peer->profile_photo_file_id)
                                    : null,
                                'is_online' => (bool) ($peer->is_online ?? false),
                            ];
                        }
                    }
                }

                $creativeTypes = [
                    'life_impact',
                    'member_introduction',
                    'recognition',
                    'growth_honour',
                    'milestone_honour',
                    'milestone_badge',
                    'welcome',
                    'birthday',
                    'anniversary',
                    'global_peer_certificate',
                    'introduction',
                    'life_impact_recognition',
                ];

                $candidatePeerIds = [];
                $inlineNamedMatches = [];

                $sourceType = (string) ($this->source_type ?? '');
                $postType = (string) ($this->post_type ?? '');
                $tags = is_array($this->tags) ? $this->tags : (is_string($this->tags) ? (json_decode($this->tags, true) ?? []) : []);

                $activityCreative = null;
                if (Schema::hasTable('activity_creatives') && ! empty($this->id)) {
                    $activityCreative = ActivityCreative::where('post_id', $this->id)->where('status', 'active')->first();
                }

                // Resolve counterpart peer for P2P Meeting
                if ($sourceType === 'p2p_meeting' || in_array('p2p_meeting', $tags, true) || ($activityCreative && in_array(strtolower((string) $activityCreative->activity_type), ['p2p_meeting', 'p2pmeeting'], true))) {
                    $meetingId = $this->source_id ?? $activityCreative?->activity_id;
                    $meeting = $meetingId ? P2pMeeting::find($meetingId) : null;
                    if (! $meeting && $authorId !== '' && $this->created_at) {
                        $meeting = P2pMeeting::where(function ($q) use ($authorId) {
                            $q->where('initiator_user_id', $authorId)->orWhere('peer_user_id', $authorId);
                        })
                        ->whereBetween('created_at', [$this->created_at->copy()->subMinutes(5), $this->created_at->copy()->addMinutes(5)])
                        ->latest('created_at')
                        ->first();
                    }
                    if ($meeting) {
                        $counterpart = (string) $meeting->initiator_user_id === $authorId ? (string) $meeting->peer_user_id : (string) $meeting->initiator_user_id;
                        if ($counterpart !== '' && $counterpart !== $authorId) {
                            $candidatePeerIds[] = $counterpart;
                        }
                    }
                }

                // Resolve counterpart peer for Business Deal
                if ($sourceType === 'business_deal' || in_array('business_deal', $tags, true) || ($activityCreative && strtolower((string) $activityCreative->activity_type) === 'business_deal')) {
                    $dealId = $this->source_id ?? $activityCreative?->activity_id;
                    $deal = $dealId ? BusinessDeal::find($dealId) : null;
                    if (! $deal && $authorId !== '' && $this->created_at) {
                        $deal = BusinessDeal::where(function ($q) use ($authorId) {
                            $q->where('from_user_id', $authorId)->orWhere('to_user_id', $authorId);
                        })
                        ->whereBetween('created_at', [$this->created_at->copy()->subMinutes(5), $this->created_at->copy()->addMinutes(5)])
                        ->latest('created_at')
                        ->first();
                    }
                    if ($deal) {
                        $counterpart = (string) $deal->from_user_id === $authorId ? (string) $deal->to_user_id : (string) $deal->from_user_id;
                        if ($counterpart !== '' && $counterpart !== $authorId) {
                            $candidatePeerIds[] = $counterpart;
                        }
                    }
                }

                // Resolve counterpart peer for Testimonial
                if ($sourceType === 'testimonial' || in_array('testimonial', $tags, true) || ($activityCreative && strtolower((string) $activityCreative->activity_type) === 'testimonial')) {
                    $testId = $this->source_id ?? $activityCreative?->activity_id;
                    $test = $testId ? Testimonial::find($testId) : null;
                    if (! $test && $authorId !== '' && $this->created_at) {
                        $test = Testimonial::where(function ($q) use ($authorId) {
                            $q->where('from_user_id', $authorId)->orWhere('to_user_id', $authorId);
                        })
                        ->whereBetween('created_at', [$this->created_at->copy()->subMinutes(5), $this->created_at->copy()->addMinutes(5)])
                        ->latest('created_at')
                        ->first();
                    }
                    if ($test) {
                        $counterpart = (string) $test->from_user_id === $authorId ? (string) $test->to_user_id : (string) $test->from_user_id;
                        if ($counterpart !== '' && $counterpart !== $authorId) {
                            $candidatePeerIds[] = $counterpart;
                        }
                    }
                }

                // Resolve counterpart peer for Referral
                if ($sourceType === 'referral' || in_array('referral', $tags, true) || $postType === 'referral') {
                    $refId = $this->source_id ?? $this->id;
                    $ref = $refId ? Referral::find($refId) : null;
                    if ($ref && ! empty($ref->to_user_id) && (string) $ref->to_user_id !== $authorId) {
                        $candidatePeerIds[] = (string) $ref->to_user_id;
                    }
                }

                // Text regex name match fallback
                if (! empty($this->content_text)) {
                    $text = (string) $this->content_text;
                    $matchedName = null;
                    if (preg_match('/sharing a moment of gratitude to\s+([^.]+?)(?:\.|$)/i', $text, $m)) {
                        $matchedName = trim($m[1]);
                    } elseif (preg_match('/made a deal with\s+(.+?)\s+of amount/i', $text, $m)) {
                        $matchedName = trim($m[1]);
                    } elseif (preg_match('/connected with\s+([^,]+?),/i', $text, $m)) {
                        $matchedName = trim($m[1]);
                    } elseif (preg_match('/referral for\s+.+?\s+to\s+([^.\n]+?)(?:\.|\n|$)/i', $text, $m)) {
                        $matchedName = trim($m[1]);
                    }

                    if ($matchedName !== null && $matchedName !== '' && strtolower($matchedName) !== 'peer') {
                        $matchedUser = User::query()
                            ->where(function ($q) use ($matchedName) {
                                $q->where('display_name', $matchedName)
                                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) = ?", [$matchedName]);
                            })
                            ->first(['id', 'display_name', 'first_name', 'last_name', 'profile_photo_file_id', 'profile_photo_url', 'is_online']);

                        if ($matchedUser && (string) $matchedUser->id !== $authorId) {
                            $candidatePeerIds[] = (string) $matchedUser->id;
                        }
                    }
                }

                if ((in_array($sourceType, $creativeTypes, true) || in_array($postType, $creativeTypes, true)) && ! empty($this->source_id) && (string) $this->source_id !== $authorId) {
                    $candidatePeerIds[] = (string) $this->source_id;
                }

                foreach ($tags as $t) {
                    if (is_string($t) && preg_match('/^[0-9a-fA-F-]{36}$/', $t) && (string) $t !== $authorId) {
                        $candidatePeerIds[] = $t;
                    }
                }

                if ($activityCreative && ! empty($activityCreative->user_id) && (string) $activityCreative->user_id !== $authorId) {
                    $actType = strtolower((string) $activityCreative->activity_type);
                    if (! in_array($actType, ['p2p_meeting', 'p2pmeeting', 'business_deal', 'testimonial', 'referral'], true)) {
                        $candidatePeerIds[] = (string) $activityCreative->user_id;
                    }
                }

                if (! empty($this->content_text) && preg_match_all('/@\[([^\]]+)\]\(([0-9a-fA-F-]{36})\)/', (string) $this->content_text, $inlineMatches)) {
                    foreach ($inlineMatches[2] as $idx => $matchedUuid) {
                        if ((string) $matchedUuid !== $authorId) {
                            $candidatePeerIds[] = $matchedUuid;
                            $inlineNamedMatches[$matchedUuid] = $inlineMatches[1][$idx] ?? null;
                        }
                    }
                }

                $alreadyMentionedIds = collect($mentions)->pluck('id')->all();
                $unresolvedPeerIds = array_values(array_diff(array_unique($candidatePeerIds), $alreadyMentionedIds));

                if (! empty($unresolvedPeerIds)) {
                    $users = User::whereIn('id', $unresolvedPeerIds)->get();
                    foreach ($users as $peer) {
                        if ((string) $peer->id === $authorId) {
                            continue;
                        }
                        $name = $peer->display_name ?: trim(($peer->first_name ?? '').' '.($peer->last_name ?? ''));
                        $fallbackName = $inlineNamedMatches[(string) $peer->id] ?? null;
                        $finalName = $name !== '' ? $name : ($fallbackName ?: 'Peer Member');
                        $mentions[] = [
                            'id' => (string) $peer->id,
                            'name' => $finalName,
                            'profile_photo_url' => $peer->profile_photo_file_id
                                ? url('/api/v1/files/'.$peer->profile_photo_file_id)
                                : ($peer->profile_photo_url ?: null),
                            'is_online' => (bool) ($peer->is_online ?? false),
                        ];
                    }
                }

                return array_values(array_filter($mentions, fn ($m) => (string) ($m['id'] ?? '') !== $authorId));
            })(),
            'visibility' => $this->visibility,
            'is_system_announcement' => $isSystemPost || $isAnniversary,

            'author' => $isSystemPost
                ? $systemAuthor
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
                            'is_online' => (bool) ($author?->is_online ?? false),
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

        if ($isSystemPost || $isAnniversary) {
            $response['user'] = [
                'id' => $systemUserId,
                'display_name' => 'Peers Global Genie',
                'first_name' => 'Peers Global',
                'last_name' => 'Genie',
                'profile_photo_url' => $systemPhoto,
                'profile_photo_image' => $systemPhoto,
            ];
            $response['author'] = $systemAuthor;
            $response['is_system_announcement'] = true;
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
