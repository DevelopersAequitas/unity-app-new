<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$postId = 'a0974b8e-bf01-4489-9cb3-7686ea1d4cfb';
$post = Post::withTrashed()->find($postId);

if (!$post) {
    echo "Post not found!\n";
    exit(1);
}

// 1. Reset post attributes
$post->deleted_at = null;
if (Schema::hasColumn('posts', 'is_deleted')) {
    $post->is_deleted = false;
}
if (Schema::hasColumn('posts', 'is_active')) {
    $post->is_active = true;
}
if (Schema::hasColumn('posts', 'deactivation_remarks')) {
    $post->deactivation_remarks = null;
}
$post->save();

// 2. Remove reversal coin ledger entry and restore coins balance
$coinReversals = DB::table('coins_ledger')
    ->where('user_id', $post->user_id)
    ->where('amount', '<', 0)
    ->where(function ($q) use ($postId) {
        $q->where('reference_id', $postId)
          ->orWhere('reference', 'LIKE', "%{$postId}%");
    })
    ->get();

$reversedCoinsTotal = 0;
foreach ($coinReversals as $cr) {
    $reversedCoinsTotal += abs((int)$cr->amount);
    DB::table('coins_ledger')->where('transaction_id', $cr->transaction_id)->delete();
}

if ($reversedCoinsTotal > 0) {
    $user = User::find($post->user_id);
    if ($user) {
        $user->coins_balance = (int)($user->coins_balance ?? 0) + $reversedCoinsTotal;
        $user->save();
        echo "Restored {$reversedCoinsTotal} coins to user {$user->display_name}. New balance: {$user->coins_balance}\n";
    }
}

// 3. Remove reversal life impact history entry and restore points
$impactReversals = DB::table('life_impact_histories')
    ->where('user_id', $post->user_id)
    ->where('impact_value', '<', 0)
    ->where(function ($q) use ($postId) {
        $q->where('activity_id', $postId)
          ->orWhere('meta', 'LIKE', "%{$postId}%");
    })
    ->get();

$reversedImpactTotal = 0;
foreach ($impactReversals as $ir) {
    $reversedImpactTotal += abs((int)$ir->impact_value);
    DB::table('life_impact_histories')->where('id', $ir->id)->delete();
}

if ($reversedImpactTotal > 0) {
    $user = User::find($post->user_id);
    if ($user && isset($user->life_impact_point)) {
        $user->life_impact_point = (int)($user->life_impact_point ?? 0) + $reversedImpactTotal;
        $user->save();
    }
    echo "Restored {$reversedImpactTotal} impact points to user.\n";
}

echo "Post {$postId} successfully reset to ACTIVE with rewards available for reversal test!\n";
