<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Ads\ReviewAdBookingRequest;
use App\Models\AdBooking;
use App\Services\Ads\AdBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdBookingAdminController extends BaseApiController
{
    public function __construct(
        private readonly AdBookingService $adBookingService
    ) {}

    /**
     * GET /api/v1/admin/ad-bookings — List all ad booking requests with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('q', ''));
        $perPage = (int) ($request->query('per_page', 15));

        $isPgSql = DB::connection()->getDriverName() === 'pgsql';
        $likeOp = $isPgSql ? 'ILIKE' : 'LIKE';

        $bookings = AdBooking::query()
            ->with('user:id,first_name,last_name,email,phone')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search, $likeOp) {
                $query->where(function ($sub) use ($search, $likeOp) {
                    $sub->where('title', $likeOp, '%'.$search.'%')
                        ->orWhere('description', $likeOp, '%'.$search.'%');
                });
            })
            ->latest()
            ->paginate($perPage);

        $items = collect($bookings->items())->map(fn (AdBooking $booking): array => $this->formatBooking($booking));

        return $this->success([
            'items' => $items->values(),
            'pagination' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ], 'Ad booking requests fetched successfully.');
    }

    /**
     * GET /api/v1/admin/ad-bookings/{id} — View a single booking detail.
     */
    public function show(string $id): JsonResponse
    {
        $booking = AdBooking::with('user:id,first_name,last_name,email,phone')->find($id);

        if (! $booking) {
            return $this->error('Ad booking request not found.', 404);
        }

        return $this->success($this->formatBooking($booking), 'Ad booking request fetched successfully.');
    }

    /**
     * POST /api/v1/admin/ad-bookings/{id}/review — Approve or reject a booking.
     */
    public function review(ReviewAdBookingRequest $request, string $id): JsonResponse
    {
        $booking = AdBooking::find($id);

        if (! $booking) {
            return $this->error('Ad booking request not found.', 404);
        }

        if ($booking->status !== 'pending') {
            return $this->error('This booking has already been reviewed.', 422);
        }

        $admin = Auth::guard('admin')->user() ?? $request->user();

        if (! $admin) {
            return $this->error('Unauthenticated.', 401);
        }
        $validated = $request->validated();
        $remarks = $validated['admin_remarks'] ?? null;

        if ($validated['status'] === 'approved') {
            $booking = $this->adBookingService->approveBooking($booking, $admin, $remarks);
        } else {
            $booking = $this->adBookingService->rejectBooking($booking, $admin, $remarks);
        }

        $actionLabel = $validated['status'] === 'approved' ? 'approved' : 'rejected';

        return $this->success(
            $this->formatBooking($booking),
            "Ad booking request {$actionLabel} successfully."
        );
    }

    /**
     * Format a booking record for the admin API response.
     *
     * @return array<string, mixed>
     */
    private function formatBooking(AdBooking $booking): array
    {
        $user = $booking->relationLoaded('user') ? $booking->user : null;

        return [
            'id' => $booking->id,
            'user_id' => $booking->user_id,
            'user' => $user ? [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
            ] : null,
            'title' => $booking->title,
            'subtitle' => $booking->subtitle,
            'description' => $booking->description,
            'image_url' => $booking->image_url,
            'redirect_url' => $booking->redirect_url,
            'button_text' => $booking->button_text,
            'placement' => $booking->placement,
            'page_name' => $booking->page_name,
            'starts_at' => $booking->starts_at,
            'ends_at' => $booking->ends_at,
            'status' => $booking->status,
            'admin_remarks' => $booking->admin_remarks,
            'reviewed_by' => $booking->reviewed_by,
            'reviewed_at' => $booking->reviewed_at,
            'ad_id' => $booking->ad_id,
            'created_at' => $booking->created_at,
            'updated_at' => $booking->updated_at,
        ];
    }
}
