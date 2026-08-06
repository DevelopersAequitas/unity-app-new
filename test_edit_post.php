<?php

require_once __DIR__.'/vendor/autoload.php';

use App\Http\Controllers\Api\PostController;
use App\Models\User;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "==================================================\n";
echo "TESTING ERROR MESSAGE FOR NON-EXISTENT POST ID\n";
echo "==================================================\n";

// 1. Find a user
$user = User::where('status', 'active')->first() ?: User::first();
if (! $user) {
    echo "❌ No user found in database to test.\n";
    exit(1);
}

echo "Testing as user: {$user->display_name}\n";

// 2. Use a random UUID (which doesn't exist in the database)
$fakePostId = '00000000-0000-0000-0000-000000000000';
echo "Sending update request for non-existent Post ID: {$fakePostId}\n\n";

$payload = [
    'content_text' => 'Testing fake post update.',
];

$postController = app(PostController::class);

$request = Request::create("/api/v1/posts/{$fakePostId}", 'PUT', $payload);
$request->setUserResolver(fn () => $user);

try {
    $response = $postController->update($request, $fakePostId, app(NotificationDispatchService::class));

    echo 'Response Status: '.$response->getStatusCode()."\n";
    echo "Response Content:\n";
    echo json_encode(json_decode($response->getContent()), JSON_PRETTY_PRINT)."\n\n";
} catch (Exception $e) {
    echo '❌ Exception occurred: '.$e->getMessage()."\n";
}
