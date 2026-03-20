<?php

namespace App\Exports;

use App\Models\Category;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CategoriesExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Category::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->category_name,
                'sector_id' => $category->sector,
                'remarks' => $category->remarks,
            ]);
    }

    public function headings(): array
    {
        return ['id', 'name', 'sector_id', 'remarks'];
    }
}
