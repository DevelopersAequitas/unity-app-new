<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Categories\StoreCategoryRequest;
use App\Http\Requests\Admin\Categories\UpdateCategoryRequest;
use App\Imports\CategoriesImport;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
        $categories = Category::query()
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('category_name', 'ILIKE', '%' . $search . '%')
                        ->orWhere('sector', 'ILIKE', '%' . $search . '%');
                });
            'category' => new Category(),
    {
        Category::query()->create($request->validated());

    public function edit(Category $category): View
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
        $category->update($request->validated());
    public function destroy(Category $category): RedirectResponse
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

        } catch (\Exception $e) {
            $categories = Category::query()
                ->select([
                    'id',
                    'category_name',
                    'sector',
                    'remarks',
                    'created_at',
                    'updated_at',
                ])
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery
                            ->where('category_name', 'ILIKE', '%' . $search . '%')
                            ->orWhere('sector', 'ILIKE', '%' . $search . '%');
                    });
                    fputcsv($handle, ['ID', 'Category Name', 'Sector', 'Remarks', 'Created At', 'Updated At']);
                            (string) ($category->category_name ?? ''),
                            (string) ($category->sector ?? ''),
                            (string) ($category->remarks ?? ''),
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
