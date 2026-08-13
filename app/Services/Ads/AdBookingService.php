<?php

declare(strict_types=1);

namespace App\Services\Ads;

use App\Models\Ad;
use App\Models\AdBooking;
use App\Models\User;
use App\Services\Media\FileUploadService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

class AdBookingService
{
    public function __construct(
        private readonly FileUploadService $fileUploadService
    ) {}

    /**
     * Create a new ad booking request from a user.
     *
     * @param  array<string, mixed>  $data  Validated form data
     */
    public function createBooking(User $user, array $data, ?UploadedFile $image = null): AdBooking
    {
        $bookingData = [
            'user_id' => $user->id,
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? null,
            'description' => $data['description'] ?? null,
            'redirect_url' => $data['redirect_url'] ?? null,
            'button_text' => $data['button_text'] ?? null,
            'placement' => $data['placement'] ?? null,
            'page_name' => $data['page_name'] ?? null,
            'starts_at' => ! empty($data['starts_at']) ? Carbon::parse($data['starts_at']) : null,
            'ends_at' => ! empty($data['ends_at']) ? Carbon::parse($data['ends_at']) : null,
            'status' => 'pending',
        ];

        if ($image) {
            $fileModel = $this->fileUploadService->store($image, $user);
            $bookingData['image_file_id'] = $fileModel->id;
        }

        return AdBooking::create($bookingData);
    }

    /**
     * Approve a booking and create the corresponding Ad record.
     */
    public function approveBooking(AdBooking $booking, Authenticatable $admin, ?string $remarks = null): AdBooking
    {
        $ad = Ad::create([
            'title' => $booking->title,
            'subtitle' => $booking->subtitle,
            'description' => $booking->description,
            'image_path' => $booking->image_file_id,
            'redirect_url' => $booking->redirect_url,
            'button_text' => $booking->button_text,
            'placement' => $booking->placement,
            'page_name' => $booking->page_name,
            'starts_at' => $booking->starts_at,
            'ends_at' => $booking->ends_at,
            'is_active' => true,
            'created_by' => $booking->user_id,
        ]);

        $booking->update([
            'status' => 'approved',
            'admin_remarks' => $remarks,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'ad_id' => $ad->id,
        ]);

        return $booking->refresh();
    }

    /**
     * Reject a booking request.
     */
    public function rejectBooking(AdBooking $booking, Authenticatable $admin, ?string $remarks = null): AdBooking
    {
        $booking->update([
            'status' => 'rejected',
            'admin_remarks' => $remarks,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        return $booking->refresh();
    }
}
