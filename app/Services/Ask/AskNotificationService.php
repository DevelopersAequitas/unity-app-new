<?php

declare(strict_types=1);

namespace App\Services\Ask;

use App\Models\Ask\Ask;
use App\Models\Ask\AskResponse;
use App\Models\Referral;
use App\Models\User;
use App\Services\Notifications\NotifyUserService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class AskNotificationService
{
    public function __construct(
        protected NotifyUserService $notifyUserService
    ) {}

    /**
     * Notify matching peers when an Ask is published.
     *
     * @param  Collection<int, User>  $peers
     */
    public function notifyAskPublished(Ask $ask, Collection $peers): void
    {
        $creator = $ask->user;
        if (! $creator) {
            return;
        }

        $flowName = $ask->flow?->name ?? 'Ask';
        $title = "New {$flowName} Opportunity";
        $body = "A new {$flowName} matches your profile: {$ask->title}";

        foreach ($peers as $peer) {
            if ($peer->id === $creator->id) {
                continue;
            }

            try {
                $this->notifyUserService->notifyUser(
                    to: $peer,
                    from: $creator,
                    type: 'ask_published_match',
                    data: [
                        'title' => $title,
                        'body' => $body,
                        'ask_id' => (string) $ask->id,
                        'flow_id' => (string) $ask->flow_id,
                        'type_id' => (string) $ask->type_id,
                    ],
                    notifiable: $ask
                );
            } catch (Throwable $e) {
                Log::warning('Failed sending ask published notification', [
                    'ask_id' => $ask->id,
                    'peer_id' => $peer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Notify Ask owner when a peer responds to their Ask.
     */
    public function notifyResponseSubmitted(AskResponse $response): void
    {
        $ask = $response->ask;
        $creator = $ask?->user;
        $responder = $response->responder;

        if (! $ask || ! $creator || ! $responder || $creator->id === $responder->id) {
            return;
        }

        $responderName = $responder->display_name ?: trim(($responder->first_name ?? '').' '.($responder->last_name ?? '')) ?: 'A peer';
        $title = 'New Response to your Ask';

        if ($response->response_type === AskResponse::TYPE_CAN_INTRODUCE_PEER) {
            $peer = $response->introducedUser;
            $peerName = $peer ? ($peer->display_name ?: trim(($peer->first_name ?? '').' '.($peer->last_name ?? ''))) : 'a peer';
            $body = "{$responderName} introduced {$peerName} for your ask: {$ask->title}";
        } elseif ($response->response_type === AskResponse::TYPE_KNOW_SOMEONE) {
            $contactName = $response->contact?->full_name ?: 'a contact';
            $body = "{$responderName} referred an external contact ({$contactName}) for your ask: {$ask->title}";
        } else {
            $body = "{$responderName} offered to help on your ask: {$ask->title}";
        }

        try {
            $this->notifyUserService->notifyUser(
                to: $creator,
                from: $responder,
                type: 'ask_response_received',
                data: [
                    'title' => $title,
                    'body' => $body,
                    'ask_id' => (string) $ask->id,
                    'response_id' => (string) $response->id,
                    'response_type' => $response->response_type,
                ],
                notifiable: $response
            );
        } catch (Throwable $e) {
            Log::warning('Failed sending ask response notification to author', [
                'response_id' => $response->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Branch 3: Notify Introduced Peer
        if ($response->response_type === AskResponse::TYPE_CAN_INTRODUCE_PEER && $response->introducedUser) {
            $introducedPeer = $response->introducedUser;
            if ($introducedPeer->id !== $responder->id && $introducedPeer->id !== $creator->id) {
                $authorName = $creator->display_name ?: trim(($creator->first_name ?? '').' '.($creator->last_name ?? '')) ?: 'A peer';
                $peerTitle = 'You were recommended for an Ask';
                $peerBody = "{$responderName} recommended you for an ask: {$ask->title} posted by {$authorName}";

                try {
                    $this->notifyUserService->notifyUser(
                        to: $introducedPeer,
                        from: $responder,
                        type: 'ask_peer_recommended',
                        data: [
                            'title' => $peerTitle,
                            'body' => $peerBody,
                            'ask_id' => (string) $ask->id,
                            'response_id' => (string) $response->id,
                            'response_type' => $response->response_type,
                        ],
                        notifiable: $response
                    );
                } catch (Throwable $e) {
                    Log::warning('Failed sending ask recommendation notification to introduced peer', [
                        'response_id' => $response->id,
                        'introduced_peer_id' => $introducedPeer->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Notify responder when Ask owner updates the response status.
     */
    public function notifyResponseStatusUpdated(AskResponse $response, string $newStatus): void
    {
        $ask = $response->ask;
        $creator = $ask?->user;
        $responder = $response->responder;

        if (! $ask || ! $creator || ! $responder || $creator->id === $responder->id) {
            return;
        }

        $statusLabel = ucwords(str_replace('_', ' ', $newStatus));
        $title = "Ask Response Status Updated: {$statusLabel}";
        $body = "Your response for '{$ask->title}' has been marked as {$statusLabel}.";

        try {
            $this->notifyUserService->notifyUser(
                to: $responder,
                from: $creator,
                type: 'ask_response_status_updated',
                data: [
                    'title' => $title,
                    'body' => $body,
                    'ask_id' => (string) $ask->id,
                    'response_id' => (string) $response->id,
                    'new_status' => $newStatus,
                ],
                notifiable: $response
            );
        } catch (Throwable $e) {
            Log::warning('Failed sending ask status updated notification', [
                'response_id' => $response->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify Ask owner and recipient when a referral is created from an Ask.
     */
    public function notifyReferralCreated(Ask $ask, Referral $referral): void
    {
        $creator = $ask->user;
        $fromUser = $referral->fromUser;
        $toUser = $referral->toUser;

        if ($fromUser && $toUser && $fromUser->id !== $toUser->id) {
            try {
                $this->notifyUserService->notifyUser(
                    to: $toUser,
                    from: $fromUser,
                    type: 'ask_referral_created',
                    data: [
                        'title' => 'New Referral from Ask',
                        'body' => "You received a new referral connected to request: {$ask->title}",
                        'ask_id' => (string) $ask->id,
                        'referral_id' => (string) $referral->id,
                    ],
                    notifiable: $referral
                );
            } catch (Throwable $e) {
                Log::warning('Failed sending ask referral notification', [
                    'ask_id' => $ask->id,
                    'referral_id' => $referral->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
