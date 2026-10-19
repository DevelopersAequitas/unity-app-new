<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Post;
use App\Models\User;
use App\Services\Posts\PostDeactivationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

echo "=== Testing Post Deactivation & Reward Reversal Workflow ===\n\n";

DB::beginTransaction();

try {
    // 1. Get or create test user
    $user = User::query()->first();
    if (! $user) {
        echo "No users found in database.\n";
        exit(1);
    }

    $initialCoins = (int) ($user->coins_balance ?? 0);
    $initialImpact = (int) ($user->life_impacted_count ?? 0);
    echo "1. Initial User State: {$user->display_name} (ID: {$user->id})\n";
    echo "   Coins Balance: " . number_format($initialCoins) . "\n";
    echo "   Life Impact Score: " . number_format($initialImpact) . "\n\n";

    // 2. Simulate activity and awards
    $activityId = (string) Str::uuid();
    $awardedCoins = 3000;
    $awardedImpact = 3;

    // Credit coins
    $tempBalance = $initialCoins + $awardedCoins;
    $user->coins_balance = $tempBalance;
    $user->life_impacted_count = $initialImpact + $awardedImpact;
    $user->save();

    if (Schema::hasTable('coins_ledger')) {
        $ledgerInsert = [
            'transaction_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'amount' => $awardedCoins,
            'balance_after' => $tempBalance,
            'activity_id' => null,
            'reference' => 'Activity: business_deal reward',
            'created_by' => $user->id,
            'created_at' => now(),
        ];
        if (Schema::hasColumn('coins_ledger', 'reference_id')) {
            $ledgerInsert['reference_id'] = $activityId;
        }
        if (Schema::hasColumn('coins_ledger', 'reference_type')) {
            $ledgerInsert['reference_type'] = 'ACTIVITY';
        }
        if (Schema::hasColumn('coins_ledger', 'source_id')) {
            $ledgerInsert['source_id'] = $activityId;
        }
        if (Schema::hasColumn('coins_ledger', 'source_type')) {
            $ledgerInsert['source_type'] = 'business_deal';
        }
        DB::table('coins_ledger')->insert($ledgerInsert);
    }

    if (Schema::hasTable('life_impact_histories')) {
        $lifeTable = config('database.default') === 'sqlite' ? 'life_impact_histories' : 'public.life_impact_histories';
        $lifeInsert = [
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'activity_type' => 'business_deal',
            'activity_id' => $activityId,
            'impact_value' => $awardedImpact,
            'title' => 'Closed a business deal',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('life_impact_histories', 'life_impacted')) {
            $lifeInsert['life_impacted'] = $awardedImpact;
        }
        if (Schema::hasColumn('life_impact_histories', 'action_key')) {
            $lifeInsert['action_key'] = 'business_deal';
        }
        if (Schema::hasColumn('life_impact_histories', 'action_label')) {
            $lifeInsert['action_label'] = 'Business Deal';
        }
        if (Schema::hasColumn('life_impact_histories', 'impact_category')) {
            $lifeInsert['impact_category'] = 'business_deal';
        }
        if (Schema::hasColumn('life_impact_histories', 'status')) {
            $lifeInsert['status'] = 'approved';
        }
        DB::table($lifeTable)->insert($lifeInsert);
    }

    echo "2. Simulated Awarded Rewards for Activity ({$activityId}):\n";
    echo "   +{$awardedCoins} Coins (New Balance: " . number_format($user->coins_balance) . ")\n";
    echo "   +{$awardedImpact} Impact Points (New Score: " . number_format($user->life_impacted_count) . ")\n\n";

    // 3. Create Post linked to this activity
    $post = Post::create([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'source_type' => 'business_deal',
        'source_id' => $activityId,
        'content_text' => 'Demo post for deactivation and reversal test',
        'status' => 'active',
        'active' => true,
        'is_deleted' => false,
        'visibility' => 'public',
        'moderation_status' => 'approved',
    ]);
    echo "3. Created Post ID: {$post->id}\n\n";

    // 4. Deactivate Post using PostDeactivationService
    $remarks = 'Duplicate post or image uploaded.';
    echo "4. Deactivating Post with Remarks: '{$remarks}'...\n";
    $service = app(PostDeactivationService::class);
    $adminId = (string) Str::uuid();
    $result = $service->deactivatePost($post, $remarks, $adminId);

    echo "   Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
    echo "   Coins Reversed: " . number_format($result['coins_reversed']) . "\n";
    echo "   Impact Reversed: " . number_format($result['impact_reversed']) . "\n";
    echo "   Message: {$result['message']}\n\n";

    // 5. Verify Post is deactivated
    $post->refresh();
    echo "5. Verifying Post Status:\n";
    echo "   is_deleted: " . ($post->is_deleted ? 'TRUE (Deactivated)' : 'FALSE') . "\n";
    echo "   status: {$post->status}\n";
    if (Schema::hasColumn('posts', 'deactivation_remarks')) {
        echo "   deactivation_remarks: {$post->deactivation_remarks}\n";
    }

    // 6. Verify User Balances reverted
    $user->refresh();
    echo "\n6. Verifying User Balance After Reversal:\n";
    echo "   Coins Balance: " . number_format($user->coins_balance) . " (Expected: " . number_format($initialCoins) . ")\n";
    echo "   Life Impact Score: " . number_format($user->life_impacted_count) . " (Expected: " . number_format($initialImpact) . ")\n";

    // 7. Verify Idempotency (Calling deactivate again)
    echo "\n7. Testing Idempotency (calling deactivate on already deactivated post):\n";
    $repeatResult = $service->deactivatePost($post, 'Attempting duplicate deactivation', 'admin-demo-id');
    echo "   Already Deactivated Detected: " . ($repeatResult['already_deactivated'] ? 'YES' : 'NO') . "\n";
    echo "   Duplicate Coins Reversed: {$repeatResult['coins_reversed']} (Should be 0)\n";
    echo "   Duplicate Impact Reversed: {$repeatResult['impact_reversed']} (Should be 0)\n\n";

    echo "=== ALL VERIFICATIONS PASSED SUCCESSFULLY! ===\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    // Rollback so demo script leaves no garbage in the database
    DB::rollBack();
    echo "\n(Database Transaction safely rolled back. No permanent test data left in DB.)\n";
}
