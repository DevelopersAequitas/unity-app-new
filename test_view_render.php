<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Post;

session()->flash('success', 'Test Success Message 12345');
session()->save();

$view = view('admin.posts.index', [
    'posts' => Post::paginate(10),
    'filters' => [],
    'circles' => [],
    'visibilities' => [],
    'moderationOptions' => [],
    'errors' => new \Illuminate\Support\ViewErrorBag(),
])->render();

echo "Has session('success'): " . var_export(session('success'), true) . PHP_EOL;
echo "Found in view output: " . (strpos($view, 'Test Success Message 12345') !== false ? 'YES' : 'NO') . PHP_EOL;
if (strpos($view, 'Test Success Message 12345') !== false) {
    preg_match('/<div class="alert alert-success[^>]*>.*?<\/div>/s', $view, $m);
    echo "Alert markup: " . ($m[0] ?? '') . PHP_EOL;
}
