<?php

declare(strict_types=1);

namespace App\Services\Impacts;

use App\Models\Impact;
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
                'impact_coin' => 2500,
            ]);
        }

        $select = ['id', 'name', 'impact_score', 'is_active', 'sort_order', 'created_at'];
        if (Schema::hasColumn('impact_actions', 'impact_coin')) {
            $select[] = 'impact_coin';
        }

        return ImpactAction::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get($select);
    }

    public function createAction(string $name, int $impactScore = 1, ?int $impactCoin = null): ImpactAction
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

        $validScore = max(1, $impactScore);
        $validCoin = max(1, $impactCoin ?? ($validScore * 2500));

        return ImpactAction::query()->create([
            'name' => $normalized,
            'impact_score' => $validScore,
            'impact_coin' => $validCoin,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    public function updateAction(string $id, string $name, int $impactScore = 1, ?int $impactCoin = null, ?bool $isActive = null): ImpactAction
    {
        if (! Schema::hasTable('impact_actions')) {
            throw new \RuntimeException('impact_actions table is not available.');
        }

        $action = ImpactAction::query()->findOrFail($id);
        $normalized = trim($name);

        if ($normalized === '') {
            throw new \InvalidArgumentException('Action name is required.');
        }

        $exists = ImpactAction::query()
            ->whereKeyNot($action->id)
            ->whereRaw('LOWER(name) = ?', [Str::lower($normalized)])
            ->exists();

        if ($exists) {
            throw new \InvalidArgumentException('This impact action already exists.');
        }

        $validScore = max(1, $impactScore);
        $validCoin = max(1, $impactCoin ?? ($validScore * 2500));

        $action->name = $normalized;
        $action->impact_score = $validScore;
        $action->impact_coin = $validCoin;
        if ($isActive !== null) {
            $action->is_active = $isActive;
        }
        $action->save();

        return $action;
    }

    public function deleteOrDeactivateAction(string $id): void
    {
        if (! Schema::hasTable('impact_actions')) {
            throw new \RuntimeException('impact_actions table is not available.');
        }

        $action = ImpactAction::query()->findOrFail($id);

        $isUsed = Impact::query()->where('action', $action->name)->exists();

        if ($isUsed) {
            $action->is_active = false;
            $action->save();

            return;
        }

        $action->delete();
    }

    public function activeActionsForApi(): array
    {
        if (! Schema::hasTable('impact_actions')) {
            return [];
        }

        $select = ['id', 'name', 'impact_score', 'is_active'];
        if (Schema::hasColumn('impact_actions', 'impact_coin')) {
            $select[] = 'impact_coin';
        }

        return ImpactAction::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get($select)
            ->map(fn (ImpactAction $action) => [
                'id' => (string) $action->id,
                'name' => trim((string) $action->name),
                'impact_score' => max(1, (int) ($action->impact_score ?? 1)),
                'impact_coin' => max(1, (int) ($action->impact_coin ?? (($action->impact_score ?? 1) * 2500))),
                'is_active' => (bool) $action->is_active,
            ])
            ->values()
            ->all();
    }
}
