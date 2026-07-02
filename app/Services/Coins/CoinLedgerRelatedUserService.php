<?php

namespace App\Services\Coins;

use App\Models\BusinessDeal;
use App\Models\P2pMeeting;
use App\Models\Referral;
use App\Models\Testimonial;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CoinLedgerRelatedUserService
{
    /** @var Collection<string, User> */
    private Collection $userCache;

    public function __construct()
    {
        $this->userCache = collect();
    }

    /**
     * @param  mixed  $ledger
     * @return array{related_user: ?User, activity_type: ?string, activity_id: ?string}
     */
    public function enrichLedgerItem($ledger, int|string $authUserId, string $reasonLabel): array
    {
        $type = $this->detectTypeFromReason($reasonLabel);

        if (! $type) {
            return ['related_user' => null, 'activity_type' => null, 'activity_id' => null];
        }

        $createdAt = $ledger->created_at instanceof Carbon
            ? $ledger->created_at
            : Carbon::parse($ledger->created_at);

        $match = match ($type) {
            'referral' => $this->findClosestReferral($authUserId, $createdAt),
            'testimonial' => $this->findClosestTestimonial($authUserId, $createdAt),
            'business_deal' => $this->findClosestBusinessDeal($authUserId, $createdAt),
            'p2p_meeting' => $this->findClosestP2pMeeting($authUserId, $createdAt),
            default => null,
        };

        if (! $match) {
            return ['related_user' => null, 'activity_type' => null, 'activity_id' => null];
        }

        $otherUserId = $this->resolveCounterpartyId($type, $match, $authUserId);

        if (! $otherUserId) {
            return ['related_user' => null, 'activity_type' => $type, 'activity_id' => $match->id];
        }

        return [
            'related_user' => $this->loadUser($otherUserId),
            'activity_type' => $type,
            'activity_id' => $match->id,
        ];
    }

    private function detectTypeFromReason(string $reasonLabel): ?string
    {
        $normalized = Str::of($reasonLabel)->lower();

        return match (true) {
            $normalized->contains('referral') => 'referral',
            $normalized->contains('testimonial') => 'testimonial',
            $normalized->contains('business deal') => 'business_deal',
            $normalized->contains('p2p meeting') => 'p2p_meeting',
            default => null,
        };
    }

    private function findClosestReferral(int|string $authUserId, Carbon $createdAt): ?Referral
    {
        $window = $this->timeWindow($createdAt);

        $records = Referral::whereBetween('created_at', [$window['start'], $window['end']])
            ->where(function ($q) use ($authUserId) {
                $q->where('from_user_id', $authUserId)->orWhere('to_user_id', $authUserId);
            })
            ->get(['id', 'from_user_id', 'to_user_id', 'created_at']);

        return $this->closestByTimestamp($records, $createdAt);
    }

    private function findClosestTestimonial(int|string $authUserId, Carbon $createdAt): ?Testimonial
    {
        $window = $this->timeWindow($createdAt);

        $records = Testimonial::whereBetween('created_at', [$window['start'], $window['end']])
            ->where(function ($q) use ($authUserId) {
                $q->where('from_user_id', $authUserId)->orWhere('to_user_id', $authUserId);
            })
            ->get(['id', 'from_user_id', 'to_user_id', 'created_at']);

        return $this->closestByTimestamp($records, $createdAt);
    }

    private function findClosestBusinessDeal(int|string $authUserId, Carbon $createdAt): ?BusinessDeal
    {
        $window = $this->timeWindow($createdAt);

        $records = BusinessDeal::whereBetween('created_at', [$window['start'], $window['end']])
            ->where(function ($q) use ($authUserId) {
                $q->where('from_user_id', $authUserId)->orWhere('to_user_id', $authUserId);
            })
            ->get(['id', 'from_user_id', 'to_user_id', 'created_at']);

        return $this->closestByTimestamp($records, $createdAt);
    }

    private function findClosestP2pMeeting(int|string $authUserId, Carbon $createdAt): ?P2pMeeting
    {
        $window = $this->timeWindow($createdAt);

        $records = P2pMeeting::whereBetween('created_at', [$window['start'], $window['end']])
            ->where(function ($q) use ($authUserId) {
                $q->where('initiator_user_id', $authUserId)->orWhere('peer_user_id', $authUserId);
            })
            ->get(['id', 'initiator_user_id', 'peer_user_id', 'created_at']);

        return $this->closestByTimestamp($records, $createdAt);
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, T>  $records
     * @return ?T
     */
    private function closestByTimestamp(Collection $records, Carbon $target)
    {
        if ($records->isEmpty()) {
            return null;
        }

        return $records
            ->sortBy(fn ($record) => abs($target->diffInSeconds($record->created_at, true)))
            ->first();
    }

    private function resolveCounterpartyId(string $type, $record, int|string $authUserId): ?string
    {
        return match ($type) {
            'referral', 'testimonial', 'business_deal' => $record->from_user_id === $authUserId
                ? ($record->to_user_id ?? null)
                : ($record->from_user_id ?? null),
            'p2p_meeting' => $record->initiator_user_id === $authUserId
                ? ($record->peer_user_id ?? null)
                : ($record->initiator_user_id ?? null),
            default => null,
        };
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function timeWindow(Carbon $center): array
    {
        return [
            'start' => $center->copy()->subMinutes(5),
            'end' => $center->copy()->addMinutes(5),
        ];
    }

    private function loadUser(?string $userId): ?User
    {
        if (! $userId) {
            return null;
        }

        if ($this->userCache->has($userId)) {
            return $this->userCache->get($userId);
        }

        $user = User::find($userId);

        if ($user) {
            $this->userCache->put($userId, $user);
        }

        return $user;
    }

    public function loadUserById(?string $userId): ?User
    {
        return $this->loadUser($userId);
    }
}
