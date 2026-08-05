<?php

require_once __DIR__.'/vendor/autoload.php';

use App\Http\Controllers\Api\PostController;
use App\Models\User;
use App\Models\Post;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "==================================================\n";
echo "TESTING EDIT POST API FOR USER CREATED POSTS\n";
echo "==================================================\n";

// 1. Find a user
$user = User::where('status', 'active')->first() ?: User::first();
if (!$user) {
    echo "❌ No user found in database to test.\n";
    exit(1);
}

echo "Testing as user: {$user->display_name} ({$user->email})\n";

// 2. Create a standard user post
$post = Post::create([
    'user_id' => $user->id,
    'content_text' => 'This is my original post content created for testing.',
    'visibility' => 'public',
    'moderation_status' => 'pending',
    'sponsored' => false,
    'is_deleted' => false,
]);

echo "✅ Created post with ID: {$post->id} (post_type is: '{$post->post_type}')\n\n";

// 3. Try to edit the post
$payload = [
    'content_text' => 'This is my UPDATED post content! The edit API works!',
    'tags' => ['updated', 'test-tag']
];

$postController = app(PostController::class);

$request = Request::create("/api/v1/posts/{$post->id}", 'PUT', $payload);
$request->setUserResolver(fn () => $user);

try {
    $response = $postController->update($request, $post->id, app(\App\Services\Notifications\NotificationDispatchService::class));
    
    echo "Response Status: " . $response->getStatusCode() . "\n";
    echo "Response Content:\n";
    echo json_encode(json_decode($response->getContent()), JSON_PRETTY_PRINT) . "\n\n";
    
    // Clean up test post
    $post->forceDelete();
    echo "🗑️ Test post cleaned up from database.\n";
} catch (\Exception $e) {
    echo "❌ Exception occurred: " . $e->getMessage() . "\n";
    $post->forceDelete();
}
