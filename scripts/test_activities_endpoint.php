<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Leader\Services\LeaderMember360Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$service = $app->make(LeaderMember360Service::class);

$targetId = '75fa33c4-b560-4728-8013-ee4c92bdbd3b';
$user = User::query()->where('id', $targetId)->first();

if (!$user) {
    echo "Target user {$targetId} not in local DB, finding user with most activities...\n";
    // Check which users have records in referrals, business_deals, p2p_meetings, etc.
    $userId = DB::table('referrals')->value('from_user_id')
        ?? DB::table('business_deals')->value('from_user_id')
        ?? DB::table('p2p_meetings')->value('initiator_user_id')
        ?? DB::table('coins_ledger')->value('user_id');
    $user = User::query()->where('id', $userId)->first() ?? User::query()->first();
}

if ($user) {
    echo "Testing activities for user: {$user->id} ({$user->display_name})\n";
    $result = $service->getActivities($user->id, [
        'page' => 1,
        'per_page' => 10,
    ]);

    echo "Total activities: " . $result['meta']['total'] . "\n";
    echo "Items in page: " . count($result['data']) . "\n";

    foreach ($result['data'] as $idx => $item) {
        echo "\n--- Item #{$idx} [{$item['activity_type']}] ---\n";
        echo json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
