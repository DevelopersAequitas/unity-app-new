<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\UserCircleRequest;

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
            $this->merge([
                'recommended_peers' => $peers,
                'nominees' => $peers,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'recommended_peers'                   => ['required', 'array', 'min:1'],
            'recommended_peers.*.name'            => ['required', 'string', 'max:200'],
            'recommended_peers.*.phone'           => ['nullable', 'string', 'max:30'],
            'recommended_peers.*.email'           => ['nullable', 'email', 'max:200'],
            'recommended_peers.*.user_id'         => ['nullable', 'uuid', 'exists:users,id'],
            'recommended_peers.*.nominee_user_id' => ['nullable', 'uuid', 'exists:users,id'],
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

            $seenPhones = [];
            $seenEmails = [];
            $currentUser = $this->user();

            $currentUserPhoneDigits = $currentUser && $currentUser->phone
                ? substr((string) preg_replace('/\D+/', '', $currentUser->phone), -10)
                : null;
            $currentUserEmail = $currentUser && $currentUser->email
                ? strtolower(trim((string) $currentUser->email))
                : null;

            foreach ($peers as $index => $peer) {
                if (! is_array($peer)) {
                    continue;
                }

                $rowNum = $index + 1;
                $rawPhone = trim((string) ($peer['phone'] ?? ''));
                $rawEmail = strtolower(trim((string) ($peer['email'] ?? '')));

                // 1. Phone number deduplication
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
                            "Duplicate phone number detected at row #{$rowNum} ('{$rawPhone}'). This number was already entered at row #{$originalRow} ('{$originalPhone}'). Each recommended peer must have a unique phone number."
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
                    if ($currentUserEmail && $rawEmail === $currentUserEmail) {
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
            }
        });
    }
}
