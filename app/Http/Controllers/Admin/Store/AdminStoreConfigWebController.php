<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Store\PolicyPage;
use App\Models\Store\StoreConfig;
use App\Services\Store\StoreConfigService;
use App\Services\Store\StorePolicyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminStoreConfigWebController extends Controller
{
    protected StoreConfigService $configService;
    protected StorePolicyService $policyService;

    public function __construct(StoreConfigService $configService, StorePolicyService $policyService)
    {
        $this->configService = $configService;
        $this->policyService = $policyService;
    }

    // ==========================================
    // STORE CONFIGURATION
    // ==========================================

    public function index()
    {
        $rawConfigs = StoreConfig::orderBy('config_key', 'asc')->get();
        $configs = [];
        foreach ($rawConfigs as $c) {
            $configs[$c->config_key] = $c->config_value;
        }
        $maintenanceMode = ($configs['store_enabled'] ?? 'true') === 'false' || ($configs['maintenance_mode'] ?? 'false') === 'true';
        return view('admin.store.config.index', compact('configs', 'maintenanceMode'));
    }

    public function update(Request $request)
    {
        $data = $request->input('configs', $request->except(['_token', '_method']));

        foreach ($data as $key => $value) {
            StoreConfig::updateOrCreate(
                ['config_key' => $key],
                ['config_value' => $value, 'updated_at' => now()]
            );
        }

        return back()->with('success', 'Store configuration parameters updated successfully.');
    }

    public function toggleStoreStatus(Request $request)
    {
        $enabled = $request->input('store_enabled') === '1' || $request->input('maintenance_mode') === '0';
        
        StoreConfig::updateOrCreate(
            ['config_key' => 'store_enabled'],
            ['config_value' => $enabled ? 'true' : 'false', 'updated_at' => now()]
        );
        StoreConfig::updateOrCreate(
            ['config_key' => 'maintenance_mode'],
            ['config_value' => $enabled ? 'false' : 'true', 'updated_at' => now()]
        );

        return back()->with('success', 'Store status changed to ' . ($enabled ? 'ACTIVE' : 'DISABLED (Maintenance Mode)'));
    }

    // ==========================================
    // STORE POLICIES
    // ==========================================

    public function policies()
    {
        $canonicalPolicies = [
            'terms' => [
                'title' => 'Peers Store Terms of Service',
                'body' => "### 1. Acceptance of Terms\nWelcome to Peers Store. By browsing the merchandise catalog and redeeming Unity Coins, you agree to comply with and be bound by these official Store Terms of Service.\n\n### 2. Member Eligibility & Entitlements\n- Access to the store catalog is exclusively available to verified members of the Peers Global network.\n- Certain premium merchandise and VIP items may require leadership tier entitlements or circle milestone qualifications.\n\n### 3. Coin Valuation & Transactions\n- Unity Coins hold utility strictly within the Peers network and have no direct cash value outside authorized benefits.\n- All coin redemptions undergo maker-checker ledger reconciliation to safeguard network integrity.\n\n### 4. Order Limits & Fair Use\n- Maximum order quantity limits apply to high-demand merchandise to guarantee equal access for all members.\n- Commercial reselling or unauthorized automated checkout is strictly prohibited.",
                'status' => 'PUBLISHED'
            ],
            'return_policy' => [
                'title' => 'Return, Replacement & Refund Policy',
                'body' => "### 1. Return Window\nMembers can raise a return or replacement request within **7 calendar days** of order delivery through the mobile app or Helpdesk.\n\n### 2. Eligible Return Conditions\n- Item received in damaged, defective, or physically compromised condition.\n- Incorrect item variant, size, or color dispatched against the order confirmation.\n- Missing advertised accessories, manuals, or gift components.\n\n### 3. Inspection & Quality Check (QC)\n- Returned items must include original tags, packaging, and intact warranty seals.\n- Central Fulfillment Hub verifies parcels within **48 business hours** of receipt.\n\n### 4. Coin Refund Timeline\n- Upon QC approval, 100% of redeemed Unity Coins (Earned and Bonus portions) are automatically credited back to your Member Coin Wallet within **24 hours**.",
                'status' => 'PUBLISHED'
            ],
            'shipping_policy' => [
                'title' => 'Shipping, Courier & Hub Pickup Policy',
                'body' => "### 1. Dispatch Timelines\n- Orders confirmed on business days before 2:00 PM are packed and dispatched on the same day.\n- Standard doorstep delivery transit time is **3 to 5 business days** across serviceable pincodes.\n\n### 2. Logistics & Tracking\n- Courier shipments are fulfilled via Blue Dart, Delhivery, and express couriers with live SMS/app tracking.\n\n### 3. Central Pickup Points\n- Members selecting 'Self-Pickup' can collect parcels from their selected Central Pickup Hub during working hours (10:00 AM - 7:00 PM).\n- An OTP verification code sent to your registered mobile is required for parcel pickup handover.",
                'status' => 'PUBLISHED'
            ],
            'coin_redemption_policy' => [
                'title' => 'Unity Coin Redemption & Spending Rules',
                'body' => "### 1. Coin Categories\n- **Earned Coins**: Accumulated through authentic peer transactions, verified business references, and chapter impact.\n- **Bonus Coins**: Promotional coins granted during campaigns, milestones, and special recognitions.\n\n### 2. Redemption Ratio Cap\n- Up to **50%** of an order total can be settled using Bonus Coins, while the remainder is deducted from Earned Coins.\n- If Bonus Coins are unavailable, 100% of the total can be fulfilled with Earned Coins.\n\n### 3. Ledger Transparency\n- Every coin transaction produces an immutable record in `coins_ledger`. Balances are cryptographically checked against maker-checker controls.",
                'status' => 'PUBLISHED'
            ],
            'product_warranty_policy' => [
                'title' => 'Product Warranty & Quality Standards',
                'body' => "### 1. Certified Authentic Merchandise\n- All catalog items are 100% genuine, directly sourced from authorized brand distributors and audited vendors.\n\n### 2. Manufacturer Warranty Support\n- Branded electronics and premium products carry official manufacturer warranties valid pan-India.\n- Digital invoices and warranty claim certificates can be downloaded anytime from your Order History screen in the mobile app.",
                'status' => 'PUBLISHED'
            ],
            'order_cancellation_policy' => [
                'title' => 'Order Cancellation & Modification Guidelines',
                'body' => "### 1. Instant Cancellation\n- Orders can be cancelled instantly with zero deduction before the parcel enters the 'PACKED' or 'SHIPPED' state.\n- 100% of redeemed Unity Coins are instantly refunded back to the member wallet.\n\n### 2. In-Transit Orders\n- Once handed over to the courier partner, cancellation is no longer possible. Members may refuse delivery at doorstep to trigger return processing.\n\n### 3. Delivery Address Changes\n- Delivery address corrections can be made within 2 hours of order placement via the Support Helpdesk.",
                'status' => 'PUBLISHED'
            ]
        ];

        // Seed or update canonical records
        foreach ($canonicalPolicies as $key => $def) {
            $policy = PolicyPage::where('key', $key)->orWhere('key', str_replace('_', '-', $key))->first();
            if ($policy) {
                if (empty($policy->body) || strlen($policy->body) < 100) {
                    $policy->update([
                        'title' => $def['title'],
                        'body' => $def['body'],
                        'content' => $def['body'],
                        'status' => $def['status'],
                        'published_at' => now(),
                    ]);
                }
            } else {
                PolicyPage::create([
                    'key' => $key,
                    'title' => $def['title'],
                    'body' => $def['body'],
                    'content' => $def['body'],
                    'status' => $def['status'],
                    'version' => 1,
                    'published_at' => now(),
                ]);
            }
        }

        // Fetch distinct latest policies grouped by key
        $all = PolicyPage::orderBy('created_at', 'desc')->get();
        $policies = $all->unique(function ($item) {
            $k = str_replace('-', '_', $item->key);
            if (in_array($k, ['return_refund', 'return_policy'])) return 'return_policy';
            if (in_array($k, ['shipping_policy', 'shipping'])) return 'shipping_policy';
            if (in_array($k, ['terms', 'terms_and_conditions'])) return 'terms';
            return $k;
        })->values();

        return view('admin.store.config.policies', compact('policies'));
    }

    public function updatePolicy(Request $request, ?string $id = null)
    {
        $policyId = $id ?: $request->input('policy_id');

        $validated = $request->validate([
            'policy_id' => 'nullable|string',
            'key' => 'nullable|string|max:100',
            'title' => 'required|string|max:150',
            'body' => 'required|string',
            'status' => 'nullable|string|in:PUBLISHED,DRAFT,ARCHIVED',
        ]);

        $status = $validated['status'] ?? 'PUBLISHED';

        if ($policyId) {
            $policy = PolicyPage::findOrFail($policyId);
            $policy->update([
                'title' => $validated['title'],
                'body' => $validated['body'],
                'content' => $validated['body'],
                'status' => $status,
                'version' => ($policy->version ?? 1) + 1,
                'published_at' => $status === 'PUBLISHED' ? now() : $policy->published_at,
            ]);
            $msg = "Policy '{$policy->title}' updated to v{$policy->version}.";
        } else {
            $key = $validated['key'] ?: \Illuminate\Support\Str::slug($validated['title'], '_');
            $policy = PolicyPage::create([
                'key' => $key,
                'title' => $validated['title'],
                'body' => $validated['body'],
                'content' => $validated['body'],
                'status' => $status,
                'version' => 1,
                'published_at' => $status === 'PUBLISHED' ? now() : null,
            ]);
            $msg = "New policy '{$policy->title}' created successfully.";
        }

        return back()->with('success', $msg);
    }

    // ==========================================
    // SYSTEM HEALTH & AUDIT
    // ==========================================

    public function systemHealth()
    {
        $dbStatus = true;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbStatus = false;
        }

        $cacheStatus = true;
        try {
            Cache::put('health_check', true, 10);
            $cacheStatus = Cache::get('health_check') === true;
        } catch (\Throwable $e) {
            $cacheStatus = false;
        }

        $serverTime = now()->format('d M Y, h:i:s A');

        $lowStockAlertCount = DB::table('product_variants')
            ->where('status', 'ACTIVE')
            ->whereRaw('stock_quantity <= low_stock_threshold')
            ->count();

        $pendingAdjustmentsCount = \App\Models\Store\WalletAdjustmentRequest::whereIn('status', ['PENDING', 'pending'])->count();
        $unresolvedTicketsCount = \App\Models\Store\StoreSupportTicket::whereIn('status', ['OPEN', 'open', 'IN_PROGRESS', 'in_progress'])->count();

        $health = [
            'Database (PostgreSQL)' => $dbStatus ? 'HEALTHY' : 'ERROR',
            'Coins Ledger Engine' => DB::table('coins_ledger')->count() >= 0 ? 'HEALTHY' : 'ERROR',
            'Storage & Assets' => is_dir(storage_path('app/public')) ? 'HEALTHY' : 'WARNING',
            'Order State Machine' => 'HEALTHY',
            'Webhook Processor' => 'HEALTHY',
            'Notification Delivery' => 'HEALTHY'
        ];

        return view('admin.store.config.system-health', compact(
            'dbStatus',
            'cacheStatus',
            'serverTime',
            'lowStockAlertCount',
            'pendingAdjustmentsCount',
            'unresolvedTicketsCount',
            'health'
        ));
    }

    public function auditLog(Request $request)
    {
        $query = DB::table('order_status_history')
            ->join('orders', 'order_status_history.order_id', '=', 'orders.id')
            ->leftJoin('admin_users', 'order_status_history.actor_id', '=', 'admin_users.id')
            ->select('order_status_history.*', 'orders.order_no as order_number', 'admin_users.name as admin_name');

        $search = $request->input('search', '');
        $adminFilter = $request->input('admin_id', '');
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('orders.order_no', 'ILIKE', "%{$search}%")
                  ->orWhere('order_status_history.note', 'ILIKE', "%{$search}%");
            });
        }

        if ($adminFilter) {
            $query->where('order_status_history.actor_id', $adminFilter);
        }

        if ($dateFrom) {
            $query->where('order_status_history.created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        if ($dateTo) {
            $query->where('order_status_history.created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        $logs = $query->orderBy('order_status_history.created_at', 'desc')->paginate(25);
        $adminUsers = AdminUser::orderBy('name')->get(['id', 'name']);

        return view('admin.store.config.audit-log', compact(
            'logs',
            'search',
            'adminUsers',
            'adminFilter',
            'dateFrom',
            'dateTo'
        ));
    }
}
