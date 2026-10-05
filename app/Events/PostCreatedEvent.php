<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Post;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class PostCreatedEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Post $post
    ) {}

    /**
     * The channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('community-stream'),
            new Channel('admin-channel'),
            new PrivateChannel('admin-channel'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'post.created';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->post->loadMissing([
            'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,designation,active_circle_id',
            'circle:id,name',
        ]);

        $user = $this->post->user;
        $author = $user ? [
            'id' => $user->id,
            'name' => $user->name ?? $user->display_name ?? trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'display_name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'company_name' => $user->company_name,
            'company' => $user->company_name,
            'designation' => $user->designation,
            'avatar' => $user->avatar ?? $user->profile_photo_url,
            'profile_photo_url' => $user->profile_photo_url,
        ] : null;

        $postArray = $this->post->toArray();
        $mediaUrl = $this->post->video_path
            ? asset('storage/'.ltrim((string) $this->post->video_path, '/'))
            : ($this->post->media_url ? (Str::startsWith((string) $this->post->media_url, ['http://', 'https://']) ? (string) $this->post->media_url : url((string) $this->post->media_url)) : $this->post->media_url);

        $mediaType = ($this->post->video_path || preg_match('/\.(mp4|mov|webm|m4v)(\?.*)?$/i', (string) ($this->post->media_url ?? $mediaUrl ?? '')) || $this->post->media_type === 'video')
            ? 'video'
            : ($mediaUrl || $this->post->media_type === 'image' ? 'image' : null);

        $postArray['media_url'] = $mediaUrl;
        $postArray['media_type'] = $mediaType;
        $postArray['author'] = $author;
        $postArray['user'] = $author;

        return [
            'post' => $postArray,
            ...$postArray,
        ];
    }
}
