<?php

namespace App\Http\Requests\Admin\Categories;

use Illuminate\Foundation\Http\FormRequest;
            'category_name' => ['required', 'string', 'max:255', 'unique:categories,category_name'],
            'sector' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'category_name.required' => 'Category name is required.',
            'category_name.unique' => 'This category already exists.',
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('circle_categories', 'name')->where(fn ($query) => $query->where('level', 1)),
            ],
            'slug' => ['nullable', 'string', 'max:255'],
            'circle_key' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'remarks' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Category name is required.',
            'name.unique' => 'This category already exists.',
        ];
    }
}
