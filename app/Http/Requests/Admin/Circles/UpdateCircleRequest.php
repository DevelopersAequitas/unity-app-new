<?php

namespace App\Http\Requests\Admin\Circles;

use App\Models\Circle;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCircleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'purpose' => ['nullable', 'string'],
            'announcement' => ['nullable', 'string'],
            'city_id' => ['required', 'uuid', 'exists:cities,id'],
            'country' => ['required', 'string', 'max:100'],
            'circle_founder_user_id' => ['required', 'uuid', 'exists:users,id'],
            'circle_director_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'industry_director_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'ded_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'eed_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'business_growth_committee_chair_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'membership_growth_committee_chair_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'events_impacts_committee_chair_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'power_house_chair_1_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'power_house_chair_2_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'power_house_chair_3_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'cover_file_id' => ['nullable', 'uuid'],
            'circle_image_file_id' => ['nullable', 'uuid'],
            'type' => ['required', Rule::in(Circle::TYPE_OPTIONS)],
            'status' => ['required', Rule::in(Circle::STATUS_OPTIONS)],
            'circle_stage' => ['nullable', Rule::in(Circle::STAGE_OPTIONS)],
            'industry_tags' => ['nullable', 'array'],
            'industry_tags.*' => ['string', 'max:50'],
            'meeting_mode' => ['nullable', Rule::in(Circle::MEETING_MODE_OPTIONS)],
            'meeting_frequency' => ['nullable', Rule::in(Circle::MEETING_FREQUENCY_OPTIONS)],
            'meeting_link' => ['nullable', 'string', 'max:1000'],
            'meeting_passcode' => ['nullable', 'string', 'max:250'],
            'meeting_venue' => ['nullable', 'string', 'max:1000'],
            'meeting_landmark' => ['nullable', 'string', 'max:500'],
            'launch_date' => ['nullable', 'date'],
            'meeting_repeat' => ['nullable', 'array'],
            'calendar_meetings' => ['nullable', 'array'],
            'calendar_meetings.*.frequency' => ['nullable', Rule::in(['weekly', 'monthly', 'quarterly'])],
            'calendar_meetings.*.default_meet_day' => ['nullable', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'calendar_meetings.*.default_meet_time' => ['nullable', 'date_format:H:i'],
            'calendar_meetings.*.monthly_rule' => ['nullable', Rule::in(['first', 'second', 'third', 'fourth', 'last'])],
            'payment_gateway' => ['nullable', 'string', Rule::in(['zoho', 'razorpay'])],
            'payment_plan_id' => ['nullable', 'string', 'max:255'],
            'circle_package' => ['nullable', 'string', 'max:120'],
            'circle_price_amount' => ['nullable', 'numeric', 'min:0'],
            'circle_gst_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_package_active' => ['nullable', 'boolean'],
            'categories' => ['nullable', 'array', 'max:1'],
            'categories.*' => ['integer', 'exists:circle_categories,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $currentCircle = $this->route('circle');
            if (is_string($currentCircle)) {
                $currentCircle = Circle::query()->find($currentCircle);
            }

            $currentGateway = $currentCircle?->payment_gateway ?: ($currentCircle?->zoho_addon_code ? 'zoho' : 'zoho');
            $gateway = (string) ($this->input('payment_gateway') ?: $currentGateway);

            $package = trim((string) ($this->input('circle_package') ?: $this->input('payment_plan_id') ?: ''));
            $price = $this->input('circle_price_amount');
            $isPaid = $price !== null && (float) $price > 0;

            if ($isPaid && $gateway === '') {
                $validator->errors()->add('payment_gateway', 'A payment gateway must be selected for paid circles.');
            }

            // Existing Zoho circles retain legacy Zoho pricing behavior
            $isExistingZoho = $gateway === 'zoho' && $currentCircle && (
                ! empty($currentCircle->zoho_addon_code)
                || ! empty($currentCircle->zoho_addon_id)
                || (float) $currentCircle->circle_price_amount > 0
            );

            if ($isPaid && $package === '' && ! $isExistingZoho) {
                $validator->errors()->add('circle_package', 'A package must be selected for the chosen payment gateway.');
            }

            if ($package !== '') {
                if ($gateway === 'razorpay') {
                    $plan = Str::isUuid($package)
                        ? MembershipPlan::query()->circleOnly()->find($package)
                        : MembershipPlan::query()->circleOnly()->where('slug', $package)->first();

                    if (! $plan) {
                        $validator->errors()->add('circle_package', 'The selected package is not a valid Razorpay Circle plan.');
                    } else {
                        // Allow existing assigned plan if unchanged, but reject newly selected inactive plans
                        $isCurrentPlan = $currentCircle && (
                            (string) $currentCircle->payment_plan_id === (string) $plan->id
                            || (string) $currentCircle->payment_plan_id === (string) $plan->slug
                        );

                        if (! $plan->is_active && ! $isCurrentPlan) {
                            $validator->errors()->add('circle_package', 'The selected Razorpay package is inactive.');
                        }
                    }
                } elseif ($gateway === 'zoho') {
                    if (Str::isUuid($package) && MembershipPlan::query()->where('id', $package)->exists()) {
                        $validator->errors()->add('circle_package', 'The selected package belongs to Razorpay, not Zoho.');
                    }
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->filled('industry_tags') && is_string($this->input('industry_tags'))) {
            $payload['industry_tags'] = array_values(array_filter(array_map('trim', explode(',', $this->input('industry_tags')))));
        }

        if ($this->filled('meeting_repeat') && is_string($this->input('meeting_repeat'))) {
            $decoded = json_decode($this->input('meeting_repeat'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload['meeting_repeat'] = $decoded;
            }
        }

        if ($this->has('category_ids') && ! $this->has('categories')) {
            $payload['categories'] = $this->input('category_ids');
        }

        $currentCircle = $this->route('circle');
        if (is_string($currentCircle)) {
            $currentCircle = Circle::query()->find($currentCircle);
        }
        $defaultGateway = $currentCircle?->payment_gateway ?: ($currentCircle?->zoho_addon_code ? 'zoho' : 'zoho');
        $gateway = (string) ($this->input('payment_gateway') ?: $defaultGateway);
        $payload['payment_gateway'] = $gateway;

        if ($gateway === 'razorpay') {
            if ($this->filled('razorpay_package')) {
                $payload['circle_package'] = $this->input('razorpay_package');
                $payload['payment_plan_id'] = $this->input('razorpay_package');
            } elseif ($this->filled('circle_package')) {
                $payload['payment_plan_id'] = $this->input('circle_package');
            } elseif ($currentCircle && $currentCircle->payment_plan_id) {
                $payload['circle_package'] = $currentCircle->payment_plan_id;
                $payload['payment_plan_id'] = $currentCircle->payment_plan_id;
            }
        } elseif ($gateway === 'zoho') {
            if ($this->filled('zoho_package')) {
                $payload['circle_package'] = $this->input('zoho_package');
                $payload['payment_plan_id'] = $this->input('zoho_package');
            } elseif ($this->filled('circle_package')) {
                $payload['payment_plan_id'] = $this->input('circle_package');
            } elseif ($currentCircle && ($currentCircle->zoho_addon_code ?: $currentCircle->zoho_addon_id)) {
                $payload['circle_package'] = $currentCircle->zoho_addon_code ?: $currentCircle->zoho_addon_id;
                $payload['payment_plan_id'] = $currentCircle->zoho_addon_code ?: $currentCircle->zoho_addon_id;
            }
        }

        if ($payload !== []) {
            $this->merge($payload);
        }

        if ($this->filled('circle_founder_user_id')) {
            return;
        }

        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return;
        }

        $defaultFounder = User::query()->where('email', $admin->email)->first();

        if ($defaultFounder) {
            $this->merge([
                'circle_founder_user_id' => $defaultFounder->id,
            ]);
        }
    }
}
