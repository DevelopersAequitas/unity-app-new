<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => 'nullable|uuid',
            'subject' => 'required|string|max:300',
            'description' => 'required|string|max:2000',
            'priority' => 'nullable|in:LOW,NORMAL,HIGH,URGENT',
        ];
    }
}
