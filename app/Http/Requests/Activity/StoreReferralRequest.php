<?php

declare(strict_types=1);

namespace App\Http\Requests\Activity;

use App\Models\CircleMember;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $toUserId = $this->input('to_user_id');

        if (! empty($toUserId) && ! Str::isUuid((string) $toUserId)) {
            $toUserIdStr = trim((string) $toUserId);
            $resolvedUserId = null;

            // 1. Check CircleMember by ID / user_id
            try {
                $circleMember = CircleMember::where('id', $toUserIdStr)->first();
                if ($circleMember && $circleMember->user_id && Str::isUuid((string) $circleMember->user_id)) {
                    $resolvedUserId = (string) $circleMember->user_id;
                }
            } catch (\Throwable) {
            }

            // 2. Check User by public_profile_slug
            if (! $resolvedUserId) {
                try {
                    $userBySlug = User::whereRaw('LOWER(public_profile_slug) = ?', [Str::lower($toUserIdStr)])->first();
                    if ($userBySlug) {
                        $resolvedUserId = (string) $userBySlug->id;
                    }
                } catch (\Throwable) {
                }
            }

            // 3. Check User by numeric / phone or raw match
            if (! $resolvedUserId && is_numeric($toUserIdStr)) {
                try {
                    $digits = preg_replace('/\D+/', '', $toUserIdStr) ?? '';
                    $short = substr($digits, -10);
                    $userByPhone = User::query()
                        ->where('phone', $toUserIdStr)
                        ->orWhere('phone', 'like', "%{$short}")
                        ->orWhere('secondary_mobile', $toUserIdStr)
                        ->orWhere('secondary_mobile', 'like', "%{$short}")
                        ->first();
                    if ($userByPhone) {
                        $resolvedUserId = (string) $userByPhone->id;
                    }
                } catch (\Throwable) {
                }
            }

            if ($resolvedUserId) {
                $this->merge(['to_user_id' => $resolvedUserId]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'to_user_id' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! Str::isUuid($value) || ! User::where('id', $value)->exists()) {
                        $fail('The selected to user id is invalid.');
                    }
                },
            ],
            'referral_type' => [
                'required',
                'string',
                Rule::in([
                    'customer_referral',
                    'b2b_referral',
                    'b2g_referral',
                    'collaborative_projects',
                    'referral_partnerships',
                    'vendor_referrals',
                    'others',
                ]),
            ],
            'referral_date' => ['required', 'date_format:Y-m-d'],
            'referral_of' => ['required', 'string'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string'],
            'hot_value' => ['required', 'integer', 'min:1', 'max:5'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
