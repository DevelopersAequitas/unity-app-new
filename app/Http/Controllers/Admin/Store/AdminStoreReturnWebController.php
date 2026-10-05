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
                        $uq->where('name', 'ILIKE', "%{$search}%");
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
        ]);

        DB::transaction(function () use ($return, $admin, $validated) {
            $this->returnService->approveReturn($return, $validated['inspection_notes'] ?? 'Approved by '.$admin->name, $admin->id);
            $this->refundService->processReturnRefund($return, $admin->id);
        });

        return back()->with('success', 'Return request approved. Refund coins credited back to peer wallet ledger.');
    }

    public function reject(Request $request, string $id)
    {
        $return = StoreReturn::findOrFail($id);
        $admin = Auth::guard('admin')->user();

        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:5|max:500',
        ]);

        $this->returnService->rejectReturn($return, $validated['rejection_reason'], $admin->id);

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
                        $uq->where('name', 'ILIKE', "%{$search}%");
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
