<?php

declare(strict_types=1);

namespace App\Leader\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaderAssignCircleRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid'],
            'role' => [
                'required',
                'string',
                Rule::in([
                    'chair',
                    'circle_chair',
                    'chair_business_growth_committee',
                    'chair_membership_growth_committee',
                    'chair_events_impacts_committee',
                    'vice_chair',
                    'secretary',
                    'committee_leader',
                ]),
            ],
            'committee_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
