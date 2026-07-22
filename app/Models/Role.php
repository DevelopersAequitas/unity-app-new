<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_hierarchies', 'child_role_id', 'parent_role_id');
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_hierarchies', 'parent_role_id', 'child_role_id');
    }

    public function allDescendants(): Collection
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

    public function allAncestors(): Collection
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
            // Check if it's a known/standard role and create it dynamically
            $standardRoles = [
                'global_admin' => 'Global Admin',
                'global_founder' => 'Global Founder',
                'industry_director' => 'Industry Director',
                'ded' => 'DED',
                'circle_leader' => 'Circle Leader',
                'chair' => 'Chair',
                'vice_chair' => 'Vice Chair',
                'secretary' => 'Secretary',
                'founder' => 'Founder',
                'director' => 'Director',
                'committee_leader' => 'Committee Leader',
                'member' => 'Member',
                'marketing_team' => 'Marketing Team',
                'analytics_team' => 'Analytics Team',
                'content_team' => 'Content Team',
                'read_only' => 'Read Only Staff',
            ];

            if (array_key_exists($key, $standardRoles)) {
                $role = static::query()->create([
                    'id' => (string) Str::uuid(),
                    'key' => $key,
                    'name' => $standardRoles[$key],
                    'description' => $standardRoles[$key].' Role',
                ]);

                return $role->id;
            }

            Log::error('Role key missing in roles table.', [
                'role_key' => $key,
            ]);

            throw new RuntimeException("Role key '{$key}' not found in roles table.");
        }

        return $roleId;
    }
}
