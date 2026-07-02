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

        $roleKey = AdminAccess::primaryCircleRoleKey($admin);
        $requiresSelection = in_array($roleKey, ['founder', 'director'], true);

        $allowedCircleIds = AdminAccess::allowedCircleIds($admin);
        $circleId = $request->query('circle_id');

        // By default, choose the first circle for founder/director roles
        if ($requiresSelection && empty($circleId) && ! empty($allowedCircleIds)) {
            $circleId = $allowedCircleIds[0];
        }

        // If selection is required, check if they have selected one
        $isCircleSelected = ! empty($circleId);

        // Fetch data
        if ($requiresSelection && ! $isCircleSelected) {
            // Load only circles to populate dropdown
            $dashboardData = $this->dashboardService->getDashboardData($admin, null, true);
        } else {
            // Load full dashboard data (either filtered by circle_id or for all circles merged)
            $dashboardData = $this->dashboardService->getDashboardData($admin, $circleId);
        }

        return view('admin.circle_member.dashboard', [
            'data' => $dashboardData,
            'roleLabel' => AdminAccess::primaryCircleRoleLabel($admin),
            'roleKey' => $roleKey,
            'requiresSelection' => $requiresSelection,
            'selectedCircleId' => $circleId,
            'isCircleSelected' => $isCircleSelected || ! $requiresSelection,
        ]);
    }
}
