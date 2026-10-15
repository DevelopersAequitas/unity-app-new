<?php

namespace Tests\Feature\Store;

use App\Models\Store\Order;
use App\Models\Store\OrderItem;
use App\Models\Store\Product;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreConfig;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreOrderItemReturnTest extends TestCase
{
    protected User $user;
    protected StoreCategory $category;
    protected Product $customizedProduct;
    protected Product $returnableProduct;
    protected Order $order;
    protected OrderItem $customizedItem;
    protected OrderItem $returnableItem;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge();

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->string('membership_status')->nullable();
            $table->integer('coins_balance')->default(0);
            $table->string('coin_medal_rank')->nullable();
            $table->string('coin_milestone_title')->nullable();
            $table->string('coin_milestone_meaning')->nullable();
            $table->timestamps();
        });

        Schema::create('store_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug');
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('sku')->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->uuid('category_id')->nullable();
            $table->string('type')->default('PHYSICAL');
            $table->integer('price_coins')->default(0);
            $table->integer('coin_price')->default(0);
            $table->integer('compare_coin_price')->default(0);
            $table->string('status')->default('ACTIVE');
            $table->boolean('return_allowed')->default(true);
            $table->boolean('customised')->default(false);
            $table->timestamps();
        });

        Schema::create('product_images', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->text('image_url')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('order_no');
            $table->uuid('user_id');
            $table->string('status')->default('DELIVERED');
            $table->integer('total_coins')->default(0);
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->uuid('product_id')->nullable();
            $table->uuid('variant_id')->nullable();
            $table->json('product_snapshot')->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('unit_coin_price')->default(0);
            $table->integer('total_coin_price')->default(0);
            $table->string('status')->default('DELIVERED');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('returns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('return_no');
            $table->uuid('order_id');
            $table->uuid('order_item_id')->nullable();
            $table->uuid('user_id');
            $table->string('reason_code')->default('DAMAGED');
            $table->text('reason_detail')->nullable();
            $table->string('status')->default('REQUESTED');
            $table->integer('refund_coins')->default(0);
            $table->integer('refund_coins_amount')->default(0);
            $table->timestamps();
        });

        Schema::create('return_photos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('return_id');
            $table->text('file_url');
            $table->string('file_type')->default('IMAGE');
            $table->timestamps();
        });

        Schema::create('notification_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_key');
            $table->uuid('user_id')->nullable();
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('status')->default('PENDING');
            $table->timestamp('available_at')->nullable();
            $table->timestamps();
        });

        Schema::create('store_config', function (Blueprint $table): void {
            $table->string('config_key')->primary();
            $table->json('config_value')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('order_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->timestamps();
        });

        Schema::create('order_status_history', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->nullable();
            $table->uuid('return_id')->nullable();
            $table->timestamps();
        });

        Schema::create('receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Store Test User',
            'display_name' => 'Store Test User',
            'coins_balance' => 500000,
        ]);

        Sanctum::actingAs($this->user);

        $this->category = StoreCategory::create([
            'id' => (string) Str::uuid(),
            'name' => 'Store Test Category',
            'slug' => 'test-cat-' . Str::random(8),
            'status' => 'ACTIVE',
        ]);

        // Product 1: Customized product (customised = true)
        $this->customizedProduct = Product::create([
            'id' => (string) Str::uuid(),
            'sku' => 'CUSTOM-PROD-' . Str::random(6),
            'name' => 'Customized Engraved Mug',
            'slug' => 'custom-mug-' . Str::random(6),
            'category_id' => $this->category->id,
            'type' => 'PHYSICAL',
            'price_coins' => 1000,
            'status' => 'ACTIVE',
            'return_allowed' => true,
            'customised' => true,
        ]);

        // Product 2: Regular returnable product (customised = false)
        $this->returnableProduct = Product::create([
            'id' => (string) Str::uuid(),
            'sku' => 'REGULAR-PROD-' . Str::random(6),
            'name' => 'Standard T-Shirt',
            'slug' => 'standard-tshirt-' . Str::random(6),
            'category_id' => $this->category->id,
            'type' => 'PHYSICAL',
            'price_coins' => 1500,
            'status' => 'ACTIVE',
            'return_allowed' => true,
            'customised' => false,
        ]);

        // Order containing BOTH items
        $this->order = Order::create([
            'id' => (string) Str::uuid(),
            'order_no' => 'ORD-' . strtoupper(Str::random(8)),
            'user_id' => $this->user->id,
            'status' => 'DELIVERED',
            'total_coins' => 2500,
            'placed_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $this->customizedItem = OrderItem::create([
            'id' => (string) Str::uuid(),
            'order_id' => $this->order->id,
            'product_id' => $this->customizedProduct->id,
            'quantity' => 1,
            'unit_coin_price' => 1000,
            'total_coin_price' => 1000,
            'status' => 'DELIVERED',
        ]);

        $this->returnableItem = OrderItem::create([
            'id' => (string) Str::uuid(),
            'order_id' => $this->order->id,
            'product_id' => $this->returnableProduct->id,
            'quantity' => 1,
            'unit_coin_price' => 1500,
            'total_coin_price' => 1500,
            'status' => 'DELIVERED',
        ]);
    }

    public function test_can_return_non_customized_item_when_order_also_contains_customized_item(): void
    {
        $response = $this->postJson("/api/v1/orders/{$this->order->id}/return", [
            'order_item_id' => $this->returnableItem->id,
            'reason' => 'DEFECTIVE',
            'description' => 'Item arrived with a stitch defect.',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Return request submitted successfully',
        ]);

        $this->assertDatabaseHas('returns', [
            'order_id' => $this->order->id,
            'order_item_id' => $this->returnableItem->id,
            'user_id' => $this->user->id,
            'reason_code' => 'DEFECTIVE',
            'refund_coins' => 1500,
        ]);

        $this->assertEquals(1500, $response->json('data.refund_coins_amount'));

        // Item status is now RETURN_REQUESTED
        $this->returnableItem->refresh();
        $this->assertEquals('RETURN_REQUESTED', $this->returnableItem->status);

        // Multi-product order: Order status remains DELIVERED (not overwritten)
        $this->order->refresh();
        $this->assertEquals('DELIVERED', $this->order->status);

        // GET /api/v1/orders/{order_id} returns item-level status and return_request
        $getOrderResponse = $this->getJson("/api/v1/orders/{$this->order->id}");
        $getOrderResponse->assertStatus(200);
        $returnedItemData = collect($getOrderResponse->json('data.items'))->firstWhere('id', $this->returnableItem->id);
        $this->assertNotNull($returnedItemData);
        $this->assertEquals('RETURN_REQUESTED', $returnedItemData['status']);
        $this->assertNotNull($returnedItemData['return_request']);
        $this->assertEquals($this->returnableItem->id, $returnedItemData['return_request']['order_item_id']);
        $this->assertEquals(1500, $returnedItemData['return_request']['refund_coins_amount']);
    }

    public function test_rejects_return_when_returning_customized_order_item(): void
    {
        $response = $this->postJson("/api/v1/orders/{$this->order->id}/return", [
            'order_item_id' => $this->customizedItem->id,
            'reason' => 'DEFECTIVE',
            'description' => 'Trying to return customized mug.',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Customized product cannot be returned.',
        ]);
    }

    public function test_rejects_return_when_order_item_does_not_belong_to_order(): void
    {
        $randomItemId = (string) Str::uuid();

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/return", [
            'order_item_id' => $randomItemId,
            'reason' => 'DEFECTIVE',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Selected order item was not found in this order.',
        ]);
    }

    public function test_rejects_duplicate_return_for_same_order_item(): void
    {
        // First return succeeds
        $firstResponse = $this->postJson("/api/v1/orders/{$this->order->id}/return", [
            'order_item_id' => $this->returnableItem->id,
            'reason' => 'DEFECTIVE',
        ]);
        $firstResponse->assertStatus(201);

        // Second return for same item must be rejected
        $secondResponse = $this->postJson("/api/v1/orders/{$this->order->id}/return", [
            'order_item_id' => $this->returnableItem->id,
            'reason' => 'DEFECTIVE',
        ]);

        $secondResponse->assertStatus(422);
        $this->assertFalse($secondResponse->json('success'));
        $this->assertEquals('A return request has already been submitted for this item.', $secondResponse->json('message'));
    }
}
