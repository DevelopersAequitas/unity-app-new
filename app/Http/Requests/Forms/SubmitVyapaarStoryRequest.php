<?php

declare(strict_types=1);

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

class SubmitVyapaarStoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => $this->trimValue($this->input('full_name')),
            'designation' => $this->trimValue($this->input('designation')),
            'company_name' => $this->trimValue($this->input('company_name')),
            'website' => $this->trimValue($this->input('website')),
            'entrepreneurial_journey' => $this->trimValue($this->input('entrepreneurial_journey')),
            'business_description' => $this->trimValue($this->input('business_description')),
            'biggest_challenge' => $this->trimValue($this->input('biggest_challenge')),
            'biggest_achievement' => $this->trimValue($this->input('biggest_achievement')),
            'business_impact' => $this->trimValue($this->input('business_impact')),
            'future_goals' => $this->trimValue($this->input('future_goals')),
            'advice_for_entrepreneurs' => $this->trimValue($this->input('advice_for_entrepreneurs')),
            'linkedin_url' => $this->trimValue($this->input('linkedin_url')),
            'facebook_url' => $this->trimValue($this->input('facebook_url')),
            'instagram_url' => $this->trimValue($this->input('instagram_url')),
            'twitter_url' => $this->trimValue($this->input('twitter_url')),
        ]);
    }

    public function rules(): array
    {
        return [
            // Screen 1: Basic Information
            'user_id' => ['nullable', 'string', 'uuid'],
            'full_name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'profile_photo' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'company_logo' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],

            // Screen 2: Your Story
            'entrepreneurial_journey' => ['required', 'string'],
            'business_description' => ['required', 'string'],
            'biggest_challenge' => ['required', 'string'],
            'biggest_achievement' => ['required', 'string'],
            'business_impact' => ['required', 'string'],
            'future_goals' => ['required', 'string'],
            'advice_for_entrepreneurs' => ['required', 'string'],

            // Screen 3: Promotion
            'linkedin_url' => ['nullable', 'url', 'max:500'],
            'facebook_url' => ['nullable', 'url', 'max:500'],
            'instagram_url' => ['nullable', 'url', 'max:500'],
            'twitter_url' => ['nullable', 'url', 'max:500'],

            // Final Screen
            'consent' => ['required', 'accepted'],
        ];
    }

    private function trimValue(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }
}
