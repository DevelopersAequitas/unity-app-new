<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Categories\BulkDestroyCategoryRequest;
use App\Http\Requests\Admin\Categories\StoreCategoryRequest;
use App\Http\Requests\Admin\Categories\UpdateCategoryRequest;
use App\Imports\CategoriesImport;
use App\Models\CircleCategory;
use App\Models\CircleCategoryLevel2;
use App\Models\CircleCategoryLevel3;
use App\Models\CircleCategoryLevel4;
use App\Services\Admin\CategoryBulkActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $categories = CircleCategory::query()
            ->where('level', 1)
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'ILIKE', '%'.$search.'%');
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.categories.index', [
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.create', [
            'category' => new CircleCategory([
                'level' => 1,
                'is_active' => true,
                'sort_order' => 0,
            ]),
        ]);
    }

    public function show(Request $request, CircleCategory $category): View|JsonResponse
    {
        abort_unless((int) $category->level === 1 && $category->is_active, 404);

        $level2Table = (new CircleCategoryLevel2)->getTable();
        $hasLevel2Table = Schema::hasTable($level2Table);
        $level2Categories = $hasLevel2Table ? CircleCategoryLevel2::query()
            ->where('circle_category_id', $category->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get() : collect();

        $level3Table = (new CircleCategoryLevel3)->getTable();
        $hasLevel3Table = Schema::hasTable($level3Table);
        $level3Categories = $hasLevel3Table ? CircleCategoryLevel3::query()
            ->where('circle_category_id', $category->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get() : collect();

        $level4Table = (new CircleCategoryLevel4)->getTable();
        $hasLevel2Col = Schema::hasColumn($level4Table, 'level2_id');
        $hasLevel2AltCol = Schema::hasColumn($level4Table, 'circle_category_level2_id');
        $hasLevel3Col = Schema::hasColumn($level4Table, 'level3_id');
        $hasLevel3AltCol = Schema::hasColumn($level4Table, 'circle_category_level3_id');

        // Retrieve only hierarchical Level 4 categories (attached to Level 2 or Level 3)
        $hierarchicalLevel4 = collect();
        if ($hasLevel2Col || $hasLevel2AltCol || $hasLevel3Col || $hasLevel3AltCol) {
            $hierarchicalLevel4 = CircleCategoryLevel4::query()
                ->where('circle_category_id', $category->id)
                ->where('is_active', true)
                ->where(function ($query) use ($hasLevel2Col, $hasLevel2AltCol, $hasLevel3Col, $hasLevel3AltCol) {
                    $hasAny = false;
                    if ($hasLevel2Col) {
                        $query->whereNotNull('level2_id');
                        $hasAny = true;
                    }
                    if ($hasLevel2AltCol) {
                        $hasAny ? $query->orWhereNotNull('circle_category_level2_id') : $query->whereNotNull('circle_category_level2_id');
                        $hasAny = true;
                    }
                    if ($hasLevel3Col) {
                        $hasAny ? $query->orWhereNotNull('level3_id') : $query->whereNotNull('level3_id');
                        $hasAny = true;
                    }
                    if ($hasLevel3AltCol) {
                        $hasAny ? $query->orWhereNotNull('circle_category_level3_id') : $query->whereNotNull('circle_category_level3_id');
                        $hasAny = true;
                    }
                    if (! $hasAny) {
                        $query->whereRaw('1 = 0');
                    }
                })
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        $level3ByLevel2 = [];
        foreach ($level3Categories as $level3Category) {
            $level2Id = $level3Category->level2_id ?? $level3Category->circle_category_level2_id ?? null;
            if ($level2Id === null) {
                continue;
            }

            $level3ByLevel2[$level2Id][] = $level3Category;
        }

        $level4ByLevel3 = [];
        $level4ByLevel2Direct = [];

        foreach ($hierarchicalLevel4 as $hLevel4) {
            $level3Id = $hLevel4->level3_id ?? $hLevel4->circle_category_level3_id ?? null;
            $level2Id = $hLevel4->level2_id ?? $hLevel4->circle_category_level2_id ?? null;

            if ($level3Id !== null) {
                $level4ByLevel3[$level3Id][] = $hLevel4;
            } elseif ($level2Id !== null) {
                $level4ByLevel2Direct[$level2Id][] = $hLevel4;
            }
        }

        $children = [];
        foreach ($level2Categories as $level2Category) {
            $level3Children = $level3ByLevel2[$level2Category->id] ?? [];
            $level2DirectL4 = $level4ByLevel2Direct[$level2Category->id] ?? [];

            $children[] = [
                'category' => $level2Category,
                'direct_level4' => $level2DirectL4,
                'children' => collect($level3Children)->map(function ($level3Category) use ($level4ByLevel3) {
                    return [
                        'category' => $level3Category,
                        'children' => $level4ByLevel3[$level3Category->id] ?? [],
                    ];
                })->all(),
            ];
        }

        // Direct Level 4 Categories query (without Level 2/3 parents)
        $directL4BaseQuery = CircleCategoryLevel4::query()
            ->where('circle_category_id', $category->id)
            ->where('is_active', true)
            ->where(function ($query) use ($hasLevel2Col, $hasLevel2AltCol, $hasLevel3Col, $hasLevel3AltCol) {
                if ($hasLevel2Col) {
                    $query->whereNull('level2_id');
                }
                if ($hasLevel2AltCol) {
                    $query->whereNull('circle_category_level2_id');
                }
                if ($hasLevel3Col) {
                    $query->whereNull('level3_id');
                }
                if ($hasLevel3AltCol) {
                    $query->whereNull('circle_category_level3_id');
                }
            });

        $directLevel4Total = (clone $directL4BaseQuery)->count();

        $search = trim((string) $request->input('search', ''));
        $directL4Query = clone $directL4BaseQuery;
        if ($search !== '') {
            $operator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            $directL4Query->where('name', $operator, '%'.$search.'%');
        }

        $perPage = (int) $request->input('per_page', 50);
        if (! in_array($perPage, [15, 25, 50, 100, 200], true)) {
            $perPage = 50;
        }

        $directLevel4Categories = $directL4Query
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $level2Count = $level2Categories->count();
        $level3Count = $level3Categories->count();
        $level4Count = $directLevel4Total + $hierarchicalLevel4->count();
        $totalChildren = $level2Count + $level3Count + $level4Count;

        if ($request->ajax() || $request->wantsJson() || $request->boolean('ajax')) {
            return response()->json([
                'success' => true,
                'items_html' => view('admin.categories.partials.direct_level4_items', [
                    'directLevel4Categories' => $directLevel4Categories,
                ])->render(),
                'pagination_html' => view('admin.categories.partials.direct_level4_pagination', [
                    'paginator' => $directLevel4Categories,
                ])->render(),
                'total' => $directLevel4Categories->total(),
                'direct_total' => $directLevel4Total,
                'current_page' => $directLevel4Categories->currentPage(),
                'last_page' => $directLevel4Categories->lastPage(),
                'per_page' => $directLevel4Categories->perPage(),
                'from' => $directLevel4Categories->firstItem() ?? 0,
                'to' => $directLevel4Categories->lastItem() ?? 0,
                'search' => $search,
            ]);
        }

        return view('admin.categories.view', [
            'category' => $category,
            'level2Count' => $level2Count,
            'level3Count' => $level3Count,
            'level4Count' => $level4Count,
            'totalChildren' => $totalChildren,
            'children' => $children,
            'directLevel4Categories' => $directLevel4Categories,
            'directLevel4Total' => $directLevel4Total,
            'level2Options' => $level2Categories,
            'level3Options' => $level3Categories,
        ]);
    }

    public function storeLevel2(Request $request, CircleCategory $category): RedirectResponse
    {
        abort_unless((int) $category->level === 1, 404);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('circle_category_level2', 'name')->where(fn ($query) => $query->where('circle_category_id', $category->id)),
            ],
        ]);

        CircleCategoryLevel2::query()->create([
            'circle_category_id' => $category->id,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'is_active' => true,
            'sort_order' => ((int) CircleCategoryLevel2::query()->where('circle_category_id', $category->id)->max('sort_order')) + 1,
        ]);

        return redirect()->route('admin.categories.view', $category)->with('success', "Level 2 category \"{$validated['name']}\" added successfully.");
    }

    public function storeLevel3(Request $request, CircleCategory $category): RedirectResponse
    {
        abort_unless((int) $category->level === 1, 404);

        $validated = $request->validate([
            'level2_id' => [
                'required',
                'integer',
                Rule::exists('circle_category_level2', 'id')->where(fn ($query) => $query->where('circle_category_id', $category->id)),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('circle_category_level3', 'name')->where(fn ($query) => $query->where('level2_id', (int) $request->input('level2_id'))),
            ],
        ]);

        CircleCategoryLevel3::query()->create([
            'circle_category_id' => $category->id,
            'level2_id' => (int) $validated['level2_id'],
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'is_active' => true,
            'sort_order' => ((int) CircleCategoryLevel3::query()->where('level2_id', (int) $validated['level2_id'])->max('sort_order')) + 1,
        ]);

        return redirect()->route('admin.categories.view', $category)->with('success', "Level 3 category \"{$validated['name']}\" added successfully.");
    }

    public function storeLevel4(Request $request, CircleCategory $category): RedirectResponse
    {
        abort_unless((int) $category->level === 1, 404);

        $validated = $request->validate([
            'level2_id' => [
                'nullable',
                'integer',
                Rule::exists('circle_category_level2', 'id')->where(fn ($query) => $query->where('circle_category_id', $category->id)),
            ],
            'level3_id' => [
                'nullable',
                'integer',
                Rule::exists('circle_category_level3', 'id')->where(function ($query) use ($category, $request) {
                    $query->where('circle_category_id', $category->id);
                    if ($request->filled('level2_id')) {
                        $query->where('level2_id', (int) $request->input('level2_id'));
                    }

                    return $query;
                }),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('circle_category_level4', 'name')->where(function ($query) use ($category, $request) {
                    $query->where('circle_category_id', $category->id);
                    if ($request->filled('level3_id')) {
                        $query->where('level3_id', (int) $request->input('level3_id'));
                    } else {
                        $query->whereNull('level3_id');
                        if ($request->filled('level2_id')) {
                            $query->where('level2_id', (int) $request->input('level2_id'));
                        } else {
                            $query->whereNull('level2_id');
                        }
                    }

                    return $query;
                }),
            ],
        ]);

        $level2Id = $request->filled('level2_id') ? (int) $validated['level2_id'] : null;
        $level3Id = $request->filled('level3_id') ? (int) $validated['level3_id'] : null;

        if ($level3Id !== null && $level2Id === null) {
            $level2Id = CircleCategoryLevel3::query()->where('id', $level3Id)->value('level2_id');
        }

        $sortQuery = CircleCategoryLevel4::query()->where('circle_category_id', $category->id);
        if ($level3Id !== null) {
            $sortQuery->where('level3_id', $level3Id);
        } elseif ($level2Id !== null) {
            $sortQuery->where('level2_id', $level2Id)->whereNull('level3_id');
        } else {
            $sortQuery->whereNull('level2_id')->whereNull('level3_id');
        }

        CircleCategoryLevel4::query()->create([
            'circle_category_id' => $category->id,
            'level2_id' => $level2Id,
            'level3_id' => $level3Id,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'is_active' => true,
            'sort_order' => ((int) $sortQuery->max('sort_order')) + 1,
        ]);

        return redirect()->route('admin.categories.view', $category)->with('success', "Level 4 category \"{$validated['name']}\" saved successfully.");
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $payload['level'] = 1;
        $payload['is_active'] = $request->boolean('is_active');

        CircleCategory::query()->create($payload);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function edit(CircleCategory $category): View
    {
        return view('admin.categories.edit', [
            'category' => $category,
        ]);
    }

    public function update(UpdateCategoryRequest $request, CircleCategory $category): RedirectResponse
    {
        $payload = $request->validated();
        $payload['level'] = 1;
        $payload['is_active'] = $request->boolean('is_active');

        $category->update($payload);

        return redirect()
            ->route('admin.categories.view', $category)
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(CircleCategory $category): RedirectResponse
    {
        try {
            if ((int) $category->level !== 1) {
                return redirect()
                    ->route('admin.categories.index')
                    ->with('error', 'Category not found.');
            }

            if (! $category->is_active) {
                return redirect()
                    ->route('admin.categories.index')
                    ->with('error', 'Category is already inactive.');
            }

            DB::transaction(function () use ($category): void {
                $now = now();

                DB::table('circle_category_level4')
                    ->where('circle_category_id', $category->id)
                    ->update([
                        'is_active' => false,
                        'updated_at' => $now,
                    ]);

                DB::table('circle_category_level3')
                    ->where('circle_category_id', $category->id)
                    ->update([
                        'is_active' => false,
                        'updated_at' => $now,
                    ]);

                DB::table('circle_category_level2')
                    ->where('circle_category_id', $category->id)
                    ->update([
                        'is_active' => false,
                        'updated_at' => $now,
                    ]);

                $deactivatedMain = DB::table('circle_categories')
                    ->where('id', $category->id)
                    ->update([
                        'is_active' => false,
                        'updated_at' => $now,
                    ]);

                if ($deactivatedMain !== 1) {
                    throw new \RuntimeException('Circle category deactivation did not affect any rows.');
                }
            });

            return redirect()
                ->route('admin.categories.index')
                ->with('success', 'Category deactivated successfully.');
        } catch (\Throwable $e) {
            Log::error('admin.circle_category.delete_failed', [
                'category_id' => (int) $category->id,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.categories.index')
                ->with('error', 'Something went wrong: '.$e->getMessage());
        }
    }

    public function export(Request $request)
    {
        try {
            $categoryId = $request->query('category_id');

            if ($categoryId) {
                $category = CircleCategory::query()->findOrFail($categoryId);

                $level2Categories = CircleCategoryLevel2::query()
                    ->where('circle_category_id', $category->id)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get();

                $level3Categories = CircleCategoryLevel3::query()
                    ->where('circle_category_id', $category->id)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get();

                $level4Categories = CircleCategoryLevel4::query()
                    ->where('circle_category_id', $category->id)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get();

                $level2Map = $level2Categories->keyBy('id');
                $level3Map = $level3Categories->keyBy('id');

                $fileName = Str::slug($category->name).'_categories_'.now()->format('Ymd_His').'.csv';

                return response()->streamDownload(
                    function () use ($category, $level2Categories, $level3Categories, $level4Categories, $level2Map, $level3Map): void {
                        $handle = fopen('php://output', 'w');

                        if ($handle === false) {
                            throw new \RuntimeException('Could not open output stream for CSV export.');
                        }

                        fwrite($handle, "\xEF\xBB\xBF");
                        fputcsv($handle, ['ID', 'Level', 'Category Name', 'Parent Name', 'Slug', 'Sort Order', 'Is Active']);

                        // Level 1 (Main)
                        fputcsv($handle, [
                            $category->id,
                            'Level 1',
                            (string) ($category->name ?? ''),
                            '—',
                            (string) ($category->slug ?? ''),
                            (string) ($category->sort_order ?? ''),
                            $category->is_active ? 'true' : 'false',
                        ]);

                        // Level 2
                        foreach ($level2Categories as $l2) {
                            fputcsv($handle, [
                                $l2->id,
                                'Level 2',
                                (string) ($l2->name ?? ''),
                                (string) ($category->name ?? ''),
                                (string) ($l2->slug ?? ''),
                                (string) ($l2->sort_order ?? ''),
                                $l2->is_active ? 'true' : 'false',
                            ]);
                        }

                        // Level 3
                        foreach ($level3Categories as $l3) {
                            $parentL2Id = $l3->level2_id ?? $l3->circle_category_level2_id ?? null;
                            $parentL2 = $parentL2Id ? $level2Map->get($parentL2Id) : null;
                            fputcsv($handle, [
                                $l3->id,
                                'Level 3',
                                (string) ($l3->name ?? ''),
                                (string) ($parentL2 ? $parentL2->name : $category->name),
                                (string) ($l3->slug ?? ''),
                                (string) ($l3->sort_order ?? ''),
                                $l3->is_active ? 'true' : 'false',
                            ]);
                        }

                        // Level 4
                        foreach ($level4Categories as $l4) {
                            $parentL3Id = $l4->level3_id ?? $l4->circle_category_level3_id ?? null;
                            $parentL2Id = $l4->level2_id ?? $l4->circle_category_level2_id ?? null;
                            $parentL3 = $parentL3Id ? $level3Map->get($parentL3Id) : null;
                            $parentL2 = $parentL2Id ? $level2Map->get($parentL2Id) : null;
                            $parentName = $parentL3 ? $parentL3->name : ($parentL2 ? $parentL2->name : $category->name);

                            fputcsv($handle, [
                                $l4->id,
                                'Level 4',
                                (string) ($l4->name ?? ''),
                                (string) $parentName,
                                (string) ($l4->slug ?? ''),
                                (string) ($l4->sort_order ?? ''),
                                $l4->is_active ? 'true' : 'false',
                            ]);
                        }

                        fclose($handle);
                    },
                    $fileName,
                    [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]
                );
            }

            $search = trim((string) $request->query('q', ''));

            $categories = CircleCategory::query()
                ->select([
                    'id',
                    'name',
                    'slug',
                    'circle_key',
                    'level',
                    'sort_order',
                    'is_active',
                    'created_at',
                    'updated_at',
                ])
                ->where('level', 1)
                ->where('is_active', true)
                ->when($search !== '', function ($query) use ($search) {
                    $query->where('name', 'ILIKE', '%'.$search.'%');
                })
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            return response()->streamDownload(
                function () use ($categories): void {
                    $handle = fopen('php://output', 'w');

                    if ($handle === false) {
                        throw new \RuntimeException('Could not open output stream for CSV export.');
                    }

                    fwrite($handle, "\xEF\xBB\xBF");
                    fputcsv($handle, ['ID', 'Name', 'Slug', 'Circle Key', 'Level', 'Sort Order', 'Is Active', 'Created At', 'Updated At']);

                    foreach ($categories as $category) {
                        fputcsv($handle, [
                            $category->id,
                            (string) ($category->name ?? ''),
                            (string) ($category->slug ?? ''),
                            (string) ($category->circle_key ?? ''),
                            (string) ($category->level ?? ''),
                            (string) ($category->sort_order ?? ''),
                            $category->is_active ? 'true' : 'false',
                            (string) ($category->created_at ?? ''),
                            (string) ($category->updated_at ?? ''),
                        ]);
                    }

                    fclose($handle);
                },
                'circle_categories.csv',
                [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                ]
            );
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', 'Unable to export categories: '.$e->getMessage());
        }
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xlsx',
        ]);

        try {
            $result = (new CategoriesImport)->import($request->file('file'));
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', 'Unable to import categories: '.$e->getMessage());
        }

        return redirect()
            ->back()
            ->with('success', "Categories import completed. Imported: {$result['imported_count']}, Skipped duplicates: {$result['skipped_duplicate_count']}, Skipped empty: {$result['skipped_empty_count']}")
            ->with('imported_count', $result['imported_count'])
            ->with('skipped_duplicate_count', $result['skipped_duplicate_count'])
            ->with('skipped_empty_count', $result['skipped_empty_count']);
    }

    public function updateLevel2(Request $request, CircleCategoryLevel2 $level2): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('circle_category_level2', 'name')
                    ->where(fn ($query) => $query->where('circle_category_id', $level2->circle_category_id))
                    ->ignore($level2->id),
            ],
        ]);

        $level2->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', "Level 2 category \"{$validated['name']}\" updated successfully.");
    }

    public function updateLevel3(Request $request, CircleCategoryLevel3 $level3): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('circle_category_level3', 'name')
                    ->where(fn ($query) => $query->where('level2_id', $level3->level2_id))
                    ->ignore($level3->id),
            ],
        ]);

        $level3->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', "Level 3 category \"{$validated['name']}\" updated successfully.");
    }

    public function updateLevel4(Request $request, CircleCategoryLevel4 $level4): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('circle_category_level4', 'name')
                    ->where(function ($query) use ($level4) {
                        $query->where('circle_category_id', $level4->circle_category_id);
                        if ($level4->level3_id !== null) {
                            $query->where('level3_id', $level4->level3_id);
                        } else {
                            $query->whereNull('level3_id');
                            if ($level4->level2_id !== null) {
                                $query->where('level2_id', $level4->level2_id);
                            } else {
                                $query->whereNull('level2_id');
                            }
                        }

                        return $query;
                    })
                    ->ignore($level4->id),
            ],
        ]);

        $level4->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', "Level 4 category \"{$validated['name']}\" updated successfully.");
    }

    public function destroyLevel2(CircleCategoryLevel2 $level2): RedirectResponse
    {
        try {
            DB::transaction(function () use ($level2): void {
                $now = now();

                DB::table('circle_category_level4')
                    ->where('level2_id', $level2->id)
                    ->update([
                        'is_active' => false,
                        'updated_at' => $now,
                    ]);

                DB::table('circle_category_level3')
                    ->where('level2_id', $level2->id)
                    ->update([
                        'is_active' => false,
                        'updated_at' => $now,
                    ]);

                $level2->update([
                    'is_active' => false,
                    'updated_at' => $now,
                ]);
            });

            return redirect()
                ->back()
                ->with('success', 'Level 2 category and its subcategories deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('admin.circle_category.level2_delete_failed', [
                'level2_id' => (int) $level2->id,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Something went wrong: '.$e->getMessage());
        }
    }

    public function destroyLevel3(CircleCategoryLevel3 $level3): RedirectResponse
    {
        try {
            DB::transaction(function () use ($level3): void {
                $now = now();

                DB::table('circle_category_level4')
                    ->where('level3_id', $level3->id)
                    ->update([
                        'is_active' => false,
                        'updated_at' => $now,
                    ]);

                $level3->update([
                    'is_active' => false,
                    'updated_at' => $now,
                ]);
            });

            return redirect()
                ->back()
                ->with('success', 'Level 3 category and its subcategories deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('admin.circle_category.level3_delete_failed', [
                'level3_id' => (int) $level3->id,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Something went wrong: '.$e->getMessage());
        }
    }

    public function destroyLevel4(CircleCategoryLevel4 $level4): RedirectResponse
    {
        try {
            $level4->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

            return redirect()
                ->back()
                ->with('success', 'Level 4 category deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('admin.circle_category.level4_delete_failed', [
                'level4_id' => (int) $level4->id,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Something went wrong: '.$e->getMessage());
        }
    }

    public function bulkDestroy(BulkDestroyCategoryRequest $request, CircleCategory $category, CategoryBulkActionService $bulkActionService): RedirectResponse
    {
        $level2Ids = array_filter(array_map('intval', (array) $request->input('level2_ids', [])));
        $level3Ids = array_filter(array_map('intval', (array) $request->input('level3_ids', [])));
        $level4Ids = array_filter(array_map('intval', (array) $request->input('level4_ids', [])));

        if (empty($level2Ids) && empty($level3Ids) && empty($level4Ids)) {
            return redirect()
                ->route('admin.categories.view', $category)
                ->with('error', 'No categories were selected for deletion.');
        }

        try {
            $result = $bulkActionService->bulkDestroyChildren($category, $level2Ids, $level3Ids, $level4Ids);
            $totalDeleted = $result['total_deleted'];

            return redirect()
                ->route('admin.categories.view', $category)
                ->with('success', "{$totalDeleted} category item(s) deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('admin.circle_category.bulk_destroy_failed', [
                'category_id' => (int) $category->id,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.categories.view', $category)
                ->with('error', 'Something went wrong while deleting categories: '.$e->getMessage());
        }
    }
}
