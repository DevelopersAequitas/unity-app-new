<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Api\V1\CircleCategoryUsageController;
use App\Models\Circle;
use App\Models\CircleCategory;
use App\Models\CircleCategoryLevel2;
use App\Models\CircleCategoryLevel3;
use App\Models\CircleCategoryLevel4;
use App\Models\CircleMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CircleCategoryAdminController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) ($request->query('search') ?? $request->query('q') ?? ''));

        $items = CircleCategory::query()
            ->where('level', 1)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('slug', 'ILIKE', "%{$search}%")
                        ->orWhere('circle_key', 'ILIKE', "%{$search}%");
                });
            })
            ->withCount([
                'level2Categories as child_level2_count',
                'level3Categories as child_level3_count',
                'level4Categories as child_level4_count',
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $allCircles = Circle::query()
            ->select(['id', 'name', 'template_id', 'status', 'city', 'industry_tags'])
            ->whereNull('deleted_at')
            ->get();

        $transformed = $items->map(function (CircleCategory $cat) use ($allCircles) {
            $catId = (int) $cat->id;
            $catName = strtolower(trim($cat->name));
            $catKey = strtolower(trim($cat->circle_key ?? ''));

            $matchedCircles = $allCircles->filter(function ($c) use ($catId, $catName, $catKey) {
                if ((int) $c->template_id === $catId) {
                    return true;
                }
                if (!empty($c->industry_tags) && is_array($c->industry_tags)) {
                    foreach ($c->industry_tags as $t) {
                        $tagStr = strtolower(trim((string) $t));
                        if ($tagStr === (string) $catId || $tagStr === $catName || $tagStr === $catKey) {
                            return true;
                        }
                    }
                }
                return false;
            });

            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'circle_key' => $cat->circle_key,
                'level' => $cat->level,
                'sort_order' => $cat->sort_order,
                'is_active' => (bool) $cat->is_active,
                'child_level2_count' => (int) $cat->child_level2_count,
                'child_level3_count' => (int) $cat->child_level3_count,
                'child_level4_count' => (int) $cat->child_level4_count,
                'circles_count' => $matchedCircles->count(),
                'active_circles_count' => $matchedCircles->where('status', 'active')->count(),
            ];
        });

        return $this->success([
            'items' => $transformed,
            'total' => $transformed->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:circle_categories,slug'],
            'circle_key' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $slug = filled($data['slug'] ?? null)
            ? Str::slug($data['slug'])
            : Str::slug($data['name']);

        $key = filled($data['circle_key'] ?? null)
            ? Str::slug($data['circle_key'], '_')
            : Str::slug($data['name'], '_');

        $maxOrder = (int) CircleCategory::where('level', 1)->max('sort_order');

        $category = CircleCategory::create([
            'name' => trim($data['name']),
            'slug' => $slug,
            'circle_key' => $key,
            'level' => 1,
            'sort_order' => $data['sort_order'] ?? ($maxOrder + 1),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->success([
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'circle_key' => $category->circle_key,
            'level' => 1,
            'sort_order' => $category->sort_order,
            'is_active' => (bool) $category->is_active,
            'child_level2_count' => 0,
            'child_level3_count' => 0,
            'child_level4_count' => 0,
            'circles_count' => 0,
            'active_circles_count' => 0,
        ], 'Category created successfully.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $cat = CircleCategory::where('level', 1)
            ->where(function ($q) use ($id) {
                if (ctype_digit($id)) {
                    $q->where('id', (int) $id);
                } else {
                    $q->where('slug', $id)->orWhere('circle_key', $id);
                }
            })
            ->firstOrFail();

        $catId = (int) $cat->id;
        $catName = strtolower(trim($cat->name));
        $catKey = strtolower(trim($cat->circle_key ?? ''));

        // Get circles belonging to this category
        $circles = Circle::query()
            ->with(['founderUser:id,first_name,last_name,display_name,email'])
            ->withCount(['members' => fn ($q) => $q->where('status', 'approved')->whereNull('deleted_at')])
            ->whereNull('deleted_at')
            ->where(function ($q) use ($catId, $catName, $catKey) {
                $q->where('template_id', $catId);
                if ($catName !== '') {
                    $q->orWhereRaw("EXISTS (SELECT 1 FROM jsonb_array_elements_text(industry_tags::jsonb) AS tag WHERE LOWER(tag) = ?)", [$catName]);
                }
                if ($catKey !== '') {
                    $q->orWhereRaw("EXISTS (SELECT 1 FROM jsonb_array_elements_text(industry_tags::jsonb) AS tag WHERE LOWER(tag) = ?)", [$catKey]);
                }
            })
            ->orderBy('name')
            ->get();

        // Level 4 categories under this Level 1
        $level4Categories = CircleCategoryLevel4::query()
            ->where('circle_category_id', $catId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'level2_id', 'level3_id']);

        $transformedCircles = $circles->map(function (Circle $c) {
            $founder = $c->founderUser;
            $founderName = $founder ? (trim($founder->display_name ?: "{$founder->first_name} {$founder->last_name}")) : 'Unassigned';

            return [
                'id' => (string) $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'description' => $c->description ?? '',
                'city' => $c->city ?? 'Global',
                'status' => $c->status ?? 'active',
                'meeting_mode' => $c->meeting_mode ?? 'online',
                'meeting_frequency' => $c->meeting_frequency ?? 'monthly',
                'member_count' => (int) $c->members_count,
                'founder_name' => $founderName,
                'health_score' => (int) ($c->health_score ?? 85),
            ];
        });

        return $this->success([
            'category' => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'circle_key' => $cat->circle_key,
                'level' => 1,
                'sort_order' => $cat->sort_order,
                'is_active' => (bool) $cat->is_active,
                'total_subcategories' => $level4Categories->count(),
            ],
            'circles' => $transformedCircles,
            'subcategories' => $level4Categories,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $cat = CircleCategory::where('level', 1)->findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', "unique:circle_categories,slug,{$cat->id}"],
            'circle_key' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $payload = [
            'name' => trim($data['name']),
            'is_active' => $request->boolean('is_active', $cat->is_active),
        ];

        if (isset($data['slug']) && filled($data['slug'])) {
            $payload['slug'] = Str::slug($data['slug']);
        }
        if (isset($data['circle_key']) && filled($data['circle_key'])) {
            $payload['circle_key'] = Str::slug($data['circle_key'], '_');
        }
        if (isset($data['sort_order'])) {
            $payload['sort_order'] = (int) $data['sort_order'];
        }

        $cat->update($payload);

        return $this->success([
            'id' => $cat->id,
            'name' => $cat->name,
            'slug' => $cat->slug,
            'circle_key' => $cat->circle_key,
            'level' => 1,
            'sort_order' => $cat->sort_order,
            'is_active' => (bool) $cat->is_active,
        ], "Category '{$cat->name}' updated successfully.");
    }

    public function destroy(string $id): JsonResponse
    {
        $cat = CircleCategory::where('level', 1)->findOrFail($id);
        $name = $cat->name;
        $cat->delete();

        return $this->success(['deleted' => true], "Category '{$name}' deleted successfully.");
    }
}
