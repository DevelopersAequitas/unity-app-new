<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ask\Ask;
use App\Models\BusinessDeal;
use App\Models\EventRegistration;
use App\Models\P2pMeeting;
use App\Models\PeerRecommendation;
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
    public function getActivityData(User $user, ?string $timezone = null, ?int $month = null, ?int $year = null): array
    {
        $tz = $timezone ?? ($user->timezone ?? config('app.timezone'));
        if (! $tz || ! is_string($tz) || ! in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            $tz = (string) (config('app.timezone') ?: 'UTC');
        }

        if ($month !== null && $year !== null) {
            $startDate = Carbon::createFromDate($year, $month, 1, $tz)->startOfDay();
            $endDate = $startDate->copy()->endOfMonth()->endOfDay();
            $totalDays = (int) $startDate->daysInMonth;
        } else {
            $endDate = now($tz)->endOfDay();
            $startDate = now($tz)->subDays(29)->startOfDay();
            $totalDays = 30;
        }

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

        // 2b. Business Deals Given
        $dealsGiven = BusinessDeal::query()
            ->where(function ($q): void {
                $q->where('is_deleted', false)
                    ->orWhereNull('is_deleted');
            })
            ->whereNull('deleted_at')
            ->where('from_user_id', $user->id)
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
            ->with('toUser')
            ->orderByDesc('deal_date')
            ->orderByDesc('created_at')
            ->get();

        $dealGivenItems = $dealsGiven->map(function (BusinessDeal $deal): array {
            $toUser = $deal->toUser;
            $peerName = $toUser
                ? ($toUser->display_name ?? trim(($toUser->first_name ?? '').' '.($toUser->last_name ?? '')))
                : 'Unknown';

            $actDate = $deal->deal_date
                ? Carbon::parse($deal->deal_date)->format('Y-m-d')
                : ($deal->created_at ? $deal->created_at->format('Y-m-d') : '');

            return [
                'id' => (string) $deal->id,
                'activity_date' => $actDate,
                'peer_id' => $deal->to_user_id,
                'peer_name' => $peerName,
                'amount' => (float) $deal->deal_amount,
                'comment' => $deal->comment ?? '',
                'deal_type' => $deal->business_type ?? '',
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

        // 5. Registered Visitors / Visitor Registrations
        $visitorItems = [];
        if (Schema::hasTable('visitor_registrations')) {
            $visitorQuery = VisitorRegistration::query()
                ->where(function ($q) use ($user): void {
                    $q->where('user_id', $user->id);
                    if (Schema::hasColumn('visitor_registrations', 'invited_by_user_id')) {
                        $q->orWhere('invited_by_user_id', $user->id);
                    }
                    if (Schema::hasColumn('visitor_registrations', 'created_by')) {
                        $q->orWhere('created_by', $user->id);
                    }
                })
                ->where(function ($sq): void {
                    $sq->whereNull('status')
                        ->orWhereRaw('LOWER(status) != ?', ['rejected']);
                })
                ->where(function ($q) use ($startStr, $endStr, $startDate, $endDate): void {
                    $q->whereBetween('created_at', [$startDate, $endDate])
                        ->orWhere(function ($q2) use ($startStr, $endStr): void {
                            $q2->whereNotNull('event_date')
                                ->whereDate('event_date', '>=', $startStr)
                                ->whereDate('event_date', '<=', $endStr);
                        });
                });

            if (Schema::hasColumn('visitor_registrations', 'is_deleted')) {
                $visitorQuery->where(function ($q): void {
                    $q->where('is_deleted', false)->orWhereNull('is_deleted');
                });
            }

            if (Schema::hasColumn('visitor_registrations', 'deleted_at')) {
                $visitorQuery->whereNull('deleted_at');
            }

            $visitors = $visitorQuery
                ->orderByDesc('event_date')
                ->orderByDesc('created_at')
                ->get();

            $visitorItems = $visitors->map(function (VisitorRegistration $visitor): array {
                $actDate = $visitor->created_at ? Carbon::parse($visitor->created_at)->format('Y-m-d') : '';
                $eventDate = $visitor->event_date ? Carbon::parse($visitor->event_date)->format('Y-m-d') : '';
                $visitDate = $eventDate !== '' ? $eventDate : $actDate;

                return [
                    'id' => (string) $visitor->id,
                    'activity_date' => $actDate !== '' ? $actDate : $visitDate,
                    'visitor_name' => (string) ($visitor->visitor_full_name ?? ''),
                    'visitor_full_name' => (string) ($visitor->visitor_full_name ?? ''),
                    'company_name' => (string) ($visitor->visitor_business ?? ''),
                    'visitor_business' => (string) ($visitor->visitor_business ?? ''),
                    'visitor_mobile' => (string) ($visitor->visitor_mobile ?? ''),
                    'visitor_city' => (string) ($visitor->visitor_city ?? ''),
                    'event_name' => (string) ($visitor->event_name ?? ''),
                    'event_date' => $eventDate,
                    'visit_date' => $visitDate,
                    'status' => (string) ($visitor->status ?? 'pending'),
                ];
            })->values()->all();
        }

        if (Schema::hasTable('event_registrations') && Schema::hasColumn('event_registrations', 'visitor_name')) {
            $eventVisitorQuery = EventRegistration::query()
                ->where(function ($q) use ($user): void {
                    $hasCol = false;
                    if (Schema::hasColumn('event_registrations', 'invited_by_user_id')) {
                        $q->where('invited_by_user_id', $user->id);
                        $hasCol = true;
                    }
                    if (Schema::hasColumn('event_registrations', 'user_id')) {
                        $method = $hasCol ? 'orWhere' : 'where';
                        $q->{$method}(function ($uq) use ($user): void {
                            $uq->where('user_id', $user->id)
                                ->where(function ($vt): void {
                                    $vt->where('registration_type', 'visitor')
                                        ->orWhereNotNull('visitor_name');
                                });
                        });
                        $hasCol = true;
                    }
                    if (! $hasCol) {
                        $q->whereRaw('1=0');
                    }
                })
                ->whereNotNull('visitor_name')
                ->where('visitor_name', '!=', '')
                ->where(function ($sq): void {
                    $sq->whereNull('status')
                        ->orWhereNotIn(DB::raw('LOWER(status)'), ['rejected', 'cancelled']);
                })
                ->where(function ($q) use ($startStr, $endStr, $startDate, $endDate): void {
                    $q->whereBetween('created_at', [$startDate, $endDate])
                        ->orWhere(function ($q2) use ($startStr, $endStr): void {
                            if (Schema::hasColumn('event_registrations', 'registered_at')) {
                                $q2->whereNotNull('registered_at')
                                    ->whereDate('registered_at', '>=', $startStr)
                                    ->whereDate('registered_at', '<=', $endStr);
                            }
                        });
                });

            if (Schema::hasColumn('event_registrations', 'deleted_at')) {
                $eventVisitorQuery->whereNull('deleted_at');
            }

            $eventVisitors = $eventVisitorQuery->with(['event'])->get();
            $existingVisitorNames = collect($visitorItems)->pluck('visitor_name')->map(fn ($n) => strtolower(trim((string) $n)))->all();

            foreach ($eventVisitors as $ev) {
                $vName = (string) ($ev->visitor_name ?? '');
                if ($vName !== '' && in_array(strtolower(trim($vName)), $existingVisitorNames, true)) {
                    continue;
                }

                $regDate = $ev->registered_at
                    ? Carbon::parse($ev->registered_at)->format('Y-m-d')
                    : ($ev->created_at ? Carbon::parse($ev->created_at)->format('Y-m-d') : '');

                $visitorItems[] = [
                    'id' => (string) $ev->id,
                    'activity_date' => $regDate,
                    'visitor_name' => $vName,
                    'visitor_full_name' => $vName,
                    'company_name' => (string) ($ev->visitor_company ?? ''),
                    'visitor_business' => (string) ($ev->visitor_company ?? ''),
                    'visitor_mobile' => (string) ($ev->visitor_phone ?? ''),
                    'visitor_city' => (string) ($ev->visitor_city ?? ''),
                    'event_name' => (string) ($ev->event?->name ?? 'Event'),
                    'event_date' => $regDate,
                    'visit_date' => $regDate,
                    'status' => (string) ($ev->status ?? 'pending'),
                ];
            }
        }

        // 6. Recommended Peers
        $recommendedPeersItems = [];

        if (Schema::hasTable('peer_recommendations')) {
            $peerRecs = PeerRecommendation::query()
                ->where('user_id', (string) $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderByDesc('created_at')
                ->get();

            foreach ($peerRecs as $rec) {
                $inviteDate = $rec->created_at ? Carbon::parse($rec->created_at)->format('Y-m-d') : '';
                $recommendedPeersItems[] = [
                    'id' => (string) $rec->id,
                    'activity_date' => $inviteDate,
                    'friend_name' => (string) ($rec->peer_name ?? ''),
                    'email' => (string) ($rec->peer_email ?? ''),
                    'status' => (string) ($rec->status ?? 'Recommended'),
                    'invite_date' => $inviteDate,
                ];
            }
        }

        if (Schema::hasTable('referraldata')) {
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

            $referralDataItems = $recommendedPeersQuery
                ->orderByDesc('rd.created_at')
                ->get()
                ->map(function (\stdClass $row): array {
                    $inviteDate = Carbon::parse($row->invite_date)->format('Y-m-d');

                    return [
                        'id' => isset($row->id) ? (string) $row->id : '',
                        'activity_date' => $inviteDate,
                        'friend_name' => (string) ($row->friend_name ?? ''),
                        'email' => (string) ($row->email ?? ''),
                        'status' => 'Joined',
                        'invite_date' => $inviteDate,
                    ];
                })
                ->values()
                ->all();

            $recommendedPeersItems = array_merge($recommendedPeersItems, $referralDataItems);
        }

        // 7. Listed Requirements
        $requirementItems = [];

        if (Schema::hasTable('requirements')) {
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
                    'requirement_title' => (string) ($req->subject ?? ''),
                    'created_at' => $actDate,
                ];
            })->values()->all();
        }

        if (Schema::hasTable('asks')) {
            $asks = Ask::query()
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderByDesc('created_at')
                ->get();

            $askItems = $asks->map(function (Ask $ask): array {
                $actDate = $ask->created_at ? $ask->created_at->format('Y-m-d') : '';

                return [
                    'id' => (string) $ask->id,
                    'activity_date' => $actDate,
                    'requirement_id' => (string) $ask->id,
                    'requirement_title' => (string) ($ask->title ?? ''),
                    'created_at' => $actDate,
                ];
            })->values()->all();

            $requirementItems = array_merge($requirementItems, $askItems);
        }

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
            'title' => (string) ($story->title ?? ''),
            'description' => (string) ($story->short_description ?? $story->story ?? ''),
            'shared_date' => $story->created_at ? $story->created_at->format('Y-m-d') : '',
        ] : null;

        // Build display texts
        $p2pNames = collect($p2pItems)->pluck('peer_name')->toArray();
        $dealNames = collect($dealItems)->pluck('peer_name')->toArray();
        $dealGivenNames = collect($dealGivenItems)->pluck('peer_name')->toArray();
        $refTexts = collect($referralItems)->map(function (array $item): string {
            return $item['connected_with_name'] !== ''
                ? "{$item['connected_with_name']} (to {$item['peer_name']})"
                : $item['peer_name'];
        })->toArray();
        $testimonialNames = collect($testimonialItems)->pluck('peer_name')->toArray();
        $visitorNames = collect($visitorItems)->pluck('visitor_name')->filter()->unique()->values()->toArray();
        $friendNames = collect($recommendedPeersItems)->pluck('friend_name')->toArray();
        $reqTitles = collect($requirementItems)->pluck('requirement_title')->toArray();

        $userDisplayName = $user->display_name ?: (trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: (string) $user->email);

        $visitorActivityPayload = [
            'count' => count($visitorItems),
            'items' => $visitorItems,
            'display_text' => $this->buildDisplayText($visitorNames),
        ];

        return [
            'user' => [
                'user_id' => $user->id,
                'display_name' => $userDisplayName,
                'business_name' => $user->company_name,
                'profile_photo_url' => $user->profile_photo_file_id
                    ? url("/api/v1/files/{$user->profile_photo_file_id}")
                    : null,
            ],
            'period' => [
                'start_date' => $startStr,
                'end_date' => $endStr,
                'total_days' => $totalDays,
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
                'business_deals_given' => [
                    'count' => count($dealGivenItems),
                    'items' => $dealGivenItems,
                    'display_text' => $this->buildDisplayText($dealGivenNames),
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
                'visitor_registrations' => $visitorActivityPayload,
                'registered_visitors' => $visitorActivityPayload,
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
