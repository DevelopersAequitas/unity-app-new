<?php

namespace Tests\Feature;

use App\Models\CoinsLedger;
use App\Models\Store\Cart;
use App\Models\Store\CartItem;
use App\Models\Store\Product;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreConfig;
use App\Models\User;
use App\Services\Store\CartService;
use App\Services\Store\CheckoutQuoteService;
use App\Services\Store\OrderService;
use App\Services\Store\StoreConfigService;
use Exception;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorePurchaseLimitsAndOrderTotalTest extends TestCase
{
    protected User $user;
    protected StoreCategory $category;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'coins_balance' => 500000,
            'wallet_state' => 'ACTIVE',
        ]);

        $this->category = StoreCategory::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Category',
            'slug' => 'test-cat-' . Str::random(6),
            'status' => 'ACTIVE',
        ]);

        $this->productA = Product::create([
            'id' => (string) Str::uuid(),
            'sku' => 'TEST-PROD-A-' . Str::random(6),
            'name' => 'Product A',
            'slug' => 'prod-a-' . Str::random(6),
            'category_id' => $this->category->id,
            'type' => 'PHYSICAL',
            'price_coins' => 1000,
            'coin_price' => 1000,
            'status' => 'ACTIVE',
            'min_quantity_per_order' => 2,
            'max_quantity_per_order' => 5,
            'delivery_modes' => ['DELIVERY', 'PICKUP'],
            'track_inventory' => false,
        ]);

        $this->productB = Product::create([
            'id' => (string) Str::uuid(),
            'sku' => 'TEST-PROD-B-' . Str::random(6),
            'name' => 'Product B',
            'slug' => 'prod-b-' . Str::random(6),
            'category_id' => $this->category->id,
            'type' => 'PHYSICAL',
            'price_coins' => 2000,
            'coin_price' => 2000,
            'status' => 'ACTIVE',
            'min_quantity_per_order' => 1,
            'max_quantity_per_order' => 10,
            'delivery_modes' => ['DELIVERY', 'PICKUP'],
            'track_inventory' => false,
        ]);

        // Default store configs for testing
        StoreConfig::updateOrCreate(['config_key' => 'min_member_coins_to_buy'], ['config_value' => '0', 'updated_at' => now()]);
        StoreConfig::updateOrCreate(['config_key' => 'min_delivery_order_coins'], ['config_value' => '0', 'updated_at' => now()]);
        StoreConfig::updateOrCreate(['config_key' => 'min_order_total_coins'], ['config_value' => '0', 'updated_at' => now()]);
    }

    public function test_cart_enforces_product_minimum_purchase_quantity()
    {
        $cartService = app(CartService::class);

        // Product A requires at least 2 units. Adding 1 unit must throw 422.
        $this->expectException(Exception::class);
        $this->expectExceptionCode(422);
        $this->expectExceptionMessage("Minimum purchase quantity for Product A is 2.");

        $cartService->addItem($this->user, $this->productA->id, null, 1);
    }

    public function test_cart_allows_valid_quantity_and_prevents_exceeding_maximum()
    {
        $cartService = app(CartService::class);

        // Adding 2 units (valid)
        $result = $cartService->addItem($this->user, $this->productA->id, null, 2);
        $this->assertNotNull($result['cart_item']);
        $this->assertEquals(2, $result['cart_item']->quantity);

        // Adding 4 more units would make 6 units (exceeding max of 5)
        $this->expectException(Exception::class);
        $this->expectExceptionCode(422);
        $this->expectExceptionMessage("Maximum purchase quantity for Product A is 5.");

        $cartService->addItem($this->user, $this->productA->id, null, 4);
    }

    public function test_cart_item_update_enforces_min_and_max_quantity()
    {
        $cartService = app(CartService::class);
        $result = $cartService->addItem($this->user, $this->productA->id, null, 3);
        $cartItemId = $result['cart_item']->id;

        // Updating to 1 unit (below min of 2)
        try {
            $cartService->updateItem($this->user, $cartItemId, 1);
            $this->fail("Expected Exception for updating below min quantity not thrown");
        } catch (Exception $e) {
            $this->assertEquals(422, $e->getCode());
            $this->assertStringContainsString("Minimum purchase quantity for Product A is 2.", $e->getMessage());
        }

        // Updating to 6 units (above max of 5)
        try {
            $cartService->updateItem($this->user, $cartItemId, 6);
            $this->fail("Expected Exception for updating above max quantity not thrown");
        } catch (Exception $e) {
            $this->assertEquals(422, $e->getCode());
            $this->assertStringContainsString("Maximum purchase quantity for Product A is 5.", $e->getMessage());
        }

        // Updating to 4 units (valid)
        $updated = $cartService->updateItem($this->user, $cartItemId, 4);
        $this->assertEquals(4, $updated['cart_item']->quantity);
    }

    public function test_minimum_order_total_coins_blocks_checkout_quote_when_subtotal_is_insufficient()
    {
        // Set minimum order value in coins to 3,000
        StoreConfig::updateOrCreate(['config_key' => 'min_order_total_coins'], ['config_value' => '3000', 'updated_at' => now()]);

        $cartService = app(CartService::class);
        // Product A: 2 units * 1,000 coins = 2,000 coins (< 3,000 coins)
        $cartService->addItem($this->user, $this->productA->id, null, 2);

        // Cart validation should report min_order_met = false
        $cartValidation = $cartService->validateCart($this->user);
        $this->assertFalse($cartValidation['min_order_met']);
        $this->assertFalse($cartValidation['is_valid']);
        $this->assertEquals(3000, $cartValidation['min_order_total_coins']);

        // Checkout quote should throw 422
        $quoteService = app(CheckoutQuoteService::class);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(422);
        $this->expectExceptionMessage("Your order must contain products worth at least 3,000 coins. Please add more products to continue.");

        $quoteService->createQuote($this->user, ['delivery_mode' => 'PICKUP']);
    }

    public function test_minimum_order_total_coins_permits_checkout_when_threshold_met()
    {
        // Minimum order value: 3,000 coins
        StoreConfig::updateOrCreate(['config_key' => 'min_order_total_coins'], ['config_value' => '3000', 'updated_at' => now()]);

        $cartService = app(CartService::class);
        // Product A: 2 * 1,000 = 2,000 coins
        $cartService->addItem($this->user, $this->productA->id, null, 2);
        // Product B: 1 * 2,000 = 2,000 coins. Total = 4,000 coins (>= 3,000)
        $cartService->addItem($this->user, $this->productB->id, null, 1);

        $quoteService = app(CheckoutQuoteService::class);
        $quote = $quoteService->createQuote($this->user, ['delivery_mode' => 'PICKUP']);

        $this->assertNotNull($quote['quote_id']);
        $this->assertEquals(4000, $quote['subtotal_coins']);
        $this->assertTrue($quote['min_order_coins_met']);
        $this->assertTrue($quote['can_place_order']);
    }

    public function test_store_config_service_returns_new_thresholds()
    {
        StoreConfig::updateOrCreate(['config_key' => 'min_order_total_coins'], ['config_value' => '3000', 'updated_at' => now()]);
        StoreConfig::updateOrCreate(['config_key' => 'min_member_coins_to_buy'], ['config_value' => '100000', 'updated_at' => now()]);

        $configService = app(StoreConfigService::class);
        $config = $configService->getStoreConfig($this->user);

        $this->assertEquals(3000, $config['min_order_total_coins']);
        $this->assertEquals(100000, $config['min_member_coins_to_buy']);
    }
}
