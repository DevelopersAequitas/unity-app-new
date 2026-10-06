# 🚀 Peers Global Unity — Complete Store & Coin Wallet API Documentation (137 APIs)

> **Local Base URL**: `http://localhost:8000/api`  
> **Live Base URL**: `https://api.peersglobalunity.com/api`  
> **Default Auth**: `Authorization: Bearer <SANCTUM_TOKEN>`  
> **Format**: `Content-Type: application/json`, `Accept: application/json`  

---

### 1. Store Configuration
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/v1/store/config`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/store/config`
- **Purpose & Description**: Get Store global flags, minimum app version requirement, user coin balance, delivery minimum & home banners.
- **Flutter / Frontend Usage**: Call during mobile app splash/home initial load or Wallet Screen. Populates store banners and shows coin breakdown (Earned vs Bonus).
- **Headers**: ``Authorization: Bearer <TOKEN>` (Optional)`
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Store configuration retrieved",
  "data": {
    "store_enabled": true,
    "minimum_app_version": "1.0.0",
    "current_app_version_supported": true,
    "coin_balance": 125000,
    "earned_coins": 100000,
    "bonus_coins": 25000,
    "delivery_minimum_coins": 100000,
    "pickup_available": true,
    "return_window_days": 7,
    "pickup_hold_days": 7,
    "banners": [
      {
        "id": "d0730dc3-3a80-4265-aeac-1dbe5eb3ac47",
        "title": "Exclusive Unity Merchandise",
        "image_url": "https://images.unsplash.com/photo-1523381210434-271e8be1f52b",
        "link_type": "PRODUCT",
        "link_value": "a05ceb9e-2747-4dba-841d-63da4d725c33"
      }
    ]
  }
}
```

---

### 2. Store Banners
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/v1/store/banners?page=1&per_page=10`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/store/banners?page=1&per_page=10`
- **Purpose & Description**: Get paginated list of active store banners for home carousel.
- **Flutter / Frontend Usage**: Call during mobile app splash/home initial load or Wallet Screen. Populates store banners and shows coin breakdown (Earned vs Bonus).
- **Headers**: ``None``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Active banners retrieved",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "d0730dc3-3a80-4265-aeac-1dbe5eb3ac47",
        "title": "Exclusive Unity Merchandise",
        "subtitle": "Wear your peer identity with pride",
        "image_url": "https://images.unsplash.com/photo-1523381210434-271e8be1f52b",
        "link_type": "CATEGORY",
        "link_value": "apparel-clothing"
      }
    ],
    "total": 1
  }
}
```

---

### 3. User Wallet Balance & Split
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/v1/wallet`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/wallet`
- **Purpose & Description**: Get total coin balance, Earned vs Bonus breakdown, lifetime spent, and wallet state (`ACTIVE`, `FROZEN`, `CLOSED`).
- **Flutter / Frontend Usage**: Call during mobile app splash/home initial load or Wallet Screen. Populates store banners and shows coin breakdown (Earned vs Bonus).
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Wallet details retrieved",
  "data": {
    "balance": 125000,
    "earned_balance": 100000,
    "bonus_balance": 25000,
    "lifetime_earned": 250000,
    "lifetime_spent": 125000,
    "lifetime_purchased": 0,
    "wallet_state": "ACTIVE",
    "wallet_version": 14
  }
}
```

---

### 4. Wallet Ledger History
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/v1/wallet/ledger?bucket=BONUS&reference_type=ORDER&page=1&per_page=20`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/wallet/ledger?bucket=BONUS&reference_type=ORDER&page=1&per_page=20`
- **Purpose & Description**: Get user coin transaction passbook with filters.
- **Flutter / Frontend Usage**: Call during mobile app splash/home initial load or Wallet Screen. Populates store banners and shows coin breakdown (Earned vs Bonus).
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Wallet ledger history retrieved",
  "data": {
    "current_page": 1,
    "data": [
      {
        "transaction_id": "a1b2c3d4-e5f6-7890-1234-56789abcdef0",
        "amount": -25000,
        "balance_after": 100000,
        "bucket": "BONUS",
        "reference_type": "ORDER",
        "reference_id": "21098765-4321-0fed-cba9-876543210fed",
        "reference": "Order #ORD-982147",
        "created_at": "2026-09-29T10:00:00Z"
      }
    ],
    "total": 1
  }
}
```

---

### 5. Wallet Monthly Summary
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/v1/wallet/summary`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/wallet/summary`
- **Purpose & Description**: Get user's current month spent vs earned coins summary.
- **Flutter / Frontend Usage**: Call in Store Catalog & Search screens. Render horizontal category chips, product list view, search filtering, and detailed product page.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Wallet monthly summary retrieved",
  "data": {
    "current_balance": 125000,
    "earned": 100000,
    "bonus": 25000,
    "spent_this_month": 45000,
    "earned_this_month": 12000
  }
}
```

---

### 6. Wallet Status & Freeze Info
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/v1/wallet/status`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/wallet/status`
- **Purpose & Description**: Check if wallet is frozen or closed, along with freeze date and reason.
- **Flutter / Frontend Usage**: Call in Store Catalog & Search screens. Render horizontal category chips, product list view, search filtering, and detailed product page.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Wallet status retrieved",
  "data": {
    "wallet_state": "ACTIVE",
    "frozen": false,
    "frozen_at": null,
    "frozen_reason": null
  }
}
```

---

### 7. Store Categories
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/v1/store/categories`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/store/categories`
- **Purpose & Description**: Get active categories list with active product count in each.
- **Flutter / Frontend Usage**: Call in Store Catalog & Search screens. Render horizontal category chips, product list view, search filtering, and detailed product page.
- **Headers**: ``None``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Active categories retrieved",
  "data": [
    {
      "id": "b5a69870-1234-5678-9abc-def012345678",
      "name": "Merchandise & Apparel",
      "slug": "apparel-clothing-apparel",
      "image_url": "https://...",
      "products_count": 8
    }
  ]
}
```

---

### 8. Product Listing & Filtering
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/v1/store/products?category_id=84582fbc-a518-49e7-a249-169b42362bec&type=PHYSICAL&min_coins=1000&max_coins=100000&sort=price_asc&page=1&per_page=20`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/store/products?category_id=84582fbc-a518-49e7-a249-169b42362bec&type=PHYSICAL&min_coins=1000&max_coins=100000&sort=price_asc&page=1&per_page=20`
- **Purpose & Description**: Filter products by category, price, type, featured status, sorting, and pagination.
- **Flutter / Frontend Usage**: Call in Store Catalog & Search screens. Render horizontal category chips, product list view, search filtering, and detailed product page.
- **Headers**: ``None``
- **Request Body**: `None`
- **Response (JSON)**:
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
        "coin_price": 45000,
        "is_featured": true,
        "primary_image": {
          "image_url": "https://..."
        }
      }
    ],
    "total": 1
  }
}
```

---

### 9. Product Details
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/v1/store/products/a05ceb9e-2747-4dba-841d-63da4d725c33`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/store/products/a05ceb9e-2747-4dba-841d-63da4d725c33`
- **Purpose & Description**: Get complete product detail including all images, variants, stock, and approved reviews.
- **Flutter / Frontend Usage**: Call in Store Catalog & Search screens. Render horizontal category chips, product list view, search filtering, and detailed product page.
- **Headers**: ``None``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Product details retrieved",
  "data": {
    "id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
    "name": "Peers Official Polo T-Shirt",
    "sku": "POLO-001",
    "type": "PHYSICAL",
    "coin_price": 45000,
    "description": "Premium 100% cotton polo t-shirt.",
    "delivery_modes": ["DELIVERY", "PICKUP"],
    "return_allowed": true,
    "customised": false,
    "stock_qty": 50,
    "images": [
      { "image_url": "https://...", "is_primary": true }
    ],
    "variants": [
      { "id": "935d6a66-837e-40c0-9971-aadda2db131e", "name": "Black - L", "coin_price": 45000, "stock_qty": 30 }
    ],
    "approved_reviews": []
  }
}
```

---

### 10. Featured Products
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/v1/store/products/featured`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/store/products/featured`
- **Purpose & Description**: Get top 10 featured products for store home page.
- **Flutter / Frontend Usage**: Call in Store Catalog & Search screens. Render horizontal category chips, product list view, search filtering, and detailed product page.
- **Headers**: ``None``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Featured products retrieved",
  "data": [
    {
      "id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
      "name": "Peers Official Polo T-Shirt",
      "coin_price": 45000,
      "primary_image": { "image_url": "https://..." }
    }
  ]
}
```

---

### 11. Search Products
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/v1/store/search?q=tshirt&category_id=84582fbc-a518-49e7-a249-169b42362bec&page=1`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/store/search?q=tshirt&category_id=84582fbc-a518-49e7-a249-169b42362bec&page=1`
- **Purpose & Description**: Search products by name, description, brand, or SKU.
- **Flutter / Frontend Usage**: Call in Shopping Cart screen. Manage items quantity, remove items, verify real-time stock, and calculate subtotal.
- **Headers**: ``None``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/cart`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/cart`
- **Purpose & Description**: Get the user's active cart with item quantities, unit prices, and product images.
- **Flutter / Frontend Usage**: Call in Shopping Cart screen. Manage items quantity, remove items, verify real-time stock, and calculate subtotal.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Active cart retrieved",
  "data": {
    "id": "01a0ed41-15dc-70db-82a3-80011e737c08",
    "status": "ACTIVE",
    "items": [
      {
        "id": "01a0ed41-15dc-70db-82a3-80011e737c08",
        "product_id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
        "quantity": 2,
        "unit_coin_price": 45000,
        "product": { "name": "Peers Official Polo T-Shirt" }
      }
    ]
  }
}
```

---

### 13. Add Item to Cart
- **Method**: `POST`
- **Local URL**: `http://localhost:8000/api/v1/cart/items`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/cart/items`
- **Purpose & Description**: Add product or variant to user cart with eligibility & quantity limit checks.
- **Flutter / Frontend Usage**: Call in Shopping Cart screen. Manage items quantity, remove items, verify real-time stock, and calculate subtotal.
- **Headers**: ``Authorization: Bearer <TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "product_id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
  "variant_id": "935d6a66-837e-40c0-9971-aadda2db131e",
  "quantity": 2
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/cart/items/01a0ed41-15dc-70db-82a3-80011e737c08`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/cart/items/01a0ed41-15dc-70db-82a3-80011e737c08`
- **Purpose & Description**: Change quantity of an item in the cart.
- **Flutter / Frontend Usage**: Call in Shopping Cart screen. Manage items quantity, remove items, verify real-time stock, and calculate subtotal.
- **Headers**: ``Authorization: Bearer <TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "quantity": 3
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/cart/items/01a0ed41-15dc-70db-82a3-80011e737c08`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/cart/items/01a0ed41-15dc-70db-82a3-80011e737c08`
- **Purpose & Description**: Remove an item from the cart.
- **Flutter / Frontend Usage**: Call in Shopping Cart screen. Manage items quantity, remove items, verify real-time stock, and calculate subtotal.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/cart/validate`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/cart/validate`
- **Purpose & Description**: Validate price changes, stock availability, and inactive products across all cart items.
- **Flutter / Frontend Usage**: Call in Shopping Cart screen. Manage items quantity, remove items, verify real-time stock, and calculate subtotal.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/addresses`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/addresses`
- **Purpose & Description**: Get all active delivery addresses of the authenticated user.
- **Flutter / Frontend Usage**: Call in Shopping Cart screen. Manage items quantity, remove items, verify real-time stock, and calculate subtotal.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "User addresses retrieved",
  "data": [
    {
      "id": "01a0ecd1-1d7f-70ab-885a-020f273de747",
      "label": "Office",
      "full_name": "Rajesh Patel",
      "phone": "9876543210",
      "line1": "402, Titanium City Centre",
      "city": "Ahmedabad",
      "state": "Gujarat",
      "pincode": "380015",
      "is_default": true
    }
  ]
}
```

---

### 18. Create Delivery Address
- **Method**: `POST`
- **Local URL**: `http://localhost:8000/api/v1/addresses`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/addresses`
- **Purpose & Description**: Add a new delivery address (automatically manages default address flag).
- **Flutter / Frontend Usage**: Call in Shopping Cart screen. Manage items quantity, remove items, verify real-time stock, and calculate subtotal.
- **Headers**: ``Authorization: Bearer <TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "label": "Office",
  "full_name": "Rajesh Patel",
  "phone": "9876543210",
  "line1": "402, Titanium City Centre",
  "line2": "100 Feet Anand Nagar Road",
  "city": "Ahmedabad",
  "state": "Gujarat",
  "pincode": "380015",
  "country": "India",
  "is_default": true
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/addresses/01a0ecd1-1d7f-70ab-885a-020f273de747`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/addresses/01a0ecd1-1d7f-70ab-885a-020f273de747`
- **Purpose & Description**: Get details of a single address.
- **Flutter / Frontend Usage**: Call in Delivery Address & Pickup Point selection. Add shipping address, check pincode courier SLA, or select central pickup points.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/addresses/01a0ecd1-1d7f-70ab-885a-020f273de747`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/addresses/01a0ecd1-1d7f-70ab-885a-020f273de747`
- **Purpose & Description**: Update an existing address.
- **Flutter / Frontend Usage**: Call in Delivery Address & Pickup Point selection. Add shipping address, check pincode courier SLA, or select central pickup points.
- **Headers**: ``Authorization: Bearer <TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "full_name": "Rajesh M. Patel",
  "phone": "9876543210",
  "line1": "405, Titanium City Centre",
  "city": "Ahmedabad",
  "state": "Gujarat",
  "pincode": "380015",
  "is_default": true
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/addresses/01a0ecd1-1d7f-70ab-885a-020f273de747`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/addresses/01a0ecd1-1d7f-70ab-885a-020f273de747`
- **Purpose & Description**: Soft delete a delivery address.
- **Flutter / Frontend Usage**: Call in Delivery Address & Pickup Point selection. Add shipping address, check pincode courier SLA, or select central pickup points.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/serviceability/check`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/serviceability/check`
- **Purpose & Description**: Check if home delivery and pickup are serviceable for a given 6-digit pincode.
- **Flutter / Frontend Usage**: Call in Delivery Address & Pickup Point selection. Add shipping address, check pincode courier SLA, or select central pickup points.
- **Headers**: ``Authorization: Bearer <TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "pincode": "380015"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/pickup-points?pincode=380015`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/pickup-points?pincode=380015`
- **Purpose & Description**: Get all active takeaway pickup locations.
- **Flutter / Frontend Usage**: Call in Delivery Address & Pickup Point selection. Add shipping address, check pincode courier SLA, or select central pickup points.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/pickup-points/5c4b3a21-0987-6543-210f-edcba9876543`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/pickup-points/5c4b3a21-0987-6543-210f-edcba9876543`
- **Purpose & Description**: Get detailed address, manager name, contact phone, and operating hours of pickup point.
- **Flutter / Frontend Usage**: Call in Delivery Address & Pickup Point selection. Add shipping address, check pincode courier SLA, or select central pickup points.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/checkout/quote`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/checkout/quote`
- **Purpose & Description**: Lock stock and price for 15 minutes, calculate coin total, verify delivery minimum, and calculate coin shortfall.
- **Flutter / Frontend Usage**: Call on entering Checkout screen. Pre-calculates 15-minute valuation quote and triggers SMS/In-app OTP if high coins threshold is reached.
- **Headers**: ``Authorization: Bearer <TOKEN>`, `Content-Type: application/json``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Checkout quote generated successfully",
  "data": {
    "quote_id": "77889900-aabb-ccdd-eeff-001122334455",
    "quote_no": "QUO-984210",
    "expires_at": "2026-09-29T10:15:00Z",
    "subtotal_coins": 90000,
    "delivery_coins": 0,
    "total_coins": 90000,
    "wallet": {
      "balance": 125000,
      "shortfall": 0
    },
    "serviceability": { "serviceable": true },
    "stock": { "available": true },
    "delivery_minimum_met": true,
    "otp_required": false,
    "can_place_order": true
  }
}
```

---

### 26. Send Checkout OTP Challenge
- **Method**: `POST`
- **Local URL**: `http://localhost:8000/api/v1/checkout/otp/send`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/checkout/otp/send`
- **Purpose & Description**: Send step-up verification OTP for orders >= configured coin limit or address changes.
- **Flutter / Frontend Usage**: Call on entering Checkout screen. Pre-calculates 15-minute valuation quote and triggers SMS/In-app OTP if high coins threshold is reached.
- **Headers**: ``Authorization: Bearer <TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "purpose": "ORDER",
  "quote_id": "77889900-aabb-ccdd-eeff-001122334455"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/checkout/otp/verify`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/checkout/otp/verify`
- **Purpose & Description**: Verify 6-digit OTP and get a short-lived `verification_token`.
- **Flutter / Frontend Usage**: Call on entering Checkout screen. Pre-calculates 15-minute valuation quote and triggers SMS/In-app OTP if high coins threshold is reached.
- **Headers**: ``Authorization: Bearer <TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "challenge_id": "3a210987-6543-210f-edcb-a9876543210f",
  "otp": "482915"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/orders`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/orders`
- **Purpose & Description**: Finalize purchase, lock wallet & inventory, debit coins (Bonus-first then Earned), generate receipt & status history.
- **Flutter / Frontend Usage**: Call on entering Checkout screen. Pre-calculates 15-minute valuation quote and triggers SMS/In-app OTP if high coins threshold is reached.
- **Headers**: `- `Authorization: Bearer <TOKEN>``
- **Request Body (JSON)**:
```json
{
  "quote_id": "77889900-aabb-ccdd-eeff-001122334455",
  "otp_verification_token": "store_otp_tok_98f7e6d5c4b3a2109876543210fedcba",
  "notes": "Please deliver before 6 PM"
}
```
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Order placed successfully",
  "data": {
    "id": "21098765-4321-0fed-cba9-876543210fed",
    "order_no": "ORD-123456",
    "status": "CONFIRMED",
    "total_coins": 90000,
    "coins_from_bonus": 25000,
    "coins_from_earned": 65000,
    "receipt": {
      "receipt_no": "REC-789012",
      "coins_paid": 90000
    }
  }
}
```

---

### 29. List User Orders
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/v1/orders?status=CONFIRMED&page=1&per_page=20`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/orders?status=CONFIRMED&page=1&per_page=20`
- **Purpose & Description**: Get paginated list of user orders.
- **Flutter / Frontend Usage**: Call on 'Place Order' or 'Cancel Order'. Atomically executes wallet deduction, inventory decrement, and lifecycle updates.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Orders retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "21098765-4321-0fed-cba9-876543210fed",
        "order_no": "ORD-123456",
        "status": "CONFIRMED",
        "total_coins": 90000
      }
    ],
    "total": 1
  }
}
```

---

### 30. Get Order Details & Action Flags
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/v1/orders/21098765-4321-0fed-cba9-876543210fed`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/orders/21098765-4321-0fed-cba9-876543210fed`
- **Purpose & Description**: Full order details with items, snapshot, payments split, receipt, tracking, and Flutter action flags (`can_cancel`, `can_return`, etc.).
- **Flutter / Frontend Usage**: Call on 'Place Order' or 'Cancel Order'. Atomically executes wallet deduction, inventory decrement, and lifecycle updates.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/cancel`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/cancel`
- **Purpose & Description**: Cancel eligible order (`PLACED`, `CONFIRMED`) and automatically refund exact Bonus/Earned coin split to user wallet.
- **Flutter / Frontend Usage**: Call on 'Place Order' or 'Cancel Order'. Atomically executes wallet deduction, inventory decrement, and lifecycle updates.
- **Headers**: ``Authorization: Bearer <TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "reason": "Ordered by mistake"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/status-history`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/status-history`
- **Purpose & Description**: View timestamped history of all status transitions of the order.
- **Flutter / Frontend Usage**: Call on 'Place Order' or 'Cancel Order'. Atomically executes wallet deduction, inventory decrement, and lifecycle updates.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/receipt`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/receipt`
- **Purpose & Description**: View or download immutable receipt of the order.
- **Flutter / Frontend Usage**: Call on 'Place Order' or 'Cancel Order'. Atomically executes wallet deduction, inventory decrement, and lifecycle updates.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/tracking`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/tracking`
- **Purpose & Description**: Get courier tracking information and shipment events.
- **Flutter / Frontend Usage**: Call on Order Details screen. Track courier shipment AWB live milestones and view official tax receipt details.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Tracking info retrieved",
  "data": {
    "tracking_no": "DELH9876543210",
    "carrier": "Delhivery",
    "status": "DISPATCHED",
    "events": []
  }
}
```

---

### 35. Submit Return Request
- **Method**: `POST`
- **Local URL**: `http://localhost:8000/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/return`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/return`
- **Purpose & Description**: Submit return request within 7 days of delivery with photos (customized products are rejected).
- **Flutter / Frontend Usage**: Call on Order Details screen. Track courier shipment AWB live milestones and view official tax receipt details.
- **Headers**: ``Authorization: Bearer <TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "reason": "DAMAGED",
  "description": "Stitching on left sleeve is torn.",
  "photos": [
    "https://storage.googleapis.com/.../return_photo1.jpg"
  ]
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/returns?page=1`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/returns?page=1`
- **Purpose & Description**: Get all return requests submitted by the user.
- **Flutter / Frontend Usage**: Call on Order Details screen. Track courier shipment AWB live milestones and view official tax receipt details.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/returns/10987654-3210-fedc-ba98-76543210fedc`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/returns/10987654-3210-fedc-ba98-76543210fedc`
- **Purpose & Description**: View return status, quality check result, and refund info.
- **Flutter / Frontend Usage**: Call on Order Details screen. Track courier shipment AWB live milestones and view official tax receipt details.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Return request details retrieved",
  "data": {
    "id": "10987654-3210-fedc-ba98-76543210fedc",
    "return_no": "RET-981240",
    "status": "REQUESTED"
  }
}
```

---

### 38. Cancel Return Request
- **Method**: `POST`
- **Local URL**: `http://localhost:8000/api/v1/returns/10987654-3210-fedc-ba98-76543210fedc/cancel`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/returns/10987654-3210-fedc-ba98-76543210fedc/cancel`
- **Purpose & Description**: Cancel a pending return request.
- **Flutter / Frontend Usage**: Call on Order Details screen. Track courier shipment AWB live milestones and view official tax receipt details.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Return request cancelled",
  "data": {
    "status": "CANCELLED"
  }
}
```

---

### 39. Get Refund Details
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/v1/refunds/09876543-210f-edcb-a987-6543210fedcb`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/refunds/09876543-210f-edcb-a987-6543210fedcb`
- **Purpose & Description**: View refund transaction, restored coins, and ledger reference.
- **Flutter / Frontend Usage**: Call on Return Request screen. Allows peer to request item return within 7 days and track coin refund status.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/membership`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/membership`
- **Purpose & Description**: Get membership status, start date, expiry date, and days remaining.
- **Flutter / Frontend Usage**: Call on Return Request screen. Allows peer to request item return within 7 days and track coin refund status.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/membership/plans`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/membership/plans`
- **Purpose & Description**: Get active membership renewal plans with duration and coin prices.
- **Flutter / Frontend Usage**: Call on Return Request screen. Allows peer to request item return within 7 days and track coin refund status.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/membership/quote`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/membership/quote`
- **Purpose & Description**: Calculate new end date (extending active or lapsed membership), coin shortfall, and max horizon limits.
- **Flutter / Frontend Usage**: Call on Return Request screen. Allows peer to request item return within 7 days and track coin refund status.
- **Headers**: ``Authorization: Bearer <TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "plan_id": "f1e2d3c4-b5a6-9870-1234-56789abcdef0"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/membership/renew`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/membership/renew`
- **Purpose & Description**: Atomically pay coins, extend membership dates, log membership ledger, and generate receipt.
- **Flutter / Frontend Usage**: Call on Product Detail Reviews section. Verified buyers submit 1-5 star ratings and reviews.
- **Headers**: `- `Authorization: Bearer <TOKEN>``
- **Request Body (JSON)**:
```json
{
  "plan_id": "f1e2d3c4-b5a6-9870-1234-56789abcdef0"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/library?page=1`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/library?page=1`
- **Purpose & Description**: List all purchased digital assets, courses, and subscription entitlements.
- **Flutter / Frontend Usage**: Call on Product Detail Reviews section. Verified buyers submit 1-5 star ratings and reviews.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/library/e2d3c4b5-a698-7012-3456-789abcdef012`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/library/e2d3c4b5-a698-7012-3456-789abcdef012`
- **Purpose & Description**: Get single digital course/asset entitlement details and period.
- **Flutter / Frontend Usage**: Call on Product Detail Reviews section. Verified buyers submit 1-5 star ratings and reviews.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/library/e2d3c4b5-a698-7012-3456-789abcdef012/access`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/library/e2d3c4b5-a698-7012-3456-789abcdef012/access`
- **Purpose & Description**: Generate 15-minute temporary secure signed S3/storage URL to access private content.
- **Flutter / Frontend Usage**: Call in Customer Support screen. Create tickets regarding order issues and exchange 2-way live messages with admin.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/internal/v1/coins/credit`
- **Live URL**: `https://api.peersglobalunity.com/api/internal/v1/coins/credit`
- **Purpose & Description**: Idempotent coin credit by background systems (re-submitting same `source_event_id` will not double credit).
- **Flutter / Frontend Usage**: Call in Customer Support screen. Create tickets regarding order issues and exchange 2-way live messages with admin.
- **Headers**: ``Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "user_id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
  "amount": 5000,
  "bucket": "EARNED",
  "source_event_id": "ACTIVITY_EVENT_REF_98124",
  "reference_type": "ACTIVITY",
  "reference_id": "935d6a66-837e-40c0-9971-aadda2db131e",
  "remark": "P2P Meeting completed"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/internal/v1/coins/reverse`
- **Live URL**: `https://api.peersglobalunity.com/api/internal/v1/coins/reverse`
- **Purpose & Description**: Idempotent reversal of a previously awarded credit without modifying historical ledger rows.
- **Flutter / Frontend Usage**: Call in Customer Support screen. Create tickets regarding order issues and exchange 2-way live messages with admin.
- **Headers**: ``Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "source_event_id": "ACTIVITY_EVENT_REF_98124",
  "reason": "Activity cancelled by host"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/notifications?page=1&per_page=20`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/notifications?page=1&per_page=20`
- **Purpose & Description**: User notifications inbox.
- **Flutter / Frontend Usage**: Call in Membership & Legal Policy screens. Buy Gold/Silver passes with coin balance and view terms.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/notifications/d3c4b5a6-9870-1234-5678-9abcdef01234`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/notifications/d3c4b5a6-9870-1234-5678-9abcdef01234`
- **Purpose & Description**: View single notification.
- **Flutter / Frontend Usage**: Call in Membership & Legal Policy screens. Buy Gold/Silver passes with coin balance and view terms.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/notifications/d3c4b5a6-9870-1234-5678-9abcdef01234/read`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/notifications/d3c4b5a6-9870-1234-5678-9abcdef01234/read`
- **Purpose & Description**: Mark a notification as read.
- **Flutter / Frontend Usage**: Call in Membership & Legal Policy screens. Buy Gold/Silver passes with coin balance and view terms.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/devices`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/devices`
- **Purpose & Description**: Register FCM / APNS push token for order notifications.
- **Flutter / Frontend Usage**: Call in Membership & Legal Policy screens. Buy Gold/Silver passes with coin balance and view terms.
- **Headers**: ``Authorization: Bearer <TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "device_id": "device_pixel_7_abc",
  "platform": "ANDROID",
  "push_token": "fcm_token_xyz_987456...",
  "app_version": "2.5.0"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/devices/device_pixel_7_abc`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/devices/device_pixel_7_abc`
- **Purpose & Description**: Remove push token upon user logout.
- **Flutter / Frontend Usage**: Call in Membership & Legal Policy screens. Buy Gold/Silver passes with coin balance and view terms.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/webhooks/courier/tracking`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/webhooks/courier/tracking`
- **Purpose & Description**: Courier tracking updates webhook with `provider_event_id` idempotency.
- **Flutter / Frontend Usage**: Call in Membership & Legal Policy screens. Buy Gold/Silver passes with coin balance and view terms.
- **Headers**: ``Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "provider_event_id": "DELHIVERY_EVT_89124",
  "awb": "AWB987654321",
  "status": "DELIVERED",
  "location": "Ahmedabad Hub",
  "description": "Delivered to recipient"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/webhooks/whatsapp/status`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/webhooks/whatsapp/status`
- **Purpose & Description**: WhatsApp delivery callback.
- **Flutter / Frontend Usage**: Call in Membership & Legal Policy screens. Buy Gold/Silver passes with coin balance and view terms.
- **Headers**: ``Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "message_id": "wamid.HBgL...",
  "status": "delivered",
  "timestamp": "1727600000"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/webhooks/email/status`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/webhooks/email/status`
- **Purpose & Description**: Email delivery callback.
- **Flutter / Frontend Usage**: Call in 'My Digital Library' screen. Download purchased PDFs/eBooks via signed secure URLs.
- **Headers**: ``Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "email": "peer@unity.com",
  "event": "delivered"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/webhooks/sms/status`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/webhooks/sms/status`
- **Purpose & Description**: SMS delivery callback.
- **Flutter / Frontend Usage**: Call in 'My Digital Library' screen. Download purchased PDFs/eBooks via signed secure URLs.
- **Headers**: ``Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "sms_id": "sms_98124",
  "status": "SUCCESS"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/support/tickets`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/support/tickets`
- **Purpose & Description**: Open a support ticket linked to an order.
- **Flutter / Frontend Usage**: Call in 'My Digital Library' screen. Download purchased PDFs/eBooks via signed secure URLs.
- **Headers**: ``Authorization: Bearer <TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "order_id": "21098765-4321-0fed-cba9-876543210fed",
  "subject": "Delay in delivery",
  "description": "My order has not been dispatched yet.",
  "priority": "NORMAL"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/support/tickets?page=1`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/support/tickets?page=1`
- **Purpose & Description**: List all support tickets opened by the user.
- **Flutter / Frontend Usage**: Call in User Notification Feed. Real-time order dispatch notifications and coin transaction alerts.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456`
- **Purpose & Description**: View ticket details and threaded chat messages.
- **Flutter / Frontend Usage**: Call in User Notification Feed. Real-time order dispatch notifications and coin transaction alerts.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456/messages`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456/messages`
- **Purpose & Description**: Send message/attachment in existing support ticket.
- **Flutter / Frontend Usage**: Call in User Notification Feed. Real-time order dispatch notifications and coin transaction alerts.
- **Headers**: ``Authorization: Bearer <TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "message": "Any update on this?",
  "attachment_url": "https://storage.googleapis.com/.../receipt.pdf"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456/close`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456/close`
- **Purpose & Description**: Close a support ticket by user.
- **Flutter / Frontend Usage**: Call in User Notification Feed. Real-time order dispatch notifications and coin transaction alerts.
- **Headers**: ``Authorization: Bearer <TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/store/policies`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/store/policies`
- **Purpose & Description**: Get all published store policies (Terms, Return, Shipping, Privacy, etc.).
- **Flutter / Frontend Usage**: Call in User Notification Feed. Real-time order dispatch notifications and coin transaction alerts.
- **Headers**: ``None``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/v1/store/policies/return-refund`
- **Live URL**: `https://api.peersglobalunity.com/api/v1/store/policies/return-refund`
- **Purpose & Description**: Get latest published version of a specific policy.
- **Flutter / Frontend Usage**: Call in User Notification Feed. Real-time order dispatch notifications and coin transaction alerts.
- **Headers**: ``None``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/dashboard`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/dashboard`
- **Purpose & Description**: Overview of orders today, stuck orders, coin burn/redemption, low stock, pending returns.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Dashboard metrics retrieved",
  "data": {
    "orders_today": 14,
    "pending_orders": 3,
    "stuck_orders": 0,
    "coins_issued_today": 45000,
    "coins_redeemed_today": 120000,
    "low_stock_products": 2,
    "pending_returns": 1,
    "pending_wallet_adjustments": 1
  }
}
```

---

### 66. Admin Dashboard Summary Metrics
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/admin/v1/dashboard/summary?from=2026-09-01&to=2026-09-30`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/dashboard/summary?from=2026-09-01&to=2026-09-30`
- **Purpose & Description**: Filtered financial summary metrics by date range.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Summary metrics retrieved",
  "data": {
    "sales": { "total_orders": 45, "total_sales_coins": 1850000 },
    "redemptions": { "total_coins_redeemed": 1850000 },
    "issuance": { "total_coins_issued": 2500000 }
  }
}
```

---

### 67. Admin Pending Operational Actions
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/admin/v1/dashboard/pending-actions`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/dashboard/pending-actions`
- **Purpose & Description**: Tasks needing admin attention (pending returns, pending wallet adjustments, stuck orders).
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/categories`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/categories`
- **Purpose & Description**: List all categories.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "All categories retrieved",
  "data": []
}
```

---

### 69. Admin Create Category
- **Method**: `POST`
- **Local URL**: `http://localhost:8000/api/admin/v1/categories`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/categories`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "name": "Electronics & Gadgets",
  "slug": "electronics-gadgets",
  "description": "Smart watches, powerbanks, audio gear",
  "image_url": "https://...",
  "sort_order": 1,
  "is_active": true
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/categories/b5a69870-1234-5678-9abc-def012345678`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/categories/b5a69870-1234-5678-9abc-def012345678`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "name": "Electronics & Smart Devices",
  "is_active": true
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/categories/b5a69870-1234-5678-9abc-def012345678`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/categories/b5a69870-1234-5678-9abc-def012345678`
- **Purpose & Description**: Safe deletion (disables category if referenced by products).
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Category deleted",
  "data": { "deleted": true }
}
```

---

### 72. Admin List Products
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/admin/v1/products?search=polo&status=ACTIVE&page=1`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/products?search=polo&status=ACTIVE&page=1`
- **Purpose & Description**: Filter products for admin management.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/products`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/products`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "category_id": "b5a69870-1234-5678-9abc-def012345678",
  "sku": "PEERS-POLO-BLK",
  "type": "PHYSICAL",
  "name": "Peers Premium Polo T-Shirt",
  "slug": "peers-premium-polo-t-shirt",
  "short_description": "100% Organic Cotton Polo",
  "description": "High quality embroidered Peers Polo T-shirt.",
  "price_coins": 50000,
  "compare_coin_price": 60000,
  "unit_cost_inr": 650.00,
  "delivery_modes": ["DELIVERY", "PICKUP"],
  "max_quantity_per_order": 5,
  "stock_qty": 100,
  "track_inventory": true,
  "return_allowed": true,
  "customised": false,
  "status": "ACTIVE",
  "is_featured": true
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "name": "Peers Premium Polo T-Shirt (Updated Edition)",
  "price_coins": 48000,
  "status": "ACTIVE"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/disable`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/disable`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/images`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/images`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "image_url": "https://storage.googleapis.com/.../polo_front.jpg",
  "is_primary": true,
  "alt_text": "Polo Front View"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/images/a6987012-3456-789a-bcde-f0123456789a`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/images/a6987012-3456-789a-bcde-f0123456789a`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Image removed",
  "data": { "deleted": true }
}
```

---

### 79. Admin Create Product Variant
- **Method**: `POST`
- **Local URL**: `http://localhost:8000/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/variants`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/variants`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "sku": "POLO-BLK-XL",
  "name": "Black - XL",
  "attributes": {
    "size": "XL",
    "color": "Black"
  },
  "coin_price": 50000,
  "stock_qty": 40,
  "low_stock_threshold": 5,
  "is_active": true
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "coin_price": 52000,
  "is_active": true
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e/stock-adjustment`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e/stock-adjustment`
- **Purpose & Description**: Stock in/out adjustment with inventory movement log.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "quantity_change": 50,
  "reason": "STOCK_RECEIVED",
  "note": "New warehouse shipment batch #491"
}
```
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Stock adjusted successfully",
  "data": {
    "variant": { "stock_qty": 90 },
    "movement": { "quantity_change": 50, "quantity_after": 90 }
  }
}
```

---

### 82. Admin Variant Inventory Movements
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e/inventory`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e/inventory`
- **Purpose & Description**: View full audit log of stock increments and decrements for a variant.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/orders?order_no=ORD&status=CONFIRMED&page=1`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/orders?order_no=ORD&status=CONFIRMED&page=1`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/status`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/status`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "status": "PACKED",
  "note": "Packed and ready for courier pickup"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/notes`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/notes`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "note": "Customer requested gift wrap."
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/packing-slip`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/packing-slip`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Packing slip details retrieved",
  "data": {
    "order_no": "ORD-123456",
    "shipping_address": {}
  }
}
```

---

### 88. Admin Dispatch & Add Shipment
- **Method**: `POST`
- **Local URL**: `http://localhost:8000/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/shipment`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/shipment`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "courier": "Delhivery",
  "courier_service": "Express Surface",
  "awb": "DELH9876543210",
  "tracking_url": "https://www.delhivery.com/track/package/DELH9876543210"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/pickup/verify`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/pickup/verify`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "pickup_code": "482915"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/returns?status=REQUESTED&page=1`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/returns?status=REQUESTED&page=1`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/approve`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/approve`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/reject`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/reject`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "reason": "Return request submitted past 7-day delivery window."
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/receive`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/receive`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/inspect`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/inspect`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "passed": true,
  "notes": "Verified manufacturing defect on seam."
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/refund`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/refund`
- **Purpose & Description**: Restore exact Bonus/Earned split into user wallet atomically.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: `- `Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/wallets?search=rajesh&page=1`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/wallets?search=rajesh&page=1`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33/freeze`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33/freeze`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "reason": "Account under compliance investigation."
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33/unfreeze`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33/unfreeze`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/wallet-adjustments?status=PENDING&page=1`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/wallet-adjustments?status=PENDING&page=1`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/wallet-adjustments`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/wallet-adjustments`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "user_id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
  "adjustment_type": "CREDIT",
  "coins": 20000,
  "bucket": "BONUS",
  "reason": "Founder special recognition grant"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/wallet-adjustments/98701234-5678-9abc-def0-123456789abc/approve`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/wallet-adjustments/98701234-5678-9abc-def0-123456789abc/approve`
- **Purpose & Description**: Executes atomic coin credit/debit (Strict Maker != Checker validation).
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN_CHECKER>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/wallet-adjustments/98701234-5678-9abc-def0-123456789abc/reject`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/wallet-adjustments/98701234-5678-9abc-def0-123456789abc/reject`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "reason": "Insufficient justification"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/bonus-grants`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/bonus-grants`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/bonus-grants`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/bonus-grants`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "user_id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
  "coins": 10000,
  "reason": "Top contributor of the month"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/bonus-grants/87654321-0fed-cba9-8765-43210fedcba9/approve`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/bonus-grants/87654321-0fed-cba9-8765-43210fedcba9/approve`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN_CHECKER>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/bonus-grants/87654321-0fed-cba9-8765-43210fedcba9/reject`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/bonus-grants/87654321-0fed-cba9-8765-43210fedcba9/reject`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/membership/plans`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/membership/plans`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Admin membership plans retrieved",
  "data": []
}
```

---

### 110. Admin Create Membership Plan
- **Method**: `POST`
- **Local URL**: `http://localhost:8000/api/admin/v1/membership/plans`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/membership/plans`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "name": "12 Months Unity Global Peer",
  "duration_months": 12,
  "price_coins": 250000,
  "active": true,
  "sort_order": 1,
  "features": [
    "Unlimited P2P Meetings",
    "Store Access",
    "Digital Library Access"
  ]
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/membership/plans/f1e2d3c4-b5a6-9870-1234-56789abcdef0`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/membership/plans/f1e2d3c4-b5a6-9870-1234-56789abcdef0`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "price_coins": 240000,
  "active": true
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/membership/ledger/a05ceb9e-2747-4dba-841d-63da4d725c33`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/membership/ledger/a05ceb9e-2747-4dba-841d-63da4d725c33`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "User membership ledger retrieved",
  "data": []
}
```

---

### 113. Admin List Entitlements
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/admin/v1/entitlements?page=1`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/entitlements?page=1`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/entitlements/e2d3c4b5-a698-7012-3456-789abcdef012`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/entitlements/e2d3c4b5-a698-7012-3456-789abcdef012`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/entitlements/grant`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/entitlements/grant`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "user_id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
  "product_id": "a05ceb9e-2747-4dba-841d-63da4d725c33",
  "feature_key": "DIGITAL_LEADERSHIP_MASTERCLASS",
  "start_date": "2026-09-29",
  "end_date": "2027-09-29"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/entitlements/e2d3c4b5-a698-7012-3456-789abcdef012/revoke`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/entitlements/e2d3c4b5-a698-7012-3456-789abcdef012/revoke`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "reason": "Membership lapsed / revoked by admin"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/notifications/templates`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/notifications/templates`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Notification templates retrieved",
  "data": []
}
```

---

### 118. Admin Get Notification Template
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/admin/v1/notifications/templates/d3c4b5a6-9870-1234-5678-9abcdef01234`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/notifications/templates/d3c4b5a6-9870-1234-5678-9abcdef01234`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Template retrieved",
  "data": {}
}
```

---

### 119. Admin Notification Logs
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/admin/v1/notifications/logs?page=1`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/notifications/logs?page=1`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/notifications/logs/87012345-6789-abcd-ef01-23456789abcd/resend`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/notifications/logs/87012345-6789-abcd-ef01-23456789abcd/resend`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/support/tickets?status=OPEN&page=1`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/support/tickets?status=OPEN&page=1`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456/assign`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456/assign`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "assignee_id": "a05ceb9e-2747-4dba-841d-63da4d725c33"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456/messages`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456/messages`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "message": "Hello, we have tracked your parcel and it will be delivered today."
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456/resolve`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456/resolve`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "note": "Resolved following courier delivery confirmation."
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/config`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/config`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "All store configs retrieved",
  "data": []
}
```

---

### 127. Admin Get Store Config
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/admin/v1/config/min_delivery_order_coins`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/config/min_delivery_order_coins`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/config/min_delivery_order_coins`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/config/min_delivery_order_coins`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "value": 100000,
  "description": "Minimum coin value for home delivery"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/policies`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/policies`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Admin policies retrieved",
  "data": []
}
```

---

### 130. Admin Create Policy
- **Method**: `POST`
- **Local URL**: `http://localhost:8000/api/admin/v1/policies`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/policies`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "key": "return-refund",
  "version": 2,
  "title": "Peers Store Return & Refund Policy (v2)",
  "content": "Return requests must be submitted within 7 days of delivery...",
  "status": "DRAFT"
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/policies/70123456-789a-bcde-f012-3456789abcde`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/policies/70123456-789a-bcde-f012-3456789abcde`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "title": "Peers Store Return & Refund Policy (v2.1)",
  "content": "Updated return terms..."
}
```
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/policies/70123456-789a-bcde-f012-3456789abcde/publish`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/policies/70123456-789a-bcde-f012-3456789abcde/publish`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/reports/sales?from=2026-09-01&to=2026-09-30`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/reports/sales?from=2026-09-01&to=2026-09-30`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
```json
{
  "success": true,
  "message": "Sales report retrieved",
  "data": {
    "total_orders": 45,
    "total_sales_coins": 1850000,
    "avg_order_value_coins": 41111
  }
}
```

---

### 134. Admin Coin Redemption Report
- **Method**: `GET`
- **Local URL**: `http://localhost:8000/api/admin/v1/reports/coin-redemption?from=2026-09-01&to=2026-09-30`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/reports/coin-redemption?from=2026-09-01&to=2026-09-30`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/reports/coin-issuance?from=2026-09-01&to=2026-09-30`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/reports/coin-issuance?from=2026-09-01&to=2026-09-30`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/reports/membership-renewals?from=2026-09-01&to=2026-09-30`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/reports/membership-renewals?from=2026-09-01&to=2026-09-30`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>``
- **Request Body**: `None`
- **Response (JSON)**:
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
- **Local URL**: `http://localhost:8000/api/admin/v1/reports/export`
- **Live URL**: `https://api.peersglobalunity.com/api/admin/v1/reports/export`
- **Purpose & Description**: Process store transaction.
- **Flutter / Frontend Usage**: Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.
- **Headers**: ``Authorization: Bearer <ADMIN_TOKEN>`, `Content-Type: application/json``
- **Request Body (JSON)**:
```json
{
  "report": "SALES",
  "from": "2026-09-01",
  "to": "2026-09-30",
  "format": "CSV"
}
```
- **Response (JSON)**:
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

