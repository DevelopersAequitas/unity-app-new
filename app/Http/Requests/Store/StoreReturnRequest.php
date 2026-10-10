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
            'reason' => 'required|string|in:DEFECTIVE,WRONG_ITEM,SIZE_MISFIT,QUALITY_ISSUE,DAMAGED,OTHER',
            'description' => 'nullable|string|max:1000',
            'photos' => 'nullable|array',
            'photos.*' => 'string|url',
        ];
    }
}
