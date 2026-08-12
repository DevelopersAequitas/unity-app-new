<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

use App\Models\AdminUser;
use App\Services\Admin\PermissionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app->make(Kernel::class)->bootstrap();

$user = DB::table('admin_users')->where('email', 'urvashi@gmail.com')->first();
if ($user) {
    echo 'Found user: '.$user->name.' ('.$user->id.")\n";
    $adminUser = AdminUser::find($user->id);
    $service = app(PermissionService::class);

    echo 'canAccessRoute result: ';
    var_dump($service->canAccessRoute($adminUser, 'admin.rbac.lifespan.index'));
} else {
    echo "User not found\n";
}
