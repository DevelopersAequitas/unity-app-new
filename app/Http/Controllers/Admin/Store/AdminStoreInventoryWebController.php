<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\Store\InventoryMovement;
use App\Models\Store\Product;
use App\Models\Store\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminStoreInventoryWebController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $hasLowStockCol = \Illuminate\Support\Facades\Schema::hasColumn('product_variants', 'low_stock_threshold');
        $lowStockQuery = ProductVariant::where('status', 'ACTIVE');
        if ($hasLowStockCol) {
            $lowStockQuery->whereRaw('stock_quantity <= COALESCE(low_stock_threshold, 5)');
        } else {
            $lowStockQuery->where('stock_quantity', '<=', 5);
        }
        $lowStockCount = $lowStockQuery->count();
        
        $query = Product::with(['category', 'variants'])->orderBy('created_at', 'desc');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('slug', 'ILIKE', "%{$search}%")
                  ->orWhereHas('variants', function($vq) use ($search) {
                      $vq->where('sku', 'ILIKE', "%{$search}%");
                  });
            });
        }

        $products = $query->paginate(20);
        return view('admin.store.inventory.index', compact('products', 'search', 'lowStockCount'));
    }

    public function lowStock(Request $request)
    {
        $threshold = (int) $request->input('threshold', 5);
        $thresholds = [3, 5, 10, 20, 50];

        $variants = ProductVariant::with(['product.category'])
            ->where('stock_quantity', '<=', $threshold)
            ->orderBy('stock_quantity', 'asc')
            ->paginate(20);

        return view('admin.store.inventory.low-stock', compact('variants', 'threshold', 'thresholds'));
    }

    public function movements(Request $request)
    {
        $search = $request->input('search', '');
        $refType = $request->input('reference_type', '');
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');
        $referenceTypes = ['PURCHASE', 'ORDER_RESERVATION', 'ORDER_RELEASE', 'ORDER_CANCEL', 'RETURN', 'DAMAGE', 'MANUAL_ADJUSTMENT', 'STOCK_RECEIVED', 'SYSTEM_CORRECTION'];

        $query = InventoryMovement::with(['variant.product'])->orderBy('created_at', 'desc');

        if ($search) {
            $query->whereHas('variant', function($vq) use ($search) {
                $vq->where('sku', 'ILIKE', "%{$search}%")->orWhereHas('product', function($pq) use ($search) {
                    $pq->where('name', 'ILIKE', "%{$search}%");
                });
            });
        }
        if ($refType) {
            $query->where('reason', $refType);
        }
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $movements = $query->paginate(25);
        return view('admin.store.inventory.movements', compact('movements', 'referenceTypes', 'refType', 'search', 'dateFrom', 'dateTo'));
    }

    public function adjustStock(Request $request)
    {
        $variantId = $request->input('variant_id');
        $variant = ProductVariant::with('product')->findOrFail($variantId);

        $adjustmentType = $request->input('adjustment_type', 'add');
        $qty = (int) $request->input('quantity', 0);
        $reason = $request->input('reason', 'Manual Adjustment');

        DB::transaction(function() use ($variant, $adjustmentType, $qty, $reason) {
            if ($adjustmentType === 'add') {
                $qtyChange = $qty;
                $newStock = $variant->stock_quantity + $qty;
            } elseif ($adjustmentType === 'subtract') {
                $qtyChange = -$qty;
                $newStock = max(0, $variant->stock_quantity - $qty);
            } else {
                $qtyChange = $qty - $variant->stock_quantity;
                $newStock = max(0, $qty);
            }

            $variant->update(['stock_quantity' => $newStock]);

            InventoryMovement::create([
                'product_variant_id' => $variant->id,
                'quantity_change' => $qtyChange,
                'balance_after' => $newStock,
                'reason' => 'MANUAL_ADJUSTMENT',
                'reference' => 'MANUAL_AUDIT_' . uniqid(),
                'notes' => $reason,
                'actor_id' => Auth::guard('admin')->id() ?? '00000000-0000-0000-0000-000000000000',
                'actor_type' => 'AdminUser'
            ]);
        });

        return back()->with('success', "Stock updated for SKU: {$variant->sku}. Current stock: {$variant->stock_quantity}.");
    }

    public function exportLowStockCsv(Request $request): StreamedResponse
    {
        $threshold = (int) $request->input('threshold', 5);
        $variants = ProductVariant::with(['product.category'])
            ->where('stock_quantity', '<=', $threshold)
            ->orderBy('stock_quantity', 'asc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="low_stock_report_' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function() use ($variants) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Product Name', 'Category', 'Variant Name', 'SKU', 'Available Stock', 'Threshold', 'Status', 'Exported At']);

            foreach ($variants as $v) {
                fputcsv($handle, [
                    $v->product->name ?? 'N/A',
                    $v->product->category->name ?? 'N/A',
                    $v->name,
                    $v->sku,
                    $v->stock_quantity,
                    $v->low_stock_threshold,
                    $v->stock_quantity <= 0 ? 'OUT_OF_STOCK' : 'LOW_STOCK',
                    now()->toIso8601String()
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }
}
