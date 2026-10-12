<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\Store\Refund;
use App\Models\Store\StoreReturn;
use App\Services\Store\StoreRefundService;
use App\Services\Store\StoreReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminStoreReturnWebController extends Controller
{
    protected StoreReturnService $returnService;

    protected StoreRefundService $refundService;

    public function __construct(StoreReturnService $returnService, StoreRefundService $refundService)
    {
        $this->returnService = $returnService;
        $this->refundService = $refundService;
    }

    public function index(Request $request)
    {
        $query = StoreReturn::with(['user', 'order.items.product'])->orderBy('created_at', 'desc');

        $tab = $request->input('tab', 'all');
        $search = $request->input('search', '');

        $tabs = [
            'all' => 'All Requests',
            'pending' => 'Pending Review',
            'approved' => 'Approved',
            'completed' => 'Completed / Refunded',
            'rejected' => 'Rejected',
        ];

        if ($tab !== 'all') {
            $query->where('status', 'ILIKE', $tab);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('return_no', 'ILIKE', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('first_name', 'ILIKE', "%{$search}%")
                            ->orWhere('last_name', 'ILIKE', "%{$search}%")
                            ->orWhere('display_name', 'ILIKE', "%{$search}%")
                            ->orWhere('phone', 'ILIKE', "%{$search}%")
                            ->orWhere('email', 'ILIKE', "%{$search}%")
                            ->orWhere(DB::raw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))"), 'ILIKE', "%{$search}%");
                    });
            });
        }

        $returns = $query->paginate(20);

        return view('admin.store.returns.index', compact('returns', 'tab', 'search', 'tabs'));
    }

    public function show(string $id)
    {
        $return = StoreReturn::with(['user', 'order.items.product', 'photos'])->findOrFail($id);

        return view('admin.store.returns.show', compact('return'));
    }

    public function approve(Request $request, string $id)
    {
        $return = StoreReturn::with(['user', 'order'])->findOrFail($id);
        $admin = Auth::guard('admin')->user();

        $validated = $request->validate([
            'inspection_notes' => 'nullable|string|max:500',
            'refund_coins' => 'nullable|integer|min:1',
        ]);

        $refundCoins = (int) ($validated['refund_coins'] ?? ($return->order ? $return->order->total_coins : 0));

        DB::transaction(function () use ($return, $admin, $validated, $refundCoins) {
            $notes = $validated['inspection_notes'] ?? ('Approved by ' . ($admin ? $admin->name : 'Admin'));

            $return->update([
                'status' => 'APPROVED',
                'reviewed_by' => $admin ? $admin->id : null,
                'reviewed_at' => now(),
                'quality_check_passed' => true,
                'quality_check_notes' => $notes,
            ]);

            if ($return->order && $return->user && $refundCoins > 0) {
                // If StoreRefundService expects ?App\Models\User instead of AdminUser, pass $admin only if instance of User to avoid TypeError
                $adminArg = ($admin instanceof \App\Models\User) ? $admin : null;

                $refund = $this->refundService->processOrderRefund(
                    $return->order,
                    $return->user,
                    $refundCoins,
                    'RETURN_REFUND',
                    $notes,
                    $return->id,
                    null,
                    $adminArg
                );

                if ($admin && empty($refund->processed_by)) {
                    $refund->update(['processed_by' => $admin->id]);
                }

                $return->update(['status' => 'COMPLETED']);
            }
        });

        return back()->with('success', 'Return request approved. Refund coins credited back to peer wallet ledger.');
    }

    public function reject(Request $request, string $id)
    {
        $return = StoreReturn::findOrFail($id);
        $admin = Auth::guard('admin')->user();

        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:3|max:500',
        ]);

        $return->update([
            'status' => 'REJECTED',
            'rejection_reason' => $validated['rejection_reason'],
            'reviewed_by' => $admin ? $admin->id : null,
            'reviewed_at' => now(),
            'quality_check_passed' => false,
        ]);

        return back()->with('success', 'Return request rejected with reason remarks.');
    }

    public function refunds(Request $request)
    {
        $search = $request->input('search', '');
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');

        $query = Refund::with(['order', 'user', 'processor'])->orderBy('created_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('refund_no', 'ILIKE', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('first_name', 'ILIKE', "%{$search}%")
                            ->orWhere('last_name', 'ILIKE', "%{$search}%")
                            ->orWhere('display_name', 'ILIKE', "%{$search}%")
                            ->orWhere('phone', 'ILIKE', "%{$search}%")
                            ->orWhere('email', 'ILIKE', "%{$search}%")
                            ->orWhere(DB::raw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))"), 'ILIKE', "%{$search}%");
                    });
            });
        }
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $totalRefundedCoins = (int) (clone $query)->sum('refund_coins');
        $refunds = $query->paginate(20);

        return view('admin.store.returns.refunds', compact('refunds', 'search', 'dateFrom', 'dateTo', 'totalRefundedCoins'));
    }
}
