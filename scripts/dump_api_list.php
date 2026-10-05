<?php

$content = file_get_contents(__DIR__ . '/../docs/api/PEERS_STORE_137_APIS_COMPLETE_ENGLISH_DOC.md');
preg_match_all('/###\s*(\d+)\.\s*([^\r\n]+)(.*?)(?=(?:###\s*\d+\.|$))/s', $content, $rawMatches, PREG_SET_ORDER);

$apis = [];
foreach ($rawMatches as $m) {
    $num = (int)$m[1];
    $title = trim($m[2]);
    $body = $m[3];

    preg_match('/-\s*\*\*Method\*\*:\s*`?([A-Z]+)`?/i', $body, $methodMatch);
    $method = !empty($methodMatch[1]) ? strtoupper($methodMatch[1]) : 'GET';

    preg_match('/-\s*\*\*Live URL\*\*:\s*`?([^`\r\n]+)`?/i', $body, $urlMatch);
    $rawUrl = !empty($urlMatch[1]) ? trim($urlMatch[1]) : '';
    $path = preg_replace('/^https?:\/\/[^\/]+/i', '', $rawUrl);
    if (empty($path)) {
        preg_match('/-\s*\*\*Local URL\*\*:\s*`?([^`\r\n]+)`?/i', $body, $localMatch);
        $rawUrl = !empty($localMatch[1]) ? trim($localMatch[1]) : '';
        $path = preg_replace('/^https?:\/\/[^\/]+/i', '', $rawUrl);
    }
    if (!str_starts_with($path, '/')) $path = '/' . $path;
    if (!str_starts_with($path, '/api')) $path = '/api' . $path;

    preg_match('/-\s*\*\*Purpose\s*&\s*Description\*\*:\s*([^\r\n]+)/i', $body, $purposeMatch);
    $purpose = !empty($purposeMatch[1]) ? trim($purposeMatch[1]) : '';

    $apis[$num] = [
        'num' => $num,
        'title' => $title,
        'method' => $method,
        'path' => $path,
        'source_purpose' => $purpose
    ];
}

file_put_contents(__DIR__ . '/api_meta.json', json_encode($apis, JSON_PRETTY_PRINT));
echo "Dumped " . count($apis) . " APIs\n";
