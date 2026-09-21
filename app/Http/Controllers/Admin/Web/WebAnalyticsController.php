<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebAnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $metrics = [
            'total_visitors' => '48,920',
            'unique_promoters' => '12,410',
            'circle_applications' => '342',
            'partnership_inquiries' => '186',
            'page_views_monthly' => '184,200',
            'bounce_rate' => '24.8%',
            'avg_session' => '4m 18s',
        ];

        return view('admin.web.analytics.index', [
            'metrics' => $metrics,
        ]);
    }
}
