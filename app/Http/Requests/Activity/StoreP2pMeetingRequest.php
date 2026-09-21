<?php

namespace App\Http\Requests\Activity;

use Illuminate\Foundation\Http\FormRequest;

class StoreP2pMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (! $this->has('peer_user_id') && $this->filled('to_user_id')) {
            $merge['peer_user_id'] = $this->input('to_user_id');
        }

        if (! $this->has('meeting_date') && $this->filled('date')) {
            $merge['meeting_date'] = $this->input('date');
        }

        if (! $this->has('meeting_place') && $this->filled('place')) {
            $merge['meeting_place'] = $this->input('place');
        }

        if (! $this->has('remarks') && $this->filled('notes')) {
            $merge['remarks'] = $this->input('notes');
        }

        if (! $this->has('media_file_ids')) {
            if ($this->filled('media_file_id')) {
                $merge['media_file_ids'] = [(string) $this->input('media_file_id')];
            } elseif ($this->filled('media')) {
                $fileIds = [];
                foreach ((array) $this->input('media') as $item) {
                    if (is_array($item) && ! empty($item['file_id'])) {
                        $fileIds[] = (string) $item['file_id'];
                    } elseif (is_string($item)) {
                        $fileIds[] = $item;
                    }
                }
                if (! empty($fileIds)) {
                    $merge['media_file_ids'] = $fileIds;
                }
            }
        }

        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'peer_user_id' => ['required', 'uuid', 'exists:users,id'],
            'meeting_date' => ['required', 'date_format:Y-m-d'],
            'meeting_place' => ['required', 'string', 'max:255'],
            'remarks' => ['required', 'string'],
            'media_file_ids' => ['sometimes', 'array'],
            'media_file_ids.*' => ['uuid', 'exists:files,id'],
        ];
    }
}
