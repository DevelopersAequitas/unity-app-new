<?php

$descriptions = [
    1 => [
        'cat' => 'Customer Store & Banners',
        'p' => 'Provides essential global runtime configuration for the store module. It returns store availability status, current minimum app version requirements to trigger mandatory upgrade dialogs, the authenticated user coin wallet balance (with Earned vs Bonus coin breakdown), delivery coin threshold rules, and top promotional banners.',
        'f' => '<strong>When to Call:</strong> Invoke during initial app startup / Splash Screen or upon switching to the Store tab.<br><strong>State Handling:</strong> Store `store_enabled`, `minimum_app_version`, and user coin balances in global state (e.g. Riverpod `storeConfigProvider`). If `current_app_version_supported` is false, pop a non-dismissible Force-Update modal dialog directing the user to Play Store / App Store.'
    ],
    2 => [
        'cat' => 'Customer Store & Banners',
        'p' => 'Retrieves the paginated list of active marketing banners configured by admins for the mobile store home screen. Each banner record provides high-resolution image URLs, promotional captions, and deep-link routing definitions (CATEGORY, PRODUCT, EXTERNAL_URL, or PROMOTION_PAGE).',
        'f' => '<strong>When to Call:</strong> Call in the Store Home Screen view model initialization.<br><strong>UI Rendering:</strong> Feed items directly into a swipeable `PageView.builder` or `CarouselSlider` with dot indicators and 4-second auto-scroll timer. When tapped, parse `link_type` and `link_value` to push named routes (e.g. `/product-details` or `/category-view`).'
    ],
    3 => [
        'cat' => 'Customer Wallet & Balances',
        'p' => 'Fetches the real-time coin balance of the authenticated peer directly from the `users` and `coins_ledger` tables. Returns an explicit breakdown between Earned Coins (accumulated from verified business meetings, referrals, and closed deals) and Bonus Coins (rewarded by admins), alongside lifetime earnings and current wallet account state (ACTIVE, FROZEN, SUSPENDED).',
        'f' => '<strong>When to Call:</strong> Call on Store Header load, Wallet Screen load, and right after any successful order or refund.<br><strong>UI Handling:</strong> Display formatted coin counters with custom Unity Coin badge icons. If `wallet_state` is \'FROZEN\', disable checkout buttons and render an alert banner directing to customer support.'
    ],
    4 => [
        'cat' => 'Customer Wallet & Balances',
        'p' => 'Retrieves the paginated transaction ledger history of the peer wallet. Supports querying and filtering by coin bucket (`EARNED` vs `BONUS`), transaction reference type (`ORDER`, `RETURN_REFUND`, `ADMIN_ADJUSTMENT`, `BONUS_GRANT`), and date range. Every entry contains balance before/after snapshots and immutable ledger IDs for reconciliation.',
        'f' => '<strong>When to Call:</strong> Use on the Wallet Ledger / Transaction History tab with infinite scrolling pagination.<br><strong>UI Handling:</strong> Render positive amounts in green (`+500 Coins`) and debit deductions in dark charcoal/red (`-1200 Coins`). Allow tapping a transaction row to open the associated Order Details screen using `reference_id`.'
    ],
    5 => [
        'cat' => 'Customer Wallet & Balances',
        'p' => 'Calculates and summarizes the peer coin activity for the current month or a specified fiscal month. Aggregates total coins earned, total coins redeemed on merchandise, pending coin hold reserves, and net growth compared to the previous month.',
        'f' => '<strong>When to Call:</strong> Call when rendering the Monthly Wallet Analytics card on the user profile or rewards dashboard.<br><strong>UI Handling:</strong> Render comparative progress bars or mini bar charts highlighting coin earnings versus expenditure for the month.'
    ],
    6 => [
        'cat' => 'Customer Wallet & Balances',
        'p' => 'Queries the security and operational status of the user wallet. Checks whether the wallet is ACTIVE or temporarily FROZEN / CLOSED due to administrative compliance checks, returning the freeze timestamp and official reason string.',
        'f' => '<strong>When to Call:</strong> Call before loading payment/checkout screens or on wallet settings tab.<br><strong>UI Handling:</strong> If `wallet_state == \'FROZEN\'`, block coin spend actions and show a contact-admin support CTA with the provided freeze reason.'
    ],
    7 => [
        'cat' => 'Customer Catalog & Merchandise',
        'p' => 'Fetches the complete catalog of active merchandise categories (e.g. Unity Apparel, Office Accessories, Books, Tech Gadgets) with thumbnail icons, slug identifiers, display sort sequences, and active product counts.',
        'f' => '<strong>When to Call:</strong> Call once on Store Home screen launch and cache in local state.<br><strong>UI Handling:</strong> Render as horizontal category pills or an interactive category grid. Tapping a category navigates to the Catalog Listing screen with `category_id` pre-filtered.'
    ],
    8 => [
        'cat' => 'Customer Catalog & Merchandise',
        'p' => 'Queries the master product catalog with multi-parameter filtering including category ID, keyword search, coin price range (min_coins/max_coins), physical vs digital type, and sorting options (price_asc, price_desc, newest).',
        'f' => '<strong>When to Call:</strong> Power the main Store Catalog grid and Search Results page.<br><strong>Performance:</strong> Implement a 300ms debounce on search text inputs. Use `PagingController` for 20-item chunk pagination and render shimmer placeholders while fetching.'
    ],
    9 => [
        'cat' => 'Customer Catalog & Merchandise',
        'p' => 'Retrieves the comprehensive product profile for a single item by its UUID or slug. Returns full image galleries, rich HTML description, return eligibility, available delivery modes (Courier Delivery, Hub Pickup), and all configured SKU variants with stock quantities and coin pricing.',
        'f' => '<strong>When to Call:</strong> Call upon navigating into the Product Details screen.<br><strong>UI Handling:</strong> Bind variant selector chips (e.g., Size M, L, XL / Color Navy, Black). When a variant is selected, dynamically update the displayed coin price, stock availability badge, and active image.'
    ],
    10 => [
        'cat' => 'Customer Catalog & Merchandise',
        'p' => 'Retrieves curated list of top featured and high-demand merchandise items flagged with `is_featured: true` by store administrators.',
        'f' => '<strong>When to Call:</strong> Call on Store Home screen to populate the \'Featured Products\' horizontal shelf.<br><strong>UI Handling:</strong> Display product cards with prominent coin price badges and \'Quick Add\' tap actions.'
    ],
    11 => [
        'cat' => 'Customer Catalog & Merchandise',
        'p' => 'Provides live SKU-level stock verification and variant metadata for a specific product variant ID. Checks `product_variants.stock_quantity` minus unexpired checkout reservations to ensure real-time purchase eligibility before adding to cart.',
        'f' => '<strong>When to Call:</strong> Call when user switches variant options on Product Details screen or opens the quick-purchase bottom sheet.<br><strong>UI Handling:</strong> If `stock_quantity <= 0`, disable the \'Add to Cart\' button and display an \'Out of Stock\' red chip.'
    ],
    12 => [
        'cat' => 'Cart Management',
        'p' => 'Fetches the active user shopping cart with all line items, populated variant details, item-level coin totals, shipping surcharge estimation, and total coin payable value. Also verifies if any cart item has fallen out of stock.',
        'f' => '<strong>When to Call:</strong> Call on entering the Cart screen and whenever an item is modified.<br><strong>UI Handling:</strong> Render item cards with quantity steppers (`-`, `+`), remove swipe actions, and a sticky bottom checkout bar showing total coins.'
    ],
    13 => [
        'cat' => 'Cart Management',
        'p' => 'Adds a specified product variant and quantity to the user active shopping cart. Validates available inventory in `product_variants` and enforces peer monthly purchase quotas (`max_quantity_per_peer_month`).',
        'f' => '<strong>When to Call:</strong> Trigger when the user taps \'Add to Cart\' on Product Details or Catalog quick-add buttons.<br><strong>UI Handling:</strong> Show an instant toast/snackbar confirmation with \'View Cart\' CTA and increment the cart badge count in the top navigation bar.'
    ],
    14 => [
        'cat' => 'Cart Management',
        'p' => 'Updates the quantity of an existing line item in the active cart. Performs real-time inventory checks to prevent selecting quantities exceeding available stock.',
        'f' => '<strong>When to Call:</strong> Trigger when user taps `+` or `-` steppers on the Cart screen.<br><strong>UI Handling:</strong> Implement optimistic UI updates with a 200ms debounce. If the server returns a 422 (insufficient stock), revert the stepper and show a warning snackbar.'
    ],
    15 => [
        'cat' => 'Cart Management',
        'p' => 'Removes a specific line item from the active shopping cart by item ID and recalculates the updated cart coin subtotal.',
        'f' => '<strong>When to Call:</strong> Trigger on trash icon tap or when user swipes a cart item tile left-to-delete.<br><strong>UI Handling:</strong> Animate item removal from the list with `AnimatedList` and update the sticky checkout subtotal.'
    ],
    16 => [
        'cat' => 'Cart Management',
        'p' => 'Completely empties all items from the peer active shopping cart in a single atomic database operation.',
        'f' => '<strong>When to Call:</strong> Trigger when user taps \'Clear Cart\' in cart screen options or after successful checkout cleanup.<br><strong>UI Handling:</strong> Show a confirmation alert dialog before calling. On success, switch cart view to the Empty Cart state with a \'Browse Store\' button.'
    ],
    17 => [
        'cat' => 'Address & Pincode Serviceability',
        'p' => 'Retrieves all saved delivery shipping addresses for the authenticated peer, including receiver name, phone number, complete street address, city, state, postal pincode, and default address flag.',
        'f' => '<strong>When to Call:</strong> Call on Checkout Step 1 (Select Address) and on Profile -> Saved Addresses.<br><strong>UI Handling:</strong> Display address selection cards with a radio button indicating the currently selected delivery destination.'
    ],
    18 => [
        'cat' => 'Address & Pincode Serviceability',
        'p' => 'Creates a new shipping address record in the `user_addresses` table after validating mandatory address fields and postal pincode format.',
        'f' => '<strong>When to Call:</strong> Trigger when user fills out the \'Add New Address\' modal form.<br><strong>UI Handling:</strong> Validate inputs locally before submission. On success, auto-select this new address in checkout and pop the modal.'
    ],
    19 => [
        'cat' => 'Address & Pincode Serviceability',
        'p' => 'Fetches the detailed record of a specific saved address by its address ID.',
        'f' => '<strong>When to Call:</strong> Call prior to opening the \'Edit Address\' bottom sheet to populate form fields with existing data.<br><strong>UI Handling:</strong> Pre-fill text editing controllers for Name, Mobile, Address Line 1, Landmark, and Pincode.'
    ],
    20 => [
        'cat' => 'Address & Pincode Serviceability',
        'p' => 'Updates an existing shipping address record in the database for the authenticated peer.',
        'f' => '<strong>When to Call:</strong> Trigger on tapping \'Save Changes\' inside the Edit Address form.<br><strong>UI Handling:</strong> Show a loading spinner on the submit button. On success, refresh the address list state and pop the sheet.'
    ],
    21 => [
        'cat' => 'Address & Pincode Serviceability',
        'p' => 'Deletes a saved address from the user profile if it is not currently tied to an active ongoing order dispatch.',
        'f' => '<strong>When to Call:</strong> Trigger when user taps delete on an address card.<br><strong>UI Handling:</strong> Show a confirmation dialog (\'Are you sure you want to delete this address?\'). On 200 OK, remove card from UI.'
    ],
    22 => [
        'cat' => 'Address & Pincode Serviceability',
        'p' => 'Sets a chosen shipping address as the primary default address for all future checkouts and order dispatches.',
        'f' => '<strong>When to Call:</strong> Trigger when user taps \'Set as Default\' radio or checkbox on an address card.<br><strong>UI Handling:</strong> Instantly update default badge state across all address cards.'
    ],
    23 => [
        'cat' => 'Address & Pincode Serviceability',
        'p' => 'Verifies whether a 6-digit postal pincode is serviceable by courier logistics partners (BlueDart, Delhivery, Express) and returns estimated delivery transit days.',
        'f' => '<strong>When to Call:</strong> Trigger with 300ms debounce as soon as the user enters 6 digits in the pincode input field.<br><strong>UI Handling:</strong> If `serviceable: false`, show an inline red warning (\'Pincode not serviceable for home delivery\') and suggest picking up from a Unity Hub.'
    ],
    24 => [
        'cat' => 'Address & Pincode Serviceability',
        'p' => 'Retrieves the list of official Peers Central Hubs and chapter pickup points where peers can choose to collect their merchandise orders with zero delivery coin charges.',
        'f' => '<strong>When to Call:</strong> Call when the user switches delivery method tab to \'Central Pickup Point\' on Checkout.<br><strong>UI Handling:</strong> Render pickup hub cards with hub name, address, operating hours, and Google Maps location button.'
    ],
    25 => [
        'cat' => 'Checkout Valuation & Security',
        'p' => 'Generates an official, immutable 15-minute checkout quote (`checkout_quotes` table). Computes exact coin valuation, delivery fee breakdown, wallet balance eligibility (Earned vs Bonus coins deduction), and evaluates if an OTP challenge is required.',
        'f' => '<strong>When to Call:</strong> Call immediately upon transitioning to the Order Summary / Review screen.<br><strong>UI Handling:</strong> Start a 15-minute countdown timer on screen (`expires_at`). If `otp_required` is true, prepare to prompt user with OTP verification upon confirming.'
    ],
    26 => [
        'cat' => 'Checkout Valuation & Security',
        'p' => 'Retrieves an existing checkout quote by its quote ID or token to inspect current lock status, remaining validity seconds, and calculated fee breakdowns.',
        'f' => '<strong>When to Call:</strong> Call if app resumes or refreshes during the checkout review step.<br><strong>UI Handling:</strong> If the quote has expired, prompt the user with a dialog: \'Quote expired due to price/inventory recalculation. Tap to refresh.\''
    ],
    27 => [
        'cat' => 'Checkout Valuation & Security',
        'p' => 'Dispatches a 6-digit one-time security passcode via SMS/WhatsApp for high-coin order redemptions or address changes.',
        'f' => '<strong>When to Call:</strong> Trigger when the user taps \'Place Order\' if the quote indicated `otp_required: true`.<br><strong>UI Handling:</strong> Open the OTP Verification Bottom Sheet with 6 pin input boxes and a 60-second \'Resend OTP\' countdown timer.'
    ],
    28 => [
        'cat' => 'Checkout Valuation & Security',
        'p' => 'Verifies the 6-digit security OTP submitted by the peer against the active challenge record and unlocks the quote for final order placement.',
        'f' => '<strong>When to Call:</strong> Trigger automatically as soon as the 6th digit is typed in the OTP input fields.<br><strong>UI Handling:</strong> If valid, show green checkmark animation and proceed immediately to order creation.'
    ],
    29 => [
        'cat' => 'Orders, Invoices & Tracking',
        'p' => 'Atomically converts a valid checkout quote into a confirmed order inside a database transaction: deducts coins from the user wallet, records `coins_ledger` debit entries, decreases stock inventory, and creates `orders` and `order_items` records.',
        'f' => '<strong>When to Call:</strong> Trigger on \'Confirm & Place Order\' button click.<br><strong>Critical Requirement:</strong> Pass a generated UUID in the `Idempotency-Key` header to prevent duplicate orders on poor mobile connections. On success, navigate to the Order Confirmation Celebration screen.'
    ],
    30 => [
        'cat' => 'Orders, Invoices & Tracking',
        'p' => 'Retrieves the authenticated peer paginated order history with status filters (ALL, PLACED, PROCESSING, SHIPPED, DELIVERED, CANCELLED, RETURNED).',
        'f' => '<strong>When to Call:</strong> Call on the \'My Orders\' tab with pull-to-refresh and infinite scrolling.<br><strong>UI Handling:</strong> Render order summary cards displaying Order Number, item thumbnails, total coins spent, and color-coded status badges.'
    ],
    31 => [
        'cat' => 'Orders, Invoices & Tracking',
        'p' => 'Fetches the comprehensive order details for a specific order ID: items, variant specifications, delivery address / pickup hub info, price breakdown, courier tracking status, and return eligibility flags.',
        'f' => '<strong>When to Call:</strong> Call when the user taps an order card in My Orders.<br><strong>UI Handling:</strong> Render the full order profile including order progress stepper, invoice download button, and support ticket CTA.'
    ],
    32 => [
        'cat' => 'Orders, Invoices & Tracking',
        'p' => 'Allows a peer to cancel a placed order before it reaches the SHIPPED status. Automatically triggers an immediate database transaction reversing the coin deduction back to the user wallet ledger.',
        'f' => '<strong>When to Call:</strong> Trigger when user taps \'Cancel Order\' on an eligible pending order.<br><strong>UI Handling:</strong> Show a bottom sheet asking for cancellation reason. On 200 OK, refresh wallet balance and update order status to CANCELLED.'
    ],
    33 => [
        'cat' => 'Orders, Invoices & Tracking',
        'p' => 'Generates or retrieves the official PDF/JSON tax invoice and redemption receipt for a completed order.',
        'f' => '<strong>When to Call:</strong> Trigger when user taps \'Download Receipt / Invoice\' on the Order Details screen.<br><strong>UI Handling:</strong> Download the file using `flutter_downloader` or display inline using `flutter_pdfview`.'
    ],
    34 => [
        'cat' => 'Orders, Invoices & Tracking',
        'p' => 'Fetches live courier tracking milestones and logistics status for an order dispatch (Airway Bill number, carrier partner, estimated arrival, checkpoint timestamps).',
        'f' => '<strong>When to Call:</strong> Call on the Order Tracking tab or when user taps \'Track Package\'.<br><strong>UI Handling:</strong> Render a vertical timeline stepper showing Order Placed -> Packed -> Shipped -> Out for Delivery -> Delivered.'
    ],
    35 => [
        'cat' => 'Orders, Invoices & Tracking',
        'p' => 'Retrieves the pickup security verification code and QR code for orders designated for Central Pickup Hub collection.',
        'f' => '<strong>When to Call:</strong> Call on Order Details when `delivery_type` is \'PICKUP\'.<br><strong>UI Handling:</strong> Render a large scannable QR Code and 6-digit numeric pickup PIN to present to the warehouse coordinator.'
    ],
    36 => [
        'cat' => 'Returns & Replacements',
        'p' => 'Retrieves the peer history of submitted product return requests alongside their inspection and refund approval status.',
        'f' => '<strong>When to Call:</strong> Call on the \'My Returns\' screen in the user profile.<br><strong>UI Handling:</strong> Display return cards with current return status (PENDING_INSPECTION, APPROVED, REJECTED, REFUNDED).'
    ],
    37 => [
        'cat' => 'Returns & Replacements',
        'p' => 'Submits a new return request for an eligible delivered item within the allowed 7-day return window. Peer submits return reason, description, and proof image URLs.',
        'f' => '<strong>When to Call:</strong> Trigger from Order Details screen when user taps \'Return Item\'.<br><strong>UI Handling:</strong> Multi-step form with image picker to upload photos of defective goods. Validate that at least one photo is attached.'
    ],
    38 => [
        'cat' => 'Returns & Replacements',
        'p' => 'Fetches the detailed inspection status and timeline notes for a specific return request ID.',
        'f' => '<strong>When to Call:</strong> Call when user taps a return record in My Returns.<br><strong>UI Handling:</strong> Render quality inspection progress stepper and admin resolution remarks.'
    ],
    39 => [
        'cat' => 'Returns & Replacements',
        'p' => 'Allows the peer to withdraw or cancel an open return request before warehouse pickup has occurred.',
        'f' => '<strong>When to Call:</strong> Trigger when user taps \'Cancel Return Request\'.<br><strong>UI Handling:</strong> Confirm with dialog; on success, update status chip to CANCELLED.'
    ],
    40 => [
        'cat' => 'VIP Memberships & Passes',
        'p' => 'Lists available store privilege passes and VIP membership tiers (e.g. Gold Pass, Platinum Pass) with their coin price, duration, and perks (zero delivery coins, early catalog drops).',
        'f' => '<strong>When to Call:</strong> Call on the Store Membership / VIP Club tab.<br><strong>UI Handling:</strong> Render attractive gradient membership cards highlighting benefits with a \'Subscribe with Coins\' button.'
    ],
    41 => [
        'cat' => 'VIP Memberships & Passes',
        'p' => 'Subscribes the peer to a membership pass by deducting required coins from their wallet and activating pass perks in `user_memberships`.',
        'f' => '<strong>When to Call:</strong> Trigger when user confirms membership purchase.<br><strong>UI Handling:</strong> Show coin deduction confirmation dialog. On success, play celebration confetti and refresh user pass status.'
    ],
    42 => [
        'cat' => 'VIP Memberships & Passes',
        'p' => 'Retrieves the active membership pass details and expiration timestamp for the authenticated peer.',
        'f' => '<strong>When to Call:</strong> Call during profile load and checkout to apply member discounts or free shipping automatically.<br><strong>UI Handling:</strong> Display VIP badge next to user profile name.'
    ],
    43 => [
        'cat' => 'VIP Memberships & Passes',
        'p' => 'Allows a user to cancel auto-renewal for an active store membership pass.',
        'f' => '<strong>When to Call:</strong> Trigger on \'Cancel Auto-Renewal\' button on Membership Settings screen.<br><strong>UI Handling:</strong> Confirm intent with dialog; on success, toggle auto-renewal switch to off.'
    ],
    44 => [
        'cat' => 'Digital Library & Assets',
        'p' => 'Lists all digital assets, eBooks, training guides, and masterclass resources owned or unlocked by the peer.',
        'f' => '<strong>When to Call:</strong> Call on the \'My Digital Library\' tab.<br><strong>UI Handling:</strong> Render a grid of downloadable eBooks and media cards with \'Read Now\' or \'Download\' buttons.'
    ],
    45 => [
        'cat' => 'Digital Library & Assets',
        'p' => 'Fetches the detailed metadata for a specific digital asset including file size, format (PDF, MP4), and access entitlement status.',
        'f' => '<strong>When to Call:</strong> Call on selecting a digital resource card.<br><strong>UI Handling:</strong> Render resource overview and author details.'
    ],
    46 => [
        'cat' => 'Digital Library & Assets',
        'p' => 'Generates a secure, time-expiring signed URL (e.g. valid for 15 minutes) allowing the peer to securely download or stream the digital media file.',
        'f' => '<strong>When to Call:</strong> Trigger when user taps \'Download PDF\' or \'Play Masterclass\'.<br><strong>UI Handling:</strong> Open the signed URL directly in an in-app PDF viewer or media player.'
    ],
    47 => [
        'cat' => 'Internal Engine Engine',
        'p' => 'Internal engine endpoint: Credits coins directly to a peer wallet ledger when verified business milestones, meeting attendance, or chapter achievements are recorded by core services.',
        'f' => '<strong>Microservice / Internal Engine Integration:</strong> Secured via internal server bearer token. Not directly invoked by Flutter mobile client.'
    ],
    48 => [
        'cat' => 'Internal Engine Engine',
        'p' => 'Internal engine endpoint: Debits coins from a peer wallet for authorized cross-system penalties, annual subscriptions, or platform transaction charges.',
        'f' => '<strong>Microservice / Internal Engine Integration:</strong> Atomic transaction ledger debit with pre-balance validation.'
    ],
    49 => [
        'cat' => 'Notifications & Webhooks',
        'p' => 'Retrieves the paginated feed of in-app store alerts, order updates, coin credit notices, and return notifications for the authenticated peer.',
        'f' => '<strong>When to Call:</strong> Call on the Notifications screen and poll periodically.<br><strong>UI Handling:</strong> Render list of notification items with unread indicators and deep-link routing on tap.'
    ],
    50 => [
        'cat' => 'Notifications & Webhooks',
        'p' => 'Fetches the count of unread notifications for fast header badge rendering.',
        'f' => '<strong>When to Call:</strong> Call on app launch and after push notification reception.<br><strong>UI Handling:</strong> Show numeric red badge over the notification bell icon.'
    ],
    51 => [
        'cat' => 'Notifications & Webhooks',
        'p' => 'Marks a single notification as read by notification ID.',
        'f' => '<strong>When to Call:</strong> Trigger when the user taps on a specific notification tile.<br><strong>UI Handling:</strong> Dim the background of the notification card and decrement unread counter.'
    ],
    52 => [
        'cat' => 'Notifications & Webhooks',
        'p' => 'Marks all pending notifications as read in a single batch request.',
        'f' => '<strong>When to Call:</strong> Trigger when user taps \'Mark All as Read\' in Notifications screen.<br><strong>UI Handling:</strong> Clear all unread badges across the UI.'
    ],
    53 => [
        'cat' => 'Notifications & Webhooks',
        'p' => 'Deletes a single notification record from the user feed.',
        'f' => '<strong>When to Call:</strong> Trigger on swipe-to-delete on a notification card.<br><strong>UI Handling:</strong> Animate removal from the list view.'
    ],
    54 => [
        'cat' => 'Notifications & Webhooks',
        'p' => 'Deletes all notifications from the user feed.',
        'f' => '<strong>When to Call:</strong> Trigger on \'Clear All\' button in notifications.<br><strong>UI Handling:</strong> Switch screen to empty state.'
    ],
    55 => [
        'cat' => 'Notifications & Webhooks',
        'p' => 'Retrieves the peer notification channel preferences (Order SMS, Coin Push Alerts, Promo Emails).',
        'f' => '<strong>When to Call:</strong> Call on Notification Settings screen.<br><strong>UI Handling:</strong> Render toggle switches for each notification category.'
    ],
    56 => [
        'cat' => 'Notifications & Webhooks',
        'p' => 'Updates peer notification channel preferences in the database.',
        'f' => '<strong>When to Call:</strong> Trigger when user flips a notification preference toggle switch.<br><strong>UI Handling:</strong> Persist changes immediately with optimistic toggle feedback.'
    ],
    57 => [
        'cat' => 'Notifications & Webhooks',
        'p' => 'Receives real-time delivery status webhook events from 3rd party courier partners (Delhivery / BlueDart) to automatically advance order shipment stages.',
        'f' => '<strong>Server-to-Server Webhook:</strong> Validates webhook secret HMAC signature. Dispatches background jobs to notify peer mobile apps via FCM push notifications.'
    ],
    58 => [
        'cat' => 'Customer Support Tickets',
        'p' => 'Retrieves the list of support tickets submitted by the authenticated peer regarding store orders, damaged items, or coin disputes.',
        'f' => '<strong>When to Call:</strong> Call on the Support Tickets / Helpdesk screen.<br><strong>UI Handling:</strong> Render ticket cards showing Ticket ID, Subject, Status (OPEN, IN_PROGRESS, RESOLVED), and last reply timestamp.'
    ],
    59 => [
        'cat' => 'Customer Support Tickets',
        'p' => 'Creates a new customer support ticket with subject, category, description, optional order ID link, and attachments.',
        'f' => '<strong>When to Call:</strong> Trigger from \'Contact Support\' form.<br><strong>UI Handling:</strong> Submit form and navigate directly into the newly created ticket chat thread.'
    ],
    60 => [
        'cat' => 'Customer Support Tickets',
        'p' => 'Fetches the complete message thread and history of a specific support ticket.',
        'f' => '<strong>When to Call:</strong> Call on entering the Ticket Chat screen.<br><strong>UI Handling:</strong> Render chat bubble interface differentiating between Peer messages and Admin Staff replies.'
    ],
    61 => [
        'cat' => 'Customer Support Tickets',
        'p' => 'Sends a new reply message or image attachment in an ongoing support ticket thread.',
        'f' => '<strong>When to Call:</strong> Trigger when user taps the send button in the ticket chat box.<br><strong>UI Handling:</strong> Append message optimistically to the bottom of the chat list.'
    ],
    62 => [
        'cat' => 'Customer Support Tickets',
        'p' => 'Allows the peer to mark a support ticket as resolved and closed.',
        'f' => '<strong>When to Call:</strong> Trigger when user taps \'Close Ticket\'.<br><strong>UI Handling:</strong> Update ticket status badge to RESOLVED and lock the message input box.'
    ],
    63 => [
        'cat' => 'Public Policies & Terms',
        'p' => 'Retrieves the list of active public legal documents and policies (Store Terms & Conditions, Coin Redemption Rules, Return & Replacement Policy, Privacy Guidelines).',
        'f' => '<strong>When to Call:</strong> Call on Store Settings -> Legal & Policies screen.<br><strong>UI Handling:</strong> Render list of policy titles with navigation chevron.'
    ],
    64 => [
        'cat' => 'Public Policies & Terms',
        'p' => 'Fetches the full rich markdown/HTML content of a specific store policy by its slug (e.g. `coin-policy`, `return-policy`).',
        'f' => '<strong>When to Call:</strong> Call upon selecting a policy from the list.<br><strong>UI Handling:</strong> Render formatted text using `flutter_widget_from_html`.'
    ],
    65 => [
        'cat' => 'Admin Metrics & Dashboard',
        'p' => 'Admin dashboard metrics endpoint: Computes real-time totals for total orders, total coins redeemed, active returns pending inspection, low stock alerts, and open support tickets.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Poll every 60 seconds on the Admin Dashboard to refresh top KPI statistics cards.'
    ],
    66 => [
        'cat' => 'Admin Metrics & Dashboard',
        'p' => 'Admin inventory metrics endpoint: Computes total SKU count, total units in stock, reserved inventory in active checkout quotes, and out-of-stock items.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Populate Admin Inventory Overview dashboard with donut charts and critical stock alert badges.'
    ],
    67 => [
        'cat' => 'Admin Categories',
        'p' => 'Admin endpoint: Retrieves all categories including hidden and inactive ones with display ordering and full product counts.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Power the Categories management table with drag-and-drop display sequence sorting.'
    ],
    68 => [
        'cat' => 'Admin Categories',
        'p' => 'Admin endpoint: Creates a new merchandise category with custom name, slug, thumbnail image, display order, and active toggle.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Submit from the \'Add Category\' modal dialog.'
    ],
    69 => [
        'cat' => 'Admin Categories',
        'p' => 'Admin endpoint: Retrieves detailed category configuration by ID for editing.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Pre-fill category edit modal inputs.'
    ],
    70 => [
        'cat' => 'Admin Categories',
        'p' => 'Admin endpoint: Updates category title, slug, image URL, display order, and active visibility state.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Save modifications on category edit form.'
    ],
    71 => [
        'cat' => 'Admin Categories',
        'p' => 'Admin endpoint: Deletes or archives a merchandise category if no active products are associated.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Trigger from delete category action button.'
    ],
    72 => [
        'cat' => 'Admin Products',
        'p' => 'Admin endpoint: Paginated master list of all products with full stock totals, variant counts, visibility status, and coin prices.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Main Admin Products table with multi-column filtering and bulk actions.'
    ],
    73 => [
        'cat' => 'Admin Products',
        'p' => 'Admin endpoint: Creates a new product record in the `products` table with base specifications, description, and category mapping.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Trigger from Admin \'Add New Product\' wizard.'
    ],
    74 => [
        'cat' => 'Admin Products',
        'p' => 'Admin endpoint: Retrieves deep administrative product details including all SKU variants, inventory movements, and sales performance.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Populate Product Management detail view.'
    ],
    75 => [
        'cat' => 'Admin Products',
        'p' => 'Admin endpoint: Updates product title, description, category, and base pricing.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Save edits on Product Edit form.'
    ],
    76 => [
        'cat' => 'Admin Products',
        'p' => 'Admin endpoint: Soft-deletes or archives a product from the catalog.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Trigger on product archive action.'
    ],
    77 => [
        'cat' => 'Admin Variants',
        'p' => 'Admin endpoint: Lists all SKU variants (sizes, colors, specs) configured for a given product ID.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Render SKU variant table in Product Details.'
    ],
    78 => [
        'cat' => 'Admin Variants',
        'p' => 'Admin endpoint: Creates a new SKU variant for a product with distinct SKU code, variant attributes, coin price, and initial stock quantity.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Trigger from \'Add Variant\' modal.'
    ],
    79 => [
        'cat' => 'Admin Variants',
        'p' => 'Admin endpoint: Updates SKU variant attributes, coin pricing, or barcode identifier.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Save variant modifications.'
    ],
    80 => [
        'cat' => 'Admin Variants',
        'p' => 'Admin endpoint: Deletes an SKU variant if no active orders depend on it.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Remove variant row from table.'
    ],
    81 => [
        'cat' => 'Admin Stock & Inventory',
        'p' => 'Admin endpoint: Retrieves complete inventory stock audit logs and historical warehouse stock adjustments.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Stock Movement Audit screen.'
    ],
    82 => [
        'cat' => 'Admin Stock & Inventory',
        'p' => 'Admin endpoint: Executes manual inventory stock replenishment or deduction for an SKU with reason notes (e.g., Damaged Stock, Warehouse Restock).',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Trigger from \'Adjust Stock\' modal.'
    ],
    83 => [
        'cat' => 'Admin Orders Management',
        'p' => 'Admin endpoint: Master admin orders table with comprehensive filters by status, peer name, date range, and payment coin bucket.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Main Admin Orders management view.'
    ],
    84 => [
        'cat' => 'Admin Orders Management',
        'p' => 'Admin endpoint: Fetches complete administrative order file including full audit logs, customer details, shipment records, and ledger IDs.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Admin Order Inspector view.'
    ],
    85 => [
        'cat' => 'Admin Orders Management',
        'p' => 'Admin endpoint: Advances order status through workflow (PLACED -> PROCESSING -> SHIPPED -> DELIVERED).',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Order status dropdown action.'
    ],
    86 => [
        'cat' => 'Admin Orders Management',
        'p' => 'Admin endpoint: Admin action to cancel an order and immediately execute automatic coin refund to peer wallet.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Trigger from \'Cancel & Refund\' button.'
    ],
    87 => [
        'cat' => 'Admin Fulfillment & Pickup Hubs',
        'p' => 'Admin endpoint: Lists all active courier shipments and fulfillment batches across warehouses.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Logistics & Dispatch Management view.'
    ],
    88 => [
        'cat' => 'Admin Fulfillment & Pickup Hubs',
        'p' => 'Admin endpoint: Creates a new courier shipment dispatch record, assigning Airway Bill (AWB) number and logistics carrier to an order.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Trigger from \'Generate AWB & Dispatch\' modal.'
    ],
    89 => [
        'cat' => 'Admin Fulfillment & Pickup Hubs',
        'p' => 'Admin endpoint: Retrieves shipment details and courier checkpoint history.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Shipment tracking dialog.'
    ],
    90 => [
        'cat' => 'Admin Fulfillment & Pickup Hubs',
        'p' => 'Admin endpoint: Updates shipment tracking milestones and marks packages as Out For Delivery or Delivered.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Manual tracking update modal.'
    ],
    91 => [
        'cat' => 'Admin Fulfillment & Pickup Hubs',
        'p' => 'Admin endpoint: Lists all configured central warehouse pickup hubs and physical collection points.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Pickup Hubs Configuration table.'
    ],
    92 => [
        'cat' => 'Admin Fulfillment & Pickup Hubs',
        'p' => 'Admin endpoint: Creates a new physical pickup hub location with operational hours and coordinator contact.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> \'Add New Hub\' form.'
    ],
    93 => [
        'cat' => 'Admin Returns Inspection',
        'p' => 'Admin endpoint: Admin table of all peer return requests filtered by inspection state.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Returns Inspection queue.'
    ],
    94 => [
        'cat' => 'Admin Returns Inspection',
        'p' => 'Admin endpoint: Fetches full return file with uploaded customer damage photos and item specifications.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Return Quality Assessment view.'
    ],
    95 => [
        'cat' => 'Admin Returns Inspection',
        'p' => 'Admin endpoint: Approves a return request and automatically triggers the refund coin ledger credit to user wallet.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> \'Approve Return & Refund\' button.'
    ],
    96 => [
        'cat' => 'Admin Returns Inspection',
        'p' => 'Admin endpoint: Rejects a return request with mandatory quality rejection reason remarks.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> \'Reject Return\' dialog.'
    ],
    97 => [
        'cat' => 'Admin Wallets & Adjustments',
        'p' => 'Admin endpoint: Admin view of all peer wallets with balance summaries, lifetime metrics, and account status.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Peer Wallets table.'
    ],
    98 => [
        'cat' => 'Admin Wallets & Adjustments',
        'p' => 'Admin endpoint: Fetches detailed coin ledger and audit history for a specific peer wallet.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> User Wallet Deep-Dive modal.'
    ],
    99 => [
        'cat' => 'Admin Wallets & Adjustments',
        'p' => 'Admin endpoint: Initiates a Maker coin adjustment request (credit/debit) requiring secondary Checker approval.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> \'Initiate Coin Adjustment\' form.'
    ],
    100 => [
        'cat' => 'Admin Wallets & Adjustments',
        'p' => 'Admin endpoint: Approves a pending Maker-Checker coin adjustment request and executes ledger credit/debit.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Checker Approval action.'
    ],
    101 => [
        'cat' => 'Admin Wallets & Adjustments',
        'p' => 'Admin endpoint: Rejects a pending Maker-Checker coin adjustment request.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Checker Rejection action.'
    ],
    102 => [
        'cat' => 'Admin Wallets & Adjustments',
        'p' => 'Admin endpoint: Administrative override to freeze a peer wallet against further coin redemptions.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> \'Freeze Wallet\' emergency toggle.'
    ],
    103 => [
        'cat' => 'Admin Wallets & Adjustments',
        'p' => 'Admin endpoint: Unfreezes a previously locked peer wallet, restoring full redemption privileges.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> \'Unfreeze Wallet\' button.'
    ],
    104 => [
        'cat' => 'Admin Bonus Campaigns',
        'p' => 'Admin endpoint: Lists all promotional bonus coin distribution campaigns and grant batches.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Bonus Campaigns table.'
    ],
    105 => [
        'cat' => 'Admin Bonus Campaigns',
        'p' => 'Admin endpoint: Creates a new bulk bonus coin distribution campaign for eligible peer chapters.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> \'Create Bonus Campaign\' wizard.'
    ],
    106 => [
        'cat' => 'Admin Bonus Campaigns',
        'p' => 'Admin endpoint: Retrieves detailed metrics and peer recipient list for a specific bonus campaign.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Bonus Campaign Inspection view.'
    ],
    107 => [
        'cat' => 'Admin Bonus Campaigns',
        'p' => 'Admin endpoint: Updates promotional bonus campaign parameters or schedule.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Edit Campaign form.'
    ],
    108 => [
        'cat' => 'Admin Bonus Campaigns',
        'p' => 'Admin endpoint: Cancels or revokes an active bonus coin campaign.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Cancel Campaign dialog.'
    ],
    109 => [
        'cat' => 'Admin Store Memberships',
        'p' => 'Admin endpoint: Lists all store membership tiers and VIP subscription plans configured in the system.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Membership Plans table.'
    ],
    110 => [
        'cat' => 'Admin Store Memberships',
        'p' => 'Admin endpoint: Creates a new store membership plan with coin price, duration days, and privilege perks.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> \'Create Membership Plan\' modal.'
    ],
    111 => [
        'cat' => 'Admin Store Memberships',
        'p' => 'Admin endpoint: Updates membership plan pricing, perk description, or active status.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Edit Plan modal.'
    ],
    112 => [
        'cat' => 'Admin Store Memberships',
        'p' => 'Admin endpoint: Archives or deletes a membership tier from public store listing.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Delete Plan confirmation.'
    ],
    113 => [
        'cat' => 'Admin Digital Assets',
        'p' => 'Admin endpoint: Lists all digital assets, masterclasses, and eBooks uploaded in the digital library repository.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Digital Assets catalog table.'
    ],
    114 => [
        'cat' => 'Admin Digital Assets',
        'p' => 'Admin endpoint: Uploads a new digital asset record with file storage path, coin price, and access entitlement rules.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> \'Upload New Digital Asset\' form.'
    ],
    115 => [
        'cat' => 'Admin Digital Assets',
        'p' => 'Admin endpoint: Manually grants a digital entitlement or eBook license to a specific peer without coin deduction.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> \'Grant Entitlement\' action modal in user profile.'
    ],
    116 => [
        'cat' => 'Admin Digital Assets',
        'p' => 'Admin endpoint: Revokes an active digital library entitlement or asset license from a user account.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> \'Revoke License\' button in user asset entitlements table.'
    ],
    117 => [
        'cat' => 'Admin Notifications Management',
        'p' => 'Admin endpoint: Lists all in-app and push notification templates configured for automated system triggers.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Notification Templates table.'
    ],
    118 => [
        'cat' => 'Admin Notifications Management',
        'p' => 'Admin endpoint: Retrieves the configuration and text template body of a specific notification template by ID.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Notification Template inspector view.'
    ],
    119 => [
        'cat' => 'Admin Notifications Management',
        'p' => 'Admin endpoint: Retrieves the paginated audit log of dispatched push/SMS/in-app notifications with delivery status.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Dispatched Notification Logs table.'
    ],
    120 => [
        'cat' => 'Admin Notifications Management',
        'p' => 'Admin endpoint: Retries or resends a failed notification log record to the recipient device.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> \'Resend Notification\' action button in log details.'
    ],
    121 => [
        'cat' => 'Admin Support Helpdesk',
        'p' => 'Admin endpoint: Master admin helpdesk table of all submitted customer support tickets filtered by urgency, department, and status.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Admin Helpdesk Dashboard.'
    ],
    122 => [
        'cat' => 'Admin Support Helpdesk',
        'p' => 'Admin endpoint: Fetches the complete support ticket thread file including peer profile and full conversation history.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Admin Support Ticket view.'
    ],
    123 => [
        'cat' => 'Admin Support Helpdesk',
        'p' => 'Admin endpoint: Assigns a support ticket to a designated administrative agent or department.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Staff assignment dropdown selector.'
    ],
    124 => [
        'cat' => 'Admin Support Helpdesk',
        'p' => 'Admin endpoint: Posts an administrative reply message to a peer support ticket thread.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Admin message composer in ticket conversation view.'
    ],
    125 => [
        'cat' => 'Admin Support Helpdesk',
        'p' => 'Admin endpoint: Marks a customer support ticket as resolved and closes the ticket thread.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> \'Resolve & Close Ticket\' button.'
    ],
    126 => [
        'cat' => 'Admin Store Configuration',
        'p' => 'Admin endpoint: Retrieves the complete dictionary of store configuration parameters from `store_configs`.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Store Configuration master panel.'
    ],
    127 => [
        'cat' => 'Admin Store Configuration',
        'p' => 'Admin endpoint: Retrieves the value and metadata of a specific store configuration key (e.g. `min_delivery_order_coins`).',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Config key detail view.'
    ],
    128 => [
        'cat' => 'Admin Store Configuration',
        'p' => 'Admin endpoint: Updates the value of a specific store configuration parameter in `store_configs`.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Save Config value button.'
    ],
    129 => [
        'cat' => 'Admin Policies Management',
        'p' => 'Admin endpoint: Lists all legal policies, Terms & Conditions, and compliance documents in the system.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Policies Management table.'
    ],
    130 => [
        'cat' => 'Admin Policies Management',
        'p' => 'Admin endpoint: Creates a new policy draft with rich markdown body text and version label.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> \'Create Policy Draft\' editor modal.'
    ],
    131 => [
        'cat' => 'Admin Policies Management',
        'p' => 'Admin endpoint: Updates an existing policy draft content, title, or summary notes.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Save Policy Draft button.'
    ],
    132 => [
        'cat' => 'Admin Policies Management',
        'p' => 'Admin endpoint: Formally publishes a policy version, making it live and visible across customer mobile apps.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> \'Publish Version\' confirmation action.'
    ],
    133 => [
        'cat' => 'Admin Financial Reports & Analytics',
        'p' => 'Admin endpoint: Generates aggregate sales and financial metrics (total orders, total coins redeemed, average order value in coins) over a custom date window.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Sales & Financial KPI cards and charts.'
    ],
    134 => [
        'cat' => 'Admin Financial Reports & Analytics',
        'p' => 'Admin endpoint: Generates coin redemption analytics report broken down by Earned Coins versus Bonus Coins expenditure.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Coin Redemption Breakdown donut chart.'
    ],
    135 => [
        'cat' => 'Admin Financial Reports & Analytics',
        'p' => 'Admin endpoint: Generates coin issuance analytics report tracking total coins minted and awarded across chapters.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Coin Issuance trend bar chart.'
    ],
    136 => [
        'cat' => 'Admin Financial Reports & Analytics',
        'p' => 'Admin endpoint: Generates VIP membership renewals and subscription coin revenue report over custom date ranges.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> Membership Subscriptions Performance report.'
    ],
    137 => [
        'cat' => 'Admin Financial Reports & Analytics',
        'p' => 'Admin endpoint: Triggers an asynchronous dataset export and returns a direct CSV download URL for comprehensive offline bookkeeping and auditing.',
        'f' => '<strong>Admin Web / Portal Integration:</strong> \'Download CSV Export\' action link.'
    ]
];

$dictCode = "<?php\n\nfunction getApiSpecificData(\$num, \$title, \$method, \$path) {\n    static \$dict = [\n";

foreach ($descriptions as $num => $d) {
    $dictCode .= "        {$num} => [\n";
    $dictCode .= "            'category' => '" . addslashes($d['cat']) . "',\n";
    $dictCode .= "            'purpose' => '" . addslashes($d['p']) . "',\n";
    $dictCode .= "            'flutter' => '" . addslashes($d['f']) . "'\n";
    $dictCode .= "        ],\n";
}

$dictCode .= "    ];\n\n";
$dictCode .= "    if (isset(\$dict[\$num])) return \$dict[\$num];\n";
$dictCode .= "    return ['category' => 'Store API', 'purpose' => 'API Endpoint', 'flutter' => 'Integration guide'];\n";
$dictCode .= "}\n";

file_put_contents(__DIR__ . '/api_descriptions_data.php', $dictCode);
echo "Written descriptions for 137 APIs to api_descriptions_data.php\n";

