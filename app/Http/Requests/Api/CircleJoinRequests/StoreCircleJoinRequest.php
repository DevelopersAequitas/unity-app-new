<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\CircleJoinRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCircleJoinRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $rawLevel4 = $this->input('level4_category_id', $this->input('level_4_category_id'));
        $rawCategory = $this->input('category_id', $this->input('level1_category_id', $this->input('level_1_category_id')));

        $isOther = $this->boolean('is_other_category')
            || strtolower((string) $rawLevel4) === 'other'
            || strtolower((string) $this->input('business_category_id')) === 'other';

        $level4 = (is_numeric($rawLevel4) && (int) $rawLevel4 > 0) ? (int) $rawLevel4 : null;
        $category = (is_numeric($rawCategory) && (int) $rawCategory > 0) ? (int) $rawCategory : null;
        $otherCategoryName = $this->nullableInput('other_category_name', $this->nullableInput('custom_category_name'));

        $this->merge([
            'category_id' => $category,
            'level1_category_id' => $category,
            'level4_category_id' => $level4,
            'is_other_category' => $isOther,
            'other_category_name' => $otherCategoryName,
            'custom_category_name' => $otherCategoryName,
        ]);
    }

    private function nullableInput(string $key, mixed $default = null): mixed
    {
        $value = $this->input($key, $default);

        return $value === '' ? null : $value;
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'circle_id' => ['nullable', 'required_without_all:category_id,level1_category_id,level4_category_id', 'uuid', 'exists:circles,id'],
            'category_id' => ['nullable', 'required_without_all:circle_id,level4_category_id', 'integer', 'exists:circle_categories,id'],
            'level1_category_id' => ['nullable', 'integer', 'exists:circle_categories,id'],
            'reason_for_joining' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'level4_category_id' => ['nullable', 'integer'],
            'is_other_category' => ['nullable', 'boolean'],
            'other_category_name' => ['nullable', 'string', 'max:255'],
            'custom_category_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
