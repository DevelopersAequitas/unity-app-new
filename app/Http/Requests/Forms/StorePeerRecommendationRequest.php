<?php

declare(strict_types=1);

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePeerRecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('is_aware') || $this->input('is_aware') === null) {
            $this->merge(['is_aware' => true]);
        }
    }

    public function rules(): array
    {
        return [
            'peer_name' => ['required', 'string', 'max:255'],
            'peer_mobile' => ['required', 'string', 'min:10', 'max:20'],
            'peer_email' => ['nullable', 'email', 'max:255'],
            'peer_city' => ['nullable', 'string', 'max:255'],
            'peer_city_country' => ['nullable', 'string', 'max:255'],
            'peer_business' => ['nullable', 'string', 'max:255'],
            'peer_industry' => ['nullable', 'string', 'max:255'],
            'main_business_category_id' => ['nullable', 'integer'],
            'main_business_category' => ['nullable', 'string', 'max:255'],
            'main_category_id' => ['nullable', 'integer'],
            'main_category' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'peer_category' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'business_subcategory_id' => ['nullable'],
            'business_subcategory' => ['nullable', 'string', 'max:255'],
            'subcategory_id' => ['nullable'],
            'subcategory' => ['nullable', 'string', 'max:255'],
            'how_well_known' => ['required', 'string', Rule::in(['business_associate', 'close_friend', 'client', 'community_contact'])],
            'is_aware' => ['nullable', 'boolean'],
            'why_valuable' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'circle_id' => ['nullable'],
            'circle_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'peer_name.required' => 'The peer name field is required.',
            'peer_mobile.required' => 'The peer mobile field is required and must be valid.',
            'peer_mobile.min' => 'The peer mobile field is required and must be valid.',
            'peer_mobile.max' => 'The peer mobile field is required and must be valid.',
            'how_well_known.required' => 'The how well known field is required.',
            'how_well_known.in' => 'The selected relationship is invalid.',
        ];
    }
}
