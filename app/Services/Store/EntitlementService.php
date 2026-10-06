<?php

namespace App\Services\Store;

use App\Constants\StoreErrorCodes;
use App\Models\Store\Entitlement;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class EntitlementService
{
    public function getUserLibrary(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Entitlement::with(['product.primaryImage'])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getEntitlementDetails(User $user, string $id): Entitlement
    {
        $entitlement = Entitlement::with(['product.images', 'order'])
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $entitlement) {
            throw new Exception(StoreErrorCodes::PRODUCT_NOT_FOUND, 404);
        }

        return $entitlement;
    }

    public function generateSignedAccessUrl(User $user, string $id): array
    {
        $entitlement = $this->getEntitlementDetails($user, $id);

        if ($entitlement->status !== 'ACTIVE' || ! $entitlement->is_active) {
            throw new Exception(StoreErrorCodes::FORBIDDEN, 403);
        }

        if ($entitlement->end_date && $entitlement->end_date < now()->toDateString()) {
            throw new Exception(StoreErrorCodes::FORBIDDEN, 403);
        }

        $contentRef = $entitlement->content_reference ?? ($entitlement->product ? $entitlement->product->digital_asset_url : null);

        // Generate temporary URL (15 minutes) or fallback to direct URL
        $signedUrl = null;
        if ($contentRef) {
            try {
                $signedUrl = Storage::disk('public')->temporaryUrl($contentRef, now()->addMinutes(15));
            } catch (\Throwable $e) {
                $signedUrl = filter_var($contentRef, FILTER_VALIDATE_URL) ? $contentRef : Storage::disk('public')->url($contentRef);
            }
        }

        return [
            'entitlement_id' => $entitlement->id,
            'access_method' => $entitlement->access_method ?? 'SIGNED_URL',
            'signed_url' => $signedUrl ?? $contentRef,
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
        ];
    }
}
