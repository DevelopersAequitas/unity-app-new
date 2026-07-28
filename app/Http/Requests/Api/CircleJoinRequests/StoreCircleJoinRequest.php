<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\CircleJoinRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCircleJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'circle_id' => ['nullable', 'required_without_all:category_id,level4_category_id', 'uuid', 'exists:circles,id'],
            'category_id' => ['nullable', 'required_without_all:circle_id,level4_category_id', 'integer', 'exists:circle_categories,id'],
            'reason_for_joining' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'level4_category_id' => ['nullable', 'required_without_all:circle_id,category_id', 'integer', 'exists:circle_category_level4,id'],
        ];
    }
}
