<?php

declare(strict_types=1);

namespace App\Services\Ask;

use App\Models\Ask\Ask;
use App\Models\Ask\AskResponse;
use App\Models\Ask\AskResponseContact;
use App\Models\Ask\AskResponseStatusHistory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AskResponseService
{
    public function __construct(
        protected AskNotificationService $notificationService
    ) {}

    /**
     * Get responder view of an Ask.
     */
    public function getAskForResponse(Ask $ask): Ask
    {
        return $ask->loadMissing([
            'flow',
            'type',
            'answers.option',
            'answers.optionGroup',
            'user',
        ]);
    }

    /**
     * Submit response to an Ask.
     *
     * @param  array<string, mixed>  $data
     */
    public function submitResponse(Ask $ask, User $responder, array $data): AskResponse
    {
        if ($ask->user_id === $responder->id) {
            throw ValidationException::withMessages([
                'response' => ['You cannot respond to your own Ask.'],
            ]);
        }

        return DB::transaction(function () use ($ask, $responder, $data): AskResponse {
            $responseType = (string) $data['response_type'];
            $introducedUserId = ! empty($data['introduced_user_id']) ? (string) $data['introduced_user_id'] : null;
            $message = ! empty($data['message']) ? (string) $data['message'] : null;

            $response = AskResponse::query()->updateOrCreate(
                [
                    'ask_id' => $ask->id,
                    'responder_user_id' => $responder->id,
                ],
                [
                    'response_type' => $responseType,
                    'message' => $message,
                    'introduced_user_id' => $introducedUserId,
                    'status' => AskResponse::STATUS_PENDING,
                    'responded_at' => now(),
                ]
            );

            // Handle "I know someone" contact payload
            if ($responseType === AskResponse::TYPE_KNOW_SOMEONE && ! empty($data['contact']) && is_array($data['contact'])) {
                $contactData = $data['contact'];
                AskResponseContact::query()->updateOrCreate(
                    ['response_id' => $response->id],
                    [
                        'full_name' => (string) ($contactData['full_name'] ?? ''),
                        'company_name' => $contactData['company_name'] ?? null,
                        'designation' => $contactData['designation'] ?? null,
                        'email' => $contactData['email'] ?? null,
                        'phone' => $contactData['phone'] ?? null,
                        'alternate_phone' => $contactData['alternate_phone'] ?? null,
                        'notes' => $contactData['notes'] ?? null,
                        'metadata' => $contactData['metadata'] ?? [],
                    ]
                );
            }

            // Create status history record
            AskResponseStatusHistory::create([
                'response_id' => $response->id,
                'changed_by_user_id' => $responder->id,
                'old_status' => null,
                'new_status' => AskResponse::STATUS_PENDING,
                'note' => 'Response submitted',
            ]);

            // Notify Ask owner
            $this->notificationService->notifyResponseSubmitted($response);

            return $response->fresh(['responder', 'introducedUser', 'contact']);
        });
    }

    /**
     * Get responses for an Ask.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getResponses(Ask $ask, array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return AskResponse::query()
            ->where('ask_id', $ask->id)
            ->when(! empty($filters['response_type']), function (Builder $q) use ($filters) {
                $q->where('response_type', $filters['response_type']);
            })
            ->when(! empty($filters['status']), function (Builder $q) use ($filters) {
                $q->where('status', $filters['status']);
            })
            ->with(['responder', 'introducedUser', 'contact'])
            ->orderByDesc('responded_at')
            ->paginate($perPage);
    }

    /**
     * Get details of a single response.
     */
    public function getResponseDetails(AskResponse $response): AskResponse
    {
        return $response->loadMissing([
            'ask.flow',
            'ask.type',
            'responder',
            'introducedUser',
            'contact',
            'statusHistories.changedBy',
        ]);
    }

    /**
     * Update response status (accepted, declined, in_progress, completed, closed, withdrawn).
     */
    public function updateResponseStatus(AskResponse $response, User $user, string $status, ?string $note): AskResponse
    {
        $oldStatus = $response->status;

        $response->update([
            'status' => $status,
        ]);

        AskResponseStatusHistory::create([
            'response_id' => $response->id,
            'changed_by_user_id' => $user->id,
            'old_status' => $oldStatus,
            'new_status' => $status,
            'note' => $note,
        ]);

        $this->notificationService->notifyResponseStatusUpdated($response, $status);

        return $response->fresh(['responder', 'introducedUser', 'contact']);
    }

    /**
     * Get status history of a response.
     *
     * @return Collection<int, AskResponseStatusHistory>
     */
    public function getResponseStatusHistory(AskResponse $response): Collection
    {
        return AskResponseStatusHistory::query()
            ->where('response_id', $response->id)
            ->with('changedBy')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Update contact details on a response.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateResponseContact(AskResponse $response, array $data): AskResponseContact
    {
        $contact = $response->contact;

        if (! $contact) {
            $contact = AskResponseContact::create(array_merge($data, ['response_id' => $response->id]));
        } else {
            $contact->update($data);
        }

        return $contact;
    }
}
