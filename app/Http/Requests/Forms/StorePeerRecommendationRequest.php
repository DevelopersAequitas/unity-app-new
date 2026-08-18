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

    public function rules(): array
    {
        return [
            'peer_name' => ['required', 'string', 'max:150'],
            'peer_mobile' => ['nullable', 'string', 'max:50'],
            'peer_email' => ['nullable', 'email', 'max:190'],
            'peer_city' => ['nullable', 'string', 'max:120'],
            'peer_city_country' => ['nullable', 'string', 'max:150'],
            'peer_business' => ['nullable', 'string', 'max:150'],
            'peer_industry' => ['nullable', 'string', 'max:150'],
            'why_valuable' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:150'],
            'peer_category' => ['nullable', 'string', 'max:150'],
            'category_id' => ['nullable', 'integer'],
            'circle_id' => ['nullable', 'string', 'max:100'],
            'circle_name' => ['nullable', 'string', 'max:150'],
            'how_well_known' => ['required', Rule::in(['close_friend', 'business_associate', 'client', 'community_contact'])],
            'is_aware' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
