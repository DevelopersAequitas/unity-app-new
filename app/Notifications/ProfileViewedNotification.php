<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Http\Resources\UserMiniResource;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProfileViewedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $viewer
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $viewerName = $this->viewer->display_name ?? trim(($this->viewer->first_name ?? '').' '.($this->viewer->last_name ?? ''));
        if (empty($viewerName)) {
            $viewerName = 'Someone';
        }

        return [
            'notification_type' => NotificationType::PROFILE_VIEWED->value,
            'title' => 'Profile Viewed',
            'body' => $viewerName.' viewed your profile.',
            'viewer_id' => $this->viewer->id,
            'viewer_name' => $viewerName,
            'viewer' => (new UserMiniResource($this->viewer))->resolve(),
        ];
    }
}
