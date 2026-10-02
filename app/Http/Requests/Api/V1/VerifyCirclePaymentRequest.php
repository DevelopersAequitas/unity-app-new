<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class VerifyCirclePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'razorpay_order_id' => ['required', 'string', 'min:5'],
            'razorpay_payment_id' => ['required', 'string', 'min:5'],
            'razorpay_signature' => ['required', 'string', 'min:10'],
        ];
    }
}
