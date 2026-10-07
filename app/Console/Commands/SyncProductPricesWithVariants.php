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
    protected $description = 'Synchronize parent products coin_price with the minimum variant coin_price';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting product prices synchronization...');

        $products = Product::with(['variants'])->get();
        $updatedCount = 0;

        foreach ($products as $product) {
            $activeVariants = $product->variants->filter(function ($v) {
                return (bool) ($v->is_active ?? ($v->status === 'ACTIVE'));
            });

            if ($activeVariants->isEmpty()) {
                // If no active variants, check any variants
                $activeVariants = $product->variants;
            }

            if ($activeVariants->isNotEmpty()) {
                $minVariantPrice = $activeVariants->map(function ($v) {
                    return (int) ($v->coin_price ?: ($v->price_coins ?: 0));
                })->filter(fn ($p) => $p > 0)->min();

                if ($minVariantPrice !== null && $minVariantPrice > 0) {
                    $currentParentPrice = (int) ($product->price_coins ?: ($product->coin_price ?: 0));

                    if ($currentParentPrice !== $minVariantPrice) {
                        $this->line("Updating Product [{$product->name}] (ID: {$product->id}): {$currentParentPrice} -> {$minVariantPrice} Coins");

                        DB::table('products')
                            ->where('id', $product->id)
                            ->update([
                                'price_coins' => $minVariantPrice,
                                'coin_price' => $minVariantPrice,
                                'updated_at' => now(),
                            ]);

                        $updatedCount++;
                    }
                }
            }
        }

        $this->info("Completed! Total {$updatedCount} products synchronized.");

        return Command::SUCCESS;
    }
}
