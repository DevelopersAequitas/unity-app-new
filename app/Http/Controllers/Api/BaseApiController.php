<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BaseApiController extends Controller
{
    protected function success($data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function error(string $message, int $status = 400, $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    protected function buildActivityPostMessage(string $activityType, ?User $otherUser, array $context = []): string
    {
        $normalizedType = Str::of($activityType)->lower()->replace(' ', '_')->toString();
        $peerName = $this->resolveDisplayName($otherUser);
        $actorName = $this->resolveDisplayName($context['actor_user'] ?? null);
        $testimonialMessage = trim((string) ($context['testimonial_message'] ?? ''));
        $amountText = trim((string) ($context['amount'] ?? ''));

        return match ($normalizedType) {
            'testimonial' => $this->buildTestimonialPostMessage($peerName, $testimonialMessage),
            'business_deal' => "Hey Peers, another business connection and handshake turned into real results.\n"
                . $actorName . ' made a deal with ' . $peerName . ' of amount ' . $amountText . '.',
            'p2p_meeting' => 'Hey Peers, I have connected with ' . $peerName
                . ', exchanged ideas, and discussed to have collaboration.',
            default => '',
        };
    }

    protected function resolveDisplayName(?User $user): string
    {
        if (! $user) {
            return 'Peer';
        }

        if (! empty($user->display_name)) {
            return $user->display_name;
        }

        $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return $fullName !== '' ? $fullName : 'Peer';
    }

    protected function buildTestimonialPostMessage(string $peerName, string $testimonialMessage): string
    {
        $message = 'Hey Peers, sharing a moment of gratitude to ' . $peerName . '.';

        if ($testimonialMessage !== '') {
            $message .= ' ' . ltrim($testimonialMessage);
        }

        return $message;
    }

    protected function increaseLifeImpact(string $userId, int $points): void
    {
        $incrementBy = (int) $points;

        if ($incrementBy <= 0) {
            return;
        }

        DB::table('users')
            ->where('id', $userId)
            ->update([
                'life_impacted_count' => DB::raw('COALESCE(life_impacted_count, 0) + ' . $incrementBy),
                'updated_at' => now(),
            ]);
    }
}
