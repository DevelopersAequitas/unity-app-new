<?php

// Ensure memory limit is sufficient
ini_set('memory_limit', '512M');

$sourcePath = __DIR__.'/../docs/api/PEERS_STORE_137_APIS_COMPLETE_ENGLISH_DOC.md';
$content = file_get_contents($sourcePath);

preg_match_all('/###\s*(\d+)\.\s*([^\r\n]+)(.*?)(?=(?:###\s*\d+\.|$))/s', $content, $rawMatches, PREG_SET_ORDER);

// Detailed individual 137 API knowledge base
require_once __DIR__.'/api_descriptions_data.php';

$apis = [];

foreach ($rawMatches as $m) {
    $num = (int) $m[1];
    $title = trim($m[2]);
    $rawBody = $m[3];

    // Method
    preg_match('/-\s*\*\*Method\*\*:\s*`?([A-Z]+)`?/i', $rawBody, $methodMatch);
    $method = ! empty($methodMatch[1]) ? strtoupper($methodMatch[1]) : 'GET';

    // URL / Path
    preg_match('/-\s*\*\*(?:Live|Local)?\s*URL\*\*:\s*`?([^`\r\n]+)`?/i', $rawBody, $urlMatch);
    $rawUrl = ! empty($urlMatch[1]) ? trim($urlMatch[1]) : '';
    $path = preg_replace('/^https?:\/\/[^\/]+/i', '', $rawUrl);
    if (empty($path)) {
        $path = '/api/v1/store';
    }
    if (! str_starts_with($path, '/')) {
        $path = '/'.$path;
    }
    if (! str_starts_with($path, '/api')) {
        $path = '/api'.$path;
    }

    // Headers
    preg_match('/-\s*\*\*Headers\*\*:\s*([^\r\n]+)/i', $rawBody, $headersMatch);
    $headers = ! empty($headersMatch[1]) ? trim(trim($headersMatch[1]), '`') : 'Authorization: Bearer <TOKEN>, Content-Type: application/json';

    // Request Body
    preg_match('/-\s*\*\*Request Body\*\*:\s*(?:`None`|None)?\s*(?:```(?:json)?\s*([\s\S]*?)```)?/i', $rawBody, $reqMatch);
    $reqBody = ! empty($reqMatch[1]) ? trim($reqMatch[1]) : null;
    if ($reqBody) {
        $reqBody = str_replace('https://...', 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=500', $reqBody);
    }

    // Response Body
    preg_match('/-\s*\*\*Response\s*(?:\(JSON\))?\*\*:\s*```(?:json)?\s*([\s\S]*?)```/i', $rawBody, $resMatch);
    $resBody = ! empty($resMatch[1]) ? trim($resMatch[1]) : null;
    if ($resBody) {
        $resBody = str_replace('https://...', 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=500', $resBody);
    } else {
        $resBody = "{\n  \"success\": true,\n  \"message\": \"Request executed successfully\",\n  \"data\": {}\n}";
    }

    // Lookup unique descriptions from array
    $customInfo = getApiSpecificData($num, $title, $method, $path);

    $apis[$num] = [
        'num' => $num,
        'title' => $title,
        'method' => $method,
        'path' => $path,
        'headers' => $headers,
        'reqBody' => $reqBody,
        'resBody' => $resBody,
        'purpose' => $customInfo['purpose'],
        'flutter' => $customInfo['flutter'],
        'category' => $customInfo['category'],
    ];
}

echo 'Loaded '.count($apis)." API entries with unique metadata.\n";

// 1. Build Markdown File
$md = "# 🚀 Peers Global Unity — All 137 Store & Coin Wallet APIs (Official Master Guide)\n\n";
$md .= "> **Base Endpoint**: `/api` (Live & Local root)\n";
$md .= "> **Default Auth**: `Authorization: Bearer <SANCTUM_TOKEN>`\n";
$md .= "> **Format**: `Content-Type: application/json`, `Accept: application/json`\n\n";
$md .= "---\n\n";

foreach ($apis as $num => $a) {
    $md .= "### {$num}. {$a['title']}\n";
    $md .= "- **Method**: `{$a['method']}`\n";
    $md .= "- **Endpoint**: `{$a['path']}`\n";
    $md .= "- **Category / Module**: {$a['category']}\n";
    $md .= "- **Developer Purpose & Deep Technical Overview**: {$a['purpose']}\n";
    $md .= "- **Flutter / Frontend Integration Guide**: {$a['flutter']}\n";
    $md .= "- **Headers**: `{$a['headers']}`\n";
    if ($a['reqBody']) {
        $md .= "- **Request Body**:\n```json\n".$a['reqBody']."\n```\n";
    } else {
        $md .= "- **Request Body**: `None`\n";
    }
    $md .= "- **Success Response (JSON)**:\n```json\n".$a['resBody']."\n```\n\n";
    $md .= "---\n\n";
}

file_put_contents(__DIR__.'/../docs/api/PEERS_STORE_137_APIS_MASTER_DOCUMENTATION.md', $md);
echo "Successfully generated docs/api/PEERS_STORE_137_APIS_MASTER_DOCUMENTATION.md\n";

// 2. Build Interactive HTML File
$html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peers Global Unity — All 137 Store & Coin Wallet APIs Documentation</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #090d16;
            --bg-card: #111827;
            --bg-card-hover: #162032;
            --border-color: #1f2937;
            --text-primary: #f9fafb;
            --text-secondary: #9ca3af;
            --text-muted: #6b7280;
            --accent-purple: #8b5cf6;
            --accent-blue: #3b82f6;
            --accent-green: #10b981;
            --accent-amber: #f59e0b;
            --accent-red: #ef4444;
            --badge-get: #10b981;
            --badge-post: #3b82f6;
            --badge-put: #f59e0b;
            --badge-delete: #ef4444;
            --badge-patch: #8b5cf6;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-primary);
            line-height: 1.6;
            padding: 0;
            margin: 0;
        }

        /* Top Header Navbar */
        .top-navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(17, 24, 39, 0.95);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .brand-section {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 20px;
            color: white;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
        }

        .brand-info h1 {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.02em;
            background: linear-gradient(90deg, #ffffff, #cbd5e1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand-info p {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .search-box {
            position: relative;
            width: 320px;
        }

        .search-input {
            width: 100%;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 10px 14px 10px 38px;
            color: white;
            font-size: 13px;
            font-family: inherit;
            outline: none;
            transition: all 0.2s;
        }

        .search-input:focus {
            border-color: var(--accent-purple);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.25);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-pdf {
            background: linear-gradient(135deg, #8b5cf6, #6366f1);
            color: white;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.35);
        }

        .btn-pdf:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }

        /* Container */
        .container {
            max-width: 1360px;
            margin: 0 auto;
            padding: 32px 24px;
        }

        /* Hero Banner */
        .hero-banner {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05));
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 16px;
            padding: 28px 32px;
            margin-bottom: 32px;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            align-items: center;
        }

        .hero-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .stat-card {
            background: rgba(17, 24, 39, 0.8);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 14px 18px;
            text-align: center;
        }

        .stat-num {
            font-size: 24px;
            font-weight: 800;
            color: #a78bfa;
        }

        .stat-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        /* Quick Filter Badges */
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .filter-btn {
            background: #1e293b;
            color: var(--text-secondary);
            border: 1px solid #334155;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-btn.active, .filter-btn:hover {
            background: var(--accent-purple);
            color: white;
            border-color: var(--accent-purple);
        }

        /* API Grid / List */
        .api-list {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .api-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            overflow: hidden;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .api-card:hover {
            border-color: #374151;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
        }

        .api-header {
            padding: 20px 24px;
            background: #162032;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .api-id-title {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .api-number {
            width: 34px;
            height: 34px;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            color: #cbd5e1;
        }

        .api-title {
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
        }

        .api-category-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            background: rgba(139, 92, 246, 0.15);
            color: #c4b5fd;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        .endpoint-row {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            margin-top: 10px;
            background: #0f172a;
            border: 1px solid #1e293b;
            padding: 10px 14px;
            border-radius: 8px;
            font-family: "JetBrains Mono", monospace;
            font-size: 13px;
        }

        .http-badge {
            font-weight: 700;
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 4px;
            color: #ffffff;
            text-transform: uppercase;
        }

        .http-GET { background: var(--badge-get); }
        .http-POST { background: var(--badge-post); }
        .http-PUT { background: var(--badge-put); }
        .http-DELETE { background: var(--badge-delete); }
        .http-PATCH { background: var(--badge-patch); }

        .endpoint-path {
            color: #38bdf8;
            word-break: break-all;
            flex-grow: 1;
        }

        .api-body {
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Detail Blocks */
        .info-block {
            background: #0f172a;
            border-radius: 10px;
            border-left: 4px solid var(--accent-purple);
            padding: 16px 20px;
        }

        .info-block.flutter {
            border-left-color: #0ea5e9;
            background: rgba(14, 165, 233, 0.05);
            border: 1px solid rgba(14, 165, 233, 0.2);
            border-left: 4px solid #0ea5e9;
        }

        .block-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #a78bfa;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-block.flutter .block-title {
            color: #38bdf8;
        }

        .block-text {
            font-size: 13.5px;
            color: #cbd5e1;
            line-height: 1.6;
        }

        .block-text strong {
            color: #f8fafc;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .meta-item {
            display: flex;
            gap: 8px;
            font-size: 13px;
        }

        .meta-label {
            color: var(--text-muted);
            min-width: 90px;
            font-weight: 600;
        }

        .meta-val {
            color: #e2e8f0;
            font-family: "JetBrains Mono", monospace;
            font-size: 12px;
            word-break: break-all;
        }

        /* Code Blocks */
        .code-container {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .code-header {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-secondary);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        pre {
            background: #090d16;
            border: 1px solid #1f2937;
            border-radius: 8px;
            padding: 16px;
            overflow-x: auto;
            font-family: "JetBrains Mono", monospace;
            font-size: 12.5px;
            color: #e2e8f0;
            max-height: 380px;
        }

        /* Print Mode */
        @media print {
            .top-navbar, .filter-bar, .hero-banner {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                color: #000000 !important;
            }
            .api-card {
                page-break-inside: avoid;
                border: 1px solid #ccc !important;
                background: #ffffff !important;
                margin-bottom: 20px !important;
            }
            .api-header {
                background: #f1f5f9 !important;
            }
            .api-title {
                color: #0f172a !important;
            }
            .endpoint-row, pre, .info-block {
                background: #f8fafc !important;
                color: #0f172a !important;
                border-color: #cbd5e1 !important;
            }
            .block-text, .meta-val {
                color: #334155 !important;
            }
        }
    </style>
</head>
<body>

    <!-- Sticky Navbar -->
    <header class="top-navbar">
        <div class="brand-section">
            <div class="brand-logo">PG</div>
            <div class="brand-info">
                <h1>Peers Global Unity Store & Wallet</h1>
                <p>Official 137 APIs Complete Architecture & Integration Guide</p>
            </div>
        </div>
        <div class="nav-actions">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" id="apiSearchInput" class="search-input" placeholder="Search API by name, method or path..." onkeyup="filterApis()">
            </div>
            <button class="btn-action btn-pdf" onclick="window.print()">
                <span>📄</span> Export / Save as PDF
            </button>
        </div>
    </header>

    <main class="container">
        <!-- Hero Section -->
        <section class="hero-banner">
            <div>
                <h2 style="font-size: 22px; font-weight: 800; margin-bottom: 8px;">137 Endpoints Full Architecture Reference</h2>
                <p style="color: #94a3b8; font-size: 14px;">Every endpoint below features comprehensive technical overviews, exact database table mappings, error validations, request payloads, full response schemas, and dedicated <strong>Flutter & Frontend Integration Guides</strong>.</p>
            </div>
            <div class="hero-stats">
                <div class="stat-card">
                    <div class="stat-num">137</div>
                    <div class="stat-label">Total APIs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num">64</div>
                    <div class="stat-label">Customer Store</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num">73</div>
                    <div class="stat-label">Admin Management</div>
                </div>
            </div>
        </section>

        <!-- Quick Filter Buttons -->
        <div class="filter-bar">
            <button class="filter-btn active" onclick="setFilter(\'all\', this)">All APIs (137)</button>
            <button class="filter-btn" onclick="setFilter(\'Customer Store\', this)">Store & Catalog</button>
            <button class="filter-btn" onclick="setFilter(\'Cart\', this)">Cart & Addresses</button>
            <button class="filter-btn" onclick="setFilter(\'Checkout\', this)">Checkout & Orders</button>
            <button class="filter-btn" onclick="setFilter(\'Wallet\', this)">Wallet & Ledger</button>
            <button class="filter-btn" onclick="setFilter(\'Returns\', this)">Returns & Support</button>
            <button class="filter-btn" onclick="setFilter(\'Admin\', this)">Admin Portal</button>
        </div>

        <!-- API List -->
        <div class="api-list" id="apiList">';

foreach ($apis as $num => $a) {
    $reqBlock = '';
    if ($a['reqBody']) {
        $escapedReq = htmlspecialchars($a['reqBody'], ENT_QUOTES, 'UTF-8');
        $reqBlock = '<div class="code-container">
            <div class="code-header"><span>Request Body (JSON)</span></div>
            <pre><code>'.$escapedReq.'</code></pre>
        </div>';
    }

    $escapedRes = htmlspecialchars($a['resBody'], ENT_QUOTES, 'UTF-8');
    $escapedPurpose = htmlspecialchars($a['purpose'], ENT_QUOTES, 'UTF-8');
    $escapedFlutter = $a['flutter']; // Contains strong HTML tags

    $html .= '
    <article class="api-card" data-category="'.htmlspecialchars($a['category']).'" data-text="'.htmlspecialchars(strtolower($a['title'].' '.$a['method'].' '.$a['path'].' '.$a['category'])).'">
        <div class="api-header">
            <div class="api-id-title">
                <div class="api-number">#'.$num.'</div>
                <h3 class="api-title">'.htmlspecialchars($a['title']).'</h3>
            </div>
            <span class="api-category-badge">'.htmlspecialchars($a['category']).'</span>
            <div class="endpoint-row">
                <span class="http-badge http-'.$a['method'].'">'.$a['method'].'</span>
                <span class="endpoint-path">'.htmlspecialchars($a['path']).'</span>
            </div>
        </div>
        <div class="api-body">
            <div class="info-block">
                <div class="block-title"><span>📘</span> Developer Purpose & Deep Technical Overview</div>
                <div class="block-text">'.$escapedPurpose.'</div>
            </div>

            <div class="info-block flutter">
                <div class="block-title"><span>📱</span> Flutter & Frontend Integration Guide</div>
                <div class="block-text">'.$escapedFlutter.'</div>
            </div>

            <div class="meta-grid">
                <div class="meta-item">
                    <span class="meta-label">Headers:</span>
                    <span class="meta-val">'.htmlspecialchars($a['headers']).'</span>
                </div>
            </div>

            '.$reqBlock.'

            <div class="code-container">
                <div class="code-header"><span>Success Response (JSON)</span></div>
                <pre><code>'.$escapedRes.'</code></pre>
            </div>
        </div>
    </article>';
}

$html .= '
        </div>
    </main>

    <script>
        function filterApis() {
            const query = document.getElementById("apiSearchInput").value.toLowerCase().trim();
            const cards = document.querySelectorAll(".api-card");
            cards.forEach(card => {
                const text = card.getAttribute("data-text");
                if (!query || text.includes(query)) {
                    card.style.display = "";
                } else {
                    card.style.display = "none";
                }
            });
        }

        function setFilter(cat, btn) {
            document.querySelectorAll(".filter-btn").forEach(b => b.classList.remove("active"));
            btn.classList.add("active");
            const cards = document.querySelectorAll(".api-card");
            cards.forEach(card => {
                const itemCat = card.getAttribute("data-category");
                if (cat === "all" || itemCat.toLowerCase().includes(cat.toLowerCase())) {
                    card.style.display = "";
                } else {
                    card.style.display = "none";
                }
            });
        }
    </script>
</body>
</html>';

file_put_contents(__DIR__.'/../public/peers_store_137_apis_documentation.html', $html);
echo "Successfully generated public/peers_store_137_apis_documentation.html\n";
