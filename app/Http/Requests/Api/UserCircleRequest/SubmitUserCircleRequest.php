<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\UserCircleRequest;

use App\Http\Controllers\Api\UserCircleRequestController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitUserCircleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    protected function prepareForValidation(): void
    {
        $peers = $this->input('recommended_peers')
            ?? $this->input('recommended_users')
            ?? $this->input('nominees');

        if (is_array($peers)) {
            $this->merge([
                'recommended_peers' => $peers,
                'nominees' => $peers, // for backward compatibility
            ]);
        }
    }

    public function rules(): array
    {
        return [
            // Step 1: Circle Basics
            'circle_type'                  => ['nullable', 'string', 'in:industry,interest'],
            'circle_name'                  => ['required', 'string', 'max:200'],
            'category_id'                  => ['nullable', 'integer'],
            'city'                         => ['nullable', 'string', 'max:100'],
            'country'                      => ['nullable', 'string', 'max:100'],
            'why_circle'                   => ['nullable', 'string', 'max:2000'],
            'description'                  => ['nullable', 'string', 'max:2000'],
            'ideal_member'                 => ['nullable', 'string', 'max:2000'],
            'problem_solved'               => ['nullable', 'string', 'max:2000'],

            // Step 2: Founder Profile
            'founder_business_name'        => ['nullable', 'string', 'max:200'],
            'founder_years_in_business'    => ['nullable', 'integer', 'min:0'],
            'founder_website_linkedin'     => ['nullable', 'string', 'max:500'],
            'founder_category_id'          => ['nullable', 'integer'],
            'founder_leadership_experience'=> ['nullable', 'string', 'max:2000'],

            // Step 3: Meeting Plan
            'meeting_mode'                 => ['nullable', 'string', 'in:online,offline,hybrid,in_person'],
            'preferred_day'                => ['nullable', 'string', 'max:50'],
            'preferred_time_slot'          => ['nullable', 'string', 'max:100'],
            'locality'                     => ['nullable', 'string', 'max:200'],
            'meeting_language'             => ['nullable', 'string', 'max:100'],
            'target_first_meeting_days'    => ['nullable', 'integer', 'in:30,60,90'],
            'meeting_frequency'            => ['nullable', 'string', 'in:monthly,quarterly'],

            // Step 4: Proposed Leadership (Optional)
            'proposed_leadership'          => ['nullable', 'array'],

            // Step 5: Recommended Peers (Build Your 40)
            'recommended_peers'            => ['required', 'array', 'min:1'],
            'recommended_peers.*.name'          => ['required', 'string', 'max:200'],
            'recommended_peers.*.phone'         => ['required', 'string', 'max:30'],
            'recommended_peers.*.business_name' => ['nullable', 'string', 'max:255'],
            'recommended_peers.*.category_id'   => ['nullable', 'integer'],
            'recommended_peers.*.interest_level'=> ['nullable', 'string', 'in:high,medium,low'],
            'recommended_peers.*.email'         => ['nullable', 'email', 'max:200'],
            'recommended_peers.*.user_id'       => ['nullable', 'uuid', 'exists:users,id'],
            'recommended_peers.*.nominee_user_id' => ['nullable', 'uuid', 'exists:users,id'],

            // Step 6: Declarations
            'declarations_accepted'        => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'circle_name.required'              => 'Circle chapter name is required.',
            'recommended_peers.required'        => 'You must add recommended peers to build your circle.',
            'recommended_peers.min'             => 'You must add recommended peers to build your circle.',
            'recommended_peers.*.name.required' => 'Each peer row must have a name.',
            'recommended_peers.*.phone.required'=> 'Each peer row must have a mobile number.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $peers = $this->input('recommended_peers') ?? [];
            if (! is_array($peers)) {
                return;
            }

            $minimum = UserCircleRequestController::getMinimumRequired();
            $seenPhones = [];
            $seenEmails = [];
            $seenCategories = [];
            $completeCount = 0;
            $currentUser = $this->user();

            $currentUserPhoneDigits = $currentUser && $currentUser->phone
                ? substr((string) preg_replace('/\D+/', '', $currentUser->phone), -10)
                : null;
            $currentUserEmail = $currentUser && $currentUser->email
                ? strtolower(trim((string) $currentUserEmail))
                : null;

            // Also check founder category (seat 1)
            $founderCategory = $this->input('founder_category_id');
            if (! empty($founderCategory)) {
                $seenCategories[(int) $founderCategory] = [
                    'row' => 'Founder (Seat 1)',
                    'name' => 'Founder',
                ];
            }

            foreach ($peers as $index => $peer) {
                if (! is_array($peer)) {
                    continue;
                }

                $rowNum = $index + 1;
                $rawName = trim((string) ($peer['name'] ?? ''));
                $rawPhone = trim((string) ($peer['phone'] ?? ''));
                $rawEmail = strtolower(trim((string) ($peer['email'] ?? '')));
                $rawBusiness = trim((string) ($peer['business_name'] ?? ''));
                $rawCategory = $peer['category_id'] ?? null;

                $isComplete = ($rawName !== '' && $rawPhone !== '' && $rawBusiness !== '' && ! empty($rawCategory));
                if ($isComplete) {
                    $completeCount++;
                }

                // 1. Phone number deduplication (normalized to 10 digits)
                if ($rawPhone !== '') {
                    $digits = (string) preg_replace('/\D+/', '', $rawPhone);
                    $phoneKey = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

                    // Cannot recommend self
                    if ($currentUserPhoneDigits && $phoneKey === $currentUserPhoneDigits) {
                        $v->errors()->add(
                            "recommended_peers.{$index}.phone",
                            "Row #{$rowNum}: You cannot recommend your own phone number ({$rawPhone}). Please provide a peer's phone number."
                        );
                    }

                    if (isset($seenPhones[$phoneKey])) {
                        $originalRow = $seenPhones[$phoneKey]['row'];
                        $originalPhone = $seenPhones[$phoneKey]['phone'];
                        $v->errors()->add(
                            "recommended_peers.{$index}.phone",
                            "Duplicate mobile number at row #{$rowNum} ('{$rawPhone}'). Already entered at row #{$originalRow} ('{$originalPhone}'). Each peer must have a unique number."
                        );
                    } else {
                        $seenPhones[$phoneKey] = [
                            'row' => $rowNum,
                            'phone' => $rawPhone,
                        ];
                    }
                }

                // 2. Category uniqueness per circle
                if (! empty($rawCategory)) {
                    $catId = (int) $rawCategory;
                    if (isset($seenCategories[$catId])) {
                        $existingHolder = $seenCategories[$catId]['name'];
                        $existingRow = $seenCategories[$catId]['row'];
                        $v->errors()->add(
                            "recommended_peers.{$index}.category_id",
                            "Row #{$rowNum} ('{$rawName}'): Category is already assigned to {$existingHolder} at {$existingRow}. Only one member per specific category is permitted in this Circle."
                        );
                    } else {
                        $seenCategories[$catId] = [
                            'row' => "Row #{$rowNum}",
                            'name' => $rawName ?: "Peer #{$rowNum}",
                        ];
                    }
                }

                // 3. Email deduplication (if provided)
                if ($rawEmail !== '') {
                    if ($currentUserEmail && $rawEmail === $currentUserEmail) {
                        $v->errors()->add(
                            "recommended_peers.{$index}.email",
                            "Row #{$rowNum}: You cannot recommend your own email address ({$rawEmail})."
                        );
                    }

                    if (isset($seenEmails[$rawEmail])) {
                        $originalRow = $seenEmails[$rawEmail]['row'];
                        $v->errors()->add(
                            "recommended_peers.{$index}.email",
                            "Duplicate email address at row #{$rowNum} ('{$rawEmail}'). Already entered at row #{$originalRow}."
                        );
                    } else {
                        $seenEmails[$rawEmail] = [
                            'row' => $rowNum,
                            'email' => $rawEmail,
                        ];
                    }
                }
            }

            // Verify minimum complete peers threshold
            if ($completeCount < $minimum) {
                $incomplete = count($peers) - $completeCount;
                $v->errors()->add(
                    'recommended_peers',
                    "Submission requires at least {$minimum} complete peer entries. Currently {$completeCount} complete, {$incomplete} incomplete. (Each row requires Name, Mobile, Business Name, and Category)."
                );
            }
        });
    }
}
