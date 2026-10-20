<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$p = App\Models\Post::find('a0974b8e-bf01-4489-9cb3-7686ea1d4cfb');
echo 'Post exists: ' . ($p ? 'yes' : 'no') . PHP_EOL;
if ($p) {
    echo 'Initial deleted_at: ' . var_export($p->deleted_at, true) . PHP_EOL;
    echo 'Initial is_deleted: ' . var_export($p->is_deleted, true) . PHP_EOL;
    echo 'Initial is_active: ' . var_export($p->is_active, true) . PHP_EOL;
    $res = $p->delete();
    echo 'delete() return: ' . var_export($res, true) . PHP_EOL;
    echo 'After delete trashed: ' . var_export($p->trashed(), true) . PHP_EOL;
    echo 'After delete deleted_at: ' . var_export($p->deleted_at, true) . PHP_EOL;
    $fresh = App\Models\Post::withTrashed()->find('a0974b8e-bf01-4489-9cb3-7686ea1d4cfb');
    echo 'DB fresh deleted_at: ' . var_export($fresh->deleted_at, true) . PHP_EOL;
    echo 'DB fresh is_deleted: ' . var_export($fresh->is_deleted, true) . PHP_EOL;
}
