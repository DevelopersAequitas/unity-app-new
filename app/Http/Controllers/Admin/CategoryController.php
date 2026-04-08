<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Categories\StoreCategoryRequest;
use App\Http\Requests\Admin\Categories\UpdateCategoryRequest;
use App\Imports\CategoriesImport;
use App\Models\CircleCategory;
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

        $categories = CircleCategory::query()
            ->when($this->hierarchyColumnsAvailable(), function ($query) {
                $query->whereNull('parent_id')
                    ->where('level', 1)
                    ->when(Schema::hasColumn('circle_categories', 'is_active'), fn ($q) => $q->where('is_active', true));
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('name', 'ILIKE', '%' . $search . '%')
                        ->orWhere('remarks', 'ILIKE', '%' . $search . '%');
                });
            })
            ->orderBy('id')
            ->paginate(20)
            ->appends($request->query());

        return view('admin.categories.index', [
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    public function showHierarchy(CircleCategory $category): View
    {
        abort_unless($this->isMainCategory($category), 404);

        $level2Categories = $this->childrenQuery($category->id, 2)->get();
        $level2Ids = $level2Categories->pluck('id');

        $level3Count = $level2Ids->isEmpty()
            ? 0
            : $this->applyActiveFilter(
                CircleCategory::query()
                    ->whereIn('parent_id', $level2Ids)
                    ->where('level', 3)
            )->count();

        $level3Ids = $level2Ids->isEmpty()
            ? collect()
            : $this->applyActiveFilter(
                CircleCategory::query()
                    ->whereIn('parent_id', $level2Ids)
                    ->where('level', 3)
            )->pluck('id');

        $level4Count = $level3Ids->isEmpty()
            ? 0
            : $this->applyActiveFilter(
                CircleCategory::query()
                    ->whereIn('parent_id', $level3Ids)
                    ->where('level', 4)
            )->count();

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

    public function children(CircleCategory $category, Request $request): JsonResponse
    {
        $requestedLevel = $request->filled('level')
            ? (int) $request->query('level')
            : $this->resolveExpectedChildLevel($category);

        $items = $this->childrenQuery($category->id)
            ->when($requestedLevel !== null, fn ($query) => $query->where('level', $requestedLevel))
            ->get()
            ->map(fn (CircleCategory $item) => [
                'id' => $item->id,
                'name' => $item->category_name,
                'level' => $item->level,
                'remarks' => $item->remarks,
                'parent_name' => $category->category_name,
                'circle_category_id' => $item->id,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => $items,
        ]);
    }

    public function storeHierarchy(Request $request, CircleCategory $category): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'category_name' => ['required', 'string', 'max:255'],
            'sector' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'parent_id' => ['required', 'integer', 'exists:circle_categories,id'],
            'level' => ['required', 'integer', 'in:2,3,4'],
        ]);

        $level = (int) $validated['level'];
        $parentId = (int) $validated['parent_id'];

        if ($level === 2 && $parentId !== (int) $category->id) {
            return $this->hierarchyErrorResponse($request, 'Invalid Level 2 parent selection.');
        }

        if ($level === 3) {
            $parent = CircleCategory::query()->find($parentId);
            if (! $parent || (int) ($parent->level ?? 0) !== 2) {
                return $this->hierarchyErrorResponse($request, 'Please select a Level 2 category first.');
            }
        }

        if ($level === 4) {
            $parent = CircleCategory::query()->find($parentId);
            if (! $parent || (int) ($parent->level ?? 0) !== 3) {
                return $this->hierarchyErrorResponse($request, 'Please select a Level 3 category first.');
            }
        }

        $payload = $this->filterCategoryPayload([
            'name' => $validated['category_name'],
            'remarks' => $validated['remarks'] ?? null,
            'parent_id' => $parentId,
            'level' => $level,
            'is_active' => true,
        ]);

        try {
            $createdCategory = DB::transaction(function () use ($payload, $level): CircleCategory {
                $payload['slug'] = $this->nextUniqueValue('circle_categories', 'slug', Str::slug((string) ($payload['name'] ?? '')));
                $payload['circle_key'] = $this->nextUniqueValue('circle_categories', 'circle_key', Str::upper(Str::snake((string) ($payload['name'] ?? ''))), '_');
                $payload['sort_order'] = ((int) CircleCategory::query()->where('level', $level)->max('sort_order')) + 1;

                return CircleCategory::query()->create($payload);
            });

            $responseData = [
                'id' => $createdCategory->id,
                'name' => $createdCategory->name,
                'level' => $level,
                'parent_id' => $parentId,
                'circle_parent_id' => $parentId,
                'circle_category_id' => $createdCategory->id,
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
            'category' => new CircleCategory(),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $payload = $request->validate([
            'category_name' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ]);

        try {
            DB::transaction(function () use ($payload): void {
                $name = trim((string) ($payload['category_name'] ?? ''));
                CircleCategory::query()->create([
                    'name' => $name,
                    'parent_id' => null,
                    'level' => 1,
                    'slug' => $this->nextUniqueValue('circle_categories', 'slug', Str::slug($name)),
                    'circle_key' => $this->nextUniqueValue('circle_categories', 'circle_key', Str::upper(Str::snake($name)), '_'),
                    'sort_order' => ((int) CircleCategory::query()->where('level', 1)->max('sort_order')) + 1,
                    'is_active' => true,
                    'remarks' => $payload['remarks'] ?? null,
                ]);
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

    public function edit(CircleCategory $category): View
    {
        return view('admin.categories.edit', [
            'category' => $category,
        ]);
    }

    public function update(UpdateCategoryRequest $request, CircleCategory $category): RedirectResponse
    {
        $payload = $request->validate([
            'category_name' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ]);

        $category->update([
            'name' => $payload['category_name'],
            'remarks' => $payload['remarks'] ?? null,
        ]);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(CircleCategory $category): RedirectResponse
    {
        try {
            if (
                DB::getSchemaBuilder()->hasColumn('event_galleries', 'circle_category_id')
                && DB::table('event_galleries')->where('circle_category_id', $category->id)->exists()
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

            $categories = CircleCategory::query()
                ->select([
                    'id',
                    DB::raw('name as category_name'),
                    'remarks',
                    'created_at',
                    'updated_at',
                ])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery
                            ->where('name', 'ILIKE', '%' . $search . '%')
                            ->orWhere('remarks', 'ILIKE', '%' . $search . '%');
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
                            '',
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
            ->filter(fn ($value, $key) => Schema::hasColumn('circle_categories', $key))
            ->all();
    }

    private function childrenQuery(int $parentId, ?int $level = null)
    {
        return $this->applyActiveFilter(
            CircleCategory::query()
            ->where('parent_id', $parentId)
            ->when($level !== null, fn ($query) => $query->where('level', $level))
        )
            ->orderByRaw(Schema::hasColumn('circle_categories', 'sort_order') ? 'sort_order ASC NULLS LAST' : 'id ASC')
            ->orderBy('name');
    }

    private function hierarchyColumnsAvailable(): bool
    {
        return Schema::hasColumn('circle_categories', 'parent_id') && Schema::hasColumn('circle_categories', 'level');
    }

    private function isMainCategory(CircleCategory $category): bool
    {
        if (! $this->hierarchyColumnsAvailable()) {
            return true;
        }

        return $category->parent_id === null && (int) $category->level === 1;
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

    private function resolveExpectedChildLevel(CircleCategory $category): ?int
    {
        $level = (int) ($category->level ?? 0);

        if ($level >= 1 && $level < 4) {
            return $level + 1;
        }

        return null;
    }

    private function applyActiveFilter($query)
    {
        if (! Schema::hasColumn('circle_categories', 'is_active')) {
            return $query;
        }

        return $query->where(function ($activeQuery): void {
            $activeQuery->where('is_active', true)
                ->orWhereNull('is_active');
        });
    }
}
