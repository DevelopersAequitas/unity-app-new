<?php

use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$userId = '8963c869-0f37-41f4-9b74-4fc82aa7d068';

// Detect columns
$columns = DB::select("select column_name from information_schema.columns where table_name = 'user_push_tokens'");
echo "Columns in user_push_tokens:\n";
foreach ($columns as $c) {
    echo '- '.$c->column_name."\n";
}

echo "\nTokens for $userId:\n";
try {
    $tokens = DB::select('select * from user_push_tokens where user_id = ?', [$userId]);
    foreach ($tokens as $t) {
        echo json_encode($t, JSON_PRETTY_PRINT)."\n";
    }
    if (empty($tokens)) {
        echo "0 tokens found for user_id.\n";
    }
} catch (\Exception $e) {
    echo 'user_id check failed: '.$e->getMessage()."\n";

    // Fallback to usr_id
    try {
        $tokens = DB::select('select * from user_push_tokens where usr_id = ?', [$userId]);
        foreach ($tokens as $t) {
            echo json_encode($t, JSON_PRETTY_PRINT)."\n";
        }
        if (empty($tokens)) {
            echo "0 tokens found for usr_id.\n";
        }
    } catch (\Exception $e2) {
        echo 'usr_id check failed: '.$e2->getMessage()."\n";
    }
}
