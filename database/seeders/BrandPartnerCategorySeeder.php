<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BrandPartnerCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandPartnerCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Technology',
            'Education',
            'Healthcare',
            'Finance',
            'Travel',
            'Food',
            'Marketing',
            'Business Services',
            'Legal',
            'HR',
            'Insurance',
            'Real Estate',
            'Manufacturing',
        ];

        // Replace existing categories with the specified list
        BrandPartnerCategory::query()->delete();

        foreach ($categories as $index => $categoryName) {
            BrandPartnerCategory::query()->create([
                'id' => (string) Str::uuid(),
                'name' => $categoryName,
                'sort_order' => $index + 1,
                'status' => 'active',
            ]);
        }
    }
}
