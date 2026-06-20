<?php

namespace App\Services\Notifications;

use App\Models\CircleMember;
use App\Models\Connection;
use App\Models\Notifications\AppNotification;
use App\Models\Notifications\NotificationCampaign;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NotificationDispatchService
{
    public function __construct(private NotificationService $notifications)
    {
    }


    public function sendNewPostNotification(Post $post, User $author): Collection
    {
        $recipients = $this->getNewPostRecipients($post, $author);
        $preview = $this->postPreview($post);
        $media = collect($post->media ?? [])->first();
        $bodyPreview = trim((string) $post->content_text) !== ''
            ? $preview
            : ($this->displayName($author) . ' shared a new ' . (($media['type'] ?? '') === 'video' ? 'video.' : 'photo.'));

        $notifications = $this->sendCampaignNotification(
            'new_post_activity_circle',
            $recipients,
            ['person' => $this->displayName($author), 'post_preview_content' => $bodyPreview],
            [
                'screen' => 'post_details',
                'tap_destination' => 'post_details',
                'post_id' => (string) $post->id,
                'author_id' => (string) $author->id,
                'type' => 'new_post_activity_circle',
            ],
            $author,
            $post,
            ['type' => 'new_post_activity_circle', 'reference_type' => 'post', 'reference_id' => (string) $post->id, 'dedupe_key' => 'new_post:' . $post->id, 'bypass_limits' => true]
        );

        Log::info('New post notification dispatched', [
            'post_id' => (string) $post->id,
            'author_id' => (string) $author->id,
            'recipient_count' => $recipients->count(),
            'recipient_ids' => $recipients->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
            'campaign_code' => 'new_post_activity_circle',
            'notification_created_count' => $notifications->count(),
            'push_attempt_count' => $notifications->count(),
        ]);

        return $notifications;
    }

    public function sendPostLikeNotification(Post $post, User $liker): Collection
    {
        if ((string) $post->user_id === (string) $liker->id || ! ($owner = User::find($post->user_id))) {
            return collect();
        }

        return $this->sendCampaignNotification('post_like_received', $owner, ['person' => $this->displayName($liker), 'post_preview_content' => $this->postPreview($post)], ['screen' => 'post_details', 'post_id' => (string) $post->id, 'liker_id' => (string) $liker->id, 'type' => 'post_like_received'], $liker, $post, ['type' => 'post_like_received', 'reference_type' => 'post', 'reference_id' => (string) $post->id, 'dedupe_key' => 'post_like:' . $post->id . ':' . $liker->id, 'bypass_limits' => true]);
    }

    public function sendPostCommentNotification(Post $post, PostComment $comment, User $commenter): Collection
    {
        if ((string) $post->user_id === (string) $commenter->id || ! ($owner = User::find($post->user_id))) {
            return collect();
        }

        return $this->sendCampaignNotification('post_comment_received', $owner, ['person' => $this->displayName($commenter), 'comment_preview_content' => Str::limit($comment->content, 120), 'post_preview_content' => $this->postPreview($post)], ['screen' => 'post_details', 'post_id' => (string) $post->id, 'comment_id' => (string) $comment->id, 'commenter_id' => (string) $commenter->id, 'type' => 'post_comment_received'], $commenter, $comment, ['type' => 'post_comment_received', 'reference_type' => 'post_comment', 'reference_id' => (string) $comment->id, 'dedupe_key' => 'post_comment:' . $comment->id, 'bypass_limits' => true]);
    }

    public function sendPostMentionNotification(Post $post, User $actor, Collection|array $mentionedUsers, ?PostComment $comment = null, ?string $previewText = null): Collection
    {
        $recipients = collect($mentionedUsers)->filter(fn ($user) => $user instanceof User)->reject(fn (User $user) => (string) $user->id === (string) $actor->id)->unique('id')->values();

        return $this->sendCampaignNotification('user_mention_notification', $recipients, ['person' => $this->displayName($actor), 'post_preview_content' => Str::limit($previewText ?: $this->postPreview($post), 120), 'comment_preview_content' => Str::limit($previewText ?: '', 120)], ['screen' => 'post_details', 'post_id' => (string) $post->id, 'comment_id' => $comment?->id, 'mentioned_by' => (string) $actor->id, 'type' => 'user_mention_notification'], $actor, $comment ?: $post, ['type' => 'user_mention_notification', 'reference_type' => $comment ? 'post_comment' : 'post', 'reference_id' => (string) ($comment?->id ?? $post->id), 'dedupe_key' => 'mention:' . ($comment?->id ?? $post->id), 'bypass_limits' => true]);
    }

    public function sendPostShareNotification(Post $post, User $sharedBy): Collection
    {
        if ((string) $post->user_id === (string) $sharedBy->id || ! ($owner = User::find($post->user_id))) {
            return collect();
        }

        return $this->sendCampaignNotification('share_post_alert', $owner, ['person' => $this->displayName($sharedBy), 'post_preview_content' => $this->postPreview($post)], ['screen' => 'post_details', 'post_id' => (string) $post->id, 'shared_by' => (string) $sharedBy->id, 'type' => 'share_post_alert'], $sharedBy, $post, ['type' => 'share_post_alert', 'reference_type' => 'post', 'reference_id' => (string) $post->id, 'dedupe_key' => 'post_share:' . $post->id . ':' . $sharedBy->id, 'bypass_limits' => true]);
    }

    public function getNewPostRecipients(Post $post, User $author): Collection
    {
        $connectionIds = Connection::query()->where('is_approved', true)->where(fn ($q) => $q->where('requester_id', $author->id)->orWhere('addressee_id', $author->id))->get()->flatMap(fn (Connection $connection) => [(string) $connection->requester_id, (string) $connection->addressee_id]);
        $circleIds = CircleMember::query()->where('user_id', $author->id)->whereNull('deleted_at')->where(fn ($q) => $q->whereNull('status')->orWhereIn('status', ['active', 'approved', 'member']))->pluck('circle_id');
        $circleUserIds = CircleMember::query()->whereIn('circle_id', $circleIds)->whereNull('deleted_at')->where(fn ($q) => $q->whereNull('status')->orWhereIn('status', ['active', 'approved', 'member']))->pluck('user_id');
        $ids = $connectionIds->merge($circleUserIds)->filter()->unique()->reject(fn ($id) => (string) $id === (string) $author->id)->values();

        return User::whereIn('id', $ids)
            ->when(Schema::hasColumn('users', 'status'), fn ($q) => $q->where(fn ($query) => $query->whereNull('status')->orWhere('status', 'active')))
            ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where(fn ($query) => $query->whereNull('is_active')->orWhere('is_active', true)))
            ->get();
    }

    public function sendCampaignNotification(
        string $campaignCode,
        array|Collection|User $recipients,
        array $placeholders = [],
        array $data = [],
        ?User $actor = null,
        ?Model $reference = null,
        array $options = []
    ): Collection {
        $campaign = NotificationCampaign::where('code', $campaignCode)->where('is_active', true)->first();
        if (! $campaign) {
            return collect();
        }

        $users = $recipients instanceof User ? collect([$recipients]) : collect($recipients);
        $users = $users->filter(fn ($user) => $user instanceof User)
            ->unique(fn (User $user) => (string) $user->id)
            ->reject(fn (User $user) => $actor && (string) $user->id === (string) $actor->id && empty($options['send_to_actor']))
            ->values();

        $screen = $options['screen'] ?? $data['screen'] ?? $campaign->tap_screen;
        $type = $options['type'] ?? $data['type'] ?? $campaign->code;
        $referenceType = $options['reference_type'] ?? ($reference ? $reference::class : null);
        $referenceId = $options['reference_id'] ?? ($reference ? (string) $reference->getKey() : null);
        $basePlaceholders = array_merge($this->defaultPlaceholders($actor), $placeholders);

        return $users->map(function (User $user) use ($campaign, $basePlaceholders, $data, $actor, $screen, $type, $referenceType, $referenceId, $options): ?AppNotification {
            $rendered = $this->renderCampaign($campaign, $basePlaceholders);
            $dedupeKey = $options['dedupe_key'] ?? $data['dedupe_key'] ?? null;
            if ($dedupeKey) {
                $dedupeKey .= ':' . $user->id;
            }

            return $this->notifications->sendToUser(
                $user,
                $type,
                $rendered['title'],
                $rendered['body'],
                array_merge($data, ['screen' => $screen, 'campaign_id' => $campaign->id]),
                [
                    'campaign' => $campaign,
                    'category' => $campaign->category,
                    'channel' => $options['channel'] ?? $campaign->channel ?? 'push',
                    'priority' => $options['priority'] ?? $campaign->priority ?? 'medium',
                    'screen' => $screen,
                    'actor_user_id' => $actor?->id,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'dedupe_key' => $dedupeKey,
                    'send_to_actor' => $options['send_to_actor'] ?? false,
                    'bypass_limits' => $options['bypass_limits'] ?? false,
                ]
            );
        })->filter()->values();
    }

    private function renderCampaign(NotificationCampaign $campaign, array $placeholders): array
    {
        return [
            'title' => $this->renderTemplate((string) $campaign->title_template, $placeholders),
            'body' => $this->renderTemplate((string) $campaign->body_template, $placeholders),
        ];
    }

    private function renderTemplate(string $template, array $placeholders): string
    {
        foreach ($placeholders as $key => $value) {
            $value = (string) $value;
            $template = str_replace([
                '{{' . $key . '}}',
                '{' . $key . '}',
                '<' . $key . '>',
                '[' . Str::of($key)->replace('_', ' ')->title() . ']',
            ], $value, $template);
        }

        return $template;
    }

    private function defaultPlaceholders(?User $actor): array
    {
        $name = $actor ? (trim((string) ($actor->display_name ?? '')) ?: trim(((string) ($actor->first_name ?? '')) . ' ' . ((string) ($actor->last_name ?? ''))) ?: (string) ($actor->name ?? 'A member')) : 'A member';

        return ['person' => $name, 'name' => $name];
    }

    private function displayName(User $user): string
    {
        return trim((string) ($user->display_name ?? '')) ?: trim(((string) ($user->first_name ?? '')) . ' ' . ((string) ($user->last_name ?? ''))) ?: (string) ($user->name ?? 'A member');
    }

    private function postPreview(Post $post): string
    {
        return Str::limit(trim((string) $post->content_text), 120) ?: 'New post';
    }
}
