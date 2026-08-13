<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Ads\StoreAdBookingRequest;
use App\Models\AdBooking;
use App\Services\Ads\AdBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdBookingController extends BaseApiController
{
    public function __construct(
        private readonly AdBookingService $adBookingService
    ) {}

    /**
     * POST /api/v1/ad-bookings — User submits a new ad booking request.
     */
    public function store(StoreAdBookingRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $image = $request->hasFile('image') ? $request->file('image') : null;

        $booking = $this->adBookingService->createBooking($user, $data, $image);

        return $this->success([
            'id' => $booking->id,
            'title' => $booking->title,
            'status' => $booking->status,
            'created_at' => $booking->created_at,
        ], 'Ad booking request submitted successfully.', 201);
    }

    /**
     * GET /api/v1/ad-bookings — User lists their own ad booking requests.
     */
    public function myBookings(Request $request): JsonResponse
    {
        $user = $request->user();

        $bookings = AdBooking::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(fn (AdBooking $booking): array => $this->formatBooking($booking));

        return $this->success(
            $bookings->values(),
            $bookings->isEmpty() ? 'No ad booking requests found.' : 'Ad booking requests fetched successfully.'
        );
    }

    /**
     * GET /api/v1/ad-bookings/{id} — User views a single booking detail.
     */
    public function show(string $id): JsonResponse
    {
        $booking = AdBooking::query()
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (! $booking) {
            return $this->error('Ad booking request not found.', 404);
        }

        return $this->success($this->formatBooking($booking), 'Ad booking request fetched successfully.');
    }

    /**
     * Format a booking record for the API response.
     *
     * @return array<string, mixed>
     */
    private function formatBooking(AdBooking $booking): array
    {
        return [
            'id' => $booking->id,
            'user_id' => $booking->user_id,
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
            'reviewed_at' => $booking->reviewed_at,
            'ad_id' => $booking->ad_id,
            'created_at' => $booking->created_at,
            'updated_at' => $booking->updated_at,
        ];
    }
}
