<?php

namespace App\Services\Impacts;

use App\Models\ImpactAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImpactActionService
{
    public function availableActions(): array
    {
        if (! Schema::hasTable('impact_actions')) {
            return [];
        }

        return ImpactAction::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn (string $name) => $name !== '')
            ->values()
            ->all();
    }

    public function listForAdmin(): Collection
    {
        if (! Schema::hasTable('impact_actions')) {
            return collect($this->availableActions())->map(fn (string $name) => (object) [
                'name' => $name,
                'is_active' => true,
                'sort_order' => 0,
                'impact_score' => 1,
            ]);
        }

        return ImpactAction::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'impact_score', 'is_active', 'sort_order', 'created_at']);
    }

    public function createAction(string $name, int $impactScore = 1): ImpactAction
    {
        if (! Schema::hasTable('impact_actions')) {
            throw new \RuntimeException('impact_actions table is not available.');
        }

        $normalized = trim($name);

        if ($normalized === '') {
            throw new \InvalidArgumentException('Action name is required.');
        }

        $exists = ImpactAction::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($normalized)])
            ->exists();

        if ($exists) {
            throw new \InvalidArgumentException('This impact action already exists.');
        }

        return ImpactAction::query()->create([
            'name' => $normalized,
            'impact_score' => max(1, $impactScore),
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    public function activeActionsForApi(): array
    {
        if (! Schema::hasTable('impact_actions')) {
            return [];
        }

        return ImpactAction::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'impact_score', 'is_active'])
            ->map(fn (ImpactAction $action) => [
                'id' => (string) $action->id,
                'name' => trim((string) $action->name),
                'impact_score' => max(1, (int) ($action->impact_score ?? 1)),
                'is_active' => (bool) $action->is_active,
            ])
            ->values()
            ->all();
    }
}
