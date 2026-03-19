<?php

namespace App\Services;

use App\Http\Resources\V1\AdResource;
use App\Models\Ad;
use Illuminate\Support\Collection;

class AdFeedService
{
    public function timelineAds(?int $limit = null): Collection
    {
        $now = now();

        $query = Ad::query()
            ->whereRaw('LOWER(placement) = ?', ['timeline'])
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where(function ($builder) use ($now) {
                $builder->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($builder) use ($now) {
                $builder->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->orderByRaw('CASE WHEN timeline_position IS NULL THEN 1 ELSE 0 END')
            ->orderBy('timeline_position')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function mergeTimelineFeed(Collection $posts, Collection $ads): Collection
    {
        if ($ads->isEmpty()) {
            return $posts->values();
        }

        $postItems = $posts->values()->all();

        $positioned = $ads
            ->filter(fn (Ad $ad) => ! is_null($ad->timeline_position) && (int) $ad->timeline_position > 0)
            ->groupBy(fn (Ad $ad) => (int) $ad->timeline_position)
            ->map(function (Collection $group) {
                return $group->sortBy([
                    ['sort_order', 'asc'],
                    ['created_at', 'asc'],
                    ['id', 'asc'],
                ])->values()->all();
            })
            ->sortKeys();

        $unpositioned = $ads
            ->filter(fn (Ad $ad) => is_null($ad->timeline_position) || (int) $ad->timeline_position <= 0)
            ->sortBy([
                ['sort_order', 'asc'],
                ['created_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->all();

        $result = [];
        $postIndex = 0;
        $slot = 1;
        $maxSlot = max(
            count($postItems) + $positioned->flatten(1)->count(),
            (int) ($positioned->keys()->max() ?? 0)
        );

        while ($slot <= $maxSlot || $postIndex < count($postItems)) {
            if ($positioned->has($slot)) {
                foreach ($positioned->get($slot) as $ad) {
                    $result[] = $this->transformAd($ad);
                }
                $positioned->forget($slot);
            }

            if ($postIndex < count($postItems)) {
                $result[] = $postItems[$postIndex];
                $postIndex++;
            }

            $slot++;
        }

        foreach ($positioned as $group) {
            foreach ($group as $ad) {
                $result[] = $this->transformAd($ad);
            }
        }

        foreach ($unpositioned as $ad) {
            $result[] = $this->transformAd($ad);
        }

        return collect($result)->values();
    }

    private function transformAd(Ad $ad): array
    {
        return AdResource::make($ad)->resolve();
    }
}
