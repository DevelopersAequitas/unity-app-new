<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\P2PMeetingRescheduleRequestResource;
use App\Mail\P2PMeetingWorkflowMail;
use App\Models\P2PMeetingRequest;
use App\Models\P2PMeetingRescheduleRequest;
use App\Models\User;
use App\Services\EmailLogs\EmailLogService;
use App\Services\Notifications\NotifyUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class P2PMeetingRescheduleController extends BaseApiController
{
    public function pendingReceived(Request $request)
    {
        $items = P2PMeetingRescheduleRequest::query()
            ->with(['requestedBy', 'requestedTo', 'meetingRequest.requester', 'meetingRequest.invitee'])
            ->where('requested_to_user_id', $request->user()->id)
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        return $this->success([
            'total' => $items->count(),
            'items' => P2PMeetingRescheduleRequestResource::collection($items),
        ]);
    }

    public function approve(Request $request, string $id, NotifyUserService $notifyUserService)
    {
        $authUser = $request->user();

        $meetingRequest = null;
        $rescheduleRequest = null;
        $requester = null;

        $result = DB::transaction(function () use ($id, $authUser, &$meetingRequest, &$rescheduleRequest, &$requester) {
            $rescheduleRequest = P2PMeetingRescheduleRequest::query()
                ->with(['requestedBy', 'requestedTo', 'meetingRequest.requester', 'meetingRequest.invitee'])
                ->lockForUpdate()
                ->find($id);

            if (! $rescheduleRequest) {
                return ['error' => 'Reschedule request not found.', 'status' => 404];
            }

            if ((string) $rescheduleRequest->requested_to_user_id !== (string) $authUser->id) {
                return ['error' => 'Only the requested peer can approve this reschedule request.', 'status' => 403];
            }

            if ($rescheduleRequest->status !== 'pending') {
                return ['error' => 'Only pending reschedule requests can be approved.', 'status' => 422];
            }

            $meetingRequest = P2PMeetingRequest::query()
                ->with(['requester', 'invitee'])
                ->lockForUpdate()
                ->findOrFail($rescheduleRequest->p2p_meeting_request_id);

            if (in_array((string) $meetingRequest->status, ['completed', 'cancelled', 'rejected'], true)) {
                return ['error' => 'This meeting cannot be rescheduled.', 'status' => 422];
            }

            $rescheduleRequest->update([
                'status' => 'approved',
                'approved_at' => now(),
                'responded_by_user_id' => $authUser->id,
            ]);

            $meetingRequest->update([
                'scheduled_at' => $rescheduleRequest->new_scheduled_at,
                'place' => $rescheduleRequest->new_place ?? $meetingRequest->place,
                'status' => 'scheduled',
            ]);

            $meetingRequest->refresh()->load(['requester', 'invitee']);
            $rescheduleRequest->refresh()->load(['requestedBy', 'requestedTo', 'meetingRequest.requester', 'meetingRequest.invitee']);
            $requester = $rescheduleRequest->requestedBy;

            return ['meeting' => $meetingRequest];
        });

        if (isset($result['error'])) {
            return $this->error($result['error'], $result['status'] ?? 422);
        }

        if ($requester) {
            $this->dispatchPushNotification($notifyUserService, $requester, $authUser, 'p2p_reschedule_approved', $meetingRequest, $rescheduleRequest);
            $this->sendWorkflowEmail($requester, $authUser, 'p2p_reschedule_approved', $meetingRequest, $rescheduleRequest);
        }

        return $this->success([
            'p2p_meeting_request_id' => (string) $meetingRequest->id,
            'scheduled_at' => $meetingRequest->scheduled_at?->toIso8601String(),
            'place' => $meetingRequest->place,
            'status' => (string) $meetingRequest->status,
        ], 'P2P meeting reschedule request approved successfully.');
    }

    public function reject(Request $request, string $id, NotifyUserService $notifyUserService)
    {
        $authUser = $request->user();
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $meetingRequest = null;
        $rescheduleRequest = null;
        $requester = null;

        $result = DB::transaction(function () use ($id, $authUser, &$meetingRequest, &$rescheduleRequest, &$requester) {
            $rescheduleRequest = P2PMeetingRescheduleRequest::query()
                ->with(['requestedBy', 'requestedTo', 'meetingRequest.requester', 'meetingRequest.invitee'])
                ->lockForUpdate()
                ->find($id);

            if (! $rescheduleRequest) {
                return ['error' => 'Reschedule request not found.', 'status' => 404];
            }

            if ((string) $rescheduleRequest->requested_to_user_id !== (string) $authUser->id) {
                return ['error' => 'Only the requested peer can reject this reschedule request.', 'status' => 403];
            }

            if ($rescheduleRequest->status !== 'pending') {
                return ['error' => 'Only pending reschedule requests can be rejected.', 'status' => 422];
            }

            $meetingRequest = P2PMeetingRequest::query()
                ->with(['requester', 'invitee'])
                ->lockForUpdate()
                ->findOrFail($rescheduleRequest->p2p_meeting_request_id);

            $rescheduleRequest->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'responded_by_user_id' => $authUser->id,
            ]);

            if (! in_array((string) $meetingRequest->status, ['completed', 'cancelled', 'rejected'], true)) {
                $statusToRestore = $meetingRequest->responded_at !== null ? 'scheduled' : 'pending';
                $meetingRequest->update(['status' => $statusToRestore]);
            }

            $meetingRequest->refresh()->load(['requester', 'invitee']);
            $rescheduleRequest->refresh()->load(['requestedBy', 'requestedTo', 'meetingRequest.requester', 'meetingRequest.invitee']);
            $requester = $rescheduleRequest->requestedBy;

            return ['reschedule_request' => $rescheduleRequest];
        });

        if (isset($result['error'])) {
            return $this->error($result['error'], $result['status'] ?? 422);
        }

        if ($requester) {
            $this->dispatchPushNotification($notifyUserService, $requester, $authUser, 'p2p_reschedule_rejected', $meetingRequest, $rescheduleRequest, $validated['reason'] ?? null);
            $this->sendWorkflowEmail($requester, $authUser, 'p2p_reschedule_rejected', $meetingRequest, $rescheduleRequest, $validated['reason'] ?? null);
        }

        return $this->success([
            'reschedule_request_id' => (string) $rescheduleRequest->id,
            'status' => (string) $rescheduleRequest->status,
        ], 'P2P meeting reschedule request rejected successfully.');
    }

    private function dispatchPushNotification(
        NotifyUserService $notifyUserService,
        User $toUser,
        User $fromUser,
        string $notificationType,
        P2PMeetingRequest $meetingRequest,
        ?P2PMeetingRescheduleRequest $rescheduleRequest = null,
        ?string $responseReason = null
    ): void {
        $fromName = trim((string) ($fromUser->display_name ?? $fromUser->name ?? 'A member'));
        $titleMap = [
            'p2p_reschedule_requested' => 'P2P Reschedule Requested',
            'p2p_reschedule_approved' => 'P2P Reschedule Approved',
            'p2p_reschedule_rejected' => 'P2P Reschedule Rejected',
        ];
        $title = $titleMap[$notificationType] ?? 'P2P Reschedule Update';

        $bodyMap = [
            'p2p_reschedule_requested' => $fromName.' requested to reschedule the P2P meeting.',
            'p2p_reschedule_approved' => $fromName.' approved the reschedule request.',
            'p2p_reschedule_rejected' => $fromName.' rejected the reschedule request.',
        ];
        $body = $bodyMap[$notificationType] ?? 'You have a new P2P reschedule update.';

        $notifyUserService->notifyUser(
            $toUser,
            $fromUser,
            $notificationType,
            [
                'title' => $title,
                'body' => $body,
                'notification_type' => $notificationType,
                'type' => $notificationType,
                'meeting_request_id' => (string) $meetingRequest->id,
                'reschedule_request_id' => $rescheduleRequest ? (string) $rescheduleRequest->id : null,
                'scheduled_at' => $meetingRequest->scheduled_at?->toIso8601String(),
                'place' => $meetingRequest->place,
                'new_scheduled_at' => $rescheduleRequest?->new_scheduled_at?->toIso8601String(),
                'new_place' => $rescheduleRequest?->new_place,
                'response_reason' => $responseReason,
                'screen' => '/p2p_meetings',
                'navigation_screen' => '/p2p_meetings',
            ],
            $meetingRequest
        );
    }

    private function sendWorkflowEmail(User $recipient, User $actor, string $eventType, P2PMeetingRequest $meetingRequest, ?P2PMeetingRescheduleRequest $rescheduleRequest = null, ?string $responseReason = null): void
    {
        $email = trim((string) $recipient->email);

        if ($email === '') {
            return;
        }

        $mailable = new P2PMeetingWorkflowMail($eventType, $meetingRequest, $recipient, $actor, $rescheduleRequest, $responseReason);
        $logData = [
            'user_id' => (string) $recipient->id,
            'to_email' => $email,
            'to_name' => (string) ($recipient->display_name ?: trim(($recipient->first_name ?? '').' '.($recipient->last_name ?? ''))),
            'template_key' => 'p2p_meeting_'.$eventType,
            'source_module' => 'P2P Meetings',
            'related_type' => P2PMeetingRequest::class,
            'related_id' => (string) $meetingRequest->id,
            'triggered_user_id' => (string) $actor->id,
            'triggered_by' => (string) ($actor->display_name ?: $actor->email ?: $actor->id),
            'payload' => ['event_type' => $eventType],
        ];

        try {
            Mail::to($email)->send($mailable);
            app(EmailLogService::class)->logMailableSent($mailable, $logData);
        } catch (\Throwable $exception) {
            app(EmailLogService::class)->logMailableFailed($mailable, $logData, $exception);
            Log::error('P2P meeting workflow email failed', [
                'event_type' => $eventType,
                'meeting_request_id' => (string) $meetingRequest->id,
                'recipient_id' => (string) $recipient->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
