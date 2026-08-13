<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Ads\ReviewAdBookingWebRequest;
use App\Models\AdBooking;
use App\Services\Ads\AdBookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdBookingAdminWebController extends Controller
{
    public function __construct(
        private readonly AdBookingService $adBookingService
    ) {}

    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('q', ''));

        $isPgSql = DB::connection()->getDriverName() === 'pgsql';
        $likeOp = $isPgSql ? 'ILIKE' : 'LIKE';

        $totalPending = AdBooking::query()->where('status', 'pending')->count();
        $totalApproved = AdBooking::query()->where('status', 'approved')->count();
        $totalRejected = AdBooking::query()->where('status', 'rejected')->count();
        $totalCount = AdBooking::query()->count();

        $bookings = AdBooking::query()
            ->with(['user', 'reviewer', 'ad'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search, $likeOp) {
                $query->where(function ($sub) use ($search, $likeOp) {
                    $sub->where('title', $likeOp, '%'.$search.'%')
                        ->orWhere('subtitle', $likeOp, '%'.$search.'%')
                        ->orWhere('description', $likeOp, '%'.$search.'%')
                        ->orWhereHas('user', function ($uQuery) use ($search, $likeOp) {
                            $uQuery->where('first_name', $likeOp, '%'.$search.'%')
                                ->orWhere('last_name', $likeOp, '%'.$search.'%')
                                ->orWhere('email', $likeOp, '%'.$search.'%');
                        });
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->appends($request->query());

        return view('admin.ad_bookings.index', compact(
            'bookings',
            'status',
            'search',
            'totalPending',
            'totalApproved',
            'totalRejected',
            'totalCount'
        ));
    }

    public function show(AdBooking $adBooking): View
    {
        $adBooking->loadMissing(['user', 'reviewer', 'ad']);

        return view('admin.ad_bookings.show', compact('adBooking'));
    }

    public function review(ReviewAdBookingWebRequest $request, AdBooking $adBooking): RedirectResponse
    {
        if ($adBooking->status !== 'pending') {
            return redirect()->back()->with('error', 'This ad booking request has already been reviewed.');
        }

        $admin = Auth::guard('admin')->user() ?? $request->user();

        if (! $admin) {
            return redirect()->back()->with('error', 'You must be logged in as an admin to perform this action.');
        }

        $validated = $request->validated();
        $remarks = $validated['admin_remarks'] ?? null;

        if ($validated['status'] === 'approved') {
            $this->adBookingService->approveBooking($adBooking, $admin, $remarks);
            $message = 'Ad booking request approved successfully and live ad created.';
        } else {
            $this->adBookingService->rejectBooking($adBooking, $admin, $remarks);
            $message = 'Ad booking request rejected successfully.';
        }

        return redirect()->route('admin.ad-bookings.index')->with('success', $message);
    }
}
