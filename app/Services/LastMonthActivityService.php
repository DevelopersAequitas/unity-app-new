<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BusinessDeal;
use App\Models\P2pMeeting;
use App\Models\Referral;
use App\Models\Requirement;
use App\Models\SmeBusinessStorySubmission;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\VisitorRegistration;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LastMonthActivityService
{
    /**
     * Get consolidated activity data for the authenticated user for the rolling 30-day period.
     *
     * @return array<string, mixed>
     */
    public function getActivityData(User $user, ?string $timezone = null): array
    {
        $tz = $timezone ?? ($user->timezone ?? config('app.timezone'));
        if (! $tz || ! is_string($tz) || ! in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            $tz = (string) (config('app.timezone') ?: 'UTC');
        }

        $endDate = now($tz)->endOfDay();
        $startDate = now($tz)->subDays(29)->startOfDay();
        $startStr = $startDate->format('Y-m-d');
        $endStr = $endDate->format('Y-m-d');

        // 1. P2P Meetings
        $p2pMeetings = P2pMeeting::query()
            ->where(function ($q): void {
                $q->where('is_deleted', false)
                    ->orWhereNull('is_deleted');
            })
            ->whereNull('deleted_at')
            ->where(function ($q) use ($user): void {
                $q->where('initiator_user_id', $user->id)
                    ->orWhere('peer_user_id', $user->id);
            })
            ->where(function ($q) use ($startStr, $endStr, $startDate, $endDate): void {
                $q->where(function ($q2) use ($startStr, $endStr): void {
                    $q2->whereNotNull('meeting_date')
                        ->whereDate('meeting_date', '>=', $startStr)
                        ->whereDate('meeting_date', '<=', $endStr);
                })->orWhere(function ($q2) use ($startDate, $endDate): void {
                    $q2->whereNull('meeting_date')
                        ->whereBetween('created_at', [$startDate, $endDate]);
                });
            })
            ->with(['initiator', 'peer'])
            ->orderByDesc('meeting_date')
            ->orderByDesc('created_at')
            ->get();

        $p2pItems = $p2pMeetings->map(function (P2pMeeting $meeting) use ($user): array {
            $otherPeer = $meeting->initiator_user_id === $user->id ? $meeting->peer : $meeting->initiator;
            $peerName = $otherPeer
                ? ($otherPeer->display_name ?? trim(($otherPeer->first_name ?? '').' '.($otherPeer->last_name ?? '')))
                : 'Unknown';

            $actDate = $meeting->meeting_date
                ? Carbon::parse($meeting->meeting_date)->format('Y-m-d')
                : ($meeting->created_at ? $meeting->created_at->format('Y-m-d') : '');

            return [
                'id' => (string) $meeting->id,
                'activity_date' => $actDate,
                'peer_id' => $otherPeer?->id,
                'peer_name' => $peerName,
                'meeting_date' => $meeting->meeting_date ? Carbon::parse($meeting->meeting_date)->format('Y-m-d') : '',
            ];
        })->values()->all();

        // 2. Business Deals Received
        $deals = BusinessDeal::query()
            ->where(function ($q): void {
                $q->where('is_deleted', false)
                    ->orWhereNull('is_deleted');
            })
            ->whereNull('deleted_at')
            ->where('to_user_id', $user->id)
            ->where(function ($q) use ($startStr, $endStr, $startDate, $endDate): void {
                $q->where(function ($q2) use ($startStr, $endStr): void {
                    $q2->whereNotNull('deal_date')
                        ->whereDate('deal_date', '>=', $startStr)
                        ->whereDate('deal_date', '<=', $endStr);
                })->orWhere(function ($q2) use ($startDate, $endDate): void {
                    $q2->whereNull('deal_date')
                        ->whereBetween('created_at', [$startDate, $endDate]);
                });
            })
            ->with('fromUser')
            ->orderByDesc('deal_date')
            ->orderByDesc('created_at')
            ->get();

        $dealItems = $deals->map(function (BusinessDeal $deal): array {
            $fromUser = $deal->fromUser;
            $peerName = $fromUser
                ? ($fromUser->display_name ?? trim(($fromUser->first_name ?? '').' '.($fromUser->last_name ?? '')))
                : 'Unknown';

            $actDate = $deal->deal_date
                ? Carbon::parse($deal->deal_date)->format('Y-m-d')
                : ($deal->created_at ? $deal->created_at->format('Y-m-d') : '');

            return [
                'id' => (string) $deal->id,
                'activity_date' => $actDate,
                'peer_id' => $deal->from_user_id,
                'peer_name' => $peerName,
                'amount' => (float) $deal->deal_amount,
                'deal_date' => $deal->deal_date ? Carbon::parse($deal->deal_date)->format('Y-m-d') : '',
            ];
        })->values()->all();

        // 3. Referrals Given
        $referrals = Referral::query()
            ->where(function ($q): void {
                $q->where('is_deleted', false)
                    ->orWhereNull('is_deleted');
            })
            ->whereNull('deleted_at')
            ->where('from_user_id', $user->id)
            ->where(function ($q) use ($startStr, $endStr, $startDate, $endDate): void {
                $q->where(function ($q2) use ($startStr, $endStr): void {
                    $q2->whereNotNull('referral_date')
                        ->whereDate('referral_date', '>=', $startStr)
                        ->whereDate('referral_date', '<=', $endStr);
                })->orWhere(function ($q2) use ($startDate, $endDate): void {
                    $q2->whereNull('referral_date')
                        ->whereBetween('created_at', [$startDate, $endDate]);
                });
            })
            ->with('toUser')
            ->orderByDesc('referral_date')
            ->orderByDesc('created_at')
            ->get();

        $referralItems = $referrals->map(function (Referral $referral): array {
            $toUser = $referral->toUser;
            $peerName = $toUser
                ? ($toUser->display_name ?? trim(($toUser->first_name ?? '').' '.($toUser->last_name ?? '')))
                : 'Unknown';

            $actDate = $referral->referral_date
                ? Carbon::parse($referral->referral_date)->format('Y-m-d')
                : ($referral->created_at ? $referral->created_at->format('Y-m-d') : '');

            return [
                'id' => (string) $referral->id,
                'activity_date' => $actDate,
                'peer_id' => $referral->to_user_id,
                'peer_name' => $peerName,
                'connected_with_name' => $referral->referral_of ?? '',
                'date' => $referral->referral_date ? Carbon::parse($referral->referral_date)->format('Y-m-d') : '',
            ];
        })->values()->all();

        // 4. Testimonials Given
        $testimonials = Testimonial::query()
            ->where(function ($q): void {
                $q->where('is_deleted', false)
                    ->orWhereNull('is_deleted');
            })
            ->whereNull('deleted_at')
            ->where('from_user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('toUser')
            ->orderByDesc('created_at')
            ->get();

        $testimonialItems = $testimonials->map(function (Testimonial $testimonial): array {
            $toUser = $testimonial->toUser;
            $peerName = $toUser
                ? ($toUser->display_name ?? trim(($toUser->first_name ?? '').' '.($toUser->last_name ?? '')))
                : 'Unknown';

            $actDate = $testimonial->created_at ? $testimonial->created_at->format('Y-m-d') : '';

            return [
                'id' => (string) $testimonial->id,
                'activity_date' => $actDate,
                'peer_id' => $testimonial->to_user_id,
                'peer_name' => $peerName,
                'date' => $actDate,
            ];
        })->values()->all();

        // 5. Registered Visitors
        $visitors = VisitorRegistration::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', 'rejected')
            ->where(function ($q) use ($startStr, $endStr, $startDate, $endDate): void {
                $q->where(function ($q2) use ($startStr, $endStr): void {
                    $q2->whereNotNull('event_date')
                        ->whereDate('event_date', '>=', $startStr)
                        ->whereDate('event_date', '<=', $endStr);
                })->orWhere(function ($q2) use ($startDate, $endDate): void {
                    $q2->whereNull('event_date')
                        ->whereBetween('created_at', [$startDate, $endDate]);
                });
            })
            ->orderByDesc('event_date')
            ->orderByDesc('created_at')
            ->get();

        $visitorItems = $visitors->map(function (VisitorRegistration $visitor): array {
            $visitDate = $visitor->event_date
                ? $visitor->event_date->format('Y-m-d')
                : ($visitor->created_at ? $visitor->created_at->format('Y-m-d') : '');

            return [
                'id' => (string) $visitor->id,
                'activity_date' => $visitDate,
                'visitor_name' => $visitor->visitor_full_name,
                'company_name' => $visitor->visitor_business,
                'visit_date' => $visitDate,
            ];
        })->values()->all();

        // 6. Recommended Peers
        $recommendedPeersQuery = DB::table('referraldata as rd')
            ->join('users as u', 'u.id', '=', 'rd.referred_user_id')
            ->where('rd.referrer_user_id', $user->id)
            ->whereBetween('rd.created_at', [$startDate, $endDate]);

        if (Schema::hasColumn('referraldata', 'id')) {
            $recommendedPeersQuery->select([
                'rd.id as id',
                'u.display_name as friend_name',
                'u.email',
                'rd.created_at as invite_date',
            ]);
        } else {
            $recommendedPeersQuery->select([
                'u.display_name as friend_name',
                'u.email',
                'rd.created_at as invite_date',
            ]);
        }

        $recommendedPeersItems = $recommendedPeersQuery
            ->orderByDesc('rd.created_at')
            ->get()
            ->map(function (\stdClass $row): array {
                $inviteDate = Carbon::parse($row->invite_date)->format('Y-m-d');

                return [
                    'id' => isset($row->id) ? (string) $row->id : '',
                    'activity_date' => $inviteDate,
                    'friend_name' => $row->friend_name ?? '',
                    'email' => $row->email ?? '',
                    'status' => 'Joined',
                    'invite_date' => $inviteDate,
                ];
            })
            ->values()
            ->all();

        // 7. Listed Requirements
        $requirements = Requirement::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderByDesc('created_at')
            ->get();

        $requirementItems = $requirements->map(function (Requirement $req): array {
            $actDate = $req->created_at ? $req->created_at->format('Y-m-d') : '';

            return [
                'id' => (string) $req->id,
                'activity_date' => $actDate,
                'requirement_id' => (string) $req->id,
                'requirement_title' => $req->subject,
                'created_at' => $actDate,
            ];
        })->values()->all();

        // 8. Success Story
        $story = null;
        if (Schema::hasTable('sme_business_story_submissions') && Schema::hasColumn('sme_business_story_submissions', 'user_id')) {
            $story = SmeBusinessStorySubmission::query()
                ->where('user_id', $user->id)
                ->whereRaw('LOWER(status) = ?', ['approved'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->latest('created_at')
                ->first();
        }

        $successStoryItem = $story ? [
            'id' => (string) $story->id,
            'activity_date' => $story->created_at ? $story->created_at->format('Y-m-d') : '',
            'story_id' => (string) $story->id,
            'title' => $story->title,
            'description' => $story->short_description ?? $story->story,
            'shared_date' => $story->created_at ? $story->created_at->format('Y-m-d') : '',
        ] : null;

        // Build display texts
        $p2pNames = collect($p2pItems)->pluck('peer_name')->toArray();
        $dealNames = collect($dealItems)->pluck('peer_name')->toArray();
        $refTexts = collect($referralItems)->map(function (array $item): string {
            return $item['connected_with_name'] !== ''
                ? "{$item['connected_with_name']} (to {$item['peer_name']})"
                : $item['peer_name'];
        })->toArray();
        $testimonialNames = collect($testimonialItems)->pluck('peer_name')->toArray();
        $visitorNames = collect($visitorItems)->pluck('visitor_name')->toArray();
        $friendNames = collect($recommendedPeersItems)->pluck('friend_name')->toArray();
        $reqTitles = collect($requirementItems)->pluck('requirement_title')->toArray();

        return [
            'user' => [
                'user_id' => $user->id,
                'display_name' => $user->display_name ?? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?? $user->email,
                'business_name' => $user->company_name,
                'profile_photo_url' => $user->profile_photo_file_id
                    ? url("/api/v1/files/{$user->profile_photo_file_id}")
                    : null,
            ],
            'period' => [
                'start_date' => $startStr,
                'end_date' => $endStr,
                'total_days' => 30,
            ],
            'activities' => [
                'p2p_meetings' => [
                    'count' => count($p2pItems),
                    'items' => $p2pItems,
                    'display_text' => $this->buildDisplayText($p2pNames),
                ],
                'business_deals_received' => [
                    'count' => count($dealItems),
                    'items' => $dealItems,
                    'display_text' => $this->buildDisplayText($dealNames),
                ],
                'referrals_given' => [
                    'count' => count($referralItems),
                    'items' => $referralItems,
                    'display_text' => $this->buildDisplayText($refTexts),
                ],
                'testimonials_given' => [
                    'count' => count($testimonialItems),
                    'items' => $testimonialItems,
                    'display_text' => $this->buildDisplayText($testimonialNames),
                ],
                'registered_visitors' => [
                    'count' => count($visitorItems),
                    'items' => $visitorItems,
                    'display_text' => $this->buildDisplayText($visitorNames),
                ],
                'recommended_peers' => [
                    'count' => count($recommendedPeersItems),
                    'items' => $recommendedPeersItems,
                    'display_text' => $this->buildDisplayText($friendNames),
                ],
                'listed_requirements' => [
                    'count' => count($requirementItems),
                    'items' => $requirementItems,
                    'display_text' => $this->buildDisplayText($reqTitles),
                ],
                'success_story' => $successStoryItem,
            ],
        ];
    }

    /**
     * Helper to build dynamic display text from a list of names.
     *
     * @param  array<int, string>  $names
     */
    private function buildDisplayText(array $names): string
    {
        $count = count($names);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return $names[0];
        }
        if ($count === 2) {
            return $names[0].' and '.$names[1];
        }
        $last = array_pop($names);

        return implode(', ', $names).' and '.$last;
    }
}
