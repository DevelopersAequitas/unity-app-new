<?php

$mdPath = __DIR__ . '/../docs/api/PEERS_STORE_137_APIS_MASTER_DOCUMENTATION.md';
$content = file_get_contents($mdPath);

// Regex split by ### <number>. <Title>
preg_match_all('/###\s*(\d+)\.\s*([^\r\n]+)(.*?)(?=(?:###\s*\d+\.|$))/s', $content, $matches, PREG_SET_ORDER);

$apis = [];

foreach ($matches as $m) {
    $num = (int)$m[1];
    $title = trim($m[2]);
    $body = $m[3];

    // Method
    preg_match('/-\s*\*\*Method\*\*:\s*`?([A-Z]+)`?/i', $body, $methodMatch);
    $method = !empty($methodMatch[1]) ? strtoupper($methodMatch[1]) : 'GET';

    // URL
    preg_match('/-\s*\*\*URL\*\*:\s*`?([^`\r\n]+)`?/i', $body, $urlMatch);
    $rawUrl = !empty($urlMatch[1]) ? trim($urlMatch[1]) : '';
    $path = preg_replace('/^https?:\/\/[^\/]+/i', '', $rawUrl);
    if (empty($path)) {
        $path = '/api/v1/store';
    }
    if (!str_starts_with($path, '/')) {
        $path = '/' . $path;
    }
    if (!str_starts_with($path, '/api')) {
        $path = '/api' . $path;
    }

    $localUrl = 'http://localhost:8000' . $path;
    $liveUrl = 'https://api.peersglobalunity.com' . $path;

    // Purpose / Description
    preg_match('/-\s*\*\*Purpose\*\*:\s*([^\r\n]+)/i', $body, $purposeMatch);
    $purpose = !empty($purposeMatch[1]) ? trim($purposeMatch[1]) : 'Process store and coin wallet transaction for the authenticated user.';

    // Headers
    preg_match('/-\s*\*\*Headers\*\*:\s*([^\r\n]+)/i', $body, $headersMatch);
    $headers = !empty($headersMatch[1]) ? trim($headersMatch[1]) : 'Authorization: Bearer <TOKEN>, Content-Type: application/json';

    // Request Body JSON
    preg_match('/-\s*\*\*Request Body\*\*:\s*(?:`None`|None)?\s*(?:```(?:json)?\s*([\s\S]*?)```)?/i', $body, $reqMatch);
    $reqBody = !empty($reqMatch[1]) ? trim($reqMatch[1]) : null;

    // Response JSON
    preg_match('/-\s*\*\*Response\*\*:\s*```(?:json)?\s*([\s\S]*?)```/i', $body, $resMatch);
    $resBody = !empty($resMatch[1]) ? trim($resMatch[1]) : "{\n  \"success\": true,\n  \"message\": \"Success\",\n  \"data\": {}\n}";

    // Generate Deep English Flutter / Frontend description based on API number and purpose
    $flutterUsage = "Trigger this API from the Flutter mobile app / Web client when user interacts with this feature. Handles state validation, token authentication, and data synchronization.";
    
    if ($num >= 1 && $num <= 4) {
        $flutterUsage = "Call during mobile app splash/home initial load or Wallet Screen. Use response to populate store banners, verify app version compatibility, and render total spendable vs earned/bonus coins.";
    } elseif ($num >= 5 && $num <= 10) {
        $flutterUsage = "Call in Store Catalog & Search screens. Use for horizontal category chips, infinite scroll product grids, search debounce filtering, and product detail viewing.";
    } elseif ($num >= 11 && $num <= 18) {
        $flutterUsage = "Call in Shopping Cart screen. Manage cart items quantity (+/-), stock reserve verification, item removal, and auto-subtotal calculation.";
    } elseif ($num >= 19 && $num <= 24) {
        $flutterUsage = "Call in Checkout Delivery Address & Pincode checker. Manage user saved addresses, set default shipping address, check courier courier delivery SLA, or select central pickup points.";
    } elseif ($num >= 25 && $num <= 28) {
        $flutterUsage = "Call when user enters Checkout screen. Generates a temporary 15-minute valuation quote locking prices and delivery charges. Triggers SMS/In-app OTP challenge if high-value coin redemption is detected.";
    } elseif ($num >= 29 && $num <= 33) {
        $flutterUsage = "Call on 'Place Order' or 'Cancel Order' button click. Deducts coins atomically in DB transaction, transitions order lifecycle from CONFIRMED to SHIPPED/DELIVERED/CANCELLED.";
    } elseif ($num >= 34 && $num <= 38) {
        $flutterUsage = "Call in Order Details & Fulfillment screen. Displays live AWB tracking milestones (IN_TRANSIT, DELIVERED) and allows downloading official tax receipts & invoices.";
    } elseif ($num >= 39 && $num <= 42) {
        $flutterUsage = "Call in Return Request flow. Allows peers to file a return within 7 days of physical item delivery, upload reason details, and track refund credit status.";
    } elseif ($num >= 43 && $num <= 45) {
        $flutterUsage = "Call on Product Detail Review section. Allows verified buyers to submit 1-5 star ratings, feedback reviews, and view aggregated ratings.";
    } elseif ($num >= 46 && $num <= 48) {
        $flutterUsage = "Call in Customer Support screen. Open inquiries regarding delayed orders, send two-way conversation messages with admin support executives.";
    } elseif ($num >= 49 && $num <= 55) {
        $flutterUsage = "Call in Membership & Legal Policy screens. Display Gold/Silver Pass benefits, purchase membership with coin wallet, and display Terms & Return Policies.";
    } elseif ($num >= 56 && $num <= 58) {
        $flutterUsage = "Call in 'My Digital Library' tab. Fetch secure signed download URLs for purchased eBooks, masterclass PDF assets, and verify course access permissions.";
    } elseif ($num >= 59 && $num <= 64) {
        $flutterUsage = "Call in User Notification Feed. Fetch real-time order status updates, coin credit/debit alerts, and mark notification logs as read.";
    } else {
        $flutterUsage = "Admin Portal execution: Used by management & warehouse dispatch operators for catalog maintenance, stock adjustments, AWB creation, returns inspection, maker-checker wallet approvals, and financial report exports.";
    }

    $apis[] = [
        'num' => $num,
        'title' => $title,
        'method' => $method,
        'path' => $path,
        'localUrl' => $localUrl,
        'liveUrl' => $liveUrl,
        'purpose' => $purpose,
        'headers' => $headers,
        'reqBody' => $reqBody,
        'resBody' => $resBody,
        'flutterUsage' => $flutterUsage,
    ];
}

// Build HTML content
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peers Global Unity — All 137 Store & Wallet APIs Master Documentation</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #eef2ff;
            --secondary: #0ea5e9;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #0f172a;
            --gray-900: #1e293b;
            --gray-800: #334155;
            --gray-700: #475569;
            --gray-600: #64748b;
            --gray-300: #cbd5e1;
            --gray-200: #e2e8f0;
            --gray-100: #f1f5f9;
            --gray-50: #f8fafc;
            --card-bg: #ffffff;
            --code-bg: #0f172a;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.6;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar navigation */
        .sidebar {
            width: 340px;
            background: #ffffff;
            border-right: 1px solid var(--gray-200);
            height: 100vh;
            position: sticky;
            top: 0;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            z-index: 10;
        }

        .sidebar-header {
            padding: 24px 20px 16px;
            border-bottom: 1px solid var(--gray-200);
        }

        .sidebar-header h2 {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sidebar-header .badge {
            background: var(--primary-light);
            color: var(--primary);
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 999px;
            font-weight: 700;
        }

        .search-box {
            padding: 12px 20px;
            border-bottom: 1px solid var(--gray-200);
        }

        .search-box input {
            width: 100%;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid var(--gray-300);
            font-family: inherit;
            font-size: 0.85rem;
            outline: none;
            transition: all 0.2s;
        }

        .search-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
        }

        .sidebar-nav {
            overflow-y: auto;
            padding: 12px 10px;
            flex-grow: 1;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border-radius: 6px;
            color: var(--gray-700);
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.15s;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .nav-item:hover {
            background: var(--gray-100);
            color: var(--primary);
        }

        .method-badge {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            min-width: 44px;
            text-align: center;
            flex-shrink: 0;
        }

        .method-get { background: #dbeafe; color: #1e40af; }
        .method-post { background: #dcfce7; color: #166534; }
        .method-put { background: #fef3c7; color: #92400e; }
        .method-patch { background: #fae8ff; color: #86198f; }
        .method-delete { background: #fee2e2; color: #991b1b; }

        /* Main Content */
        .main-content {
            flex-grow: 1;
            padding: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .top-banner {
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            color: white;
            border-radius: 16px;
            padding: 36px 32px;
            margin-bottom: 36px;
            box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .top-banner h1 {
            font-size: 1.9rem;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .top-banner p {
            font-size: 0.95rem;
            opacity: 0.95;
            max-width: 650px;
        }

        .print-btn {
            background: #ffffff;
            color: var(--primary);
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }

        /* Config Card */
        .config-card {
            background: #ffffff;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 36px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .config-card h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--dark);
        }

        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }

        .config-item {
            background: var(--gray-50);
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid var(--gray-200);
        }

        .config-item .label {
            font-size: 0.75rem;
            color: var(--gray-600);
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .config-item .val {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            color: var(--primary);
            font-weight: 600;
            word-break: break-all;
        }

        /* API Card */
        .api-card {
            background: #ffffff;
            border: 1px solid var(--gray-200);
            border-radius: 14px;
            padding: 28px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            scroll-margin-top: 30px;
        }

        .api-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .api-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .api-num {
            background: var(--gray-100);
            color: var(--gray-800);
            font-size: 0.85rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
        }

        .url-box {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 18px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .url-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.82rem;
        }

        .url-row .tag {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.68rem;
            padding: 2px 6px;
            border-radius: 4px;
            min-width: 50px;
            text-align: center;
        }

        .tag-local { background: #e0f2fe; color: #0369a1; }
        .tag-live { background: #fef3c7; color: #92400e; }

        .url-row .link {
            font-family: 'JetBrains Mono', monospace;
            color: var(--dark);
            font-weight: 600;
            word-break: break-all;
        }

        .api-section-title {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-600);
            font-weight: 700;
            margin: 16px 0 8px;
        }

        .desc-box {
            background: #f8fafc;
            border-left: 4px solid var(--primary);
            padding: 12px 16px;
            border-radius: 0 8px 8px 0;
            font-size: 0.9rem;
            color: var(--gray-800);
            margin-bottom: 14px;
        }

        .desc-box .usage-tag {
            display: inline-block;
            background: #e0e7ff;
            color: #3730a3;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            margin-top: 8px;
        }

        .code-block {
            background: var(--code-bg);
            color: #f8fafc;
            padding: 14px 16px;
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            overflow-x: auto;
            position: relative;
            margin-bottom: 14px;
            line-height: 1.5;
        }

        .headers-badge {
            display: inline-block;
            background: var(--gray-100);
            border: 1px solid var(--gray-200);
            padding: 6px 12px;
            border-radius: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            color: var(--gray-800);
            margin-bottom: 12px;
        }

        /* Print Mode */
        @media print {
            .sidebar, .print-btn, .search-box {
                display: none !important;
            }
            body {
                background: white !important;
                display: block !important;
            }
            .main-content {
                padding: 0 !important;
                max-width: 100% !important;
            }
            .api-card {
                page-break-inside: avoid;
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                margin-bottom: 20px !important;
                padding: 20px !important;
            }
            .top-banner {
                background: #4f46e5 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar with all 137 APIs -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>Peers Store APIs <span class="badge">137 APIs</span></h2>
            <div style="font-size: 0.78rem; color: var(--gray-600); margin-top: 4px;">Complete Flutter & Web Master Guide</div>
        </div>
        <div class="search-box">
            <input type="text" id="apiSearch" placeholder="Search API by number, name, path..." onkeyup="filterApis()">
        </div>
        <nav class="sidebar-nav" id="sidebarNav">
            <?php foreach ($apis as $api): ?>
                <?php 
                    $methodClass = 'method-' . strtolower($api['method']);
                ?>
                <a href="#api-<?= $api['num'] ?>" class="nav-item">
                    <span class="method-badge <?= $methodClass ?>"><?= htmlspecialchars($api['method']) ?></span>
                    <span><?= $api['num'] ?>. <?= htmlspecialchars($api['title']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- Main Content Container -->
    <main class="main-content">

        <!-- Top Header Banner -->
        <section class="top-banner">
            <div>
                <h1>Peers Global Unity — Store & Wallet Master API Guide</h1>
                <p>Comprehensive 100% English documentation for Flutter Mobile App Developers, Frontend Teams, and Backend Engineers across all 137 APIs.</p>
            </div>
            <button class="print-btn" onclick="window.print()">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"></path></svg>
                Print / Save as PDF
            </button>
        </section>

        <!-- Base Configuration Card -->
        <section class="config-card">
            <h3>🌐 Environment Base URLs & Global Headers</h3>
            <div class="config-grid">
                <div class="config-item">
                    <div class="label">Local Development URL</div>
                    <div class="val">http://localhost:8000/api</div>
                </div>
                <div class="config-item">
                    <div class="label">Live Production URL</div>
                    <div class="val">https://api.peersglobalunity.com/api</div>
                </div>
                <div class="config-item">
                    <div class="label">Default Authentication Header</div>
                    <div class="val">Authorization: Bearer &lt;SANCTUM_TOKEN&gt;</div>
                </div>
                <div class="config-item">
                    <div class="label">Payload Format</div>
                    <div class="val">Content-Type: application/json; Accept: application/json</div>
                </div>
            </div>
        </section>

        <!-- RENDER ALL 137 APIS SEQUENTIALLY -->
        <?php foreach ($apis as $api): ?>
            <?php 
                $methodClass = 'method-' . strtolower($api['method']);
            ?>
            <article class="api-card" id="api-<?= $api['num'] ?>">
                <div class="api-header">
                    <div class="api-title">
                        <span class="api-num">#<?= $api['num'] ?></span>
                        <?= htmlspecialchars($api['title']) ?>
                    </div>
                    <span class="method-badge <?= $methodClass ?>"><?= htmlspecialchars($api['method']) ?></span>
                </div>

                <!-- URLs -->
                <div class="url-box">
                    <div class="url-row">
                        <span class="tag tag-local">Local</span>
                        <span class="link"><?= htmlspecialchars($api['localUrl']) ?></span>
                    </div>
                    <div class="url-row">
                        <span class="tag tag-live">Live</span>
                        <span class="link"><?= htmlspecialchars($api['liveUrl']) ?></span>
                    </div>
                </div>

                <!-- In-depth Description for Developers -->
                <div class="desc-box">
                    <strong>Developer Description & Purpose:</strong><br>
                    <?= htmlspecialchars($api['purpose']) ?>
                    <br>
                    <span class="usage-tag">📱 Flutter & Frontend Usage: <?= htmlspecialchars($api['flutterUsage']) ?></span>
                </div>

                <!-- Headers -->
                <div class="api-section-title">Required Headers</div>
                <div class="headers-badge">
                    <?= htmlspecialchars($api['headers']) ?>
                </div>

                <!-- Request Body (if any) -->
                <?php if (!empty($api['reqBody'])): ?>
                    <div class="api-section-title">Request Body (JSON)</div>
                    <pre class="code-block"><?= htmlspecialchars($api['reqBody']) ?></pre>
                <?php else: ?>
                    <div class="api-section-title">Request Body</div>
                    <div style="font-size: 0.85rem; color: var(--gray-600); margin-bottom: 12px;">No request body required (Query parameters or URL parameter only).</div>
                <?php endif; ?>

                <!-- Response Body -->
                <div class="api-section-title">Success Response Example</div>
                <pre class="code-block"><?= htmlspecialchars($api['resBody']) ?></pre>
            </article>
        <?php endforeach; ?>

    </main>

    <script>
        function filterApis() {
            const query = document.getElementById('apiSearch').value.toLowerCase();
            const cards = document.querySelectorAll('.api-card');
            const navItems = document.querySelectorAll('.nav-item');

            cards.forEach(card => {
                const text = card.innerText.toLowerCase();
                if (text.includes(query)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });

            navItems.forEach(item => {
                const text = item.innerText.toLowerCase();
                if (text.includes(query)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
<?php
$htmlContent = ob_get_clean();
file_put_contents(__DIR__ . '/../public/peers_store_137_apis_documentation.html', $htmlContent);
echo "Successfully generated HTML documentation with " . count($apis) . " APIs.\n";
