<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CircleMember\CircleMemberDashboardService;
use App\Support\AdminAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CircleMemberDashboardController extends Controller
{
    public function __construct(
        private readonly CircleMemberDashboardService $dashboardService
    ) {}

    /**
     * Display the circle member dashboard view.
     */
    public function index(Request $request): View
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin && AdminAccess::isCircleScoped($admin), 403);

        $dashboardData = $this->dashboardService->getDashboardData($admin);

        return view('admin.circle_member.dashboard', [
            'data' => $dashboardData,
            'roleLabel' => AdminAccess::primaryCircleRoleLabel($admin),
        ]);
    }
}
