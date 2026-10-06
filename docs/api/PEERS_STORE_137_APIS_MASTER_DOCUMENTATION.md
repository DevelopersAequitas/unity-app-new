# 🚀 Peers Global Unity — All 137 Store & Coin Wallet APIs (Official Master Guide)

> **Base Endpoint**: `/api` (Live & Local root)
> **Default Auth**: `Authorization: Bearer <SANCTUM_TOKEN>`
> **Format**: `Content-Type: application/json`, `Accept: application/json`

---

### 1. Store Configuration
- **Method**: `GET`
- **Endpoint**: `/api/v1/store/config`
- **Category / Module**: Customer Catalog
- **Developer Purpose & Deep Technical Overview**: Returns global runtime configuration for the mobile store module: store open/maintenance status, minimum supported app version requirement, user coin balance split (Earned vs Bonus coins), minimum coin threshold for home delivery, and promotional home banners.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Invoke on app launch / Splash Screen and when entering Store tab.<br><strong>State Handling:</strong> Store values in global state (`storeConfigProvider`). If `current_app_version_supported` is false, present a non-dismissible Force-Update modal dialog directing to App Store / Google Play.
- **Headers**: `Authorization: Bearer <TOKEN>` (Optional)`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Store configuration retrieved successfully",
    "data": {
        "store_enabled": true,
        "maintenance_mode": false,
        "maintenance_message": null,
        "minimum_app_version": "1.0.0",
        "latest_app_version": "1.4.2",
        "current_app_version_supported": true,
        "force_update_required": false,
        "coin_balance": 125000,
        "earned_coins": 100000,
        "bonus_coins": 25000,
        "locked_coins": 0,
        "spendable_coins": 125000,
        "delivery_minimum_coins": 100000,
        "free_delivery_coins_threshold": 250000,
        "standard_delivery_charge_coins": 5000,
        "pickup_available": true,
        "pickup_hold_days": 7,
        "return_window_days": 7,
        "max_quantity_per_peer_month": 5,
        "otp_required_coin_threshold": 50000,
        "currency_name": "Unity Coin",
        "currency_symbol": "UC",
        "support_email": "support@peersglobalunity.com",
        "support_phone": "+91 98250 12345",
        "banners": [
            {
                "id": "d0730dc3-3a80-4265-aeac-1dbe5eb3ac47",
                "title": "Exclusive Unity Merchandise",
                "subtitle": "Wear your peer identity with pride",
                "image_url": "https://images.unsplash.com/photo-1523381210434-271e8be1f52b?w=800",
                "link_type": "CATEGORY",
                "link_value": "apparel-clothing",
                "display_order": 1,
                "is_active": true,
                "created_at": "2026-09-01T10:00:00.000000Z",
                "updated_at": "2026-09-15T12:30:00.000000Z"
            },
            {
                "id": "e1841ed4-4b91-5376-bfbd-2ecf6fc4bd58",
                "title": "Annual Business Leadership Pass",
                "subtitle": "Unlock zero shipping coins on all physical catalog orders",
                "image_url": "https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800",
                "link_type": "MEMBERSHIP",
                "link_value": "gold-pass",
                "display_order": 2,
                "is_active": true,
                "created_at": "2026-09-05T08:15:00.000000Z",
                "updated_at": "2026-09-20T11:00:00.000000Z"
            }
        ]
    }
}
```

---

### 2. Store Banners
- **Method**: `GET`
- **Endpoint**: `/api/v1/store/banners?page=1&per_page=10`
- **Category / Module**: Customer Catalog
- **Developer Purpose & Deep Technical Overview**: Retrieves active promotional banners configured for the mobile store home screen with direct deep-link targets (CATEGORY, PRODUCT, PROMOTION_PAGE).
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Call on Store Home screen initialization.<br><strong>UI Handling:</strong> Feed into a `PageView` or `CarouselSlider` with 4-second auto-scroll. On banner tap, route user to the deep-linked product or category screen.
- **Headers**: `None`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Active banners retrieved successfully",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": "d0730dc3-3a80-4265-aeac-1dbe5eb3ac47",
                "title": "Exclusive Unity Merchandise",
                "subtitle": "Wear your peer identity with pride",
                "image_url": "https://images.unsplash.com/photo-1523381210434-271e8be1f52b?w=800",
                "link_type": "CATEGORY",
                "link_value": "apparel-clothing",
                "display_order": 1,
                "is_active": true,
                "starts_at": "2026-09-01T00:00:00.000000Z",
                "ends_at": "2026-12-31T23:59:59.000000Z",
                "created_at": "2026-09-01T10:00:00.000000Z",
                "updated_at": "2026-09-15T12:30:00.000000Z"
            },
            {
                "id": "e1841ed4-4b91-5376-bfbd-2ecf6fc4bd58",
                "title": "New Fall Collection 2026",
                "subtitle": "Polos, Blazers & Executive Bags",
                "image_url": "https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=800",
                "link_type": "PRODUCT",
                "link_value": "a05ceb9e-2747-4dba-841d-63da4d725c33",
                "display_order": 2,
                "is_active": true,
                "starts_at": "2026-09-10T00:00:00.000000Z",
                "ends_at": "2026-11-30T23:59:59.000000Z",
                "created_at": "2026-09-10T09:00:00.000000Z",
                "updated_at": "2026-09-12T14:20:00.000000Z"
            }
        ],
        "first_page_url": "http://localhost:8000/api/v1/store/banners?page=1",
        "from": 1,
        "last_page": 1,
        "last_page_url": "http://localhost:8000/api/v1/store/banners?page=1",
        "links": [
            {
                "url": null,
                "label": "&laquo; Previous",
                "active": false
            },
            {
                "url": "http://localhost:8000/api/v1/store/banners?page=1",
                "label": "1",
                "active": true
            },
            {
                "url": null,
                "label": "Next &raquo;",
                "active": false
            }
        ],
        "next_page_url": null,
        "path": "http://localhost:8000/api/v1/store/banners",
        "per_page": 10,
        "prev_page_url": null,
        "to": 2,
        "total": 2
    }
}
```

---

### 3. User Wallet Balance & Split
- **Method**: `GET`
- **Endpoint**: `/api/v1/wallet`
- **Category / Module**: Customer Wallet
- **Developer Purpose & Deep Technical Overview**: Queries the user coin wallet to provide real-time balances: total spendable coins, split between Earned Coins and Bonus Coins, lifetime earnings/expenditures, and account lock state (ACTIVE, FROZEN, CLOSED).
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Call on Store App Bar load, Wallet Screen, and immediately after placing an order or receiving a refund.<br><strong>UI Handling:</strong> Render formatted coin counters with coin icons. If `wallet_state == 'FROZEN'`, disable checkout and display an alert dialog.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Wallet details retrieved successfully",
    "data": {
        "user_id": "9a2f7c41-831e-4c02-990a-112233445566",
        "balance": 125000,
        "earned_balance": 100000,
        "bonus_balance": 25000,
        "locked_balance": 0,
        "lifetime_earned": 350000,
        "lifetime_spent": 225000,
        "lifetime_purchased": 0,
        "lifetime_bonus_granted": 50000,
        "wallet_state": "ACTIVE",
        "wallet_version": 18,
        "is_frozen": false,
        "frozen_at": null,
        "freeze_reason": null,
        "currency_unit": "COINS",
        "last_transaction_at": "2026-09-30T14:45:22.000000Z",
        "created_at": "2026-01-10T08:00:00.000000Z",
        "updated_at": "2026-09-30T14:45:22.000000Z"
    }
}
```

---

### 4. Wallet Ledger History
- **Method**: `GET`
- **Endpoint**: `/api/v1/wallet/ledger?bucket=BONUS&reference_type=ORDER&page=1&per_page=20`
- **Category / Module**: Customer Wallet
- **Developer Purpose & Deep Technical Overview**: Returns paginated passbook transactions from `coins_ledger` with filters by coin bucket (EARNED, BONUS), reference type (ORDER, RETURN_REFUND, BONUS_GRANT), and date range.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Use on Wallet Ledger screen with infinite scrolling list.<br><strong>UI Handling:</strong> Color credit entries green (`+500 Coins`) and debit deductions dark grey/red (`-1000 Coins`). Tapping a record navigates to the associated order details.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Wallet ledger history retrieved successfully",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": "f0123456-789a-bcde-f012-3456789abcde",
                "transaction_id": "TXN-20260930-89124",
                "user_id": "9a2f7c41-831e-4c02-990a-112233445566",
                "bucket": "BONUS",
                "direction": "DEBIT",
                "amount": -25000,
                "balance_before": 150000,
                "balance_after": 125000,
                "reference_type": "ORDER",
                "reference_id": "21098765-4321-0fed-cba9-876543210fed",
                "reference": "Order #ORD-982147",
                "narration": "Redeemed 25,000 Bonus Coins towards Order #ORD-982147",
                "created_by": null,
                "created_at": "2026-09-30T14:45:22.000000Z",
                "updated_at": "2026-09-30T14:45:22.000000Z"
            },
            {
                "id": "e9876543-210f-edcb-a987-6543210fedcb",
                "transaction_id": "TXN-20260925-54120",
                "user_id": "9a2f7c41-831e-4c02-990a-112233445566",
                "bucket": "EARNED",
                "direction": "CREDIT",
                "amount": 50000,
                "balance_before": 100000,
                "balance_after": 150000,
                "reference_type": "BUSINESS_MILESTONE",
                "reference_id": "b5a69870-1234-5678-9abc-def012345678",
                "reference": "Meeting Referral Milestone Tier 2",
                "narration": "Awarded for completing 10 verified business 1-to-1 meetings",
                "created_by": "system_engine",
                "created_at": "2026-09-25T11:20:10.000000Z",
                "updated_at": "2026-09-25T11:20:10.000000Z"
            }
        ],
        "first_page_url": "http://localhost:8000/api/v1/wallet/ledger?page=1",
        "from": 1,
        "last_page": 1,
        "last_page_url": "http://localhost:8000/api/v1/wallet/ledger?page=1",
        "links": [
            {
                "url": null,
                "label": "&laquo; Previous",
                "active": false
            },
            {
                "url": "http://localhost:8000/api/v1/wallet/ledger?page=1",
                "label": "1",
                "active": true
            },
            {
                "url": null,
                "label": "Next &raquo;",
                "active": false
            }
        ],
        "next_page_url": null,
        "path": "http://localhost:8000/api/v1/wallet/ledger",
        "per_page": 20,
        "prev_page_url": null,
        "to": 2,
        "total": 2
    }
}
```

---

### 5. Wallet Monthly Summary
- **Method**: `GET`
- **Endpoint**: `/api/v1/wallet/summary`
- **Category / Module**: Customer Wallet
- **Developer Purpose & Deep Technical Overview**: Aggregates the user's total coin earnings, redemptions, and pending hold amounts for the current calendar month compared against previous periods.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Call on user rewards dashboard or profile analytics widget.<br><strong>UI Handling:</strong> Render comparative monthly progress charts comparing earned coins against redeemed coins.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Wallet monthly summary retrieved successfully",
    "data": {
        "fiscal_month": "2026-09",
        "period_start": "2026-09-01T00:00:00.000000Z",
        "period_end": "2026-09-30T23:59:59.000000Z",
        "opening_balance": 90000,
        "current_balance": 125000,
        "earned_balance": 100000,
        "bonus_balance": 25000,
        "total_earned_this_month": 60000,
        "earned_from_meetings": 35000,
        "earned_from_referrals": 25000,
        "bonus_granted_this_month": 15000,
        "total_spent_this_month": 40000,
        "spent_on_physical_orders": 40000,
        "spent_on_memberships": 0,
        "net_balance_growth": 35000,
        "growth_percentage": 38.89,
        "total_orders_placed": 2,
        "transactions_count": 5
    }
}
```

---

### 6. Wallet Status & Freeze Info
- **Method**: `GET`
- **Endpoint**: `/api/v1/wallet/status`
- **Category / Module**: Customer Wallet
- **Developer Purpose & Deep Technical Overview**: Checks whether the user wallet is in good standing or frozen due to compliance reviews, returning freeze reason remarks and timestamp.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Call before entering the Checkout flow or on Wallet settings.<br><strong>UI Handling:</strong> If frozen, render a warning banner and disable 'Proceed to Checkout' buttons.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Wallet security status retrieved",
    "data": {
        "user_id": "9a2f7c41-831e-4c02-990a-112233445566",
        "wallet_state": "ACTIVE",
        "is_frozen": false,
        "frozen_at": null,
        "freeze_reason": null,
        "freeze_initiated_by": null,
        "can_transact": true,
        "can_redeem_store": true,
        "can_transfer": true,
        "daily_spend_limit_coins": 500000,
        "daily_spent_today_coins": 25000,
        "remaining_daily_limit_coins": 475000,
        "last_compliance_check_at": "2026-09-28T09:00:00.000000Z"
    }
}
```

---

### 7. Store Categories
- **Method**: `GET`
- **Endpoint**: `/api/v1/store/categories`
- **Category / Module**: Customer Catalog
- **Developer Purpose & Deep Technical Overview**: Fetches the list of active merchandise categories (Apparel, Accessories, Books, Tech) with thumbnail icons, slug names, and active product counts.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Call once on Store Home screen load and cache locally.<br><strong>UI Handling:</strong> Render as horizontal category chips or category card grid with product counts.
- **Headers**: `None`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Active categories retrieved successfully",
    "data": [
        {
            "id": "b5a69870-1234-5678-9abc-def012345678",
            "name": "Apparel & Merchandise",
            "slug": "apparel-clothing",
            "description": "Official Unity branded polo t-shirts, blazers, and caps",
            "icon_url": "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=100",
            "image_url": "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=500",
            "display_order": 1,
            "is_active": true,
            "products_count": 12,
            "created_at": "2026-08-15T10:00:00.000000Z",
            "updated_at": "2026-09-20T14:30:00.000000Z"
        },
        {
            "id": "c6b7a981-2345-6789-0bcd-ef0123456789",
            "name": "Executive Office & Stationery",
            "slug": "office-stationery",
            "description": "Engraved pens, metallic card holders, and leather diaries",
            "icon_url": "https://images.unsplash.com/photo-1583485088034-697b5bc54ccd?w=100",
            "image_url": "https://images.unsplash.com/photo-1583485088034-697b5bc54ccd?w=500",
            "display_order": 2,
            "is_active": true,
            "products_count": 8,
            "created_at": "2026-08-15T10:05:00.000000Z",
            "updated_at": "2026-09-18T11:20:00.000000Z"
        },
        {
            "id": "d7c8ba92-3456-7890-1cde-f01234567890",
            "name": "Digital Masterclasses & eBooks",
            "slug": "digital-masterclasses",
            "description": "High-impact business growth courses and training guides",
            "icon_url": "https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=100",
            "image_url": "https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=500",
            "display_order": 3,
            "is_active": true,
            "products_count": 6,
            "created_at": "2026-08-20T09:00:00.000000Z",
            "updated_at": "2026-09-22T16:45:00.000000Z"
        }
    ]
}
```

---

### 8. Product Listing & Filtering
- **Method**: `GET`
- **Endpoint**: `/api/v1/store/products?category_id=84582fbc-a518-49e7-a249-169b42362bec&type=PHYSICAL&min_coins=1000&max_coins=100000&sort=price_asc&page=1&per_page=20`
- **Category / Module**: Customer Catalog
- **Developer Purpose & Deep Technical Overview**: Queries the product catalog with filtering parameters: category ID, search keyword, coin price ranges (min/max), physical vs digital types, and sort sequences.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Main catalog browse screen and search result view.<br><strong>Performance:</strong> Implement a 300ms debounce on the search bar. Use 20-item chunk pagination with pull-to-refresh.
- **Headers**: `None`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Products retrieved successfully",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
                "name": "Peers Official Polo T-Shirt",
                "slug": "peers-official-polo-t-shirt",
                "sku": "POLO-001",
                "type": "PHYSICAL",
                "category_id": "b5a69870-1234-5678-9abc-def012345678",
                "category": {
                    "id": "b5a69870-1234-5678-9abc-def012345678",
                    "name": "Apparel & Merchandise",
                    "slug": "apparel-clothing"
                },
                "coin_price": 45000,
                "mrp": 1499,
                "short_description": "100% Breathable Pique Cotton with embroidered golden unity crest",
                "is_featured": true,
                "is_active": true,
                "stock_qty": 120,
                "delivery_modes": [
                    "DELIVERY",
                    "PICKUP"
                ],
                "return_allowed": true,
                "variants_count": 4,
                "primary_image": {
                    "id": "img_01",
                    "image_url": "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=600",
                    "is_primary": true
                },
                "created_at": "2026-09-01T10:00:00.000000Z",
                "updated_at": "2026-09-28T15:20:00.000000Z"
            }
        ],
        "first_page_url": "http://localhost:8000/api/v1/store/products?page=1",
        "from": 1,
        "last_page": 1,
        "last_page_url": "http://localhost:8000/api/v1/store/products?page=1",
        "links": [
            {
                "url": null,
                "label": "&laquo; Previous",
                "active": false
            },
            {
                "url": "http://localhost:8000/api/v1/store/products?page=1",
                "label": "1",
                "active": true
            },
            {
                "url": null,
                "label": "Next &raquo;",
                "active": false
            }
        ],
        "next_page_url": null,
        "path": "http://localhost:8000/api/v1/store/products",
        "per_page": 20,
        "prev_page_url": null,
        "to": 1,
        "total": 1
    }
}
```

---

### 9. Product Details
- **Method**: `GET`
- **Endpoint**: `/api/v1/store/products/a05ceb9e-2747-4dba-841d-63da4d725c33`
- **Category / Module**: Customer Catalog
- **Developer Purpose & Deep Technical Overview**: Retrieves complete product specifications, high-res image gallery, HTML description, delivery options (Courier vs Hub Pickup), and all configured SKU variants with live stock levels.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Call upon opening the Product Details screen.<br><strong>UI Handling:</strong> Bind variant selector chips (Size/Color). Update displayed coin price and stock badge in real-time as variants are selected.
- **Headers**: `None`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Product details retrieved successfully",
    "data": {
        "id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
        "name": "Peers Official Polo T-Shirt",
        "slug": "peers-official-polo-t-shirt",
        "sku": "POLO-001",
        "type": "PHYSICAL",
        "coin_price": 45000,
        "mrp": 1499,
        "short_description": "100% Breathable Pique Cotton with embroidered golden unity crest",
        "description": "<p>The signature Peers Global Unity Executive Polo is crafted from 240 GSM organic combed cotton with pre-shrunk wash. Features ribbed collar, two-button placket, and high-definition crest embroidery on the chest.</p>",
        "category_id": "b5a69870-1234-5678-9abc-def012345678",
        "category": {
            "id": "b5a69870-1234-5678-9abc-def012345678",
            "name": "Apparel & Merchandise",
            "slug": "apparel-clothing"
        },
        "delivery_modes": [
            "DELIVERY",
            "PICKUP"
        ],
        "return_allowed": true,
        "return_window_days": 7,
        "customised": false,
        "is_featured": true,
        "is_active": true,
        "stock_qty": 120,
        "images": [
            {
                "id": "img_01",
                "image_url": "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=800",
                "is_primary": true,
                "display_order": 1
            },
            {
                "id": "img_02",
                "image_url": "https://images.unsplash.com/photo-1581655353564-df123a1eb820?w=800",
                "is_primary": false,
                "display_order": 2
            }
        ],
        "variants": [
            {
                "id": "935d6a66-837e-40c0-9971-aadda2db131e",
                "product_id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
                "name": "Navy Blue - Medium (M)",
                "sku": "POLO-001-BLU-M",
                "coin_price": 45000,
                "mrp": 1499,
                "stock_qty": 40,
                "reserved_qty": 2,
                "is_active": true,
                "attributes": {
                    "size": "M",
                    "color": "Navy Blue",
                    "chest_inch": 40
                }
            },
            {
                "id": "a46e7b77-948f-51d1-0082-bbecb3ec242f",
                "product_id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
                "name": "Navy Blue - Large (L)",
                "sku": "POLO-001-BLU-L",
                "coin_price": 45000,
                "mrp": 1499,
                "stock_qty": 50,
                "reserved_qty": 1,
                "is_active": true,
                "attributes": {
                    "size": "L",
                    "color": "Navy Blue",
                    "chest_inch": 42
                }
            },
            {
                "id": "b57f8c88-0590-62e2-1193-ccfdc4fd3530",
                "product_id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
                "name": "Navy Blue - Extra Large (XL)",
                "sku": "POLO-001-BLU-XL",
                "coin_price": 45000,
                "mrp": 1499,
                "stock_qty": 30,
                "reserved_qty": 0,
                "is_active": true,
                "attributes": {
                    "size": "XL",
                    "color": "Navy Blue",
                    "chest_inch": 44
                }
            }
        ],
        "reviews_summary": {
            "average_rating": 4.85,
            "total_reviews": 24,
            "rating_distribution": {
                "5_star": 21,
                "4_star": 2,
                "3_star": 1,
                "2_star": 0,
                "1_star": 0
            }
        },
        "created_at": "2026-09-01T10:00:00.000000Z",
        "updated_at": "2026-09-28T15:20:00.000000Z"
    }
}
```

---

### 10. Featured Products
- **Method**: `GET`
- **Endpoint**: `/api/v1/store/products/featured`
- **Category / Module**: Customer Catalog
- **Developer Purpose & Deep Technical Overview**: Fetches curated spotlight products flagged by administrators as featured items for promotional carousels.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Call on Store Home screen to render the 'Featured Collections' horizontal slider.<br><strong>UI Handling:</strong> Display product cards with special 'Featured' ribbon badges.
- **Headers**: `None`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Featured products retrieved",
    "data": [
        {
            "id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
            "name": "Peers Official Polo T-Shirt",
            "coin_price": 45000,
            "primary_image": {
                "image_url": "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=500"
            }
        }
    ]
}
```

---

### 11. Search Products
- **Method**: `GET`
- **Endpoint**: `/api/v1/store/search?q=tshirt&category_id=84582fbc-a518-49e7-a249-169b42362bec&page=1`
- **Category / Module**: Customer Catalog
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Search Products on `/api/v1/store/search?q=tshirt&category_id=84582fbc-a518-49e7-a249-169b42362bec&page=1` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/v1/store/search?q=tshirt&category_id=84582fbc-a518-49e7-a249-169b42362bec&page=1` with active Bearer token.
- **Headers**: `None`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Search results retrieved",
    "data": {
        "current_page": 1,
        "data": [],
        "total": 0
    }
}
```

---

### 12. Get Active Cart
- **Method**: `GET`
- **Endpoint**: `/api/v1/cart`
- **Category / Module**: Customer Cart
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Get Active Cart on `/api/v1/cart` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/v1/cart` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Shopping cart retrieved successfully",
    "data": {
        "id": "cart_78901234-5678-9abc-def0-123456789abc",
        "user_id": "9a2f7c41-831e-4c02-990a-112233445566",
        "items_count": 2,
        "total_quantity": 2,
        "subtotal_coins": 90000,
        "estimated_delivery_coins": 5000,
        "discount_coins": 0,
        "total_coins": 95000,
        "user_coin_balance": 125000,
        "has_sufficient_balance": true,
        "has_out_of_stock_items": false,
        "items": [
            {
                "id": "item_11223344-5566-7788-99aa-bbccddeeff00",
                "cart_id": "cart_78901234-5678-9abc-def0-123456789abc",
                "product_id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
                "product_variant_id": "935d6a66-837e-40c0-9971-aadda2db131e",
                "quantity": 1,
                "unit_coin_price": 45000,
                "total_item_coins": 45000,
                "product_name": "Peers Official Polo T-Shirt",
                "variant_name": "Navy Blue - Medium (M)",
                "sku": "POLO-001-BLU-M",
                "image_url": "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=300",
                "in_stock": true,
                "available_stock": 40,
                "max_allowed_quantity": 5
            },
            {
                "id": "item_22334455-6677-8899-aabb-ccddeeff0011",
                "cart_id": "cart_78901234-5678-9abc-def0-123456789abc",
                "product_id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
                "product_variant_id": "a46e7b77-948f-51d1-0082-bbecb3ec242f",
                "quantity": 1,
                "unit_coin_price": 45000,
                "total_item_coins": 45000,
                "product_name": "Peers Official Polo T-Shirt",
                "variant_name": "Navy Blue - Large (L)",
                "sku": "POLO-001-BLU-L",
                "image_url": "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=300",
                "in_stock": true,
                "available_stock": 50,
                "max_allowed_quantity": 5
            }
        ],
        "created_at": "2026-09-30T10:15:00.000000Z",
        "updated_at": "2026-09-30T10:20:00.000000Z"
    }
}
```

---

### 13. Add Item to Cart
- **Method**: `POST`
- **Endpoint**: `/api/v1/cart/items`
- **Category / Module**: Customer Cart
- **Developer Purpose & Deep Technical Overview**: Adds a product variant and quantity to the cart, enforcing stock availability and monthly peer purchase limits.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Trigger on 'Add to Cart' button tap.<br><strong>UI Handling:</strong> Show toast notification with 'View Cart' CTA and increment top cart badge counter.
- **Headers**: `Authorization: Bearer <TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Item added to cart",
    "data": {
        "cart_item": {
            "id": "01a0ed41-15dc-70db-82a3-80011e737c08",
            "quantity": 2,
            "unit_coin_price": 45000
        }
    }
}
```

---

### 14. Update Cart Item Quantity
- **Method**: `PATCH`
- **Endpoint**: `/api/v1/cart/items/01a0ed41-15dc-70db-82a3-80011e737c08`
- **Category / Module**: Customer Cart
- **Developer Purpose & Deep Technical Overview**: Adjusts the quantity of an existing line item in the cart, verifying live inventory limits before saving.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Trigger on cart quantity stepper clicks (`+` or `-`).<br><strong>UI Handling:</strong> Optimistic local UI update with server rollback if a 422 stock error is returned.
- **Headers**: `Authorization: Bearer <TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Cart item updated",
    "data": {
        "cart_item": {
            "id": "01a0ed41-15dc-70db-82a3-80011e737c08",
            "quantity": 3
        }
    }
}
```

---

### 15. Remove Item from Cart
- **Method**: `DELETE`
- **Endpoint**: `/api/v1/cart/items/01a0ed41-15dc-70db-82a3-80011e737c08`
- **Category / Module**: Customer Cart
- **Developer Purpose & Deep Technical Overview**: Deletes a specific line item from the shopping cart and recalculates total payable coins.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Trigger on delete icon or swipe-to-delete gesture on a cart row.<br><strong>UI Handling:</strong> Animate item removal and update bottom total bar.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Item removed from cart",
    "data": {
        "removed": true
    }
}
```

---

### 16. Validate Cart (Pre-checkout Check)
- **Method**: `POST`
- **Endpoint**: `/api/v1/cart/validate`
- **Category / Module**: Customer Cart
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Validate Cart (Pre-checkout Check) on `/api/v1/cart/validate` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/v1/cart/validate` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Cart validation completed",
    "data": {
        "is_valid": true,
        "items": [
            {
                "cart_item_id": "01a0ed41-15dc-70db-82a3-80011e737c08",
                "status": "OK",
                "price_seen": 45000,
                "current_price": 45000,
                "price_changed": false
            }
        ]
    }
}
```

---

### 17. List User Addresses
- **Method**: `GET`
- **Endpoint**: `/api/v1/addresses`
- **Category / Module**: Address & Delivery
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for List User Addresses on `/api/v1/addresses` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/v1/addresses` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Saved addresses retrieved successfully",
    "data": [
        {
            "id": "01a0ecd1-1d7f-70ab-885a-020f273de747",
            "user_id": "9a2f7c41-831e-4c02-990a-112233445566",
            "full_name": "Rajesh M. Patel",
            "phone": "9825012345",
            "alternate_phone": "9825098765",
            "address_line1": "402, Titanium City Centre, Anandnagar Road",
            "address_line2": "Near Sachin Tower, Prahladnagar",
            "landmark": "Opp. Seema Hall",
            "city": "Ahmedabad",
            "state": "Gujarat",
            "pincode": "380015",
            "address_type": "OFFICE",
            "is_default": true,
            "delivery_instructions": "Deliver during office hours between 10 AM to 6 PM",
            "created_at": "2026-08-10T11:00:00.000000Z",
            "updated_at": "2026-09-20T16:00:00.000000Z"
        },
        {
            "id": "02b1fde2-2e80-81bc-996b-1310384ef858",
            "user_id": "9a2f7c41-831e-4c02-990a-112233445566",
            "full_name": "Rajesh Patel (Residence)",
            "phone": "9825012345",
            "alternate_phone": null,
            "address_line1": "B-12, Suryodaya Bungalows, Bodakdev",
            "address_line2": "Judges Bungalow Road",
            "landmark": "Behind Pakwan Dining Hall",
            "city": "Ahmedabad",
            "state": "Gujarat",
            "pincode": "380054",
            "address_type": "HOME",
            "is_default": false,
            "delivery_instructions": "Leave with security if not available",
            "created_at": "2026-08-25T14:30:00.000000Z",
            "updated_at": "2026-08-25T14:30:00.000000Z"
        }
    ]
}
```

---

### 18. Create Delivery Address
- **Method**: `POST`
- **Endpoint**: `/api/v1/addresses`
- **Category / Module**: Address & Delivery
- **Developer Purpose & Deep Technical Overview**: Validates and creates a new delivery shipping address in `user_addresses`.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Trigger from 'Add New Address' modal form.<br><strong>UI Handling:</strong> Form validation for phone and 6-digit pincode; auto-select upon creation.
- **Headers**: `Authorization: Bearer <TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Address created successfully",
    "data": {
        "id": "01a0ecd1-1d7f-70ab-885a-020f273de747",
        "full_name": "Rajesh Patel",
        "pincode": "380015",
        "is_default": true
    }
}
```

---

### 19. Get Address by ID
- **Method**: `GET`
- **Endpoint**: `/api/v1/addresses/01a0ecd1-1d7f-70ab-885a-020f273de747`
- **Category / Module**: Address & Delivery
- **Developer Purpose & Deep Technical Overview**: Fetches full details of a specific saved address by its address UUID.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Call before opening the Edit Address bottom sheet.<br><strong>UI Handling:</strong> Pre-populate text form fields.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Address details retrieved",
    "data": {
        "id": "01a0ecd1-1d7f-70ab-885a-020f273de747",
        "full_name": "Rajesh Patel",
        "pincode": "380015"
    }
}
```

---

### 20. Update Address
- **Method**: `PUT`
- **Endpoint**: `/api/v1/addresses/01a0ecd1-1d7f-70ab-885a-020f273de747`
- **Category / Module**: Address & Delivery
- **Developer Purpose & Deep Technical Overview**: Modifies existing shipping address details in the database.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Trigger on 'Save Address' button.<br><strong>UI Handling:</strong> Show submit spinner and refresh address list on success.
- **Headers**: `Authorization: Bearer <TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Address updated successfully",
    "data": {
        "id": "01a0ecd1-1d7f-70ab-885a-020f273de747",
        "full_name": "Rajesh M. Patel"
    }
}
```

---

### 21. Delete Address
- **Method**: `DELETE`
- **Endpoint**: `/api/v1/addresses/01a0ecd1-1d7f-70ab-885a-020f273de747`
- **Category / Module**: Address & Delivery
- **Developer Purpose & Deep Technical Overview**: Soft deletes a delivery address from the user profile if not linked to an active pending dispatch.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Trigger on delete button in address management.<br><strong>UI Handling:</strong> Confirm with dialog and remove card from list.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Address removed successfully",
    "data": {
        "deleted": true
    }
}
```

---

### 22. Check Pincode Serviceability
- **Method**: `POST`
- **Endpoint**: `/api/v1/serviceability/check`
- **Category / Module**: Address & Delivery
- **Developer Purpose & Deep Technical Overview**: Validates if a 6-digit postal pincode is covered by courier partners and returns delivery transit day estimates.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Trigger with 300ms debounce as soon as user types 6 digits in the pincode field.<br><strong>UI Handling:</strong> If not serviceable, show red warning and suggest selecting Central Pickup Hub.
- **Headers**: `Authorization: Bearer <TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Serviceability checked successfully",
    "data": {
        "serviceable": true,
        "pincode": "380015",
        "city": "Ahmedabad",
        "state": "Gujarat",
        "delivery_available": true,
        "pickup_available": true,
        "delivery_days": 4
    }
}
```

---

### 23. List Pickup Points
- **Method**: `GET`
- **Endpoint**: `/api/v1/pickup-points?pincode=380015`
- **Category / Module**: Address & Delivery
- **Developer Purpose & Deep Technical Overview**: Retrieves all active Peers Central Hub takeaway pickup points where peers can collect orders with zero delivery coins.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Call when user chooses 'Pickup from Hub' delivery mode on Checkout.<br><strong>UI Handling:</strong> Render hub cards with address, manager contact, and operating hours.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Pickup points retrieved",
    "data": [
        {
            "id": "5c4b3a21-0987-6543-210f-edcba9876543",
            "name": "Peers Ahmedabad Central Hub",
            "code": "AHM-01",
            "city": "Ahmedabad",
            "pincode": "380015"
        }
    ]
}
```

---

### 24. Get Pickup Point Detail
- **Method**: `GET`
- **Endpoint**: `/api/v1/pickup-points/5c4b3a21-0987-6543-210f-edcba9876543`
- **Category / Module**: Address & Delivery
- **Developer Purpose & Deep Technical Overview**: Fetches complete operational details and Google Maps coordinate info for a specific pickup hub.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Call when user taps on a pickup hub card.<br><strong>UI Handling:</strong> Display detailed location modal with 'Open in Maps' action.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Pickup point details retrieved",
    "data": {
        "id": "5c4b3a21-0987-6543-210f-edcba9876543",
        "name": "Peers Ahmedabad Central Hub",
        "address_line1": "GF, Shapath V, SG Highway",
        "city": "Ahmedabad",
        "phone": "9825000000",
        "manager_name": "Sanjay Shah"
    }
}
```

---

### 25. Generate Checkout Quote
- **Method**: `POST`
- **Endpoint**: `/api/v1/checkout/quote`
- **Category / Module**: Checkout & Quotes
- **Developer Purpose & Deep Technical Overview**: Creates a 15-minute locked checkout valuation quote (`checkout_quotes`), calculating coin balances, delivery charges, and assessing if OTP challenge is mandatory.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Call on opening Order Review / Checkout summary.<br><strong>UI Handling:</strong> Start a 15-minute visual countdown timer. If `otp_required: true`, open OTP bottom sheet upon placing order.
- **Headers**: `Authorization: Bearer <TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Checkout valuation quote generated successfully",
    "data": {
        "quote_id": "quo_98765432-10fe-dcba-9876-543210fedcba",
        "quote_token": "qtok_live_77a88b99cc0011223344",
        "user_id": "9a2f7c41-831e-4c02-990a-112233445566",
        "delivery_type": "DELIVERY",
        "shipping_address_id": "01a0ecd1-1d7f-70ab-885a-020f273de747",
        "pickup_point_id": null,
        "items_count": 2,
        "total_quantity": 2,
        "subtotal_coins": 90000,
        "delivery_charge_coins": 5000,
        "discount_coins": 0,
        "total_coins_payable": 95000,
        "user_available_coins": 125000,
        "coin_split": {
            "earned_coins_to_debit": 70000,
            "bonus_coins_to_debit": 25000,
            "remaining_balance_after_order": 30000
        },
        "coin_shortfall": 0,
        "can_proceed": true,
        "otp_required": false,
        "otp_challenge_id": null,
        "expires_at": "2026-10-01T10:45:00.000000Z",
        "validity_seconds_remaining": 900,
        "items": [
            {
                "product_variant_id": "935d6a66-837e-40c0-9971-aadda2db131e",
                "product_name": "Peers Official Polo T-Shirt",
                "variant_name": "Navy Blue - Medium (M)",
                "sku": "POLO-001-BLU-M",
                "quantity": 1,
                "unit_coin_price": 45000,
                "total_coins": 45000
            },
            {
                "product_variant_id": "a46e7b77-948f-51d1-0082-bbecb3ec242f",
                "product_name": "Peers Official Polo T-Shirt",
                "variant_name": "Navy Blue - Large (L)",
                "sku": "POLO-001-BLU-L",
                "quantity": 1,
                "unit_coin_price": 45000,
                "total_coins": 45000
            }
        ],
        "created_at": "2026-10-01T10:30:00.000000Z"
    }
}
```

---

### 26. Send Checkout OTP Challenge
- **Method**: `POST`
- **Endpoint**: `/api/v1/checkout/otp/send`
- **Category / Module**: Checkout & Quotes
- **Developer Purpose & Deep Technical Overview**: Dispatches a 6-digit SMS/WhatsApp verification OTP for high-value coin redemptions or address modifications.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Trigger when placing order if quote indicated OTP requirement.<br><strong>UI Handling:</strong> Render 6-digit pin input dialog with 60-second resend timer.
- **Headers**: `Authorization: Bearer <TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "OTP challenge sent successfully",
    "data": {
        "challenge_id": "3a210987-6543-210f-edcb-a9876543210f",
        "expires_at": "2026-09-29T10:25:00Z",
        "phone_hint": "3210"
    }
}
```

---

### 27. Verify Checkout OTP Challenge
- **Method**: `POST`
- **Endpoint**: `/api/v1/checkout/otp/verify`
- **Category / Module**: Checkout & Quotes
- **Developer Purpose & Deep Technical Overview**: Validates the 6-digit OTP passcode against the active challenge record and unlocks the quote for order placement.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Auto-trigger when 6th OTP digit is entered.<br><strong>UI Handling:</strong> On success, immediately proceed to call Place Order API.
- **Headers**: `Authorization: Bearer <TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "OTP verified successfully",
    "data": {
        "verified": true,
        "verification_token": "store_otp_tok_98f7e6d5c4b3a2109876543210fedcba"
    }
}
```

---

### 28. Place Order (Atomic Transaction)
- **Method**: `POST`
- **Endpoint**: `/api/v1/orders`
- **Category / Module**: Orders & Tracking
- **Developer Purpose & Deep Technical Overview**: Atomically finalizes purchase inside a database transaction: locks wallet, debits coins (Bonus first, then Earned), reserves inventory, and creates immutable order records.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Trigger on final 'Confirm & Place Order' tap.<br><strong>Crucial Rule:</strong> Pass a unique UUID in `Idempotency-Key` header to prevent duplicate orders on poor connectivity. Navigate to Order Success celebration.
- **Headers**: `- `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Order placed successfully",
    "data": {
        "id": "21098765-4321-0fed-cba9-876543210fed",
        "order_number": "ORD-20261001-982147",
        "user_id": "9a2f7c41-831e-4c02-990a-112233445566",
        "status": "PLACED",
        "payment_status": "PAID_WITH_COINS",
        "delivery_type": "DELIVERY",
        "subtotal_coins": 90000,
        "delivery_charge_coins": 5000,
        "discount_coins": 0,
        "total_coins_paid": 95000,
        "earned_coins_debited": 70000,
        "bonus_coins_debited": 25000,
        "coin_ledger_transaction_id": "TXN-20261001-982147",
        "shipping_address": {
            "id": "01a0ecd1-1d7f-70ab-885a-020f273de747",
            "full_name": "Rajesh M. Patel",
            "phone": "9825012345",
            "address_line1": "402, Titanium City Centre, Anandnagar Road",
            "address_line2": "Near Sachin Tower, Prahladnagar",
            "city": "Ahmedabad",
            "state": "Gujarat",
            "pincode": "380015"
        },
        "pickup_point": null,
        "items": [
            {
                "id": "oi_11223344-5566-7788-99aa-bbccddeeff00",
                "order_id": "21098765-4321-0fed-cba9-876543210fed",
                "product_id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
                "product_variant_id": "935d6a66-837e-40c0-9971-aadda2db131e",
                "product_name": "Peers Official Polo T-Shirt",
                "variant_name": "Navy Blue - Medium (M)",
                "sku": "POLO-001-BLU-M",
                "coin_price": 45000,
                "quantity": 1,
                "total_coins": 45000,
                "status": "CONFIRMED"
            },
            {
                "id": "oi_22334455-6677-8899-aabb-ccddeeff0011",
                "order_id": "21098765-4321-0fed-cba9-876543210fed",
                "product_id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
                "product_variant_id": "a46e7b77-948f-51d1-0082-bbecb3ec242f",
                "product_name": "Peers Official Polo T-Shirt",
                "variant_name": "Navy Blue - Large (L)",
                "sku": "POLO-001-BLU-L",
                "coin_price": 45000,
                "quantity": 1,
                "total_coins": 45000,
                "status": "CONFIRMED"
            }
        ],
        "action_flags": {
            "can_cancel": true,
            "can_return": false,
            "can_track": false,
            "can_download_receipt": true,
            "can_pickup": false
        },
        "status_history": [
            {
                "id": "sh_01",
                "from_status": "DRAFT",
                "to_status": "PLACED",
                "remarks": "Order successfully placed and coin wallet debited.",
                "created_at": "2026-10-01T10:35:00.000000Z"
            }
        ],
        "invoice_url": "http://localhost:8000/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/receipt",
        "created_at": "2026-10-01T10:35:00.000000Z",
        "updated_at": "2026-10-01T10:35:00.000000Z"
    }
}
```

---

### 29. List User Orders
- **Method**: `GET`
- **Endpoint**: `/api/v1/orders?status=CONFIRMED&page=1&per_page=20`
- **Category / Module**: Orders & Tracking
- **Developer Purpose & Deep Technical Overview**: Retrieves paginated order history for the authenticated peer with status filtering (PLACED, PROCESSING, SHIPPED, DELIVERED, CANCELLED, RETURNED).
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Call on 'My Orders' screen with pull-to-refresh.<br><strong>UI Handling:</strong> Display order cards with item thumbnails, total coins, and color-coded status badges.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Order placed successfully",
    "data": {
        "id": "21098765-4321-0fed-cba9-876543210fed",
        "order_number": "ORD-20261001-982147",
        "user_id": "9a2f7c41-831e-4c02-990a-112233445566",
        "status": "PLACED",
        "payment_status": "PAID_WITH_COINS",
        "delivery_type": "DELIVERY",
        "subtotal_coins": 90000,
        "delivery_charge_coins": 5000,
        "discount_coins": 0,
        "total_coins_paid": 95000,
        "earned_coins_debited": 70000,
        "bonus_coins_debited": 25000,
        "coin_ledger_transaction_id": "TXN-20261001-982147",
        "shipping_address": {
            "id": "01a0ecd1-1d7f-70ab-885a-020f273de747",
            "full_name": "Rajesh M. Patel",
            "phone": "9825012345",
            "address_line1": "402, Titanium City Centre, Anandnagar Road",
            "address_line2": "Near Sachin Tower, Prahladnagar",
            "city": "Ahmedabad",
            "state": "Gujarat",
            "pincode": "380015"
        },
        "pickup_point": null,
        "items": [
            {
                "id": "oi_11223344-5566-7788-99aa-bbccddeeff00",
                "order_id": "21098765-4321-0fed-cba9-876543210fed",
                "product_id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
                "product_variant_id": "935d6a66-837e-40c0-9971-aadda2db131e",
                "product_name": "Peers Official Polo T-Shirt",
                "variant_name": "Navy Blue - Medium (M)",
                "sku": "POLO-001-BLU-M",
                "coin_price": 45000,
                "quantity": 1,
                "total_coins": 45000,
                "status": "CONFIRMED"
            },
            {
                "id": "oi_22334455-6677-8899-aabb-ccddeeff0011",
                "order_id": "21098765-4321-0fed-cba9-876543210fed",
                "product_id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
                "product_variant_id": "a46e7b77-948f-51d1-0082-bbecb3ec242f",
                "product_name": "Peers Official Polo T-Shirt",
                "variant_name": "Navy Blue - Large (L)",
                "sku": "POLO-001-BLU-L",
                "coin_price": 45000,
                "quantity": 1,
                "total_coins": 45000,
                "status": "CONFIRMED"
            }
        ],
        "action_flags": {
            "can_cancel": true,
            "can_return": false,
            "can_track": false,
            "can_download_receipt": true,
            "can_pickup": false
        },
        "status_history": [
            {
                "id": "sh_01",
                "from_status": "DRAFT",
                "to_status": "PLACED",
                "remarks": "Order successfully placed and coin wallet debited.",
                "created_at": "2026-10-01T10:35:00.000000Z"
            }
        ],
        "invoice_url": "http://localhost:8000/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/receipt",
        "created_at": "2026-10-01T10:35:00.000000Z",
        "updated_at": "2026-10-01T10:35:00.000000Z"
    }
}
```

---

### 30. Get Order Details & Action Flags
- **Method**: `GET`
- **Endpoint**: `/api/v1/orders/21098765-4321-0fed-cba9-876543210fed`
- **Category / Module**: Orders & Tracking
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Get Order Details & Action Flags on `/api/v1/orders/21098765-4321-0fed-cba9-876543210fed` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/v1/orders/21098765-4321-0fed-cba9-876543210fed` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Order details retrieved",
    "data": {
        "id": "21098765-4321-0fed-cba9-876543210fed",
        "order_no": "ORD-123456",
        "status": "CONFIRMED",
        "total_coins": 90000,
        "action_flags": {
            "can_cancel": true,
            "can_return": false,
            "can_track": true,
            "can_download_receipt": true,
            "can_pickup": false
        }
    }
}
```

---

### 31. Cancel Order
- **Method**: `POST`
- **Endpoint**: `/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/cancel`
- **Category / Module**: Orders & Tracking
- **Developer Purpose & Deep Technical Overview**: Allows peer to cancel an order prior to SHIPPED status, instantly executing an automatic coin refund transaction to the user wallet.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Trigger from 'Cancel Order' button on eligible orders.<br><strong>UI Handling:</strong> Show reason selection sheet; refresh wallet and order status on success.
- **Headers**: `Authorization: Bearer <TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Order cancelled and coins refunded successfully",
    "data": {
        "id": "21098765-4321-0fed-cba9-876543210fed",
        "status": "CANCELLED"
    }
}
```

---

### 32. Order Status Audit History
- **Method**: `GET`
- **Endpoint**: `/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/status-history`
- **Category / Module**: Orders & Tracking
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Order Status Audit History on `/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/status-history` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/v1/orders/21098765-4321-0fed-cba9-876543210fed/status-history` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Order status history retrieved",
    "data": [
        {
            "from_status": null,
            "to_status": "CONFIRMED",
            "reason": "Order placed successfully",
            "created_at": "2026-09-29T10:00:00Z"
        }
    ]
}
```

---

### 33. Order Coin Receipt
- **Method**: `GET`
- **Endpoint**: `/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/receipt`
- **Category / Module**: Orders & Tracking
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Order Coin Receipt on `/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/receipt` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/v1/orders/21098765-4321-0fed-cba9-876543210fed/receipt` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Receipt retrieved",
    "data": {
        "receipt_no": "REC-789012",
        "coins_paid": 90000,
        "receipt_data": {
            "order_no": "ORD-123456",
            "peer_name": "Rajesh Patel",
            "total_coins": 90000
        }
    }
}
```

---

### 34. Order Live Tracking
- **Method**: `GET`
- **Endpoint**: `/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/tracking`
- **Category / Module**: Orders & Tracking
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Order Live Tracking on `/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/tracking` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/v1/orders/21098765-4321-0fed-cba9-876543210fed/tracking` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Shipment tracking details retrieved",
    "data": {
        "shipment_id": "shp_34567890-1234-5678-90ab-cdef12345678",
        "order_id": "21098765-4321-0fed-cba9-876543210fed",
        "order_number": "ORD-20261001-982147",
        "carrier_name": "Delhivery Express",
        "carrier_code": "DELHIVERY",
        "awb_number": "DEL1234567890IN",
        "status": "IN_TRANSIT",
        "shipped_at": "2026-10-02T11:00:00.000000Z",
        "estimated_delivery_at": "2026-10-05T18:00:00.000000Z",
        "delivered_at": null,
        "tracking_url": "https://www.delhivery.com/track/package/DEL1234567890IN",
        "origin_hub": "Peers Central Warehouse, Changodar, Ahmedabad",
        "destination_pincode": "380015",
        "checkpoints": [
            {
                "timestamp": "2026-10-02T11:00:00.000000Z",
                "location": "Ahmedabad Central Warehouse",
                "status": "SHIPPED",
                "remarks": "Manifested and handed over to Delhivery logistics"
            },
            {
                "timestamp": "2026-10-02T16:30:00.000000Z",
                "location": "Ahmedabad Sorting Hub (Bavla)",
                "status": "IN_TRANSIT",
                "remarks": "Package processed at central sort facility"
            },
            {
                "timestamp": "2026-10-03T09:15:00.000000Z",
                "location": "Prahladnagar Delivery Branch",
                "status": "ARRIVED_AT_DESTINATION_HUB",
                "remarks": "Received at local delivery station"
            }
        ]
    }
}
```

---

### 35. Submit Return Request
- **Method**: `POST`
- **Endpoint**: `/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/return`
- **Category / Module**: Orders & Tracking
- **Developer Purpose & Deep Technical Overview**: Submits a return request within the 7-day delivery window with return reason description and photo evidence.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Trigger from Order Details 'Return Item' CTA.<br><strong>UI Handling:</strong> Form with camera/gallery picker requiring at least one photo upload.
- **Headers**: `Authorization: Bearer <TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Return request submitted successfully",
    "data": {
        "id": "10987654-3210-fedc-ba98-76543210fedc",
        "return_no": "RET-981240",
        "status": "REQUESTED"
    }
}
```

---

### 36. List User Returns
- **Method**: `GET`
- **Endpoint**: `/api/v1/returns?page=1`
- **Category / Module**: Returns & Refunds
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for List User Returns on `/api/v1/returns?page=1` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/v1/returns?page=1` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Return requests retrieved",
    "data": {
        "current_page": 1,
        "data": [],
        "total": 0
    }
}
```

---

### 37. Get Return Details
- **Method**: `GET`
- **Endpoint**: `/api/v1/returns/10987654-3210-fedc-ba98-76543210fedc`
- **Category / Module**: Returns & Refunds
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Get Return Details on `/api/v1/returns/10987654-3210-fedc-ba98-76543210fedc` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/v1/returns/10987654-3210-fedc-ba98-76543210fedc` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Return request processed successfully",
    "data": {
        "id": "ret_56789012-3456-7890-abcd-ef1234567890",
        "return_number": "RET-20261005-4412",
        "order_id": "21098765-4321-0fed-cba9-876543210fed",
        "order_item_id": "oi_11223344-5566-7788-99aa-bbccddeeff00",
        "user_id": "9a2f7c41-831e-4c02-990a-112233445566",
        "product_name": "Peers Official Polo T-Shirt",
        "variant_name": "Navy Blue - Medium (M)",
        "reason_code": "SIZE_MISFIT",
        "reason_description": "Item received is tighter than expected sizing measurements. Requesting coin refund.",
        "proof_images": [
            "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=800"
        ],
        "status": "PENDING_INSPECTION",
        "refund_coins_amount": 45000,
        "refund_ledger_id": null,
        "pickup_address": {
            "full_name": "Rajesh M. Patel",
            "phone": "9825012345",
            "address_line1": "402, Titanium City Centre",
            "city": "Ahmedabad",
            "pincode": "380015"
        },
        "inspection_remarks": null,
        "created_at": "2026-10-05T14:20:00.000000Z",
        "updated_at": "2026-10-05T14:20:00.000000Z"
    }
}
```

---

### 38. Cancel Return Request
- **Method**: `POST`
- **Endpoint**: `/api/v1/returns/10987654-3210-fedc-ba98-76543210fedc/cancel`
- **Category / Module**: Returns & Refunds
- **Developer Purpose & Deep Technical Overview**: Allows peer to withdraw an open return request before warehouse collection.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Trigger on 'Cancel Return' button.<br><strong>UI Handling:</strong> Confirm with dialog and update status chip to CANCELLED.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Return request processed successfully",
    "data": {
        "id": "ret_56789012-3456-7890-abcd-ef1234567890",
        "return_number": "RET-20261005-4412",
        "order_id": "21098765-4321-0fed-cba9-876543210fed",
        "order_item_id": "oi_11223344-5566-7788-99aa-bbccddeeff00",
        "user_id": "9a2f7c41-831e-4c02-990a-112233445566",
        "product_name": "Peers Official Polo T-Shirt",
        "variant_name": "Navy Blue - Medium (M)",
        "reason_code": "SIZE_MISFIT",
        "reason_description": "Item received is tighter than expected sizing measurements. Requesting coin refund.",
        "proof_images": [
            "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=800"
        ],
        "status": "PENDING_INSPECTION",
        "refund_coins_amount": 45000,
        "refund_ledger_id": null,
        "pickup_address": {
            "full_name": "Rajesh M. Patel",
            "phone": "9825012345",
            "address_line1": "402, Titanium City Centre",
            "city": "Ahmedabad",
            "pincode": "380015"
        },
        "inspection_remarks": null,
        "created_at": "2026-10-05T14:20:00.000000Z",
        "updated_at": "2026-10-05T14:20:00.000000Z"
    }
}
```

---

### 39. Get Refund Details
- **Method**: `GET`
- **Endpoint**: `/api/v1/refunds/09876543-210f-edcb-a987-6543210fedcb`
- **Category / Module**: Customer Catalog
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Get Refund Details on `/api/v1/refunds/09876543-210f-edcb-a987-6543210fedcb` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/v1/refunds/09876543-210f-edcb-a987-6543210fedcb` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Refund details retrieved",
    "data": {
        "id": "09876543-210f-edcb-a987-6543210fedcb",
        "refund_no": "REF-987654",
        "refund_coins": 90000,
        "status": "COMPLETED"
    }
}
```

---

### 40. User Membership Status
- **Method**: `GET`
- **Endpoint**: `/api/v1/membership`
- **Category / Module**: Customer Catalog
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for User Membership Status on `/api/v1/membership` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/v1/membership` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Membership status retrieved",
    "data": {
        "status": "ACTIVE",
        "membership_status": "active",
        "end_date": "2027-09-29",
        "days_remaining": 365
    }
}
```

---

### 41. List Membership Plans for Coins
- **Method**: `GET`
- **Endpoint**: `/api/v1/membership/plans`
- **Category / Module**: Customer Catalog
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for List Membership Plans for Coins on `/api/v1/membership/plans` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/v1/membership/plans` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Active membership plans retrieved",
    "data": [
        {
            "id": "f1e2d3c4-b5a6-9870-1234-56789abcdef0",
            "name": "12 Months Global Peer",
            "duration_months": 12,
            "price_coins": 250000
        }
    ]
}
```

---

### 42. Membership Renewal Quote
- **Method**: `POST`
- **Endpoint**: `/api/v1/membership/quote`
- **Category / Module**: Customer Catalog
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Membership Renewal Quote on `/api/v1/membership/quote` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/v1/membership/quote` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Membership renewal quote calculated",
    "data": {
        "plan_id": "f1e2d3c4-b5a6-9870-1234-56789abcdef0",
        "plan_name": "12 Months Global Peer",
        "duration_months": 12,
        "price_coins": 250000,
        "new_end_date": "2027-09-29",
        "wallet_balance": 300000,
        "shortfall": 0,
        "can_renew": true
    }
}
```

---

### 43. Renew Membership with Coins
- **Method**: `POST`
- **Endpoint**: `/api/v1/membership/renew`
- **Category / Module**: Customer Catalog
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Renew Membership with Coins on `/api/v1/membership/renew` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/v1/membership/renew` with active Bearer token.
- **Headers**: `- `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Membership renewed successfully using coins",
    "data": {
        "ledger": {
            "coins_paid": 250000,
            "end_date": "2027-09-29",
            "status": "ACTIVE"
        }
    }
}
```

---

### 44. Digital Library / Entitlements
- **Method**: `GET`
- **Endpoint**: `/api/v1/library?page=1`
- **Category / Module**: Customer Catalog
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Digital Library / Entitlements on `/api/v1/library?page=1` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/v1/library?page=1` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "User digital library retrieved",
    "data": {
        "current_page": 1,
        "data": [],
        "total": 0
    }
}
```

---

### 45. Digital Entitlement Detail
- **Method**: `GET`
- **Endpoint**: `/api/v1/library/e2d3c4b5-a698-7012-3456-789abcdef012`
- **Category / Module**: Customer Catalog
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Digital Entitlement Detail on `/api/v1/library/e2d3c4b5-a698-7012-3456-789abcdef012` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/v1/library/e2d3c4b5-a698-7012-3456-789abcdef012` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Entitlement details retrieved",
    "data": {
        "id": "e2d3c4b5-a698-7012-3456-789abcdef012",
        "feature_key": "DIGITAL_COURSE_01",
        "status": "ACTIVE"
    }
}
```

---

### 46. Get Signed Access URL
- **Method**: `GET`
- **Endpoint**: `/api/v1/library/e2d3c4b5-a698-7012-3456-789abcdef012/access`
- **Category / Module**: Customer Catalog
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Get Signed Access URL on `/api/v1/library/e2d3c4b5-a698-7012-3456-789abcdef012/access` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/v1/library/e2d3c4b5-a698-7012-3456-789abcdef012/access` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Temporary access URL generated",
    "data": {
        "entitlement_id": "e2d3c4b5-a698-7012-3456-789abcdef012",
        "access_method": "SIGNED_URL",
        "signed_url": "https://storage.googleapis.com/.../private_video.mp4?X-Amz-Signature=...",
        "expires_at": "2026-09-29T10:30:00Z"
    }
}
```

---

### 47. Internal Coin Credit (Engine API)
- **Method**: `POST`
- **Endpoint**: `/api/internal/v1/coins/credit`
- **Category / Module**: Internal Engine
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Internal Coin Credit (Engine API) on `/api/internal/v1/coins/credit` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/internal/v1/coins/credit` with active Bearer token.
- **Headers**: `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Coins credited successfully",
    "data": {
        "amount": 5000,
        "balance_after": 130000,
        "bucket": "EARNED"
    }
}
```

---

### 48. Internal Coin Reversal (Engine API)
- **Method**: `POST`
- **Endpoint**: `/api/internal/v1/coins/reverse`
- **Category / Module**: Internal Engine
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Internal Coin Reversal (Engine API) on `/api/internal/v1/coins/reverse` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/internal/v1/coins/reverse` with active Bearer token.
- **Headers**: `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Coins reversed successfully",
    "data": {
        "amount": -5000,
        "balance_after": 125000
    }
}
```

---

### 49. List Notifications
- **Method**: `GET`
- **Endpoint**: `/api/v1/notifications?page=1&per_page=20`
- **Category / Module**: In-App Notifications
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for List Notifications on `/api/v1/notifications?page=1&per_page=20` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/v1/notifications?page=1&per_page=20` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Notifications retrieved",
    "data": {
        "current_page": 1,
        "data": [],
        "total": 0
    }
}
```

---

### 50. Get Notification Detail
- **Method**: `GET`
- **Endpoint**: `/api/v1/notifications/d3c4b5a6-9870-1234-5678-9abcdef01234`
- **Category / Module**: In-App Notifications
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Get Notification Detail on `/api/v1/notifications/d3c4b5a6-9870-1234-5678-9abcdef01234` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/v1/notifications/d3c4b5a6-9870-1234-5678-9abcdef01234` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Notification details retrieved",
    "data": {
        "id": "d3c4b5a6-9870-1234-5678-9abcdef01234",
        "event_key": "order.confirmed",
        "status": "SENT"
    }
}
```

---

### 51. Mark Notification as Read
- **Method**: `PATCH`
- **Endpoint**: `/api/v1/notifications/d3c4b5a6-9870-1234-5678-9abcdef01234/read`
- **Category / Module**: In-App Notifications
- **Developer Purpose & Deep Technical Overview**: Marks a specific notification as read by ID.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Trigger when user taps a notification tile.<br><strong>UI Handling:</strong> Dim card background and decrement unread counter.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Notification marked as read",
    "data": {
        "read": true
    }
}
```

---

### 52. Register Device Push Token
- **Method**: `POST`
- **Endpoint**: `/api/v1/devices`
- **Category / Module**: Customer Catalog
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Register Device Push Token on `/api/v1/devices` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/v1/devices` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Device registered successfully",
    "data": {
        "registered": true
    }
}
```

---

### 53. Delete Device Token
- **Method**: `DELETE`
- **Endpoint**: `/api/v1/devices/device_pixel_7_abc`
- **Category / Module**: Customer Catalog
- **Developer Purpose & Deep Technical Overview**: Handles DELETE operation for Delete Device Token on `/api/v1/devices/device_pixel_7_abc` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `DELETE /api/v1/devices/device_pixel_7_abc` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Device token removed",
    "data": {
        "removed": true
    }
}
```

---

### 54. Inbound Courier Tracking Webhook
- **Method**: `POST`
- **Endpoint**: `/api/v1/webhooks/courier/tracking`
- **Category / Module**: Webhooks
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Inbound Courier Tracking Webhook on `/api/v1/webhooks/courier/tracking` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/v1/webhooks/courier/tracking` with active Bearer token.
- **Headers**: `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Courier webhook processed",
    "data": {
        "processed": true
    }
}
```

---

### 55. WhatsApp Delivery Status Webhook
- **Method**: `POST`
- **Endpoint**: `/api/v1/webhooks/whatsapp/status`
- **Category / Module**: Webhooks
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for WhatsApp Delivery Status Webhook on `/api/v1/webhooks/whatsapp/status` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/v1/webhooks/whatsapp/status` with active Bearer token.
- **Headers**: `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "data": {
        "status": "acknowledged"
    }
}
```

---

### 56. Email Delivery Webhook
- **Method**: `POST`
- **Endpoint**: `/api/v1/webhooks/email/status`
- **Category / Module**: Webhooks
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Email Delivery Webhook on `/api/v1/webhooks/email/status` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/v1/webhooks/email/status` with active Bearer token.
- **Headers**: `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "data": {
        "status": "acknowledged"
    }
}
```

---

### 57. SMS Delivery Webhook
- **Method**: `POST`
- **Endpoint**: `/api/v1/webhooks/sms/status`
- **Category / Module**: Webhooks
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for SMS Delivery Webhook on `/api/v1/webhooks/sms/status` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/v1/webhooks/sms/status` with active Bearer token.
- **Headers**: `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "data": {
        "status": "acknowledged"
    }
}
```

---

### 58. Create Support Ticket
- **Method**: `POST`
- **Endpoint**: `/api/v1/support/tickets`
- **Category / Module**: Support Tickets
- **Developer Purpose & Deep Technical Overview**: Creates a new customer support ticket with subject, category, description, order link, and attachments.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Trigger from 'Contact Support' form.<br><strong>UI Handling:</strong> Submit form and navigate to the ticket chat thread.
- **Headers**: `Authorization: Bearer <TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Support ticket created successfully",
    "data": {
        "id": "c4b5a698-7012-3456-789a-bcdef0123456",
        "ticket_no": "TICK-987456",
        "status": "OPEN"
    }
}
```

---

### 59. List User Support Tickets
- **Method**: `GET`
- **Endpoint**: `/api/v1/support/tickets?page=1`
- **Category / Module**: Support Tickets
- **Developer Purpose & Deep Technical Overview**: Retrieves customer support tickets submitted by the peer with status (OPEN, IN_PROGRESS, RESOLVED).
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Call on Helpdesk / Support screen.<br><strong>UI Handling:</strong> Render ticket cards with subject, ID, and status tag.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Support tickets retrieved",
    "data": {
        "current_page": 1,
        "data": [],
        "total": 0
    }
}
```

---

### 60. Get Support Ticket Thread
- **Method**: `GET`
- **Endpoint**: `/api/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456`
- **Category / Module**: Support Tickets
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Get Support Ticket Thread on `/api/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Support ticket details retrieved",
    "data": {
        "id": "c4b5a698-7012-3456-789a-bcdef0123456",
        "ticket_no": "TICK-987456",
        "messages": []
    }
}
```

---

### 61. Send Message in Support Ticket
- **Method**: `POST`
- **Endpoint**: `/api/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456/messages`
- **Category / Module**: Support Tickets
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Send Message in Support Ticket on `/api/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456/messages` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456/messages` with active Bearer token.
- **Headers**: `Authorization: Bearer <TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Message sent successfully",
    "data": {
        "body": "Any update on this?"
    }
}
```

---

### 62. Close Support Ticket
- **Method**: `POST`
- **Endpoint**: `/api/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456/close`
- **Category / Module**: Support Tickets
- **Developer Purpose & Deep Technical Overview**: Allows peer to mark a support ticket as resolved and close the thread.
- **Flutter / Frontend Integration Guide**: <strong>When to Call:</strong> Trigger on 'Close Ticket' button.<br><strong>UI Handling:</strong> Update status badge to RESOLVED and disable composer.
- **Headers**: `Authorization: Bearer <TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Ticket closed successfully",
    "data": {
        "status": "CLOSED"
    }
}
```

---

### 63. List Published Store Policies
- **Method**: `GET`
- **Endpoint**: `/api/v1/store/policies`
- **Category / Module**: Public Policies
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for List Published Store Policies on `/api/v1/store/policies` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/v1/store/policies` with active Bearer token.
- **Headers**: `None`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Published policies retrieved",
    "data": [
        {
            "key": "return-refund",
            "title": "Return & Refund Policy",
            "version": 1
        }
    ]
}
```

---

### 64. Get Policy by Key
- **Method**: `GET`
- **Endpoint**: `/api/v1/store/policies/return-refund`
- **Category / Module**: Public Policies
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Get Policy by Key on `/api/v1/store/policies/return-refund` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/v1/store/policies/return-refund` with active Bearer token.
- **Headers**: `None`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Policy retrieved successfully",
    "data": {
        "key": "return-refund",
        "title": "Return & Refund Policy",
        "content": "Returns must be made within 7 days..."
    }
}
```

---

### 65. Admin Dashboard Metrics
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/dashboard`
- **Category / Module**: Admin Store Module
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Admin Dashboard Metrics on `/api/admin/v1/dashboard` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/dashboard` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Admin store dashboard metrics calculated successfully",
    "data": {
        "orders_summary": {
            "total_orders_placed": 1248,
            "orders_today": 34,
            "orders_pending_fulfillment": 18,
            "orders_shipped": 42,
            "orders_delivered": 1140,
            "orders_cancelled": 14
        },
        "coin_flow_summary": {
            "total_coins_redeemed": 54800000,
            "earned_coins_redeemed": 41200000,
            "bonus_coins_redeemed": 13600000,
            "average_order_coins": 43910,
            "total_refunded_coins": 630000
        },
        "inventory_alerts": {
            "total_active_skus": 84,
            "low_stock_skus_count": 6,
            "out_of_stock_skus_count": 2
        },
        "support_and_returns": {
            "open_support_tickets": 5,
            "pending_return_inspections": 3,
            "maker_checker_adjustments_pending": 2
        },
        "generated_at": "2026-10-01T15:00:00.000000Z"
    }
}
```

---

### 66. Admin Dashboard Summary Metrics
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/dashboard/summary?from=2026-09-01&to=2026-09-30`
- **Category / Module**: Admin Store Module
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Admin Dashboard Summary Metrics on `/api/admin/v1/dashboard/summary?from=2026-09-01&to=2026-09-30` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/dashboard/summary?from=2026-09-01&to=2026-09-30` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Summary metrics retrieved",
    "data": {
        "sales": {
            "total_orders": 45,
            "total_sales_coins": 1850000
        },
        "redemptions": {
            "total_coins_redeemed": 1850000
        },
        "issuance": {
            "total_coins_issued": 2500000
        }
    }
}
```

---

### 67. Admin Pending Operational Actions
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/dashboard/pending-actions`
- **Category / Module**: Admin Store Module
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Admin Pending Operational Actions on `/api/admin/v1/dashboard/pending-actions` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/dashboard/pending-actions` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Pending operational actions retrieved",
    "data": {
        "pending_orders": 3,
        "stuck_orders": 0,
        "pending_returns": 1,
        "pending_adjustments": 1,
        "low_stock_products": 2
    }
}
```

---

### 68. Admin List Categories
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/categories`
- **Category / Module**: Admin Categories
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Retrieves all categories including inactive ones with sequence ordering and product counts.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Power the Categories management table with drag-and-drop sort.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "All categories retrieved",
    "data": {
        "id": "01a0ecd1-1d7f-70ab-885a-020f273de747",
        "status": "SUCCESS",
        "reference_id": "ref_6abe374b232d5",
        "processed_at": "2026-10-01T10:00:00.000000Z"
    }
}
```

---

### 69. Admin Create Category
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/categories`
- **Category / Module**: Admin Categories
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Creates a new merchandise category with custom name, slug, image, sequence order, and visibility toggle.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Submit from 'Add Category' modal dialog.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Category created successfully",
    "data": {
        "id": "b5a69870-1234-5678-9abc-def012345678",
        "name": "Electronics & Gadgets"
    }
}
```

---

### 70. Admin Update Category
- **Method**: `PUT`
- **Endpoint**: `/api/admin/v1/categories/b5a69870-1234-5678-9abc-def012345678`
- **Category / Module**: Admin Categories
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Updates category title, slug, image URL, sequence order, and visibility state.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Save modifications on category edit form.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Category updated successfully",
    "data": {
        "id": "b5a69870-1234-5678-9abc-def012345678",
        "name": "Electronics & Smart Devices"
    }
}
```

---

### 71. Admin Delete Category
- **Method**: `DELETE`
- **Endpoint**: `/api/admin/v1/categories/b5a69870-1234-5678-9abc-def012345678`
- **Category / Module**: Admin Categories
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Deletes or archives a category if no active products are attached.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Trigger from delete category confirmation.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Category deleted",
    "data": {
        "deleted": true
    }
}
```

---

### 72. Admin List Products
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/products?search=polo&status=ACTIVE&page=1`
- **Category / Module**: Admin Products
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Paginated master list of all products with stock counts, variant totals, visibility status, and coin prices.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Main Admin Products table with multi-column filtering and bulk actions.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Admin products retrieved",
    "data": {
        "current_page": 1,
        "data": [],
        "total": 0
    }
}
```

---

### 73. Admin Create Product
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/products`
- **Category / Module**: Admin Products
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Creates a new product record with base specs, description, category ID, and delivery flags.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Trigger from Admin 'Add Product' wizard.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Product created successfully",
    "data": {
        "id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
        "name": "Peers Premium Polo T-Shirt",
        "price_coins": 50000
    }
}
```

---

### 74. Admin Get Product Detail
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33`
- **Category / Module**: Admin Products
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Admin Get Product Detail on `/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Product retrieved",
    "data": {
        "id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
        "name": "Peers Premium Polo T-Shirt"
    }
}
```

---

### 75. Admin Update Product
- **Method**: `PUT`
- **Endpoint**: `/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33`
- **Category / Module**: Admin Products
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Updates product title, description, category mapping, and base pricing.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Save edits on Product Edit form.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Product updated successfully",
    "data": {
        "id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
        "price_coins": 48000
    }
}
```

---

### 76. Admin Disable Product
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/disable`
- **Category / Module**: Admin Products
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Admin Disable Product on `/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/disable` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/disable` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Product disabled successfully",
    "data": {
        "status": "INACTIVE"
    }
}
```

---

### 77. Admin Add Product Image
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/images`
- **Category / Module**: Admin Products
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Admin Add Product Image on `/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/images` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/images` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Image added successfully",
    "data": {
        "id": "a6987012-3456-789a-bcde-f0123456789a",
        "is_primary": true
    }
}
```

---

### 78. Admin Delete Product Image
- **Method**: `DELETE`
- **Endpoint**: `/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/images/a6987012-3456-789a-bcde-f0123456789a`
- **Category / Module**: Admin Products
- **Developer Purpose & Deep Technical Overview**: Handles DELETE operation for Admin Delete Product Image on `/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/images/a6987012-3456-789a-bcde-f0123456789a` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `DELETE /api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/images/a6987012-3456-789a-bcde-f0123456789a` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Image removed",
    "data": {
        "deleted": true
    }
}
```

---

### 79. Admin Create Product Variant
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/variants`
- **Category / Module**: Admin Variants
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Creates a new SKU variant with SKU code, attributes, coin price, and initial stock quantity.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Trigger from 'Add Variant' modal.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Variant created successfully",
    "data": {
        "id": "935d6a66-837e-40c0-9971-aadda2db131e",
        "name": "Black - XL",
        "stock_qty": 40
    }
}
```

---

### 80. Admin Update Variant
- **Method**: `PUT`
- **Endpoint**: `/api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e`
- **Category / Module**: Admin Variants
- **Developer Purpose & Deep Technical Overview**: Handles PUT operation for Admin Update Variant on `/api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `PUT /api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Variant updated successfully",
    "data": {
        "id": "935d6a66-837e-40c0-9971-aadda2db131e",
        "coin_price": 52000
    }
}
```

---

### 81. Admin Manual Stock Adjustment
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e/stock-adjustment`
- **Category / Module**: Admin Variants
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Admin Manual Stock Adjustment on `/api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e/stock-adjustment` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e/stock-adjustment` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Stock adjusted successfully",
    "data": {
        "variant": {
            "stock_qty": 90
        },
        "movement": {
            "quantity_change": 50,
            "quantity_after": 90
        }
    }
}
```

---

### 82. Admin Variant Inventory Movements
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e/inventory`
- **Category / Module**: Admin Variants
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Admin Variant Inventory Movements on `/api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e/inventory` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e/inventory` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Inventory movements history retrieved",
    "data": {
        "current_page": 1,
        "data": [],
        "total": 0
    }
}
```

---

### 83. Admin List Orders
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/orders?order_no=ORD&status=CONFIRMED&page=1`
- **Category / Module**: Admin Orders
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Master admin orders table with comprehensive filters by status, peer name, date range, and coin bucket.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Main Admin Orders management view.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Admin orders retrieved",
    "data": {
        "current_page": 1,
        "data": [],
        "total": 0
    }
}
```

---

### 84. Admin Get Order Detail
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed`
- **Category / Module**: Admin Orders
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Admin Get Order Detail on `/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Order retrieved",
    "data": {
        "id": "21098765-4321-0fed-cba9-876543210fed",
        "order_no": "ORD-123456"
    }
}
```

---

### 85. Admin Update Order Status
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/status`
- **Category / Module**: Admin Orders
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Advances order status through workflow (PLACED -> PROCESSING -> SHIPPED -> DELIVERED).
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Order status dropdown action.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Order status updated successfully",
    "data": {
        "status": "PACKED"
    }
}
```

---

### 86. Admin Add Note to Order
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/notes`
- **Category / Module**: Admin Orders
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Admin Add Note to Order on `/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/notes` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/notes` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Note added to order",
    "data": {
        "notes": "Customer requested gift wrap."
    }
}
```

---

### 87. Admin Get Packing Slip
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/packing-slip`
- **Category / Module**: Admin Orders
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Admin Get Packing Slip on `/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/packing-slip` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/packing-slip` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Packing slip details retrieved",
    "data": {
        "order_no": "ORD-123456",
        "shipping_address": []
    }
}
```

---

### 88. Admin Dispatch & Add Shipment
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/shipment`
- **Category / Module**: Admin Orders
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Admin Dispatch & Add Shipment on `/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/shipment` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/shipment` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Shipment created and order marked as shipped",
    "data": {
        "tracking_no": "DELH9876543210",
        "carrier": "Delhivery",
        "status": "DISPATCHED"
    }
}
```

---

### 89. Admin Verify Pickup Takeaway Code
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/pickup/verify`
- **Category / Module**: Admin Orders
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Admin Verify Pickup Takeaway Code on `/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/pickup/verify` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/pickup/verify` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Pickup code verified and order delivered",
    "data": {
        "verified": true
    }
}
```

---

### 90. Admin List Return Requests
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/returns?status=REQUESTED&page=1`
- **Category / Module**: Admin Returns
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Admin List Return Requests on `/api/admin/v1/returns?status=REQUESTED&page=1` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/returns?status=REQUESTED&page=1` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Admin returns retrieved",
    "data": {
        "current_page": 1,
        "data": [],
        "total": 0
    }
}
```

---

### 91. Admin Get Return Detail
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc`
- **Category / Module**: Admin Returns
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Admin Get Return Detail on `/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Return details retrieved",
    "data": {
        "id": "10987654-3210-fedc-ba98-76543210fedc",
        "return_no": "RET-981240"
    }
}
```

---

### 92. Admin Approve Return Request
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/approve`
- **Category / Module**: Admin Returns
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Admin Approve Return Request on `/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/approve` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/approve` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Return request approved",
    "data": {
        "status": "APPROVED"
    }
}
```

---

### 93. Admin Reject Return Request
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/reject`
- **Category / Module**: Admin Returns
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Admin Reject Return Request on `/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/reject` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/reject` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Return request rejected",
    "data": {
        "status": "REJECTED"
    }
}
```

---

### 94. Admin Mark Return Received
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/receive`
- **Category / Module**: Admin Returns
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Admin Mark Return Received on `/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/receive` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/receive` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Return item received at warehouse",
    "data": {
        "status": "RECEIVED"
    }
}
```

---

### 95. Admin Inspect Returned Item
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/inspect`
- **Category / Module**: Admin Returns
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Admin Inspect Returned Item on `/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/inspect` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/inspect` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Quality inspection recorded",
    "data": {
        "quality_check_passed": true,
        "status": "APPROVED"
    }
}
```

---

### 96. Admin Execute Return Refund
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/refund`
- **Category / Module**: Admin Returns
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Admin Execute Return Refund on `/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/refund` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/refund` with active Bearer token.
- **Headers**: `- `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Return refunded successfully",
    "data": {
        "refund_no": "REF-987654",
        "refund_coins": 90000,
        "status": "COMPLETED"
    }
}
```

---

### 97. Admin List Peer Wallets
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/wallets?search=rajesh&page=1`
- **Category / Module**: Admin Wallets
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Admin List Peer Wallets on `/api/admin/v1/wallets?search=rajesh&page=1` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/wallets?search=rajesh&page=1` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Peer wallets retrieved",
    "data": {
        "current_page": 1,
        "data": [],
        "total": 0
    }
}
```

---

### 98. Admin Single Peer Wallet Detail
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33`
- **Category / Module**: Admin Wallets
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Admin Single Peer Wallet Detail on `/api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Wallet details retrieved",
    "data": {
        "balance": 125000,
        "earned_balance": 100000,
        "bonus_balance": 25000,
        "wallet_state": "ACTIVE"
    }
}
```

---

### 99. Admin Freeze Peer Wallet
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33/freeze`
- **Category / Module**: Admin Wallets
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Admin Freeze Peer Wallet on `/api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33/freeze` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33/freeze` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Wallet frozen successfully",
    "data": {
        "wallet_state": "FROZEN"
    }
}
```

---

### 100. Admin Unfreeze Peer Wallet
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33/unfreeze`
- **Category / Module**: Admin Wallets
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Admin Unfreeze Peer Wallet on `/api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33/unfreeze` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33/unfreeze` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Wallet unfrozen successfully",
    "data": {
        "wallet_state": "ACTIVE"
    }
}
```

---

### 101. Admin List Wallet Adjustment Requests
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/wallet-adjustments?status=PENDING&page=1`
- **Category / Module**: Admin Store Module
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Admin List Wallet Adjustment Requests on `/api/admin/v1/wallet-adjustments?status=PENDING&page=1` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/wallet-adjustments?status=PENDING&page=1` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Wallet adjustment requests retrieved",
    "data": {
        "current_page": 1,
        "data": [],
        "total": 0
    }
}
```

---

### 102. Admin Create Wallet Adjustment Request (Maker)
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/wallet-adjustments`
- **Category / Module**: Admin Store Module
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Admin Create Wallet Adjustment Request (Maker) on `/api/admin/v1/wallet-adjustments` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/wallet-adjustments` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Wallet adjustment request created (pending maker-checker approval)",
    "data": {
        "id": "98701234-5678-9abc-def0-123456789abc",
        "status": "PENDING"
    }
}
```

---

### 103. Admin Approve Wallet Adjustment (Checker)
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/wallet-adjustments/98701234-5678-9abc-def0-123456789abc/approve`
- **Category / Module**: Admin Store Module
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Admin Approve Wallet Adjustment (Checker) on `/api/admin/v1/wallet-adjustments/98701234-5678-9abc-def0-123456789abc/approve` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/wallet-adjustments/98701234-5678-9abc-def0-123456789abc/approve` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN_CHECKER>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Wallet adjustment approved and executed successfully",
    "data": {
        "new_balance": 145000
    }
}
```

---

### 104. Admin Reject Wallet Adjustment
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/wallet-adjustments/98701234-5678-9abc-def0-123456789abc/reject`
- **Category / Module**: Admin Store Module
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Admin Reject Wallet Adjustment on `/api/admin/v1/wallet-adjustments/98701234-5678-9abc-def0-123456789abc/reject` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/wallet-adjustments/98701234-5678-9abc-def0-123456789abc/reject` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Wallet adjustment request rejected",
    "data": {
        "status": "REJECTED"
    }
}
```

---

### 105. Admin List Bonus Grants
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/bonus-grants`
- **Category / Module**: Admin Store Module
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Admin List Bonus Grants on `/api/admin/v1/bonus-grants` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/bonus-grants` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Wallet adjustment requests retrieved",
    "data": {
        "current_page": 1,
        "data": [],
        "total": 0
    }
}
```

---

### 106. Admin Create Bonus Grant Request
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/bonus-grants`
- **Category / Module**: Admin Store Module
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Admin Create Bonus Grant Request on `/api/admin/v1/bonus-grants` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/bonus-grants` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Bonus coin grant created (pending maker-checker approval)",
    "data": {
        "id": "87654321-0fed-cba9-8765-43210fedcba9",
        "status": "PENDING"
    }
}
```

---

### 107. Admin Approve Bonus Grant
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/bonus-grants/87654321-0fed-cba9-8765-43210fedcba9/approve`
- **Category / Module**: Admin Store Module
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Admin Approve Bonus Grant on `/api/admin/v1/bonus-grants/87654321-0fed-cba9-8765-43210fedcba9/approve` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/bonus-grants/87654321-0fed-cba9-8765-43210fedcba9/approve` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN_CHECKER>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Wallet adjustment approved and executed successfully",
    "data": {
        "new_balance": 135000
    }
}
```

---

### 108. Admin Reject Bonus Grant
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/bonus-grants/87654321-0fed-cba9-8765-43210fedcba9/reject`
- **Category / Module**: Admin Store Module
- **Developer Purpose & Deep Technical Overview**: Handles POST operation for Admin Reject Bonus Grant on `/api/admin/v1/bonus-grants/87654321-0fed-cba9-8765-43210fedcba9/reject` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/bonus-grants/87654321-0fed-cba9-8765-43210fedcba9/reject` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Wallet adjustment request rejected",
    "data": {
        "status": "REJECTED"
    }
}
```

---

### 109. Admin List Membership Plans
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/membership/plans`
- **Category / Module**: Admin Store Module
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Lists all store membership tiers and VIP subscription plans configured in the system.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Membership Plans table.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Admin membership plans retrieved",
    "data": {
        "id": "01a0ecd1-1d7f-70ab-885a-020f273de747",
        "status": "SUCCESS",
        "reference_id": "ref_6abe374b233aa",
        "processed_at": "2026-10-01T10:00:00.000000Z"
    }
}
```

---

### 110. Admin Create Membership Plan
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/membership/plans`
- **Category / Module**: Admin Store Module
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Creates a new store membership plan with coin price, duration days, and privilege perks.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> 'Create Membership Plan' modal.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Membership plan created",
    "data": {
        "id": "f1e2d3c4-b5a6-9870-1234-56789abcdef0",
        "name": "12 Months Unity Global Peer"
    }
}
```

---

### 111. Admin Update Membership Plan
- **Method**: `PUT`
- **Endpoint**: `/api/admin/v1/membership/plans/f1e2d3c4-b5a6-9870-1234-56789abcdef0`
- **Category / Module**: Admin Store Module
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Updates membership plan pricing, perk description, or active status.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Edit Plan modal.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Membership plan updated",
    "data": {
        "price_coins": 240000
    }
}
```

---

### 112. Admin View Peer Membership Ledger
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/membership/ledger/a05ceb9e-2747-4dba-841d-63da4d725c33`
- **Category / Module**: Admin Store Module
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Admin View Peer Membership Ledger on `/api/admin/v1/membership/ledger/a05ceb9e-2747-4dba-841d-63da4d725c33` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/membership/ledger/a05ceb9e-2747-4dba-841d-63da4d725c33` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "User membership ledger retrieved",
    "data": {
        "id": "01a0ecd1-1d7f-70ab-885a-020f273de747",
        "status": "SUCCESS",
        "reference_id": "ref_6abe374b233c3",
        "processed_at": "2026-10-01T10:00:00.000000Z"
    }
}
```

---

### 113. Admin List Entitlements
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/entitlements?page=1`
- **Category / Module**: Admin Digital Assets
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Admin List Entitlements on `/api/admin/v1/entitlements?page=1` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/entitlements?page=1` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Admin entitlements retrieved",
    "data": {
        "current_page": 1,
        "data": [],
        "total": 0
    }
}
```

---

### 114. Admin Get Entitlement Detail
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/entitlements/e2d3c4b5-a698-7012-3456-789abcdef012`
- **Category / Module**: Admin Digital Assets
- **Developer Purpose & Deep Technical Overview**: Handles GET operation for Admin Get Entitlement Detail on `/api/admin/v1/entitlements/e2d3c4b5-a698-7012-3456-789abcdef012` with authentication and authorization checks.
- **Flutter / Frontend Integration Guide**: <strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/entitlements/e2d3c4b5-a698-7012-3456-789abcdef012` with active Bearer token.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Entitlement details retrieved",
    "data": {
        "id": "e2d3c4b5-a698-7012-3456-789abcdef012"
    }
}
```

---

### 115. Admin Manually Grant Entitlement
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/entitlements/grant`
- **Category / Module**: Admin Digital Assets
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Manually grants a digital entitlement or eBook license to a specific peer without coin deduction.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> 'Grant Entitlement' action modal in user profile.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Entitlement granted successfully",
    "data": {
        "id": "e2d3c4b5-a698-7012-3456-789abcdef012",
        "status": "ACTIVE"
    }
}
```

---

### 116. Admin Revoke Entitlement
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/entitlements/e2d3c4b5-a698-7012-3456-789abcdef012/revoke`
- **Category / Module**: Admin Digital Assets
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Revokes an active digital library entitlement or asset license from a user account.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> 'Revoke License' button in user asset entitlements table.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Entitlement revoked successfully",
    "data": {
        "status": "REVOKED"
    }
}
```

---

### 117. Admin Notification Templates List
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/notifications/templates`
- **Category / Module**: Admin Notifications
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Lists all in-app and push notification templates configured for automated system triggers.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Notification Templates table.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Notification templates retrieved",
    "data": {
        "id": "01a0ecd1-1d7f-70ab-885a-020f273de747",
        "status": "SUCCESS",
        "reference_id": "ref_6abe374b233e8",
        "processed_at": "2026-10-01T10:00:00.000000Z"
    }
}
```

---

### 118. Admin Get Notification Template
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/notifications/templates/d3c4b5a6-9870-1234-5678-9abcdef01234`
- **Category / Module**: Admin Notifications
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Retrieves the configuration and text template body of a specific notification template by ID.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Notification Template inspector view.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Template retrieved",
    "data": {
        "id": "01a0ecd1-1d7f-70ab-885a-020f273de747",
        "status": "SUCCESS",
        "reference_id": "ref_6abe374b233ef",
        "processed_at": "2026-10-01T10:00:00.000000Z"
    }
}
```

---

### 119. Admin Notification Logs
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/notifications/logs?page=1`
- **Category / Module**: Admin Notifications
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Retrieves the paginated audit log of dispatched push/SMS/in-app notifications with delivery status.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Dispatched Notification Logs table.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Notification logs retrieved",
    "data": {
        "current_page": 1,
        "data": [],
        "total": 0
    }
}
```

---

### 120. Admin Resend Notification Log
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/notifications/logs/87012345-6789-abcd-ef01-23456789abcd/resend`
- **Category / Module**: Admin Notifications
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Retries or resends a failed notification log record to the recipient device.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> 'Resend Notification' action button in log details.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Notification queued for resending",
    "data": {
        "status": "RETRYING"
    }
}
```

---

### 121. Admin List Support Tickets
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/support/tickets?status=OPEN&page=1`
- **Category / Module**: Admin Support Helpdesk
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Master admin helpdesk table of all submitted customer support tickets filtered by urgency, department, and status.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Admin Helpdesk Dashboard.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Admin support tickets retrieved",
    "data": {
        "current_page": 1,
        "data": [],
        "total": 0
    }
}
```

---

### 122. Admin Get Support Ticket Thread
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456`
- **Category / Module**: Admin Support Helpdesk
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Fetches the complete support ticket thread file including peer profile and full conversation history.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Admin Support Ticket view.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Ticket details retrieved",
    "data": {
        "ticket_no": "TICK-987456",
        "messages": []
    }
}
```

---

### 123. Admin Assign Support Ticket
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456/assign`
- **Category / Module**: Admin Support Helpdesk
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Assigns a support ticket to a designated administrative agent or department.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Staff assignment dropdown selector.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Ticket assigned successfully",
    "data": {
        "assigned_to": "a05ceb9e-2747-4dba-841d-63da4d725c33",
        "status": "IN_PROGRESS"
    }
}
```

---

### 124. Admin Reply to Support Ticket
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456/messages`
- **Category / Module**: Admin Support Helpdesk
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Posts an administrative reply message to a peer support ticket thread.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Admin message composer in ticket conversation view.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Reply posted successfully",
    "data": {
        "body": "Hello, we have tracked your parcel and it will be delivered today."
    }
}
```

---

### 125. Admin Resolve Support Ticket
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456/resolve`
- **Category / Module**: Admin Support Helpdesk
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Marks a customer support ticket as resolved and closes the ticket thread.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> 'Resolve & Close Ticket' button.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Ticket marked as resolved",
    "data": {
        "status": "RESOLVED"
    }
}
```

---

### 126. Admin List Store Configs
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/config`
- **Category / Module**: Admin Store Configuration
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Retrieves the complete dictionary of store configuration parameters from `store_configs`.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Store Configuration master panel.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "All store configs retrieved",
    "data": {
        "id": "01a0ecd1-1d7f-70ab-885a-020f273de747",
        "status": "SUCCESS",
        "reference_id": "ref_6abe374b2342f",
        "processed_at": "2026-10-01T10:00:00.000000Z"
    }
}
```

---

### 127. Admin Get Store Config
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/config/min_delivery_order_coins`
- **Category / Module**: Admin Store Configuration
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Retrieves the value and metadata of a specific store configuration key (e.g. `min_delivery_order_coins`).
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Config key detail view.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Store config retrieved",
    "data": {
        "config_key": "min_delivery_order_coins",
        "config_value": 100000
    }
}
```

---

### 128. Admin Update Store Config
- **Method**: `PUT`
- **Endpoint**: `/api/admin/v1/config/min_delivery_order_coins`
- **Category / Module**: Admin Store Configuration
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Updates the value of a specific store configuration parameter in `store_configs`.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Save Config value button.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Store config updated successfully",
    "data": {
        "config_key": "min_delivery_order_coins",
        "config_value": 100000
    }
}
```

---

### 129. Admin List Policies
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/policies`
- **Category / Module**: Admin Policies
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Lists all legal policies, Terms & Conditions, and compliance documents in the system.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Policies Management table.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Admin policies retrieved",
    "data": {
        "id": "01a0ecd1-1d7f-70ab-885a-020f273de747",
        "status": "SUCCESS",
        "reference_id": "ref_6abe374b2344a",
        "processed_at": "2026-10-01T10:00:00.000000Z"
    }
}
```

---

### 130. Admin Create Policy
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/policies`
- **Category / Module**: Admin Policies
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Creates a new policy draft with rich markdown body text and version label.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> 'Create Policy Draft' editor modal.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Policy created",
    "data": {
        "id": "70123456-789a-bcde-f012-3456789abcde",
        "version": 2
    }
}
```

---

### 131. Admin Update Policy
- **Method**: `PUT`
- **Endpoint**: `/api/admin/v1/policies/70123456-789a-bcde-f012-3456789abcde`
- **Category / Module**: Admin Policies
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Updates an existing policy draft content, title, or summary notes.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Save Policy Draft button.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Policy updated",
    "data": {
        "title": "Peers Store Return & Refund Policy (v2.1)"
    }
}
```

---

### 132. Admin Publish Policy Version
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/policies/70123456-789a-bcde-f012-3456789abcde/publish`
- **Category / Module**: Admin Policies
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Formally publishes a policy version, making it live and visible across customer mobile apps.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> 'Publish Version' confirmation action.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Policy version published",
    "data": {
        "status": "PUBLISHED"
    }
}
```

---

### 133. Admin Sales Report
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/reports/sales?from=2026-09-01&to=2026-09-30`
- **Category / Module**: Admin Reports & Analytics
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Generates aggregate sales and financial metrics (total orders, total coins redeemed, average order value in coins) over a custom date window.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Sales & Financial KPI cards and charts.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Sales reconciliation report generated successfully",
    "data": {
        "report_range": {
            "from": "2026-09-01T00:00:00.000000Z",
            "to": "2026-09-30T23:59:59.000000Z"
        },
        "totals": {
            "total_orders": 342,
            "total_units_sold": 512,
            "gross_sales_coins": 18450000,
            "delivery_charges_coins": 680000,
            "discounts_coins": 120000,
            "net_sales_coins": 19010000,
            "average_order_value_coins": 55584
        },
        "sales_by_category": [
            {
                "category_name": "Apparel & Merchandise",
                "orders_count": 210,
                "coins_collected": 12400000,
                "percentage": 65.2
            },
            {
                "category_name": "Executive Office & Stationery",
                "orders_count": 98,
                "coins_collected": 4800000,
                "percentage": 25.3
            },
            {
                "category_name": "Digital Masterclasses",
                "orders_count": 34,
                "coins_collected": 1810000,
                "percentage": 9.5
            }
        ]
    }
}
```

---

### 134. Admin Coin Redemption Report
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/reports/coin-redemption?from=2026-09-01&to=2026-09-30`
- **Category / Module**: Admin Reports & Analytics
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Generates coin redemption analytics report broken down by Earned Coins versus Bonus Coins expenditure.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Coin Redemption Breakdown donut chart.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Coin redemption report retrieved",
    "data": {
        "total_coins_redeemed": 1850000,
        "bonus_coins_redeemed": 600000,
        "earned_coins_redeemed": 1250000
    }
}
```

---

### 135. Admin Coin Issuance Report
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/reports/coin-issuance?from=2026-09-01&to=2026-09-30`
- **Category / Module**: Admin Reports & Analytics
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Generates coin issuance analytics report tracking total coins minted and awarded across chapters.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Coin Issuance trend bar chart.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Coin issuance report retrieved",
    "data": {
        "total_coins_issued": 2500000,
        "earned_issued": 2000000,
        "bonus_issued": 500000
    }
}
```

---

### 136. Admin Membership Renewals Report
- **Method**: `GET`
- **Endpoint**: `/api/admin/v1/reports/membership-renewals?from=2026-09-01&to=2026-09-30`
- **Category / Module**: Admin Reports & Analytics
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Generates VIP membership renewals and subscription coin revenue report over custom date ranges.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> Membership Subscriptions Performance report.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Membership renewals report retrieved",
    "data": {
        "total_renewals": 12,
        "total_coins_collected": 3000000
    }
}
```

---

### 137. Admin Export Reports (CSV / XLSX)
- **Method**: `POST`
- **Endpoint**: `/api/admin/v1/reports/export`
- **Category / Module**: Admin Reports & Analytics
- **Developer Purpose & Deep Technical Overview**: Admin endpoint: Triggers an asynchronous dataset export and returns a direct CSV download URL for comprehensive offline bookkeeping and auditing.
- **Flutter / Frontend Integration Guide**: <strong>Admin Web / Portal Integration:</strong> 'Download CSV Export' action link.
- **Headers**: `Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json`
- **Request Body**: `None`
- **Success Response (JSON)**:
```json
{
    "success": true,
    "message": "Report export generated",
    "data": {
        "export_job_id": "job_uuid_12345",
        "status": "COMPLETED",
        "download_url": "http://localhost:8000/api/admin/v1/reports/download/xyz987"
    }
}
```

---

