<?php

$mdPath = __DIR__.'/../docs/api/PEERS_STORE_137_APIS_MASTER_DOCUMENTATION.md';
$content = file_get_contents($mdPath);

preg_match_all('/###\s*(\d+)\.\s*([^\r\n]+)(.*?)(?=(?:###\s*\d+\.|$))/s', $content, $matches, PREG_SET_ORDER);

$out = "# 🚀 Peers Global Unity — Complete Store & Coin Wallet API Documentation (137 APIs)\n\n";
$out .= "> **Local Base URL**: `http://localhost:8000/api`  \n";
$out .= "> **Live Base URL**: `https://api.peersglobalunity.com/api`  \n";
$out .= "> **Default Auth**: `Authorization: Bearer <SANCTUM_TOKEN>`  \n";
$out .= "> **Format**: `Content-Type: application/json`, `Accept: application/json`  \n\n";
$out .= "---\n\n";

foreach ($matches as $m) {
    $num = (int) $m[1];
    $title = trim($m[2]);
    $body = $m[3];

    preg_match('/-\s*\*\*Method\*\*:\s*`?([A-Z]+)`?/i', $body, $methodMatch);
    $method = ! empty($methodMatch[1]) ? strtoupper($methodMatch[1]) : 'GET';

    preg_match('/-\s*\*\*URL\*\*:\s*`?([^`\r\n]+)`?/i', $body, $urlMatch);
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

    $localUrl = 'http://localhost:8000'.$path;
    $liveUrl = 'https://api.peersglobalunity.com'.$path;

    preg_match('/-\s*\*\*Purpose\*\*:\s*([^\r\n]+)/i', $body, $purposeMatch);
    $purpose = ! empty($purposeMatch[1]) ? trim($purposeMatch[1]) : 'Process store transaction.';

    preg_match('/-\s*\*\*Headers\*\*:\s*([^\r\n]+)/i', $body, $headersMatch);
    $headers = ! empty($headersMatch[1]) ? trim($headersMatch[1]) : 'Authorization: Bearer <TOKEN>';

    preg_match('/-\s*\*\*Request Body\*\*:\s*(?:`None`|None)?\s*(?:```(?:json)?\s*([\s\S]*?)```)?/i', $body, $reqMatch);
    $reqBody = ! empty($reqMatch[1]) ? trim($reqMatch[1]) : null;

    preg_match('/-\s*\*\*Response\*\*:\s*```(?:json)?\s*([\s\S]*?)```/i', $body, $resMatch);
    $resBody = ! empty($resMatch[1]) ? trim($resMatch[1]) : "{\n  \"success\": true,\n  \"message\": \"Success\",\n  \"data\": {}\n}";

    if ($num >= 1 && $num <= 4) {
        $flutterUsage = 'Call during mobile app splash/home initial load or Wallet Screen. Populates store banners and shows coin breakdown (Earned vs Bonus).';
    } elseif ($num >= 5 && $num <= 10) {
        $flutterUsage = 'Call in Store Catalog & Search screens. Render horizontal category chips, product list view, search filtering, and detailed product page.';
    } elseif ($num >= 11 && $num <= 18) {
        $flutterUsage = 'Call in Shopping Cart screen. Manage items quantity, remove items, verify real-time stock, and calculate subtotal.';
    } elseif ($num >= 19 && $num <= 24) {
        $flutterUsage = 'Call in Delivery Address & Pickup Point selection. Add shipping address, check pincode courier SLA, or select central pickup points.';
    } elseif ($num >= 25 && $num <= 28) {
        $flutterUsage = 'Call on entering Checkout screen. Pre-calculates 15-minute valuation quote and triggers SMS/In-app OTP if high coins threshold is reached.';
    } elseif ($num >= 29 && $num <= 33) {
        $flutterUsage = "Call on 'Place Order' or 'Cancel Order'. Atomically executes wallet deduction, inventory decrement, and lifecycle updates.";
    } elseif ($num >= 34 && $num <= 38) {
        $flutterUsage = 'Call on Order Details screen. Track courier shipment AWB live milestones and view official tax receipt details.';
    } elseif ($num >= 39 && $num <= 42) {
        $flutterUsage = 'Call on Return Request screen. Allows peer to request item return within 7 days and track coin refund status.';
    } elseif ($num >= 43 && $num <= 45) {
        $flutterUsage = 'Call on Product Detail Reviews section. Verified buyers submit 1-5 star ratings and reviews.';
    } elseif ($num >= 46 && $num <= 48) {
        $flutterUsage = 'Call in Customer Support screen. Create tickets regarding order issues and exchange 2-way live messages with admin.';
    } elseif ($num >= 49 && $num <= 55) {
        $flutterUsage = 'Call in Membership & Legal Policy screens. Buy Gold/Silver passes with coin balance and view terms.';
    } elseif ($num >= 56 && $num <= 58) {
        $flutterUsage = "Call in 'My Digital Library' screen. Download purchased PDFs/eBooks via signed secure URLs.";
    } elseif ($num >= 59 && $num <= 64) {
        $flutterUsage = 'Call in User Notification Feed. Real-time order dispatch notifications and coin transaction alerts.';
    } else {
        $flutterUsage = 'Admin Portal execution: Catalog maintenance, stock adjustments, AWB dispatch, return inspection, maker-checker approvals, and report exports.';
    }

    $out .= "### {$num}. {$title}\n";
    $out .= "- **Method**: `{$method}`\n";
    $out .= "- **Local URL**: `{$localUrl}`\n";
    $out .= "- **Live URL**: `{$liveUrl}`\n";
    $out .= "- **Purpose & Description**: {$purpose}\n";
    $out .= "- **Flutter / Frontend Usage**: {$flutterUsage}\n";
    $out .= "- **Headers**: `{$headers}`\n";
    if (! empty($reqBody)) {
        $out .= "- **Request Body (JSON)**:\n```json\n{$reqBody}\n```\n";
    } else {
        $out .= "- **Request Body**: `None`\n";
    }
    $out .= "- **Response (JSON)**:\n```json\n{$resBody}\n```\n\n";
    $out .= "---\n\n";
}

file_put_contents(__DIR__.'/../docs/api/PEERS_STORE_137_APIS_COMPLETE_ENGLISH_DOC.md', $out);
echo "Successfully generated markdown documentation with 137 APIs.\n";
