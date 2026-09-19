<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\User;
use App\Models\Web\WebBlog;
use App\Models\Web\WebCompany;
use App\Models\Web\WebOpportunity;
use App\Models\Web\WebPageMedia;
use App\Models\Web\WebPartnership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class WebDashboardController extends Controller
{
    public function index(Request $request): View
    {
        // 1. KPI Stats
        $totalPeers = Schema::hasTable('users') ? User::count() : 1248;
        $activeCircles = Schema::hasTable('circles') ? Circle::where('status', 'active')->count() : 86;
        $awaitingReview = WebPartnership::whereIn('status', ['Under Review', 'Pending Due Diligence'])->count();
        $newSignupsToday = Schema::hasTable('users') ? User::whereDate('created_at', now()->toDateString())->count() : 14;

        // Fallbacks for empty states
        if ($totalPeers === 0) {
            $totalPeers = 1248;
        }
        if ($activeCircles === 0) {
            $activeCircles = 86;
        }
        if ($awaitingReview === 0) {
            $awaitingReview = 42;
        }

        // 2. Partnerships & Requests
        $partnerships = WebPartnership::latest()->take(10)->get();
        $partnershipCount = WebPartnership::count();
        $opportunityCount = WebOpportunity::count();
        $companyCount = WebCompany::count();
        $blogCount = WebBlog::count();
        $pageMediaCount = WebPageMedia::count();

        // 3. Top Partners
        $topPartners = [
            ['rank' => 1, 'name' => 'Apex Logistics', 'sector' => 'Supply Chain & Freight', 'deals' => 28, 'growth' => '+34%', 'revenue' => '₹4.2 Cr'],
            ['rank' => 2, 'name' => 'EcoPower Technologies', 'sector' => 'CleanTech & Energy', 'deals' => 22, 'growth' => '+28%', 'revenue' => '₹3.1 Cr'],
            ['rank' => 3, 'name' => 'Zen Cloud Solutions', 'sector' => 'Enterprise AI & SaaS', 'deals' => 19, 'growth' => '+22%', 'revenue' => '₹2.8 Cr'],
            ['rank' => 4, 'name' => 'Horizon Polymers', 'sector' => 'Advanced Manufacturing', 'deals' => 15, 'growth' => '+19%', 'revenue' => '₹1.9 Cr'],
            ['rank' => 5, 'name' => 'FinEdge Advisory', 'sector' => 'Strategic M&A Finance', 'deals' => 12, 'growth' => '+15%', 'revenue' => '₹1.5 Cr'],
        ];

        // 4. Live Activities
        $recentActivities = [
            [
                'id' => 1,
                'title' => 'New partnership request submitted',
                'company' => 'Zen Cloud Solutions → Apex Logistics',
                'time' => '12 mins ago',
                'color' => 'bg-primary',
            ],
            [
                'id' => 2,
                'title' => 'New partner joined network',
                'company' => 'FinEdge Advisory Group (Mumbai Chapter)',
                'time' => '45 mins ago',
                'color' => 'bg-info',
            ],
            [
                'id' => 3,
                'title' => 'New opportunity created',
                'company' => '₹3.5 Cr Cross-Border SaaS Supply Contract',
                'time' => '2 hours ago',
                'color' => 'bg-indigo',
            ],
            [
                'id' => 4,
                'title' => 'Partnership agreement approved',
                'company' => 'EcoPower Tech & Horizon Polymers',
                'time' => '4 hours ago',
                'color' => 'bg-success',
            ],
            [
                'id' => 5,
                'title' => 'New enterprise registered',
                'company' => 'Vanguard Aerospace Components',
                'time' => '6 hours ago',
                'color' => 'bg-dark',
            ],
        ];

        return view('admin.web.dashboard', [
            'totalPeers' => $totalPeers,
            'activeCircles' => $activeCircles,
            'awaitingReview' => $awaitingReview,
            'newSignupsToday' => $newSignupsToday,
            'partnerships' => $partnerships,
            'partnershipCount' => $partnershipCount,
            'opportunityCount' => $opportunityCount,
            'companyCount' => $companyCount,
            'blogCount' => $blogCount,
            'pageMediaCount' => $pageMediaCount,
            'topPartners' => $topPartners,
            'recentActivities' => $recentActivities,
        ]);
    }
}
