<?php

function getFullDetailedResponse($num, $method, $path, $title) {
    // Generate full-fidelity, multi-field JSON schema matching exact Postman results
    switch ($num) {
        case 1: // Store Configuration
            return json_encode([
                "success" => true,
                "message" => "Store configuration retrieved successfully",
                "data" => [
                    "store_enabled" => true,
                    "maintenance_mode" => false,
                    "maintenance_message" => null,
                    "minimum_app_version" => "1.0.0",
                    "latest_app_version" => "1.4.2",
                    "current_app_version_supported" => true,
                    "force_update_required" => false,
                    "coin_balance" => 125000,
                    "earned_coins" => 100000,
                    "bonus_coins" => 25000,
                    "locked_coins" => 0,
                    "spendable_coins" => 125000,
                    "delivery_minimum_coins" => 100000,
                    "free_delivery_coins_threshold" => 250000,
                    "standard_delivery_charge_coins" => 5000,
                    "pickup_available" => true,
                    "pickup_hold_days" => 7,
                    "return_window_days" => 7,
                    "max_quantity_per_peer_month" => 5,
                    "otp_required_coin_threshold" => 50000,
                    "currency_name" => "Unity Coin",
                    "currency_symbol" => "UC",
                    "support_email" => "support@peersglobalunity.com",
                    "support_phone" => "+91 98250 12345",
                    "banners" => [
                        [
                            "id" => "d0730dc3-3a80-4265-aeac-1dbe5eb3ac47",
                            "title" => "Exclusive Unity Merchandise",
                            "subtitle" => "Wear your peer identity with pride",
                            "image_url" => "https://images.unsplash.com/photo-1523381210434-271e8be1f52b?w=800",
                            "link_type" => "CATEGORY",
                            "link_value" => "apparel-clothing",
                            "display_order" => 1,
                            "is_active" => true,
                            "created_at" => "2026-09-01T10:00:00.000000Z",
                            "updated_at" => "2026-09-15T12:30:00.000000Z"
                        ],
                        [
                            "id" => "e1841ed4-4b91-5376-bfbd-2ecf6fc4bd58",
                            "title" => "Annual Business Leadership Pass",
                            "subtitle" => "Unlock zero shipping coins on all physical catalog orders",
                            "image_url" => "https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800",
                            "link_type" => "MEMBERSHIP",
                            "link_value" => "gold-pass",
                            "display_order" => 2,
                            "is_active" => true,
                            "created_at" => "2026-09-05T08:15:00.000000Z",
                            "updated_at" => "2026-09-20T11:00:00.000000Z"
                        ]
                    ]
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        case 2: // Store Banners
            return json_encode([
                "success" => true,
                "message" => "Active banners retrieved successfully",
                "data" => [
                    "current_page" => 1,
                    "data" => [
                        [
                            "id" => "d0730dc3-3a80-4265-aeac-1dbe5eb3ac47",
                            "title" => "Exclusive Unity Merchandise",
                            "subtitle" => "Wear your peer identity with pride",
                            "image_url" => "https://images.unsplash.com/photo-1523381210434-271e8be1f52b?w=800",
                            "link_type" => "CATEGORY",
                            "link_value" => "apparel-clothing",
                            "display_order" => 1,
                            "is_active" => true,
                            "starts_at" => "2026-09-01T00:00:00.000000Z",
                            "ends_at" => "2026-12-31T23:59:59.000000Z",
                            "created_at" => "2026-09-01T10:00:00.000000Z",
                            "updated_at" => "2026-09-15T12:30:00.000000Z"
                        ],
                        [
                            "id" => "e1841ed4-4b91-5376-bfbd-2ecf6fc4bd58",
                            "title" => "New Fall Collection 2026",
                            "subtitle" => "Polos, Blazers & Executive Bags",
                            "image_url" => "https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=800",
                            "link_type" => "PRODUCT",
                            "link_value" => "a05ceb9e-2747-4dba-841d-63da4d725c33",
                            "display_order" => 2,
                            "is_active" => true,
                            "starts_at" => "2026-09-10T00:00:00.000000Z",
                            "ends_at" => "2026-11-30T23:59:59.000000Z",
                            "created_at" => "2026-09-10T09:00:00.000000Z",
                            "updated_at" => "2026-09-12T14:20:00.000000Z"
                        ]
                    ],
                    "first_page_url" => "http://localhost:8000/api/v1/store/banners?page=1",
                    "from" => 1,
                    "last_page" => 1,
                    "last_page_url" => "http://localhost:8000/api/v1/store/banners?page=1",
                    "links" => [
                        ["url" => null, "label" => "&laquo; Previous", "active" => false],
                        ["url" => "http://localhost:8000/api/v1/store/banners?page=1", "label" => "1", "active" => true],
                        ["url" => null, "label" => "Next &raquo;", "active" => false]
                    ],
                    "next_page_url" => null,
                    "path" => "http://localhost:8000/api/v1/store/banners",
                    "per_page" => 10,
                    "prev_page_url" => null,
                    "to" => 2,
                    "total" => 2
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        case 3: // User Wallet Balance & Split
            return json_encode([
                "success" => true,
                "message" => "Wallet details retrieved successfully",
                "data" => [
                    "user_id" => "9a2f7c41-831e-4c02-990a-112233445566",
                    "balance" => 125000,
                    "earned_balance" => 100000,
                    "bonus_balance" => 25000,
                    "locked_balance" => 0,
                    "lifetime_earned" => 350000,
                    "lifetime_spent" => 225000,
                    "lifetime_purchased" => 0,
                    "lifetime_bonus_granted" => 50000,
                    "wallet_state" => "ACTIVE",
                    "wallet_version" => 18,
                    "is_frozen" => false,
                    "frozen_at" => null,
                    "freeze_reason" => null,
                    "currency_unit" => "COINS",
                    "last_transaction_at" => "2026-09-30T14:45:22.000000Z",
                    "created_at" => "2026-01-10T08:00:00.000000Z",
                    "updated_at" => "2026-09-30T14:45:22.000000Z"
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        case 4: // Wallet Ledger History
            return json_encode([
                "success" => true,
                "message" => "Wallet ledger history retrieved successfully",
                "data" => [
                    "current_page" => 1,
                    "data" => [
                        [
                            "id" => "f0123456-789a-bcde-f012-3456789abcde",
                            "transaction_id" => "TXN-20260930-89124",
                            "user_id" => "9a2f7c41-831e-4c02-990a-112233445566",
                            "bucket" => "BONUS",
                            "direction" => "DEBIT",
                            "amount" => -25000,
                            "balance_before" => 150000,
                            "balance_after" => 125000,
                            "reference_type" => "ORDER",
                            "reference_id" => "21098765-4321-0fed-cba9-876543210fed",
                            "reference" => "Order #ORD-982147",
                            "narration" => "Redeemed 25,000 Bonus Coins towards Order #ORD-982147",
                            "created_by" => null,
                            "created_at" => "2026-09-30T14:45:22.000000Z",
                            "updated_at" => "2026-09-30T14:45:22.000000Z"
                        ],
                        [
                            "id" => "e9876543-210f-edcb-a987-6543210fedcb",
                            "transaction_id" => "TXN-20260925-54120",
                            "user_id" => "9a2f7c41-831e-4c02-990a-112233445566",
                            "bucket" => "EARNED",
                            "direction" => "CREDIT",
                            "amount" => 50000,
                            "balance_before" => 100000,
                            "balance_after" => 150000,
                            "reference_type" => "BUSINESS_MILESTONE",
                            "reference_id" => "b5a69870-1234-5678-9abc-def012345678",
                            "reference" => "Meeting Referral Milestone Tier 2",
                            "narration" => "Awarded for completing 10 verified business 1-to-1 meetings",
                            "created_by" => "system_engine",
                            "created_at" => "2026-09-25T11:20:10.000000Z",
                            "updated_at" => "2026-09-25T11:20:10.000000Z"
                        ]
                    ],
                    "first_page_url" => "http://localhost:8000/api/v1/wallet/ledger?page=1",
                    "from" => 1,
                    "last_page" => 1,
                    "last_page_url" => "http://localhost:8000/api/v1/wallet/ledger?page=1",
                    "links" => [
                        ["url" => null, "label" => "&laquo; Previous", "active" => false],
                        ["url" => "http://localhost:8000/api/v1/wallet/ledger?page=1", "label" => "1", "active" => true],
                        ["url" => null, "label" => "Next &raquo;", "active" => false]
                    ],
                    "next_page_url" => null,
                    "path" => "http://localhost:8000/api/v1/wallet/ledger",
                    "per_page" => 20,
                    "prev_page_url" => null,
                    "to" => 2,
                    "total" => 2
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        case 5: // Wallet Monthly Summary
            return json_encode([
                "success" => true,
                "message" => "Wallet monthly summary retrieved successfully",
                "data" => [
                    "fiscal_month" => "2026-09",
                    "period_start" => "2026-09-01T00:00:00.000000Z",
                    "period_end" => "2026-09-30T23:59:59.000000Z",
                    "opening_balance" => 90000,
                    "current_balance" => 125000,
                    "earned_balance" => 100000,
                    "bonus_balance" => 25000,
                    "total_earned_this_month" => 60000,
                    "earned_from_meetings" => 35000,
                    "earned_from_referrals" => 25000,
                    "bonus_granted_this_month" => 15000,
                    "total_spent_this_month" => 40000,
                    "spent_on_physical_orders" => 40000,
                    "spent_on_memberships" => 0,
                    "net_balance_growth" => 35000,
                    "growth_percentage" => 38.89,
                    "total_orders_placed" => 2,
                    "transactions_count" => 5
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        case 6: // Wallet Status & Freeze Info
            return json_encode([
                "success" => true,
                "message" => "Wallet security status retrieved",
                "data" => [
                    "user_id" => "9a2f7c41-831e-4c02-990a-112233445566",
                    "wallet_state" => "ACTIVE",
                    "is_frozen" => false,
                    "frozen_at" => null,
                    "freeze_reason" => null,
                    "freeze_initiated_by" => null,
                    "can_transact" => true,
                    "can_redeem_store" => true,
                    "can_transfer" => true,
                    "daily_spend_limit_coins" => 500000,
                    "daily_spent_today_coins" => 25000,
                    "remaining_daily_limit_coins" => 475000,
                    "last_compliance_check_at" => "2026-09-28T09:00:00.000000Z"
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        case 7: // Store Categories
            return json_encode([
                "success" => true,
                "message" => "Active categories retrieved successfully",
                "data" => [
                    [
                        "id" => "b5a69870-1234-5678-9abc-def012345678",
                        "name" => "Apparel & Merchandise",
                        "slug" => "apparel-clothing",
                        "description" => "Official Unity branded polo t-shirts, blazers, and caps",
                        "icon_url" => "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=100",
                        "image_url" => "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=500",
                        "display_order" => 1,
                        "is_active" => true,
                        "products_count" => 12,
                        "created_at" => "2026-08-15T10:00:00.000000Z",
                        "updated_at" => "2026-09-20T14:30:00.000000Z"
                    ],
                    [
                        "id" => "c6b7a981-2345-6789-0bcd-ef0123456789",
                        "name" => "Executive Office & Stationery",
                        "slug" => "office-stationery",
                        "description" => "Engraved pens, metallic card holders, and leather diaries",
                        "icon_url" => "https://images.unsplash.com/photo-1583485088034-697b5bc54ccd?w=100",
                        "image_url" => "https://images.unsplash.com/photo-1583485088034-697b5bc54ccd?w=500",
                        "display_order" => 2,
                        "is_active" => true,
                        "products_count" => 8,
                        "created_at" => "2026-08-15T10:05:00.000000Z",
                        "updated_at" => "2026-09-18T11:20:00.000000Z"
                    ],
                    [
                        "id" => "d7c8ba92-3456-7890-1cde-f01234567890",
                        "name" => "Digital Masterclasses & eBooks",
                        "slug" => "digital-masterclasses",
                        "description" => "High-impact business growth courses and training guides",
                        "icon_url" => "https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=100",
                        "image_url" => "https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=500",
                        "display_order" => 3,
                        "is_active" => true,
                        "products_count" => 6,
                        "created_at" => "2026-08-20T09:00:00.000000Z",
                        "updated_at" => "2026-09-22T16:45:00.000000Z"
                    ]
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        case 8: // Product Listing & Filtering
            return json_encode([
                "success" => true,
                "message" => "Products retrieved successfully",
                "data" => [
                    "current_page" => 1,
                    "data" => [
                        [
                            "id" => "a05ceb9e-2747-4dba-841d-63da4d725c33",
                            "name" => "Peers Official Polo T-Shirt",
                            "slug" => "peers-official-polo-t-shirt",
                            "sku" => "POLO-001",
                            "type" => "PHYSICAL",
                            "category_id" => "b5a69870-1234-5678-9abc-def012345678",
                            "category" => [
                                "id" => "b5a69870-1234-5678-9abc-def012345678",
                                "name" => "Apparel & Merchandise",
                                "slug" => "apparel-clothing"
                            ],
                            "coin_price" => 45000,
                            "mrp" => 1499,
                            "short_description" => "100% Breathable Pique Cotton with embroidered golden unity crest",
                            "is_featured" => true,
                            "is_active" => true,
                            "stock_qty" => 120,
                            "delivery_modes" => ["DELIVERY", "PICKUP"],
                            "return_allowed" => true,
                            "variants_count" => 4,
                            "primary_image" => [
                                "id" => "img_01",
                                "image_url" => "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=600",
                                "is_primary" => true
                            ],
                            "created_at" => "2026-09-01T10:00:00.000000Z",
                            "updated_at" => "2026-09-28T15:20:00.000000Z"
                        ]
                    ],
                    "first_page_url" => "http://localhost:8000/api/v1/store/products?page=1",
                    "from" => 1,
                    "last_page" => 1,
                    "last_page_url" => "http://localhost:8000/api/v1/store/products?page=1",
                    "links" => [
                        ["url" => null, "label" => "&laquo; Previous", "active" => false],
                        ["url" => "http://localhost:8000/api/v1/store/products?page=1", "label" => "1", "active" => true],
                        ["url" => null, "label" => "Next &raquo;", "active" => false]
                    ],
                    "next_page_url" => null,
                    "path" => "http://localhost:8000/api/v1/store/products",
                    "per_page" => 20,
                    "prev_page_url" => null,
                    "to" => 1,
                    "total" => 1
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        case 9: // Product Details
            return json_encode([
                "success" => true,
                "message" => "Product details retrieved successfully",
                "data" => [
                    "id" => "a05ceb9e-2747-4dba-841d-63da4d725c33",
                    "name" => "Peers Official Polo T-Shirt",
                    "slug" => "peers-official-polo-t-shirt",
                    "sku" => "POLO-001",
                    "type" => "PHYSICAL",
                    "coin_price" => 45000,
                    "mrp" => 1499,
                    "short_description" => "100% Breathable Pique Cotton with embroidered golden unity crest",
                    "description" => "<p>The signature Peers Global Unity Executive Polo is crafted from 240 GSM organic combed cotton with pre-shrunk wash. Features ribbed collar, two-button placket, and high-definition crest embroidery on the chest.</p>",
                    "category_id" => "b5a69870-1234-5678-9abc-def012345678",
                    "category" => [
                        "id" => "b5a69870-1234-5678-9abc-def012345678",
                        "name" => "Apparel & Merchandise",
                        "slug" => "apparel-clothing"
                    ],
                    "delivery_modes" => ["DELIVERY", "PICKUP"],
                    "return_allowed" => true,
                    "return_window_days" => 7,
                    "customised" => false,
                    "is_featured" => true,
                    "is_active" => true,
                    "stock_qty" => 120,
                    "images" => [
                        [
                            "id" => "img_01",
                            "image_url" => "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=800",
                            "is_primary" => true,
                            "display_order" => 1
                        ],
                        [
                            "id" => "img_02",
                            "image_url" => "https://images.unsplash.com/photo-1581655353564-df123a1eb820?w=800",
                            "is_primary" => false,
                            "display_order" => 2
                        ]
                    ],
                    "variants" => [
                        [
                            "id" => "935d6a66-837e-40c0-9971-aadda2db131e",
                            "product_id" => "a05ceb9e-2747-4dba-841d-63da4d725c33",
                            "name" => "Navy Blue - Medium (M)",
                            "sku" => "POLO-001-BLU-M",
                            "coin_price" => 45000,
                            "mrp" => 1499,
                            "stock_qty" => 40,
                            "reserved_qty" => 2,
                            "is_active" => true,
                            "attributes" => [
                                "size" => "M",
                                "color" => "Navy Blue",
                                "chest_inch" => 40
                            ]
                        ],
                        [
                            "id" => "a46e7b77-948f-51d1-0082-bbecb3ec242f",
                            "product_id" => "a05ceb9e-2747-4dba-841d-63da4d725c33",
                            "name" => "Navy Blue - Large (L)",
                            "sku" => "POLO-001-BLU-L",
                            "coin_price" => 45000,
                            "mrp" => 1499,
                            "stock_qty" => 50,
                            "reserved_qty" => 1,
                            "is_active" => true,
                            "attributes" => [
                                "size" => "L",
                                "color" => "Navy Blue",
                                "chest_inch" => 42
                            ]
                        ],
                        [
                            "id" => "b57f8c88-0590-62e2-1193-ccfdc4fd3530",
                            "product_id" => "a05ceb9e-2747-4dba-841d-63da4d725c33",
                            "name" => "Navy Blue - Extra Large (XL)",
                            "sku" => "POLO-001-BLU-XL",
                            "coin_price" => 45000,
                            "mrp" => 1499,
                            "stock_qty" => 30,
                            "reserved_qty" => 0,
                            "is_active" => true,
                            "attributes" => [
                                "size" => "XL",
                                "color" => "Navy Blue",
                                "chest_inch" => 44
                            ]
                        ]
                    ],
                    "reviews_summary" => [
                        "average_rating" => 4.85,
                        "total_reviews" => 24,
                        "rating_distribution" => [
                            "5_star" => 21,
                            "4_star" => 2,
                            "3_star" => 1,
                            "2_star" => 0,
                            "1_star" => 0
                        ]
                    ],
                    "created_at" => "2026-09-01T10:00:00.000000Z",
                    "updated_at" => "2026-09-28T15:20:00.000000Z"
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        case 12: // Get User Cart
            return json_encode([
                "success" => true,
                "message" => "Shopping cart retrieved successfully",
                "data" => [
                    "id" => "cart_78901234-5678-9abc-def0-123456789abc",
                    "user_id" => "9a2f7c41-831e-4c02-990a-112233445566",
                    "items_count" => 2,
                    "total_quantity" => 2,
                    "subtotal_coins" => 90000,
                    "estimated_delivery_coins" => 5000,
                    "discount_coins" => 0,
                    "total_coins" => 95000,
                    "user_coin_balance" => 125000,
                    "has_sufficient_balance" => true,
                    "has_out_of_stock_items" => false,
                    "items" => [
                        [
                            "id" => "item_11223344-5566-7788-99aa-bbccddeeff00",
                            "cart_id" => "cart_78901234-5678-9abc-def0-123456789abc",
                            "product_id" => "a05ceb9e-2747-4dba-841d-63da4d725c33",
                            "product_variant_id" => "935d6a66-837e-40c0-9971-aadda2db131e",
                            "quantity" => 1,
                            "unit_coin_price" => 45000,
                            "total_item_coins" => 45000,
                            "product_name" => "Peers Official Polo T-Shirt",
                            "variant_name" => "Navy Blue - Medium (M)",
                            "sku" => "POLO-001-BLU-M",
                            "image_url" => "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=300",
                            "in_stock" => true,
                            "available_stock" => 40,
                            "max_allowed_quantity" => 5
                        ],
                        [
                            "id" => "item_22334455-6677-8899-aabb-ccddeeff0011",
                            "cart_id" => "cart_78901234-5678-9abc-def0-123456789abc",
                            "product_id" => "a05ceb9e-2747-4dba-841d-63da4d725c33",
                            "product_variant_id" => "a46e7b77-948f-51d1-0082-bbecb3ec242f",
                            "quantity" => 1,
                            "unit_coin_price" => 45000,
                            "total_item_coins" => 45000,
                            "product_name" => "Peers Official Polo T-Shirt",
                            "variant_name" => "Navy Blue - Large (L)",
                            "sku" => "POLO-001-BLU-L",
                            "image_url" => "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=300",
                            "in_stock" => true,
                            "available_stock" => 50,
                            "max_allowed_quantity" => 5
                        ]
                    ],
                    "created_at" => "2026-09-30T10:15:00.000000Z",
                    "updated_at" => "2026-09-30T10:20:00.000000Z"
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        case 17: // List Saved Addresses
            return json_encode([
                "success" => true,
                "message" => "Saved addresses retrieved successfully",
                "data" => [
                    [
                        "id" => "01a0ecd1-1d7f-70ab-885a-020f273de747",
                        "user_id" => "9a2f7c41-831e-4c02-990a-112233445566",
                        "full_name" => "Rajesh M. Patel",
                        "phone" => "9825012345",
                        "alternate_phone" => "9825098765",
                        "address_line1" => "402, Titanium City Centre, Anandnagar Road",
                        "address_line2" => "Near Sachin Tower, Prahladnagar",
                        "landmark" => "Opp. Seema Hall",
                        "city" => "Ahmedabad",
                        "state" => "Gujarat",
                        "pincode" => "380015",
                        "address_type" => "OFFICE",
                        "is_default" => true,
                        "delivery_instructions" => "Deliver during office hours between 10 AM to 6 PM",
                        "created_at" => "2026-08-10T11:00:00.000000Z",
                        "updated_at" => "2026-09-20T16:00:00.000000Z"
                    ],
                    [
                        "id" => "02b1fde2-2e80-81bc-996b-1310384ef858",
                        "user_id" => "9a2f7c41-831e-4c02-990a-112233445566",
                        "full_name" => "Rajesh Patel (Residence)",
                        "phone" => "9825012345",
                        "alternate_phone" => null,
                        "address_line1" => "B-12, Suryodaya Bungalows, Bodakdev",
                        "address_line2" => "Judges Bungalow Road",
                        "landmark" => "Behind Pakwan Dining Hall",
                        "city" => "Ahmedabad",
                        "state" => "Gujarat",
                        "pincode" => "380054",
                        "address_type" => "HOME",
                        "is_default" => false,
                        "delivery_instructions" => "Leave with security if not available",
                        "created_at" => "2026-08-25T14:30:00.000000Z",
                        "updated_at" => "2026-08-25T14:30:00.000000Z"
                    ]
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        case 25: // Generate Checkout Quote
            return json_encode([
                "success" => true,
                "message" => "Checkout valuation quote generated successfully",
                "data" => [
                    "quote_id" => "quo_98765432-10fe-dcba-9876-543210fedcba",
                    "quote_token" => "qtok_live_77a88b99cc0011223344",
                    "user_id" => "9a2f7c41-831e-4c02-990a-112233445566",
                    "delivery_type" => "DELIVERY",
                    "shipping_address_id" => "01a0ecd1-1d7f-70ab-885a-020f273de747",
                    "pickup_point_id" => null,
                    "items_count" => 2,
                    "total_quantity" => 2,
                    "subtotal_coins" => 90000,
                    "delivery_charge_coins" => 5000,
                    "discount_coins" => 0,
                    "total_coins_payable" => 95000,
                    "user_available_coins" => 125000,
                    "coin_split" => [
                        "earned_coins_to_debit" => 70000,
                        "bonus_coins_to_debit" => 25000,
                        "remaining_balance_after_order" => 30000
                    ],
                    "coin_shortfall" => 0,
                    "can_proceed" => true,
                    "otp_required" => false,
                    "otp_challenge_id" => null,
                    "expires_at" => "2026-10-01T10:45:00.000000Z",
                    "validity_seconds_remaining" => 900,
                    "items" => [
                        [
                            "product_variant_id" => "935d6a66-837e-40c0-9971-aadda2db131e",
                            "product_name" => "Peers Official Polo T-Shirt",
                            "variant_name" => "Navy Blue - Medium (M)",
                            "sku" => "POLO-001-BLU-M",
                            "quantity" => 1,
                            "unit_coin_price" => 45000,
                            "total_coins" => 45000
                        ],
                        [
                            "product_variant_id" => "a46e7b77-948f-51d1-0082-bbecb3ec242f",
                            "product_name" => "Peers Official Polo T-Shirt",
                            "variant_name" => "Navy Blue - Large (L)",
                            "sku" => "POLO-001-BLU-L",
                            "quantity" => 1,
                            "unit_coin_price" => 45000,
                            "total_coins" => 45000
                        ]
                    ],
                    "created_at" => "2026-10-01T10:30:00.000000Z"
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        case 28: // Place Order (Atomic Transaction)
        case 29: // List User Orders or show single order
            return json_encode([
                "success" => true,
                "message" => "Order placed successfully",
                "data" => [
                    "id" => "21098765-4321-0fed-cba9-876543210fed",
                    "order_number" => "ORD-20261001-982147",
                    "user_id" => "9a2f7c41-831e-4c02-990a-112233445566",
                    "status" => "PLACED",
                    "payment_status" => "PAID_WITH_COINS",
                    "delivery_type" => "DELIVERY",
                    "subtotal_coins" => 90000,
                    "delivery_charge_coins" => 5000,
                    "discount_coins" => 0,
                    "total_coins_paid" => 95000,
                    "earned_coins_debited" => 70000,
                    "bonus_coins_debited" => 25000,
                    "coin_ledger_transaction_id" => "TXN-20261001-982147",
                    "shipping_address" => [
                        "id" => "01a0ecd1-1d7f-70ab-885a-020f273de747",
                        "full_name" => "Rajesh M. Patel",
                        "phone" => "9825012345",
                        "address_line1" => "402, Titanium City Centre, Anandnagar Road",
                        "address_line2" => "Near Sachin Tower, Prahladnagar",
                        "city" => "Ahmedabad",
                        "state" => "Gujarat",
                        "pincode" => "380015"
                    ],
                    "pickup_point" => null,
                    "items" => [
                        [
                            "id" => "oi_11223344-5566-7788-99aa-bbccddeeff00",
                            "order_id" => "21098765-4321-0fed-cba9-876543210fed",
                            "product_id" => "a05ceb9e-2747-4dba-841d-63da4d725c33",
                            "product_variant_id" => "935d6a66-837e-40c0-9971-aadda2db131e",
                            "product_name" => "Peers Official Polo T-Shirt",
                            "variant_name" => "Navy Blue - Medium (M)",
                            "sku" => "POLO-001-BLU-M",
                            "coin_price" => 45000,
                            "quantity" => 1,
                            "total_coins" => 45000,
                            "status" => "CONFIRMED"
                        ],
                        [
                            "id" => "oi_22334455-6677-8899-aabb-ccddeeff0011",
                            "order_id" => "21098765-4321-0fed-cba9-876543210fed",
                            "product_id" => "a05ceb9e-2747-4dba-841d-63da4d725c33",
                            "product_variant_id" => "a46e7b77-948f-51d1-0082-bbecb3ec242f",
                            "product_name" => "Peers Official Polo T-Shirt",
                            "variant_name" => "Navy Blue - Large (L)",
                            "sku" => "POLO-001-BLU-L",
                            "coin_price" => 45000,
                            "quantity" => 1,
                            "total_coins" => 45000,
                            "status" => "CONFIRMED"
                        ]
                    ],
                    "action_flags" => [
                        "can_cancel" => true,
                        "can_return" => false,
                        "can_track" => false,
                        "can_download_receipt" => true,
                        "can_pickup" => false
                    ],
                    "status_history" => [
                        [
                            "id" => "sh_01",
                            "from_status" => "DRAFT",
                            "to_status" => "PLACED",
                            "remarks" => "Order successfully placed and coin wallet debited.",
                            "created_at" => "2026-10-01T10:35:00.000000Z"
                        ]
                    ],
                    "invoice_url" => "http://localhost:8000/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/receipt",
                    "created_at" => "2026-10-01T10:35:00.000000Z",
                    "updated_at" => "2026-10-01T10:35:00.000000Z"
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        case 34: // Track Courier Shipment
            return json_encode([
                "success" => true,
                "message" => "Shipment tracking details retrieved",
                "data" => [
                    "shipment_id" => "shp_34567890-1234-5678-90ab-cdef12345678",
                    "order_id" => "21098765-4321-0fed-cba9-876543210fed",
                    "order_number" => "ORD-20261001-982147",
                    "carrier_name" => "Delhivery Express",
                    "carrier_code" => "DELHIVERY",
                    "awb_number" => "DEL1234567890IN",
                    "status" => "IN_TRANSIT",
                    "shipped_at" => "2026-10-02T11:00:00.000000Z",
                    "estimated_delivery_at" => "2026-10-05T18:00:00.000000Z",
                    "delivered_at" => null,
                    "tracking_url" => "https://www.delhivery.com/track/package/DEL1234567890IN",
                    "origin_hub" => "Peers Central Warehouse, Changodar, Ahmedabad",
                    "destination_pincode" => "380015",
                    "checkpoints" => [
                        [
                            "timestamp" => "2026-10-02T11:00:00.000000Z",
                            "location" => "Ahmedabad Central Warehouse",
                            "status" => "SHIPPED",
                            "remarks" => "Manifested and handed over to Delhivery logistics"
                        ],
                        [
                            "timestamp" => "2026-10-02T16:30:00.000000Z",
                            "location" => "Ahmedabad Sorting Hub (Bavla)",
                            "status" => "IN_TRANSIT",
                            "remarks" => "Package processed at central sort facility"
                        ],
                        [
                            "timestamp" => "2026-10-03T09:15:00.000000Z",
                            "location" => "Prahladnagar Delivery Branch",
                            "status" => "ARRIVED_AT_DESTINATION_HUB",
                            "remarks" => "Received at local delivery station"
                        ]
                    ]
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        case 37: // Submit Return Request
        case 38: // Get Return Request Details
            return json_encode([
                "success" => true,
                "message" => "Return request processed successfully",
                "data" => [
                    "id" => "ret_56789012-3456-7890-abcd-ef1234567890",
                    "return_number" => "RET-20261005-4412",
                    "order_id" => "21098765-4321-0fed-cba9-876543210fed",
                    "order_item_id" => "oi_11223344-5566-7788-99aa-bbccddeeff00",
                    "user_id" => "9a2f7c41-831e-4c02-990a-112233445566",
                    "product_name" => "Peers Official Polo T-Shirt",
                    "variant_name" => "Navy Blue - Medium (M)",
                    "reason_code" => "SIZE_MISFIT",
                    "reason_description" => "Item received is tighter than expected sizing measurements. Requesting coin refund.",
                    "proof_images" => [
                        "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=800"
                    ],
                    "status" => "PENDING_INSPECTION",
                    "refund_coins_amount" => 45000,
                    "refund_ledger_id" => null,
                    "pickup_address" => [
                        "full_name" => "Rajesh M. Patel",
                        "phone" => "9825012345",
                        "address_line1" => "402, Titanium City Centre",
                        "city" => "Ahmedabad",
                        "pincode" => "380015"
                    ],
                    "inspection_remarks" => null,
                    "created_at" => "2026-10-05T14:20:00.000000Z",
                    "updated_at" => "2026-10-05T14:20:00.000000Z"
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        case 65: // Admin Store Dashboard Metrics
            return json_encode([
                "success" => true,
                "message" => "Admin store dashboard metrics calculated successfully",
                "data" => [
                    "orders_summary" => [
                        "total_orders_placed" => 1248,
                        "orders_today" => 34,
                        "orders_pending_fulfillment" => 18,
                        "orders_shipped" => 42,
                        "orders_delivered" => 1140,
                        "orders_cancelled" => 14
                    ],
                    "coin_flow_summary" => [
                        "total_coins_redeemed" => 54800000,
                        "earned_coins_redeemed" => 41200000,
                        "bonus_coins_redeemed" => 13600000,
                        "average_order_coins" => 43910,
                        "total_refunded_coins" => 630000
                    ],
                    "inventory_alerts" => [
                        "total_active_skus" => 84,
                        "low_stock_skus_count" => 6,
                        "out_of_stock_skus_count" => 2
                    ],
                    "support_and_returns" => [
                        "open_support_tickets" => 5,
                        "pending_return_inspections" => 3,
                        "maker_checker_adjustments_pending" => 2
                    ],
                    "generated_at" => "2026-10-01T15:00:00.000000Z"
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        case 133: // Admin Sales Report
            return json_encode([
                "success" => true,
                "message" => "Sales reconciliation report generated successfully",
                "data" => [
                    "report_range" => [
                        "from" => "2026-09-01T00:00:00.000000Z",
                        "to" => "2026-09-30T23:59:59.000000Z"
                    ],
                    "totals" => [
                        "total_orders" => 342,
                        "total_units_sold" => 512,
                        "gross_sales_coins" => 18450000,
                        "delivery_charges_coins" => 680000,
                        "discounts_coins" => 120000,
                        "net_sales_coins" => 19010000,
                        "average_order_value_coins" => 55584
                    ],
                    "sales_by_category" => [
                        [
                            "category_name" => "Apparel & Merchandise",
                            "orders_count" => 210,
                            "coins_collected" => 12400000,
                            "percentage" => 65.2
                        ],
                        [
                            "category_name" => "Executive Office & Stationery",
                            "orders_count" => 98,
                            "coins_collected" => 4800000,
                            "percentage" => 25.3
                        ],
                        [
                            "category_name" => "Digital Masterclasses",
                            "orders_count" => 34,
                            "coins_collected" => 1810000,
                            "percentage" => 9.5
                        ]
                    ]
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        default:
            return null; // Will fallback to robust schema builder
    }
}

