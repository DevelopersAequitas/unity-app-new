<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\UserCircleRequest;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateUserCircleRequestNominees extends FormRequest
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
                            $peer['category_id'] = $existingUser->category_id ?? $existingUser->business_category_id ?? null;
                        }
                        if (empty($peer['source'])) {
                            $peer['source'] = 'app';
                        }
                    }
                }

                if (empty($peer['source'])) {
                    $peer['source'] = ! empty($peer['peer_id']) ? 'app' : 'manual';
                }
            }
            unset($peer);

            $this->merge([
                'recommended_peers' => $peers,
                'nominees' => $peers,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'recommended_peers'                    => ['required', 'array', 'min:1'],
            'recommended_peers.*.source'           => ['nullable', 'string', 'in:app,contacts,manual'],
            'recommended_peers.*.peer_id'          => ['nullable', 'string', 'max:50'],
            'recommended_peers.*.user_id'          => ['nullable', 'string', 'max:50'],
            'recommended_peers.*.nominee_user_id'  => ['nullable', 'string', 'max:50'],
            'recommended_peers.*.name'             => ['required', 'string', 'max:200'],
            'recommended_peers.*.phone'            => ['nullable', 'string', 'max:30'],
            'recommended_peers.*.business_name'    => ['nullable', 'string', 'max:255'],
            'recommended_peers.*.category_id'      => ['nullable', 'integer'],
            'recommended_peers.*.interest_level'   => ['nullable', 'string', 'in:high,medium,low'],
            'recommended_peers.*.email'            => ['nullable', 'email', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'recommended_peers.required'        => 'You must add at least one recommended peer.',
            'recommended_peers.min'             => 'You must add at least one recommended peer.',
            'recommended_peers.*.name.required' => 'Each recommended peer must have a name.',
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

            foreach ($peers as $index => $peer) {
                if (! is_array($peer)) {
                    continue;
                }

                $rowNum = $index + 1;
                $rawName = trim((string) ($peer['name'] ?? ''));
                $rawPhone = trim((string) ($peer['phone'] ?? ''));
                $rawEmail = strtolower(trim((string) ($peer['email'] ?? '')));
                $rawPeerId = (string) ($peer['peer_id'] ?? $peer['user_id'] ?? $peer['nominee_user_id'] ?? '');

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

                    // Cannot recommend self
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
                            "Duplicate phone number detected at row #{$rowNum} ('{$rawPhone}'). This number was already entered at row #{$originalRow} ('{$originalPhone}'). Each recommended peer must have a unique phone number."
                        );
                    } else {
                        $seenPhones[$phoneKey] = [
                            'row' => $rowNum,
                            'phone' => $rawPhone,
                        ];
                    }
                }

                // 2. Email check & deduplication
                if ($rawEmail !== '') {
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
                            "Duplicate email address detected at row #{$rowNum} ('{$rawEmail}'). This email was already entered at row #{$originalRow}. Each recommended peer must have a unique email address."
                        );
                    } else {
                        $seenEmails[$rawEmail] = [
                            'row' => $rowNum,
                            'email' => $rawEmail,
                        ];
                    }
                }

                // Require at least peer_id, phone, or email
                if ($rawPeerId === '' && $rawPhone === '' && $rawEmail === '') {
                    $v->errors()->add(
                        "recommended_peers.{$index}.phone",
                        "Row #{$rowNum}: Please provide a phone number or email for {$rawName}."
                    );
                }
            }
        });
    }
}
