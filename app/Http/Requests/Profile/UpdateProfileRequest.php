<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use App\Support\ContactVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('contact_visibility')) {
            $this->merge([
                'contact_visibility' => ContactVisibility::normalize($this->input('contact_visibility')),
            ]);
        }

        $rawBusinessCategory = $this->input('business_category');
        $rawBusinessCategoryId = $this->input('business_category_id');

        $isOther = $this->has('is_other_category')
            ? $this->boolean('is_other_category')
            : (
                strtolower((string) $rawBusinessCategoryId) === 'other'
                || (is_array($rawBusinessCategory) && strtolower((string) data_get($rawBusinessCategory, 'id')) === 'other')
                || (! empty($this->input('other_category_name')) && empty($rawBusinessCategoryId) && empty($rawBusinessCategory))
            );

        if ($isOther) {
            $otherName = $this->input('other_category_name', $this->input('custom_category_name', $this->input('business_sub_category')));
            $this->merge([
                'is_other_category' => true,
                'other_category_name' => $otherName,
                'custom_category_name' => $otherName,
                'business_sub_category' => $otherName,
                'business_category_id' => null,
                'business_category' => null,
            ]);
        } elseif ($this->has('business_category') && is_array($rawBusinessCategory)) {
            $catId = data_get($rawBusinessCategory, 'id');
            if ($catId && strtolower((string) $catId) !== 'other') {
                $this->merge([
                    'business_category_id' => $catId,
                ]);
            }
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currentYear = (int) date('Y');

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'designation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'about' => ['sometimes', 'nullable', 'string'],
            'gender' => ['sometimes', 'nullable', 'in:male,female,other'],
            'dob' => ['sometimes', 'nullable', 'date'],
            'anniversary_date' => ['sometimes', 'nullable', 'date'],
            'experience_years' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'],
            'experience_summary' => ['sometimes', 'nullable', 'string'],
            'city_id' => ['sometimes', 'nullable'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city_of_residence' => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'skills' => ['sometimes', 'nullable', 'array'],
            'skills.*' => ['string', 'max:100'],
            'interests' => ['sometimes', 'nullable', 'array'],
            'interests.*' => ['string', 'max:100'],
            'media' => ['sometimes', 'array'],
            'media.*.id' => ['required', 'string', 'max:255'],
            'media.*.type' => ['required', Rule::in(['video', 'image'])],
            'media.*.url' => ['sometimes', 'nullable', 'url', 'max:1000'],
            'social_links' => ['sometimes', 'nullable', 'array'],
            'social_links.linkedin' => ['nullable', 'url', 'max:500'],
            'social_links.facebook' => ['nullable', 'url', 'max:500'],
            'social_links.instagram' => ['nullable', 'url', 'max:500'],
            'social_links.website' => ['nullable', 'url', 'max:500'],
            'profile_photo_id' => ['sometimes', 'nullable'],
            'cover_photo_id' => ['sometimes', 'nullable'],
            'intro_video_id' => ['sometimes', 'nullable'],
            'profile_video_id' => ['sometimes', 'nullable'],

            'state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:100'],
            'preferred_language' => ['sometimes', 'nullable', 'string', 'max:50'],
            'business_logo_id' => ['sometimes', 'nullable'],
            'is_other_category' => ['sometimes', 'nullable', 'boolean'],
            'other_category_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'custom_category_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_category' => ['sometimes', 'nullable'],
            'business_category_id' => ['sometimes', 'nullable'],
            'main_business_category_id' => ['sometimes', 'nullable'],
            'business_sub_category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'year_of_establishment' => ['sometimes', 'nullable', 'integer', 'between:1800,'.$currentYear],
            'annual_revenue_range' => ['sometimes', 'nullable', 'string', 'max:100'],
            'number_of_employees' => ['sometimes', 'nullable', 'string', 'max:50'],
            'gst_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'business_website' => ['sometimes', 'nullable', 'url', 'max:500'],
            'superpower' => ['sometimes', 'nullable', 'string', 'max:100'],
            'i_can_help_with' => ['sometimes', 'nullable', 'array'],
            'i_can_help_with.*' => ['string', 'max:150'],
            'i_am_looking_for' => ['sometimes', 'nullable', 'array'],
            'i_am_looking_for.*' => ['string', 'max:150'],
            'business_keywords' => ['sometimes', 'nullable', 'array'],
            'business_keywords.*' => ['string', 'max:100'],
            'products_services_offered' => ['sometimes', 'nullable', 'string'],
            'secondary_mobile' => ['sometimes', 'nullable', 'string', 'max:30'],
            'linkedin_profile' => ['sometimes', 'nullable', 'url', 'max:500'],
            'instagram_handle' => ['sometimes', 'nullable', 'url', 'max:255'],
            'twitter_handle' => ['sometimes', 'nullable', 'url', 'max:255'],
            'facebook_profile' => ['sometimes', 'nullable', 'url', 'max:500'],
            'youtube_channel' => ['sometimes', 'nullable', 'url', 'max:500'],
            'other_website' => ['sometimes', 'nullable', 'url', 'max:500'],
            'profile_visibility' => ['sometimes', 'nullable', Rule::in(['everyone', 'connected_only', 'circle_only', 'hidden'])],
            'contact_visibility' => ['sometimes', 'nullable', Rule::in(ContactVisibility::allowedValues())],
            'business_address' => ['sometimes', 'nullable', 'string'],
            'business_city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'business_state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'business_pincode' => ['sometimes', 'nullable', 'string', 'max:20'],
            'business_country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'google_maps_latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'google_maps_longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'industries_of_interest' => ['sometimes', 'nullable', 'array'],
            'industries_of_interest.*' => ['string', 'max:150'],
            'collaboration_goals' => ['sometimes', 'nullable', 'array'],
            'collaboration_goals.*' => ['string', 'max:150'],
            'preferred_meeting_format' => ['sometimes', 'nullable', 'string', 'max:50'],
            'willing_to_mentor' => ['sometimes', 'nullable', 'boolean'],
            'open_to_cross_city_collaboration' => ['sometimes', 'nullable', 'boolean'],
            'open_to_speaking_at_events' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
