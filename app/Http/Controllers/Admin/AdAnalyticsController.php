<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ads\AdAnalyticsService;
use Illuminate\View\View;

class AdAnalyticsController extends Controller
{
    public function __construct(
        private readonly AdAnalyticsService $analyticsService
    ) {}

    public function index(): View
    {
        $stats = $this->analyticsService->getDashboardStats();
        $charts = $this->analyticsService->getDashboardCharts();

        return view('admin.ads.dashboard', compact('stats', 'charts'));
    }

    public function detailedReport(): View
    {
        $stats = $this->analyticsService->getDashboardStats();
        $charts = $this->analyticsService->getDashboardCharts();

        return view('admin.ads.analytics', compact('stats', 'charts'));
    }
}
