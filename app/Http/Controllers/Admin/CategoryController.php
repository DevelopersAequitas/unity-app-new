<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Categories\StoreCategoryRequest;
use App\Http\Requests\Admin\Categories\UpdateCategoryRequest;
use App\Imports\CategoriesImport;
use App\Models\CircleCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $categories = CircleCategory::query()
            ->where('level', 1)
            ->when($this->hasIsActiveColumn(), function ($query) {
                $query->where('is_active', true);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'ILIKE', '%' . $search . '%');
            })
            ->with('parent:id,name')
            ->orderByRaw('COALESCE(sort_order, 2147483647) ASC')
            ->orderBy('id')
            ->paginate(20)
            ->appends($request->query());

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
            ]),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $payload = $this->filterPayload($request->validated());
        $payload['level'] = 1;

        if ($this->hasIsActiveColumn()) {
            $payload['is_active'] = (bool) ($payload['is_active'] ?? true);
        }

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
        $payload = $this->filterPayload($request->validated());
        $payload['level'] = 1;

        if ($this->hasIsActiveColumn()) {
            $payload['is_active'] = (bool) ($payload['is_active'] ?? $category->is_active ?? true);
        }

        $category->update($payload);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(CircleCategory $category): RedirectResponse
    {
        try {
            $category->delete();

            return redirect()
                ->route('admin.categories.index')
                ->with('success', 'Category deleted successfully.');
        } catch (\Throwable $e) {
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
                ->with('parent:id,name')
                ->where('level', 1)
                ->when($this->hasIsActiveColumn(), function ($query) {
                    $query->where('is_active', true);
                })
                ->when($search !== '', function ($query) use ($search) {
                    $query->where('name', 'ILIKE', '%' . $search . '%');
                })
                ->orderByRaw('COALESCE(sort_order, 2147483647) ASC')
                ->orderBy('id')
                ->get();

            return response()->streamDownload(
                function () use ($categories): void {
                    $handle = fopen('php://output', 'w');

                    if ($handle === false) {
                        throw new \RuntimeException('Could not open output stream for CSV export.');
                    }

                    fwrite($handle, "\xEF\xBB\xBF");
                    fputcsv($handle, ['ID', 'Category Name', 'Sector', 'Remarks', 'Sort Order', 'Created At', 'Updated At']);

                    foreach ($categories as $category) {
                        $sector = $category->parent?->name
                            ?? $category->circle_key
                            ?? 'Main Category';

                        fputcsv($handle, [
                            $category->id,
                            (string) ($category->name ?? ''),
                            (string) $sector,
                            (string) ($category->remarks ?? ''),
                            (string) ($category->sort_order ?? ''),
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

    private function hasIsActiveColumn(): bool
    {
        return Schema::hasColumn('circle_categories', 'is_active');
    }

    private function filterPayload(array $payload): array
    {
        $allowedColumns = Collection::make([
            'name',
            'slug',
            'circle_key',
            'sort_order',
            'remarks',
            'is_active',
            'parent_id',
            'level',
        ])->filter(fn (string $column) => Schema::hasColumn('circle_categories', $column))
            ->all();

        return array_intersect_key($payload, array_flip($allowedColumns));
    }
}
