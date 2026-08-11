<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use App\Services\Admin\PermissionService;

// Bootstrap Laravel
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = DB::table('admin_users')->where('email', 'urvashi@gmail.com')->first();
if ($user) {
    echo "Found user: " . $user->name . " (" . $user->id . ")\n";
    $adminUser = \App\Models\AdminUser::find($user->id);
    $service = app(PermissionService::class);
    
    echo "canAccessRoute result: ";
    var_dump($service->canAccessRoute($adminUser, 'admin.rbac.lifespan.index'));
} else {
    echo "User not found\n";
}
