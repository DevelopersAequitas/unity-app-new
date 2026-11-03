<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\UserCircleRequest;

use App\Models\User;
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
            $peerIds = [];
            foreach ($peers as $peer) {
                if (! is_array($peer)) {
                    continue;
                }
                $pId = $peer['peer_id'] ?? $peer['user_id'] ?? $peer['nominee_user_id'] ?? null;
                if ($pId) {
                    $peerIds[] = (string) $pId;
                }
            }

            $usersMap = [];
            if (! empty($peerIds)) {
                $usersMap = User::query()
                    ->whereIn('id', array_unique($peerIds))
                    ->get()
                    ->keyBy('id');
            }

            foreach ($peers as &$peer) {
                if (! is_array($peer)) {
                    continue;
                }

                $peerId = $peer['peer_id'] ?? $peer['user_id'] ?? $peer['nominee_user_id'] ?? null;
                if ($peerId) {
                    $peer['peer_id'] = (string) $peerId;
                    $peer['user_id'] = (string) $peerId;
                    $peer['nominee_user_id'] = (string) $peerId;

                    if (isset($usersMap[(string) $peerId])) {
                        /** @var User $existingUser */
                        $existingUser = $usersMap[(string) $peerId];

                        if (empty($peer['name'])) {
                            $peer['name'] = trim(($existingUser->first_name ?? '') . ' ' . ($existingUser->last_name ?? ''))
                                ?: $existingUser->display_name
                                ?: $existingUser->name;
                        }
                        if (empty($peer['phone']) && ! empty($existingUser->phone)) {
                            $peer['phone'] = $existingUser->phone;
                        }
                        if (empty($peer['email']) && ! empty($existingUser->email)) {
                            $peer['email'] = $existingUser->email;
                        }
                        if (empty($peer['business_name'])) {
                            $peer['business_name'] = $existingUser->company_name ?? $existingUser->business_name ?? null;
                        }
                        if (empty($peer['category_id'])) {
                            $peer['category_id'] = $existingUser->main_business_category_id ?? $existingUser->category_id ?? null;
                        }
                        if (empty($peer['subcategory_id']) && empty($peer['sub_category_id'])) {
                            $peer['subcategory_id'] = $existingUser->business_category_id ?? $existingUser->level_4_category_id ?? null;
                        }
                        if (empty($peer['source'])) {
                            $peer['source'] = 'app';
                        }
                    }
                }

                if (! empty($peer['sub_category_id']) && empty($peer['subcategory_id'])) {
                    $peer['subcategory_id'] = $peer['sub_category_id'];
                }

                if (empty($peer['source'])) {
                    $peer['source'] = ! empty($peer['peer_id']) ? 'app' : 'manual';
                }
            }
            unset($peer);

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
            'founder_subcategory_id'       => ['nullable', 'integer'],
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

            // Step 5: Recommended Peers (Flexible Initial Submit: min:1)
            'recommended_peers'                    => ['required', 'array', 'min:1'],
            'recommended_peers.*.source'           => ['nullable', 'string', 'in:app,contacts,manual'],
            'recommended_peers.*.peer_id'          => ['nullable', 'string', 'max:50'],
            'recommended_peers.*.user_id'          => ['nullable', 'string', 'max:50'],
            'recommended_peers.*.nominee_user_id'  => ['nullable', 'string', 'max:50'],
            'recommended_peers.*.name'             => ['required', 'string', 'max:200'],
            'recommended_peers.*.phone'            => ['nullable', 'string', 'max:30'],
            'recommended_peers.*.business_name'    => ['nullable', 'string', 'max:255'],
            'recommended_peers.*.category_id'      => ['nullable', 'integer'],
            'recommended_peers.*.subcategory_id'   => ['nullable', 'integer'],
            'recommended_peers.*.sub_category_id'  => ['nullable', 'integer'],
            'recommended_peers.*.interest_level'   => ['nullable', 'string', 'in:high,medium,low'],
            'recommended_peers.*.email'            => ['nullable', 'email', 'max:200'],

            // Step 6: Declarations
            'declarations_accepted'        => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'circle_name.required'              => 'Circle chapter name is required.',
            'recommended_peers.required'        => 'You must add at least one recommended peer to build your circle.',
            'recommended_peers.min'             => 'You must add at least one recommended peer to build your circle.',
            'recommended_peers.*.name.required' => 'Each peer row must have a name.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $peers = $this->input('recommended_peers') ?? [];
            if (! is_array($peers)) {
                return;
            }

            $currentUser = $this->user() ?? auth()->user();
            $currentPhone = $currentUser?->phone
                ? substr((string) preg_replace('/\D+/', '', $currentUser->phone), -10)
                : null;
            $currentEmail = $currentUser?->email
                ? strtolower(trim((string) $currentUser->email))
                : null;
            $currentUserId = $currentUser ? (string) $currentUser->id : null;

            $seenPhones = [];
            $seenEmails = [];
            $seenPeerIds = [];
            $seenCategories = [];
            $seenSubcategories = [];

            // Also check founder category & subcategory (seat 1)
            $founderCategory = $this->input('founder_category_id');
            if (! empty($founderCategory)) {
                $seenCategories[(int) $founderCategory] = [
                    'row' => 'Founder (Seat 1)',
                    'name' => 'Founder',
                ];
            }
            $founderSubcategory = $this->input('founder_subcategory_id');
            if (! empty($founderSubcategory)) {
                $seenSubcategories[(int) $founderSubcategory] = [
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
                $rawPeerId = (string) ($peer['peer_id'] ?? $peer['user_id'] ?? $peer['nominee_user_id'] ?? '');
                $rawCategory = $peer['category_id'] ?? null;
                $rawSubcategory = $peer['subcategory_id'] ?? $peer['sub_category_id'] ?? null;

                // Self peer check
                if ($currentUserId && $rawPeerId !== '' && $rawPeerId === $currentUserId) {
                    $v->errors()->add(
                        "recommended_peers.{$index}.peer_id",
                        "Row #{$rowNum}: You cannot recommend yourself as a peer."
                    );
                }

                // Peer ID deduplication
                if ($rawPeerId !== '') {
                    if (isset($seenPeerIds[$rawPeerId])) {
                        $origRow = $seenPeerIds[$rawPeerId];
                        $v->errors()->add(
                            "recommended_peers.{$index}.peer_id",
                            "Duplicate peer selected at row #{$rowNum}. Already selected at row #{$origRow}."
                        );
                    } else {
                        $seenPeerIds[$rawPeerId] = $rowNum;
                    }
                }

                // 1. Phone number check & deduplication
                if ($rawPhone !== '') {
                    $digits = (string) preg_replace('/\D+/', '', $rawPhone);
                    $phoneKey = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

                    // Cannot recommend self phone
                    if ($currentPhone && strlen($phoneKey) === 10 && $phoneKey === $currentPhone) {
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

                // 2. Email deduplication
                if ($rawEmail !== '') {
                    // Cannot recommend self email
                    if ($currentEmail && $rawEmail === $currentEmail) {
                        $v->errors()->add(
                            "recommended_peers.{$index}.email",
                            "Row #{$rowNum}: You cannot recommend your own email address ({$rawEmail}). Please provide a peer's email."
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

                // Require at least peer_id, phone, or email so we have contact info
                if ($rawPeerId === '' && $rawPhone === '' && $rawEmail === '') {
                    $v->errors()->add(
                        "recommended_peers.{$index}.phone",
                        "Row #{$rowNum}: Please provide a mobile number or email for {$rawName}."
                    );
                }

                // 3. Subcategory and Category uniqueness per circle
                if (! empty($rawSubcategory)) {
                    $subId = (int) $rawSubcategory;
                    if (isset($seenSubcategories[$subId])) {
                        $existingHolder = $seenSubcategories[$subId]['name'];
                        $existingRow = $seenSubcategories[$subId]['row'];
                        $v->errors()->add(
                            "recommended_peers.{$index}.subcategory_id",
                            "Row #{$rowNum} ('{$rawName}'): Subcategory is already assigned to {$existingHolder} at {$existingRow}. Each subcategory can only be selected once per Circle."
                        );
                    } else {
                        $seenSubcategories[$subId] = [
                            'row' => "Row #{$rowNum}",
                            'name' => $rawName ?: "Peer #{$rowNum}",
                        ];
                    }
                } elseif (! empty($rawCategory)) {
                    $catId = (int) $rawCategory;
                    if (isset($seenCategories[$catId])) {
                        $existingHolder = $seenCategories[$catId]['name'];
                        $existingRow = $seenCategories[$catId]['row'];
                        $v->errors()->add(
                            "recommended_peers.{$index}.category_id",
                            "Row #{$rowNum} ('{$rawName}'): Category is already assigned to {$existingHolder} at {$existingRow}. Only one member per specific category is permitted in this Circle. (Tip: select a specific subcategory to avoid duplicate conflict)."
                        );
                    } else {
                        $seenCategories[$catId] = [
                            'row' => "Row #{$rowNum}",
                            'name' => $rawName ?: "Peer #{$rowNum}",
                        ];
                    }
                }
            }
        });
    }
}
