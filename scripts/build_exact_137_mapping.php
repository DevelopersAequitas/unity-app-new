<?php

$meta = json_decode(file_get_contents(__DIR__ . '/api_meta.json'), true);

$entries = [];

foreach ($meta as $num => $item) {
    $t = $item['title'];
    $m = $item['method'];
    $p = $item['path'];
    $sp = $item['source_purpose'];

    // Define category based on path and num
    $cat = 'Customer Store';
    if (str_contains($p, '/admin/')) {
        if (str_contains($p, '/reports/')) $cat = 'Admin Reports & Analytics';
        elseif (str_contains($p, '/config')) $cat = 'Admin Store Configuration';
        elseif (str_contains($p, '/policies')) $cat = 'Admin Policies';
        elseif (str_contains($p, '/support/')) $cat = 'Admin Support Helpdesk';
        elseif (str_contains($p, '/notifications/')) $cat = 'Admin Notifications';
        elseif (str_contains($p, '/entitlements') || str_contains($p, '/digital-assets')) $cat = 'Admin Digital Assets';
        elseif (str_contains($p, '/memberships')) $cat = 'Admin Memberships';
        elseif (str_contains($p, '/campaigns')) $cat = 'Admin Bonus Campaigns';
        elseif (str_contains($p, '/wallets')) $cat = 'Admin Wallets';
        elseif (str_contains($p, '/returns')) $cat = 'Admin Returns';
        elseif (str_contains($p, '/pickup-points')) $cat = 'Admin Pickup Hubs';
        elseif (str_contains($p, '/shipments')) $cat = 'Admin Shipments';
        elseif (str_contains($p, '/orders')) $cat = 'Admin Orders';
        elseif (str_contains($p, '/stock-movements') || str_contains($p, '/stock/')) $cat = 'Admin Inventory & Stock';
        elseif (str_contains($p, '/variants')) $cat = 'Admin Variants';
        elseif (str_contains($p, '/products')) $cat = 'Admin Products';
        elseif (str_contains($p, '/categories')) $cat = 'Admin Categories';
        elseif (str_contains($p, '/metrics')) $cat = 'Admin Metrics';
        else $cat = 'Admin Store Module';
    } else {
        if (str_contains($p, '/wallet')) $cat = 'Customer Wallet';
        elseif (str_contains($p, '/cart')) $cat = 'Customer Cart';
        elseif (str_contains($p, '/addresses') || str_contains($p, '/serviceability') || str_contains($p, '/pickup-points')) $cat = 'Address & Delivery';
        elseif (str_contains($p, '/checkout')) $cat = 'Checkout & Quotes';
        elseif (str_contains($p, '/orders')) $cat = 'Orders & Tracking';
        elseif (str_contains($p, '/returns')) $cat = 'Returns & Refunds';
        elseif (str_contains($p, '/memberships')) $cat = 'Store Memberships';
        elseif (str_contains($p, '/digital-library') || str_contains($p, '/assets')) $cat = 'Digital Library';
        elseif (str_contains($p, '/internal/')) $cat = 'Internal Engine';
        elseif (str_contains($p, '/notifications')) $cat = 'In-App Notifications';
        elseif (str_contains($p, '/webhooks/')) $cat = 'Webhooks';
        elseif (str_contains($p, '/support/')) $cat = 'Support Tickets';
        elseif (str_contains($p, '/policies')) $cat = 'Public Policies';
        else $cat = 'Customer Catalog';
    }

    // Now write a rich, highly specific purpose and flutter integration guide based on the title, path, and method
    $purpose = "";
    $flutter = "";

    // Specific definitions per exact title
    if ($t === "Store Configuration") {
        $purpose = "Returns global runtime configuration for the mobile store module: store open/maintenance status, minimum supported app version requirement, user coin balance split (Earned vs Bonus coins), minimum coin threshold for home delivery, and promotional home banners.";
        $flutter = "<strong>When to Call:</strong> Invoke on app launch / Splash Screen and when entering Store tab.<br><strong>State Handling:</strong> Store values in global state (`storeConfigProvider`). If `current_app_version_supported` is false, present a non-dismissible Force-Update modal dialog directing to App Store / Google Play.";
    } elseif ($t === "Store Banners") {
        $purpose = "Retrieves active promotional banners configured for the mobile store home screen with direct deep-link targets (CATEGORY, PRODUCT, PROMOTION_PAGE).";
        $flutter = "<strong>When to Call:</strong> Call on Store Home screen initialization.<br><strong>UI Handling:</strong> Feed into a `PageView` or `CarouselSlider` with 4-second auto-scroll. On banner tap, route user to the deep-linked product or category screen.";
    } elseif ($t === "User Wallet Balance & Split") {
        $purpose = "Queries the user coin wallet to provide real-time balances: total spendable coins, split between Earned Coins and Bonus Coins, lifetime earnings/expenditures, and account lock state (ACTIVE, FROZEN, CLOSED).";
        $flutter = "<strong>When to Call:</strong> Call on Store App Bar load, Wallet Screen, and immediately after placing an order or receiving a refund.<br><strong>UI Handling:</strong> Render formatted coin counters with coin icons. If `wallet_state == 'FROZEN'`, disable checkout and display an alert dialog.";
    } elseif ($t === "Wallet Ledger History") {
        $purpose = "Returns paginated passbook transactions from `coins_ledger` with filters by coin bucket (EARNED, BONUS), reference type (ORDER, RETURN_REFUND, BONUS_GRANT), and date range.";
        $flutter = "<strong>When to Call:</strong> Use on Wallet Ledger screen with infinite scrolling list.<br><strong>UI Handling:</strong> Color credit entries green (`+500 Coins`) and debit deductions dark grey/red (`-1000 Coins`). Tapping a record navigates to the associated order details.";
    } elseif ($t === "Wallet Monthly Summary") {
        $purpose = "Aggregates the user's total coin earnings, redemptions, and pending hold amounts for the current calendar month compared against previous periods.";
        $flutter = "<strong>When to Call:</strong> Call on user rewards dashboard or profile analytics widget.<br><strong>UI Handling:</strong> Render comparative monthly progress charts comparing earned coins against redeemed coins.";
    } elseif ($t === "Wallet Status & Freeze Info") {
        $purpose = "Checks whether the user wallet is in good standing or frozen due to compliance reviews, returning freeze reason remarks and timestamp.";
        $flutter = "<strong>When to Call:</strong> Call before entering the Checkout flow or on Wallet settings.<br><strong>UI Handling:</strong> If frozen, render a warning banner and disable 'Proceed to Checkout' buttons.";
    } elseif ($t === "Store Categories") {
        $purpose = "Fetches the list of active merchandise categories (Apparel, Accessories, Books, Tech) with thumbnail icons, slug names, and active product counts.";
        $flutter = "<strong>When to Call:</strong> Call once on Store Home screen load and cache locally.<br><strong>UI Handling:</strong> Render as horizontal category chips or category card grid with product counts.";
    } elseif ($t === "Product Listing & Filtering") {
        $purpose = "Queries the product catalog with filtering parameters: category ID, search keyword, coin price ranges (min/max), physical vs digital types, and sort sequences.";
        $flutter = "<strong>When to Call:</strong> Main catalog browse screen and search result view.<br><strong>Performance:</strong> Implement a 300ms debounce on the search bar. Use 20-item chunk pagination with pull-to-refresh.";
    } elseif ($t === "Product Details") {
        $purpose = "Retrieves complete product specifications, high-res image gallery, HTML description, delivery options (Courier vs Hub Pickup), and all configured SKU variants with live stock levels.";
        $flutter = "<strong>When to Call:</strong> Call upon opening the Product Details screen.<br><strong>UI Handling:</strong> Bind variant selector chips (Size/Color). Update displayed coin price and stock badge in real-time as variants are selected.";
    } elseif ($t === "Featured Products") {
        $purpose = "Fetches curated spotlight products flagged by administrators as featured items for promotional carousels.";
        $flutter = "<strong>When to Call:</strong> Call on Store Home screen to render the 'Featured Collections' horizontal slider.<br><strong>UI Handling:</strong> Display product cards with special 'Featured' ribbon badges.";
    } elseif ($t === "Product Variants & Real-Time Stock") {
        $purpose = "Queries live SKU-level stock quantities and pricing for a specific product variant, accounting for active checkout quote reservations.";
        $flutter = "<strong>When to Call:</strong> Call when user chooses a variant in the quick-order sheet.<br><strong>UI Handling:</strong> If `stock_quantity <= 0`, disable purchase action and show an 'Out of Stock' chip.";
    } elseif ($t === "Get User Cart") {
        $purpose = "Fetches the active shopping cart with all line items, variant details, coin subtotal, shipping estimation, and out-of-stock validation flags.";
        $flutter = "<strong>When to Call:</strong> Call on Cart screen load and after any item quantity modification.<br><strong>UI Handling:</strong> Render items with quantity steppers (`-`, `+`) and a sticky bottom checkout bar.";
    } elseif ($t === "Add Item to Cart") {
        $purpose = "Adds a product variant and quantity to the cart, enforcing stock availability and monthly peer purchase limits.";
        $flutter = "<strong>When to Call:</strong> Trigger on 'Add to Cart' button tap.<br><strong>UI Handling:</strong> Show toast notification with 'View Cart' CTA and increment top cart badge counter.";
    } elseif ($t === "Update Cart Item Quantity") {
        $purpose = "Adjusts the quantity of an existing line item in the cart, verifying live inventory limits before saving.";
        $flutter = "<strong>When to Call:</strong> Trigger on cart quantity stepper clicks (`+` or `-`).<br><strong>UI Handling:</strong> Optimistic local UI update with server rollback if a 422 stock error is returned.";
    } elseif ($t === "Remove Item from Cart") {
        $purpose = "Deletes a specific line item from the shopping cart and recalculates total payable coins.";
        $flutter = "<strong>When to Call:</strong> Trigger on delete icon or swipe-to-delete gesture on a cart row.<br><strong>UI Handling:</strong> Animate item removal and update bottom total bar.";
    } elseif ($t === "Clear Entire Cart") {
        $purpose = "Empties all items from the active user cart in a single atomic database operation.";
        $flutter = "<strong>When to Call:</strong> Trigger on 'Empty Cart' action or following successful order completion.<br><strong>UI Handling:</strong> Prompt with confirmation dialog before clearing; then show empty cart state.";
    } elseif ($t === "List Saved Addresses") {
        $purpose = "Retrieves all saved delivery addresses for the authenticated peer, including contact info, address lines, pincode, and default flag.";
        $flutter = "<strong>When to Call:</strong> Call on Checkout Step 1 and Profile -> Saved Addresses.<br><strong>UI Handling:</strong> Render address selection cards with radio selection for the active destination.";
    } elseif ($t === "Create Delivery Address") {
        $purpose = "Validates and creates a new delivery shipping address in `user_addresses`.";
        $flutter = "<strong>When to Call:</strong> Trigger from 'Add New Address' modal form.<br><strong>UI Handling:</strong> Form validation for phone and 6-digit pincode; auto-select upon creation.";
    } elseif ($t === "Get Address by ID") {
        $purpose = "Fetches full details of a specific saved address by its address UUID.";
        $flutter = "<strong>When to Call:</strong> Call before opening the Edit Address bottom sheet.<br><strong>UI Handling:</strong> Pre-populate text form fields.";
    } elseif ($t === "Update Address") {
        $purpose = "Modifies existing shipping address details in the database.";
        $flutter = "<strong>When to Call:</strong> Trigger on 'Save Address' button.<br><strong>UI Handling:</strong> Show submit spinner and refresh address list on success.";
    } elseif ($t === "Delete Address") {
        $purpose = "Soft deletes a delivery address from the user profile if not linked to an active pending dispatch.";
        $flutter = "<strong>When to Call:</strong> Trigger on delete button in address management.<br><strong>UI Handling:</strong> Confirm with dialog and remove card from list.";
    } elseif ($t === "Check Pincode Serviceability") {
        $purpose = "Validates if a 6-digit postal pincode is covered by courier partners and returns delivery transit day estimates.";
        $flutter = "<strong>When to Call:</strong> Trigger with 300ms debounce as soon as user types 6 digits in the pincode field.<br><strong>UI Handling:</strong> If not serviceable, show red warning and suggest selecting Central Pickup Hub.";
    } elseif ($t === "List Pickup Points") {
        $purpose = "Retrieves all active Peers Central Hub takeaway pickup points where peers can collect orders with zero delivery coins.";
        $flutter = "<strong>When to Call:</strong> Call when user chooses 'Pickup from Hub' delivery mode on Checkout.<br><strong>UI Handling:</strong> Render hub cards with address, manager contact, and operating hours.";
    } elseif ($t === "Get Pickup Point Detail") {
        $purpose = "Fetches complete operational details and Google Maps coordinate info for a specific pickup hub.";
        $flutter = "<strong>When to Call:</strong> Call when user taps on a pickup hub card.<br><strong>UI Handling:</strong> Display detailed location modal with 'Open in Maps' action.";
    } elseif ($t === "Generate Checkout Quote") {
        $purpose = "Creates a 15-minute locked checkout valuation quote (`checkout_quotes`), calculating coin balances, delivery charges, and assessing if OTP challenge is mandatory.";
        $flutter = "<strong>When to Call:</strong> Call on opening Order Review / Checkout summary.<br><strong>UI Handling:</strong> Start a 15-minute visual countdown timer. If `otp_required: true`, open OTP bottom sheet upon placing order.";
    } elseif ($t === "Send Checkout OTP Challenge") {
        $purpose = "Dispatches a 6-digit SMS/WhatsApp verification OTP for high-value coin redemptions or address modifications.";
        $flutter = "<strong>When to Call:</strong> Trigger when placing order if quote indicated OTP requirement.<br><strong>UI Handling:</strong> Render 6-digit pin input dialog with 60-second resend timer.";
    } elseif ($t === "Verify Checkout OTP Challenge") {
        $purpose = "Validates the 6-digit OTP passcode against the active challenge record and unlocks the quote for order placement.";
        $flutter = "<strong>When to Call:</strong> Auto-trigger when 6th OTP digit is entered.<br><strong>UI Handling:</strong> On success, immediately proceed to call Place Order API.";
    } elseif ($t === "Place Order (Atomic Transaction)") {
        $purpose = "Atomically finalizes purchase inside a database transaction: locks wallet, debits coins (Bonus first, then Earned), reserves inventory, and creates immutable order records.";
        $flutter = "<strong>When to Call:</strong> Trigger on final 'Confirm & Place Order' tap.<br><strong>Crucial Rule:</strong> Pass a unique UUID in `Idempotency-Key` header to prevent duplicate orders on poor connectivity. Navigate to Order Success celebration.";
    } elseif ($t === "List User Orders") {
        $purpose = "Retrieves paginated order history for the authenticated peer with status filtering (PLACED, PROCESSING, SHIPPED, DELIVERED, CANCELLED, RETURNED).";
        $flutter = "<strong>When to Call:</strong> Call on 'My Orders' screen with pull-to-refresh.<br><strong>UI Handling:</strong> Display order cards with item thumbnails, total coins, and color-coded status badges.";
    } elseif ($t === "Get Order Details") {
        $purpose = "Fetches comprehensive order details: items, variants, shipping address or pickup hub, payment coin breakdown, tracking status, and return eligibility.";
        $flutter = "<strong>When to Call:</strong> Call upon selecting an order card.<br><strong>UI Handling:</strong> Render full order stepper, invoice download CTA, and 'Return Items' button if eligible.";
    } elseif ($t === "Cancel Order") {
        $purpose = "Allows peer to cancel an order prior to SHIPPED status, instantly executing an automatic coin refund transaction to the user wallet.";
        $flutter = "<strong>When to Call:</strong> Trigger from 'Cancel Order' button on eligible orders.<br><strong>UI Handling:</strong> Show reason selection sheet; refresh wallet and order status on success.";
    } elseif ($t === "Download Order Invoice / Receipt") {
        $purpose = "Generates or retrieves the official PDF tax invoice and coin redemption receipt for a completed order.";
        $flutter = "<strong>When to Call:</strong> Trigger on 'Download Receipt' button.<br><strong>UI Handling:</strong> Open download stream via `flutter_downloader` or view inline.";
    } elseif ($t === "Track Courier Shipment") {
        $purpose = "Fetches real-time courier tracking milestones (AWB number, carrier name, checkpoint history, estimated delivery date).";
        $flutter = "<strong>When to Call:</strong> Call on Order Tracking view.<br><strong>UI Handling:</strong> Render vertical progress timeline with carrier live link.";
    } elseif ($t === "Get Order Pickup Verification Code & QR") {
        $purpose = "Retrieves the secure 6-digit pickup PIN and scannable QR code for hub pickup orders.";
        $flutter = "<strong>When to Call:</strong> Call on Order Details when delivery mode is PICKUP.<br><strong>UI Handling:</strong> Render high-contrast QR code for warehouse coordinator scanning.";
    } elseif ($t === "List User Return Requests") {
        $purpose = "Retrieves all submitted return requests filed by the peer with current quality inspection status (PENDING, APPROVED, REJECTED, REFUNDED).";
        $flutter = "<strong>When to Call:</strong> Call on 'My Returns' tab in user profile.<br><strong>UI Handling:</strong> Display return cards with inspection progress tags.";
    } elseif ($t === "Submit Return Request") {
        $purpose = "Submits a return request within the 7-day delivery window with return reason description and photo evidence.";
        $flutter = "<strong>When to Call:</strong> Trigger from Order Details 'Return Item' CTA.<br><strong>UI Handling:</strong> Form with camera/gallery picker requiring at least one photo upload.";
    } elseif ($t === "Get Return Request Details") {
        $purpose = "Fetches detailed inspection history and admin resolution notes for a specific return ticket.";
        $flutter = "<strong>When to Call:</strong> Call upon selecting a return card.<br><strong>UI Handling:</strong> Display inspection checklist and admin feedback.";
    } elseif ($t === "Cancel Return Request") {
        $purpose = "Allows peer to withdraw an open return request before warehouse collection.";
        $flutter = "<strong>When to Call:</strong> Trigger on 'Cancel Return' button.<br><strong>UI Handling:</strong> Confirm with dialog and update status chip to CANCELLED.";
    } elseif ($t === "List Store Membership Passes") {
        $purpose = "Lists available VIP membership passes (Gold, Platinum) with coin pricing, duration, and perks (free delivery, exclusive drops).";
        $flutter = "<strong>When to Call:</strong> Call on VIP Memberships screen.<br><strong>UI Handling:</strong> Render gradient membership cards with 'Subscribe' CTA.";
    } elseif ($t === "Purchase Store Membership Pass") {
        $purpose = "Debits required coins from user wallet and activates store privilege pass in `user_memberships`.";
        $flutter = "<strong>When to Call:</strong> Trigger when confirming membership purchase.<br><strong>UI Handling:</strong> Play celebration animation and update user VIP badge.";
    } elseif ($t === "Get Active User Membership Pass") {
        $purpose = "Retrieves the active membership pass details, benefits, and expiration timestamp for the authenticated peer.";
        $flutter = "<strong>When to Call:</strong> Call on profile load and checkout to auto-apply member perks.<br><strong>UI Handling:</strong> Display VIP badge in app header.";
    } elseif ($t === "Cancel Membership Auto-Renewal") {
        $purpose = "Disables automated renewal for an active store membership pass.";
        $flutter = "<strong>When to Call:</strong> Trigger on Membership Settings toggle.<br><strong>UI Handling:</strong> Confirm with modal and toggle off auto-renew switch.";
    } elseif ($t === "List User Digital Assets & Entitlements") {
        $purpose = "Lists all unlocked digital assets, eBooks, training guides, and masterclasses owned by the peer.";
        $flutter = "<strong>When to Call:</strong> Call on 'Digital Library' tab.<br><strong>UI Handling:</strong> Render eBook/media cards with 'Read PDF' or 'Stream' action.";
    } elseif ($t === "Get Digital Asset Details") {
        $purpose = "Fetches detailed metadata, file format, and description for a specific digital asset.";
        $flutter = "<strong>When to Call:</strong> Call upon selecting a digital library card.<br><strong>UI Handling:</strong> Render overview screen with author info.";
    } elseif ($t === "Generate Signed Digital Download URL") {
        $purpose = "Generates a time-expiring (15-minute) signed URL to securely stream or download digital files.";
        $flutter = "<strong>When to Call:</strong> Trigger when tapping 'Download' or 'Read Now'.<br><strong>UI Handling:</strong> Launch signed URL directly in an in-app PDF viewer or video player.";
    } elseif ($t === "Internal Engine Credit Coins") {
        $purpose = "Internal microservice API: Credits coins to a peer wallet ledger when meeting milestones, referrals, or closed deals are verified.";
        $flutter = "<strong>Microservice Integration:</strong> Secured via internal server bearer token. Not directly invoked by Flutter mobile client.";
    } elseif ($t === "Internal Engine Debit Coins") {
        $purpose = "Internal microservice API: Debits coins from a peer wallet for platform services or cross-system penalties.";
        $flutter = "<strong>Microservice Integration:</strong> Atomic transaction ledger debit with pre-balance validation.";
    } elseif ($t === "List In-App Notifications") {
        $purpose = "Retrieves paginated in-app alerts, order status updates, coin credit notices, and return updates.";
        $flutter = "<strong>When to Call:</strong> Call on Notifications screen.<br><strong>UI Handling:</strong> Render notifications list with unread markers and deep-link routing on tap.";
    } elseif ($t === "Get Unread Notifications Count") {
        $purpose = "Fast endpoint returning the count of unread notifications for badge rendering.";
        $flutter = "<strong>When to Call:</strong> Call on app launch and after receiving push notifications.<br><strong>UI Handling:</strong> Render numeric red badge over notification bell.";
    } elseif ($t === "Mark Notification as Read") {
        $purpose = "Marks a specific notification as read by ID.";
        $flutter = "<strong>When to Call:</strong> Trigger when user taps a notification tile.<br><strong>UI Handling:</strong> Dim card background and decrement unread counter.";
    } elseif ($t === "Mark All Notifications as Read") {
        $purpose = "Marks all unread notifications as read in a single batch request.";
        $flutter = "<strong>When to Call:</strong> Trigger on 'Mark All Read' action.<br><strong>UI Handling:</strong> Clear all notification badges.";
    } elseif ($t === "Delete Notification") {
        $purpose = "Deletes a single notification record from the user feed.";
        $flutter = "<strong>When to Call:</strong> Trigger on swipe-to-delete gesture.<br><strong>UI Handling:</strong> Animate item removal from list.";
    } elseif ($t === "Clear All Notifications") {
        $purpose = "Deletes all notification items from the user feed.";
        $flutter = "<strong>When to Call:</strong> Trigger on 'Clear All' button.<br><strong>UI Handling:</strong> Show empty notifications placeholder.";
    } elseif ($t === "Get Notification Preferences") {
        $purpose = "Retrieves user channel notification preferences (SMS, Push, Email).";
        $flutter = "<strong>When to Call:</strong> Call on Notification Settings screen.<br><strong>UI Handling:</strong> Bind toggle switches for each channel.";
    } elseif ($t === "Update Notification Preferences") {
        $purpose = "Saves user channel notification preferences in the database.";
        $flutter = "<strong>When to Call:</strong> Trigger when user toggles a preference switch.<br><strong>UI Handling:</strong> Persist changes with optimistic UI feedback.";
    } elseif ($t === "Courier Delivery Webhook") {
        $purpose = "Receives real-time shipment milestone webhooks from logistics partners (Delhivery / BlueDart) to advance order status.";
        $flutter = "<strong>Server-to-Server Webhook:</strong> Validates webhook secret signature and dispatches FCM push notifications to Flutter clients.";
    } elseif ($t === "List User Support Tickets") {
        $purpose = "Retrieves customer support tickets submitted by the peer with status (OPEN, IN_PROGRESS, RESOLVED).";
        $flutter = "<strong>When to Call:</strong> Call on Helpdesk / Support screen.<br><strong>UI Handling:</strong> Render ticket cards with subject, ID, and status tag.";
    } elseif ($t === "Create Support Ticket") {
        $purpose = "Creates a new customer support ticket with subject, category, description, order link, and attachments.";
        $flutter = "<strong>When to Call:</strong> Trigger from 'Contact Support' form.<br><strong>UI Handling:</strong> Submit form and navigate to the ticket chat thread.";
    } elseif ($t === "Get Support Ticket Messages") {
        $purpose = "Fetches the full conversation thread for a specific support ticket.";
        $flutter = "<strong>When to Call:</strong> Call upon entering Ticket Chat screen.<br><strong>UI Handling:</strong> Render chat bubble stream differentiating Peer and Admin replies.";
    } elseif ($t === "Send Support Ticket Message") {
        $purpose = "Appends a new reply message or photo attachment to an ongoing support ticket.";
        $flutter = "<strong>When to Call:</strong> Trigger on chat send button.<br><strong>UI Handling:</strong> Append message optimistically to the bottom of the chat list.";
    } elseif ($t === "Close Support Ticket") {
        $purpose = "Allows peer to mark a support ticket as resolved and close the thread.";
        $flutter = "<strong>When to Call:</strong> Trigger on 'Close Ticket' button.<br><strong>UI Handling:</strong> Update status badge to RESOLVED and disable composer.";
    } elseif ($t === "List Store Policies") {
        $purpose = "Retrieves the list of active public legal policies (Terms & Conditions, Coin Policy, Return Policy, Privacy).";
        $flutter = "<strong>When to Call:</strong> Call on Store Settings -> Legal Policies.<br><strong>UI Handling:</strong> Render list of policy titles with navigation arrows.";
    } elseif ($t === "Get Store Policy Content") {
        $purpose = "Fetches full rich HTML/Markdown text content for a specific policy by slug.";
        $flutter = "<strong>When to Call:</strong> Call upon selecting a policy item.<br><strong>UI Handling:</strong> Render formatted content using `flutter_widget_from_html`.";
    } elseif ($t === "Admin Store Dashboard Metrics") {
        $purpose = "Admin summary endpoint: Computes total orders placed, aggregate coins redeemed, active returns pending inspection, low stock alerts, and open tickets.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Poll every 60s on Admin Dashboard to populate summary statistics cards.";
    } elseif ($t === "Admin Inventory & Stock Metrics") {
        $purpose = "Admin inventory endpoint: Calculates total SKU count, units in warehouse, reserved checkout units, and out-of-stock items.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Render on Inventory Management overview with donut charts.";
    } elseif ($t === "Admin List Categories") {
        $purpose = "Admin endpoint: Retrieves all categories including inactive ones with sequence ordering and product counts.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Power the Categories management table with drag-and-drop sort.";
    } elseif ($t === "Admin Create Category") {
        $purpose = "Admin endpoint: Creates a new merchandise category with custom name, slug, image, sequence order, and visibility toggle.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Submit from 'Add Category' modal dialog.";
    } elseif ($t === "Admin Get Category") {
        $purpose = "Admin endpoint: Retrieves full category record by ID for editing.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Pre-fill category edit modal inputs.";
    } elseif ($t === "Admin Update Category") {
        $purpose = "Admin endpoint: Updates category title, slug, image URL, sequence order, and visibility state.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Save modifications on category edit form.";
    } elseif ($t === "Admin Delete Category") {
        $purpose = "Admin endpoint: Deletes or archives a category if no active products are attached.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Trigger from delete category confirmation.";
    } elseif ($t === "Admin List Products") {
        $purpose = "Admin endpoint: Paginated master list of all products with stock counts, variant totals, visibility status, and coin prices.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Main Admin Products table with multi-column filtering and bulk actions.";
    } elseif ($t === "Admin Create Product") {
        $purpose = "Admin endpoint: Creates a new product record with base specs, description, category ID, and delivery flags.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Trigger from Admin 'Add Product' wizard.";
    } elseif ($t === "Admin Get Product") {
        $purpose = "Admin endpoint: Fetches deep administrative product profile including all SKU variants, inventory logs, and sales metrics.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Populate Product Management detail view.";
    } elseif ($t === "Admin Update Product") {
        $purpose = "Admin endpoint: Updates product title, description, category mapping, and base pricing.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Save edits on Product Edit form.";
    } elseif ($t === "Admin Delete Product") {
        $purpose = "Admin endpoint: Soft-deletes or archives a product from the catalog.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Trigger on product archive action.";
    } elseif ($t === "Admin List Product Variants") {
        $purpose = "Admin endpoint: Lists all SKU variants (sizes, colors, attributes) configured for a given product ID.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Render SKU variant table in Product Details.";
    } elseif ($t === "Admin Create Product Variant") {
        $purpose = "Admin endpoint: Creates a new SKU variant with SKU code, attributes, coin price, and initial stock quantity.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Trigger from 'Add Variant' modal.";
    } elseif ($t === "Admin Update Product Variant") {
        $purpose = "Admin endpoint: Updates SKU variant attributes, coin pricing, or barcode identifier.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Save variant modifications.";
    } elseif ($t === "Admin Delete Product Variant") {
        $purpose = "Admin endpoint: Deletes an SKU variant if no active orders depend on it.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Remove variant row from table.";
    } elseif ($t === "Admin List Stock Movements") {
        $purpose = "Admin endpoint: Retrieves complete inventory stock audit logs and historical warehouse stock adjustments.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Stock Movement Audit screen.";
    } elseif ($t === "Admin Adjust Stock") {
        $purpose = "Admin endpoint: Executes manual inventory stock replenishment or deduction for an SKU with audit reason notes.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Trigger from 'Adjust Stock' modal.";
    } elseif ($t === "Admin List Orders") {
        $purpose = "Admin endpoint: Master admin orders table with comprehensive filters by status, peer name, date range, and coin bucket.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Main Admin Orders management view.";
    } elseif ($t === "Admin Get Order Details") {
        $purpose = "Admin endpoint: Fetches complete administrative order file including full audit logs, customer details, shipment records, and ledger IDs.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Admin Order Inspector view.";
    } elseif ($t === "Admin Update Order Status") {
        $purpose = "Admin endpoint: Advances order status through workflow (PLACED -> PROCESSING -> SHIPPED -> DELIVERED).";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Order status dropdown action.";
    } elseif ($t === "Admin Cancel & Refund Order") {
        $purpose = "Admin endpoint: Cancels an order administratively and executes automatic coin refund credit to peer wallet.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Trigger from 'Cancel & Refund' button.";
    } elseif ($t === "Admin List Shipments") {
        $purpose = "Admin endpoint: Lists all active courier shipments and fulfillment batches across warehouses.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Logistics & Dispatch Management view.";
    } elseif ($t === "Admin Create Shipment & AWB") {
        $purpose = "Admin endpoint: Creates a new courier shipment dispatch record, assigning Airway Bill (AWB) number and logistics carrier to an order.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Trigger from 'Generate AWB & Dispatch' modal.";
    } elseif ($t === "Admin Get Shipment") {
        $purpose = "Admin endpoint: Retrieves shipment details and courier checkpoint history.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Shipment tracking dialog.";
    } elseif ($t === "Admin Update Shipment Status") {
        $purpose = "Admin endpoint: Updates shipment tracking milestones and marks packages as Out For Delivery or Delivered.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Manual tracking update modal.";
    } elseif ($t === "Admin List Pickup Hubs") {
        $purpose = "Admin endpoint: Lists all configured central warehouse pickup hubs and physical collection points.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Pickup Hubs Configuration table.";
    } elseif ($t === "Admin Create Pickup Hub") {
        $purpose = "Admin endpoint: Creates a new physical pickup hub location with operational hours and coordinator contact.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> 'Add New Hub' form.";
    } elseif ($t === "Admin List Returns") {
        $purpose = "Admin endpoint: Admin table of all peer return requests filtered by inspection state.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Returns Inspection queue.";
    } elseif ($t === "Admin Get Return Details") {
        $purpose = "Admin endpoint: Fetches full return file with uploaded customer damage photos and item specifications.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Return Quality Assessment view.";
    } elseif ($t === "Admin Approve Return & Refund") {
        $purpose = "Admin endpoint: Approves a return request and automatically triggers the refund coin ledger credit to user wallet.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> 'Approve Return & Refund' button.";
    } elseif ($t === "Admin Reject Return") {
        $purpose = "Admin endpoint: Rejects a return request with mandatory quality rejection reason remarks.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> 'Reject Return' dialog.";
    } elseif ($t === "Admin List Wallets") {
        $purpose = "Admin endpoint: Admin view of all peer wallets with balance summaries, lifetime metrics, and account status.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Peer Wallets table.";
    } elseif ($t === "Admin Get Wallet Ledger") {
        $purpose = "Admin endpoint: Fetches detailed coin ledger and audit history for a specific peer wallet.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> User Wallet Deep-Dive modal.";
    } elseif ($t === "Admin Initiate Coin Adjustment (Maker)") {
        $purpose = "Admin endpoint: Initiates a Maker coin adjustment request (credit/debit) requiring secondary Checker approval.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> 'Initiate Coin Adjustment' form.";
    } elseif ($t === "Admin Approve Coin Adjustment (Checker)") {
        $purpose = "Admin endpoint: Approves a pending Maker-Checker coin adjustment request and executes ledger credit/debit.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Checker Approval action.";
    } elseif ($t === "Admin Reject Coin Adjustment (Checker)") {
        $purpose = "Admin endpoint: Rejects a pending Maker-Checker coin adjustment request.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Checker Rejection action.";
    } elseif ($t === "Admin Freeze Wallet") {
        $purpose = "Admin endpoint: Administrative override to freeze a peer wallet against further coin redemptions.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> 'Freeze Wallet' emergency toggle.";
    } elseif ($t === "Admin Unfreeze Wallet") {
        $purpose = "Admin endpoint: Unfreezes a previously locked peer wallet, restoring full redemption privileges.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> 'Unfreeze Wallet' button.";
    } elseif ($t === "Admin List Bonus Campaigns") {
        $purpose = "Admin endpoint: Lists all promotional bonus coin distribution campaigns and grant batches.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Bonus Campaigns table.";
    } elseif ($t === "Admin Create Bonus Campaign") {
        $purpose = "Admin endpoint: Creates a new bulk bonus coin distribution campaign for eligible peer chapters.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> 'Create Bonus Campaign' wizard.";
    } elseif ($t === "Admin Get Bonus Campaign") {
        $purpose = "Admin endpoint: Retrieves detailed metrics and peer recipient list for a specific bonus campaign.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Bonus Campaign Inspection view.";
    } elseif ($t === "Admin Update Bonus Campaign") {
        $purpose = "Admin endpoint: Updates promotional bonus campaign parameters or schedule.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Edit Campaign form.";
    } elseif ($t === "Admin Cancel Bonus Campaign") {
        $purpose = "Admin endpoint: Cancels or revokes an active bonus coin campaign.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Cancel Campaign dialog.";
    } elseif ($t === "Admin List Membership Plans") {
        $purpose = "Admin endpoint: Lists all store membership tiers and VIP subscription plans configured in the system.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Membership Plans table.";
    } elseif ($t === "Admin Create Membership Plan") {
        $purpose = "Admin endpoint: Creates a new store membership plan with coin price, duration days, and privilege perks.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> 'Create Membership Plan' modal.";
    } elseif ($t === "Admin Update Membership Plan") {
        $purpose = "Admin endpoint: Updates membership plan pricing, perk description, or active status.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Edit Plan modal.";
    } elseif ($t === "Admin Delete Membership Plan") {
        $purpose = "Admin endpoint: Archives or deletes a membership tier from public store listing.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Delete Plan confirmation.";
    } elseif ($t === "Admin List Digital Assets") {
        $purpose = "Admin endpoint: Lists all digital assets, masterclasses, and eBooks uploaded in the digital library repository.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Digital Assets catalog table.";
    } elseif ($t === "Admin Create Digital Asset") {
        $purpose = "Admin endpoint: Uploads a new digital asset record with file storage path, coin price, and access entitlement rules.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> 'Upload New Digital Asset' form.";
    } elseif ($t === "Admin Manually Grant Entitlement") {
        $purpose = "Admin endpoint: Manually grants a digital entitlement or eBook license to a specific peer without coin deduction.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> 'Grant Entitlement' action modal in user profile.";
    } elseif ($t === "Admin Revoke Entitlement") {
        $purpose = "Admin endpoint: Revokes an active digital library entitlement or asset license from a user account.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> 'Revoke License' button in user asset entitlements table.";
    } elseif ($t === "Admin Notification Templates List") {
        $purpose = "Admin endpoint: Lists all in-app and push notification templates configured for automated system triggers.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Notification Templates table.";
    } elseif ($t === "Admin Get Notification Template") {
        $purpose = "Admin endpoint: Retrieves the configuration and text template body of a specific notification template by ID.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Notification Template inspector view.";
    } elseif ($t === "Admin Notification Logs") {
        $purpose = "Admin endpoint: Retrieves the paginated audit log of dispatched push/SMS/in-app notifications with delivery status.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Dispatched Notification Logs table.";
    } elseif ($t === "Admin Resend Notification Log") {
        $purpose = "Admin endpoint: Retries or resends a failed notification log record to the recipient device.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> 'Resend Notification' action button in log details.";
    } elseif ($t === "Admin List Support Tickets") {
        $purpose = "Admin endpoint: Master admin helpdesk table of all submitted customer support tickets filtered by urgency, department, and status.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Admin Helpdesk Dashboard.";
    } elseif ($t === "Admin Get Support Ticket Thread") {
        $purpose = "Admin endpoint: Fetches the complete support ticket thread file including peer profile and full conversation history.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Admin Support Ticket view.";
    } elseif ($t === "Admin Assign Support Ticket") {
        $purpose = "Admin endpoint: Assigns a support ticket to a designated administrative agent or department.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Staff assignment dropdown selector.";
    } elseif ($t === "Admin Reply to Support Ticket") {
        $purpose = "Admin endpoint: Posts an administrative reply message to a peer support ticket thread.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Admin message composer in ticket conversation view.";
    } elseif ($t === "Admin Resolve Support Ticket") {
        $purpose = "Admin endpoint: Marks a customer support ticket as resolved and closes the ticket thread.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> 'Resolve & Close Ticket' button.";
    } elseif ($t === "Admin List Store Configs") {
        $purpose = "Admin endpoint: Retrieves the complete dictionary of store configuration parameters from `store_configs`.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Store Configuration master panel.";
    } elseif ($t === "Admin Get Store Config") {
        $purpose = "Admin endpoint: Retrieves the value and metadata of a specific store configuration key (e.g. `min_delivery_order_coins`).";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Config key detail view.";
    } elseif ($t === "Admin Update Store Config") {
        $purpose = "Admin endpoint: Updates the value of a specific store configuration parameter in `store_configs`.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Save Config value button.";
    } elseif ($t === "Admin List Policies") {
        $purpose = "Admin endpoint: Lists all legal policies, Terms & Conditions, and compliance documents in the system.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Policies Management table.";
    } elseif ($t === "Admin Create Policy") {
        $purpose = "Admin endpoint: Creates a new policy draft with rich markdown body text and version label.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> 'Create Policy Draft' editor modal.";
    } elseif ($t === "Admin Update Policy") {
        $purpose = "Admin endpoint: Updates an existing policy draft content, title, or summary notes.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Save Policy Draft button.";
    } elseif ($t === "Admin Publish Policy Version") {
        $purpose = "Admin endpoint: Formally publishes a policy version, making it live and visible across customer mobile apps.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> 'Publish Version' confirmation action.";
    } elseif ($t === "Admin Sales Report") {
        $purpose = "Admin endpoint: Generates aggregate sales and financial metrics (total orders, total coins redeemed, average order value in coins) over a custom date window.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Sales & Financial KPI cards and charts.";
    } elseif ($t === "Admin Coin Redemption Report") {
        $purpose = "Admin endpoint: Generates coin redemption analytics report broken down by Earned Coins versus Bonus Coins expenditure.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Coin Redemption Breakdown donut chart.";
    } elseif ($t === "Admin Coin Issuance Report") {
        $purpose = "Admin endpoint: Generates coin issuance analytics report tracking total coins minted and awarded across chapters.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Coin Issuance trend bar chart.";
    } elseif ($t === "Admin Membership Renewals Report") {
        $purpose = "Admin endpoint: Generates VIP membership renewals and subscription coin revenue report over custom date ranges.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> Membership Subscriptions Performance report.";
    } elseif ($t === "Admin Export Reports (CSV / XLSX)") {
        $purpose = "Admin endpoint: Triggers an asynchronous dataset export and returns a direct CSV download URL for comprehensive offline bookkeeping and auditing.";
        $flutter = "<strong>Admin Web / Portal Integration:</strong> 'Download CSV Export' action link.";
    } else {
        $purpose = "Handles {$m} operation for {$t} on `{$p}` with authentication and authorization checks.";
        $flutter = "<strong>Frontend Integration:</strong> Invoke `{$m} {$p}` with active Bearer token.";
    }

    $entries[$num] = [
        'category' => $cat,
        'purpose' => $purpose,
        'flutter' => $flutter
    ];
}

$dictCode = "<?php\n\nfunction getApiSpecificData(\$num, \$title, \$method, \$path) {\n    static \$dict = [\n";

foreach ($entries as $num => $d) {
    $dictCode .= "        {$num} => [\n";
    $dictCode .= "            'category' => '" . addslashes($d['category']) . "',\n";
    $dictCode .= "            'purpose' => '" . addslashes($d['purpose']) . "',\n";
    $dictCode .= "            'flutter' => '" . addslashes($d['flutter']) . "'\n";
    $dictCode .= "        ],\n";
}

$dictCode .= "    ];\n\n";
$dictCode .= "    if (isset(\$dict[\$num])) return \$dict[\$num];\n";
$dictCode .= "    return ['category' => 'Store API', 'purpose' => 'API Endpoint', 'flutter' => 'Integration guide'];\n";
$dictCode .= "}\n";

file_put_contents(__DIR__ . '/api_descriptions_data.php', $dictCode);
echo "Generated EXACT title-matched descriptions for all " . count($entries) . " APIs.\n";

