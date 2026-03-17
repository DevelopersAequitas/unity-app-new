<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CircularDetailResource;
use App\Http\Resources\CircularListResource;
use App\Models\Circular;
use App\Models\CircularBookmark;
use App\Models\CircularRead;
use App\Models\CircularReaction;
use Illuminate\Http\Request;

class CircularController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = Circular::query()
            ->with(['city', 'circle'])
            ->where('status', 'published')
            ->where('publish_date', '<=', now())
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>', now()))
            ->orderByDesc('is_pinned')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 3 WHEN 'important' THEN 2 ELSE 1 END DESC")
            ->orderByDesc('publish_date');

        $items = $query->paginate((int) $request->integer('per_page', 20));

        return $this->success([
            'items' => CircularListResource::collection($items),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ], 'Circulars fetched successfully.');
    }

    public function show(Request $request, Circular $circular)
    {
        if ($circular->status !== 'published' || $circular->publish_date?->isFuture() || ($circular->expiry_date && $circular->expiry_date->isPast())) {
            return $this->error('Circular not found.', 404);
        }

        $user = auth('sanctum')->user();

        $circular->load(['city', 'circle'])
            ->loadCount('reads');

        $circular->helpful_count = $circular->reactions()->where('reaction_type', 'helpful')->count();
        $circular->important_count = $circular->reactions()->where('reaction_type', 'important')->count();

        if ($user) {
            $circular->is_bookmarked = CircularBookmark::query()
                ->where('circular_id', $circular->id)
                ->where('user_id', $user->id)
                ->exists();
            $circular->my_reaction = CircularReaction::query()
                ->where('circular_id', $circular->id)
                ->where('user_id', $user->id)
                ->value('reaction_type');
        }

        return $this->success(new CircularDetailResource($circular), 'Circular details fetched successfully.');
    }

    public function markRead(Request $request, Circular $circular)
    {
        CircularRead::query()->updateOrCreate(
            ['circular_id' => $circular->id, 'user_id' => $request->user()->id],
            ['read_at' => now()]
        );

        return $this->success([], 'Circular marked as read.');
    }

    public function bookmark(Request $request, Circular $circular)
    {
        CircularBookmark::query()->firstOrCreate([
            'circular_id' => $circular->id,
            'user_id' => $request->user()->id,
        ]);

        return $this->success([], 'Circular bookmarked.');
    }

    public function removeBookmark(Request $request, Circular $circular)
    {
        CircularBookmark::query()
            ->where('circular_id', $circular->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return $this->success([], 'Circular bookmark removed.');
    }

    public function reaction(Request $request, Circular $circular)
    {
        $validated = $request->validate([
            'reaction_type' => ['required', 'in:helpful,important'],
        ]);

        CircularReaction::query()->updateOrCreate(
            ['circular_id' => $circular->id, 'user_id' => $request->user()->id],
            ['reaction_type' => $validated['reaction_type']]
        );

        return $this->success([], 'Reaction saved.');
    }
}
