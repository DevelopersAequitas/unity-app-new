<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$templates = App\Models\WhatsappTemplate::all();
foreach ($templates as $t) {
    echo "ID: {$t->id} | Name: {$t->name} | Key: {$t->template_key} | Lang: {$t->language} | Status: {$t->status} | Active: {$t->is_active}\n";
    if (!empty($t->components)) {
        echo "Components: " . json_encode($t->components) . "\n";
    }
    echo "---------------------------------------------------------\n";
}
