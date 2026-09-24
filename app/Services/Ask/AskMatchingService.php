<?php

declare(strict_types=1);

namespace App\Services\Ask;

use App\Models\Ask\Ask;
use App\Models\Ask\AskMatch;
use App\Models\CircleMember;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AskMatchingService
{
    public const ALGORITHM_VERSION = 'v1';

    /**
     * Run the matching engine for a given Ask and store suggested matches.
     *
     * @return Collection<int, AskMatch>
     */
    public function generateMatches(Ask $ask): Collection
    {
        $ask->loadMissing(['answers.option', 'flow', 'type']);

        // Collect dimensions from Ask answers
        $selectedIndustryCodes = [];
        $selectedGeographyCodes = [];
        $selectedStageCodes = [];
        $selectedNeedCodes = [];
        $selectedBringCodes = [];

        foreach ($ask->answers as $answer) {
            $code = strtolower((string) ($answer->option?->code ?? $answer->value_text ?? ''));
            if ($code === '') {
                continue;
            }

            match ($answer->field_key) {
                'industry', 'referral_industry' => $selectedIndustryCodes[] = $code,
                'geography', 'referral_geography' => $selectedGeographyCodes[] = $code,
                'business_stage' => $selectedStageCodes[] = $code,
                'need', 'collaboration_need' => $selectedNeedCodes[] = $code,
                'bring', 'collaboration_bring' => $selectedBringCodes[] = $code,
                default => null,
            };
        }

        // Query eligible candidates respecting visibility
        $query = User::query()
            ->where('id', '!=', $ask->user_id)
            ->where(function (Builder $q) {
                $q->whereNull('status')->orWhere('status', 'active');
            })
            ->with(['city', 'level4Category']);

        if ($ask->visibility_type === Ask::VISIBILITY_DISTRICT && $ask->visibility_district_id) {
            $query->where('district_id', $ask->visibility_district_id);
        } elseif ($ask->visibility_type === Ask::VISIBILITY_CIRCLE && $ask->visibility_circle_id) {
            $circleUserIds = CircleMember::query()
                ->where('circle_id', $ask->visibility_circle_id)
                ->where('is_approved', true)
                ->pluck('user_id');

            $query->whereIn('id', $circleUserIds);
        }

        $candidates = $query->limit(200)->get();
        $matches = collect();

        foreach ($candidates as $peer) {
            $dimensions = [];
            $reasons = [];
            $score = 50.0; // Base score for eligible peer in target audience

            // 1. Industry match
            $peerIndustry = strtolower((string) ($peer->level4Category?->name ?? $peer->industry ?? ''));
            if (! empty($selectedIndustryCodes)) {
                $matched = false;
                foreach ($selectedIndustryCodes as $code) {
                    if (str_contains($peerIndustry, $code) || str_contains($code, $peerIndustry)) {
                        $matched = true;
                        break;
                    }
                }
                if ($matched) {
                    $dimensions['industry'] = 'match';
                    $reasons[] = 'Industry match';
                    $score += 25.0;
                } else {
                    $dimensions['industry'] = 'partial';
                    $score += 5.0;
                }
            }

            // 2. Geography match
            $peerCity = strtolower((string) ($peer->city?->name ?? $peer->city ?? ''));
            if (! empty($selectedGeographyCodes)) {
                if (in_array('international', $selectedGeographyCodes, true) && strtolower((string) ($peer->country ?? '')) !== 'india') {
                    $dimensions['geography'] = 'match';
                    $reasons[] = 'International match';
                    $score += 15.0;
                } elseif (in_array('india', $selectedGeographyCodes, true)) {
                    $dimensions['geography'] = 'match';
                    $reasons[] = 'National match';
                    $score += 15.0;
                } elseif (in_array('my_city', $selectedGeographyCodes, true) && filled($peerCity)) {
                    $dimensions['geography'] = 'match';
                    $reasons[] = 'City match';
                    $score += 20.0;
                } else {
                    $dimensions['geography'] = 'partial';
                    $score += 5.0;
                }
            }

            // 3. Stage match
            if (! empty($selectedStageCodes)) {
                $dimensions['stage'] = 'match';
                $score += 10.0;
            }

            $finalScore = min(100.0, max(10.0, $score));
            $reasonText = empty($reasons) ? 'Compatible peer based on audience profile' : implode(', ', $reasons);

            $matchRecord = AskMatch::query()->updateOrCreate(
                [
                    'ask_id' => $ask->id,
                    'matched_user_id' => $peer->id,
                ],
                [
                    'match_score' => $finalScore,
                    'matched_dimensions' => $dimensions,
                    'match_reason' => $reasonText,
                    'algorithm_version' => self::ALGORITHM_VERSION,
                    'match_status' => AskMatch::STATUS_SUGGESTED,
                ]
            );

            $matches->push($matchRecord);
        }

        return $matches;
    }

    /**
     * Get paginated matched peers for an Ask.
     */
    public function getMatches(Ask $ask, array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return AskMatch::query()
            ->where('ask_id', $ask->id)
            ->when(! empty($filters['status']), function (Builder $q) use ($filters) {
                $q->where('match_status', $filters['status']);
            })
            ->with(['matchedUser.city', 'matchedUser.level4Category'])
            ->orderByDesc('match_score')
            ->paginate($perPage);
    }

    /**
     * Update match status (viewed, interested, connected, dismissed, expired).
     */
    public function updateMatchStatus(AskMatch $match, string $status): AskMatch
    {
        $updates = ['match_status' => $status];

        if ($status === AskMatch::STATUS_VIEWED && ! $match->viewed_at) {
            $updates['viewed_at'] = now();
        }

        $match->update($updates);

        return $match;
    }
}
