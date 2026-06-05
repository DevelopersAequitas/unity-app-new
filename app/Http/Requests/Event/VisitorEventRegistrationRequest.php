<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class VisitorEventRegistrationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'visitor_name' => $this->input('visitor_name', $this->input('full_name')),
            'visitor_email' => $this->input('visitor_email', $this->input('email')),
            'visitor_phone' => $this->input('visitor_phone', $this->input('phone')),
            'visitor_company' => $this->input('visitor_company', $this->input('company_name')),
            'visitor_city' => $this->input('visitor_city', $this->input('city')),
        ], fn ($value) => $value !== null));
    }

    public function rules(): array
    {
        return [
            'visitor_name' => ['required', 'string', 'max:255'],
            'visitor_email' => ['required', 'email', 'max:255'],
            'visitor_phone' => ['required', 'string', 'max:30'],
            'visitor_company' => ['nullable', 'string', 'max:255'],
            'visitor_city' => ['nullable', 'string', 'max:255'],
            'visitor_designation' => ['nullable', 'string', 'max:255'],
            'visitor_business_category_main_id' => ['nullable', 'integer'],
            'visitor_business_category_sub_id' => ['nullable', 'integer'],
            'visitor_business_website' => ['nullable', 'url', 'max:500'],
            'visitor_business_brief' => ['nullable', 'string'],
            'invited_by_type' => ['nullable', 'string', 'max:50'],
            'invited_by_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'full_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:30'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'designation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_category_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_sub_category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'referral_code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'referred_by' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'zoho_form_entry_id' => ['nullable', 'string', 'max:255'],
            'zoho_payment_id' => ['nullable', 'string', 'max:255'],
            'zoho_payment_status' => ['nullable', 'string', 'max:100'],
            'source' => ['sometimes', 'string', 'in:app,visitor_app,visitor_web,admin,zoho_form'],
            'metadata' => ['nullable', 'array'],
        ];
    }

}
