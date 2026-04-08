<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Categories\StoreCategoryRequest;
use App\Http\Requests\Admin\Categories\UpdateCategoryRequest;
use App\Imports\CategoriesImport;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $categories = Category::query()
            ->select('categories.*')
            ->when(
                Schema::hasTable('circle_categories') && Schema::hasColumn('circle_categories', 'name'),
                function ($query) {
                    $query->leftJoin('circle_categories as cc', DB::raw('LOWER(cc.name)'), '=', DB::raw('LOWER(categories.category_name)'))
                        ->addSelect(DB::raw('cc.id as circle_category_id'));
                }
            )
            ->when($this->hierarchyColumnsAvailable(), function ($query) {
                $query->whereNull('categories.parent_id')
                    ->where('categories.level', 1)
                    ->when(Schema::hasColumn('categories', 'is_active'), fn ($q) => $q->where('categories.is_active', true));
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('categories.category_name', 'ILIKE', '%' . $search . '%')
                        ->orWhere('categories.sector', 'ILIKE', '%' . $search . '%');
                });
            })
            ->orderBy('categories.id')
            ->paginate(20)
            ->appends($request->query());

        return view('admin.categories.index', [
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    public function showHierarchy(Category $category): View
    {
        abort_unless($this->isMainCategory($category), 404);

        $level2Categories = $this->childrenQuery($category->id)->get();
        $level2Categories->each(function (Category $item): void {
            $item->setAttribute('circle_category_id', $this->resolveCircleCategoryParentId($item->id));
        });
        $level2Ids = $level2Categories->pluck('id');

        $level3Count = $level2Ids->isEmpty()
            ? 0
            : Category::query()->whereIn('parent_id', $level2Ids)->count();

        $level3Ids = $level2Ids->isEmpty()
            ? collect()
            : Category::query()->whereIn('parent_id', $level2Ids)->pluck('id');

        $level4Count = $level3Ids->isEmpty()
            ? 0
            : Category::query()->whereIn('parent_id', $level3Ids)->count();

        return view('admin.categories.view', [
            'category' => $category,
            'level2Categories' => $level2Categories,
            'counts' => [
                'level2' => $level2Categories->count(),
                'level3' => $level3Count,
                'level4' => $level4Count,
            ],
        ]);
    }

    public function children(Category $category, Request $request): JsonResponse
    {
        $items = $this->childrenQuery($category->id)
            ->when($request->filled('level'), fn ($query) => $query->where('level', (int) $request->query('level')))
            ->get()
            ->map(fn (Category $item) => [
                'id' => $item->id,
                'name' => $item->category_name,
                'level' => $item->level,
                'sector' => $item->sector,
                'remarks' => $item->remarks,
                'parent_name' => $category->category_name,
                'circle_category_id' => $this->resolveCircleCategoryParentId($item->id),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => $items,
        ]);
    }

    public function storeHierarchy(Request $request, Category $category): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'category_name' => ['required', 'string', 'max:255'],
            'sector' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'parent_id' => ['required', 'integer', 'exists:categories,id'],
            'circle_parent_id' => ['nullable', 'integer'],
            'level' => ['required', 'integer', 'in:2,3,4'],
        ]);

        $level = (int) $validated['level'];
        $parentId = (int) $validated['parent_id'];

        if ($level === 2 && $parentId !== (int) $category->id) {
            return $this->hierarchyErrorResponse($request, 'Invalid Level 2 parent selection.');
        }

        if ($level === 3) {
            $parent = Category::query()->find($parentId);
            if (! $parent || (int) ($parent->level ?? 0) !== 2) {
                return $this->hierarchyErrorResponse($request, 'Please select a Level 2 category first.');
            }
        }

        if ($level === 4) {
            $parent = Category::query()->find($parentId);
            if (! $parent || (int) ($parent->level ?? 0) !== 3) {
                return $this->hierarchyErrorResponse($request, 'Please select a Level 3 category first.');
            }
        }

        $payload = $this->filterCategoryPayload([
            'category_name' => $validated['category_name'],
            'sector' => $validated['sector'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'parent_id' => $parentId,
            'level' => $level,
            'is_active' => true,
        ]);

        $circleParentId = null;
        if (Schema::hasTable('circle_categories')) {
            $circleParentId = $this->resolveCircleParentFromRequest(
                (int) $validated['level'],
                $request->integer('circle_parent_id'),
                $parentId
            );

            if ($level > 1 && $circleParentId === null) {
                return $this->hierarchyErrorResponse($request, 'Selected parent category was not found in circle_categories.');
            }
        }

        try {
            $createdCategory = null;
            $circleCategoryId = null;

            DB::transaction(function () use ($payload, $circleParentId, $level, &$createdCategory, &$circleCategoryId): void {
                if (Schema::hasTable('categories')) {
                    $createdCategory = Category::query()->create($payload);
                }

                if (Schema::hasTable('circle_categories')) {
                    $circleCategoryId = $this->insertIntoCircleCategories($payload, $circleParentId, $level);
                }
            });

            $responseData = [
                'id' => $createdCategory?->id ?? $circleCategoryId,
                'name' => $payload['category_name'],
                'level' => $level,
                'parent_id' => $parentId,
                'circle_parent_id' => $circleParentId,
                'circle_category_id' => $circleCategoryId,
                'sector' => $payload['sector'] ?? null,
                'remarks' => $payload['remarks'] ?? null,
            ];

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Category created successfully.',
                    'data' => $responseData,
                ]);
            }

            return redirect()
                ->route('admin.categories.view', $category)
                ->with('success', 'Category created successfully.');
        } catch (\Throwable $e) {
            Log::error('Hierarchy category create failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->hierarchyErrorResponse($request, $e->getMessage());
        }
    }

    public function create(): View
    {
        return view('admin.categories.create', [
            'category' => new Category(),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $payload = $this->filterCategoryPayload($request->validated());
        $payload = $this->applyMainCategoryDefaults($payload);

        try {
            DB::transaction(function () use ($payload): void {
                $created = false;

                if (Schema::hasTable('categories')) {
                    Category::query()->create($payload);
                    $created = true;
                }

                if (Schema::hasTable('circle_categories')) {
                    $this->insertIntoCircleCategories($payload);
                    $created = true;
                }

                if (! $created) {
                    throw new \RuntimeException('No category table found for insert.');
                }
            });
        } catch (\Throwable $e) {
            Log::error('Category create failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['category_name' => 'Unable to create category right now. Please try again.']);
        }

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', [
            'category' => $category,
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $payload = $this->filterCategoryPayload($request->validated());
        $payload = $this->preserveMainCategoryState($category, $payload);

        $category->update($payload);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        try {
            if (
                $category->circleMappings()->exists() ||
                (
                    DB::getSchemaBuilder()->hasColumn('event_galleries', 'circle_category_id') &&
                    DB::table('event_galleries')->where('circle_category_id', $category->id)->exists()
                )
            ) {
                return redirect()
                    ->route('admin.categories.index')
                    ->with('error', 'This category is in use and cannot be deleted.');
            }

            $category->delete();

            return redirect()
                ->route('admin.categories.index')
                ->with('success', 'Category deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.categories.index')
                ->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    public function export(Request $request)
    {
        try {
            $search = trim((string) $request->query('q', ''));

            $categories = Category::query()
                ->select([
                    'id',
                    'category_name',
                    'sector',
                    'remarks',
                    'created_at',
                    'updated_at',
                ])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery
                            ->where('category_name', 'ILIKE', '%' . $search . '%')
                            ->orWhere('sector', 'ILIKE', '%' . $search . '%');
                    });
                })
                ->orderBy('id')
                ->get();

            return response()->streamDownload(
                function () use ($categories): void {
                    $handle = fopen('php://output', 'w');

                    if ($handle === false) {
                        throw new \RuntimeException('Could not open output stream for CSV export.');
                    }

                    fwrite($handle, "\xEF\xBB\xBF");
                    fputcsv($handle, ['ID', 'Category Name', 'Sector', 'Remarks', 'Created At', 'Updated At']);

                    foreach ($categories as $category) {
                        fputcsv($handle, [
                            $category->id,
                            (string) ($category->category_name ?? ''),
                            (string) ($category->sector ?? ''),
                            (string) ($category->remarks ?? ''),
                            (string) ($category->created_at ?? ''),
                            (string) ($category->updated_at ?? ''),
                        ]);
                    }

                    fclose($handle);
                },
                'categories.csv',
                [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                ]
            );
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', 'Unable to export categories: ' . $e->getMessage());
        }
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xlsx',
        ]);

        try {
            $result = (new CategoriesImport())->import($request->file('file'));
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', 'Unable to import categories: ' . $e->getMessage());
        }

        return redirect()
            ->back()
            ->with('success', "Categories import completed. Imported: {$result['imported_count']}, Skipped duplicates: {$result['skipped_duplicate_count']}, Skipped empty: {$result['skipped_empty_count']}")
            ->with('imported_count', $result['imported_count'])
            ->with('skipped_duplicate_count', $result['skipped_duplicate_count'])
            ->with('skipped_empty_count', $result['skipped_empty_count']);
    }

    private function filterCategoryPayload(array $payload): array
    {
        return collect($payload)
            ->filter(fn ($value, $key) => Schema::hasColumn('categories', $key))
            ->all();
    }

    private function childrenQuery(int $parentId)
    {
        return Category::query()
            ->where('parent_id', $parentId)
            ->when(Schema::hasColumn('categories', 'is_active'), fn ($query) => $query->where('is_active', true))
            ->orderByRaw(Schema::hasColumn('categories', 'sort_order') ? 'sort_order ASC NULLS LAST' : 'id ASC')
            ->orderBy('category_name');
    }

    private function hierarchyColumnsAvailable(): bool
    {
        return Schema::hasColumn('categories', 'parent_id') && Schema::hasColumn('categories', 'level');
    }

    private function isMainCategory(Category $category): bool
    {
        if (! $this->hierarchyColumnsAvailable()) {
            return true;
        }

        return $category->parent_id === null && (int) $category->level === 1;
    }

    private function applyMainCategoryDefaults(array $payload): array
    {
        if (! $this->hierarchyColumnsAvailable()) {
            return $payload;
        }

        if (! array_key_exists('parent_id', $payload)) {
            $payload['parent_id'] = null;
        }

        if (! array_key_exists('level', $payload) || $payload['level'] === null) {
            $payload['level'] = 1;
        }

        if (Schema::hasColumn('categories', 'is_active') && ! array_key_exists('is_active', $payload)) {
            $payload['is_active'] = true;
        }

        return $payload;
    }

    private function preserveMainCategoryState(Category $category, array $payload): array
    {
        if (! $this->isMainCategory($category)) {
            return $payload;
        }

        if (array_key_exists('parent_id', $payload) || array_key_exists('level', $payload)) {
            return $payload;
        }

        return array_merge($payload, [
            'parent_id' => null,
            'level' => 1,
        ]);
    }

    private function insertIntoCircleCategories(array $payload, ?int $parentId = null, ?int $level = null): int
    {
        $name = trim((string) ($payload['category_name'] ?? $payload['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('Category name is required for circle_categories insert.');
        }

        $data = [
            'name' => $name,
        ];

        if (Schema::hasColumn('circle_categories', 'parent_id')) {
            $data['parent_id'] = $parentId;
        }

        if (Schema::hasColumn('circle_categories', 'level')) {
            $data['level'] = $level ?? 1;
        }

        if (Schema::hasColumn('circle_categories', 'is_active')) {
            $data['is_active'] = true;
        }

        if (Schema::hasColumn('circle_categories', 'slug')) {
            $data['slug'] = $this->nextUniqueValue('circle_categories', 'slug', Str::slug($name));
        }

        if (Schema::hasColumn('circle_categories', 'circle_key')) {
            $baseKey = Str::upper(Str::snake($name));
            $data['circle_key'] = $this->nextUniqueValue('circle_categories', 'circle_key', $baseKey, '_');
        }

        if (Schema::hasColumn('circle_categories', 'sort_order')) {
            $nextOrder = ((int) DB::table('circle_categories')->max('sort_order')) + 1;
            $data['sort_order'] = $nextOrder;
        }

        if (Schema::hasColumn('circle_categories', 'sector') && array_key_exists('sector', $payload)) {
            $data['sector'] = $payload['sector'];
        }

        if (Schema::hasColumn('circle_categories', 'remarks') && array_key_exists('remarks', $payload)) {
            $data['remarks'] = $payload['remarks'];
        }

        if (Schema::hasColumn('circle_categories', 'created_at')) {
            $data['created_at'] = now();
        }

        if (Schema::hasColumn('circle_categories', 'updated_at')) {
            $data['updated_at'] = now();
        }

        return (int) DB::table('circle_categories')->insertGetId($data);
    }

    private function nextUniqueValue(string $table, string $column, string $baseValue, string $separator = '-'): string
    {
        $value = trim($baseValue);
        if ($value === '') {
            $value = 'category';
        }

        $candidate = $value;
        $suffix = 1;

        while (DB::table($table)->where($column, $candidate)->exists()) {
            $candidate = $value . $separator . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function resolveCircleCategoryParentId(?int $categoriesParentId): ?int
    {
        if ($categoriesParentId === null || ! Schema::hasTable('circle_categories')) {
            return null;
        }

        $parent = Category::query()->find($categoriesParentId);
        if (! $parent) {
            return null;
        }

        return DB::table('circle_categories')
            ->whereRaw('LOWER(name) = LOWER(?)', [$parent->category_name])
            ->value('id');
    }

    private function resolveCircleParentFromRequest(int $level, ?int $circleParentId, int $categoryParentId): ?int
    {
        if (! Schema::hasTable('circle_categories') || $level <= 1) {
            return null;
        }

        if ($circleParentId !== null) {
            $row = DB::table('circle_categories')->where('id', $circleParentId)->first();
            if ($row && ((int) ($row->level ?? 0) === ($level - 1) || $row->level === null)) {
                return (int) $row->id;
            }
        }

        return $this->resolveCircleCategoryParentId($categoryParentId);
    }

    private function hierarchyErrorResponse(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => ['category_name' => [$message]],
            ], 422);
        }

        return redirect()->back()->withErrors(['category_name' => $message]);
    }
}
