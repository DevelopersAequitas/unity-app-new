<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\EventPopupUpdated;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\EventPopupResource;
use App\Jobs\SendEventPopupNotificationJob;
use App\Models\Event;
use App\Models\EventPopupView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EventPopupController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = now();

        $events = Event::query()
            ->with('circle')
            ->where('show_popup', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('end_at')->where('start_at', '>=', $now)
                    ->orWhere('end_at', '>=', $now);
            })
            ->when(Schema::hasColumn('events', 'status'), fn ($query) => $query->whereNotIn('status', ['cancelled', 'canceled', 'completed', 'deleted', 'archived', 'inactive']))
            ->orderBy('start_at')
            ->get();

        if ($user && Schema::hasTable('event_popup_views')) {
            $seen = EventPopupView::query()
                ->where('user_id', $user->id)
                ->whereIn('event_id', $events->pluck('id'))
                ->get(['event_id', 'popup_version'])
                ->map(fn ($view) => $view->event_id . ':' . $view->popup_version)
                ->all();
            $seen = array_flip($seen);
            $events->each(fn (Event $event) => $event->already_seen = isset($seen[$event->id . ':' . (int) ($event->popup_version ?: 1)]));
        }

        return $this->success(EventPopupResource::collection($events)->resolve(), 'Event popups fetched successfully.');
    }

    public function updateSettings(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'show_popup' => ['required', 'boolean'],
            'realtime_popup' => ['required', 'boolean'],
            'popup_title' => ['nullable', 'string', 'max:255'],
            'popup_message' => ['nullable', 'string'],
            'popup_action_url' => ['nullable', 'url'],
        ]);

        $oldRealtimePopup = (bool) $event->realtime_popup;

        DB::transaction(function () use ($event, $validated, $oldRealtimePopup): void {
            $event->fill($validated);
            $event->popup_version = (int) ($event->popup_version ?: 1) + 1;
            if (! $oldRealtimePopup && (bool) $validated['realtime_popup']) {
                $event->popup_last_triggered_at = now();
            }
            $event->save();
        });

        $event->refresh()->load('circle');
        broadcast(new EventPopupUpdated($event));

        if (! $oldRealtimePopup && (bool) $event->realtime_popup) {
            SendEventPopupNotificationJob::dispatch((string) $event->id)->afterCommit();
        }

        return $this->success(EventPopupResource::payload($event), 'Event popup settings updated successfully.');
    }

    public function seen(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'popup_version' => ['required', 'integer', 'min:1'],
        ]);

        EventPopupView::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'event_id' => $event->id,
            'popup_version' => (int) $validated['popup_version'],
        ], [
            'seen_at' => now(),
        ]);

        return $this->success(null, 'Event popup marked as seen.');
    }
}
