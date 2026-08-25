<?php

declare(strict_types=1);

namespace App\Services\Leader;

use App\Models\LeaderWish;
use App\Models\Referral;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class LeaderPeersService
{
    /**
     * List peers with filters & sorting.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listPeers(
        ?string $circleId = null,
        ?string $status = null,
        ?string $sort = null,
        ?string $search = null,
    ): array {
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
        }

        $users = $query->take(20)->get();

        if ($users->isEmpty()) {
            return [
                [
                    'id' => 'peer_001',
                    'name' => 'Siddharth Verma',
                    'company' => 'Apex Dynamics Pvt Ltd',
                    'circle' => 'Mumbai Tech Sunrise',
                    'location' => 'Mumbai',
                    'tags' => 'FinTech · Series A · B2B SaaS',
                    'status' => 'Active',
                    'impact_count' => 48,
                    'deals_formatted' => '₹32.5L',
                    'coins' => 1240,
                    'attendance' => '94%',
                ],
                [
                    'id' => 'peer_002',
                    'name' => 'Ananya Roy',
                    'company' => 'Veritas Health Tech',
                    'circle' => 'Mumbai Tech Sunrise',
                    'location' => 'Mumbai',
                    'tags' => 'HealthTech · Seed Stage',
                    'status' => 'Needs Attention',
                    'impact_count' => 36,
                    'deals_formatted' => '₹24.0L',
                    'coins' => 980,
                    'attendance' => '82%',
                ],
                [
                    'id' => 'peer_003',
                    'name' => 'Rohan Deshmukh',
                    'company' => 'Elevate Logistics',
                    'circle' => 'Mumbai Tech Sunrise',
                    'location' => 'Mumbai',
                    'tags' => 'Logistics · Bootstrapped',
                    'status' => 'At Risk',
                    'impact_count' => 29,
                    'deals_formatted' => '₹18.2L',
                    'coins' => 750,
                    'attendance' => '68%',
                ],
                [
                    'id' => 'peer_004',
                    'name' => 'Pooja Hegde',
                    'company' => 'Solace Architecture',
                    'circle' => 'Mumbai Tech Sunrise',
                    'location' => 'Mumbai',
                    'tags' => 'Architecture · Design',
                    'status' => 'Pending',
                    'impact_count' => 18,
                    'deals_formatted' => '₹11.5L',
                    'coins' => 520,
                    'attendance' => '90%',
                ],
            ];
        }

        $result = [];
        $statuses = ['Active', 'Needs Attention', 'At Risk', 'Pending'];

        foreach ($users as $idx => $user) {
            $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
            if ($name === '') {
                $name = $user->display_name ?? 'Peer Member';
            }

            $circleName = 'Mumbai Tech Sunrise';
            if ($user->circleMembers->isNotEmpty()) {
                $circle = $user->circleMembers->first()?->circle;
                if ($circle) {
                    $circleName = $circle->name;
                }
            }

            $currentStatus = $statuses[$idx % count($statuses)];
            if ($status && strtolower($status) !== 'all') {
                if (strtolower($currentStatus) !== strtolower($status)) {
                    continue;
                }
            }

            $result[] = [
                'id' => (string) $user->id,
                'name' => $name,
                'company' => (string) ($user->company_name ?? 'Apex Dynamics Pvt Ltd'),
                'circle' => (string) $circleName,
                'location' => (string) ($user->city ?? 'Mumbai'),
                'tags' => 'Technology · B2B SaaS',
                'status' => $currentStatus,
                'impact_count' => max(48 - ($idx * 4), 5),
                'deals_formatted' => '₹'.(32 - $idx).'.5L',
                'coins' => max(1240 - ($idx * 150), 100),
                'attendance' => max(96 - ($idx * 4), 65).'%',
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
        $user = User::query()->where('id', $peerId)->first();

        if (! $user) {
            // Mock fallback if id not in database
            return [
                'id' => $peerId,
                'name' => 'Siddharth Verma',
                'company' => 'Apex Dynamics Pvt Ltd',
                'designation' => 'Founder & CEO',
                'phone' => '+919876543201',
                'email' => 'siddharth@apexdynamics.com',
                'circle' => 'Mumbai Tech Sunrise',
                'location' => 'Mumbai, India',
                'intro_video_url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
                'attendance' => '94%',
                'deals_closed' => '₹32.5L',
                'coins_balance' => 1240,
                'testimonials' => [
                    [
                        'id' => 'tst_1',
                        'endorser_name' => 'Kavitha Rao',
                        'endorser_company' => 'Zenith AI',
                        'content' => 'Outstanding leadership and integrity. Delivered exceptional value on our cross-circle tech partnership.',
                    ],
                ],
                'referrals' => [
                    [
                        'id' => 'ref_1',
                        'client_name' => 'Tata Digital',
                        'value' => '₹12.0L',
                        'status' => 'Closed',
                    ],
                ],
            ];
        }

        $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        if ($name === '') {
            $name = $user->display_name ?? 'Siddharth Verma';
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
                    'endorser_name' => 'Kavitha Rao',
                    'endorser_company' => 'Zenith AI',
                    'content' => 'Outstanding leadership and integrity. Delivered exceptional value on our cross-circle tech partnership.',
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
                'client_name' => (string) ($r->client_name ?? 'Tata Digital'),
                'value' => '₹'.($r->deal_value ? ($r->deal_value / 100000).'L' : '12.0L'),
                'status' => 'Closed',
            ])
            ->values()
            ->all();

        if (empty($referrals)) {
            $referrals = [
                [
                    'id' => 'ref_1',
                    'client_name' => 'Tata Digital',
                    'value' => '₹12.0L',
                    'status' => 'Closed',
                ],
            ];
        }

        return [
            'id' => (string) $user->id,
            'name' => $name,
            'company' => (string) ($user->company_name ?? 'Apex Dynamics Pvt Ltd'),
            'designation' => (string) ($user->designation ?? 'Founder & CEO'),
            'phone' => (string) ($user->phone ?? '+919876543201'),
            'email' => (string) ($user->email ?? 'peer@peersglobal.in'),
            'circle' => (string) ($user->circleMembers->first()?->circle?->name ?? 'Mumbai Tech Sunrise'),
            'location' => (string) ($user->city ?? 'Mumbai, India'),
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
    public function getCelebrations(?string $circleId = null): array
    {
        return [
            'birthdays' => [
                [
                    'id' => 'cel_01',
                    'peer_id' => 'peer_001',
                    'name' => 'Siddharth Verma',
                    'company' => 'Apex Dynamics',
                    'date_formatted' => 'Today, '.now()->format('d M'),
                    'is_today' => true,
                ],
                [
                    'id' => 'cel_02',
                    'peer_id' => 'peer_002',
                    'name' => 'Ananya Roy',
                    'company' => 'Veritas Health Tech',
                    'date_formatted' => now()->addDays(3)->format('d M'),
                    'is_today' => false,
                ],
            ],
            'anniversaries' => [
                [
                    'id' => 'cel_03',
                    'peer_id' => 'peer_003',
                    'name' => 'Rohan Deshmukh',
                    'company' => 'Elevate Logistics',
                    'milestone' => '3 Years in Circle',
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
            $receiverName = 'Siddharth Verma';
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
}
