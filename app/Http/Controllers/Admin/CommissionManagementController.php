<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminStoreCommissionRateRequest;
use App\Http\Requests\Admin\AdminUpdateCommissionRatesRequest;
use App\Models\AdminUser;
use App\Services\Admin\CommissionManagementService;
use App\Support\AdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommissionManagementController extends Controller
{
    public function __construct(
        private readonly CommissionManagementService $commissionService,
    ) {}

    /**
     * Display the Commission Management overview and rates matrix.
     */
    public function index(Request $request): View|JsonResponse
    {
        /** @var AdminUser|null $admin */
        $admin = auth('admin')->user();

        if (! AdminAccess::isSuper($admin) && ! AdminAccess::isSectionAllowed($admin, 'Commission Management')) {
            abort(403, 'Unauthorized: Only Super Admins can access Commission Management.');
        }

        $overview = $this->commissionService->getCommissionOverview();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $overview,
            ]);
        }

        return view('admin.commissions.index', [
            'metrics' => $overview['metrics'],
            'rates' => $overview['rates'],
            'apiEndpoint' => $overview['api_endpoint'],
            'apiUpdateEndpoint' => $overview['api_update_endpoint'],
            'adminUser' => $admin,
        ]);
    }

    /**
     * Bulk update commission rates for all leadership roles.
     */
    public function updateBulk(AdminUpdateCommissionRatesRequest $request): RedirectResponse|JsonResponse
    {
        /** @var AdminUser|null $admin */
        $admin = auth('admin')->user();

        if (! AdminAccess::isSuper($admin) && ! AdminAccess::isSectionAllowed($admin, 'Commission Management')) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Super Admin has permission to modify commission structures.',
                ], 403);
            }
            abort(403, 'Unauthorized access.');
        }

        $result = $this->commissionService->updateBulkRates(
            (array) $request->validated('commission_rates'),
            $admin
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Commission rates matrix updated successfully.',
                'data' => $result,
            ]);
        }

        return redirect()
            ->route('admin.commissions.index')
            ->with('success', 'Leadership commission rates updated successfully!');
    }

    /**
     * Add a new leadership role commission configuration.
     */
    public function store(AdminStoreCommissionRateRequest $request): RedirectResponse|JsonResponse
    {
        /** @var AdminUser|null $admin */
        $admin = auth('admin')->user();

        if (! AdminAccess::isSuper($admin)) {
            abort(403, 'Unauthorized.');
        }

        $record = $this->commissionService->storeRate($request->validated());

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'New commission role configuration created successfully.',
                'data' => $record,
            ], 201);
        }

        return redirect()
            ->route('admin.commissions.index')
            ->with('success', "Commission configuration for {$record['role_name']} created successfully!");
    }

    /**
     * Delete / remove a role commission configuration.
     */
    public function destroy(string $id, Request $request): RedirectResponse|JsonResponse
    {
        /** @var AdminUser|null $admin */
        $admin = auth('admin')->user();

        if (! AdminAccess::isSuper($admin)) {
            abort(403, 'Unauthorized.');
        }

        $deleted = $this->commissionService->deleteRate($id);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => $deleted,
                'message' => $deleted ? 'Commission configuration deleted successfully.' : 'Configuration not found.',
            ]);
        }

        return redirect()
            ->route('admin.commissions.index')
            ->with('success', 'Commission configuration removed successfully.');
    }
}
