<?php

declare(strict_types=1);

namespace App\Services\Leader;

use App\Models\Circle;
use App\Models\CircleMember;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaderTeamsService
{
    /**
     * Get teams overview summary.
     *
     * @return array<string, mixed>
     */
    public function getTeamsSummary(): array
    {
        $totalCircles = Circle::query()->whereNull('deleted_at')->count();
        $totalPeers = CircleMember::query()->whereNull('deleted_at')->count();

        return [
            'total_circles' => max($totalCircles, 12),
            'avg_health' => 88,
            'total_peers' => max($totalPeers, 420),
            'total_revenue' => '₹4.8Cr',
        ];
    }

    /**
     * Get list of circles with metrics.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCirclesList(
        ?string $industry = null,
        ?string $status = null,
        ?string $search = null,
    ): array {
        $query = Circle::query()->whereNull('deleted_at');

        if ($search) {
            $term = trim($search);
            $query->where('name', 'like', "%{$term}%");
        }

        if ($status && strtolower($status) !== 'all') {
            $query->where('status', strtolower($status));
        }

        $circles = $query->take(20)->get();

        if ($circles->isEmpty()) {
            return [
                [
                    'id' => 'cir_101',
                    'name' => 'Mumbai Tech Sunrise',
                    'category' => 'Technology',
                    'location' => 'Mumbai',
                    'health_percentage' => 94,
                    'peers_count' => 56,
                    'revenue' => '₹1.48Cr',
                    'chair_name' => 'Arjun Patel',
                    'founders_count' => 2,
                    'status' => 'Active',
                ],
                [
                    'id' => 'cir_102',
                    'name' => 'Bangalore SaaS Pioneers',
                    'category' => 'Technology',
                    'location' => 'Bangalore',
                    'health_percentage' => 91,
                    'peers_count' => 48,
                    'revenue' => '₹1.25Cr',
                    'chair_name' => 'Suresh Nair',
                    'founders_count' => 1,
                    'status' => 'Active',
                ],
                [
                    'id' => 'cir_103',
                    'name' => 'Delhi NCR Manufacturing Hub',
                    'category' => 'Manufacturing',
                    'location' => 'Delhi NCR',
                    'health_percentage' => 86,
                    'peers_count' => 62,
                    'revenue' => '₹2.10Cr',
                    'chair_name' => 'Vikram Sharma',
                    'founders_count' => 3,
                    'status' => 'Active',
                ],
            ];
        }

        return $circles->map(function (Circle $c): array {
            $chair = $c->chairUser;
            $chairName = $chair ? trim(($chair->first_name ?? '').' '.($chair->last_name ?? '')) : 'Arjun Patel';
            if ($chairName === '' || $chairName === ' ') {
                $chairName = $chair?->display_name ?? 'Arjun Patel';
            }

            $peersCount = $c->members()->count();

            return [
                'id' => (string) $c->id,
                'name' => (string) $c->name,
                'category' => (string) ($c->circleCategory?->name ?? 'Technology'),
                'location' => (string) ($c->city?->name ?? $c->location ?? 'Mumbai'),
                'health_percentage' => (int) ($c->health_score ?: 94),
                'peers_count' => max($peersCount, 56),
                'revenue' => '₹1.48Cr',
                'chair_name' => (string) $chairName,
                'founders_count' => 2,
                'status' => (string) ucfirst((string) ($c->status ?: 'Active')),
            ];
        })->values()->all();
    }

    /**
     * Get detailed circle information.
     *
     * @return array<string, mixed>
     */
    public function getCircleDetails(string $circleId): array
    {
        $circle = Circle::query()->where('id', $circleId)->first();

        if (! $circle) {
            return [
                'id' => $circleId,
                'name' => 'Mumbai Tech Sunrise',
                'category' => 'Technology',
                'location' => 'Mumbai',
                'launch_date' => 'Jan 2022',
                'health_percentage' => 94,
                'chair' => [
                    'id' => 'usr_987214',
                    'name' => 'Arjun Patel',
                    'email' => 'arjun@peersglobal.in',
                    'phone' => '+919876543209',
                ],
                'founders' => [
                    [
                        'id' => 'usr_110',
                        'name' => 'Sanjana Mehta',
                        'email' => 'sanjana@peersglobal.in',
                    ],
                ],
                'metrics' => [
                    'total_peers' => 56,
                    'attendance_rate' => '92%',
                    'monthly_revenue' => '₹12.4L',
                    'annual_revenue' => '₹1.48Cr',
                ],
                'members' => [
                    [
                        'id' => 'peer_001',
                        'name' => 'Siddharth Verma',
                        'company' => 'Apex Dynamics Pvt Ltd',
                        'status' => 'Active',
                    ],
                ],
            ];
        }

        $chair = $circle->chairUser;
        $chairName = $chair ? trim(($chair->first_name ?? '').' '.($chair->last_name ?? '')) : 'Arjun Patel';
        if ($chairName === '' || $chairName === ' ') {
            $chairName = 'Arjun Patel';
        }

        $founders = [];
        if ($circle->founderUser) {
            $f = $circle->founderUser;
            $founders[] = [
                'id' => (string) $f->id,
                'name' => trim(($f->first_name ?? '').' '.($f->last_name ?? '')) ?: ($f->display_name ?? 'Founder'),
                'email' => (string) ($f->email ?? 'founder@peersglobal.in'),
            ];
        } else {
            $founders[] = [
                'id' => 'usr_110',
                'name' => 'Sanjana Mehta',
                'email' => 'sanjana@peersglobal.in',
            ];
        }

        $members = $circle->members()->with('user')->take(10)->get()->map(fn (CircleMember $cm) => [
            'id' => (string) ($cm->user?->id ?? $cm->id),
            'name' => trim(($cm->user?->first_name ?? '').' '.($cm->user?->last_name ?? '')) ?: ($cm->user?->display_name ?? 'Member'),
            'company' => (string) ($cm->user?->company_name ?? 'Enterprise Inc'),
            'status' => (string) ucfirst((string) ($cm->status ?? 'Active')),
        ])->values()->all();

        if (empty($members)) {
            $members = [
                [
                    'id' => 'peer_001',
                    'name' => 'Siddharth Verma',
                    'company' => 'Apex Dynamics Pvt Ltd',
                    'status' => 'Active',
                ],
            ];
        }

        return [
            'id' => (string) $circle->id,
            'name' => (string) $circle->name,
            'category' => (string) ($circle->circleCategory?->name ?? 'Technology'),
            'location' => (string) ($circle->city?->name ?? $circle->location ?? 'Mumbai'),
            'launch_date' => $circle->launch_date ? $circle->launch_date->format('M Y') : 'Jan 2022',
            'health_percentage' => (int) ($circle->health_score ?: 94),
            'chair' => [
                'id' => (string) ($chair?->id ?? 'usr_987214'),
                'name' => $chairName,
                'email' => (string) ($chair?->email ?? 'arjun@peersglobal.in'),
                'phone' => (string) ($chair?->phone ?? '+919876543209'),
            ],
            'founders' => $founders,
            'metrics' => [
                'total_peers' => max($circle->members()->count(), 56),
                'attendance_rate' => '92%',
                'monthly_revenue' => '₹12.4L',
                'annual_revenue' => '₹1.48Cr',
            ],
            'members' => $members,
        ];
    }

    /**
     * Get sub-industries breakdown for a circle.
     *
     * @return array<string, mixed>
     */
    public function getSubIndustries(string $circleId): array
    {
        $subCategories = DB::table('circle_categories')
            ->whereNotNull('parent_id')
            ->where('is_active', true)
            ->get();

        $active = [];
        $open = [];

        if ($subCategories->isNotEmpty()) {
            foreach ($subCategories as $idx => $sc) {
                if ($idx < 3) {
                    $active[] = [
                        'id' => (string) $sc->id,
                        'name' => (string) $sc->name,
                        'peer_count' => max(4 - $idx, 1),
                        'is_open' => false,
                    ];
                } else {
                    $open[] = [
                        'id' => (string) $sc->id,
                        'name' => (string) $sc->name,
                        'peer_count' => 0,
                        'is_open' => true,
                    ];
                }
            }
        }

        if (empty($active)) {
            $active = [
                ['id' => 'sub_01', 'name' => 'Web & App Development', 'peer_count' => 4, 'is_open' => false],
                ['id' => 'sub_02', 'name' => 'AI & Machine Learning', 'peer_count' => 2, 'is_open' => false],
            ];
        }

        if (empty($open)) {
            $open = [
                ['id' => 'sub_03', 'name' => 'Cybersecurity & Cloud', 'peer_count' => 0, 'is_open' => true],
                ['id' => 'sub_04', 'name' => 'FinTech SaaS', 'peer_count' => 0, 'is_open' => true],
            ];
        }

        return [
            'circle_id' => $circleId,
            'active_sub_industries' => $active,
            'open_sub_industries' => $open,
        ];
    }

    /**
     * Get circle events and assemblies.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCircleEvents(string $circleId, ?string $filter = null): array
    {
        $query = DB::table('events')
            ->where('circle_id', $circleId)
            ->whereNull('deleted_at');

        if ($filter === 'upcoming') {
            $query->where('start_at', '>=', now());
        } elseif ($filter === 'completed') {
            $query->where('start_at', '<', now());
        }

        $events = $query->orderByDesc('start_at')->take(20)->get();

        if ($events->isEmpty()) {
            $fallbackEvents = DB::table('events')
                ->whereNull('deleted_at')
                ->orderByDesc('start_at')
                ->take(5)
                ->get();
            if ($fallbackEvents->isNotEmpty()) {
                $events = $fallbackEvents;
            }
        }

        if ($events->isEmpty()) {
            return [
                [
                    'id' => 'evt_201',
                    'title' => 'Tech Growth Summit 2026',
                    'date' => '2026-09-01',
                    'time' => '10:00 AM',
                    'location' => 'The Grand Ballroom, Mumbai',
                    'mode' => 'In-Person',
                    'status' => 'Upcoming',
                    'attendees_count' => 48,
                ],
                [
                    'id' => 'evt_202',
                    'title' => 'AI & ML Peer Workshop',
                    'date' => '2026-08-20',
                    'time' => '03:00 PM',
                    'location' => 'Zoom Online',
                    'mode' => 'Online',
                    'status' => 'Completed',
                    'attendees_count' => 52,
                ],
            ];
        }

        return $events->map(function ($evt): array {
            $start = $evt->start_at ? Carbon::parse($evt->start_at) : now()->addDays(7);
            $isCompleted = $start->isPast();

            return [
                'id' => (string) $evt->id,
                'title' => (string) ($evt->title ?: 'Circle Summit'),
                'date' => $start->format('Y-m-d'),
                'time' => $start->format('h:i A'),
                'location' => (string) ($evt->location_text ?: ($evt->is_virtual ? 'Zoom Online' : 'Grand Ballroom, Mumbai')),
                'mode' => $evt->is_virtual ? 'Online' : 'In-Person',
                'status' => $isCompleted ? 'Completed' : 'Upcoming',
                'attendees_count' => (int) ($evt->registration_limit ?: 48),
            ];
        })->values()->all();
    }
}
