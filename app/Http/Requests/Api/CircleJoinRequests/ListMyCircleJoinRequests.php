<?php

namespace App\Http\Requests\Api\CircleJoinRequests;

use App\Models\CircleJoinRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMyCircleJoinRequests extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in([
                CircleJoinRequest::STATUS_PENDING_CD_APPROVAL,
                CircleJoinRequest::STATUS_PENDING_ID_APPROVAL,
                CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE,
                CircleJoinRequest::STATUS_CIRCLE_MEMBER,
                CircleJoinRequest::STATUS_PAID,
                CircleJoinRequest::STATUS_REJECTED_BY_CD,
                CircleJoinRequest::STATUS_REJECTED_BY_ID,
                CircleJoinRequest::STATUS_CANCELLED,
            ])],
        ];
    }
}
