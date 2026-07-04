<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Simulating index() controller logic ===" . PHP_EOL . PHP_EOL;

$reqs = App\Models\AccountDeletionRequest::with(['user' => fn ($q) => $q->withTrashed()])
    ->orderBy('created_at', 'desc')
    ->get();

foreach ($reqs as $req) {
    $linkedUser = $req->resolveLinkedUser();

    $userIsDeactivated = $linkedUser && $linkedUser->trashed();
    $userIsActive      = $linkedUser && !$linkedUser->trashed();

    echo "Request ID: " . $req->id . PHP_EOL;
    echo "  email col: " . ($req->email ?? 'NULL') . PHP_EOL;
    echo "  user_id:   " . ($req->user_id ?? 'NULL') . PHP_EOL;
    echo "  linked_user: " . ($linkedUser ? "FOUND id={$linkedUser->id} trashed=" . ($linkedUser->trashed() ? 'YES' : 'NO') : 'NULL') . PHP_EOL;
    echo "  userIsActive:      " . ($userIsActive ? 'YES' : 'NO') . PHP_EOL;
    echo "  userIsDeactivated: " . ($userIsDeactivated ? 'YES' : 'NO') . PHP_EOL;

    if ($userIsDeactivated) {
        echo "  BUTTON: [Activate] (green)" . PHP_EOL;
    } elseif ($userIsActive) {
        echo "  BUTTON: [Deactivate] (yellow)" . PHP_EOL;
    } else {
        echo "  BUTTON: [No Account badge] - no user found" . PHP_EOL;
    }
    echo PHP_EOL;
}
