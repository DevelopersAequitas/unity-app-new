<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class Role extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'key',
        'name',
        'description',
        'role_type',
        'scope_rule',
        'hierarchy_depth',
        'role_code',
        'status',
        'is_assignable',
    ];

    public function parents(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_hierarchies', 'child_role_id', 'parent_role_id');
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_hierarchies', 'parent_role_id', 'child_role_id');
    }

    public function allDescendants(): \Illuminate\Support\Collection
    {
        $descendants = collect();
        $queue = [$this];
        $visited = [$this->id => true];

        while (! empty($queue)) {
            $current = array_shift($queue);
            foreach ($current->children as $child) {
                if (! isset($visited[$child->id])) {
                    $visited[$child->id] = true;
                    $descendants->push($child);
                    $queue[] = $child;
                }
            }
        }

        return $descendants;
    }

    public function allDescendantIds(): array
    {
        return $this->allDescendants()->pluck('id')->all();
    }

    public function allAncestors(): \Illuminate\Support\Collection
    {
        $ancestors = collect();
        $queue = [$this];
        $visited = [$this->id => true];

        while (! empty($queue)) {
            $current = array_shift($queue);
            foreach ($current->parents as $parent) {
                if (! isset($visited[$parent->id])) {
                    $visited[$parent->id] = true;
                    $ancestors->push($parent);
                    $queue[] = $parent;
                }
            }
        }

        return $ancestors;
    }

    public function allAncestorIds(): array
    {
        return $this->allAncestors()->pluck('id')->all();
    }

    public function recomputeDepth(array &$visited = []): int
    {
        if (isset($visited[$this->id])) {
            return $this->hierarchy_depth ?? 0;
        }
        $visited[$this->id] = true;

        $parents = $this->parents;
        if ($parents->isEmpty()) {
            $depth = 0;
        } else {
            $depths = [];
            foreach ($parents as $parent) {
                $depths[] = $parent->recomputeDepth($visited);
            }
            $depth = 1 + max($depths);
        }

        if ($this->hierarchy_depth !== $depth) {
            $this->hierarchy_depth = $depth;
            $this->save();

            foreach ($this->children as $child) {
                $child->recomputeDepth($visited);
            }
        }

        unset($visited[$this->id]);

        return $depth;
    }

    public static function idByKey(string $key): ?string
    {
        return static::query()
            ->where('key', $key)
            ->value('id');
    }

    public static function mustIdByKey(string $key): string
    {
        $roleId = static::idByKey($key);

        if (! $roleId) {
            Log::error('Role key missing in roles table.', [
                'role_key' => $key,
            ]);

            throw new RuntimeException("Role key '{$key}' not found in roles table.");
        }

        return $roleId;
    }
}
