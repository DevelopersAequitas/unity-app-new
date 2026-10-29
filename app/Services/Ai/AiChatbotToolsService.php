<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Event;
use App\Models\P2pMeeting;
use App\Models\Referral;
use App\Models\ReferralCampaignConfig;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            return "CURRENT USER CONTEXT:\n- Guest / Unauthenticated Member";
        }

        $name = trim((string) ($user->display_name ?: ($user->first_name.' '.$user->last_name))) ?: 'Member';
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
                'description' => 'Find active Peers Global members by business category, city, or keyword.',
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
                'description' => 'Get the logged-in user referral count, P2P meeting count, coins balance, Lives Impacted score, and active referral campaign reward.',
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
            'get_upcoming_events' => $this->getUpcomingEvents($args, $collectedAttachments),
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
            $peerName = trim((string) ($peer->display_name ?: ($peer->first_name.' '.$peer->last_name))) ?: 'Peer Member';
            $company = (string) ($peer->company_name ?: 'Peers Global');
            $role = (string) ($peer->business_sub_category ?: ($peer->designation ?: 'Member'));
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
    private function getUpcomingEvents(array $args, array &$collectedAttachments): array
    {
        if (! Schema::hasTable('events')) {
            return ['events' => []];
        }

        $city = trim((string) ($args['city'] ?? ''));

        $events = Event::query()
            ->where('start_at', '>=', now()->subHours(2))
            ->when($city !== '', fn ($q) => $q->where('location_text', 'like', "%{$city}%"))
            ->orderBy('start_at', 'asc')
            ->limit(5)
            ->get()
            ->map(fn (Event $e): array => [
                'id' => (string) $e->id,
                'title' => (string) $e->title,
                'start_at' => $e->start_at?->toIso8601String(),
                'location' => (string) ($e->location_text ?? ($e->is_virtual ? 'Online / Virtual' : 'TBA')),
                'is_virtual' => (bool) $e->is_virtual,
            ])
            ->values()
            ->all();

        $collectedAttachments[] = [
            'type' => 'action_buttons',
            'items' => [
                [
                    'id' => 'act_events',
                    'title' => 'View Upcoming Events',
                    'route' => '/events',
                ],
            ],
        ];

        return ['events' => $events];
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
        $referralsCount = 0;
        $p2pCount = 0;
        $rewardTitle = null;

        if ($user) {
            if (Schema::hasTable('referrals')) {
                $referralsCount = Referral::query()->where('from_user_id', $user->id)->count();
            }
            if (Schema::hasTable('p2p_meetings')) {
                $p2pCount = P2pMeeting::query()
                    ->where('initiator_user_id', $user->id)
                    ->orWhere('peer_user_id', $user->id)
                    ->count();
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
                    'id' => 'act_referrals',
                    'title' => 'My Referrals',
                    'route' => '/referrals',
                ],
                [
                    'id' => 'act_coins',
                    'title' => 'My Coins & Impact',
                    'route' => '/coins',
                ],
            ],
        ];

        return [
            'coins_balance' => (int) ($user?->coins_balance ?? 0),
            'lives_impacted_score' => (int) ($user?->life_impacted_count ?? 0),
            'members_introduced_count' => (int) ($user?->members_introduced_count ?? 0),
            'referrals_given_count' => $referralsCount,
            'p2p_meetings_count' => $p2pCount,
            'active_referral_campaign_reward' => $rewardTitle,
        ];
    }

    /**
     * @param  array<int, string>  $enabledTools
     */
    private function isToolEnabled(string $shortKey, string $fnName, array $enabledTools): bool
    {
        return in_array($shortKey, $enabledTools, true) || in_array($fnName, $enabledTools, true);
    }
}

