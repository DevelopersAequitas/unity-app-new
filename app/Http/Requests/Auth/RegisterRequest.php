<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $incomingReferralCode = $this->input('referral_code');

        if (blank($incomingReferralCode) && $this->has('referralCode')) {
            $incomingReferralCode = $this->input('referralCode');
        }

        $level1 = $this->input('level_1_category_id', $this->input('level1_category_id'));
        $level2 = $this->input('level_2_category_id', $this->input('level2_category_id'));
        $level3 = $this->input('level_3_category_id', $this->input('level3_category_id'));
        $level4 = $this->input('level_4_category_id', $this->input('level4_category_id', $this->input('category_id')));
        $businessCategoryId = $this->input('business_category_id');

        $payload = [
            'level_1_category_id' => $level1,
            'level_2_category_id' => $level2,
            'level_3_category_id' => $level3,
            'level_4_category_id' => $level4,
            // keep legacy keys populated for backward compatibility with existing code paths
            'level1_category_id' => $level1,
            'level2_category_id' => $level2,
            'level3_category_id' => $level3,
            'level4_category_id' => $level4,
            'business_category_id' => $businessCategoryId,
        ];

        if (! blank($incomingReferralCode)) {
            $payload['referral_code'] = strtoupper(trim((string) $incomingReferralCode));
        }

        $this->merge($payload);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'display_name' => ['nullable', 'string', 'max:150'],

            'email' => ['required', 'email:rfc,dns', 'max:255', 'unique:users,email'],

            // PHONE IS REQUIRED + UNIQUE TO AVOID DB UNIQUE VIOLATION
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],

            // PASSWORD WITH CONFIRMATION (OPTIONAL FOR OTP-ONLY REGISTRATION)
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],

            // NEW OPTIONAL FIELDS FOR REGISTRATION
            'company_name' => ['nullable', 'string', 'max:255'],
            'designation'  => ['nullable', 'string', 'max:255'],
            'city_id' => ['nullable', 'uuid', 'exists:cities,id'],
            'city_of_residence' => ['nullable', 'string', 'max:150'],
            'business_category_id' => ['nullable', 'exists:circle_categories,id'],
            'referred_by_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'circle_id' => ['nullable', 'uuid', 'exists:circles,id'],
            'level_1_category_id' => ['nullable', 'integer', 'exists:circle_categories,id'],
            'level_2_category_id' => ['nullable', 'integer', 'exists:circle_category_level2,id'],
            'level_3_category_id' => ['nullable', 'integer', 'exists:circle_category_level3,id'],
            'level_4_category_id' => ['nullable', 'integer', 'exists:circle_category_level4,id'],
            'referral_code' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^[A-Z0-9]{8,32}$/',
            ],
        ];
    }


    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $businessCategoryId = $this->input('business_category_id');

            if (blank($businessCategoryId) || $validator->errors()->has('business_category_id')) {
                return;
            }

            if (! Schema::hasTable('circle_categories') || ! Schema::hasColumn('circle_categories', 'level')) {
                return;
            }

            $level = DB::table('circle_categories')
                ->where('id', $businessCategoryId)
                ->value('level');

            if ($level !== null && (int) $level !== 4) {
                $validator->errors()->add(
                    'business_category_id',
                    'The selected business category must be a Level 4 category.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'This phone number is already registered.',
            'referral_code.regex' => 'Referral code format is invalid.',
        ];
    }
}
