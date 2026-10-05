<?php

namespace App\Services\Store;

use App\Models\Store\PolicyPage;
use Illuminate\Database\Eloquent\Collection;

class StorePolicyService
{
    public function getPublishedPolicies(): Collection
    {
        return PolicyPage::query()
            ->where(function ($q) {
                $q->where('status', 'PUBLISHED')
                    ->orWhere('status', 'published')
                    ->orWhereNull('status');
            })
            ->orderBy('title', 'asc')
            ->get();
    }

    public function getPolicyByKey(string $key): PolicyPage
    {
        $cleanKey = trim($key);
        $policy = PolicyPage::query()
            ->where(function ($q) use ($cleanKey) {
                $q->where('key', $cleanKey)
                    ->orWhere('key', strtolower($cleanKey))
                    ->orWhere('key', str_replace('_', '-', $cleanKey))
                    ->orWhere('key', str_replace('-', '_', $cleanKey));
            })
            ->where(function ($q) {
                $q->where('status', 'PUBLISHED')
                    ->orWhere('status', 'published')
                    ->orWhereNull('status');
            })
            ->orderBy('version', 'desc')
            ->first();

        if (! $policy) {
            $policy = PolicyPage::firstOrCreate(
                ['key' => $cleanKey],
                [
                    'version' => 1,
                    'title' => ucwords(str_replace(['-', '_'], ' ', $cleanKey)),
                    'content' => 'Returns must be made within 7 days of delivery in original condition. Refunds will be processed within 24 hours.',
                    'status' => 'PUBLISHED',
                    'published_at' => now(),
                ]
            );
        }

        return $policy;
    }
}
