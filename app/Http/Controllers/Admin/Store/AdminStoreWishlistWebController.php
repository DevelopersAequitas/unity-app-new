<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\Store\Product;
use App\Models\Store\Wishlist;
use App\Models\User;
use App\Services\Store\WishlistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminStoreWishlistWebController extends Controller
{
    protected WishlistService $wishlistService;

    public function __construct(WishlistService $wishlistService)
    {
        $this->wishlistService = $wishlistService;
    }

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'all');
        $search = trim((string) $request->input('search', ''));
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');
        $userId = $request->input('user_id', '');
        $productId = $request->input('product_id', '');

        $tabs = [
            'all' => 'All Wishlist Items',
            'by_peer' => 'Grouped by Peer',
            'top_products' => 'Popular Products',
        ];

        // Overall Stats
        $stats = $this->wishlistService->getAdminStats();
        $totalItems = $stats['total_wishlist_items'];
        $uniqueUsers = $stats['unique_users_count'];
        $uniqueProducts = $stats['unique_products_count'];

        // Top wishlisted product
        $topProductItem = Wishlist::select('product_id', DB::raw('count(*) as count'))
            ->groupBy('product_id')
            ->orderByDesc('count')
            ->with('product')
            ->first();

        $items = null;
        $groupedUsers = null;
        $topProductsList = null;

        if ($tab === 'by_peer') {
            $groupedUsers = $this->wishlistService->getAdminWishlistsGroupedByUser(['search' => $search], 15);
        } elseif ($tab === 'top_products') {
            $topProductsQuery = Product::query()
                ->has('wishlists')
                ->withCount('wishlists')
                ->with(['primaryImage', 'category', 'variants'])
                ->orderByDesc('wishlists_count');

            if ($search) {
                $topProductsQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            $topProductsList = $topProductsQuery->paginate(20);
        } else {
            $filters = [
                'search' => $search,
                'user_id' => $userId,
                'product_id' => $productId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ];
            $items = $this->wishlistService->getAdminWishlists($filters, 25);
        }

        return view('admin.store.wishlists.index', compact(
            'tab',
            'tabs',
            'search',
            'dateFrom',
            'dateTo',
            'userId',
            'productId',
            'totalItems',
            'uniqueUsers',
            'uniqueProducts',
            'topProductItem',
            'items',
            'groupedUsers',
            'topProductsList'
        ));
    }

    public function peerWishlist(string $userId)
    {
        $user = User::findOrFail($userId);
        $wishlist = $this->wishlistService->getUserWishlist($user);

        return view('admin.store.wishlists.peer_detail', compact('user', 'wishlist'));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $items = Wishlist::query()
            ->with(['user', 'product.category', 'variant'])
            ->latest('created_at')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="store_peer_wishlists_'.date('Y-m-d_His').'.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($items) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Wishlist ID',
                'Peer Name',
                'Peer Email',
                'Peer Phone',
                'Peer Company',
                'Peer Coins Balance',
                'Product SKU',
                'Product Name',
                'Product Category',
                'Variant Name',
                'Variant SKU',
                'Coin Price',
                'Date Added',
            ]);

            foreach ($items as $item) {
                $user = $item->user;
                $product = $item->product;
                $variant = $item->variant;

                $price = $variant ? ($variant->coin_price ?: $variant->price_coins) : ($product ? ($product->coin_price ?: $product->price_coins) : 0);

                fputcsv($handle, [
                    $item->id,
                    $user ? ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))) : 'N/A',
                    $user?->email ?? 'N/A',
                    $user?->phone ?? 'N/A',
                    $user?->company_name ?? 'N/A',
                    $user?->coins_balance ?? 0,
                    $product?->sku ?? 'N/A',
                    $product?->name ?? 'N/A',
                    $product?->category?->name ?? 'N/A',
                    $variant?->name ?? 'N/A',
                    $variant?->sku ?? 'N/A',
                    $price,
                    $item->created_at?->toDateTimeString() ?? 'N/A',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function destroy(string $id)
    {
        $item = Wishlist::findOrFail($id);
        $item->delete();

        return redirect()->back()->with('success', 'Wishlist item removed successfully.');
    }
}
