<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ads;

use Illuminate\Foundation\Http\FormRequest;

class ReviewAdBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:approved,rejected'],
            'admin_remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
