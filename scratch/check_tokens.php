<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo 'ID Column Type: '.Schema::getColumnType('personal_access_tokens', 'id')."\n";
print_r(Schema::getColumnListing('personal_access_tokens'));
