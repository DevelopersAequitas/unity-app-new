<?php

namespace App\Http\Requests\CoinClaims;

use App\Support\CoinClaims\CoinClaimActivityRegistry;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class StoreCoinClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'activity_code' => ['required', 'string'],
            'payload' => ['nullable'],
            'fields' => ['nullable'],
            'files' => ['nullable'],
            'files.*' => ['nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $registry = app(CoinClaimActivityRegistry::class);
            $activityCode = (string) $this->input('activity_code', '');

            if (! $registry->has($activityCode)) {
                $validator->errors()->add('activity_code', 'The selected activity code is invalid.');

                return;
            }

            $fieldMap = $registry->fieldMap($activityCode);
            $rawInputs = $this->all();
            $payload = is_array($this->input('payload')) ? $this->input('payload') : [];
            $nestedFields = is_array($this->input('fields')) ? $this->input('fields') : [];

            // Extract root-level inputs that match known field keys or are general fields
            $rootFields = [];
            foreach ($rawInputs as $k => $v) {
                if (! in_array($k, ['_token', '_method', 'activity_code', 'payload', 'fields', 'files'], true)) {
                    $rootFields[$k] = $v;
                }
            }

            // Merge in order: root fields, payload, nested fields
            $fields = array_merge($rootFields, $payload, $nestedFields);

            // Collect all files from files[] array, root file inputs, and allFiles()
            $files = is_array($this->file('files')) ? $this->file('files') : [];
            foreach ($this->allFiles() as $fileKey => $uploadedFile) {
                if ($fileKey !== 'files' && ! isset($files[$fileKey])) {
                    $files[$fileKey] = $uploadedFile;
                }
            }

            // Special handling for peers_global_feedback_video: allow either file upload or URL/storage-reference string
            if ($activityCode === 'peers_global_feedback_video') {
                $hasVideoFile = isset($files['feedback_video']) || isset($files['file']) || $this->hasFile('feedback_video') || $this->hasFile('file');
                $hasVideoField = ! empty($fields['feedback_video']) || ! empty($fields['feedback_video_url']) || ! empty($fields['url']) || ! empty($payload['feedback_video']);

                if (! $hasVideoFile && ! $hasVideoField) {
                    $validator->errors()->add('fields.feedback_video', 'Feedback video is required.');
                }

                return;
            }

            // Only check unknown fields for explicitly submitted nested `fields` or `payload`
            $fieldKeys = array_keys($fieldMap);
            $explicitProvidedKeys = array_merge(array_keys($nestedFields), array_keys($payload));
            $unknownFieldKeys = array_diff($explicitProvidedKeys, $fieldKeys);

            foreach ($unknownFieldKeys as $unknownFieldKey) {
                $suggestedKey = $this->closestKey((string) $unknownFieldKey, $fieldKeys);

                if ($suggestedKey !== null) {
                    $validator->errors()->add(
                        "fields.$unknownFieldKey",
                        "Unknown field '$unknownFieldKey'. Did you mean '$suggestedKey'?"
                    );

                    continue;
                }

                $validator->errors()->add("fields.$unknownFieldKey", "Unknown field '$unknownFieldKey'.");
            }

            foreach ($fieldMap as $key => $definition) {
                $type = (string) ($definition['type'] ?? 'text');
                $required = (bool) ($definition['required'] ?? false);
                $label = $this->fieldLabel($key, $definition);
                $value = $fields[$key] ?? null;
                $file = $files[$key] ?? ($files['file'] ?? ($this->file($key) ?? null));

                if ($type === 'file') {
                    $hasFileRecord = $file instanceof \Illuminate\Http\UploadedFile
                        || (! empty($value) && (is_string($value) || is_numeric($value)));

                    if ($required && ! $hasFileRecord) {
                        $validator->errors()->add("files.$key", "$label is required.");
                    }

                    continue;
                }

                if ($required && ($value === null || (is_string($value) && trim($value) === ''))) {
                    $validator->errors()->add("fields.$key", "$label is required.");

                    continue;
                }

                if ($value === null || (is_string($value) && trim($value) === '')) {
                    continue;
                }

                $this->validateTypedField($validator, $key, $type, (string) $value, $label);
            }
        });
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422));
    }

    private function validateTypedField(Validator $validator, string $key, string $type, string $value, string $label): void
    {
        $ok = match ($type) {
            'date' => (bool) strtotime($value),
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'phone' => preg_match('/^[0-9+\-\s]{7,20}$/', $value) === 1,
            default => true,
        };

        if ($ok) {
            return;
        }

        $message = match ($type) {
            'date' => "$label must be a valid date.",
            'email' => "$label must be a valid email address.",
            'url' => "$label must be a valid URL.",
            'phone' => "$label must be a valid phone number.",
            default => "$label format is invalid.",
        };

        $validator->errors()->add("fields.$key", $message);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function fieldLabel(string $key, array $definition): string
    {
        $label = trim((string) ($definition['label'] ?? ''));

        if ($label !== '') {
            return $label;
        }

        return ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * @param  array<int, string>  $candidates
     */
    private function closestKey(string $inputKey, array $candidates): ?string
    {
        $bestKey = null;
        $bestDistance = null;

        foreach ($candidates as $candidate) {
            $distance = levenshtein($inputKey, $candidate);

            if ($bestDistance === null || $distance < $bestDistance) {
                $bestDistance = $distance;
                $bestKey = $candidate;
            }
        }

        if ($bestDistance === null || $bestDistance > 4) {
            return null;
        }

        return $bestKey;
    }
}
