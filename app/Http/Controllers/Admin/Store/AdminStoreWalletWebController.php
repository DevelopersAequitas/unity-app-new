<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\Store\WalletAdjustmentRequest;
use App\Models\User;
use App\Services\Store\StoreWalletService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminStoreWalletWebController extends Controller
{
    protected StoreWalletService $walletService;

    public function __construct(StoreWalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $freezeFilter = $request->input('is_frozen', '');

        $query = User::with([
            'cityRelation',
            'mainBusinessCategory',
            'businessCategory',
            'level4Category',
            'circleMembers.level1Category',
            'circleMembers.level2Category',
            'circleMembers.level3Category',
            'circleMembers.level4Category'
        ])->orderBy('coins_balance', 'desc');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'ILIKE', "%{$search}%")
                  ->orWhere('last_name', 'ILIKE', "%{$search}%")
                  ->orWhere('display_name', 'ILIKE', "%{$search}%")
                  ->orWhere('company_name', 'ILIKE', "%{$search}%")
                  ->orWhere('email', 'ILIKE', "%{$search}%")
                  ->orWhere('phone', 'ILIKE', "%{$search}%")
                  ->orWhere('city', 'ILIKE', "%{$search}%")
                  ->orWhere('business_city', 'ILIKE', "%{$search}%");
            });
        }

        if ($freezeFilter === '1') {
            $query->where('wallet_state', 'FROZEN');
        } elseif ($freezeFilter === '0') {
            $query->where(function($q) {
                $q->where('wallet_state', 'ACTIVE')->orWhereNull('wallet_state');
            });
        }

        $users = $query->paginate(20);
        return view('admin.store.wallet.index', compact('users', 'search', 'freezeFilter'));
    }

    public function show(string $userId)
    {
        $user = User::findOrFail($userId);
        
        $totalBalance = (int) $user->coins_balance;
        $earnedBalance = (int) DB::table('coins_ledger')->where('user_id', $user->id)->where('bucket', 'EARNED')->sum('amount');
        $bonusBalance = (int) DB::table('coins_ledger')->where('user_id', $user->id)->where('bucket', 'BONUS')->sum('amount');

        $ledgers = DB::table('coins_ledger')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        $pendingAdjustments = WalletAdjustmentRequest::with(['requester'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['PENDING', 'pending'])
            ->get();

        return view('admin.store.wallet.show', compact('user', 'ledgers', 'pendingAdjustments', 'earnedBalance', 'bonusBalance', 'totalBalance'));
    }

    public function freeze(Request $request, string $userId)
    {
        $user = User::findOrFail($userId);
        $reason = $request->input('reason', 'Administrative action');
        $adminId = Auth::guard('admin')->id() ?? '00000000-0000-0000-0000-000000000000';

        $this->walletService->freezeWallet($user, $reason, $adminId);

        return back()->with('success', "Wallet state updated for {$user->name}.");
    }

    public function adjustments(Request $request)
    {
        $pendingAdjustments = WalletAdjustmentRequest::with(['user', 'requester'])
            ->whereIn('status', ['PENDING', 'pending'])
            ->orderBy('requested_at', 'desc')
            ->get();

        $historicalAdjustments = WalletAdjustmentRequest::with(['user', 'requester', 'approver'])
            ->whereNotIn('status', ['PENDING', 'pending'])
            ->orderBy('requested_at', 'desc')
            ->paginate(20);

        return view('admin.store.wallet.adjustments', compact('pendingAdjustments', 'historicalAdjustments'));
    }

    public function storeAdjustmentRequest(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required',
            'coin_type' => 'required|string|in:earned,bonus',
            'action' => 'required|string|in:credit,debit',
            'amount' => 'required|integer|min:1',
            'reason' => 'required|string|min:5|max:500'
        ]);

        $adminId = Auth::guard('admin')->id() ?? '00000000-0000-0000-0000-000000000000';

        WalletAdjustmentRequest::create([
            'user_id' => $validated['user_id'],
            'requested_by' => $adminId,
            'adjustment_type' => $validated['action'] === 'credit' ? 'ADJUST_CREDIT' : 'ADJUST_DEBIT',
            'bucket' => strtoupper($validated['coin_type']),
            'coins' => (int)$validated['amount'],
            'reason' => $validated['reason'],
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        return back()->with('success', 'Adjustment request submitted to Maker-Checker queue.');
    }

    public function approveAdjustment(Request $request, string $id)
    {
        $adj = WalletAdjustmentRequest::with('user')->findOrFail($id);
        $admin = Auth::guard('admin')->user();
        $adminId = $admin->id ?? '00000000-0000-0000-0000-000000000000';

        // Segregation of duties: Maker cannot approve own request
        if ($adj->requested_by === $adminId) {
            return back()->with('error', 'Maker-Checker Violation: You cannot approve your own adjustment request. Another admin must authorize it.');
        }

        DB::transaction(function() use ($adj, $adminId) {
            $peer = $adj->user;
            if ($adj->adjustment_type === 'ADJUST_CREDIT') {
                $this->walletService->creditCoins(
                    $peer,
                    $adj->coins,
                    $adj->bucket,
                    'ADMIN_ADJUSTMENT',
                    $adj->id,
                    "Maker-Checker Approved: {$adj->reason}"
                );
            } else {
                $this->walletService->debitCoins(
                    $peer,
                    $adj->coins,
                    $adj->bucket,
                    'ADMIN_ADJUSTMENT',
                    $adj->id,
                    "Maker-Checker Approved: {$adj->reason}"
                );
            }

            $adj->update([
                'status' => 'APPROVED',
                'approved_by' => $adminId,
                'approved_at' => now()
            ]);
        });

        return back()->with('success', 'Adjustment request approved. Coins ledger updated.');
    }

    public function rejectAdjustment(Request $request, string $id)
    {
        $adj = WalletAdjustmentRequest::findOrFail($id);
        $adminId = Auth::guard('admin')->id() ?? '00000000-0000-0000-0000-000000000000';
        $reason = $request->input('rejection_reason', 'Rejected by administrator');

        $adj->update([
            'status' => 'REJECTED',
            'approved_by' => $adminId,
            'rejected_at' => now(),
            'rejection_reason' => $reason
        ]);

        return back()->with('success', 'Adjustment request rejected.');
    }

    public function economy(Request $request)
    {
        $totalCirculation = (int) DB::table('users')->sum('coins_balance');
        $totalEarnedInCirculation = (int) DB::table('coins_ledger')->where('bucket', 'EARNED')->sum('amount');
        $totalBonusInCirculation = (int) DB::table('coins_ledger')->where('bucket', 'BONUS')->sum('amount');
        $totalBurnedInStore = (int) DB::table('coins_ledger')->where('amount', '<', 0)->sum(DB::raw('ABS(amount)'));
        $totalGrantedThisMonth = (int) DB::table('coins_ledger')->where('created_at', '>=', now()->startOfMonth())->where('amount', '>', 0)->sum('amount');

        $topHolders = User::orderBy('coins_balance', 'desc')->limit(10)->get();
        $recentBurnTransactions = DB::table('coins_ledger')
            ->leftJoin('users', 'coins_ledger.user_id', '=', 'users.id')
            ->select(
                'coins_ledger.*',
                'users.first_name',
                'users.last_name',
                'users.display_name'
            )
            ->where('coins_ledger.amount', '<', 0)
            ->orderBy('coins_ledger.created_at', 'desc')
            ->limit(10)
            ->get();

        return view('admin.store.wallet.economy', compact(
            'totalCirculation',
            'totalEarnedInCirculation',
            'totalBonusInCirculation',
            'totalBurnedInStore',
            'totalGrantedThisMonth',
            'topHolders',
            'recentBurnTransactions'
        ));
    }
}
