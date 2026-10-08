<?php

namespace App\Console\Commands;

use App\Models\Store\Product;
use App\Models\Store\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncProductPricesWithVariants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'store:sync-product-prices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize variant coin_prices, parent product coin_prices (MIN > 0), and refresh cart items';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting store pricing synchronization...');

        // -------------------------------------------------------------
        // Step 1: Ensure all product_variants have a valid coin_price > 0
        // -------------------------------------------------------------
        $this->line('Step 1: Fixing product_variants with 0, NULL or invalid coin_price...');
        $invalidVariants = ProductVariant::where(function ($q) {
            $q->whereNull('coin_price')
                ->orWhere('coin_price', '<=', 0)
                ->orWhereNull('price_coins')
                ->orWhere('price_coins', '<=', 0);
        })->get();

        $fixedVariantsCount = 0;
        foreach ($invalidVariants as $variant) {
            $product = Product::find($variant->product_id);
            if ($product) {
                $parentPrice = (int) ($product->coin_price ?: ($product->price_coins ?: 0));
                $vPrice = (int) ($variant->coin_price ?: ($variant->price_coins ?: 0));
                $priceToSet = $vPrice > 0 ? $vPrice : ($parentPrice > 0 ? $parentPrice : 100);

                DB::table('product_variants')
                    ->where('id', $variant->id)
                    ->update([
                        'coin_price' => $priceToSet,
                        'price_coins' => $priceToSet,
                        'updated_at' => now(),
                    ]);
                $fixedVariantsCount++;
            }
        }
        $this->info("✓ Checked & updated {$fixedVariantsCount} variants to valid coin_price > 0.");

        // -------------------------------------------------------------
        // Step 2: Ensure products.coin_price equals MIN(product_variants.coin_price > 0)
        // -------------------------------------------------------------
        $this->line('Step 2: Synchronizing parent products coin_price with MIN(active variants coin_price > 0)...');
        $products = Product::with(['variants'])->get();
        $updatedProductsCount = 0;

        foreach ($products as $product) {
            $activeVariants = $product->variants->filter(function ($v) {
                return (bool) ($v->is_active ?? ($v->status === 'ACTIVE'));
            });

            if ($activeVariants->isEmpty()) {
                $activeVariants = $product->variants;
            }

            if ($activeVariants->isNotEmpty()) {
                // MIN() only considering prices strictly greater than 0
                $minVariantPrice = $activeVariants->map(function ($v) {
                    return (int) ($v->coin_price ?: ($v->price_coins ?: 0));
                })->filter(fn ($p) => $p > 0)->min();

                if ($minVariantPrice !== null && $minVariantPrice > 0) {
                    $currentParentPrice = (int) ($product->price_coins ?: ($product->coin_price ?: 0));

                    if ($currentParentPrice !== $minVariantPrice) {
                        $this->line("  -> Updating Product [{$product->name}] (ID: {$product->id}): {$currentParentPrice} -> {$minVariantPrice} Coins");

                        DB::table('products')
                            ->where('id', $product->id)
                            ->update([
                                'price_coins' => $minVariantPrice,
                                'coin_price' => $minVariantPrice,
                                'updated_at' => now(),
                            ]);

                        $updatedProductsCount++;
                    }
                }
            }
        }
        $this->info("✓ Synchronized {$updatedProductsCount} parent products.");

        // -------------------------------------------------------------
        // Step 3: Refresh stale prices in cart_items table
        // -------------------------------------------------------------
        $this->line('Step 3: Refreshing stale prices in active cart items...');
        $cartItems = DB::table('cart_items')->get();
        $updatedCartItemsCount = 0;

        foreach ($cartItems as $item) {
            $currentLivePrice = null;

            if (! empty($item->variant_id)) {
                $variant = DB::table('product_variants')->where('id', $item->variant_id)->first();
                if ($variant) {
                    $vPrice = (int) ($variant->coin_price ?: ($variant->price_coins ?: 0));
                    if ($vPrice > 0) {
                        $currentLivePrice = $vPrice;
                    }
                }
            }

            if ($currentLivePrice === null && ! empty($item->product_id)) {
                $product = DB::table('products')->where('id', $item->product_id)->first();
                if ($product) {
                    $pPrice = (int) ($product->coin_price ?: ($product->price_coins ?: 0));
                    if ($pPrice > 0) {
                        $currentLivePrice = $pPrice;
                    }
                }
            }

            if ($currentLivePrice !== null && $currentLivePrice > 0 && (int) ($item->price_seen_coins ?? 0) !== $currentLivePrice) {
                DB::table('cart_items')
                    ->where('id', $item->id)
                    ->update([
                        'price_seen_coins' => $currentLivePrice,
                        'updated_at' => now(),
                    ]);
                $updatedCartItemsCount++;
            }
        }
        $this->info("✓ Refreshed {$updatedCartItemsCount} cart items with live pricing.");

        $this->info("All store pricing and cart synchronizations completed successfully!");

        return Command::SUCCESS;
    }
}
