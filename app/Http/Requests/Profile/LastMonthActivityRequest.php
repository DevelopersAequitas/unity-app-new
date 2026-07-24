<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class LastMonthActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($this->parseMonth($value) === null) {
                    $fail('Invalid month format.');
                }
            }],
            'year' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_numeric($value) || strlen((string) $value) !== 4) {
                    $fail('Invalid year format.');
                }
            }],
        ];
    }

    public function parseMonth(mixed $value): ?int
    {
        if (is_numeric($value)) {
            $month = (int) $value;

            return ($month >= 1 && $month <= 12) ? $month : null;
        }

        if (is_string($value)) {
            $monthName = strtolower(trim($value));
            $months = [
                'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
                'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
                'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
                'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
                'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9,
                'oct' => 10, 'nov' => 11, 'dec' => 12,
            ];

            return $months[$monthName] ?? null;
        }

        return null;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Invalid query parameters. Month must be between 1 and 12, and Year must be a 4-digit number.',
            'data' => null,
        ], 400));
    }
}
