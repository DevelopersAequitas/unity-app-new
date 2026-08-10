<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class ScanEventQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qr_token' => ['required', 'string', 'max:512'],
            'device_info' => ['nullable', 'array'],
            'scanner_user_id' => [
                'nullable',
                'uuid',
                function ($attribute, $value, $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $existsInUsers = DB::table('users')->where('id', $value)->exists();
                    $existsInScanUsers = DB::table('scan_app_users')->where('id', $value)->exists();

                    if (! $existsInUsers && ! $existsInScanUsers) {
                        $fail('The selected '.$attribute.' is invalid.');
                    }
                },
            ],
            'force' => ['sometimes', 'boolean'],
        ];
    }
}
