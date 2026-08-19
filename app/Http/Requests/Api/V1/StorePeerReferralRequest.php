<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\PeerReferral;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePeerReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'referred_name' => ['required', 'string', 'max:255'],
            'referred_phone' => ['required', 'string', 'max:50'],
            'referred_email' => ['nullable', 'email', 'max:255'],
            'referred_company_name' => ['nullable', 'string', 'max:255'],
            'referred_designation' => ['nullable', 'string', 'max:255'],
            'main_circle_id' => ['required', 'uuid', 'exists:circles,id'],
            'circle_id' => ['nullable', 'uuid', 'exists:circles,id'],
            'open_category_id' => ['required', 'uuid'],
            'message' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $referrerId = $this->user()->id;
            $phone = $this->input('referred_phone');
            $email = $this->input('referred_email');
            $openCategoryId = $this->input('open_category_id');

            // Prevent duplicate pending referral for the same category and same peer (by phone or email) by the same referrer
            $alreadyExists = PeerReferral::query()
                ->where('referrer_user_id', $referrerId)
                ->where('open_category_id', $openCategoryId)
                ->where('status', 'pending')
                ->where(function ($query) use ($phone, $email): void {
                    $query->where('referred_phone', $phone);
                    if (! empty($email)) {
                        $query->orWhere('referred_email', $email);
                    }
                })
                ->exists();

            if ($alreadyExists) {
                $v->errors()->add('referred_phone', 'You already have a pending referral for this peer under this category.');
            }
        });
    }
}
