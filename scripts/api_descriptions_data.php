<?php

function getApiSpecificData($num, $title, $method, $path) {
    static $dict = [
        1 => [
            'category' => 'Customer Catalog',
            'purpose' => 'Returns global runtime configuration for the mobile store module: store open/maintenance status, minimum supported app version requirement, user coin balance split (Earned vs Bonus coins), minimum coin threshold for home delivery, and promotional home banners.',
            'flutter' => '<strong>When to Call:</strong> Invoke on app launch / Splash Screen and when entering Store tab.<br><strong>State Handling:</strong> Store values in global state (`storeConfigProvider`). If `current_app_version_supported` is false, present a non-dismissible Force-Update modal dialog directing to App Store / Google Play.'
        ],
        2 => [
            'category' => 'Customer Catalog',
            'purpose' => 'Retrieves active promotional banners configured for the mobile store home screen with direct deep-link targets (CATEGORY, PRODUCT, PROMOTION_PAGE).',
            'flutter' => '<strong>When to Call:</strong> Call on Store Home screen initialization.<br><strong>UI Handling:</strong> Feed into a `PageView` or `CarouselSlider` with 4-second auto-scroll. On banner tap, route user to the deep-linked product or category screen.'
        ],
        3 => [
            'category' => 'Customer Wallet',
            'purpose' => 'Queries the user coin wallet to provide real-time balances: total spendable coins, split between Earned Coins and Bonus Coins, lifetime earnings/expenditures, and account lock state (ACTIVE, FROZEN, CLOSED).',
            'flutter' => '<strong>When to Call:</strong> Call on Store App Bar load, Wallet Screen, and immediately after placing an order or receiving a refund.<br><strong>UI Handling:</strong> Render formatted coin counters with coin icons. If `wallet_state == \'FROZEN\'`, disable checkout and display an alert dialog.'
        ],
        4 => [
            'category' => 'Customer Wallet',
            'purpose' => 'Returns paginated passbook transactions from `coins_ledger` with filters by coin bucket (EARNED, BONUS), reference type (ORDER, RETURN_REFUND, BONUS_GRANT), and date range.',
            'flutter' => '<strong>When to Call:</strong> Use on Wallet Ledger screen with infinite scrolling list.<br><strong>UI Handling:</strong> Color credit entries green (`+500 Coins`) and debit deductions dark grey/red (`-1000 Coins`). Tapping a record navigates to the associated order details.'
        ],
        5 => [
            'category' => 'Customer Wallet',
            'purpose' => 'Aggregates the user\'s total coin earnings, redemptions, and pending hold amounts for the current calendar month compared against previous periods.',
            'flutter' => '<strong>When to Call:</strong> Call on user rewards dashboard or profile analytics widget.<br><strong>UI Handling:</strong> Render comparative monthly progress charts comparing earned coins against redeemed coins.'
        ],
        6 => [
            'category' => 'Customer Wallet',
            'purpose' => 'Checks whether the user wallet is in good standing or frozen due to compliance reviews, returning freeze reason remarks and timestamp.',
            'flutter' => '<strong>When to Call:</strong> Call before entering the Checkout flow or on Wallet settings.<br><strong>UI Handling:</strong> If frozen, render a warning banner and disable \'Proceed to Checkout\' buttons.'
        ],
        7 => [
            'category' => 'Customer Catalog',
            'purpose' => 'Fetches the list of active merchandise categories (Apparel, Accessories, Books, Tech) with thumbnail icons, slug names, and active product counts.',
            'flutter' => '<strong>When to Call:</strong> Call once on Store Home screen load and cache locally.<br><strong>UI Handling:</strong> Render as horizontal category chips or category card grid with product counts.'
        ],
        8 => [
            'category' => 'Customer Catalog',
            'purpose' => 'Queries the product catalog with filtering parameters: category ID, search keyword, coin price ranges (min/max), physical vs digital types, and sort sequences.',
            'flutter' => '<strong>When to Call:</strong> Main catalog browse screen and search result view.<br><strong>Performance:</strong> Implement a 300ms debounce on the search bar. Use 20-item chunk pagination with pull-to-refresh.'
        ],
        9 => [
            'category' => 'Customer Catalog',
            'purpose' => 'Retrieves complete product specifications, high-res image gallery, HTML description, delivery options (Courier vs Hub Pickup), and all configured SKU variants with live stock levels.',
            'flutter' => '<strong>When to Call:</strong> Call upon opening the Product Details screen.<br><strong>UI Handling:</strong> Bind variant selector chips (Size/Color). Update displayed coin price and stock badge in real-time as variants are selected.'
        ],
        10 => [
            'category' => 'Customer Catalog',
            'purpose' => 'Fetches curated spotlight products flagged by administrators as featured items for promotional carousels.',
            'flutter' => '<strong>When to Call:</strong> Call on Store Home screen to render the \'Featured Collections\' horizontal slider.<br><strong>UI Handling:</strong> Display product cards with special \'Featured\' ribbon badges.'
        ],
        11 => [
            'category' => 'Customer Catalog',
            'purpose' => 'Handles GET operation for Search Products on `/api/v1/store/search?q=tshirt&category_id=84582fbc-a518-49e7-a249-169b42362bec&page=1` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/v1/store/search?q=tshirt&category_id=84582fbc-a518-49e7-a249-169b42362bec&page=1` with active Bearer token.'
        ],
        12 => [
            'category' => 'Customer Cart',
            'purpose' => 'Handles GET operation for Get Active Cart on `/api/v1/cart` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/v1/cart` with active Bearer token.'
        ],
        13 => [
            'category' => 'Customer Cart',
            'purpose' => 'Adds a product variant and quantity to the cart, enforcing stock availability and monthly peer purchase limits.',
            'flutter' => '<strong>When to Call:</strong> Trigger on \'Add to Cart\' button tap.<br><strong>UI Handling:</strong> Show toast notification with \'View Cart\' CTA and increment top cart badge counter.'
        ],
        14 => [
            'category' => 'Customer Cart',
            'purpose' => 'Adjusts the quantity of an existing line item in the cart, verifying live inventory limits before saving.',
            'flutter' => '<strong>When to Call:</strong> Trigger on cart quantity stepper clicks (`+` or `-`).<br><strong>UI Handling:</strong> Optimistic local UI update with server rollback if a 422 stock error is returned.'
        ],
        15 => [
            'category' => 'Customer Cart',
            'purpose' => 'Deletes a specific line item from the shopping cart and recalculates total payable coins.',
            'flutter' => '<strong>When to Call:</strong> Trigger on delete icon or swipe-to-delete gesture on a cart row.<br><strong>UI Handling:</strong> Animate item removal and update bottom total bar.'
        ],
        16 => [
            'category' => 'Customer Cart',
            'purpose' => 'Handles POST operation for Validate Cart (Pre-checkout Check) on `/api/v1/cart/validate` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/v1/cart/validate` with active Bearer token.'
        ],
        17 => [
            'category' => 'Address & Delivery',
            'purpose' => 'Handles GET operation for List User Addresses on `/api/v1/addresses` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/v1/addresses` with active Bearer token.'
        ],
        18 => [
            'category' => 'Address & Delivery',
            'purpose' => 'Validates and creates a new delivery shipping address in `user_addresses`.',
            'flutter' => '<strong>When to Call:</strong> Trigger from \'Add New Address\' modal form.<br><strong>UI Handling:</strong> Form validation for phone and 6-digit pincode; auto-select upon creation.'
        ],
        19 => [
            'category' => 'Address & Delivery',
            'purpose' => 'Fetches full details of a specific saved address by its address UUID.',
            'flutter' => '<strong>When to Call:</strong> Call before opening the Edit Address bottom sheet.<br><strong>UI Handling:</strong> Pre-populate text form fields.'
        ],
        20 => [
            'category' => 'Address & Delivery',
            'purpose' => 'Modifies existing shipping address details in the database.',
            'flutter' => '<strong>When to Call:</strong> Trigger on \'Save Address\' button.<br><strong>UI Handling:</strong> Show submit spinner and refresh address list on success.'
        ],
        21 => [
            'category' => 'Address & Delivery',
            'purpose' => 'Soft deletes a delivery address from the user profile if not linked to an active pending dispatch.',
            'flutter' => '<strong>When to Call:</strong> Trigger on delete button in address management.<br><strong>UI Handling:</strong> Confirm with dialog and remove card from list.'
        ],
        22 => [
            'category' => 'Address & Delivery',
            'purpose' => 'Validates if a 6-digit postal pincode is covered by courier partners and returns delivery transit day estimates.',
            'flutter' => '<strong>When to Call:</strong> Trigger with 300ms debounce as soon as user types 6 digits in the pincode field.<br><strong>UI Handling:</strong> If not serviceable, show red warning and suggest selecting Central Pickup Hub.'
        ],
        23 => [
            'category' => 'Address & Delivery',
            'purpose' => 'Retrieves all active Peers Central Hub takeaway pickup points where peers can collect orders with zero delivery coins.',
            'flutter' => '<strong>When to Call:</strong> Call when user chooses \'Pickup from Hub\' delivery mode on Checkout.<br><strong>UI Handling:</strong> Render hub cards with address, manager contact, and operating hours.'
        ],
        24 => [
            'category' => 'Address & Delivery',
            'purpose' => 'Fetches complete operational details and Google Maps coordinate info for a specific pickup hub.',
            'flutter' => '<strong>When to Call:</strong> Call when user taps on a pickup hub card.<br><strong>UI Handling:</strong> Display detailed location modal with \'Open in Maps\' action.'
        ],
        25 => [
            'category' => 'Checkout & Quotes',
            'purpose' => 'Creates a 15-minute locked checkout valuation quote (`checkout_quotes`), calculating coin balances, delivery charges, and assessing if OTP challenge is mandatory.',
            'flutter' => '<strong>When to Call:</strong> Call on opening Order Review / Checkout summary.<br><strong>UI Handling:</strong> Start a 15-minute visual countdown timer. If `otp_required: true`, open OTP bottom sheet upon placing order.'
        ],
        26 => [
            'category' => 'Checkout & Quotes',
            'purpose' => 'Dispatches a 6-digit SMS/WhatsApp verification OTP for high-value coin redemptions or address modifications.',
            'flutter' => '<strong>When to Call:</strong> Trigger when placing order if quote indicated OTP requirement.<br><strong>UI Handling:</strong> Render 6-digit pin input dialog with 60-second resend timer.'
        ],
        27 => [
            'category' => 'Checkout & Quotes',
            'purpose' => 'Validates the 6-digit OTP passcode against the active challenge record and unlocks the quote for order placement.',
            'flutter' => '<strong>When to Call:</strong> Auto-trigger when 6th OTP digit is entered.<br><strong>UI Handling:</strong> On success, immediately proceed to call Place Order API.'
        ],
        28 => [
            'category' => 'Orders & Tracking',
            'purpose' => 'Atomically finalizes purchase inside a database transaction: locks wallet, debits coins (Bonus first, then Earned), reserves inventory, and creates immutable order records.',
            'flutter' => '<strong>When to Call:</strong> Trigger on final \'Confirm & Place Order\' tap.<br><strong>Crucial Rule:</strong> Pass a unique UUID in `Idempotency-Key` header to prevent duplicate orders on poor connectivity. Navigate to Order Success celebration.'
        ],
        29 => [
            'category' => 'Orders & Tracking',
            'purpose' => 'Retrieves paginated order history for the authenticated peer with status filtering (PLACED, PROCESSING, SHIPPED, DELIVERED, CANCELLED, RETURNED).',
            'flutter' => '<strong>When to Call:</strong> Call on \'My Orders\' screen with pull-to-refresh.<br><strong>UI Handling:</strong> Display order cards with item thumbnails, total coins, and color-coded status badges.'
        ],
        30 => [
            'category' => 'Orders & Tracking',
            'purpose' => 'Handles GET operation for Get Order Details & Action Flags on `/api/v1/orders/21098765-4321-0fed-cba9-876543210fed` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/v1/orders/21098765-4321-0fed-cba9-876543210fed` with active Bearer token.'
        ],
        31 => [
            'category' => 'Orders & Tracking',
            'purpose' => 'Allows peer to cancel an order prior to SHIPPED status, instantly executing an automatic coin refund transaction to the user wallet.',
            'flutter' => '<strong>When to Call:</strong> Trigger from \'Cancel Order\' button on eligible orders.<br><strong>UI Handling:</strong> Show reason selection sheet; refresh wallet and order status on success.'
        ],
        32 => [
            'category' => 'Orders & Tracking',
            'purpose' => 'Handles GET operation for Order Status Audit History on `/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/status-history` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/v1/orders/21098765-4321-0fed-cba9-876543210fed/status-history` with active Bearer token.'
        ],
        33 => [
            'category' => 'Orders & Tracking',
            'purpose' => 'Handles GET operation for Order Coin Receipt on `/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/receipt` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/v1/orders/21098765-4321-0fed-cba9-876543210fed/receipt` with active Bearer token.'
        ],
        34 => [
            'category' => 'Orders & Tracking',
            'purpose' => 'Handles GET operation for Order Live Tracking on `/api/v1/orders/21098765-4321-0fed-cba9-876543210fed/tracking` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/v1/orders/21098765-4321-0fed-cba9-876543210fed/tracking` with active Bearer token.'
        ],
        35 => [
            'category' => 'Orders & Tracking',
            'purpose' => 'Submits a return request within the 7-day delivery window with return reason description and photo evidence.',
            'flutter' => '<strong>When to Call:</strong> Trigger from Order Details \'Return Item\' CTA.<br><strong>UI Handling:</strong> Form with camera/gallery picker requiring at least one photo upload.'
        ],
        36 => [
            'category' => 'Returns & Refunds',
            'purpose' => 'Handles GET operation for List User Returns on `/api/v1/returns?page=1` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/v1/returns?page=1` with active Bearer token.'
        ],
        37 => [
            'category' => 'Returns & Refunds',
            'purpose' => 'Handles GET operation for Get Return Details on `/api/v1/returns/10987654-3210-fedc-ba98-76543210fedc` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/v1/returns/10987654-3210-fedc-ba98-76543210fedc` with active Bearer token.'
        ],
        38 => [
            'category' => 'Returns & Refunds',
            'purpose' => 'Allows peer to withdraw an open return request before warehouse collection.',
            'flutter' => '<strong>When to Call:</strong> Trigger on \'Cancel Return\' button.<br><strong>UI Handling:</strong> Confirm with dialog and update status chip to CANCELLED.'
        ],
        39 => [
            'category' => 'Customer Catalog',
            'purpose' => 'Handles GET operation for Get Refund Details on `/api/v1/refunds/09876543-210f-edcb-a987-6543210fedcb` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/v1/refunds/09876543-210f-edcb-a987-6543210fedcb` with active Bearer token.'
        ],
        40 => [
            'category' => 'Customer Catalog',
            'purpose' => 'Handles GET operation for User Membership Status on `/api/v1/membership` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/v1/membership` with active Bearer token.'
        ],
        41 => [
            'category' => 'Customer Catalog',
            'purpose' => 'Handles GET operation for List Membership Plans for Coins on `/api/v1/membership/plans` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/v1/membership/plans` with active Bearer token.'
        ],
        42 => [
            'category' => 'Customer Catalog',
            'purpose' => 'Handles POST operation for Membership Renewal Quote on `/api/v1/membership/quote` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/v1/membership/quote` with active Bearer token.'
        ],
        43 => [
            'category' => 'Customer Catalog',
            'purpose' => 'Handles POST operation for Renew Membership with Coins on `/api/v1/membership/renew` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/v1/membership/renew` with active Bearer token.'
        ],
        44 => [
            'category' => 'Customer Catalog',
            'purpose' => 'Handles GET operation for Digital Library / Entitlements on `/api/v1/library?page=1` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/v1/library?page=1` with active Bearer token.'
        ],
        45 => [
            'category' => 'Customer Catalog',
            'purpose' => 'Handles GET operation for Digital Entitlement Detail on `/api/v1/library/e2d3c4b5-a698-7012-3456-789abcdef012` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/v1/library/e2d3c4b5-a698-7012-3456-789abcdef012` with active Bearer token.'
        ],
        46 => [
            'category' => 'Customer Catalog',
            'purpose' => 'Handles GET operation for Get Signed Access URL on `/api/v1/library/e2d3c4b5-a698-7012-3456-789abcdef012/access` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/v1/library/e2d3c4b5-a698-7012-3456-789abcdef012/access` with active Bearer token.'
        ],
        47 => [
            'category' => 'Internal Engine',
            'purpose' => 'Handles POST operation for Internal Coin Credit (Engine API) on `/api/internal/v1/coins/credit` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/internal/v1/coins/credit` with active Bearer token.'
        ],
        48 => [
            'category' => 'Internal Engine',
            'purpose' => 'Handles POST operation for Internal Coin Reversal (Engine API) on `/api/internal/v1/coins/reverse` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/internal/v1/coins/reverse` with active Bearer token.'
        ],
        49 => [
            'category' => 'In-App Notifications',
            'purpose' => 'Handles GET operation for List Notifications on `/api/v1/notifications?page=1&per_page=20` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/v1/notifications?page=1&per_page=20` with active Bearer token.'
        ],
        50 => [
            'category' => 'In-App Notifications',
            'purpose' => 'Handles GET operation for Get Notification Detail on `/api/v1/notifications/d3c4b5a6-9870-1234-5678-9abcdef01234` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/v1/notifications/d3c4b5a6-9870-1234-5678-9abcdef01234` with active Bearer token.'
        ],
        51 => [
            'category' => 'In-App Notifications',
            'purpose' => 'Marks a specific notification as read by ID.',
            'flutter' => '<strong>When to Call:</strong> Trigger when user taps a notification tile.<br><strong>UI Handling:</strong> Dim card background and decrement unread counter.'
        ],
        52 => [
            'category' => 'Customer Catalog',
            'purpose' => 'Handles POST operation for Register Device Push Token on `/api/v1/devices` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/v1/devices` with active Bearer token.'
        ],
        53 => [
            'category' => 'Customer Catalog',
            'purpose' => 'Handles DELETE operation for Delete Device Token on `/api/v1/devices/device_pixel_7_abc` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `DELETE /api/v1/devices/device_pixel_7_abc` with active Bearer token.'
        ],
        54 => [
            'category' => 'Webhooks',
            'purpose' => 'Handles POST operation for Inbound Courier Tracking Webhook on `/api/v1/webhooks/courier/tracking` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/v1/webhooks/courier/tracking` with active Bearer token.'
        ],
        55 => [
            'category' => 'Webhooks',
            'purpose' => 'Handles POST operation for WhatsApp Delivery Status Webhook on `/api/v1/webhooks/whatsapp/status` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/v1/webhooks/whatsapp/status` with active Bearer token.'
        ],
        56 => [
            'category' => 'Webhooks',
            'purpose' => 'Handles POST operation for Email Delivery Webhook on `/api/v1/webhooks/email/status` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/v1/webhooks/email/status` with active Bearer token.'
        ],
        57 => [
            'category' => 'Webhooks',
            'purpose' => 'Handles POST operation for SMS Delivery Webhook on `/api/v1/webhooks/sms/status` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/v1/webhooks/sms/status` with active Bearer token.'
        ],
        58 => [
            'category' => 'Support Tickets',
            'purpose' => 'Creates a new customer support ticket with subject, category, description, order link, and attachments.',
            'flutter' => '<strong>When to Call:</strong> Trigger from \'Contact Support\' form.<br><strong>UI Handling:</strong> Submit form and navigate to the ticket chat thread.'
        ],
        59 => [
            'category' => 'Support Tickets',
            'purpose' => 'Retrieves customer support tickets submitted by the peer with status (OPEN, IN_PROGRESS, RESOLVED).',
            'flutter' => '<strong>When to Call:</strong> Call on Helpdesk / Support screen.<br><strong>UI Handling:</strong> Render ticket cards with subject, ID, and status tag.'
        ],
        60 => [
            'category' => 'Support Tickets',
            'purpose' => 'Handles GET operation for Get Support Ticket Thread on `/api/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456` with active Bearer token.'
        ],
        61 => [
            'category' => 'Support Tickets',
            'purpose' => 'Handles POST operation for Send Message in Support Ticket on `/api/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456/messages` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/v1/support/tickets/c4b5a698-7012-3456-789a-bcdef0123456/messages` with active Bearer token.'
        ],
        62 => [
            'category' => 'Support Tickets',
            'purpose' => 'Allows peer to mark a support ticket as resolved and close the thread.',
            'flutter' => '<strong>When to Call:</strong> Trigger on \'Close Ticket\' button.<br><strong>UI Handling:</strong> Update status badge to RESOLVED and disable composer.'
        ],
        63 => [
            'category' => 'Public Policies',
            'purpose' => 'Handles GET operation for List Published Store Policies on `/api/v1/store/policies` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/v1/store/policies` with active Bearer token.'
        ],
        64 => [
            'category' => 'Public Policies',
            'purpose' => 'Handles GET operation for Get Policy by Key on `/api/v1/store/policies/return-refund` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/v1/store/policies/return-refund` with active Bearer token.'
        ],
        65 => [
            'category' => 'Admin Store Module',
            'purpose' => 'Handles GET operation for Admin Dashboard Metrics on `/api/admin/v1/dashboard` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/dashboard` with active Bearer token.'
        ],
        66 => [
            'category' => 'Admin Store Module',
            'purpose' => 'Handles GET operation for Admin Dashboard Summary Metrics on `/api/admin/v1/dashboard/summary?from=2026-09-01&to=2026-09-30` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/dashboard/summary?from=2026-09-01&to=2026-09-30` with active Bearer token.'
        ],
        67 => [
            'category' => 'Admin Store Module',
            'purpose' => 'Handles GET operation for Admin Pending Operational Actions on `/api/admin/v1/dashboard/pending-actions` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/dashboard/pending-actions` with active Bearer token.'
        ],
        68 => [
            'category' => 'Admin Categories',
            'purpose' => 'Admin endpoint: Retrieves all categories including inactive ones with sequence ordering and product counts.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Power the Categories management table with drag-and-drop sort.'
        ],
        69 => [
            'category' => 'Admin Categories',
            'purpose' => 'Admin endpoint: Creates a new merchandise category with custom name, slug, image, sequence order, and visibility toggle.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Submit from \'Add Category\' modal dialog.'
        ],
        70 => [
            'category' => 'Admin Categories',
            'purpose' => 'Admin endpoint: Updates category title, slug, image URL, sequence order, and visibility state.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Save modifications on category edit form.'
        ],
        71 => [
            'category' => 'Admin Categories',
            'purpose' => 'Admin endpoint: Deletes or archives a category if no active products are attached.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Trigger from delete category confirmation.'
        ],
        72 => [
            'category' => 'Admin Products',
            'purpose' => 'Admin endpoint: Paginated master list of all products with stock counts, variant totals, visibility status, and coin prices.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Main Admin Products table with multi-column filtering and bulk actions.'
        ],
        73 => [
            'category' => 'Admin Products',
            'purpose' => 'Admin endpoint: Creates a new product record with base specs, description, category ID, and delivery flags.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Trigger from Admin \'Add Product\' wizard.'
        ],
        74 => [
            'category' => 'Admin Products',
            'purpose' => 'Handles GET operation for Admin Get Product Detail on `/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33` with active Bearer token.'
        ],
        75 => [
            'category' => 'Admin Products',
            'purpose' => 'Admin endpoint: Updates product title, description, category mapping, and base pricing.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Save edits on Product Edit form.'
        ],
        76 => [
            'category' => 'Admin Products',
            'purpose' => 'Handles POST operation for Admin Disable Product on `/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/disable` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/disable` with active Bearer token.'
        ],
        77 => [
            'category' => 'Admin Products',
            'purpose' => 'Handles POST operation for Admin Add Product Image on `/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/images` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/images` with active Bearer token.'
        ],
        78 => [
            'category' => 'Admin Products',
            'purpose' => 'Handles DELETE operation for Admin Delete Product Image on `/api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/images/a6987012-3456-789a-bcde-f0123456789a` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `DELETE /api/admin/v1/products/a05ceb9e-2747-4dba-841d-63da4d725c33/images/a6987012-3456-789a-bcde-f0123456789a` with active Bearer token.'
        ],
        79 => [
            'category' => 'Admin Variants',
            'purpose' => 'Admin endpoint: Creates a new SKU variant with SKU code, attributes, coin price, and initial stock quantity.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Trigger from \'Add Variant\' modal.'
        ],
        80 => [
            'category' => 'Admin Variants',
            'purpose' => 'Handles PUT operation for Admin Update Variant on `/api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `PUT /api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e` with active Bearer token.'
        ],
        81 => [
            'category' => 'Admin Variants',
            'purpose' => 'Handles POST operation for Admin Manual Stock Adjustment on `/api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e/stock-adjustment` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e/stock-adjustment` with active Bearer token.'
        ],
        82 => [
            'category' => 'Admin Variants',
            'purpose' => 'Handles GET operation for Admin Variant Inventory Movements on `/api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e/inventory` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/variants/935d6a66-837e-40c0-9971-aadda2db131e/inventory` with active Bearer token.'
        ],
        83 => [
            'category' => 'Admin Orders',
            'purpose' => 'Admin endpoint: Master admin orders table with comprehensive filters by status, peer name, date range, and coin bucket.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Main Admin Orders management view.'
        ],
        84 => [
            'category' => 'Admin Orders',
            'purpose' => 'Handles GET operation for Admin Get Order Detail on `/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed` with active Bearer token.'
        ],
        85 => [
            'category' => 'Admin Orders',
            'purpose' => 'Admin endpoint: Advances order status through workflow (PLACED -> PROCESSING -> SHIPPED -> DELIVERED).',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Order status dropdown action.'
        ],
        86 => [
            'category' => 'Admin Orders',
            'purpose' => 'Handles POST operation for Admin Add Note to Order on `/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/notes` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/notes` with active Bearer token.'
        ],
        87 => [
            'category' => 'Admin Orders',
            'purpose' => 'Handles GET operation for Admin Get Packing Slip on `/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/packing-slip` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/packing-slip` with active Bearer token.'
        ],
        88 => [
            'category' => 'Admin Orders',
            'purpose' => 'Handles POST operation for Admin Dispatch & Add Shipment on `/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/shipment` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/shipment` with active Bearer token.'
        ],
        89 => [
            'category' => 'Admin Orders',
            'purpose' => 'Handles POST operation for Admin Verify Pickup Takeaway Code on `/api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/pickup/verify` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/orders/21098765-4321-0fed-cba9-876543210fed/pickup/verify` with active Bearer token.'
        ],
        90 => [
            'category' => 'Admin Returns',
            'purpose' => 'Handles GET operation for Admin List Return Requests on `/api/admin/v1/returns?status=REQUESTED&page=1` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/returns?status=REQUESTED&page=1` with active Bearer token.'
        ],
        91 => [
            'category' => 'Admin Returns',
            'purpose' => 'Handles GET operation for Admin Get Return Detail on `/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc` with active Bearer token.'
        ],
        92 => [
            'category' => 'Admin Returns',
            'purpose' => 'Handles POST operation for Admin Approve Return Request on `/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/approve` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/approve` with active Bearer token.'
        ],
        93 => [
            'category' => 'Admin Returns',
            'purpose' => 'Handles POST operation for Admin Reject Return Request on `/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/reject` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/reject` with active Bearer token.'
        ],
        94 => [
            'category' => 'Admin Returns',
            'purpose' => 'Handles POST operation for Admin Mark Return Received on `/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/receive` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/receive` with active Bearer token.'
        ],
        95 => [
            'category' => 'Admin Returns',
            'purpose' => 'Handles POST operation for Admin Inspect Returned Item on `/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/inspect` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/inspect` with active Bearer token.'
        ],
        96 => [
            'category' => 'Admin Returns',
            'purpose' => 'Handles POST operation for Admin Execute Return Refund on `/api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/refund` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/returns/10987654-3210-fedc-ba98-76543210fedc/refund` with active Bearer token.'
        ],
        97 => [
            'category' => 'Admin Wallets',
            'purpose' => 'Handles GET operation for Admin List Peer Wallets on `/api/admin/v1/wallets?search=rajesh&page=1` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/wallets?search=rajesh&page=1` with active Bearer token.'
        ],
        98 => [
            'category' => 'Admin Wallets',
            'purpose' => 'Handles GET operation for Admin Single Peer Wallet Detail on `/api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33` with active Bearer token.'
        ],
        99 => [
            'category' => 'Admin Wallets',
            'purpose' => 'Handles POST operation for Admin Freeze Peer Wallet on `/api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33/freeze` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33/freeze` with active Bearer token.'
        ],
        100 => [
            'category' => 'Admin Wallets',
            'purpose' => 'Handles POST operation for Admin Unfreeze Peer Wallet on `/api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33/unfreeze` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/wallets/a05ceb9e-2747-4dba-841d-63da4d725c33/unfreeze` with active Bearer token.'
        ],
        101 => [
            'category' => 'Admin Store Module',
            'purpose' => 'Handles GET operation for Admin List Wallet Adjustment Requests on `/api/admin/v1/wallet-adjustments?status=PENDING&page=1` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/wallet-adjustments?status=PENDING&page=1` with active Bearer token.'
        ],
        102 => [
            'category' => 'Admin Store Module',
            'purpose' => 'Handles POST operation for Admin Create Wallet Adjustment Request (Maker) on `/api/admin/v1/wallet-adjustments` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/wallet-adjustments` with active Bearer token.'
        ],
        103 => [
            'category' => 'Admin Store Module',
            'purpose' => 'Handles POST operation for Admin Approve Wallet Adjustment (Checker) on `/api/admin/v1/wallet-adjustments/98701234-5678-9abc-def0-123456789abc/approve` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/wallet-adjustments/98701234-5678-9abc-def0-123456789abc/approve` with active Bearer token.'
        ],
        104 => [
            'category' => 'Admin Store Module',
            'purpose' => 'Handles POST operation for Admin Reject Wallet Adjustment on `/api/admin/v1/wallet-adjustments/98701234-5678-9abc-def0-123456789abc/reject` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/wallet-adjustments/98701234-5678-9abc-def0-123456789abc/reject` with active Bearer token.'
        ],
        105 => [
            'category' => 'Admin Store Module',
            'purpose' => 'Handles GET operation for Admin List Bonus Grants on `/api/admin/v1/bonus-grants` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/bonus-grants` with active Bearer token.'
        ],
        106 => [
            'category' => 'Admin Store Module',
            'purpose' => 'Handles POST operation for Admin Create Bonus Grant Request on `/api/admin/v1/bonus-grants` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/bonus-grants` with active Bearer token.'
        ],
        107 => [
            'category' => 'Admin Store Module',
            'purpose' => 'Handles POST operation for Admin Approve Bonus Grant on `/api/admin/v1/bonus-grants/87654321-0fed-cba9-8765-43210fedcba9/approve` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/bonus-grants/87654321-0fed-cba9-8765-43210fedcba9/approve` with active Bearer token.'
        ],
        108 => [
            'category' => 'Admin Store Module',
            'purpose' => 'Handles POST operation for Admin Reject Bonus Grant on `/api/admin/v1/bonus-grants/87654321-0fed-cba9-8765-43210fedcba9/reject` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `POST /api/admin/v1/bonus-grants/87654321-0fed-cba9-8765-43210fedcba9/reject` with active Bearer token.'
        ],
        109 => [
            'category' => 'Admin Store Module',
            'purpose' => 'Admin endpoint: Lists all store membership tiers and VIP subscription plans configured in the system.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Membership Plans table.'
        ],
        110 => [
            'category' => 'Admin Store Module',
            'purpose' => 'Admin endpoint: Creates a new store membership plan with coin price, duration days, and privilege perks.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> \'Create Membership Plan\' modal.'
        ],
        111 => [
            'category' => 'Admin Store Module',
            'purpose' => 'Admin endpoint: Updates membership plan pricing, perk description, or active status.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Edit Plan modal.'
        ],
        112 => [
            'category' => 'Admin Store Module',
            'purpose' => 'Handles GET operation for Admin View Peer Membership Ledger on `/api/admin/v1/membership/ledger/a05ceb9e-2747-4dba-841d-63da4d725c33` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/membership/ledger/a05ceb9e-2747-4dba-841d-63da4d725c33` with active Bearer token.'
        ],
        113 => [
            'category' => 'Admin Digital Assets',
            'purpose' => 'Handles GET operation for Admin List Entitlements on `/api/admin/v1/entitlements?page=1` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/entitlements?page=1` with active Bearer token.'
        ],
        114 => [
            'category' => 'Admin Digital Assets',
            'purpose' => 'Handles GET operation for Admin Get Entitlement Detail on `/api/admin/v1/entitlements/e2d3c4b5-a698-7012-3456-789abcdef012` with authentication and authorization checks.',
            'flutter' => '<strong>Frontend Integration:</strong> Invoke `GET /api/admin/v1/entitlements/e2d3c4b5-a698-7012-3456-789abcdef012` with active Bearer token.'
        ],
        115 => [
            'category' => 'Admin Digital Assets',
            'purpose' => 'Admin endpoint: Manually grants a digital entitlement or eBook license to a specific peer without coin deduction.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> \'Grant Entitlement\' action modal in user profile.'
        ],
        116 => [
            'category' => 'Admin Digital Assets',
            'purpose' => 'Admin endpoint: Revokes an active digital library entitlement or asset license from a user account.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> \'Revoke License\' button in user asset entitlements table.'
        ],
        117 => [
            'category' => 'Admin Notifications',
            'purpose' => 'Admin endpoint: Lists all in-app and push notification templates configured for automated system triggers.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Notification Templates table.'
        ],
        118 => [
            'category' => 'Admin Notifications',
            'purpose' => 'Admin endpoint: Retrieves the configuration and text template body of a specific notification template by ID.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Notification Template inspector view.'
        ],
        119 => [
            'category' => 'Admin Notifications',
            'purpose' => 'Admin endpoint: Retrieves the paginated audit log of dispatched push/SMS/in-app notifications with delivery status.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Dispatched Notification Logs table.'
        ],
        120 => [
            'category' => 'Admin Notifications',
            'purpose' => 'Admin endpoint: Retries or resends a failed notification log record to the recipient device.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> \'Resend Notification\' action button in log details.'
        ],
        121 => [
            'category' => 'Admin Support Helpdesk',
            'purpose' => 'Admin endpoint: Master admin helpdesk table of all submitted customer support tickets filtered by urgency, department, and status.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Admin Helpdesk Dashboard.'
        ],
        122 => [
            'category' => 'Admin Support Helpdesk',
            'purpose' => 'Admin endpoint: Fetches the complete support ticket thread file including peer profile and full conversation history.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Admin Support Ticket view.'
        ],
        123 => [
            'category' => 'Admin Support Helpdesk',
            'purpose' => 'Admin endpoint: Assigns a support ticket to a designated administrative agent or department.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Staff assignment dropdown selector.'
        ],
        124 => [
            'category' => 'Admin Support Helpdesk',
            'purpose' => 'Admin endpoint: Posts an administrative reply message to a peer support ticket thread.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Admin message composer in ticket conversation view.'
        ],
        125 => [
            'category' => 'Admin Support Helpdesk',
            'purpose' => 'Admin endpoint: Marks a customer support ticket as resolved and closes the ticket thread.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> \'Resolve & Close Ticket\' button.'
        ],
        126 => [
            'category' => 'Admin Store Configuration',
            'purpose' => 'Admin endpoint: Retrieves the complete dictionary of store configuration parameters from `store_configs`.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Store Configuration master panel.'
        ],
        127 => [
            'category' => 'Admin Store Configuration',
            'purpose' => 'Admin endpoint: Retrieves the value and metadata of a specific store configuration key (e.g. `min_delivery_order_coins`).',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Config key detail view.'
        ],
        128 => [
            'category' => 'Admin Store Configuration',
            'purpose' => 'Admin endpoint: Updates the value of a specific store configuration parameter in `store_configs`.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Save Config value button.'
        ],
        129 => [
            'category' => 'Admin Policies',
            'purpose' => 'Admin endpoint: Lists all legal policies, Terms & Conditions, and compliance documents in the system.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Policies Management table.'
        ],
        130 => [
            'category' => 'Admin Policies',
            'purpose' => 'Admin endpoint: Creates a new policy draft with rich markdown body text and version label.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> \'Create Policy Draft\' editor modal.'
        ],
        131 => [
            'category' => 'Admin Policies',
            'purpose' => 'Admin endpoint: Updates an existing policy draft content, title, or summary notes.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Save Policy Draft button.'
        ],
        132 => [
            'category' => 'Admin Policies',
            'purpose' => 'Admin endpoint: Formally publishes a policy version, making it live and visible across customer mobile apps.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> \'Publish Version\' confirmation action.'
        ],
        133 => [
            'category' => 'Admin Reports & Analytics',
            'purpose' => 'Admin endpoint: Generates aggregate sales and financial metrics (total orders, total coins redeemed, average order value in coins) over a custom date window.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Sales & Financial KPI cards and charts.'
        ],
        134 => [
            'category' => 'Admin Reports & Analytics',
            'purpose' => 'Admin endpoint: Generates coin redemption analytics report broken down by Earned Coins versus Bonus Coins expenditure.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Coin Redemption Breakdown donut chart.'
        ],
        135 => [
            'category' => 'Admin Reports & Analytics',
            'purpose' => 'Admin endpoint: Generates coin issuance analytics report tracking total coins minted and awarded across chapters.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Coin Issuance trend bar chart.'
        ],
        136 => [
            'category' => 'Admin Reports & Analytics',
            'purpose' => 'Admin endpoint: Generates VIP membership renewals and subscription coin revenue report over custom date ranges.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> Membership Subscriptions Performance report.'
        ],
        137 => [
            'category' => 'Admin Reports & Analytics',
            'purpose' => 'Admin endpoint: Triggers an asynchronous dataset export and returns a direct CSV download URL for comprehensive offline bookkeeping and auditing.',
            'flutter' => '<strong>Admin Web / Portal Integration:</strong> \'Download CSV Export\' action link.'
        ],
    ];

    if (isset($dict[$num])) return $dict[$num];
    return ['category' => 'Store API', 'purpose' => 'API Endpoint', 'flutter' => 'Integration guide'];
}
