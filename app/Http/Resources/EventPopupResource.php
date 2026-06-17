<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventPopupResource extends JsonResource
{
    public function toArray($request): array
    {
        $circle = $this->whenLoaded('circle', fn () => $this->circle, $this->circle);

        return [
            'event_id' => (string) $this->id,
            'event_name' => $this->title,
            'address' => $this->location_text,
            'circle_name' => $circle?->name,
            'event_type' => $this->event_type,
            'circle_id' => $circle?->id ? (string) $circle->id : null,
            'image_url' => $this->fullImageUrl($this->banner_url),
            'show_popup' => (bool) $this->show_popup,
            'realtime_popup' => (bool) $this->realtime_popup,
            'popup_title' => $this->popup_title,
            'popup_message' => $this->popup_message,
            'popup_action_url' => $this->popup_action_url,
            'popup_version' => (int) ($this->popup_version ?: 1),
            'already_seen' => (bool) ($this->already_seen ?? false),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }

    public static function payload($event): array
    {
        return (new self($event))->resolve();
    }

    public static function fcmData($event): array
    {
        $payload = self::payload($event);
        return [
            'type' => 'event_popup',
            'event_id' => $payload['event_id'],
            'event_name' => (string) $payload['event_name'],
            'address' => (string) ($payload['address'] ?? ''),
            'circle_name' => (string) ($payload['circle_name'] ?? ''),
            'event_type' => (string) ($payload['event_type'] ?? ''),
            'circle_id' => (string) ($payload['circle_id'] ?? ''),
            'image_url' => (string) ($payload['image_url'] ?? ''),
            'show_popup' => $payload['show_popup'] ? 'true' : 'false',
            'realtime_popup' => $payload['realtime_popup'] ? 'true' : 'false',
            'popup_version' => (string) $payload['popup_version'],
        ];
    }

    private function fullImageUrl(?string $path): ?string
    {
        if (! $path) return null;
        if (Str::startsWith($path, ['http://', 'https://'])) return $path;
        return Storage::disk('public')->url(ltrim(Str::after($path, 'storage/'), '/'));
    }
}
