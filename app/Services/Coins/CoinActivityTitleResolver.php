<?php

namespace App\Services\Coins;

use App\Models\BusinessDeal;
use App\Models\P2pMeeting;
use App\Models\Referral;
use App\Models\Requirement;
use App\Models\Testimonial;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CoinActivityTitleResolver
{
    /**
     * Build a map of activity_id => human-readable title.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $activities
     */
    public function resolveTitles(Collection $activities): array
    {
        $titles = [];

        $grouped = $activities->groupBy('type');

        foreach ($grouped as $type => $activityGroup) {
            $ids = $activityGroup->pluck('id')->all();

            if (empty($ids)) {
                continue;
            }

            switch ($type) {
                case 'testimonial':
                    $records = Testimonial::whereIn('id', $ids)->get(['id', 'content']);

                    foreach ($records as $record) {
                        $titles[$record->id] = $this->normalizeTitle($record->content, 'testimonial');
                    }

                    break;

                case 'referral':
                    $records = Referral::whereIn('id', $ids)->get(['id', 'referral_of', 'referral_type', 'remarks']);

                    foreach ($records as $record) {
                        $title = $record->referral_of
                            ?? $record->referral_type
                            ?? $record->remarks;

                        $titles[$record->id] = $this->normalizeTitle($title, 'referral');
                    }

                    break;

                case 'requirement':
                    $records = Requirement::whereIn('id', $ids)->get(['id', 'subject']);

                    foreach ($records as $record) {
                        $titles[$record->id] = $this->normalizeTitle($record->subject, 'requirement');
                    }

                    break;

                case 'business_deal':
                    $records = BusinessDeal::whereIn('id', $ids)->get(['id', 'business_type', 'comment']);

                    foreach ($records as $record) {
                        $title = $record->business_type ?? $record->comment;

                        $titles[$record->id] = $this->normalizeTitle($title, 'business_deal');
                    }

                    break;

                case 'p2p_meeting':
                    $records = P2pMeeting::whereIn('id', $ids)->get(['id', 'remarks', 'meeting_place']);

                    foreach ($records as $record) {
                        $title = $record->remarks ?? $record->meeting_place;

                        $titles[$record->id] = $this->normalizeTitle($title, 'p2p_meeting');
                    }

                    break;

                default:
                    break;
            }
        }

        foreach ($activities as $activity) {
            if (! isset($titles[$activity->id])) {
                $titles[$activity->id] = $this->defaultTitle($activity->type);
            }
        }

        return $titles;
    }

    private function normalizeTitle(?string $rawValue, ?string $type): string
    {
        $value = trim((string) $rawValue);

        if ($value === '') {
            return $this->defaultTitle($type);
        }

        return Str::limit($value, 120);
    }

    public function defaultTitle(?string $type): string
    {
        return match ($type) {
            'testimonial' => 'Testimonial',
            'referral' => 'Referral',
            'requirement' => 'Requirement',
            'business_deal' => 'Business Deal',
            'p2p_meeting' => 'P2P Meeting',
            default => 'Activity',
        };
    }
}
