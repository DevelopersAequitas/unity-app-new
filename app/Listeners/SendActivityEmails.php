<?php

namespace App\Listeners;

use App\Events\ActivityCreated;
use App\Mail\ActivityActorMail;
use App\Mail\ActivityAdminMail;
use App\Mail\ActivityOtherUserMail;
use App\Models\User;
use App\Services\EmailLogs\EmailLogService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendActivityEmails
{
    public function __construct(private readonly EmailLogService $emailLogService)
    {
    }

    public function handle(ActivityCreated $event): void
    {
        if (! config('activity_emails.enabled')) {
            return;
        }

        try {
            $actor = User::find($event->actorUserId);
            $otherUser = $event->otherUserId ? User::find($event->otherUserId) : null;
            $activityAttributes = $event->activityModel->getAttributes();
            $activityTitle = $this->deriveActivityTitle($event->activityModel, $event->activityType);
            $activityTypeNormalized = $this->normalizeActivityType($event->activityType);
            $viewData = $this->buildViewData($activityTypeNormalized, $event->activityModel, $actor, $otherUser);

            $this->sendActorEmail($event, $actor, $otherUser, $activityAttributes, $activityTitle, $activityTypeNormalized, $viewData);
            $this->sendOtherUserEmail($event, $actor, $otherUser, $activityAttributes, $activityTitle, $activityTypeNormalized, $viewData);
            $this->sendAdminEmail($event, $actor, $activityAttributes, $activityTitle);
        } catch (Throwable $e) {
            Log::error('Failed to dispatch activity emails', [
                'activity_type' => $event->activityType,
                'activity_id' => $event->activityModel->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendActorEmail(
        ActivityCreated $event,
        ?User $actor,
        ?User $otherUser,
        array $activityAttributes,
        string $activityTitle,
        string $activityTypeNormalized,
        array $viewData,
    ): void {
        if (! config('activity_emails.send_actor_copy')) {
            return;
        }

        if (! $actor || empty($actor->email)) {
            return;
        }

        try {
            $mailable = new ActivityActorMail(
                $activityTypeNormalized,
                $event->activityType,
                $viewData,
            );

            Mail::to($actor->email)->send($mailable);

            $this->emailLogService->logMailableSent($mailable, [
                'user_id' => (string) $actor->id,
                'to_email' => (string) $actor->email,
                'to_name' => (string) $this->displayName($actor),
                'template_key' => 'activity_actor_' . $activityTypeNormalized,
                'source_module' => 'Activities',
                'related_type' => get_class($event->activityModel),
                'related_id' => (string) $event->activityModel->getKey(),
                'payload' => [
                    'activity_type' => $event->activityType,
                    'recipient_role' => 'actor',
                ],
            ]);
        } catch (Throwable $e) {
            $this->emailLogService->logFailed([
                'user_id' => $actor?->id,
                'to_email' => (string) ($actor->email ?? ''),
                'to_name' => (string) $this->displayName($actor),
                'template_key' => 'activity_actor_' . $activityTypeNormalized,
                'source_module' => 'Activities',
                'related_type' => get_class($event->activityModel),
                'related_id' => (string) $event->activityModel->getKey(),
                'payload' => [
                    'activity_type' => $event->activityType,
                    'recipient_role' => 'actor',
                ],
            ], $e);

            Log::error('Failed to send actor activity email', [
                'activity_type' => $event->activityType,
                'activity_id' => $event->activityModel->getKey(),
                'actor_id' => $actor->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendOtherUserEmail(
        ActivityCreated $event,
        ?User $actor,
        ?User $otherUser,
        array $activityAttributes,
        string $activityTitle,
        string $activityTypeNormalized,
        array $viewData,
    ): void {
        if (! config('activity_emails.send_other_user')) {
            return;
        }

        if (! $otherUser || empty($otherUser->email)) {
            return;
        }

        if ($event->otherUserId === null) {
            return;
        }

        try {
            $mailable = new ActivityOtherUserMail(
                $activityTypeNormalized,
                $event->activityType,
                $viewData,
            );

            Mail::to($otherUser->email)->send($mailable);

            $this->emailLogService->logMailableSent($mailable, [
                'user_id' => (string) $otherUser->id,
                'to_email' => (string) $otherUser->email,
                'to_name' => (string) $this->displayName($otherUser),
                'template_key' => 'activity_other_user_' . $activityTypeNormalized,
                'source_module' => 'Activities',
                'related_type' => get_class($event->activityModel),
                'related_id' => (string) $event->activityModel->getKey(),
                'payload' => [
                    'activity_type' => $event->activityType,
                    'recipient_role' => 'other_user',
                ],
            ]);
        } catch (Throwable $e) {
            $this->emailLogService->logFailed([
                'user_id' => $otherUser?->id,
                'to_email' => (string) ($otherUser->email ?? ''),
                'to_name' => (string) $this->displayName($otherUser),
                'template_key' => 'activity_other_user_' . $activityTypeNormalized,
                'source_module' => 'Activities',
                'related_type' => get_class($event->activityModel),
                'related_id' => (string) $event->activityModel->getKey(),
                'payload' => [
                    'activity_type' => $event->activityType,
                    'recipient_role' => 'other_user',
                ],
            ], $e);

            Log::error('Failed to send other user activity email', [
                'activity_type' => $event->activityType,
                'activity_id' => $event->activityModel->getKey(),
                'other_user_id' => $otherUser->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendAdminEmail(
        ActivityCreated $event,
        ?User $actor,
        array $activityAttributes,
        string $activityTitle,
    ): void {
        if (strtolower($event->activityType) !== 'requirement') {
            return;
        }

        $adminInbox = config('activity_emails.admin_inbox');

        if (empty($adminInbox)) {
            return;
        }

        try {
            $mailable = new ActivityAdminMail(
                $event->activityType,
                $activityTitle,
                $actor,
                $activityAttributes,
            );

            Mail::to($adminInbox)->send($mailable);

            $this->emailLogService->logMailableSent($mailable, [
                'user_id' => null,
                'to_email' => (string) $adminInbox,
                'to_name' => 'Admin Inbox',
                'template_key' => 'activity_admin_requirement',
                'source_module' => 'Activities',
                'related_type' => get_class($event->activityModel),
                'related_id' => (string) $event->activityModel->getKey(),
                'payload' => [
                    'activity_type' => $event->activityType,
                    'recipient_role' => 'admin',
                ],
            ]);
        } catch (Throwable $e) {
            $this->emailLogService->logFailed([
                'to_email' => (string) $adminInbox,
                'to_name' => 'Admin Inbox',
                'template_key' => 'activity_admin_requirement',
                'source_module' => 'Activities',
                'related_type' => get_class($event->activityModel),
                'related_id' => (string) $event->activityModel->getKey(),
                'payload' => [
                    'activity_type' => $event->activityType,
                    'recipient_role' => 'admin',
                ],
            ], $e);

            Log::error('Failed to send admin activity email', [
                'activity_type' => $event->activityType,
                'activity_id' => $event->activityModel->getKey(),
                'admin_inbox' => $adminInbox,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function deriveActivityTitle(object $model, string $activityType): string
    {
        $potentialKeys = ['title', 'subject', 'content', 'comment', 'description'];

        foreach ($potentialKeys as $key) {
            if (! empty($model->{$key})) {
                return (string) $model->{$key};
            }
        }

        return $activityType;
    }

    protected function normalizeActivityType(string $activityType): string
    {
        return Str::of($activityType)->lower()->replace(' ', '_')->toString();
    }

    protected function buildViewData(string $activityTypeNormalized, object $activityModel, ?User $actor, ?User $otherUser): array
    {
        $actorName = $this->displayName($actor);
        $otherName = $this->displayName($otherUser);

        $data = [
            'actorName' => $actorName,
            'otherName' => $otherName,
            'meetingDate' => null,
            'meetingPlace' => null,
            'referralOf' => null,
            'requirementSubject' => null,
            'testimonialContent' => null,
            'dealAmountInr' => null,
        ];

        if ($activityTypeNormalized === 'p2p_meeting') {
            $data['meetingDate'] = $activityModel->meeting_date ?? null;
            $data['meetingPlace'] = $activityModel->meeting_place ?? null;
        }

        if ($activityTypeNormalized === 'referral') {
            $data['referralOf'] = $activityModel->referral_of ?? null;
        }

        if ($activityTypeNormalized === 'requirement') {
            $data['requirementSubject'] = $activityModel->subject ?? null;
        }

        if ($activityTypeNormalized === 'testimonial') {
            $data['testimonialContent'] = $activityModel->content ?? null;
        }

        if ($activityTypeNormalized === 'business_deal') {
            $data['dealAmountInr'] = isset($activityModel->deal_amount)
                ? number_format((float) $activityModel->deal_amount, 0)
                : null;
        }

        return $data;
    }

    protected function displayName(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return $user->display_name
            ?: ($fullName !== '' ? $fullName : ($user->email ?? null));
    }
}
