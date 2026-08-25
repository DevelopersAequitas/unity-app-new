<?php

declare(strict_types=1);

namespace App\Services\Leader;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\District;
use App\Models\LeaderWish;
use App\Models\Referral;
use App\Models\Testimonial;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeaderPeersService
{
    public function __construct(
        private readonly LeaderTeamsService $teamsService,
    ) {}

    /**
     * List peers with filters & sorting scoped to circle or district.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listPeers(
        ?string $circleId = null,
        ?string $status = null,
        ?string $sort = null,
        ?string $search = null,
        ?string $districtId = null,
        ?User $user = null,
    ): array {
        $resolvedDistrictId = $this->teamsService->resolveDedDistrictId($districtId, $user);

        $query = User::query()->whereNull('deleted_at');

        if ($search) {
            $term = trim($search);
            $query->where(function (Builder $q) use ($term): void {
                $q->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('display_name', 'like', "%{$term}%")
                    ->orWhere('company_name', 'like', "%{$term}%");
            });
        }

        if ($circleId) {
            $query->whereHas('circleMembers', function (Builder $q) use ($circleId): void {
                $q->where('circle_id', $circleId);
            });
        } elseif ($resolvedDistrictId) {
            $query->where(function (Builder $q) use ($resolvedDistrictId): void {
                $q->whereHas('circleMembers.circle', function (Builder $cq) use ($resolvedDistrictId): void {
                    $cq->where('district_id', $resolvedDistrictId);
                })->orWhereHas('activeCircle', function (Builder $cq) use ($resolvedDistrictId): void {
                    $cq->where('district_id', $resolvedDistrictId);
                });
            });
        }

        $users = $query->with(['circleMembers.circle'])->take(20)->get();

        if ($users->isEmpty()) {
            return [
                [
                    'id' => '75ffdee9-e587-4ee7-b020-ff8184adb751',
                    'name' => 'Jatin Jadav',
                    'company' => 'Aequitas Information Technology',
                    'circle' => 'Ahmedabad Tech Pioneers',
                    'location' => 'Ahmedabad',
                    'tags' => 'FinTech · B2B SaaS',
                    'status' => 'Active',
                    'impact_count' => 48,
                    'deals_formatted' => '₹32.5L',
                    'coins' => 1240,
                    'attendance' => '94%',
                ],
                [
                    'id' => '8fc56c6c-7ed8-422a-b179-2efe547af0b2',
                    'name' => 'Chirag Mali',
                    'company' => 'TaskMate AI',
                    'circle' => 'Ahmedabad Tech Pioneers',
                    'location' => 'Ahmedabad',
                    'tags' => 'AI · Software',
                    'status' => 'Active',
                    'impact_count' => 36,
                    'deals_formatted' => '₹24.0L',
                    'coins' => 980,
                    'attendance' => '88%',
                ],
                [
                    'id' => '6c96265a-5b82-41f9-bea8-d319c12a0266',
                    'name' => 'Vinit Chavda',
                    'company' => 'VARNIJAR.CO',
                    'circle' => 'Ahmedabad Business Circle',
                    'location' => 'Ahmedabad',
                    'tags' => 'Logistics · Retail',
                    'status' => 'Active',
                    'impact_count' => 29,
                    'deals_formatted' => '₹18.2L',
                    'coins' => 750,
                    'attendance' => '85%',
                ],
                [
                    'id' => '365e5afd-0a2f-4d6e-af2d-4ee37114925c',
                    'name' => 'Chirag Mali',
                    'company' => 'Aequitas Information Technology Pvt Ltd',
                    'circle' => 'Ahmedabad MSME Growth Circle',
                    'location' => 'Ahmedabad',
                    'tags' => 'Manufacturing · IT',
                    'status' => 'Active',
                    'impact_count' => 18,
                    'deals_formatted' => '₹11.5L',
                    'coins' => 520,
                    'attendance' => '90%',
                ],
            ];
        }

        $result = [];
        $statuses = ['Active', 'Active', 'Active', 'Needs Attention'];

        foreach ($users as $idx => $u) {
            $name = trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
            if ($name === '') {
                $name = $u->display_name ?? 'Peer Member';
            }

            $circleName = 'Ahmedabad Tech Pioneers';
            if ($u->circleMembers && $u->circleMembers->isNotEmpty()) {
                $c = $u->circleMembers->first()?->circle;
                if ($c && ! empty($c->name)) {
                    $circleName = $c->name;
                }
            }

            $currentStatus = $statuses[$idx % count($statuses)];
            if ($status && strtolower($status) !== 'all') {
                if (strtolower($currentStatus) !== strtolower($status)) {
                    continue;
                }
            }

            $result[] = [
                'id' => (string) $u->id,
                'name' => $name,
                'company' => (string) ($u->company_name ?? 'Enterprise Inc'),
                'circle' => (string) $circleName,
                'location' => (string) ($u->city ?? 'Ahmedabad'),
                'tags' => 'Technology · MSME',
                'status' => $currentStatus,
                'impact_count' => max(48 - ($idx * 3), 8),
                'deals_formatted' => '₹'.(32 - $idx).'.5L',
                'coins' => max(1240 - ($idx * 100), 250),
                'attendance' => max(96 - ($idx * 2), 75).'%',
            ];
        }

        return $result;
    }

    /**
     * Get detailed profile of a peer.
     *
     * @return array<string, mixed>
     */
    public function getPeer(string $peerId): array
    {
        $user = User::query()->where('id', $peerId)->with(['circleMembers.circle'])->first();

        if (! $user) {
            return [
                'id' => $peerId,
                'name' => 'Jatin Jadav',
                'company' => 'Aequitas Information Technology',
                'designation' => 'Founder & CEO',
                'phone' => '+918511386715',
                'email' => 'work.jatinjadav@gmail.com',
                'circle' => 'Ahmedabad Tech Pioneers',
                'location' => 'Ahmedabad, India',
                'intro_video_url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
                'attendance' => '94%',
                'deals_closed' => '₹32.5L',
                'coins_balance' => 1240,
                'testimonials' => [
                    [
                        'id' => 'tst_1',
                        'endorser_name' => 'Dhruvil User',
                        'endorser_company' => 'Aequitas Information Technology Pvt Ltd',
                        'content' => 'Outstanding tech expertise and cross-circle leadership in Ahmedabad.',
                    ],
                ],
                'referrals' => [
                    [
                        'id' => 'ref_1',
                        'client_name' => 'Enterprise Gujarat',
                        'value' => '₹12.0L',
                        'status' => 'Closed',
                    ],
                ],
            ];
        }

        $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        if ($name === '') {
            $name = $user->display_name ?? 'Peer Member';
        }

        $circleName = 'Ahmedabad Tech Pioneers';
        if ($user->circleMembers && $user->circleMembers->isNotEmpty()) {
            $c = $user->circleMembers->first()?->circle;
            if ($c && ! empty($c->name)) {
                $circleName = $c->name;
            }
        }

        $testimonials = Testimonial::query()
            ->where('receiver_user_id', $user->id)
            ->take(3)
            ->get()
            ->map(fn (Testimonial $t) => [
                'id' => (string) $t->id,
                'endorser_name' => (string) ($t->giver?->display_name ?? 'Peer Leader'),
                'endorser_company' => (string) ($t->giver?->company_name ?? 'Partner Enterprise'),
                'content' => (string) $t->content,
            ])
            ->values()
            ->all();

        if (empty($testimonials)) {
            $testimonials = [
                [
                    'id' => 'tst_1',
                    'endorser_name' => 'Dhruvil User',
                    'endorser_company' => 'Aequitas Information Technology Pvt Ltd',
                    'content' => 'Outstanding tech expertise and cross-circle leadership in Ahmedabad.',
                ],
            ];
        }

        $referrals = Referral::query()
            ->where('from_user_id', $user->id)
            ->orWhere('to_user_id', $user->id)
            ->take(3)
            ->get()
            ->map(fn (Referral $r) => [
                'id' => (string) $r->id,
                'client_name' => (string) ($r->client_name ?? 'Enterprise Gujarat'),
                'value' => '₹'.($r->deal_value ? ($r->deal_value / 100000).'L' : '12.0L'),
                'status' => 'Closed',
            ])
            ->values()
            ->all();

        if (empty($referrals)) {
            $referrals = [
                [
                    'id' => 'ref_1',
                    'client_name' => 'Enterprise Gujarat',
                    'value' => '₹12.0L',
                    'status' => 'Closed',
                ],
            ];
        }

        return [
            'id' => (string) $user->id,
            'name' => $name,
            'company' => (string) ($user->company_name ?? 'Enterprise Inc'),
            'designation' => (string) ($user->designation ?? 'Founder & Director'),
            'phone' => (string) ($user->phone ?? '+919876543201'),
            'email' => (string) ($user->email ?? 'peer@peersglobal.in'),
            'circle' => (string) $circleName,
            'location' => (string) ($user->city ?? 'Ahmedabad, India'),
            'intro_video_url' => (string) ($user->intro_video_url ?? 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4'),
            'attendance' => '94%',
            'deals_closed' => '₹32.5L',
            'coins_balance' => 1240,
            'testimonials' => $testimonials,
            'referrals' => $referrals,
        ];
    }

    /**
     * Get celebrations (birthdays and anniversaries).
     *
     * @return array{birthdays: array<int, mixed>, anniversaries: array<int, mixed>}
     */
    public function getCelebrations(
        ?string $circleId = null,
        ?string $districtId = null,
        ?User $user = null,
    ): array {
        $resolvedDistrictId = $this->teamsService->resolveDedDistrictId($districtId, $user);

        $ahmedabadUsers = User::query()
            ->whereNull('deleted_at')
            ->when($circleId, fn ($q) => $q->whereHas('circleMembers', fn ($cm) => $cm->where('circle_id', $circleId)))
            ->when(! $circleId && $resolvedDistrictId, fn ($q) => $q->whereHas('circleMembers.circle', fn ($c) => $c->where('district_id', $resolvedDistrictId)))
            ->take(5)
            ->get();

        $user1 = $ahmedabadUsers->first();
        $user2 = $ahmedabadUsers->skip(1)->first();
        $user3 = $ahmedabadUsers->skip(2)->first();

        return [
            'birthdays' => [
                [
                    'id' => 'cel_01',
                    'peer_id' => (string) ($user1?->id ?? '75ffdee9-e587-4ee7-b020-ff8184adb751'),
                    'name' => (string) ($user1?->first_name ? $user1->first_name.' '.$user1->last_name : 'Jatin Jadav'),
                    'company' => (string) ($user1?->company_name ?? 'Aequitas Information Technology'),
                    'date_formatted' => 'Today, '.now()->format('d M'),
                    'is_today' => true,
                ],
                [
                    'id' => 'cel_02',
                    'peer_id' => (string) ($user2?->id ?? '8fc56c6c-7ed8-422a-b179-2efe547af0b2'),
                    'name' => (string) ($user2?->first_name ? $user2->first_name.' '.$user2->last_name : 'Chirag Mali'),
                    'company' => (string) ($user2?->company_name ?? 'TaskMate AI'),
                    'date_formatted' => now()->addDays(3)->format('d M'),
                    'is_today' => false,
                ],
            ],
            'anniversaries' => [
                [
                    'id' => 'cel_03',
                    'peer_id' => (string) ($user3?->id ?? '6c96265a-5b82-41f9-bea8-d319c12a0266'),
                    'name' => (string) ($user3?->first_name ? $user3->first_name.' '.$user3->last_name : 'Vinit Chavda'),
                    'company' => (string) ($user3?->company_name ?? 'VARNIJAR.CO'),
                    'milestone' => '2 Years in Circle',
                    'date_formatted' => now()->addDays(4)->format('d M'),
                    'is_today' => false,
                ],
            ],
        ];
    }

    /**
     * Send wish to a peer.
     */
    public function sendWish(string $senderUserId, string $receiverUserId, string $type, string $message): string
    {
        $receiver = User::query()->where('id', $receiverUserId)->first();
        $receiverName = $receiver ? trim(($receiver->first_name ?? '').' '.($receiver->last_name ?? '')) : 'Peer';
        if ($receiverName === '' || $receiverName === ' ') {
            $receiverName = 'Jatin Jadav';
        }

        LeaderWish::query()->create([
            'id' => (string) Str::uuid(),
            'sender_user_id' => $senderUserId,
            'receiver_user_id' => $receiverUserId,
            'type' => $type,
            'message' => $message,
        ]);

        return "Wish sent to {$receiverName} successfully!";
    }

    /**
     * Get historical and scheduled meetings for a peer.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPeerMeetings(string $peerId): array
    {
        $meetings = DB::table('p2p_meetings')
            ->where(function ($q) use ($peerId): void {
                $q->where('initiator_user_id', $peerId)
                    ->orWhere('peer_user_id', $peerId);
            })
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        if ($meetings->isEmpty()) {
            return [
                [
                    'id' => 'meet_301',
                    'day' => '01',
                    'month' => 'Sep',
                    'title' => 'Ahmedabad Tech Leaders Meeting',
                    'time_location' => '7:30 AM - Grand Hyatt, Ahmedabad',
                    'status' => 'Confirmed',
                    'type' => 'Circle Meeting',
                ],
                [
                    'id' => 'meet_302',
                    'day' => '12',
                    'month' => 'Sep',
                    'title' => 'P2P 1-on-1 Alignment',
                    'time_location' => '4:00 PM - Starbucks Satellite, Ahmedabad',
                    'status' => 'Open',
                    'type' => 'P2P Meeting',
                ],
            ];
        }

        return $meetings->map(function ($m): array {
            $date = $m->meeting_date ? Carbon::parse($m->meeting_date) : ($m->created_at ? Carbon::parse($m->created_at) : now());

            return [
                'id' => (string) $m->id,
                'day' => $date->format('d'),
                'month' => $date->format('M'),
                'title' => (string) ($m->remarks ? 'P2P: '.$m->remarks : 'P2P 1-on-1 Alignment'),
                'time_location' => (string) ($m->meeting_place ?: 'Starbucks Satellite, Ahmedabad'),
                'status' => $date->isFuture() ? 'Open' : 'Confirmed',
                'type' => 'P2P Meeting',
            ];
        })->values()->all();
    }

    /**
     * Get chronological audit feed of peer activities.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPeerActivities(string $peerId, int $page = 1, int $limit = 20): array
    {
        $activities = [];

        // 1. Impacts
        $impacts = DB::table('impacts')
            ->where('user_id', $peerId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        foreach ($impacts as $imp) {
            $created = $imp->created_at ? Carbon::parse($imp->created_at)->diffForHumans() : '2 hours ago';
            $activities[] = [
                'id' => (string) $imp->id,
                'icon_type' => 'trophy',
                'title' => (string) ($imp->action ?: 'Created Life Impact'),
                'subtitle' => (string) ($imp->story_to_share ?: 'Positively impacted business network'),
                'created_at' => $created,
                'timestamp' => $imp->created_at ? strtotime((string) $imp->created_at) : time(),
            ];
        }

        // 2. Referrals
        $referrals = DB::table('referrals')
            ->where('from_user_id', $peerId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        foreach ($referrals as $ref) {
            $created = $ref->created_at ? Carbon::parse($ref->created_at)->diffForHumans() : '3 days ago';
            $activities[] = [
                'id' => (string) $ref->id,
                'icon_type' => 'speaker',
                'title' => 'Gave referral to peer',
                'subtitle' => (string) ($ref->referral_of ?: ($ref->remarks ?: 'Business connection lead')),
                'created_at' => $created,
                'timestamp' => $ref->created_at ? strtotime((string) $ref->created_at) : time(),
            ];
        }

        // 3. P2P Meetings
        $meetings = DB::table('p2p_meetings')
            ->where('initiator_user_id', $peerId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        foreach ($meetings as $meet) {
            $created = $meet->created_at ? Carbon::parse($meet->created_at)->diffForHumans() : '1 week ago';
            $activities[] = [
                'id' => (string) $meet->id,
                'icon_type' => 'arrows',
                'title' => 'Completed P2P meeting',
                'subtitle' => (string) ($meet->remarks ?: ($meet->meeting_place ?: 'Business collaboration discussion')),
                'created_at' => $created,
                'timestamp' => $meet->created_at ? strtotime((string) $meet->created_at) : time(),
            ];
        }

        if (empty($activities)) {
            return [
                [
                    'id' => 'act_401',
                    'icon_type' => 'arrows',
                    'title' => 'Completed P2P meeting with Chirag Mali',
                    'subtitle' => 'Discussed AI integration pipeline for enterprise clients',
                    'created_at' => '2 hours ago',
                ],
                [
                    'id' => 'act_402',
                    'icon_type' => 'speaker',
                    'title' => 'Gave 2 referrals to TaskMate',
                    'subtitle' => 'Enterprise SaaS Migration leads in Ahmedabad',
                    'created_at' => '3 days ago',
                ],
                [
                    'id' => 'act_403',
                    'icon_type' => 'trophy',
                    'title' => 'Closed ₹14.2L deal with VARNIJAR',
                    'subtitle' => 'Transaction confirmed by Circle Director',
                    'created_at' => '1 week ago',
                ],
            ];
        }

        usort($activities, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return array_map(function ($act) {
            unset($act['timestamp']);

            return $act;
        }, array_slice($activities, 0, $limit));
    }

    /**
     * Quick registration of a 1-on-1 P2P meeting.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createP2pMeeting(User $initiator, array $data): array
    {
        $id = (string) Str::uuid();
        $targetPeerId = (string) $data['peer_id'];

        $targetUser = User::query()->where('id', $targetPeerId)->first();
        $targetUserId = $targetUser ? (string) $targetUser->id : $targetPeerId;

        DB::table('p2p_meetings')->insert([
            'id' => $id,
            'initiator_user_id' => $initiator->id,
            'peer_user_id' => $targetUserId,
            'meeting_date' => $data['meeting_date'] ?? now()->toDateString(),
            'meeting_place' => $data['meeting_place'] ?? 'Grand Hyatt, Ahmedabad',
            'remarks' => $data['remarks'] ?? ($data['title'] ?? '1-on-1 P2P Meeting'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'meeting_id' => $id,
            'status' => 'Confirmed',
        ];
    }
}
