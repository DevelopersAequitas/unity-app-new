<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\IntroductionRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreIntroductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'introducer_id' => ['required', 'uuid', 'exists:users,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            /** @var User $requester */
            $requester = $this->user();
            $introducerId = (string) $this->input('introducer_id');

            // Requester must be active
            if ($requester->status !== 'active' || $requester->trashed()) {
                $v->errors()->add('introducer_id', 'Your account is not active.');

                return;
            }

            // Cannot select yourself
            if ($requester->id === $introducerId) {
                $v->errors()->add('introducer_id', 'You cannot select yourself as an introducer.');

                return;
            }

            // Introducer must exist and be active
            $introducer = User::withoutTrashed()->find($introducerId);
            if (! $introducer || $introducer->status !== 'active') {
                $v->errors()->add('introducer_id', 'The selected introducer is not available.');

                return;
            }

            // Requester must not already have an approved introducer
            if ($requester->introduced_by !== null) {
                $v->errors()->add('introducer_id', 'You already have an introducer assigned.');

                return;
            }

            // Prevent duplicate pending request
            $alreadyPending = IntroductionRequest::query()
                ->where('requester_id', $requester->id)
                ->where('introducer_id', $introducerId)
                ->where('status', 'pending')
                ->exists();

            if ($alreadyPending) {
                $v->errors()->add('introducer_id', 'You already have a pending introduction request for this introducer.');

                return;
            }

            // Prevent circular relationship: the introducer must not be introduced by the requester (at any level)
            if ($this->wouldCreateCircularRelationship($requester->id, $introducerId)) {
                $v->errors()->add('introducer_id', 'This selection would create a circular introduction relationship.');

                return;
            }
        });
    }

    /**
     * Walk up the introducer ancestry chain of the proposed introducer.
     * If the requester appears anywhere in that chain, it is circular.
     */
    private function wouldCreateCircularRelationship(string $requesterId, string $introducerId): bool
    {
        $visited = [];
        $currentId = $introducerId;

        while ($currentId !== null) {
            if (isset($visited[$currentId])) {
                // Loop in existing data; break to avoid infinite loop
                break;
            }

            $visited[$currentId] = true;

            /** @var User|null $current */
            $current = User::withoutTrashed()->select('id', 'introduced_by')->find($currentId);

            if (! $current) {
                break;
            }

            $parentId = $current->introduced_by;

            if ($parentId === null) {
                break;
            }

            if ($parentId === $requesterId) {
                return true;
            }

            $currentId = $parentId;
        }

        return false;
    }
}
