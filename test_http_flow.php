<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AdminUser;
use App\Models\Post;
use Illuminate\Http\Request;

// 1. Get or create post
$post = Post::withTrashed()->find('a0974b8e-bf01-4489-9cb3-7686ea1d4cfb');
$post->deleted_at = null;
$post->is_deleted = false;
$post->is_active = true;
$post->deactivated_at = null;
$post->save();

$admin = AdminUser::first();

// Step 1: POST /admin/posts/{id}/deactivate
$postRequest = Request::create(
    '/admin/posts/' . $post->id . '/deactivate',
    'POST',
    [
        'remarks' => 'Testing success message visibility',
        '_token' => 'dummy',
    ],
    [],
    [],
    [
        'HTTP_REFERER' => 'http://127.0.0.1:8000/admin/posts?page=1',
        'HTTP_HOST' => '127.0.0.1:8000',
    ]
);
\Illuminate\Support\Facades\Auth::guard('admin')->setUser($admin);

$response = $kernel->handle($postRequest);
echo "POST Status: " . $response->getStatusCode() . PHP_EOL;
$location = $response->headers->get('Location');
echo "POST Redirect Target: " . $location . PHP_EOL;

// Get session cookies
$cookies = $response->headers->getCookies();
echo "Cookies count: " . count($cookies) . PHP_EOL;

// Step 2: Follow Redirect GET
$getRequest = Request::create(
    $location ?: 'http://127.0.0.1:8000/admin/posts?page=1',
    'GET',
    [],
    [],
    [],
    [
        'HTTP_HOST' => '127.0.0.1:8000',
    ]
);
// Pass cookies
foreach ($cookies as $c) {
    $getRequest->cookies->set($c->getName(), $c->getValue());
}
\Illuminate\Support\Facades\Auth::guard('admin')->setUser($admin);

$kernel->terminate($postRequest, $response);

$getResponse = $kernel->handle($getRequest);
echo "GET Status: " . $getResponse->getStatusCode() . PHP_EOL;

$content = $getResponse->getContent();
if (strpos($content, 'alert-success') !== false) {
    echo "SUCCESS: Found 'alert-success' in HTML!" . PHP_EOL;
    preg_match('/<div class="alert alert-success[^>]*>.*?<\/div>/s', $content, $m);
    echo "Alert HTML: " . ($m[0] ?? '') . PHP_EOL;
} else {
    echo "FAILED: 'alert-success' NOT found in HTML!" . PHP_EOL;
    if (strpos($content, 'alert-warning') !== false) {
        echo "Found alert-warning instead!\n";
    }
    if (strpos($content, 'alert-danger') !== false) {
        echo "Found alert-danger instead!\n";
    }
}
