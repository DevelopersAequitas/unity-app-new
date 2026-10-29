<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Activity;
use App\Models\BusinessDeal;
use App\Models\CoinClaimRequest;
use App\Models\CoinLedger;
use App\Models\Event;
use App\Models\LifeImpactHistory;
use App\Models\P2pMeeting;
use App\Models\Referral;
use App\Models\ReferralCampaignConfig;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AiChatbotToolsService
{
    public function __construct(
        protected AiKnowledgeRagService $ragService
    ) {}

    /**
     * Step A: Fast User Context Pre-Injection (~10ms).
     */
    public function buildUserContext(?User $user): string
    {
        if (! $user) {
            return "CURRENT USER CONTEXT:\n- Guest / Unauthenticated Peer";
        }

        $name = trim((string) ($user->display_name ?: ($user->first_name.' '.$user->last_name))) ?: 'Peer';
        $city = (string) ($user->city ?: ($user->business_city ?: 'Not specified'));
        $category = (string) ($user->business_sub_category ?: ($user->designation ?: 'Not specified'));
        $coins = (int) ($user->coins_balance ?? 0);
        $impact = (int) ($user->life_impacted_count ?? 0);

        $circleName = 'None';
        if (Schema::hasTable('circle_members') && Schema::hasTable('circles')) {
            $circle = DB::table('circle_members')
                ->join('circles', 'circles.id', '=', 'circle_members.circle_id')
                ->where('circle_members.user_id', $user->id)
                ->value('circles.name');
            if ($circle) {
                $circleName = (string) $circle;
            }
        }

        return <<< CONTEXT
CURRENT USER CONTEXT:
- Name: {$name}
- City: {$city}
- Business Category: {$category}
- Coins Balance: {$coins}
- Lives Impacted Score: {$impact}
- Active Circle: {$circleName}
CONTEXT;
    }

    /**
     * Step B: Return Gemini functionDeclarations filtered by enabled_tools_json.
     *
     * @param  array<int, string>  $enabledTools
     * @return array<int, array<string, mixed>>
     */
    public function getFunctionDeclarations(array $enabledTools): array
    {
        $declarations = [];

        if ($this->isToolEnabled('search_docs', 'search_knowledge_docs', $enabledTools)) {
            $declarations[] = [
                'name' => 'search_knowledge_docs',
                'description' => 'Search official Peers Global knowledge documents, rules, policies, Coins/Lives Impacted guidelines, Unity Circles, and Speed Networking procedures.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'query' => [
                            'type' => 'STRING',
                            'description' => 'Search query for official guidelines or FAQ.',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ];
        }

        if ($this->isToolEnabled('find_peers', 'search_peers', $enabledTools)) {
            $declarations[] = [
                'name' => 'search_peers',
                'description' => 'Find active Peers Global peers by business category, city, or keyword.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'category' => [
                            'type' => 'STRING',
                            'description' => 'Business category, industry, or profession (e.g. Real Estate, IT, Manufacturing).',
                        ],
                        'city' => [
                            'type' => 'STRING',
                            'description' => 'City name (e.g. Ahmedabad, Mumbai, Surat).',
                        ],
                        'keyword' => [
                            'type' => 'STRING',
                            'description' => 'Name, company name, or skill keyword.',
                        ],
                    ],
                ],
            ];
        }

        if ($this->isToolEnabled('get_events', 'get_upcoming_events', $enabledTools)) {
            $declarations[] = [
                'name' => 'get_upcoming_events',
                'description' => 'Get upcoming Peers Global events and meetings, optionally filtered by city.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'city' => [
                            'type' => 'STRING',
                            'description' => 'Optional city filter for upcoming events.',
                        ],
                    ],
                ],
            ];
        }

        if ($this->isToolEnabled('get_speed_networking', 'get_active_speed_networking', $enabledTools)) {
            $declarations[] = [
                'name' => 'get_active_speed_networking',
                'description' => 'Get scheduled or live Speed Networking sessions.',
            ];
        }

        if ($this->isToolEnabled('get_my_stats', 'get_referral_and_impact_summary', $enabledTools)) {
            $declarations[] = [
                'name' => 'get_referral_and_impact_summary',
                'description' => 'Get the logged-in peer complete activity summary AND detailed history lists: Coins balance, recent Coin Ledger transaction history, Coin Claims history, Lives Impacted score & history, P2P meetings history, Referrals given/received history, Business Deals history, Testimonials history, and active referral campaign reward.',
            ];
        }

        return $declarations;
    }

    /**
     * Execute a single tool call requested by Gemini and collect any UI attachments.
     *
     * @param  array<string, mixed>  $args
     * @param  array<int, array<string, mixed>>  $collectedAttachments
     * @return array<string, mixed>
     */
    public function executeTool(string $name, array $args, ?User $user, array &$collectedAttachments): array
    {
        return match ($name) {
            'search_knowledge_docs' => [
                'chunks' => $this->ragService->searchKnowledgeDocs((string) ($args['query'] ?? '')),
            ],
            'search_peers' => $this->searchPeers($args, $user, $collectedAttachments),
            'get_upcoming_events' => $this->getUpcomingEvents($args, $user, $collectedAttachments),
            'get_active_speed_networking' => $this->getActiveSpeedNetworking($collectedAttachments),
            'get_referral_and_impact_summary' => $this->getReferralAndImpactSummary($user, $collectedAttachments),
            default => ['error' => "Unknown tool: {$name}"],
        };
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<int, array<string, mixed>>  $collectedAttachments
     * @return array<string, mixed>
     */
    private function searchPeers(array $args, ?User $user, array &$collectedAttachments): array
    {
        if (! Schema::hasTable('users')) {
            return ['peers' => []];
        }

        $category = trim((string) ($args['category'] ?? ''));
        $city = trim((string) ($args['city'] ?? ''));
        $keyword = trim((string) ($args['keyword'] ?? ''));

        $query = User::query()
            ->when($user !== null, fn ($q) => $q->where('id', '!=', $user->id))
            ->when($city !== '', function ($q) use ($city): void {
                $q->where(function ($sub) use ($city): void {
                    $sub->where('city', 'like', "%{$city}%")
                        ->orWhere('business_city', 'like', "%{$city}%");
                });
            })
            ->when($category !== '', function ($q) use ($category): void {
                $q->where(function ($sub) use ($category): void {
                    $sub->where('business_sub_category', 'like', "%{$category}%")
                        ->orWhere('designation', 'like', "%{$category}%")
                        ->orWhere('company_name', 'like', "%{$category}%")
                        ->orWhere('short_bio', 'like', "%{$category}%");
                });
            })
            ->when($keyword !== '', function ($q) use ($keyword): void {
                $q->where(function ($sub) use ($keyword): void {
                    $sub->where('display_name', 'like', "%{$keyword}%")
                        ->orWhere('first_name', 'like', "%{$keyword}%")
                        ->orWhere('last_name', 'like', "%{$keyword}%")
                        ->orWhere('company_name', 'like', "%{$keyword}%")
                        ->orWhere('designation', 'like', "%{$keyword}%");
                });
            })
            ->limit(5)
            ->get();

        $peers = [];
        $cardItems = [];

        foreach ($query as $peer) {
            $peerName = trim((string) ($peer->display_name ?: ($peer->first_name.' '.$peer->last_name))) ?: 'Peer';
            $company = (string) ($peer->company_name ?: 'Peers Global');
            $role = (string) ($peer->business_sub_category ?: ($peer->designation ?: 'Peer'));
            $peerCity = (string) ($peer->city ?: ($peer->business_city ?: ''));

            $peers[] = [
                'id' => (string) $peer->id,
                'name' => $peerName,
                'company' => $company,
                'category' => $role,
                'city' => $peerCity,
            ];

            $cardItems[] = [
                'id' => (string) $peer->id,
                'title' => $peerName,
                'subtitle' => trim($role.' • '.$company, ' •'),
                'meta' => $peerCity,
                'image_url' => $peer->profile_photo_url,
                'route' => '/peer-profile',
                'params' => (string) $peer->id,
            ];
        }

        if ($cardItems !== []) {
            $collectedAttachments[] = [
                'type' => 'member_list',
                'title' => 'Matching Peers',
                'items' => $cardItems,
            ];
        }

        $collectedAttachments[] = [
            'type' => 'action_buttons',
            'items' => [
                [
                    'id' => 'act_explore_peers',
                    'title' => 'Explore All Peers',
                    'route' => '/peers',
                ],
            ],
        ];

        return ['peers' => $peers];
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<int, array<string, mixed>>  $collectedAttachments
     * @return array<string, mixed>
     */
    private function getUpcomingEvents(array $args, ?User $user, array &$collectedAttachments): array
    {
        if (! Schema::hasTable('events')) {
            return ['events' => []];
        }

        $requestedCity = trim((string) ($args['city'] ?? ''));
        $userCity = trim((string) ($user?->city ?: ($user?->business_city ?? '')));
        $targetCity = $requestedCity !== '' ? $requestedCity : $userCity;

        $baseQuery = Event::query()
            ->where('start_at', '>=', now()->subHours(2))
            ->orderBy('start_at', 'asc');

        $matchedEvents = collect();
        $usedFallbackCity = false;

        if ($targetCity !== '') {
            $matchedEvents = (clone $baseQuery)
                ->where(function ($q) use ($targetCity): void {
                    $q->where('location_text', 'like', "%{$targetCity}%")
                        ->orWhere('title', 'like', "%{$targetCity}%")
                        ->orWhere('state_name', 'like', "%{$targetCity}%");
                })
                ->limit(5)
                ->get();
        }

        if ($matchedEvents->isEmpty()) {
            $matchedEvents = (clone $baseQuery)->limit(5)->get();
            if ($targetCity !== '' && $matchedEvents->isNotEmpty()) {
                $usedFallbackCity = true;
            }
        }

        $events = [];
        $eventCards = [];
        $actionButtons = [];

        foreach ($matchedEvents as $e) {
            $location = (string) ($e->location_text ?? ($e->is_virtual ? 'Online / Virtual' : 'Ahmedabad'));
            $dateFormatted = $e->start_at?->format('d M Y, h:i A') ?? '';
            $occurrenceId = '';
            try {
                if (Schema::hasTable('event_occurrences')) {
                    $occurrenceId = (string) ($e->occurrences()->orderBy('start_at', 'asc')->value('id') ?? '');
                }
            } catch (\Throwable) {
            }

            $events[] = [
                'id' => (string) $e->id,
                'event_id' => (string) $e->id,
                'occurrence_id' => $occurrenceId,
                'title' => (string) $e->title,
                'start_at' => $e->start_at?->toIso8601String(),
                'date_display' => $dateFormatted,
                'location' => $location,
                'is_virtual' => (bool) $e->is_virtual,
            ];

            $eventParams = [
                'event_id' => (string) $e->id,
                'occurrence_id' => $occurrenceId,
                'title' => (string) $e->title,
            ];

            $eventCards[] = [
                'id' => (string) $e->id,
                'title' => (string) $e->title,
                'subtitle' => $location,
                'meta' => $dateFormatted,
                'image_url' => $e->banner_url,
                'route' => '/event-detail',
                'params' => $eventParams,
                'button_label' => 'Register / View Event',
            ];

            if (count($actionButtons) < 2) {
                $actionButtons[] = [
                    'id' => (string) $e->id,
                    'title' => 'Register: '.mb_substr((string) $e->title, 0, 24),
                    'route' => '/event-detail',
                    'params' => $eventParams,
                ];
            }
        }

        if ($eventCards !== []) {
            $collectedAttachments[] = [
                'type' => 'event_list',
                'title' => $usedFallbackCity ? 'Upcoming Events in Other / Nearby Cities' : 'Upcoming Events',
                'items' => $eventCards,
            ];
        }

        $actionButtons[] = [
            'id' => 'act_events',
            'title' => 'View All Events',
            'route' => '/events',
        ];

        $collectedAttachments[] = [
            'type' => 'action_buttons',
            'items' => $actionButtons,
        ];

        return [
            'peer_city' => $userCity !== '' ? $userCity : null,
            'searched_city' => $targetCity !== '' ? $targetCity : null,
            'no_events_in_searched_city' => $usedFallbackCity,
            'assistant_guidance' => $usedFallbackCity
                ? "There are currently no upcoming events in {$targetCity}, but we have upcoming events in nearby/other cities (such as Ahmedabad) listed below. Explicitly mention the peer's city ({$targetCity}) and invite them to register for these events."
                : "Found upcoming events matching {$targetCity}. Invite the peer to tap the event to view details and register directly.",
            'events' => $events,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $collectedAttachments
     * @return array<string, mixed>
     */
    private function getActiveSpeedNetworking(array &$collectedAttachments): array
    {
        if (! Schema::hasTable('speed_networking_events')) {
            return ['sessions' => []];
        }

        $sessions = SpeedNetworkingEvent::query()
            ->whereIn('status', [
                SpeedNetworkingEvent::STATUS_SCHEDULED,
                SpeedNetworkingEvent::STATUS_LOBBY_OPEN,
                SpeedNetworkingEvent::STATUS_IN_PROGRESS,
            ])
            ->orderBy('scheduled_start_time', 'asc')
            ->limit(5)
            ->get()
            ->map(fn (SpeedNetworkingEvent $s): array => [
                'id' => (string) $s->id,
                'title' => (string) $s->title,
                'status' => (string) $s->status,
                'scheduled_start_time' => $s->scheduled_start_time?->toIso8601String(),
                'total_rounds' => (int) $s->total_rounds,
                'round_duration_seconds' => (int) $s->round_duration_seconds,
            ])
            ->values()
            ->all();

        $collectedAttachments[] = [
            'type' => 'action_buttons',
            'items' => [
                [
                    'id' => 'act_speed_networking',
                    'title' => 'Open Speed Networking',
                    'route' => '/speed-networking',
                ],
            ],
        ];

        return ['sessions' => $sessions];
    }

    /**
     * @param  array<int, array<string, mixed>>  $collectedAttachments
     * @return array<string, mixed>
     */
    private function getReferralAndImpactSummary(?User $user, array &$collectedAttachments): array
    {
        $referralsGivenCount = 0;
        $referralsReceivedCount = 0;
        $p2pCount = 0;
        $businessDealsCount = 0;
        $testimonialsCount = 0;
        $rewardTitle = null;

        $recentCoinHistory = [];
        $recentCoinClaims = [];
        $recentLifeImpactHistory = [];
        $recentP2pMeetings = [];
        $recentReferrals = [];
        $recentBusinessDeals = [];
        $recentTestimonials = [];

        if ($user) {
            // 1. Coin Ledger History (coins_ledger)
            try {
                if (Schema::hasTable('coins_ledger')) {
                    $ledgerRows = CoinLedger::query()
                        ->where('user_id', $user->id)
                        ->orderByDesc('created_at')
                        ->limit(15)
                        ->get();

                    $activityIds = $ledgerRows->pluck('activity_id')->filter()->unique()->values();
                    $activities = ($activityIds->isNotEmpty() && Schema::hasTable('activities'))
                        ? Activity::query()->whereIn('id', $activityIds)->get()->keyBy('id')
                        : collect();

                    foreach ($ledgerRows as $row) {
                        $activity = $row->activity_id ? $activities->get($row->activity_id) : null;
                        $delta = (int) ($row->coins_delta ?? $row->amount ?? 0);
                        $rawReason = (string) ($row->remark ?: ($row->reference ?: ($row->source_type ?: ($activity?->type ?: 'Coins Activity'))));
                        $reasonLabel = Str::of($rawReason)->replace(['_', '-'], ' ')->trim()->headline()->toString();
                        if ($activity && filled($activity->description)) {
                            $reasonLabel .= ' - '.$activity->description;
                        }

                        $recentCoinHistory[] = [
                            'coins_change' => ($delta >= 0 ? '+' : '').$delta,
                            'coins_delta' => $delta,
                            'balance_after' => $row->balance_after !== null ? (int) $row->balance_after : null,
                            'reason' => $reasonLabel ?: 'Coins Credited',
                            'activity_type' => $activity?->type ?? $row->source_type,
                            'date' => $row->created_at?->format('d M Y, h:i A') ?? '',
                        ];
                    }
                }
            } catch (\Throwable) {
            }

            // 2. Coin Claim Requests (coin_claim_requests)
            try {
                if (Schema::hasTable('coin_claim_requests')) {
                    $claimRows = CoinClaimRequest::query()
                        ->where('user_id', $user->id)
                        ->orderByDesc('created_at')
                        ->limit(10)
                        ->get();

                    foreach ($claimRows as $claim) {
                        $recentCoinClaims[] = [
                            'activity' => Str::of((string) $claim->activity_code)->replace(['_', '-'], ' ')->headline()->toString(),
                            'status' => (string) $claim->status,
                            'coins_awarded' => (int) ($claim->coins_awarded ?? 0),
                            'date' => $claim->created_at?->format('d M Y, h:i A') ?? '',
                        ];
                    }
                }
            } catch (\Throwable) {
            }

            // 3. Life Impact History (life_impact_histories)
            try {
                if (Schema::hasTable('life_impact_histories')) {
                    $impactRows = LifeImpactHistory::query()
                        ->where('user_id', (string) $user->id)
                        ->with('triggeredByUser:id,first_name,last_name,display_name')
                        ->orderByDesc('created_at')
                        ->limit(10)
                        ->get();

                    foreach ($impactRows as $imp) {
                        $trigPeer = $imp->triggeredByUser;
                        $peerName = $trigPeer
                            ? trim((string) ($trigPeer->display_name ?: ($trigPeer->first_name.' '.$trigPeer->last_name)))
                            : null;
                        $recentLifeImpactHistory[] = [
                            'impact_points' => '+'.$imp->resolveImpactValue(),
                            'activity_type' => Str::of((string) ($imp->action_label ?: $imp->title ?: $imp->activity_type))->replace(['_', '-'], ' ')->headline()->toString(),
                            'with_peer' => $peerName ?: null,
                            'remarks' => (string) ($imp->remarks ?: ($imp->description ?? '')),
                            'date' => $imp->created_at?->format('d M Y') ?? '',
                        ];
                    }
                }
            } catch (\Throwable) {
            }

            // 4. Referrals (referrals)
            try {
                if (Schema::hasTable('referrals')) {
                    $referralsGivenCount = Referral::query()->where('from_user_id', $user->id)->count();
                    $referralsReceivedCount = Referral::query()->where('to_user_id', $user->id)->count();

                    $refRows = Referral::query()
                        ->where(function ($q) use ($user): void {
                            $q->where('from_user_id', $user->id)
                                ->orWhere('to_user_id', $user->id);
                        })
                        ->with([
                            'fromUser:id,first_name,last_name,display_name',
                            'toUser:id,first_name,last_name,display_name',
                        ])
                        ->orderByDesc('created_at')
                        ->limit(10)
                        ->get();

                    foreach ($refRows as $ref) {
                        $isGiven = (string) $ref->from_user_id === (string) $user->id;
                        $otherPeer = $isGiven ? $ref->toUser : $ref->fromUser;
                        $peerName = $otherPeer
                            ? trim((string) ($otherPeer->display_name ?: ($otherPeer->first_name.' '.$otherPeer->last_name)))
                            : 'Peer';
                        $recentReferrals[] = [
                            'direction' => $isGiven ? 'Given to' : 'Received from',
                            'peer_name' => $peerName,
                            'referral_of' => (string) ($ref->referral_of ?? ''),
                            'referral_type' => (string) ($ref->referral_type ?? ''),
                            'remarks' => (string) ($ref->remarks ?? ''),
                            'date' => $ref->referral_date ?: ($ref->created_at?->format('d M Y') ?? ''),
                        ];
                    }
                }
            } catch (\Throwable) {
            }

            // 5. P2P Meetings (p2p_meetings)
            try {
                if (Schema::hasTable('p2p_meetings')) {
                    $p2pCount = P2pMeeting::query()
                        ->where(function ($q) use ($user): void {
                            $q->where('initiator_user_id', $user->id)
                                ->orWhere('peer_user_id', $user->id);
                        })
                        ->count();

                    $p2pRows = P2pMeeting::query()
                        ->where(function ($q) use ($user): void {
                            $q->where('initiator_user_id', $user->id)
                                ->orWhere('peer_user_id', $user->id);
                        })
                        ->with([
                            'initiator:id,first_name,last_name,display_name',
                            'peer:id,first_name,last_name,display_name',
                        ])
                        ->orderByDesc('meeting_date')
                        ->orderByDesc('created_at')
                        ->limit(10)
                        ->get();

                    foreach ($p2pRows as $m) {
                        $isInitiator = (string) $m->initiator_user_id === (string) $user->id;
                        $otherPeer = $isInitiator ? $m->peer : $m->initiator;
                        $peerName = $otherPeer
                            ? trim((string) ($otherPeer->display_name ?: ($otherPeer->first_name.' '.$otherPeer->last_name)))
                            : 'Peer';
                        $recentP2pMeetings[] = [
                            'with_peer' => $peerName,
                            'meeting_date' => (string) ($m->meeting_date ?: ($m->created_at?->format('d M Y') ?? '')),
                            'meeting_place' => (string) ($m->meeting_place ?? ''),
                            'remarks' => (string) ($m->remarks ?? ''),
                        ];
                    }
                }
            } catch (\Throwable) {
            }

            // 6. Business Deals (business_deals)
            try {
                if (Schema::hasTable('business_deals')) {
                    $businessDealsCount = BusinessDeal::query()
                        ->where(function ($q) use ($user): void {
                            $q->where('from_user_id', $user->id)
                                ->orWhere('to_user_id', $user->id);
                        })
                        ->count();

                    $dealRows = BusinessDeal::query()
                        ->where(function ($q) use ($user): void {
                            $q->where('from_user_id', $user->id)
                                ->orWhere('to_user_id', $user->id);
                        })
                        ->with([
                            'fromUser:id,first_name,last_name,display_name',
                            'toUser:id,first_name,last_name,display_name',
                        ])
                        ->orderByDesc('deal_date')
                        ->orderByDesc('created_at')
                        ->limit(10)
                        ->get();

                    foreach ($dealRows as $deal) {
                        $isFrom = (string) $deal->from_user_id === (string) $user->id;
                        $otherPeer = $isFrom ? $deal->toUser : $deal->fromUser;
                        $peerName = $otherPeer
                            ? trim((string) ($otherPeer->display_name ?: ($otherPeer->first_name.' '.$otherPeer->last_name)))
                            : 'Peer';
                        $recentBusinessDeals[] = [
                            'with_peer' => $peerName,
                            'deal_amount' => $deal->deal_amount,
                            'business_type' => (string) ($deal->business_type ?? ''),
                            'comment' => (string) ($deal->comment ?? ''),
                            'date' => (string) ($deal->deal_date ?: ($deal->created_at?->format('d M Y') ?? '')),
                        ];
                    }
                }
            } catch (\Throwable) {
            }

            // 7. Testimonials (testimonials)
            try {
                if (Schema::hasTable('testimonials')) {
                    $testimonialsCount = Testimonial::query()
                        ->where(function ($q) use ($user): void {
                            $q->where('from_user_id', $user->id)
                                ->orWhere('to_user_id', $user->id);
                        })
                        ->count();

                    $testRows = Testimonial::query()
                        ->where(function ($q) use ($user): void {
                            $q->where('from_user_id', $user->id)
                                ->orWhere('to_user_id', $user->id);
                        })
                        ->with([
                            'fromUser:id,first_name,last_name,display_name',
                            'toUser:id,first_name,last_name,display_name',
                        ])
                        ->orderByDesc('created_at')
                        ->limit(10)
                        ->get();

                    foreach ($testRows as $t) {
                        $isGiven = (string) $t->from_user_id === (string) $user->id;
                        $otherPeer = $isGiven ? $t->toUser : $t->fromUser;
                        $peerName = $otherPeer
                            ? trim((string) ($otherPeer->display_name ?: ($otherPeer->first_name.' '.$otherPeer->last_name)))
                            : 'Peer';
                        $recentTestimonials[] = [
                            'direction' => $isGiven ? 'Given to' : 'Received from',
                            'peer_name' => $peerName,
                            'content' => Str::limit((string) ($t->content ?? ''), 120),
                            'rating' => $t->rating,
                            'date' => $t->created_at?->format('d M Y') ?? '',
                        ];
                    }
                }
            } catch (\Throwable) {
            }
        }

        if (Schema::hasTable('referral_campaign_configs')) {
            $campaign = ReferralCampaignConfig::query()->where('is_active', true)->first();
            $rewardTitle = $campaign?->reward_title;
        }

        $collectedAttachments[] = [
            'type' => 'action_buttons',
            'items' => [
                [
                    'id' => 'act_coins',
                    'title' => 'My Coins',
                    'route' => '/coins',
                ],
                [
                    'id' => 'act_claim_coins',
                    'title' => 'Claim Coins',
                    'route' => '/claim-your-coin',
                ],
                [
                    'id' => 'act_referrals',
                    'title' => 'My Referrals',
                    'route' => '/referrals',
                ],
                [
                    'id' => 'act_p2p',
                    'title' => 'P2P Meetings',
                    'route' => '/p2p-meetings',
                ],
            ],
        ];

        return [
            'coins_balance' => (int) ($user?->coins_balance ?? 0),
            'lives_impacted_score' => (int) ($user?->life_impacted_count ?? 0),
            'peers_introduced_count' => (int) ($user?->members_introduced_count ?? 0),
            'referrals_given_count' => $referralsGivenCount,
            'referrals_received_count' => $referralsReceivedCount,
            'p2p_meetings_count' => $p2pCount,
            'business_deals_count' => $businessDealsCount,
            'testimonials_count' => $testimonialsCount,
            'active_referral_campaign_reward' => $rewardTitle,
            'recent_coin_history' => $recentCoinHistory,
            'recent_coin_claims' => $recentCoinClaims,
            'recent_life_impact_history' => $recentLifeImpactHistory,
            'recent_p2p_meetings' => $recentP2pMeetings,
            'recent_referrals' => $recentReferrals,
            'recent_business_deals' => $recentBusinessDeals,
            'recent_testimonials' => $recentTestimonials,
            'assistant_guidance' => 'Present the peer\'s actual history items (amounts, reasons, dates, peer names) directly in your response. NEVER say you do not have access to their transaction history, and NEVER tell them to "Open the Unity App" or guess generic menu/tab names—they are already inside the Peers Global Unity app and can tap the action button below your message (such as My Coins, Claim Coins, My Referrals, P2P Meetings, or Life Impact Score) to view the full screen.',
        ];
    }

    /**
     * Build contextual navigation buttons based on user question and assistant reply.
     *
     * @param  array<int, array<string, mixed>>  $existingAttachments
     * @return array<int, array<string, mixed>>
     */
    public function enrichContextualButtons(string $userText, string $replyMarkdown, array $existingAttachments): array
    {
        $combined = mb_strtolower($userText.' '.$replyMarkdown);
        $buttons = [];

        $rules = [
            ['keywords' => ['p2p', 'peer-to-peer', '1-on-1', 'peer meeting', 'schedule a meeting', 'scheduling meetings'], 'items' => [
                ['id' => 'nav_p2p', 'title' => 'P2P Meetings', 'route' => '/p2p-meetings'],
                ['id' => 'nav_add_p2p', 'title' => 'Schedule P2P Meeting', 'route' => '/add-p2p-meeting'],
            ]],
            ['keywords' => ['speed networking'], 'items' => [
                ['id' => 'nav_speed', 'title' => 'Speed Networking', 'route' => '/speed-networking'],
            ]],
            ['keywords' => ['upcoming event', 'events near', 'register for event'], 'items' => [
                ['id' => 'nav_events', 'title' => 'Upcoming Events', 'route' => '/events'],
            ]],
            ['keywords' => ['unity circle', 'join a circle', 'circles'], 'items' => [
                ['id' => 'nav_circles', 'title' => 'Unity Circles', 'route' => '/circles'],
            ]],
            ['keywords' => ['referral'], 'items' => [
                ['id' => 'nav_referrals', 'title' => 'My Referrals', 'route' => '/referrals'],
                ['id' => 'nav_add_referral', 'title' => 'Give a Referral', 'route' => '/add-referral'],
            ]],
            ['keywords' => ['coin'], 'items' => [
                ['id' => 'nav_coins', 'title' => 'My Coins', 'route' => '/coins'],
                ['id' => 'nav_claim_coins', 'title' => 'Claim Coins', 'route' => '/claim-your-coin'],
            ]],
            ['keywords' => ['life impact', 'lives impacted'], 'items' => [
                ['id' => 'nav_impact', 'title' => 'Life Impact Score', 'route' => '/life-impact'],
            ]],
            ['keywords' => ['business deal'], 'items' => [
                ['id' => 'nav_deals', 'title' => 'Business Deals', 'route' => '/business-deals'],
            ]],
            ['keywords' => ['testimonial'], 'items' => [
                ['id' => 'nav_testimonials', 'title' => 'Testimonials', 'route' => '/testimonials'],
            ]],
            ['keywords' => ['post an ask', 'create an ask', 'explore asks', 'open asks', 'my asks', 'collaboration ask', 'referral ask', 'help ask', 'find a collaborator'], 'items' => [
                ['id' => 'nav_asks', 'title' => 'Explore Asks', 'route' => '/post-ask'],
            ]],
            ['keywords' => ['search for peers', 'find peers', 'peers directory'], 'items' => [
                ['id' => 'nav_peers', 'title' => 'Explore Peers', 'route' => '/peers'],
            ]],
            ['keywords' => ['submit a ticket', 'support ticket', 'raise a ticket'], 'items' => [
                ['id' => 'nav_support', 'title' => 'Support & Help', 'route' => '/submit-ticket'],
            ]],
        ];

        foreach ($rules as $rule) {
            foreach ($rule['keywords'] as $kw) {
                if (str_contains($combined, $kw)) {
                    foreach ($rule['items'] as $btn) {
                        $buttons[$btn['route']] = $btn;
                    }
                    break;
                }
            }
        }

        if ($buttons !== []) {
            $existingAttachments[] = [
                'type' => 'action_buttons',
                'items' => array_slice(array_values($buttons), 0, 4),
            ];
        }

        return $existingAttachments;
    }

    /**
     * @param  array<int, string>  $enabledTools
     */
    private function isToolEnabled(string $shortKey, string $fnName, array $enabledTools): bool
    {
        return in_array($shortKey, $enabledTools, true) || in_array($fnName, $enabledTools, true);
    }
}

