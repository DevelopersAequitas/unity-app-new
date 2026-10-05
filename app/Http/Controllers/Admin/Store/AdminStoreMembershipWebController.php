<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\Store\Entitlement;
use App\Models\Store\MembershipLedger;
use App\Models\Store\Product;
use App\Models\Store\StoreMembershipPlan;
use App\Models\User;
use App\Services\Store\EntitlementService;
use App\Services\Store\StoreMembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminStoreMembershipWebController extends Controller
{
    protected StoreMembershipService $membershipService;

    protected EntitlementService $entitlementService;

    public function __construct(StoreMembershipService $membershipService, EntitlementService $entitlementService)
    {
        $this->membershipService = $membershipService;
        $this->entitlementService = $entitlementService;
    }

    // ==========================================
    // MEMBERSHIP PLANS & RENEWALS
    // ==========================================

    public function index(Request $request)
    {
        $plans = StoreMembershipPlan::orderBy('sort_order', 'asc')->paginate(15);
        $tiers = $plans;

        return view('admin.store.membership.index', compact('plans', 'tiers'));
    }

    public function storePlan(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'duration_months' => 'required|integer|min:1|max:60',
            'price_coins' => 'required|integer|min:0',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 1;

        StoreMembershipPlan::create($validated);

        return back()->with('success', 'Membership plan created.');
    }

    public function updatePlan(Request $request, string $id)
    {
        $plan = StoreMembershipPlan::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'duration_months' => 'required|integer|min:1|max:60',
            'price_coins' => 'required|integer|min:0',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $plan->update($validated);

        return back()->with('success', 'Membership plan updated.');
    }

    public function ledger(Request $request)
    {
        $query = MembershipLedger::with(['user', 'order'])->orderBy('created_at', 'desc');
        $search = $request->input('search', '');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('user', function ($uq) use ($s) {
                $uq->where('first_name', 'ILIKE', "%{$s}%")
                    ->orWhere('last_name', 'ILIKE', "%{$s}%")
                    ->orWhere('phone', 'ILIKE', "%{$s}%");
            });
        }

        $records = $query->paginate(20);
        $memberships = $records;

        return view('admin.store.membership.ledger', compact('records', 'memberships', 'search'));
    }

    // ==========================================
    // DIGITAL ENTITLEMENTS
    // ==========================================

    public function entitlements(Request $request)
    {
        $query = Entitlement::with(['user', 'product'])->orderBy('created_at', 'desc');
        $search = $request->input('search', '');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('user', function ($uq) use ($s) {
                $uq->where('first_name', 'ILIKE', "%{$s}%")
                    ->orWhere('last_name', 'ILIKE', "%{$s}%")
                    ->orWhere('email', 'ILIKE', "%{$s}%");
            });
        }

        $entitlements = $query->paginate(20);
        $users = User::select('id', 'first_name', 'last_name', 'display_name', 'email', 'phone')
            ->orderByRaw("COALESCE(NULLIF(first_name, ''), NULLIF(display_name, ''), NULLIF(email, ''), 'zzz') ASC")
            ->limit(200)
            ->get();
        $products = Product::where('status', 'ACTIVE')->orderBy('name')->get();

        return view('admin.store.membership.entitlements', compact('entitlements', 'users', 'products', 'search'));
    }

    public function grantEntitlement(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'feature_key' => 'required|string|max:100',
            'product_id' => 'nullable|uuid|exists:products,id',
            'expires_at' => 'nullable|date',
        ]);

        $admin = Auth::guard('admin')->user();
        $peer = User::findOrFail($validated['user_id']);

        $this->entitlementService->grantAccess(
            $peer,
            $validated['feature_key'],
            $validated['product_id'] ?? null,
            $validated['expires_at'] ? new \DateTime($validated['expires_at']) : null
        );

        return back()->with('success', "Digital entitlement granted to {$peer->first_name} {$peer->last_name}.");
    }

    public function revokeEntitlement(Request $request, string $id)
    {
        $entitlement = Entitlement::findOrFail($id);
        $admin = Auth::guard('admin')->user();

        $this->entitlementService->revokeAccess($entitlement, $admin->id);

        return back()->with('success', 'Entitlement access revoked.');
    }
}
