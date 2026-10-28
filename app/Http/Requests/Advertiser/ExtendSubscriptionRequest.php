<?php

declare(strict_types=1);

namespace App\Http\Requests\Advertiser;

use Illuminate\Foundation\Http\FormRequest;

class ExtendSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'package_id' => ['nullable', 'string', 'uuid'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:60'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }
}
