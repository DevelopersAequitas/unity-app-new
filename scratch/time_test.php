<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "1. Current setup (database timezone is UTC):\n";
$dbTime = Illuminate\Support\Facades\DB::select('SELECT NOW() as now')[0]->now;
echo 'PostgreSQL NOW(): '.$dbTime."\n";
$post = App\Models\Post::latest()->first();
if ($post) {
    echo 'Post raw created_at: '.$post->getRawOriginal('created_at')."\n";
    echo 'Post casted created_at: '.$post->created_at->toIso8601String()."\n";
}

$msg = App\Models\CircleChatMessage::latest()->first();
if ($msg) {
    echo 'Message raw created_at: '.$msg->getRawOriginal('created_at')."\n";
    echo 'Message casted created_at: '.$msg->created_at->toIso8601String()."\n";
}

echo "\n2. Testing with database session timezone set to 'Asia/Kolkata':\n";
Illuminate\Support\Facades\DB::statement("SET TIME ZONE 'Asia/Kolkata'");
$dbTimeKolkata = Illuminate\Support\Facades\DB::select('SELECT NOW() as now')[0]->now;
echo 'PostgreSQL NOW(): '.$dbTimeKolkata."\n";

// Refresh models from DB after setting session timezone
if ($post) {
    $postFresh = App\Models\Post::find($post->id);
    echo 'Post fresh raw created_at: '.$postFresh->getRawOriginal('created_at')."\n";
    // We parse it manually because Eloquent might cache the attribute or use connection default
    $c = Carbon\Carbon::parse($postFresh->getRawOriginal('created_at'));
    echo 'Post fresh parsed created_at: '.$c->toIso8601String()."\n";
}

if ($msg) {
    $msgFresh = App\Models\CircleChatMessage::find($msg->id);
    echo 'Message fresh raw created_at: '.$msgFresh->getRawOriginal('created_at')."\n";
    $c = Carbon\Carbon::parse($msgFresh->getRawOriginal('created_at'));
    echo 'Message fresh parsed created_at: '.$c->toIso8601String()."\n";
}
