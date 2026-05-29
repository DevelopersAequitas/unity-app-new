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
            'visitor_email' => ['nullable', 'email', 'max:255'],
            'visitor_phone' => ['required', 'string', 'min:6', 'max:50', 'regex:/^[0-9+()\-\s]+$/'],
            'visitor_company' => ['nullable', 'string', 'max:255'],
            'visitor_city' => ['nullable', 'string', 'max:255'],
            'full_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'string', 'min:6', 'max:50', 'regex:/^[0-9+()\-\s]+$/'],
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
