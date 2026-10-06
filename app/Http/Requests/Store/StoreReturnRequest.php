<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|in:DAMAGED,WRONG_ITEM,DEFECTIVE,QUALITY_ISSUE',
            'description' => 'nullable|string|max:1000',
            'photos' => 'nullable|array',
            'photos.*' => 'string|url',
        ];
    }
}
