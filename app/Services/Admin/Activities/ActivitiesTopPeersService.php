<?php

namespace App\Services\Admin\Activities;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ActivitiesTopPeersService
{
    public function topTestimonials(array $filters): Collection
    {
        return $this->buildTopPeers($filters, 'testimonials', 'from_user_id', 'created_at', 'deleted_at', 'is_deleted');
    }

    public function topReferrals(array $filters): Collection
    {
        return $this->buildTopPeers($filters, 'referrals', 'from_user_id', 'created_at', 'deleted_at', 'is_deleted');
    }

    public function topBusinessDeals(array $filters): Collection
    {
        return $this->buildTopPeers($filters, 'business_deals', 'from_user_id', 'created_at', 'deleted_at', 'is_deleted');
    }

    public function topP2PMeetings(array $filters): Collection
    {
        return $this->buildTopPeers(
            $filters,
            'p2p_meetings',
            'initiator_user_id',
            'created_at',
            'deleted_at',
            'is_deleted',
            function ($query) {
                $query->whereDate('activity.meeting_date', '<', now()->toDateString());
            }
        );
    }

    public function topRequirements(array $filters): Collection
    {
        return $this->buildTopPeers($filters, 'requirements', 'user_id', 'created_at', 'deleted_at', null);
    }

    public function topBecomeLeader(array $filters): Collection
    {
        return $this->buildTopPeers($filters, 'leader_interest_submissions', 'user_id', 'created_at', null, null);
    }

    public function topRecommendPeer(array $filters): Collection
    {
        return $this->buildTopPeers($filters, 'peer_recommendations', 'user_id', 'created_at', null, null);
    }

    private function buildTopPeers(
        array $filters,
        string $table,
        string $actorColumn,
        string $dateColumn,
        ?string $softDeleteColumn,
        ?string $softDeleteFlagColumn,
        ?\Closure $extra = null
    ): Collection {
        $query = DB::table("{$table} as activity")
            ->join('users as u', 'u.id', '=', "activity.{$actorColumn}")
            ->selectRaw("activity.{$actorColumn} as user_id")
            ->selectRaw('count(*) as total')
            ->selectRaw("COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.display_name, '-') as peer_name")
            ->selectRaw("COALESCE(NULLIF(TRIM(u.company_name), ''), '-') as peer_company")
            ->selectRaw("COALESCE(NULLIF(TRIM(u.city), ''), 'No City') as peer_city")
            ->groupByRaw("activity.{$actorColumn}, u.first_name, u.last_name, u.display_name, u.company_name, u.city")
            ->orderByDesc('total')
            ->orderBy('peer_name')
            ->limit(5);

        if ($softDeleteColumn !== null) {
            $query->whereNull("activity.{$softDeleteColumn}");
        }

        if ($softDeleteFlagColumn !== null) {
            $query->where("activity.{$softDeleteFlagColumn}", false);
        }

        $this->applySharedFilters($query, $filters, $dateColumn);

        if ($extra) {
            $extra($query);
        }

        return $query->get();
    }

    private function applySharedFilters($query, array $filters, string $dateColumn): void
    {
        $fromAt = $this->normalizeDateTime(data_get($filters, 'from_at'), false);
        $toAt = $this->normalizeDateTime(data_get($filters, 'to_at'), true);
        $search = trim((string) data_get($filters, 'search', data_get($filters, 'q', '')));
        $circleId = trim((string) data_get($filters, 'circle_id', ''));

        if ($fromAt) {
            $query->where("activity.{$dateColumn}", '>=', $fromAt);
        }

        if ($toAt) {
            $query->where("activity.{$dateColumn}", '<=', $toAt);
        }

        if ($circleId !== '' && strtolower($circleId) !== 'any') {
            $query->whereExists(function ($sub) use ($circleId) {
                $sub->selectRaw('1')
                    ->from('circle_members as cm_filter')
                    ->whereColumn('cm_filter.user_id', 'u.id')
                    ->where('cm_filter.status', 'approved')
                    ->whereNull('cm_filter.deleted_at')
                    ->where('cm_filter.circle_id', $circleId);
            });
        }

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->where(function ($inner) use ($like) {
                $inner->whereRaw("concat_ws(' ', u.first_name, u.last_name) ILIKE ?", [$like])
                    ->orWhere('u.display_name', 'ILIKE', $like)
                    ->orWhere('u.company_name', 'ILIKE', $like)
                    ->orWhere('u.city', 'ILIKE', $like);
            });
        }
    }

    private function normalizeDateTime($value, bool $isEndOfDay): ?Carbon
    {
        $value = is_string($value) ? trim($value) : null;
        if (! $value) {
            return null;
        }

        try {
            $parsed = Carbon::parse($value);

            if (! str_contains($value, 'T') && ! str_contains($value, ':')) {
                return $isEndOfDay ? $parsed->endOfDay() : $parsed->startOfDay();
            }

            return $parsed;
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
