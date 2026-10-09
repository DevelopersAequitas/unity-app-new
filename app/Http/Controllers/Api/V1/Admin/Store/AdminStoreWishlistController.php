<?php

namespace App\Http\Controllers\Api\V1\Admin\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Store\Wishlist;
use App\Models\User;
use App\Services\Store\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreWishlistController extends BaseApiController
{
    protected WishlistService $wishlistService;

    public function __construct(WishlistService $wishlistService)
    {
        $this->wishlistService = $wishlistService;
    }

    /**
     * Admin: List all user wishlists with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('search'),
            'user_id' => $request->input('user_id'),
            'product_id' => $request->input('product_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];
        $perPage = (int) $request->input('per_page', 25);

        $wishlists = $this->wishlistService->getAdminWishlists($filters, $perPage);

        return $this->success($wishlists, 'Admin wishlists retrieved successfully');
    }

    /**
     * Admin: Get all wishlist items for a specific user.
     */
    public function userWishlist(string $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $wishlist = $this->wishlistService->getUserWishlist($user);

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                'email' => $user->email,
                'phone' => $user->phone,
                'company_name' => $user->company_name,
                'coins_balance' => (int) ($user->coins_balance ?? 0),
                'profile_photo_url' => $user->profile_photo_url,
            ],
            'wishlist' => $wishlist,
        ], 'User wishlist retrieved successfully');
    }

    /**
     * Admin: Get high-level statistics and top wishlisted products.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->wishlistService->getAdminStats();

        return $this->success($stats, 'Wishlist statistics retrieved');
    }

    /**
     * Admin: Delete a wishlist item.
     */
    public function destroy(string $id): JsonResponse
    {
        $item = Wishlist::findOrFail($id);
        $item->delete();

        return $this->success(['deleted' => true], 'Wishlist item deleted successfully');
    }
}
